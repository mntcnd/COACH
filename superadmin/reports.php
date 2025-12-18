<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// --- FIX 1: TIMEZONE CONFIGURATION ---
// Set default timezone to Manila (Asia/Manila is UTC+8) for accurate time calculation.
date_default_timezone_set('Asia/Manila');

// Standard session check for a super admin user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: ../login.php");
    exit();
}

// Use your standard database connection
require '../connection/db_connection.php';

// --- INITIALIZE VARIABLES ---
$currentUser = $_SESSION['username'];
$reports = [];
$archivedPosts = [];
// Renamed POST variable to avoid collision with 'duration_type' in the custom block
$adminAction = $_POST['admin_action'] ?? ''; 
$redirect = false;

// --- ADMIN ACTION HANDLERS FOR REPORTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action 1: Dismiss a report
    if ($adminAction === 'dismiss_report' && isset($_POST['report_id'])) {
        $reportId = intval($_POST['report_id']);
        $stmt = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE report_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $stmt->close();
            $redirect = true;
        }
    }

    // Action 2: Archive the post AND dismiss the report (UPDATED)
    if ($adminAction === 'archive_and_dismiss' && isset($_POST['post_id'], $_POST['report_id'])) {
        $postId = intval($_POST['post_id']);
        $reportId = intval($_POST['report_id']);
        $archiveReason = trim($_POST['archive_reason'] ?? 'Violation found in reported post.');
        $archivedBy = $currentUser;
        $archivedAt = (new DateTime())->format('Y-m-d H:i:s');
        
        $conn->begin_transaction();
        try {
            // Update post status to 'archived' instead of deleting
            $stmt1 = $conn->prepare("UPDATE general_forums SET status = 'archived', archived_by = ?, archived_at = ?, archive_reason = ? WHERE id = ?");
            if ($stmt1) {
                $stmt1->bind_param("sssi", $archivedBy, $archivedAt, $archiveReason, $postId);
                $stmt1->execute();
                $stmt1->close();
            }

            // Resolve the report
            $stmt2 = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE report_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("i", $reportId);
                $stmt2->execute();
                $stmt2->close();
            }
            
            $conn->commit();
            $redirect = true;
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            error_log("Transaction failed: " . $exception->getMessage());
        }
    }

    // Action 3: Restore archived post
    if ($adminAction === 'restore_post' && isset($_POST['post_id'])) {
        $postId = intval($_POST['post_id']);
        
        $stmt = $conn->prepare("UPDATE general_forums SET status = 'active', archived_by = NULL, archived_at = NULL, archive_reason = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $postId);
            $stmt->execute();
            $stmt->close();
            $redirect = true;
        }
    }

    // Action 4: Permanently delete archived post
    if ($adminAction === 'permanently_delete' && isset($_POST['post_id'])) {
        $postId = intval($_POST['post_id']);
        
        $stmt = $conn->prepare("DELETE FROM general_forums WHERE id = ? AND status = 'archived'");
        if ($stmt) {
            $stmt->bind_param("i", $postId);
            $stmt->execute();
            $stmt->close();
            $redirect = true;
        }
    }

    // Action 5: Ban the user with duration
    if ($adminAction === 'ban_user' && isset($_POST['username_to_ban'])) {
        $usernameToBan = trim($_POST['username_to_ban']);
        $banReason = trim($_POST['ban_reason'] ?? 'Violation found in reported post.');
        // FIX: The custom duration radio button sends 'custom'. We need the unit (minutes/hours/days).
        // I've renamed the select element in HTML to 'duration_unit' to clarify its role.
        $durationType = $_POST['duration_type'] ?? 'permanent'; // 'permanent' or unit ('minutes', 'hours', 'days')
        $durationValue = intval($_POST['duration_value'] ?? 0);
        
        $banUntilDatetime = null;
        // Get current time in Manila for the ban creation
        $banCreatedDatetime = (new DateTime())->format('Y-m-d H:i:s'); 
        $durationText = 'Permanent';
        $banType = 'Permanent';

        // Calculate unban datetime based on duration type
        if ($durationType !== 'permanent' && $durationValue > 0) {
            $currentDatetime = new DateTime(); 
            $banType = 'Temporary';
            
            // Check the submitted duration type, which is now the unit name
            switch ($durationType) {
                case 'minutes':
                    $currentDatetime->modify("+{$durationValue} minutes");
                    $durationText = $durationValue . ' ' . ($durationValue == 1 ? 'minute' : 'minutes');
                    break;
                case 'hours':
                    $currentDatetime->modify("+{$durationValue} hours");
                    $durationText = $durationValue . ' ' . ($durationValue == 1 ? 'hour' : 'hours');
                    break;
                case 'days':
                    $currentDatetime->modify("+{$durationValue} days");
                    $durationText = $durationValue . ' ' . ($durationValue == 1 ? 'day' : 'days');
                    break;
                // If durationType is 'custom', which shouldn't happen with the JS fix, default to Permanent
                default: 
                    $banType = 'Permanent'; 
                    $durationText = 'Permanent';
                    break;
            }
            
            if ($banType === 'Temporary') {
                $banUntilDatetime = $currentDatetime->format('Y-m-d H:i:s');
            }
        }
        
        // Check if user is already banned
        $check_stmt = $conn->prepare("SELECT ban_id FROM banned_users WHERE username = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("s", $usernameToBan);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows == 0) {
                // Insert new ban with duration
                // Note: The ban_until column should be NULL for permanent bans.
                $stmt = $conn->prepare("INSERT INTO banned_users (username, banned_by_admin, reason, ban_until, ban_duration_text, ban_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssssss", $usernameToBan, $currentUser, $banReason, $banUntilDatetime, $durationText, $banType, $banCreatedDatetime);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            $check_stmt->close();
            $redirect = true;
        }
    }

    if ($redirect) {
        header("Location: reports.php");
        exit();
    }
}

// --- DATA FETCHING: Get all PENDING reports ---
$reportQuery = "SELECT
                    r.report_id, r.reported_by_username, r.reason AS report_reason, r.report_date,
                    c.id AS post_id, 
                    u.username AS post_author_username, 
                    c.display_name AS post_author_displayname, 
                    c.title, c.message, c.file_path, c.user_icon
                FROM reports AS r
                JOIN general_forums AS c ON r.post_id = c.id
                JOIN users AS u ON c.user_id = u.user_id
                WHERE r.status = 'pending'
                ORDER BY r.report_date DESC";

$stmt = $conn->prepare($reportQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
    }
    $stmt->close();
}

// --- DATA FETCHING: Get all ARCHIVED posts ---
$archivedQuery = "SELECT
                    c.id AS post_id,
                    u.username AS post_author_username,
                    c.display_name AS post_author_displayname,
                    c.title, c.message, c.file_path, c.user_icon,
                    c.archived_by, c.archived_at, c.archive_reason,
                    c.timestamp AS post_date
                FROM general_forums AS c
                JOIN users AS u ON c.user_id = u.user_id
                WHERE c.status = 'archived'
                ORDER BY c.archived_at DESC";

$stmt = $conn->prepare($archivedQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $archivedPosts[] = $row;
        }
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Content | SuperAdmin</title>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="css/report.css"/> 
    <link rel="stylesheet" href="css/dashboard.css"/>
    <link rel="stylesheet" href="css/navigation.css"/>

<style>
/* Base Styles adapted from banned-users.php */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: #f5f7fa;
    color: #333;
}

.dashboard .top .navToggle {
    font-size: 24px;
    cursor: pointer;
    color: #000000ff; /* Primary Purple */
}

.admin-container {
    padding: 30px;
    max-width: 1400px;
    /* Adjust margin-top to account for fixed top bar */
    margin: 70px auto 30px auto; 
    width: 100%;
}

/* REPORTS.PHP SPECIFIC HEADER/SECTION */
.admin-controls-header h2 {
    color: #1a1a1a;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 15px;
    margin-bottom: 20px;
    font-weight: 700;
    font-size: 28px;
}

/* Tab Navigation Styles (Unique to reports.php) */
.tab-container {
    margin: 20px 0;
    border-bottom: 2px solid #e0e0e0;
}

.tab-buttons {
    display: flex;
    gap: 0;
}

.tab-button {
    padding: 12px 25px;
    background: #f8f9fa;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    color: #6c757d;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    margin-right: 5px;
    border-radius: 6px 6px 0 0;
}

.tab-button i {
    margin-right: 8px;
}

.tab-button:hover {
    color: #000000ff; /* Primary Purple */
    background-color: #e9ecef;
}

.tab-button.active {
    color: #6a0dad; /* Primary Purple */
    border-bottom-color: #6a0dad;
    background-color: #fff;
}

.tab-content {
    padding: 20px 0;
    animation: fadeIn 0.4s ease-out;
    display: none;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Report Card & Content Styles (Unique to reports.php) */
.report-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 20px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.3s ease;
}

.report-card:hover {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.report-info, .archive-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 5px solid #ffc107; /* Yellow border for reports */
}

.archive-info {
    border-left-color: #6c757d; /* Gray border for archived */
}

.report-info p, .archive-info p {
    margin: 5px 0;
    font-size: 15px;
}

.report-reason {
    font-weight: 500;
    color: #dc3545; /* Highlight reason in red */
}

.archived-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #6c757d;
    color: white;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
    vertical-align: middle;
}

.reported-content-wrapper {
    margin-top: 15px;
    border: 1px dashed #ced4da;
    padding: 15px;
    border-radius: 6px;
}

.reported-content-wrapper strong {
    display: block;
    margin-bottom: 10px;
    color: #000000ff; /* Primary Purple */
    font-weight: 600;
}

/* Post Content Container (Nested) */
.post-container {
    background: #fff;
    border: 1px solid #e9ecef;
    padding: 15px;
    border-radius: 6px;
}

.post-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
    border: 2px solid #e0e0e0;
}

.post-author {
    font-weight: 600;
    color: #333;
}

.post-title {
    font-size: 18px;
    font-weight: 700;
    margin: 5px 0 10px 0;
    color: #000000ff; /* Primary Purple */
}

.post-content {
    font-size: 14px;
    line-height: 1.6;
    color: #555;
    word-wrap: break-word;
}

.post-content img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin-top: 10px;
    border: 1px solid #eee;
}

/* Action Buttons (Reports Specific) */
.report-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s;
    color: white;
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    text-align: center;
    padding-bottom: 12px;
}

.action-btn:hover {
    transform: translateY(-1px);
}

/* Action specific colors */
.action-btn.dismiss:hover {
    background-color: #218838;
}

.action-btn.archive:hover {
    background-color: #5a6268;
}

.action-btn.ban:hover {
    background-color: #c82333;
}

.action-btn.restore{
    color: #218838;
}

.action-btn.restore:hover {
    background-color: #0056b3;
}
.action-btn.permanent-delete {
    color: #dc3545;
}

.action-btn.permanent-delete:hover {
    background-color: #c82333;
}

/* Modal Styles (Unique to reports.php) */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    width: 90%;
    max-width: 550px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 15px;
    margin-bottom: 15px;
}

.modal-header h2 {
    margin: 0;
    color: #000000ff; /* Primary Purple */
    font-size: 24px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
}

.modal form p {
    margin: 10px 0 20px 0;
    font-size: 15px;
}

.modal form strong {
    color: #dc3545;
}

.modal form label {
    display: block;
    margin: 15px 0 5px 0;
    font-weight: 600;
    color: #333;
}

.ban-modal-reason,
.archive-modal-reason {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    transition: border-color 0.2s;
}

.ban-modal-reason:focus,
.archive-modal-reason:focus {
    border-color: #000000ff; /* Primary Purple */
    outline: none;
}

.post-btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 6px;
    margin-top: 20px;
    font-size: 16px;
    font-weight: 700;
    color: white;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s;
}

.post-btn:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}

/* Ban Duration Styles */
.ban-duration-section {
    margin: 20px 0;
    padding: 15px;
    background: #f3f0f6; /* Light purple background for emphasis */
    border-radius: 8px;
    border: 1px solid #dcd4e8;
}

.duration-options {
    display: flex;
    gap: 10px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.duration-option {
    flex: 1;
}

.duration-option label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px;
    border: 2px solid #dcd4e8;
    border-radius: 6px;
    transition: all 0.3s;
    background: #fff;
    margin: 0; /* Override default label margin */
}

.duration-option input[type="radio"] {
    display: none; /* Hide default radio button */
}

.duration-option label:after {
    content: '';
    width: 16px;
    height: 16px;
    border: 2px solid #dcd4e8;
    border-radius: 50%;
    background-color: white;
    transition: background-color 0.2s, border-color 0.2s;
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
}

.duration-option label:has(input[type="radio"]:checked) {
    border-color: #6a0dad;
    background: #e6e0f0;
    font-weight: 600;
}

.duration-option label:has(input[type="radio"]:checked):after {
    background-color: #6a0dad;
    border-color: #6a0dad;
}

.duration-option label {
    position: relative; /* For custom radio button positioning */
    padding-left: 35px; /* Space for the custom radio button */
}

.custom-duration-input {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: #fff;
    border-radius: 6px;
    border: 1px solid #dcd4e8;
}

.custom-duration-input.active {
    display: block;
}

.input-group {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-top: 10px;
}

.input-group input[type="number"],
.input-group select {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    background: white;
    height: 40px;
}

.input-group input[type="number"] {
    flex: 1;
    min-width: 80px;
}

.input-group select {
    min-width: 120px;
}

/* Media Queries */
@media (max-width: 768px) {
    .admin-container {
        padding: 16px;
    }

    .report-card, .section {
        padding: 16px;
    }

    .admin-controls-header h2 {
        font-size: 22px;
    }

    .action-btn {
        padding: 8px 12px;
        margin-bottom: 8px;
        font-size: 12px;
    }

    .report-actions {
        flex-direction: column;
        gap: 8px;
    }

    .action-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>

    <nav>
    <div class="nav-top">
        <div class="logo">
        <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
        <div class="logo-name">COACH</div>
        </div>

        <div class="admin-profile">
        <img src="<?php echo htmlspecialchars($_SESSION['superadmin_icon']); ?>" alt="SuperAdmin Profile Picture" />
        <div class="admin-text">
            <span class="admin-name">
            <?php echo htmlspecialchars($_SESSION['superadmin_name']); ?>
            </span>
            <span class="admin-role">SuperAdmin</span>
        </div>
        <a href="edit_profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
            <ion-icon name="create-outline" class="verified-icon"></ion-icon>
        </a>
        </div>
    </div>

    <div class="menu-items">
        <ul class="navLinks">
            <li class="navList">
                <a href="dashboard.php"> <ion-icon name="home-outline"></ion-icon>
                    <span class="links">Home</span>
                </a>
            </li>
            <li class="navList"><a href="moderators.php"><ion-icon name="lock-closed-outline"></ion-icon><span class="links">Moderators</span></a></li>

            <li class="navList">
                <a href="manage_mentees.php"> <ion-icon name="person-outline"></ion-icon>
                    <span class="links">Mentees</span>
                </a>
            </li>
            <li class="navList">
                <a href="manage_mentors.php"> <ion-icon name="people-outline"></ion-icon>
                    <span class="links">Mentors</span>
                </a>
            </li>
            <li class="navList">
                <a href="courses.php"> <ion-icon name="book-outline"></ion-icon>
                    <span class="links">Courses</span>
                </a>
            </li>
            <li class="navList">
                <a href="manage_session.php"> <ion-icon name="calendar-outline"></ion-icon>
                    <span class="links">Sessions</span>
                </a>
            </li>
            <li class="navList"> <a href="feedbacks.php"> <ion-icon name="star-outline"></ion-icon>
                    <span class="links">Feedback</span>
                </a>
            </li>
            <li class="navList">
                <a href="channels.php"> <ion-icon name="chatbubbles-outline"></ion-icon>
                    <span class="links">Channels</span>
                </a>
            </li>
            <li class="navList">
                <a href="activities.php"> <ion-icon name="clipboard"></ion-icon>
                    <span class="links">Activities</span>
                </a>
            </li>
            <li class="navList">
                <a href="resource.php"> <ion-icon name="library-outline"></ion-icon>
                    <span class="links">Resource Library</span>
                </a>
            </li>
            <li class="navList active">
                <a href="reports.php"><ion-icon name="folder-outline"></ion-icon>
                    <span class="links">Reported Posts</span>
                </a>
            </li>
            <li class="navList">
                <a href="banned-users.php"><ion-icon name="person-remove-outline"></ion-icon>
                    <span class="links">Banned Users</span>
                </a>
            </li>
        </ul>

    <ul class="bottom-link">
    <li class="logout-link">
        <a href="#" onclick="confirmLogout(event)" style="color: white; text-decoration: none; font-size: 18px;">
        <ion-icon name="log-out-outline"></ion-icon>
        Logout
        </a>
    </li>
    </ul>
    </div>
    </nav>

    <section class="dashboard">
        <div class="top">
            <ion-icon class="navToggle" name="menu-outline"></ion-icon>
        <img src="../uploads/img/logo.png" alt="Logo"> </div>

    <div class="admin-container">
        <div class="admin-controls-header">
            <h2>Content Moderation</h2>
        </div>

        <div class="tab-container">
            <div class="tab-buttons">
                <button id="reports-tab-button" class="tab-button active" onclick="switchTab(event, 'reports')">
                    <i class="fa fa-flag"></i> Pending Reports (<?php echo count($reports); ?>)
                </button>
                <button id="archived-tab-button" class="tab-button" onclick="switchTab(event, 'archived')">
                    <i class="fa fa-archive"></i> Archived Posts (<?php echo count($archivedPosts); ?>)
                </button>
            </div>
        </div>

        <div id="reports-tab" class="tab-content active">
            <?php if (empty($reports)): ?>
                <p>There are no pending reports to review. Good job! 🎉</p>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="report-info">
                            <p><strong>Reported By:</strong> <?php echo htmlspecialchars($report['reported_by_username']); ?></p>
                            <p><strong>Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($report['report_date'])); ?></p>
                            <p><strong>Reason:</strong> <span class="report-reason"><?php echo htmlspecialchars($report['report_reason']); ?></span></p>
                        </div>

                        <div class="reported-content-wrapper">
                            <strong>Content in Question:</strong>
                            <div class="post-container">
                                <div class="post-header">
                                    <img src="<?php echo htmlspecialchars(!empty($report['user_icon']) ? $report['user_icon'] : '../img/default-user.png'); ?>" alt="Author Icon" class="user-avatar">
                                    <div class="post-author-details">
                                        <div class="post-author"><?php echo htmlspecialchars($report['post_author_displayname']); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($report['title'])): ?>
                                    <div class="post-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                <?php endif; ?>
                                <div class="post-content">
                                    <?php echo htmlspecialchars($report['message']); ?>
                                    <br>
                                    <?php if (!empty($report['file_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($report['file_path']); ?>" alt="Post Image">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-actions">
                            <form action="reports.php" method="POST" onsubmit="return confirm('Are you sure you want to dismiss this report? This action will mark the report as resolved without taking action on the post itself.');" style="display:inline;">
                                <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($report['report_id']); ?>">
                                <input type="hidden" name="admin_action" value="dismiss_report">
                                <button type="submit" class="action-btn dismiss"><i class="fa fa-check"></i> Dismiss Report</button>
                            </form>
                            <button class="action-btn archive" onclick="openArchiveModal(<?php echo htmlspecialchars($report['report_id']); ?>, <?php echo htmlspecialchars($report['post_id']); ?>)">
                                <i class="fa fa-archive"></i> Archive Post & Resolve
                            </button>
                            <button class="action-btn ban" onclick="openBanModal('<?php echo htmlspecialchars($report['post_author_username']); ?>')">
                                <i class="fa fa-ban"></i> Ban User
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="archived-tab" class="tab-content">
            <?php if (empty($archivedPosts)): ?>
                <p>There are no archived posts.</p>
            <?php else: ?>
                <?php foreach ($archivedPosts as $archived): ?>
                    <div class="report-card">
                        <div class="archive-info">
                            <p><strong>Archived By:</strong> <?php echo htmlspecialchars($archived['archived_by']); ?> <span class="archived-badge">ARCHIVED</span></p>
                            <p><strong>Archived On:</strong> <?php echo date("F j, Y, g:i a", strtotime($archived['archived_at'])); ?></p>
                            <p><strong>Reason:</strong> <span class="report-reason"><?php echo htmlspecialchars($archived['archive_reason']); ?></span></p>
                            <p><strong>Original Post Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($archived['post_date'])); ?></p>
                        </div>

                        <div class="reported-content-wrapper">
                            <strong>Archived Content:</strong>
                            <div class="post-container">
                                <div class="post-header">
                                    <img src="<?php echo htmlspecialchars(!empty($archived['user_icon']) ? $archived['user_icon'] : '../img/default-user.png'); ?>" alt="Author Icon" class="user-avatar">
                                    <div class="post-author-details">
                                        <div class="post-author"><?php echo htmlspecialchars($archived['post_author_displayname']); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($archived['title'])): ?>
                                    <div class="post-title"><?php echo htmlspecialchars($archived['title']); ?></div>
                                <?php endif; ?>
                                <div class="post-content">
                                    <?php echo htmlspecialchars($archived['message']); ?>
                                    <br>
                                    <?php if (!empty($archived['file_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($archived['file_path']); ?>" alt="Post Image">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-actions">
                            <form action="reports.php" method="POST" onsubmit="return confirm('Are you sure you want to restore this post? It will be visible again.');" style="display:inline;">
                                <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($archived['post_id']); ?>">
                                <input type="hidden" name="admin_action" value="restore_post">
                                <button type="submit" class="action-btn restore"><i class="fa fa-undo"></i> Restore Post</button>
                            </form>
                            <form action="reports.php" method="POST" onsubmit="return confirm('This will PERMANENTLY DELETE the post from the database. This action cannot be undone. Are you absolutely sure?');" style="display:inline;">
                                <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($archived['post_id']); ?>">
                                <input type="hidden" name="admin_action" value="permanently_delete">
                                <button type="submit" class="action-btn permanent-delete"><i class="fa fa-trash"></i> Delete Permanently</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="modal-overlay" id="archive-modal-overlay" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h2>Archive Post and Resolve Report</h2>
                <button class="close-btn" onclick="closeArchiveModal()">&times;</button>
            </div>
            <form action="reports.php" method="POST" id="archiveForm">
                <input type="hidden" name="admin_action" value="archive_and_dismiss">
                <input type="hidden" id="archive-report-id" name="report_id" value="">
                <input type="hidden" id="archive-post-id" name="post_id" value="">
                
                <p>You are about to archive this post. The post will be **hidden from public view** and the report will be **resolved**.</p>
                
                <label for="archive_reason">Reason for archiving:</label>
                <textarea id="archive_reason" name="archive_reason" class="archive-modal-reason" rows="3" placeholder="Enter reason for archiving (e.g., 'Violates community standards on hate speech')." required></textarea>
                
                <button type="submit" class="post-btn" style="background-color: #6c757d;">Confirm Archive</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="ban-modal-overlay" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h2>Ban User</h2>
                <button class="close-btn" onclick="closeBanModal()">&times;</button>
            </div>
            <form action="reports.php" method="POST" id="banForm">
                <input type="hidden" name="admin_action" value="ban_user">
                <input type="hidden" id="ban-username" name="username_to_ban" value="">
                
                <p>You are about to ban user: <strong id="ban-username-display"></strong>.</p>
                
                <label for="ban_reason">Reason for ban:</label>
                <textarea id="ban_reason" name="ban_reason" class="ban-modal-reason" rows="3" placeholder="Enter reason for ban... (e.g., 'Repeat offenses, severe violation')" required></textarea>
                
                <div class="ban-duration-section">
                    <label>Ban Duration:</label>
                    <div class="duration-options">
                        <div class="duration-option">
                            <label>
                                <input type="radio" name="ban_duration_mode" value="permanent" checked onchange="toggleCustomDuration()">
                                Permanent Ban
                            </label>
                        </div>
                        <div class="duration-option">
                            <label>
                                <input type="radio" name="ban_duration_mode" value="temporary" onchange="toggleCustomDuration()">
                                Temporary Ban
                            </label>
                        </div>
                    </div>
                    
                    <div id="customDurationInput" class="custom-duration-input">
                        <label>Specify Duration:</label>
                        <div class="input-group">
                            <input type="number" name="duration_value" id="duration_value" min="1" value="1" placeholder="Enter number" required disabled>
                            <select name="duration_type" id="duration_unit" disabled> 
                                <option value="minutes">Minutes</option>
                                <option value="hours">Hours</option>
                                <option value="days" selected>Days</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="post-btn" style="background-color: #dc3545;">Confirm Ban</button>
            </form>
        </div>
    </div>
</section>

<script src="js/navigation.js"></script>
<script>
    // Nav Toggle
    const navBar = document.querySelector("nav");
    const navToggle = document.querySelector(".navToggle");
    if(navToggle) {
        navToggle.addEventListener('click', () => navBar.classList.toggle('close'));
    }

    // Tab Switching - FIXED to correctly use the event and set active button
    function switchTab(event, tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active');
        });
        
        // Show selected tab content
        document.getElementById(tabName + '-tab').classList.add('active');
        
        // Add active class to clicked button
        event.currentTarget.classList.add('active');
    }

    // Archive Modal Functions
    function openArchiveModal(reportId, postId) {
        document.getElementById('archive-report-id').value = reportId;
        document.getElementById('archive-post-id').value = postId;
        document.getElementById('archive-modal-overlay').style.display = 'flex';
        document.getElementById('archiveForm').reset();
    }
    
    function closeArchiveModal() {
        document.getElementById('archive-modal-overlay').style.display = 'none';
    }

    // Archive Form Validation
    document.getElementById('archiveForm').addEventListener('submit', function(e) {
        const reason = document.getElementById('archive_reason').value.trim();
        if (reason === '') {
            e.preventDefault();
            alert('Please provide a reason for archiving this post.');
            return false;
        }
        return confirm('Are you sure you want to archive this post and dismiss the report?');
    });

    // Ban Modal Functions
    function openBanModal(username) {
        document.getElementById('ban-username').value = username;
        document.getElementById('ban-username-display').innerText = username;
        document.getElementById('ban-modal-overlay').style.display = 'flex';
        
        // Reset form and duration inputs
        document.getElementById('banForm').reset();
        document.querySelector('input[name="ban_duration_mode"][value="permanent"]').checked = true;
        toggleCustomDuration(); // Set initial state (permanent: hide custom)
    }
    
    function closeBanModal() {
        document.getElementById('ban-modal-overlay').style.display = 'none';
    }
    
    // Toggle Custom Duration - REVISED for cleaner logic and to enable/disable fields
    function toggleCustomDuration() {
        const customInput = document.getElementById('customDurationInput');
        const durationValueInput = document.getElementById('duration_value');
        const durationUnitSelect = document.getElementById('duration_unit');
        const temporaryRadio = document.querySelector('input[name="ban_duration_mode"][value="temporary"]');
        
        if (temporaryRadio.checked) {
            customInput.classList.add('active');
            durationValueInput.disabled = false;
            durationUnitSelect.disabled = false;
        } else {
            customInput.classList.remove('active');
            durationValueInput.disabled = true;
            durationUnitSelect.disabled = true;
        }
    }
    
    // Ban Form Validation - REVISED for cleaner logic
    document.getElementById('banForm').addEventListener('submit', function(e) {
        const banMode = document.querySelector('input[name="ban_duration_mode"]:checked').value;
        const reason = document.getElementById('ban_reason').value.trim();

        if (reason === '') {
            e.preventDefault();
            alert('Please provide a reason for banning this user.');
            return false;
        }

        if (banMode === 'temporary') {
            const durationValue = parseInt(document.getElementById('duration_value').value);
            const durationUnit = document.getElementById('duration_unit').value;
            
            if (isNaN(durationValue) || durationValue < 1) {
                e.preventDefault();
                alert('Please enter a valid duration value (minimum 1).');
                return false;
            }
            
            // Re-map the name of the duration unit to 'duration_type' for PHP logic
            document.getElementById('duration_unit').name = 'duration_type';
            // Set the value of the radio button to the selected unit to pass it as the duration type
            document.querySelector('input[name="ban_duration_mode"][value="temporary"]').value = durationUnit;

            return confirm(`Are you sure you want to temporarily ban this user for ${durationValue} ${durationUnit}?`);
        } else {
            // Permanent Ban
             // Ensure 'duration_type' is 'permanent' for PHP when permanent radio is checked
            document.querySelector('input[name="ban_duration_mode"][value="permanent"]').name = 'duration_type';
            document.getElementById('duration_unit').name = 'duration_unit_temp'; // Re-name the select to avoid accidental submission
            return confirm('Are you sure you want to permanently ban this user?');
        }
    });

    // Close modals when clicking outside
    document.getElementById('archive-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            closeArchiveModal();
        }
    });

    document.getElementById('ban-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBanModal();
        }
    });
</script>

<div id="logoutDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out?</p>
        <div class="dialog-buttons">
            <button id="cancelLogout" type="button">Cancel</button>
            <button id="confirmLogoutBtn" type="button">Logout</button>
        </div>
    </div>
</div>
</body>
</html>