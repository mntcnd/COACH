<?php
session_start();

// *** FIX: Set timezone to Philippine Time (PHT) ***
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

// Redirect if mentee not logged in or required GET parameters missing
if (!isset($_SESSION['username']) || 
    !isset($_GET['course_title']) || 
    !isset($_GET['activity_title']) || 
    !isset($_GET['difficulty_level'])) {
    header("Location: activities.php");
    exit();
}

$menteeUsername = $_SESSION['username'];
$menteeUserId = $_SESSION['user_id'];
$courseTitle = $_GET['course_title'];
$activityTitle = $_GET['activity_title'];
$difficultyLevel = $_GET['difficulty_level'];

// Fetch mentee profile
$firstName = '';
$menteeIcon = '';
$stmt = $conn->prepare("SELECT First_Name, icon FROM users WHERE Username = ?");
$stmt->bind_param("s", $menteeUsername);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstName = $row['First_Name'];
    $menteeIcon = $row['icon'];
}
$stmt->close();

// ✅ Fetch only the latest attempt’s answers for this activity
$sql = "SELECT Question, Selected_Answer, Correct_Answer, Is_Correct
        FROM mentee_answers
        WHERE user_id = ?
          AND Course_Title = ?
          AND Activity_Title = ?
          AND Difficulty_Level = ?
          AND Attempt_Number = (
              SELECT MAX(Attempt_Number)
              FROM mentee_answers
              WHERE user_id = ?
                AND Course_Title = ?
                AND Activity_Title = ?
                AND Difficulty_Level = ?
          )";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "isssisss",
    $menteeUserId, $courseTitle, $activityTitle, $difficultyLevel,
    $menteeUserId, $courseTitle, $activityTitle, $difficultyLevel
);
$stmt->execute();
$result = $stmt->get_result();

$answers = [];
while ($row = $result->fetch_assoc()) {
    $row['Question_Text'] = $row['Question'] ?? "Question not found.";
    $answers[] = $row;
}
$stmt->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/courses.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<title>Review Activity - <?= htmlspecialchars($courseTitle) ?></title>
<style>
body { margin-top: 30px; font-family: Arial, sans-serif; }
.container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { color: #6a1b9a; margin-bottom: 15px; }
.question-box { background-color: #ede7f6; border: 1px solid #d1c4e9; padding: 15px; margin-bottom: 15px; border-radius: 6px; }
.correct { color: green; font-weight: bold; }
.incorrect { color: red; font-weight: bold; }
.back-btn { background-color: #7b1fa2; color: white; padding: 10px 15px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; margin-bottom: 15px; }
.back-btn:hover { background-color: #6a1b9a; }

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
        <img src="<?= htmlspecialchars($menteeIcon) ?>" alt="User Icon" style="width: 35px; height: 35px; border-radius: 50%;">
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
            <img src="<?= htmlspecialchars($menteeIcon) ?>" alt="User Icon" style="width: 40px; height: 40px; border-radius: 50%;">
          <?php else: ?>
            <ion-icon name="person-circle-outline" style="font-size: 40px;"></ion-icon>
          <?php endif; ?>
        </div>
        <div class="user-name"><?= htmlspecialchars($firstName) ?></div>
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

<div class="container">
  <a href="activities.php" class="back-btn">&larr; Back to Activities</a>
  <h2>Review of <?= htmlspecialchars($courseTitle) ?> - <?= htmlspecialchars($activityTitle) ?> (<?= htmlspecialchars($difficultyLevel) ?>)</h2>

  <?php if (count($answers) > 0): ?>
    <?php foreach ($answers as $index => $ans): ?>
      <div class="question-box">
        <p><strong>Q<?= $index + 1 ?>:</strong> <?= htmlspecialchars($ans['Question_Text']) ?></p>
        <p>Your Answer: 
          <span class="<?= $ans['Is_Correct'] ? 'correct' : 'incorrect' ?>">
            <?= htmlspecialchars($ans['Selected_Answer']) ?>
          </span>
        </p>
        <?php if (!$ans['Is_Correct']): ?>
          <p>Correct Answer: <span class="correct"><?= htmlspecialchars($ans['Correct_Answer']) ?></span></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p>No answers found for this activity.</p>
  <?php endif; ?>
</div>

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

</body>
</html>
