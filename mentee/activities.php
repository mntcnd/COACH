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

// fetch assigned activities with last submission info
$query = "
    SELECT 
        aa.Assign_ID,
        aa.Date_Assigned,
        a.Activity_ID,
        a.Lesson,
        a.Activity_Title,
        a.Activity_Type,
        a.Questions_JSON,
        a.File_Path,
        (
            SELECT MAX(s.Attempt_Number) FROM submissions s
            WHERE s.Activity_ID = a.Activity_ID AND s.Mentee_ID = aa.Mentee_ID
        ) AS Latest_Attempt,
        (
            SELECT s2.Score FROM submissions s2
            WHERE s2.Activity_ID = a.Activity_ID AND s2.Mentee_ID = aa.Mentee_ID
            ORDER BY s2.Attempt_Number DESC LIMIT 1
        ) AS Latest_Score,
        (
            SELECT s2.Submitted_At FROM submissions s2
            WHERE s2.Activity_ID = a.Activity_ID AND s2.Mentee_ID = aa.Mentee_ID
            ORDER BY s2.Attempt_Number DESC, s2.Submitted_At DESC LIMIT 1
        ) AS Last_Attempt_At
    FROM assigned_activities aa
    JOIN activities a ON aa.Activity_ID = a.Activity_ID
    WHERE aa.Mentee_ID = ? 
    AND a.Status = 'Approved' /* <-- ADD THIS LINE */
    ORDER BY aa.Date_Assigned DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $menteeUserId);
$stmt->execute();
$res = $stmt->get_result();
$assigned = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activities</title>
<link rel="stylesheet" href="css/navbar.css">
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<style>
  
/* Color Palette from start_activity.php */
:root {
    --primary-purple: #6a2c70; /* Deep Purple - Primary Button, Headings */
    --primary-hover: #9724b0ff; /* Slightly darker primary */
    --secondary-purple: #91489bff; /* Light Purple - Secondary Button */
    --secondary-hover: #60225dff; /* Slightly darker secondary */
    --text-color: #424242;        /* Default text */
    --light-bg: #fdfdff;          /* Page background */
    --container-bg: #fff;
    --border-color: #E1BEE7;
}

/* Base styles */
body { 
    margin: 0; 
    padding: 0;
    background: var(--light-bg); 
    font-family: "Poppins", sans-serif; /* Adjusted to match Poppins from start_activity */
    color: var(--text-color); 
    min-height: 100vh;
    padding-top: 60px; /* Space for the fixed navbar */
}

.container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 20px;
}

h2 {
    color: var(--primary-purple);
    border-bottom: 2px solid var(--border-color); /* Used the light border color */
    padding-bottom: 15px;
    margin-bottom: 30px;
    text-align: center;
    font-weight: 790; /* Heavier font weight */
    font-size: 2rem;
}

/* Activity Card Styling (adapted from .full-activity-wrapper) */
.assignment {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: var(--container-bg);
    padding: 20px 30px;
    margin-bottom: 20px;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    transition: box-shadow 0.2s, transform 0.2s;
    border-left: 5px solid var(--primary-purple); /* Added purple accent strip */
}

.assignment:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.assignment h3 {
    color: var(--primary-purple); /* Purple title */
    margin-top: 0;
    margin-bottom: 8px;
    font-size: 1.45rem;
    font-weight: 650;
}

.assignment p {
    margin: 4px 0;
    font-size: 1rem;
    color: var(--text-color);
}

.assignment p strong {
    color: var(--primary-purple);
    font-weight: 600;
}

/* Score highlight (preserved from previous version, adapted colors) */
.assignment p span {
    font-weight: bold; 
    /* Using primary-purple and a darker text for contrast on light background */
    color: var(--primary-purple); 
}

/* Button Styling (Adapted from start_activity.php styles) */
.actions {
    display: flex; 
    flex-direction:column; 
    gap: 8px; 
    align-items:flex-end;
}

.btn, .btn-secondary {
    cursor: pointer;
    border: none;
    padding: 12px 20px; /* Slightly smaller padding for dashboard items */
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    transition: all 0.15s ease-in-out;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 150px; 
    text-decoration: none;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.btn { /* Primary Purple (Start/Attempt Again) */
    background: var(--primary-purple); 
    color: white;
}
.btn:hover { 
    background: var(--primary-hover); 
    transform: translateY(-1px); 
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.btn-secondary { /* Secondary Purple (Review/Cancel) */
    background: var(--secondary-purple); 
    color: white; 
    box-shadow: none;
}
.btn-secondary:hover { 
    background: var(--secondary-hover); 
    transform: translateY(-1px);
}

/* Start confirmation modal (adapted from start_activity.php styles) */
.modal-inline {
    display: none; 
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 4000;
    align-items: center;
    justify-content: center;
}
.modal-card {
    background: rgba(255, 254, 254, 1);
    color: var(--text-color);
    border-radius: 12px;
    padding: 30px 35px;
    max-width: 450px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(57, 55, 55, 1);
    z-index: 4001;
}
.modal-card h3 {
    color: var(--primary-purple);
    margin-top: 0;
}

/* Media Queries for Responsiveness (adapted from start_activity.php) */
@media (max-width: 760px) {
    .container {
        margin: 10px;
        padding: 10px;
    }
    .assignment {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
    }
    .assignment > div:first-child {
        max-width: 100%;
        margin-bottom: 15px;
    }
    .actions {
        width: 100%;
        align-items: stretch;
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

<div class="container">
    <h2>Assigned Activities</h2>

    <?php if (empty($assigned)): ?>
        <p style="text-align:center; padding: 20px; background-color: var(--container-bg); border-radius: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.06);">No activities assigned yet.</p>
    <?php else: foreach ($assigned as $a): 
        $attemptNum = (int)($a['Latest_Attempt'] ?? 0);
        $score = $a['Latest_Score'] !== null ? $a['Latest_Score'] : null; // number (no %)
        $lastAttemptAt = $a['Last_Attempt_At'] ?? null;
        $dateAssigned = $a['Date_Assigned'] ?? null;
    ?>
    <div class="assignment" role="article">
        <div style="max-width:72%;">
            <h3><?= htmlspecialchars($a['Activity_Title']) ?></h3>
            <p><strong>Lesson:</strong> <?= htmlspecialchars($a['Lesson'] ?? '—') ?></p>
            <p><strong>Date Assigned:</strong> <?= $dateAssigned ? date("F d, Y", strtotime($dateAssigned)) : '—' ?></p>
            <p><strong>Latest Score:</strong> <span style="font-weight:bold; color:<?= $score !== null ? (floatval($score) > 0.75 ? '#8d1cc6ff' : '#2f003bff') : 'var(--text-color)' ?>;"><?= isset($score) ? htmlspecialchars($score) : 'N/A' ?></span></p>
            <p><strong>Last Attempt:</strong> <?= $lastAttemptAt ? date("F d, Y g:i A", strtotime($lastAttemptAt)) : 'None' ?></p>
        </div>

        <div class="actions">
            <?php if ($attemptNum === 0): ?>
                <button class="btn start-btn" data-id="<?= (int)$a['Activity_ID'] ?>">Check Activity</button>
            <?php else: ?>
                <button class="btn" onclick="location.href='start_activity.php?activity_id=<?= (int)$a['Activity_ID'] ?>&retry=1'">Attempt Again</button>
                <button class="btn-secondary" onclick="location.href='review_activity.php?activity_id=<?= (int)$a['Activity_ID'] ?>'">Review</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<div class="modal-inline" id="startModal">
    <div class="modal-card">
        <h3>Start Activity</h3>
        <p>You're about to begin this activity. Make sure you have a stable connection and won't close this window.</p>
        <div style="text-align:right; margin-top:20px;">
            <button id="startCancel" class="btn-secondary" style="min-width: 100px;">Cancel</button>
            <button id="startConfirm" class="btn" style="min-width: 150px; margin-left: 10px;">Proceed to Activity</button>
        </div>
    </div>
</div>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

<script src="js/mentee.js"></script>
<script>
const startModal = document.getElementById('startModal');
let chosenId = null;
document.querySelectorAll('.start-btn').forEach(b=>{
    b.addEventListener('click', () => {
        chosenId = b.dataset.id;
        startModal.style.display = 'flex';
    });
});
document.getElementById('startCancel').addEventListener('click', ()=> {
    startModal.style.display = 'none';
    chosenId = null;
});
document.getElementById('startConfirm').addEventListener('click', ()=> {
    if (!chosenId) return;
    // go to start_activity.php without navbar (full mode)
    window.location.href = 'start_activity.php?activity_id=' + encodeURIComponent(chosenId);
});

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