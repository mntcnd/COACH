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
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    header("Location: ../login.php");
    exit();
}

// --- FETCH USER ACCOUNT AND CONNECTION ---
require '../connection/db_connection.php';

if (!isset($_SESSION['username'])) {
  header("Location: ../login.php"); 
  exit();
}

$menteeUserId = $_SESSION['user_id'];
$firstName = '';
$menteeIcon = '';
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : ""; // Get selected category

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
$stmt->close();

// --- CONSTANTS ---
// Passing threshold is 70% of total questions
$PASSING_PERCENTAGE_THRESHOLD = 70;


// --- QUOTE LOGIC ---
$quotes = [
    '“Small progress each day adds up to big results.”',
    '“Every attempt is a step closer to success.”',
    '“Mistakes are proof you’re trying and learning.”',
    '“Consistency beats perfection—keep moving forward!”',
    '“Learning never stops; each challenge unlocks your potential.”',
    '“Your future self will thank you for not giving up today.”',
    '“Difficult roads often lead to beautiful destinations.”',
    '“Every expert was once where you are—don’t stop now!”',
    '“It doesn’t matter how slowly you go, as long as you don’t stop.”',
    '“You’re building skills today that will shape tomorrow.”'
];
$randomQuoteKey = array_rand($quotes);
$encouragementTip = $quotes[$randomQuoteKey];


// ==========================================================
// --- FIXED: Dynamic Course & Activity List for Filtering ---
// ==========================================================
$activityTitlesForDropdown = [];
$allCourseTitles = [];

// 1. Fetch distinct course titles from session_bookings (as requested)
$sqlCourses = "
    SELECT DISTINCT Course_Title 
    FROM session_bookings 
    WHERE user_id = ?
    ORDER BY Course_Title ASC
";
$stmtCourses = $conn->prepare($sqlCourses);
$stmtCourses->bind_param("i", $menteeUserId);
$stmtCourses->execute();
$resultCourses = $stmtCourses->get_result();
while ($row = $resultCourses->fetch_assoc()) {
    $allCourseTitles[] = $row['Course_Title'];
}
$stmtCourses->close();

// 2. Fetch distinct activity titles from activities where the mentee has submissions
$sqlActivities = "
    SELECT DISTINCT a.Activity_Title
    FROM activities a
    JOIN submissions s ON a.Activity_ID = s.Activity_ID
    WHERE s.Mentee_ID = ?
    ORDER BY a.Activity_Title ASC
";
$stmtActivities = $conn->prepare($sqlActivities);
$stmtActivities->bind_param("i", $menteeUserId);
$stmtActivities->execute();
$resultActivities = $stmtActivities->get_result();
while ($row = $resultActivities->fetch_assoc()) {
    $activityTitlesForDropdown[] = $row['Activity_Title'];
}
$stmtActivities->close();

$activityTitles = $activityTitlesForDropdown; // Use the dynamically fetched list for filtering logic


// --- Determine Filter Type ---
$isCourseFilter = !empty($selectedCategory) && in_array($selectedCategory, $allCourseTitles);
$isActivityFilter = !empty($selectedCategory) && in_array($selectedCategory, $activityTitles);
$isAllSelected = empty($selectedCategory);


// ==========================================================
// --- PROGRESS CIRCLES CALCULATIONS (FIXED BINDING) ---
// ==========================================================

// Revised base query to include ALL attempts for a mentee
// Parameter: s.Mentee_ID = ?
$baseMaxScoreQuery = "
    SELECT 
        c.Course_Title, 
        a.Activity_Title, 
        s.Activity_ID,
        s.Final_Score as max_final_score, -- Use actual attempt score
        a.Questions_JSON
    FROM submissions s
    JOIN activities a ON s.Activity_ID = a.Activity_ID
    JOIN courses c ON a.Course_ID = c.Course_ID
    WHERE s.Mentee_ID = ?
    -- REMOVED: GROUP BY and MAX() because we want to count every attempt
";


// --- 1. Total Passed Activities Circle (Box 1) ---
// Parameters: 1. PASSING_PERCENTAGE_THRESHOLD, 2. menteeUserId, 3. (Optional) Filter Value
$passedParams = [$PASSING_PERCENTAGE_THRESHOLD, $menteeUserId]; 
$passedTypes = "ii"; // 'i' for threshold, 'i' for menteeUserId (assuming integer types)

// WARNING: Ensure $baseMaxScoreQuery is a secured, prepared string/view name.
$sqlTotalPassed = "
    SELECT 
        SUM(CASE 
            WHEN (T.max_final_score / 
                JSON_LENGTH(T.Questions_JSON) * 100) >= ?
            THEN 1 
            ELSE 0 
        END) as total_passed
    FROM (
        " . $baseMaxScoreQuery . "
    ) as T
    WHERE 1=1
";

if ($isCourseFilter) {
    $sqlTotalPassed .= " AND T.Course_Title = ?";
    $passedParams[] = $selectedCategory;
    $passedTypes .= "s"; // 's' for Course_Title (string)
} elseif ($isActivityFilter) {
    $sqlTotalPassed .= " AND T.Activity_Title = ?";
    $passedParams[] = $selectedCategory;
    $passedTypes .= "s"; // 's' for Activity_Title (string)
}

$stmtPassedAll = $conn->prepare($sqlTotalPassed);
if ($stmtPassedAll) {
    // FIX: This dynamic binding logic is necessary for PHP's bind_param
    $bind_names = [$passedTypes];
    for ($i = 0; $i < count($passedParams); $i++) {
        // Create unique variable names (p_passed_0, p_passed_1, etc.)
        $var_name = 'p_passed_' . $i; 
        $$var_name = $passedParams[$i];
        $bind_names[] = &$$var_name;
    }
    call_user_func_array([$stmtPassedAll, 'bind_param'], $bind_names);
    
    $stmtPassedAll->execute();
    $resPassedAll = $stmtPassedAll->get_result();

    $totalPassed = 0;
    if ($row = $resPassedAll->fetch_assoc()) {
        $totalPassed = (int)$row['total_passed'];
    }
    $stmtPassedAll->close();
} else {
    // Handle prepare error
    $totalPassed = 0;
}

$passedVisualPercent = min($totalPassed * 10, 100);


// --- 2. Overall/Activity Metrics (Boxes 2 & 3) ---
$overallProgressPercent = 0;
$overallAvgPercent = 0;

// Parameters: 1. PASSING_PERCENTAGE_THRESHOLD, 2. menteeUserId, 3. (Optional) Filter Value
$allFilterParams = [$PASSING_PERCENTAGE_THRESHOLD, $menteeUserId];
$allFilterTypes = "ii";

$sqlAllData = "
    SELECT 
        COUNT(T.Activity_ID) as total_distinct_available,
        SUM(CASE 
            WHEN (T.max_final_score / 
                  JSON_LENGTH(T.Questions_JSON) * 100) >= ? 
            THEN 1 
            ELSE 0 
        END) as total_distinct_passed,
        SUM(T.max_final_score) as total_max_score_sum,
        SUM(JSON_LENGTH(T.Questions_JSON)) as total_questions_sum
    FROM (
        " . $baseMaxScoreQuery . "
    ) as T
    WHERE 1=1
";


if ($isCourseFilter) {
    $sqlAllData .= " AND T.Course_Title = ?";
    $allFilterParams[] = $selectedCategory;
    $allFilterTypes .= "s";
} elseif ($isActivityFilter) {
    $sqlAllData .= " AND T.Activity_Title = ?";
    $allFilterParams[] = $selectedCategory;
    $allFilterTypes .= "s";
}

$stmtAllData = $conn->prepare($sqlAllData);

if ($stmtAllData) {
    // FIX: Robust dynamic binding using variable variables to ensure parameters are passed by reference
    $bind_names = [$allFilterTypes];
    for ($i = 0; $i < count($allFilterParams); $i++) {
        $var_name = 'p_all_' . $i; 
        $$var_name = $allFilterParams[$i];
        $bind_names[] = &$$var_name;
    }
    call_user_func_array([$stmtAllData, 'bind_param'], $bind_names);

    $stmtAllData->execute();
    $resultAllData = $stmtAllData->get_result();

    if ($row = $resultAllData->fetch_assoc()) {
        $totalDistinctPassed = (int)$row['total_distinct_passed'];
        $totalDistinctAvailable = (int)$row['total_distinct_available'];
        $totalMaxScoreSum = (int)$row['total_max_score_sum'];
        $totalQuestionsSum = (int)$row['total_questions_sum'];

        // OVERALL PROGRESS (Completion Circle)
        // Rationale: Calculate the percentage of activities PASSED (score >= PASSING_PERCENTAGE_THRESHOLD) 
        // out of the total distinct activities AVAILABLE/ATTEMPTED. 
        // This is a robust definition of "completion" for the provided data structure.
        $totalDistinctAvailable = max(1, $totalDistinctAvailable); // Prevent division by zero
        $overallProgressPercent = round(($totalDistinctPassed / $totalDistinctAvailable) * 100); 
        
        // AVERAGE PERFORMANCE
        // Rationale: The average performance is the sum of best scores (total correct questions) 
        // divided by the sum of total questions (total possible score). 
        // If the underlying data is correct (max_final_score <= total_questions), 
        // this percentage CANNOT exceed 100%.
        $overallAvgPercent = ($totalQuestionsSum > 0) 
                            ? round(($totalMaxScoreSum / $totalQuestionsSum) * 100, 1) 
                            : 0;
        
        // --- CHECK/FIX for AVERAGE PERFORMANCE over 100% ---
        // While the formula prevents a value > 100% mathematically, if data is corrupted 
        // (e.g., total_max_score_sum > total_questions_sum), this ensures it's capped at 100.0%.
        $overallAvgPercent = min(100.0, $overallAvgPercent);
    }
    $stmtAllData->close();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/taskprogresstyle.css" /> 
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>My Progress</title>
  <style>
        /* Styles for Modal and Table Button (Kept for standalone integrity) */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .table-container table td button {
            background-color: #5c087d;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
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

  <div class="content-wrapper">
    <h1 style="text-align:center; margin-bottom: 20px;">Progress Tracker</h1>

    <div class="top-section">
      <div class="info-box profile-box">
        <img src="<?php echo !empty($menteeIcon) ? htmlspecialchars($menteeIcon) : 'https://via.placeholder.com/100'; ?>" alt="Profile" width="100" height="100">
        <h3><?php echo htmlspecialchars($firstName ?? 'Name'); ?></h3>
      </div>

      <div class="progress-info">
        <div class="info-box">
          <h4>Category</h4>
          <form method="GET" action="">
            <select name="category" onchange="this.form.submit()">
              <option value="" <?= ($selectedCategory == '') ? 'selected' : '' ?>>All</option>
              
              <optgroup label="Courses">
                <?php
                foreach ($allCourseTitles as $courseTitle) {
                    $courseTitle = htmlspecialchars($courseTitle);
                    $selected = ($selectedCategory == $courseTitle) ? "selected" : "";
                    echo "<option value='$courseTitle' $selected>$courseTitle</option>";
                }
                ?>
              </optgroup>

              <optgroup label="Activities">
                <?php
                foreach ($activityTitles as $activityTitle) {
                    $activityTitleHtml = htmlspecialchars($activityTitle);
                    $selected = ($selectedCategory == $activityTitle) ? "selected" : "";
                    echo "<option value='$activityTitleHtml' $selected>$activityTitleHtml</option>";
                }
                ?>
              </optgroup>
            </select>
          </form>
        </div>

        <div class="info-box">
          <div class="circular-progress" style="--percent: <?= $passedVisualPercent ?>%;">
            <span class="progress-value"><?= $totalPassed ?></span>
          </div>
          <div class="label">PASSED ACTIVITIES</div>
        </div>

        <div class="info-box">
          <div class="circle" style="--percent: <?= $overallProgressPercent ?>%;">
            <span><?= $overallProgressPercent ?>%</span>
          </div>
          <div class="label">OVERALL COMPLETION</div>
        </div>

        <div class="info-box text-box">
          <div class="metric-display">
            <span class="main-metric" style="color:#5c087d;"><?= $overallAvgPercent ?>%</span>
          </div>
          <div class="label">AVERAGE PERFORMANCE</div>
        </div>

        <div class="info-box text-box">
          <div class="text-tip">
            <?php echo htmlspecialchars($encouragementTip); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Course</th>
            <th>Lesson</th>
            <th>Activity</th>
            <th>Attempt</th>
            <th>Date Taken</th>
            <th>Score</th>
            <th>Final Score</th>
            <th>Feedback</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // --- Table Filtering Logic ---
          
          $tableParams = [$menteeUserId];
          $tableTypes = "i";
          $sqlBase = "
              SELECT 
                  c.Course_Title, 
                  a.Lesson, 
                  a.Activity_Title, 
                  s.Attempt_Number, 
                  s.Score AS Raw_Score, 
                  s.Final_Score, 
                  s.Feedback, 
                  a.Questions_JSON,
                  s.Submitted_At 
              FROM submissions s 
              JOIN activities a ON s.Activity_ID = a.Activity_ID
              JOIN courses c ON a.Course_ID = c.Course_ID
              WHERE s.Mentee_ID = ?";
          
          if (!empty($selectedCategory)) {
              $filterValue = $selectedCategory;

              if ($isCourseFilter) {
                  $sqlBase .= " AND c.Course_Title = ?";
                  $tableParams[] = $filterValue;
                  $tableTypes .= "s";

              } elseif ($isActivityFilter) {
                  $sqlBase .= " AND a.Activity_Title = ?";
                  $tableParams[] = $filterValue;
                  $tableTypes .= "s";
              }
          }
          
          $sqlBase .= " ORDER BY s.Submitted_At DESC";

          $stmt = $conn->prepare($sqlBase);
          
          if ($stmt) {
              // FIX: Robust dynamic binding using variable variables to ensure parameters are passed by reference
              $bind_names = [$tableTypes];
              for ($i=0; $i<count($tableParams); $i++) {
                  $var_name = 'p_table_' . $i; 
                  $$var_name = $tableParams[$i];
                  $bind_names[] = &$$var_name;
              }
              call_user_func_array([$stmt, 'bind_param'], $bind_names);
          
              $stmt->execute();
              $result = $stmt->get_result();

              if ($result->num_rows > 0) {
                  while($row = $result->fetch_assoc()) {
                      $course = htmlspecialchars($row['Course_Title']);
                      $lesson = htmlspecialchars($row['Lesson'] ?? 'N/A'); 
                      $activity = htmlspecialchars($row['Activity_Title']);
                      $attempt = (int)$row['Attempt_Number'];
                      $rawScore = $row['Raw_Score'];
                      $finalScore = $row['Final_Score'];
                      $questionsJson = $row['Questions_JSON'];
                      $dateTaken = date("m/d/y", strtotime($row['Submitted_At']));
                      $dbFeedback = $row['Feedback'];
          $feedbackForModal = empty($dbFeedback)
    ? 'No feedback provided.'
    : htmlspecialchars($dbFeedback);


                      // Calculate Total Questions from JSON
                      $questions = json_decode($questionsJson, true) ?: [];
                      $total = count($questions);
                      $total = max(1, $total); // Prevent division by zero

                      // Score display logic
                      $rawScoreDisplay = ($rawScore !== null) ? (int)$rawScore : 'N/A';

                      // Final Score display logic
                      $finalScoreDisplay = ($finalScore !== null) ? (int)$finalScore : 'N/A';
                      
                      // --- Remarks calculation based on Mentor's Final Score ---
                      $status = "<span style='color:gray; font-weight:500;'>Pending</span>";
                      
                      if ($finalScore !== null) { // Check if Mentor has provided a final score
                        $finalScoreInt = (int)$finalScore;
                        // Calculate percentage based on Mentor's Final Score
                        $scorePercent = ($finalScoreInt / $total) * 100;

                        // Use the 70% threshold as the passing criteria for Remarks
                        if ($scorePercent >= $PASSING_PERCENTAGE_THRESHOLD) {
                            $status = "<span style='color:purple; font-weight:500;'>Passed</span>";
                        } else {
                            $status = "<span style='color:red; font-weight:500;'>Failed</span>";
                        }
                      }


                      echo "<tr>
                              <td>$course</td>
                              <td>$lesson</td>
                              <td>$activity</td>
                              <td>Attempt #$attempt</td>
                              <td>$dateTaken</td>
                              <td>$rawScoreDisplay</td>
                              <td>$finalScoreDisplay</td>
                              <td><button onclick=\"showFeedback('".addslashes($feedbackForModal)."')\">View</button></td>
                              <td>$status</td>
                            </tr>";
                  }
              } else {
                  echo "<tr><td colspan='9'>No records found.</td></tr>";
              }
          
              $stmt->close();
          } else {
              // Handle prepare error
              echo "<tr><td colspan='9'>Error preparing database query for table.</td></tr>";
          }
          
          if ($conn->ping()) {
            $conn->close();
          }
          ?>
        </tbody>
      </table>
    </div>

    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeFeedbackModal()">&times;</span>
            <h2>Mentor Feedback</h2>
            <p id="modalFeedbackText"></p>
        </div>
    </div>
    <script src="js/mentee.js"></script>
    <script>
    // Global functions for Modal
    function showFeedback(feedbackText) {
        document.getElementById('modalFeedbackText').innerText = feedbackText;
        document.getElementById('feedbackModal').style.display = "block";
    }

    function closeFeedbackModal() {
        document.getElementById('feedbackModal').style.display = "none";
    }

    // Close the modal if the user clicks anywhere outside of it
    window.onclick = function(event) {
        var modal = document.getElementById('feedbackModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const profileIcon = document.getElementById("profile-icon");
        const profileMenu = document.getElementById("profile-menu");
        const logoutDialog = document.getElementById("logoutDialog");
        const cancelLogoutBtn = document.getElementById("cancelLogout");
        const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

        if (profileIcon && profileMenu) {
            profileIcon.addEventListener("click", function (e) {
                e.preventDefault();
                profileMenu.classList.toggle("show");
                profileMenu.classList.toggle("hide");
            });
            
            document.addEventListener("click", function (e) {
                if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                    profileMenu.classList.remove("show");
                    profileMenu.classList.add("hide");
                }
            });
        }

        window.confirmLogout = function(e) { 
            if (e) e.preventDefault();
            if (logoutDialog) {
                logoutDialog.style.display = "flex";
            }
        }

        if (cancelLogoutBtn && logoutDialog) {
            cancelLogoutBtn.addEventListener("click", function(e) {
                e.preventDefault(); 
                logoutDialog.style.display = "none";
            });
        }

        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener("click", function(e) {
                e.preventDefault(); 
                window.location.href = "../logout.php"; 
            });
        }
    });
    </script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

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