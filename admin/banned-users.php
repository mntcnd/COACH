<?php
session_start();

// Standard session check for an admin user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Use your standard database connection
// NOTE: Ensure this file correctly sets up the $conn variable.
require '../connection/db_connection.php'; 

// --- ADMIN ACTION HANDLER: UNBAN USER & HANDLE APPEAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminAction = $_POST['admin_action'] ?? ''; // This is your existing variable name

    // 1. Handle Existing Unban Request
    if ($adminAction === 'unban_user' && isset($_POST['username_to_unban'])) {
        $usernameToUnban = $_POST['username_to_unban'];
        $stmt = $conn->prepare("DELETE FROM banned_users WHERE username = ?");
        $stmt->bind_param("s", $usernameToUnban);
        $stmt->execute();
        $_SESSION['admin_success'] = "User **" . htmlspecialchars($usernameToUnban) . "** has been successfully unbanned.";
        
        header("Location: banned-users.php"); // Refresh the page to see the change
        exit();
    }
    
    // 2. Handle Appeal Actions (Approve/Reject) (NEW LOGIC)
    elseif ($adminAction === 'handle_appeal' && isset($_POST['appeal_id'], $_POST['status'])) {
        $appeal_id = intval($_POST['appeal_id']);
        $status = $_POST['status']; // 'approved' or 'rejected'

        // Get the username associated with the appeal
        $stmt = $conn->prepare("SELECT username FROM ban_appeals WHERE id = ?");
        $stmt->bind_param("i", $appeal_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appeal_user = $result->fetch_assoc();
        $stmt->close();

        if ($appeal_user) {
            $username_to_unban = $appeal_user['username'];

            // Update the appeal status (NEW: status field in ban_appeals)
            $stmt_appeal = $conn->prepare("UPDATE ban_appeals SET status = ? WHERE id = ?");
            $stmt_appeal->bind_param("si", $status, $appeal_id);
            $stmt_appeal->execute();
            $stmt_appeal->close();

            // If approved, unban the user
            if ($status === 'approved') {
                // Delete the ban entry from the 'banned_users' table
                $stmt_unban = $conn->prepare("DELETE FROM banned_users WHERE username = ?");
                $stmt_unban->bind_param("s", $username_to_unban);
                $stmt_unban->execute();
                $stmt_unban->close();
                $_SESSION['admin_success'] = "Appeal for user **$username_to_unban** approved and user **unbanned**.";
            } else {
                $_SESSION['admin_success'] = "Appeal for user **$username_to_unban** rejected.";
            }
        } else {
            $_SESSION['admin_error'] = "Appeal not found.";
        }

        header("Location: banned-users.php");
        exit();
    }
}

// --- DATA FETCHING: GET ALL BANNED USERS ---
$banned_users = [];
// Assuming your banned_users table has columns like: username, reason, ban_until
$bannedQuery = "SELECT username, reason, ban_until FROM banned_users";
$stmt = $conn->prepare($bannedQuery);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $banned_users[] = $row;
    }
}
$stmt->close();

// --- DATA FETCHING: GET PENDING APPEALS (NEW LOGIC) ---
$appeals = [];
$stmt = $conn->prepare("SELECT id, username, reason, appeal_date FROM ban_appeals WHERE status = 'pending' ORDER BY appeal_date DESC");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $appeals[] = $row;
    }
}
$stmt->close();

// Include your header file here
// include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banned Users | Admin</title>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="css/banned-users.css"/>
    <link rel="stylesheet" href="css/dashboard.css"/>
    <link rel="stylesheet" href="css/navigation.css"/>
    <style>
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

        .dashboard {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7fa;
        }

        .dashboard .top {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
        }

        .dashboard .top img {
            height: 40px;
            width: auto;
        }

        .dashboard .top .navToggle {
            font-size: 24px;
            cursor: pointer;
            color: #6a0dad;
        }

        .admin-container {
            flex: 1;
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #1a1a1a;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        .alert {
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-success::before {
            content: "✓";
            font-weight: bold;
            font-size: 16px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-error::before {
            content: "✕";
            font-weight: bold;
            font-size: 16px;
        }

        .section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header .badge {
            background: #6a0dad;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #ddd;
        }

        .empty-state p {
            font-size: 16px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table thead {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .user-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }

        .user-table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .user-table td {
            padding: 16px;
            font-size: 14px;
            color: #555;
        }

        .user-table td strong {
            color: #1a1a1a;
            font-weight: 600;
        }

        .badge-permanent {
            display: inline-block;
            background-color: #dc3545;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-temporary {
            display: inline-block;
            background-color: #ffc107;
            color: #333;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .action-btn {
            padding: 8px 14px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background-color: #218838;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background-color: #c82333;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .btn-unban {
            background-color: #007bff;
            color: white;
        }

        .btn-unban:hover {
            background-color: #0056b3;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .reason-cell {
            max-width: 300px;
            word-wrap: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 16px;
            }

            .section {
                padding: 16px;
            }

            .page-header h1 {
                font-size: 22px;
            }

            .user-table {
                font-size: 12px;
            }

            .user-table th,
            .user-table td {
                padding: 12px 8px;
            }

            .action-btn {
                padding: 6px 10px;
                font-size: 11px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        hr {
            margin: 0;
            border: none;
            height: 1px;
            background-color: #e0e0e0;
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
                <img src="<?php echo htmlspecialchars($_SESSION['user_icon']); ?>" alt="Admin Profile Picture" />
                <div class="admin-text">
                    <span class="admin-name">
                        <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>
                    </span>
                    <span class="admin-role">Moderator</span>
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
                <li class="navList">
                    <a href="reports.php"><ion-icon name="folder-outline"></ion-icon>
                        <span class="links">Reported Posts</span>
                    </a>
                </li>
                <li class="navList active">
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
            <img src="../uploads/img/logo.png" alt="Logo">
        </div>

        <div class="admin-container" style="margin-top: 70px;">
            <div class="page-header">
                <h1>User Management</h1>
                <p>Manage banned users and handle appeal requests</p>
            </div>

            <?php 
            // Display Success/Error Messages
            if (isset($_SESSION['admin_success'])) {
                echo '<div class="alert alert-success">' . $_SESSION['admin_success'] . '</div>';
                unset($_SESSION['admin_success']);
            }
            if (isset($_SESSION['admin_error'])) {
                echo '<div class="alert alert-error">' . $_SESSION['admin_error'] . '</div>';
                unset($_SESSION['admin_error']);
            }
            ?>

            <!-- Currently Banned Users Section -->
            <div class="section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-ban"></i> Currently Banned Users
                    </h2>
                    <span class="badge"><?php echo count($banned_users); ?></span>
                </div>

                <?php if (empty($banned_users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No users are currently banned.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Reason</th>
                                    <th>Ban Until</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($banned_users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td class="reason-cell"><?php echo htmlspecialchars($user['reason']); ?></td>
                                        <td>
                                            <?php 
                                                if ($user['ban_until']) {
                                                    echo '<span class="badge-temporary">' . date("M d, Y", strtotime($user['ban_until'])) . '</span>';
                                                } else {
                                                    echo '<span class="badge-permanent">Permanent</span>';
                                                }
                                            ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form action="banned-users.php" method="POST" onsubmit="return confirm('Are you sure you want to unban this user?');" style="display: inline;">
                                                <input type="hidden" name="username_to_unban" value="<?php echo htmlspecialchars($user['username']); ?>">
                                                <input type="hidden" name="admin_action" value="unban_user">
                                                <button type="submit" class="action-btn btn-unban">
                                                    <i class="fa fa-unlock"></i> Unban
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Ban Appeals Section -->
            <div class="section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-envelope-open"></i> Pending Ban Appeals
                    </h2>
                    <span class="badge"><?php echo count($appeals); ?></span>
                </div>

                <?php if (empty($appeals)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending ban appeals at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Appeal Reason</th>
                                    <th>Appeal Date</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appeals as $appeal): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($appeal['id']); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($appeal['username']); ?></strong></td>
                                        <td class="reason-cell"><?php echo nl2br(htmlspecialchars($appeal['reason'])); ?></td>
                                        <td><?php echo date("M d, Y H:i", strtotime($appeal['appeal_date'])); ?></td>
                                        <td style="text-align: right;">
                                            <div class="action-buttons">
                                                <form action="banned-users.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="admin_action" value="handle_appeal">
                                                    <input type="hidden" name="appeal_id" value="<?php echo $appeal['id']; ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="action-btn btn-approve" 
                                                        onclick="return confirm('Are you sure you want to APPROVE this appeal and UNBAN the user?');">
                                                        <i class="fa fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form action="banned-users.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="admin_action" value="handle_appeal">
                                                    <input type="hidden" name="appeal_id" value="<?php echo $appeal['id']; ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="action-btn btn-reject"
                                                        onclick="return confirm('Are you sure you want to REJECT this appeal? This will not unban the user.');">
                                                        <i class="fa fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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

    <?php 
    // Close the database connection
    $conn->close();
    ?>
</body>
</html>