<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Start the session

require '../connection/db_connection.php';

// --- INITIALIZATION ---
$mentorUsername = $_SESSION['username'] ?? null;
$mentorFullName = "";
$mentorIcon = "../uploads/img/default_pfp.png";
$requestMessage = ""; 

// SESSION CHECK
if (!isset($mentorUsername) || ($_SESSION['user_type'] ?? '') !== 'Mentor') {
  header("Location: ../login.php");
  exit();
}

// FETCH Mentor's Name AND Icon 
$sql = "SELECT CONCAT(first_name, ' ', last_name) AS mentor_name, icon AS mentor_icon FROM users WHERE username = ? AND user_type = 'Mentor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $mentorUsername);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $row = $result->fetch_assoc();
  $mentorFullName = $row['mentor_name'];
  $mentorIcon = !empty($row['mentor_icon']) ? $row['mentor_icon'] : "../uploads/img/default_pfp.png";
  $_SESSION['mentor_name'] = $mentorFullName;
  $_SESSION['mentor_icon'] = $mentorIcon;
} else {
  // Fallback if user data is missing
  $mentorFullName = "Unknown Mentor";
  $_SESSION['mentor_name'] = $mentorFullName;
  $_SESSION['mentor_icon'] = $mentorIcon;
}
$stmt->close();

// --- FETCH MENTOR's CURRENT COURSE ID (TO BE USED FOR BOTH REQUESTS) ---
// This is the core logic to get the current assigned course ID.
$queryCurrentCourse = "SELECT Course_ID FROM courses WHERE Assigned_Mentor = ?";
$stmtCurrentCourse = $conn->prepare($queryCurrentCourse);
$stmtCurrentCourse->bind_param("s", $mentorFullName);
$stmtCurrentCourse->execute();
$currentCourseResult = $stmtCurrentCourse->get_result();

$currentCourseId = null;
if ($currentCourseResult->num_rows > 0) {
    $row = $currentCourseResult->fetch_assoc();
    $currentCourseId = (int)$row['Course_ID'];
}
$stmtCurrentCourse->close();


// --- REQUEST SUBMISSION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $requestType = trim($_POST['request_type']); // Trim to remove any whitespace
    $reason = trim($_POST['reason']);
    
    // Determine the ID of the WANTED course (NULL for Resignation)
    // This value comes from the 'new_course_id' dropdown in the form for Course Change requests.
    $wantedCourseId = ($requestType === 'Course Change' && !empty($_POST['new_course_id'])) ? (int)$_POST['new_course_id'] : NULL;

    // Sanity check to ensure $requestType holds one of the valid ENUM values
    if (!empty($reason) && in_array($requestType, ['Resignation', 'Course Change'])) {
        
        // --- PHT Time Calculation FIX: Set timezone and get current time in PHT ---
        date_default_timezone_set('Asia/Manila');
        $philippineTime = date('Y-m-d H:i:s');
        // --- End PHT Time Calculation ---

        // Insert into mentor_requests table
        // We use $currentCourseId for current_course_id (fetched above)
        // We use $wantedCourseId for wanted_course_id (NULL for Resignation, ID for Course Change)
        $insertQuery = "INSERT INTO mentor_requests (username, request_type, current_course_id, wanted_course_id, reason, request_date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtInsert = $conn->prepare($insertQuery);
        
        // FIXED: Changed bind_param from "siiiss" to "ssiiss"
        // s = username (string)
        // s = request_type (string - NOT integer!)
        // i = current_course_id (integer)
        // i = wanted_course_id (integer or NULL)
        // s = reason (string)
        // s = request_date (string/timestamp)
        $stmtInsert->bind_param("ssiiss", $mentorUsername, $requestType, $currentCourseId, $wantedCourseId, $reason, $philippineTime);

        if ($stmtInsert->execute()) {
            // Displays the confirmed PHT time in the success message
            $requestMessage = "✅ Your **" . htmlspecialchars($requestType) . " Request** has been submitted successfully and is pending review. (Time: " . date('H:i:s') . " PHT)";
        } else {
            $requestMessage = "❌ Error submitting request: " . $stmtInsert->error;
        }
        $stmtInsert->close();
    } else {
        $requestMessage = "⚠️ Please select a valid request type and provide a reason.";
    }
}


// FETCH COURSES ASSIGNED TO THIS MENTOR (for display)
$queryCourses = "SELECT Course_ID, Course_Title, Course_Description, Skill_Level, Course_Icon FROM courses WHERE Assigned_Mentor = ?";
$stmtCourses = $conn->prepare($queryCourses);
$stmtCourses->bind_param("s", $mentorFullName);
$stmtCourses->execute();
$coursesResult = $stmtCourses->get_result();

// Save all courses for the dropdown in the modal
$allCourses = [];
if ($coursesResult->num_rows > 0) {
    while ($course = $coursesResult->fetch_assoc()) {
        $allCourses[] = $course;
    }
}
$stmtCourses->close(); // Close after fetching assigned courses


// FETCH: COURSES AVAILABLE FOR ASSIGNMENT (for the "Course to Move To" dropdown)
// This query remains correct: GET AND DISPLAY the Course_Title that has a NULL Assigned_Mentor
$queryAvailableCourses = "SELECT Course_ID, Course_Title, Skill_Level FROM courses WHERE Assigned_Mentor IS NULL";
$stmtAvailableCourses = $conn->prepare($queryAvailableCourses);
$stmtAvailableCourses->execute();
$availableCoursesResult = $stmtAvailableCourses->get_result();

$availableCourses = [];
if ($availableCoursesResult->num_rows > 0) {
    while ($course = $availableCoursesResult->fetch_assoc()) {
        $availableCourses[] = $course;
    }
}
$stmtAvailableCourses->close();


// --- 2. FETCH TOTAL APPROVED UPLOADS (from resources table) ---
// FIXED: Using 'UploadedBy' column and filtering by the Mentor's Full Name ($mentorFullName).
$total_uploads = 0;
if (!empty($mentorFullName)) {
    // The 'resources' table's 'UploadedBy' column contains the full name.
    $sql_uploads = "SELECT COUNT(*) AS total_uploads FROM resources WHERE Status = 'Approved' AND UploadedBy = ?";
    
    $stmt_uploads = $conn->prepare($sql_uploads);
    $stmt_uploads->bind_param("s", $mentorFullName); // <-- CORRECTED to use $mentorFullName
    $stmt_uploads->execute();
    $result_uploads = $stmt_uploads->get_result();
    $row_uploads = $result_uploads->fetch_assoc();
    $total_uploads = $row_uploads['total_uploads'];
    $stmt_uploads->close();
}


// --- 3. FETCH TOTAL ACTIVE BOOKED SESSIONS (from session_bookings table) ---
// *FIXED:* Joining session_bookings to courses using the 'course_title' string column, as seen in image_6e1154.jpg.
$active_bookings = 0;
if (!empty($mentorFullName)) {
    $sql_active = "SELECT COUNT(sb.booking_id) AS total_active_bookings 
                   FROM session_bookings sb
                   JOIN courses c ON sb.course_title = c.Course_Title
                   WHERE sb.status = 'approved' AND c.Assigned_Mentor = ?";
                   
    $stmt_active = $conn->prepare($sql_active);
    $stmt_active->bind_param("s", $mentorFullName);
    $stmt_active->execute();
    $result_active = $stmt_active->get_result();
    $row_active = $result_active->fetch_assoc();
    $active_bookings = $row_active['total_active_bookings'];
    $stmt_active->close();
}


// --- 4. FETCH AVERAGE FEEDBACK SCORE (from feedback table) ---
// *DEFINITIVELY FIXED:* Filtering feedback directly using the 'Session_Mentor' column from the feedback table (image_6e0d56.jpg).
$avg_feedback = 'N/A';
if (!empty($mentorFullName)) {
    // Column 'Session_Mentor' exists in the feedback table and holds the mentor's full name.
    $sql_feedback = "SELECT AVG(Mentor_Star) AS avg_feedback_score 
                     FROM feedback 
                     WHERE Session_Mentor = ?";

    $stmt_feedback = $conn->prepare($sql_feedback);
    $stmt_feedback->bind_param("s", $mentorFullName);
    $stmt_feedback->execute();
    $result_feedback = $stmt_feedback->get_result();
    $row_feedback = $result_feedback->fetch_assoc();

    if ($row_feedback['avg_feedback_score'] !== null) {
        $avg_feedback = number_format($row_feedback['avg_feedback_score'], 1);
    }
    $stmt_feedback->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css" /> 
  <link rel="stylesheet" href="../superadmin/css/clock.css"/>
  <link rel="stylesheet" href="css/navigation.css"/>
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Courses | Mentor</title>
  <style>
    /* ------------------------------------------- */
    /* PROFESSIONAL COURSE LAYOUT STYLES (Split View) */
    /* ------------------------------------------- */

    /* General Layout */
    .dashboard .main-content {
        padding: 20px 30px;
        min-height: calc(100vh - 80px); 
    }

    .assigned-heading {
        font-size: 2.2em;
        color: #6d4c90; 
        border-bottom: 3px solid #f2e3fb;
        padding-bottom: 10px;
        margin-bottom: 40px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* **SPLIT CONTAINER** */
    .split-container {
        display: grid;
        grid-template-columns: 2fr 1fr; /* 2 parts for courses, 1 part for details */
        gap: 30px;
        margin-bottom: 40px;
    }

    .course-list-area {
        /* This column contains the actual course cards */
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    /* Course Card Style: FIXING THE BLANK SPACE */
    .course-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); /* Stronger box shadow */
        transition: box-shadow 0.3s;
        display: flex;
        flex-direction: row; 
        align-items: flex-start; /* Align content to the top */
        padding: 20px;
        border: 1px solid #e0e0e0;
        width: 100%; 
    }

    .course-card:hover {
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
    }
    
    .course-card > img {
        width: 80px; /* Increased icon size for better prominence */
        height: 80px;
        object-fit: contain;
        margin-right: 20px;
        flex-shrink: 0;
        border-radius: 6px;
    }

    .course-content-wrapper {
        display: flex;
        flex-direction: column;
        flex-grow: 1; /* KEY FIX: Ensures the content takes all available width */
    }

    .course-title-row {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }

    .course-title-row h3 {
        margin: 0;
        font-size: 1.3em;
        color: #333;
        font-weight: 700;
    }

    .skill-level {
        display: inline-block;
        background-color: #ff6f61; 
        color: white;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
        margin-left: 10px;
    }
    
    .course-description {
        color: #555;
        font-size: 0.95em;
        line-height: 1.5;
        margin-top: 5px;
    }

    /* Course Details/Reminder Block (Right Column) */
    .course-details {
        background-color: #f7effeff; 
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #4e0a67ff;
        height: fit-content; 
    }

    .course-details h2 {
        color: #4e0a67ff;
        font-size: 1.5em;
        margin-top: 0;
        margin-bottom: 15px;
        font-weight: 700;
    }
    .course-details .course-reminder {
        color: #333;
        line-height: 1.6;
        margin-bottom: 20px;
        font-size: 0.95em;
    }

    .start-course-btn, .appeal-course-btn {
        padding: 12px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        width: 100%; 
        margin-top: 10px;
    }

    .start-course-btn {
        background-color: #6d4c90;
        color: white;
    }
    .start-course-btn:hover {
        background-color: #5b3c76;
    }
    
    .appeal-course-btn {
        background-color: #6d4c90; /* Primary accent color */
        color: white;
        margin-bottom: 0;
    }
    .appeal-course-btn:hover {
        background-color: #5b3c76;
    }
    
    /* Horizontal Separator */
    .course-details hr {
        border: 0; 
        border-top: 1px solid #e0e0e0; 
        margin: 25px 0;
    }

    /* Request Section (Resignation) - UPDATED MARGIN */
    .request-section {
        background-color: #fcf8f8; 
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        border-left: 5px solid #ff6f61; 
        display: flex;
        justify-content: space-between;
        align-items: center;
        /* UPDATED MARGIN-TOP to separate it from the stats above */
        margin-top: 30px; 
    }
    .request-section h2 {
        color: #ff6f61;
        font-size: 1.5em;
        margin-top: 0;
    }
    .request-section p {
        color: #555;
        margin-bottom: 0;
        font-size: 0.95em;
    }
    .resignation-btn {
        background-color: #ff6f61; 
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s;
        text-transform: uppercase;
    }
    .resignation-btn:hover {
        background-color: #e55a4f;
    }
    
    /* Modal Styles (kept concise) */
    .modal {
        display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6);
    }
    .modal-content {
        background-color: #fff; margin: 10% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 450px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); position: relative;
    }
    .close-btn {
        color: #aaa; float: right; font-size: 32px; position: absolute; top: 10px; right: 20px; cursor: pointer;
    }
    .form-group label {
        display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 0.95em;
    }
    .form-group select, .form-group textarea {
        width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1em;
    }
    .modal-submit-btn {
        background-color: #6d4c90; color: white; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%;
    }
    /* Updated IDs for the new course selection logic */
    #new_course_id_group { display: none; } 

    /* Status Message Styles (kept concise) */
    .status-message { padding: 15px; margin-bottom: 25px; border-radius: 8px; font-weight: 600; font-size: 0.95em; }
    .status-message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    
    /* Responsive adjustment */
    @media (max-width: 992px) {
        .split-container {
            grid-template-columns: 1fr; /* Stack vertically on tablets/phones */
        }
    }
    @media (max-width: 600px) {
        .request-section {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        .resignation-btn {
            width: 100%;
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
      <img src="<?php echo htmlspecialchars($_SESSION['mentor_icon']); ?>" alt="Mentor Profile Picture" />
      <div class="admin-text">
        <span class="admin-name">
          <?php echo htmlspecialchars($_SESSION['mentor_name']); ?>
        </span>
        <span class="admin-role">Mentor</span>
      </div>
      <a href="edit_profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
        <ion-icon name="create-outline" class="verified-icon"></ion-icon>
      </a>
    </div>
  </div>

  <div class="menu-items">
    <ul class="navLinks">
      <li class="navList">
        <a href="dashboard.php">
          <ion-icon name="home-outline"></ion-icon>
          <span class="links">Home</span>
        </a>
      </li>
      <li class="navList active">
        <a href="courses.php">
          <ion-icon name="book-outline"></ion-icon>
          <span class="links">Course</span>
        </a>
      </li>
      <li class="navList">
        <a href="sessions.php">
          <ion-icon name="calendar-outline"></ion-icon>
          <span class="links">Sessions</span>
        </a>
      </li>
      <li class="navList">
        <a href="feedbacks.php">
          <ion-icon name="star-outline"></ion-icon>
          <span class="links">Feedbacks</span>
        </a>
      </li>
      <li class="navList">
        <a href="activities.php">
          <ion-icon name="clipboard"></ion-icon>
          <span class="links">Activities</span>
        </a>
      </li>
      <li class="navList">
        <a href="resource.php">
          <ion-icon name="library-outline"></ion-icon>
          <span class="links">Resource Library</span>
        </a>
      </li>
            <li class="navList">
        <a href="achievement.php">
          <ion-icon name="trophy-outline"></ion-icon>
          <span class="links">Achievement</span>
        </a>
      </li>
    </ul>

    <ul class="bottom-link">
      <li class="logout-link">
        <a href="#" onclick="confirmLogout(event)">
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

  <div class="main-content" style="margin-top: 50px;">
    <?php if (!empty($requestMessage)): ?>
        <div class="status-message <?= strpos($requestMessage, '✅') !== false ? 'success' : (strpos($requestMessage, '❌') !== false ? 'error' : 'warning') ?>">
            <?= $requestMessage ?>
        </div>
    <?php endif; ?>

    <h2 class="assigned-heading">Course Dashboard</h2>

    <div class="split-container">
        
        <div class="course-list-area">
    <div class="courses-container">
        <?php if (!empty($allCourses)): ?>
            <?php foreach($allCourses as $course): ?>
                <div class="course-card">
                    <img src="../uploads/<?= htmlspecialchars($course['Course_Icon']) ?>" alt="Course Icon">

                    <div class="course-content-wrapper">
                        <div class="course-title-row">
                            <h3><?= htmlspecialchars($course['Course_Title']) ?></h3>
                            <div class="skill-level"><?= htmlspecialchars($course['Skill_Level']) ?></div>
                        </div>

                        <div class="course-description">
                            <?= htmlspecialchars($course['Course_Description']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; color: #6d4c90; font-size: 16px; background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee;">
                You currently have no courses assigned.
            </div>
        <?php endif; ?>
    </div>
  
    <h3 style="color: #4a4a4a; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 5px;">Course Overview</h3>
<div class="mentee-stats" style="display: flex; gap: 20px;">
    
    <div style="flex: 1; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa;">
        <div style="font-size: 30px; font-weight: bold; color: #6d4c90;"><?= $total_uploads ?></div>
        <div style="font-size: 14px; color: #666;">Total Uploads</div>
    </div>
    
    <div style="flex: 1; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa;">
        <div style="font-size: 30px; font-weight: bold; color: #6d4c90;"><?= $active_bookings ?></div>
        <div style="font-size: 14px; color: #666;">Active Bookings</div>
    </div>
    
    <div style="flex: 1; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa;">
        <div style="font-size: 30px; font-weight: bold; color: #6d4c90;"><?= $avg_feedback ?> ⭐</div>
        <div style="font-size: 14px; color: #666;">Avg. Feedback Score</div>
    </div>
</div>

    <div class="request-section">
        <div style="flex-grow: 1;">
            <h2>Mentor Status Change</h2>
            <p>
                To submit your <b>resignation</b> from your mentor role, please use the form below. This is for complete withdrawal only.
            </p>
        </div>
        <button class="resignation-btn" onclick="openRequestModal('Resignation')">
            Submit Resignation
        </button>
    </div>
</div>
        
  <div class="course-details">
    <h2>Ready to Begin Your Session Journey</h2>
    <p class="course-reminder">
       Check your microphone and camera, prepare all digital resources, and be ready to guide your mentees on-screen with patience and clarity.
    </p>
    <a href="sessions.php">
        <button class="start-course-btn">Start Session</button>
    </a>
    <hr>
            
            <h2 style="color: #6d4c90; font-size: 1.2em;">Course Management</h2>
            <p class="course-reminder">
                If you need to appeal a course change, submit a formal request here.
            </p>
            <button class="appeal-course-btn" onclick="openRequestModal('Course Change')">
                Appeal Course Change
            </button>
        </div>
    </div>

    </div>
</section>

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

<div id="mentorRequestModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeRequestModal()">&times;</span>
    <h3 id="modalTitle">Mentor Request Form</h3>
    <form method="POST" action="courses.php">
      <div class="form-group">
        <label for="request_type">Request Type:</label>
        <select id="request_type" name="request_type" required onchange="toggleCourseSelection(this.value)">
          <option value="">-- Select Request Type --</option>
          <option value="Resignation">Resignation (Complete Withdrawal)</option>
          <option value="Course Change">Course Change (Appeal to be assigned a different course)</option>
        </select>
      </div>

      <div class="form-group" id="new_course_id_group">
        <label for="new_course_id">Course to Move To:</label>
        <select id="new_course_id" name="new_course_id">
          <option value="">-- Select Available Course --</option>
          <?php if (!empty($availableCourses)): ?>
            <?php foreach($availableCourses as $course): ?>
              <option value="<?= htmlspecialchars($course['Course_ID']) ?>">
                  <?= htmlspecialchars($course['Course_Title']) ?> (<?= htmlspecialchars($course['Skill_Level']) ?>)
              </option>
            <?php endforeach; ?>
          <?php else: ?>
            <option value="" disabled>No courses currently available for assignment.</option>
          <?php endif; ?>
        </select>
        <small style="color:#888;">Select the new course you wish to be assigned to (only available courses are shown).</small>
      </div>

      <div class="form-group">
        <label for="reason">Reason for Request:</label>
        <textarea id="reason" name="reason" rows="5" placeholder="Clearly state your reason for the request." required></textarea>
      </div>

      <button type="submit" name="submit_request" class="modal-submit-btn">Submit Request</button>
    </form>
  </div>
</div>
<?php
  // Close the connection
  $conn->close();
?>

<script src="admin.js"></script>
<script src="js/navigation.js"></script>
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

  <script>
    // JavaScript for Modal
    const modal = document.getElementById("mentorRequestModal");
    const requestTypeSelect = document.getElementById("request_type");
    // Updated IDs for new logic
    const newCourseIdGroup = document.getElementById("new_course_id_group");
    const newCourseIdSelect = document.getElementById("new_course_id");
    const modalTitle = document.getElementById("modalTitle");

    function openRequestModal(type = '') {
      modal.style.display = "block";
      
      requestTypeSelect.value = type;
      
      if (type === 'Resignation') {
          modalTitle.textContent = 'Mentor Resignation Request';
          newCourseIdGroup.style.display = 'none';
          newCourseIdSelect.removeAttribute('required'); // Course ID not required for resignation
      } else if (type === 'Course Change') {
          modalTitle.textContent = 'Course Change Appeal';
          newCourseIdGroup.style.display = 'block';
          newCourseIdSelect.setAttribute('required', 'required'); // Course ID required for change
      } else {
          modalTitle.textContent = 'Mentor Request Form';
          newCourseIdGroup.style.display = 'none';
          newCourseIdSelect.removeAttribute('required');
      }
    }

    function closeRequestModal() {
      modal.style.display = "none";
    }

    // Close the modal if the user clicks anywhere outside of it
    window.onclick = function(event) {
      if (event.target == modal) {
        closeRequestModal();
      }
    }
    
    function toggleCourseSelection(type) {
        if (type === 'Course Change') {
            newCourseIdGroup.style.display = 'block';
            newCourseIdSelect.setAttribute('required', 'required');
        } else {
            newCourseIdGroup.style.display = 'none';
            newCourseIdSelect.removeAttribute('required');
        }

        if (type === 'Resignation') {
            modalTitle.textContent = 'Mentor Resignation Request';
        } else if (type === 'Course Change') {
            modalTitle.textContent = 'Course Change Appeal';
        } else {
            modalTitle.textContent = 'Mentor Request Form';
        }
    }

    // Function to confirm logout (required to make the navbar link functional)
    function confirmLogout(event) {
        event.preventDefault(); // Prevent default link behavior
        document.getElementById('logoutDialog').style.display = 'block';
    }

    document.getElementById('cancelLogout').onclick = function() {
        document.getElementById('logoutDialog').style.display = 'none';
    };

    document.getElementById('confirmLogoutBtn').onclick = function() {
        window.location.href = '../logout.php'; 
    };

    
  </script>
</body>
</html>