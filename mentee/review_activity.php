<?php
session_start();
date_default_timezone_set('Asia/Manila');
// ==========================================================
// --- NEW: ANTI-CACHING HEADERS (Security Block) ---
// These headers prevent the browser from caching the page, 
// forcing a server check on back button press.
// ==========================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
// ==========================================================


// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // FIX: Redirect to the correct unified login page (one directory up)
    header("Location: ../login.php");
    exit();
}

// --- FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

// SESSION CHECK
if (!isset($_SESSION['username'])) {
  // FIX: Use the correct unified login page path (one directory up)
  header("Location: ../login.php"); 
  exit();
}

$menteeUserId = $_SESSION['user_id'];
$username = $_SESSION['username']; 
$firstName = '';
$menteeIcon = '';

// Fetch First_Name and Mentee_Icon
$sql = "SELECT first_name, icon FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $menteeUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $row = $result->fetch_assoc();
  $firstName = $row['first_name'];
  $menteeIcon = $row['icon'];
}

$menteeUserId = (int)$_SESSION['user_id'];
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$view_attempt = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
if (!$activity_id) { header("Location: activities.php"); exit(); }

// fetch activity
$stmt = $conn->prepare("SELECT Activity_Title, Lesson, Questions_JSON FROM activities WHERE Activity_ID = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$activity) { echo "Activity not found"; exit(); }

// fetch all submissions for this activity/mentee
// MODIFIED: Fetching Final_Score for dual display
$stmt = $conn->prepare("SELECT Submission_ID, Attempt_Number, Score, Submitted_At, Submission_Status, Final_Score FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ? ORDER BY Attempt_Number DESC, Submitted_At DESC");
$stmt->bind_param("ii", $activity_id, $menteeUserId);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// determine which attempt to view (default: most recent)
$selected = null;
if ($view_attempt) {
    foreach ($submissions as $s) if ((int)$s['Attempt_Number'] === $view_attempt) { $selected = $s; break; }
}
if (!$selected && count($submissions)>0) $selected = $submissions[0];

// load selected answers
$questions = json_decode($activity['Questions_JSON'], true) ?: [];
$answers = [];
if ($selected) {
    $stmt = $conn->prepare("SELECT Answers_JSON FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ? AND Attempt_Number = ? LIMIT 1");
    $stmt->bind_param("iii", $activity_id, $menteeUserId, $selected['Attempt_Number']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $answers = $row ? (json_decode($row['Answers_JSON'], true) ?: []) : [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Review Activity</title>
<link rel="stylesheet" href="css/navbar.css">
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">

<style>
/* Color Palette */
:root {
    --primary-purple: #6a2c70; /* Deep Purple - Primary Button, Headings */
    --primary-hover: #9724b0ff; /* Slightly darker primary */
    --secondary-purple: #91489bff; /* Light Purple - Secondary Button (View File) */
    --secondary-hover: #60225dff; /* Slightly darker secondary */
    --text-color: #424242;        /* Default text */
    --light-bg: #fdfdff;          /* Page background */
    --container-bg: #fff;
    --border-color: #E1BEE7;
    --flat-line-color: #EEEEEE;
    --correct-color: #2e7d32;
    --wrong-color: #c62828;
}

/* Base styles */
body { 
    margin: 0; 
    padding: 0;
    background: var(--light-bg); 
    font-family: "Poppins", sans-serif; 
    color: var(--text-color); 
    min-height: 100vh;
}

.review-wrapper { 
    max-width: 1000px; /* Matched the old .container width */
    margin: 80px auto; 
    padding: 30px; 
    background: var(--container-bg); 
    border-radius: 14px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.08); /* Updated shadow */
}

/* Header and Panel Layout */
.review-header { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    gap:20px; 
    margin-bottom:25px; 
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
}
.review-header h1 { 
    color: var(--primary-purple); 
    margin:0; 
    font-size: 2rem;
    font-weight: 750;
}
.activity-lesson {
    color: var(--text-color); 
    margin-top: 5px; 
    font-size: 1.05rem;
}
.score-box {
    text-align:right;
    padding: 10px 15px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: #fcfaff;
    min-width: 180px;
    flex-shrink: 0;
}
/* NEW Styles for Score Box */
.score-box .score-group {
    margin-bottom: 5px; /* Spacing between score rows */
}
.score-box .score-label {
    font-size: 0.9rem;
    color: #666;
    display: block;
    margin-top: 5px;
}
.score-box strong { 
    font-size: 1.5rem; 
    color: var(--primary-purple); 
    font-weight: 800;
    display: inline-block;
}
.score-box .submitted-date, .score-box .status-text {
    color: var(--text-color); 
    font-size: 0.85rem;
    display: block;
}
/* NEW Styles for Status Text */
.status-graded { color: var(--correct-color); font-weight: 700; }
.status-submitted { color: var(--secondary-purple); font-weight: 700; }


.panel { 
    display:flex; 
    gap:30px; /* Increased gap */
    align-items:flex-start; 
}

/* Left Sidebar - Attempts */
.left-panel { 
    min-width:280px; 
    max-width:320px;
    padding-right: 20px;
    border-right: 1px solid var(--flat-line-color);
}
.left-panel h4 {
    color: var(--primary-purple);
    font-weight: 600;
    font-size: 1.4rem;
    margin-top: 0;
    margin-bottom: 15px;
}

.attempt-row { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    padding: 15px; /* Increased padding */
    border-radius: 10px;
    background: #fcfaff; /* Light background */
    border: 1px solid var(--border-color); 
    margin-bottom: 12px;
    transition: all 0.2s ease-in-out;
}
.attempt-row:hover {
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.attempt-row .details {
    display: flex;
    flex-direction: column;
}

.attempt-row .details div:first-child { 
    font-weight:700; 
    color: var(--primary-purple);
    font-size: 1.1rem;
}
.attempt-row .details .small-date { 
    color: #888; 
    font-size: 0.85rem;
    margin-top: 3px;
}
/* NEW Styles for attempt-row status */
.attempt-row .status-label {
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 3px;
}

.attempt-row .score-display {
    text-align:right;
}
/* Modified style for attempt row scores to allow for two lines */
.attempt-row .score-display .score-value { 
    font-weight:700; 
    color: var(--primary-purple);
    font-size: 1.2rem;
}
.attempt-row a.view-btn {
    text-decoration:none;
    padding: 8px 12px;
    border-radius: 8px;
    margin-top: 8px;
    font-size: 0.95rem;
    /* Using secondary button style for view */
    background: var(--secondary-purple); 
    color: white; 
    display: inline-block;
    transition: background 0.15s ease-in-out;
}
.attempt-row a.view-btn:hover {
    background: var(--secondary-hover);
}

/* Right Panel - Review Questions */
.review-content {
    flex:1;
}
.review-content h4 {
    color: var(--primary-purple);
    font-weight: 600;
    font-size: 1.4rem;
    margin-top: 0;
    margin-bottom: 15px;
}

.question-review-item { 
    background: #f9f9f9; /* Lighter background for question block */
    border: 1px solid var(--flat-line-color); 
    padding: 18px; 
    border-radius: 10px; 
    margin-bottom: 15px;
}
.question-review-item strong {
    font-size: 1.15rem;
    color: var(--text-color);
    display: block;
    margin-bottom: 8px;
}
.your-answer { 
    font-weight:600; 
    color: var(--primary-purple);
    display: block;
    margin-top: 8px;
}
.correct-status { 
    margin-left:15px; 
    font-weight:700; 
    font-size: 1rem;
}
.correct-status.correct { 
    color: var(--correct-color); 
}
.correct-status.wrong { 
    color: var(--wrong-color); 
}

/* Buttons */
.footer-actions { 
    margin-top: 25px; 
    display:flex; 
    gap:15px; 
}

/* Button styles from start_activity.php */
.btn, .btn-secondary {
    cursor: pointer;
    border: none;
    padding: 14px 24px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.15s ease-in-out;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 180px; 
    text-decoration: none;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
/* Primary button is not used here but kept for consistency */
.btn { 
    background: var(--primary-purple); 
    color: white;
}
.btn:hover { 
    background: var(--primary-hover); 
    transform: translateY(-1px); 
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

/* Secondary Button - Back to Activities */
.btn-secondary {
    background: var(--secondary-purple); 
    color: white; 
    box-shadow: none;
}
.btn-secondary:hover { 
    background: var(--secondary-hover); 
    transform: translateY(-1px);
}

@media (max-width: 760px) {
    .review-wrapper {
        margin: 10px;
        padding: 20px;
    }
    .review-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .score-box {
        width: 100%;
        text-align: left;
        margin-top: 15px;
    }
    .panel {
        flex-direction: column;
        gap: 20px;
    }
    .left-panel {
        min-width: unset;
        width: 100%;
        padding-right: 0;
        border-right: none;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--flat-line-color);
    }
    .footer-actions {
        flex-direction: column;
        gap: 10px;
    }
    .btn, .btn-secondary {
        min-width: unset;
        width: 100%;
    }
}


/* Logout Dialog Styles (based on rejection dialog) */
.logout-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.logout-content {
    background-color: white;
    padding: 2rem;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    text-align: center;
}

.logout-content h3 {
    margin-top: 0;
    color: #562b63;
    font-family: 'Ubuntu', sans-serif; 
    font-size: 1.5rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
}

.logout-content p {
    margin-bottom: 1.5rem;
    font-family: 'Ubuntu', sans-serif; 
    line-height: 1.4;
    font-size: 1rem;
}

.dialog-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.dialog-buttons button {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Ubuntu', sans-serif; 
    font-size: 1rem;
}

#cancelLogout {
    background-color: #f5f5f5;
    color: #333;
}

#cancelLogout:hover {
    background-color: #e0e0e0;
}

#confirmLogoutBtn {
    background: linear-gradient(to right, #5d2c69, #8a5a96);
    color: white;
}

#confirmLogoutBtn:hover {
    background: #5d2c69;
}
</style>
</head>
<body>
  <section class="background" id="home">
    <nav class="navbar">
      <div class="logo">
        <img src="../uploads/img/LogoCoach.png" alt="Logo">
        <span>COACH</span>
      </div>

      <div class="nav-center">
        <ul class="nav_items" id="nav_links">
          <li><a href="home.php">Home</a></li>
          <li><a href="course.php">Courses</a></li>
          <li><a href="resource_library.php">Resource Library</a></li>
          <li><a href="activities.php">Activities</a></li>
          <li><a href="taskprogress.php">Progress</a></li>
          <li><a href="forum-chat.php">Sessions</a></li>
          <li><a href="forums.php">Forums</a></li>
        </ul>
      </div>

      <div class="nav-profile">
  <a href="#" id="profile-icon">
    <?php if (!empty($menteeIcon)): ?>
      <img src="<?php echo htmlspecialchars($menteeIcon); ?>" alt="User Icon" style="width: 35px; height: 35px; border-radius: 50%;">
    <?php else: ?>
      <ion-icon name="person-circle-outline" style="font-size: 35px;"></ion-icon>
    <?php endif; ?>
  </a>
</div>

<div class="sub-menu-wrap hide" id="profile-menu">
  <div class="sub-menu">
    <div class="user-info">
      <div class="user-icon">
        <?php if (!empty($menteeIcon)): ?>
          <img src="<?php echo htmlspecialchars($menteeIcon); ?>" alt="User Icon" style="width: 40px; height: 40px; border-radius: 50%;">
        <?php else: ?>
          <ion-icon name="person-circle-outline" style="font-size: 40px;"></ion-icon>
        <?php endif; ?>
      </div>
      <div class="user-name"><?php echo htmlspecialchars($firstName); ?></div>
    </div>
    <ul class="sub-menu-items">
      <li><a href="profile.php">Profile</a></li>
      <li><a href="taskprogress.php">Progress</a></li>
      <li><a href="#" onclick="confirmLogout(event)">Logout</a></li>
    </ul>
  </div>
</div>
    </nav>
  </section>

<div class="review-wrapper">
    <div class="review-header">
        <div>
            <h1><?= htmlspecialchars($activity['Activity_Title']) ?></h1>
            <div class="activity-lesson"><strong>Lesson:</strong> <?= htmlspecialchars($activity['Lesson'] ?? '') ?></div>
        </div>
        
        <div class="score-box">
            <?php if ($selected):
                // NEW: Get Final_Score and Submission_Status
                $menteeScore = htmlspecialchars((string)$selected['Score']);
                $finalScore = htmlspecialchars((string)$selected['Final_Score']);
                $status = htmlspecialchars($selected['Submission_Status']);
                // Determine if it has been graded by checking status AND Final_Score is not null
                $isGraded = ($status === 'Graded' && $selected['Final_Score'] !== NULL);
            ?>
                <div class="score-group">
                    <span class="score-label">Mentee Score</span>
                    <strong><?= $menteeScore ?></strong>
                </div>

                <?php if ($isGraded): ?>
                    <div class="score-group" style="border-top: 1px dashed #eee; padding-top: 5px; margin-top: 5px;">
                        <span class="score-label">Final Score</span>
                        <strong style="color: var(--correct-color);"><?= $finalScore ?></strong>
                    </div>
                <?php endif; ?>

                <div class="status-text">Status: <span class="status-<?= strtolower($status) ?>"><?= $status ?></span></div>
                <div class="submitted-date">Submitted: <?= htmlspecialchars($selected['Submitted_At']) ?></div>
            <?php else: ?>
                <div class="score-value" style="color: var(--wrong-color);">N/A</div>
                <div class="submitted-date">No submission found.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="left-panel">
            <h4>Submission History</h4>
            <div class="attempts-list">
                <?php if (empty($submissions)): ?>
                    <div style="color: var(--text-color); font-size: 0.95rem;">No attempts yet.</div>
                <?php else: foreach ($submissions as $s): ?>
                    <?php 
                        $is_current = $selected && (int)$selected['Attempt_Number'] === (int)$s['Attempt_Number'];
                        $row_style = $is_current ? 'border: 2px solid var(--primary-purple);' : '';
                        // NEW: Variables for attempt row
                        $s_status = htmlspecialchars($s['Submission_Status']);
                        $s_score = htmlspecialchars((string)$s['Score']);
                        $s_final_score = htmlspecialchars((string)$s['Final_Score']);
                        $s_isGraded = ($s_status === 'Graded' && $s['Final_Score'] !== NULL);
                    ?>
                    <div class="attempt-row" style="<?= $row_style ?>">
                        <div class="details">
                            <div>Attempt <?= (int)$s['Attempt_Number'] ?></div>
                            <div class="small-date"><?= htmlspecialchars($s['Submitted_At']) ?></div>
                            <div class="status-label status-<?= strtolower($s_status) ?>"><?= $s_status ?></div>
                        </div>
                        <div class="score-display">
                            <div class="score-value" style="font-size: 1rem; font-weight: 500; color: var(--text-color);">Score: <?= $s_score ?></div>
                            <?php if ($s_isGraded): ?>
                                <div class="score-value" style="color: var(--correct-color); font-size: 1.2rem; margin-top: 3px;">
                                    Final: <?= $s_final_score ?>
                                </div> 
                            <?php endif; ?>
                            <a class="view-btn" href="review_activity.php?activity_id=<?= $activity_id ?>&attempt=<?= (int)$s['Attempt_Number'] ?>">View</a>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            
            <div class="footer-actions">
                <a class="btn-secondary" href="activities.php">Back to Activities</a>
            </div>
        </div>

        <div class="review-content">
            <h4>Review Details</h4>

            <?php if (!$selected): ?>
                <p style="color: var(--text-color);">Select an attempt from the left to review the answers.</p>
            <?php else: ?>
                <?php foreach ($questions as $i => $q):
                    $your = $answers["answer_$i"] ?? '';
                    $is_correct = false;
                    $is_graded = false;

                    if (($q['type'] ?? '') === 'Multiple Choice' || strtolower($q['type'] ?? '') === 'multiple_choice') {
                        $is_graded = true;
                        if (!empty($q['correct_answer']) && preg_match('/Choice\s*(\d+)/i', $q['correct_answer'], $m)) {
                            $idx = (int)$m[1] - 1;
                            $correct_letter = chr(65 + $idx);
                            if (strtoupper(trim($your)) === strtoupper($correct_letter)) $is_correct = true;
                        }
                    } elseif (isset($q['acceptable_answers'])) {
                        $is_graded = true;
                        $acceptable = $q['acceptable_answers'] ?? [];

                        // --- MODIFIED LOGIC START ---
                        // Ensure $acceptable is always an array
                        if (is_string($acceptable)) {
                            // If it's a string, split by comma and trim each value
                            $acceptable = array_map('trim', explode(',', $acceptable));
                        } elseif (!is_array($acceptable) && $acceptable !== null) {
                            // If it's a non-array, non-string, cast to string and wrap in array
                            $acceptable = [(string)$acceptable];
                        }
                        // --- MODIFIED LOGIC END ---

                        foreach ($acceptable as $acc) {
                            if (
                                mb_strtolower(trim((string)$acc)) === mb_strtolower(trim((string)$your)) &&
                                trim((string)$your) !== ''
                            ) {
                                $is_correct = true;
                                break;
                            }
                        }
                    }

                ?>
                <div class="question-review-item">
                    <div><strong><?= ($i+1) . '. ' . htmlspecialchars($q['question'] ?? '') ?></strong></div>
                    
                    <div style="margin-top:8px;">
                        <span class="your-answer">Your answer:</span> 
                        <?= htmlspecialchars((string)$your ?: '—') ?>
                        
                        <?php if ($is_graded): ?>
                            <span class='correct-status <?= $is_correct ? 'correct' : 'wrong' ?>'>
                                <?= $is_correct ? "✔ Correct" : "✖ Wrong" ?>
                            </span>
                        <?php else: ?>
                            <span style='margin-left:15px; color: var(--secondary-purple); font-weight: 500;'>Awaiting Mentor Review</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>




<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

<script src="js/mentee.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Select all necessary elements
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    // --- Profile Menu Toggle Logic ---
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide");
        });
        
        // Close menu when clicking outside
        document.addEventListener("click", function (e) {
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    }

    // --- Logout Dialog Logic ---
    // Make confirmLogout function globally accessible for the onclick in HTML
    window.confirmLogout = function(e) { 
        if (e) e.preventDefault(); // FIX: Prevent the default anchor behavior (# in URL)
        if (logoutDialog) {
            logoutDialog.style.display = "flex";
        }
    }

    // FIX: Attach event listeners to the dialog buttons after DOM is loaded
    if (cancelLogoutBtn && logoutDialog) {
        cancelLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            logoutDialog.style.display = "none";
        });
    }

    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            // FIX: Redirect to the dedicated logout script in the parent folder (root)
            window.location.href = "../logout.php"; 
        });
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