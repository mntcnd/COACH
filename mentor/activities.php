<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// *** AJAX Handler for Grade Update (Step 3 in Prompt) ***
if (isset($_POST['update_grade']) && isset($_POST['submission_id'])) {
    
    // Ensure database connection is available
    require '../connection/db_connection.php'; 

    $submission_id = (int)$_POST['submission_id'];
    $new_score = (float)$_POST['final_score'];
    // Use 'overall_feedback' field from the form
    $feedback = trim($_POST['overall_feedback']); 
    $mentor_id = $_SESSION['user_id']; // For security check

    // Check if the submission exists and belongs to an activity created by this mentor
    $check_sql = "
        SELECT s.Submission_ID
        FROM submissions s
        JOIN activities a ON s.Activity_ID = a.Activity_ID
        WHERE s.Submission_ID = ? AND a.Mentor_ID = ?
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $submission_id, $mentor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Submission not found or unauthorized.']);
        $check_stmt->close();
        $conn->close();
        exit;
    }
    $check_stmt->close();

    // Perform the update
    $update_sql = "
        UPDATE submissions 
        SET Final_Score = ?, Feedback = ?, Submission_Status = 'Graded', Reviewed_At = NOW()
        WHERE Submission_ID = ?
    ";
    $stmt = $conn->prepare($update_sql);
    
    // 'dsi': d=double (float), s=string, i=integer
    $stmt->bind_param("dsi", $new_score, $feedback, $submission_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Grade updated successfully. Submission Status set to Graded.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit; // Stop further page execution
}
// *** END AJAX Handler ***

// *** Configuration and Setup ***
date_default_timezone_set('Asia/Manila');
// NOTE: Ensure this path is correct for your setup.
require '../connection/db_connection.php'; 

// SESSION CHECK: Verify user is logged in and is a Mentor
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentor') {
    header("Location: ../login.php"); 
    exit();
}

$mentor_id = $_SESSION['user_id'];
$mentor_username = $_SESSION['username'];
$message = '';
$message_type = ''; // 'success', 'error', 'warning'

// --- Helper Functions and Data Fetching ---

// Fetch current Mentor's details and construct the full name for filtering
$mentorName = $_SESSION['mentor_name'] ?? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'Mentor User'); 
$mentorIcon = $_SESSION['mentor_icon'] ?? '../uploads/img/default_pfp.png';

// 🚨 FIX: Filter courses by Assigned_Mentor column using the current mentor's full name
$assignedCourses = [];
// Query fetches courses where Assigned_Mentor matches the current mentor's full name
$sql = "SELECT Course_ID, Course_Title FROM courses WHERE Assigned_Mentor = ? ORDER BY Course_Title ASC";

if ($stmt = $conn->prepare($sql)) {
    // Bind the mentor's full name as the filter (string 's')
    $stmt->bind_param("s", $mentorName);
    $stmt->execute();
    $coursesResult = $stmt->get_result();

    if ($coursesResult) {
        while ($courseRow = $coursesResult->fetch_assoc()) {
            $assignedCourses[] = $courseRow;
        }
    }
    $stmt->close();
} else {
    // Log the error if the query fails to prepare
    error_log("Failed to prepare course query: " . $conn->error);
}

// Fetch all mentees for the single/multi-select dropdown
$mentee_list = [];
$mentee_sql = "
    SELECT user_id, CONCAT(first_name, ' ', last_name) AS full_name
    FROM users 
    WHERE user_type = 'Mentee'
    ORDER BY full_name
";
$mentee_result = $conn->query($mentee_sql);
if ($mentee_result) {
    while ($row = $mentee_result->fetch_assoc()) {
        $mentee_list[] = $row;
    }
}

// --- Data Management and Action Handling ---

// 1. CREATE / EDIT / RESUBMIT
if (isset($_POST['submit_activity'])) {

// 🚨 DEBUG CHECK: Ensure connection is valid before submission attempt
if (!isset($conn) || !$conn) {
    $message = "❌ Database Connection Error: Cannot connect to the database. Submission aborted.";
    $message_type = 'error';
} elseif (empty($_POST['course_id']) || empty($_POST['lesson']) || empty($_POST['activity_title']) || empty($_POST['questions_json'])) {
    // Input validation: Check if required POST fields are present and not empty
    $message = "❌ Submission failed: Please ensure all required fields (Course, Lesson, Title, and at least one question) are filled.";
    $message_type = 'error';
} else {
    // ✅ Continue with submission logic here...

        
        $is_edit = isset($_POST['activity_id']) && !empty($_POST['activity_id']);
        $activity_id = $is_edit ? (int)$_POST['activity_id'] : null;

        $course_id = (int)$_POST['course_id'];
        $lesson = trim($_POST['lesson']);
        $activity_title = trim($_POST['activity_title']);
        $activity_type = 'Combined'; // Fixed value based on your form structure
        $questions_json = $_POST['questions_json']; // CAPTURES THE JSON STRING
        
        $current_file_path = $_POST['current_file_path'] ?? null; 
        $new_file_path = $current_file_path;
        $message = '';
        $message_type = '';

        // File upload handling
        if (isset($_FILES['activity_file']) && $_FILES['activity_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['activity_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
            $max_size = 50 * 1024 * 1024; // 50MB
            
            if (in_array($ext, $allowed_ext) && $file['size'] <= $max_size) {
                $upload_dir = '../uploads/activities/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $new_file_name = uniqid('act_') . '.' . $ext;
                $destination = $upload_dir . $new_file_name;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $new_file_path = $destination;
                } else {
                    $message = "❌ Error uploading file.";
                    $message_type = 'error';
                }
            } else {
                $message = "❌ Invalid file type or size (Max 50MB, PDF/Image only).";
                $message_type = 'error';
            }
        }
        
        // <--- FIXED: Closing brace for File Upload Handling
        if ($message_type !== 'error') {
            if ($is_edit) {
                // UPDATE: Questions_JSON is included here.
                $sql = "UPDATE activities SET Course_ID=?, Lesson=?, Activity_Title=?, Activity_Type=?, Questions_JSON=?, File_Path=?, Status='Pending', Admin_Remarks=NULL, Created_At=NOW() WHERE Activity_ID=? AND Mentor_ID=?";
                $stmt = $conn->prepare($sql);
                // Correct binding types (i=int, s=string): i (Course_ID) sssss (Lesson, Title, Type, Questions, File_Path) ii (Activity_ID, Mentor_ID)
                $stmt->bind_param("isssssii", $course_id, $lesson, $activity_title, $activity_type, $questions_json, $new_file_path, $activity_id, $mentor_id);
                if ($stmt->execute()) {
                    $message = "✅ Activity updated and resubmitted for Admin Approval!";
                    $message_type = 'success';
                    $active_tab = 'pending';
                } else {
                    // This will display the exact MySQL error if the query fails
                    $message = "❌ Error updating activity: " . $conn->error;
                    $message_type = 'error';
                }
            } else {
                // CREATE: Questions_JSON is included here.
                $sql = "INSERT INTO activities (
                    Mentor_ID, 
                    Course_ID, 
                    Lesson, 
                    Activity_Title, 
                    Activity_Type, 
                    Questions_JSON, 
                    File_Path, 
                    Status, 
                    Admin_Remarks, 
                    Created_At
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NULL, NOW())";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisssss", 
                    $mentor_id, 
                    $course_id, 
                    $lesson, 
                    $activity_title, 
                    $activity_type, 
                    $questions_json, 
                    $new_file_path
                );

                if ($stmt->execute()) {
                    echo "<script>console.log('✅ Insert successful');</script>";
                    $message = "✅ Activity created successfully!";
                    $message_type = 'success';
                    $active_tab = 'pending';
                } else {
                    echo "<script>console.log('❌ Insert failed: " . addslashes($stmt->error) . "');</script>";
                    $message = "❌ Database Error: " . $stmt->error;
                    $message_type = 'error';
                }
            }
            $stmt->close();
        }
    }
}

// 2. DELETE Activity (from Pending Tab) 
if (isset($_POST['delete_activity_id'])) {
    $activity_id = (int)$_POST['delete_activity_id'];
    
    $conn->begin_transaction();
    try {
        // Delete related submissions
        $stmt = $conn->prepare("DELETE FROM submissions WHERE Activity_ID = ?");
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        
        // Delete assigned activities
        $stmt = $conn->prepare("DELETE FROM assigned_activities WHERE Activity_ID = ?");
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        
        // Delete the activity itself (must be owned by the mentor)
        $stmt = $conn->prepare("DELETE FROM activities WHERE Activity_ID = ? AND Mentor_ID = ?");
        $stmt->bind_param("ii", $activity_id, $mentor_id);
        $stmt->execute();
        
        $conn->commit();
        $message = "✅ Activity deleted.";
        $message_type = 'success';
        $active_tab = 'pending'; 
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $message = "❌ Error deleting activity: " . $e->getMessage();
        $message_type = 'error';
        $active_tab = 'pending';
    }
}

// 3. ASSIGN Activity (from Approved Tab) 
if (isset($_POST['assign_activity'])) {
    $activity_id = (int)$_POST['assign_activity_id'];
    $mentee_ids = $_POST['mentee_ids'] ?? [];

    if (empty($mentee_ids)) {
        $message = "⚠️ Please select at least one mentee to assign the activity to.";
        $message_type = 'warning';
        $active_tab = 'approved';
    } else {
        $assigned_count = 0;
        $already_assigned_count = 0;
        $not_enrolled_count = 0;
        $failed_count = 0;

        $conn->begin_transaction();

        try {
            // --- 1. Get Course Title associated with the Activity (No Change Here) ---
            // Joins activities (using Course_ID) with courses to get the required Course_Title.
            $course_sql = "
                SELECT c.Course_Title 
                FROM activities a
                JOIN courses c ON a.Course_ID = c.Course_ID
                WHERE a.Activity_ID = ?";
            
            $course_stmt = $conn->prepare($course_sql);
            $course_stmt->bind_param("i", $activity_id);
            $course_stmt->execute();
            $course_result = $course_stmt->get_result();
            $activity_course = $course_result->fetch_assoc();
            $course_stmt->close();

            if (!$activity_course || empty($activity_course['Course_Title'])) {
                throw new Exception("Activity ID $activity_id is not linked to a valid Course_Title, assignment aborted.");
            }

            $required_course_title = $activity_course['Course_Title'];

            // --- Prepared statements for the loops ---

            // 2. Check Enrollment: (UPDATED COLUMN NAMES) 
            // Checks if mentee (user_id) has an active booking for the required course_title.
            $enrollment_sql = "
                SELECT booking_id 
                FROM session_bookings 
                WHERE user_id = ? AND course_title = ? AND status IN ('booked', 'approved')";
            $enrollment_stmt = $conn->prepare($enrollment_sql);

            // 3. Check Existing Assignment (No Change Here)
            $check_assignment_sql = "
                SELECT Assign_ID 
                FROM assigned_activities 
                WHERE Activity_ID = ? AND Mentee_ID = ?";
            $check_assignment_stmt = $conn->prepare($check_assignment_sql);

            // 4. Insertion (No Change Here)
            $insert_sql = "
                INSERT INTO assigned_activities (Activity_ID, Mentee_ID, Date_Assigned) 
                VALUES (?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);

            // Track successfully assigned mentees for SMS
            $successfully_assigned_mentees = [];

            foreach ($mentee_ids as $mentee_id) {
                $mentee_id = (int)$mentee_id;
                $can_assign = true;
                $reason_skipped = '';

                // --- A. Check for Enrollment in Session Booking ---
                // Bind mentee_id (i) and required_course_title (s)
                $enrollment_stmt->bind_param("is", $mentee_id, $required_course_title); 
                $enrollment_stmt->execute();
                $enrollment_result = $enrollment_stmt->get_result();

                if ($enrollment_result->num_rows === 0) {
                    $not_enrolled_count++;
                    $can_assign = false;
                    $reason_skipped = 'Not enrolled';
                }

                // --- B. Check for Existing Assignment (Only if enrolled) ---
                if ($can_assign) {
                    $check_assignment_stmt->bind_param("ii", $activity_id, $mentee_id);
                    $check_assignment_stmt->execute();
                    $check_assignment_result = $check_assignment_stmt->get_result();

                    if ($check_assignment_result->num_rows > 0) {
                        $already_assigned_count++;
                        $can_assign = false;
                        $reason_skipped = 'Already assigned';
                    }
                }

                // --- C. Perform Assignment ---
                if ($can_assign) {
                    $insert_stmt->bind_param("ii", $activity_id, $mentee_id);
                    if ($insert_stmt->execute()) {
                        $assigned_count++;
                        $successfully_assigned_mentees[] = $mentee_id; // Add to SMS list
                    } else {
                        $failed_count++;
                        error_log("Failed to assign activity $activity_id to mentee $mentee_id: " . $insert_stmt->error);
                    }
                }
            }

            // Close all prepared statements
            $enrollment_stmt->close();
            $check_assignment_stmt->close();
            $insert_stmt->close();

            // ------------------------------------------------------------------
            // --- Transaction and Message Handling ---
            // ------------------------------------------------------------------
            if ($assigned_count > 0) {
                $conn->commit();
                
                // ========== SEND SMS NOTIFICATION ==========
                if (!empty($successfully_assigned_mentees)) {
                    $sms_data = [
                        'mentee_ids' => $successfully_assigned_mentees,
                        'activity_id' => $activity_id
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "send_activity_sms.php");
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sms_data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    
                    $sms_response = curl_exec($ch);
                    curl_close($ch);
                    
                    $sms_result = json_decode($sms_response, true);
                    if ($sms_result && $sms_result['success']) {
                        error_log("SMS sent successfully for activity $activity_id to " . count($successfully_assigned_mentees) . " mentee(s)");
                    } else {
                        error_log("SMS failed for activity $activity_id: " . ($sms_result['message'] ?? 'Unknown error'));
                    }
                }
                // ========== END SMS NOTIFICATION ==========
                
                $message = "✅ Activity assigned to $assigned_count new mentee(s).";
                $message_type = 'success';
                
                $summary_parts = [];
                if ($already_assigned_count > 0) {
                    $summary_parts[] = "$already_assigned_count already assigned";
                    $message_type = 'warning';
                }
                if ($not_enrolled_count > 0) {
                    $summary_parts[] = "$not_enrolled_count skipped (Not enrolled in $required_course_title)";
                    $message_type = 'warning';
                }

                if (!empty($summary_parts)) {
                    $message .= " (". implode(", ", $summary_parts) . ").";
                }

            } elseif ($already_assigned_count > 0 || $not_enrolled_count > 0) {
                $conn->rollback(); 
                $message = "⚠️ No new assignments were made. ";
                if ($already_assigned_count > 0) {
                    $message .= "$already_assigned_count mentee(s) were already assigned. ";
                }
                if ($not_enrolled_count > 0) {
                    $message .= "$not_enrolled_count mentee(s) skipped due to lack of enrollment in $required_course_title.";
                }
                $message_type = 'warning';

            } else {
                $conn->rollback();
                $message = "❌ Assignment failed or resulted in 0 successful assignments.";
                $message_type = 'error';
            }
            $active_tab = 'approved';

        } catch (Exception $e) {
            $conn->rollback();
            $message = "❌ Error during assignment: " . $e->getMessage();
            $message_type = 'error';
            $active_tab = 'approved';
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $message = "❌ Database Error during assignment: " . $e->getMessage();
            $message_type = 'error';
            $active_tab = 'approved';
        }
    }
}

// 4. PREPARE DATA FOR EDIT 
$activity_to_edit = null;
$questions_data_json = '[]'; 
if (isset($_GET['edit_activity_id'])) {
    $edit_id = (int)$_GET['edit_activity_id'];
    // Must still filter by Mentor_ID for security.
    $stmt = $conn->prepare("SELECT * FROM activities WHERE Activity_ID = ? AND Mentor_ID = ?");
    $stmt->bind_param("ii", $edit_id, $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $activity_to_edit = $result->fetch_assoc();
        $questions_data_json = $activity_to_edit['Questions_JSON'] ?? '[]';
        $active_tab = 'create'; 
    }
    $stmt->close();
}

// --- Fetch Tab Data for Display ---
// (All display queries must still filter by Mentor_ID to show only the logged-in mentor's content)
$mentor_id = $_SESSION['user_id'];
if (isset($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
    
    // Pending Activities
    $pendingActivities = [];
    $pending_sql = "SELECT a.*, c.Course_Title FROM activities a JOIN courses c ON a.Course_ID = c.Course_ID WHERE a.Mentor_ID = ? AND a.Status = 'Pending' ORDER BY a.Created_At DESC";
    $stmt = $conn->prepare($pending_sql);
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $pendingResult = $stmt->get_result();
    while ($row = $pendingResult->fetch_assoc()) { $pendingActivities[] = $row; }
    $stmt->close();
    
    // Approved Activities
    $approvedActivities = [];
    $approved_sql = "
        SELECT a.*, c.Course_Title, 
               (SELECT COUNT(DISTINCT Mentee_ID) FROM assigned_activities aa WHERE aa.Activity_ID = a.Activity_ID) as Assigned_Count
        FROM activities a 
        JOIN courses c ON a.Course_ID = c.Course_ID 
        WHERE a.Mentor_ID = ? AND a.Status = 'Approved' 
        ORDER BY a.Created_At DESC
    ";
    $stmt = $conn->prepare($approved_sql);
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $approvedResult = $stmt->get_result();
    while ($row = $approvedResult->fetch_assoc()) { $approvedActivities[] = $row; }
    $stmt->close();
    
    // Rejected Activities
    $rejectedActivities = [];
    $rejected_sql = "SELECT a.*, c.Course_Title FROM activities a JOIN courses c ON a.Course_ID = c.Course_ID WHERE a.Mentor_ID = ? AND a.Status = 'Rejected' ORDER BY a.Created_At DESC";
    $stmt = $conn->prepare($rejected_sql);
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $rejectedResult = $stmt->get_result();
    while ($row = $rejectedResult->fetch_assoc()) { $rejectedActivities[] = $row; }
    $stmt->close();
    
// Submissions
$submissions = [];
$submissions_sql = "
     SELECT 
         s.Submission_ID, 
         s.Mentee_ID,
         s.File_Submission,
         s.Answers_JSON, 
         s.Score, 
         s.Feedback,
         s.Attempt_Number,
         s.Submission_Status AS Status, 
         s.Submitted_At,
         s.Final_Score,
         a.Activity_Title, 
         a.Lesson, 
         a.Questions_JSON,
         u.first_name, 
         u.last_name, 
         u.username
     FROM submissions s
     JOIN activities a ON s.Activity_ID = a.Activity_ID
     JOIN users u ON s.Mentee_ID = u.user_id 
     WHERE a.Mentor_ID = ? 
     ORDER BY u.user_id ASC, s.Attempt_Number DESC, s.Submitted_At DESC
";




$stmt = $conn->prepare($submissions_sql);
$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$submissionsResult = $stmt->get_result();
while ($row = $submissionsResult->fetch_assoc()) { 
    $fullName = trim($row['first_name'] . ' ' . $row['last_name']);
    $row['Mentee_Name'] = (!empty($fullName)) ? $fullName : $row['username'];
    $submissions[] = $row; 
}
$stmt->close();
// Note: $submissions now contains Questions_JSON, Answers_JSON, and Feedback
    
    $conn->close();
}

// Determine the initial active tab
$initial_tab = $active_tab ?? ($_GET['active_tab'] ?? 'create'); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activities | Mentor </title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/mentor_activities.css"> 
<link rel="stylesheet" href="css/courses.css" />
<link rel="stylesheet" href="css/navigation.css"/>
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<style>
/* Add this to your styles or a separate file for clear error highlighting */
.input-error { border: 2px solid #ff4d4d !important; box-shadow: 0 0 5px rgba(255, 77, 77, 0.5); }
.error-message { color: #ff4d4d; font-size: 12px; margin-top: 5px; }
</style>
</head>
<body data-initial-tab="<?= htmlspecialchars($initial_tab) ?>" data-questions-json='<?= htmlspecialchars($questions_data_json, ENT_QUOTES, 'UTF-8') ?>'>
<nav>
    <div class="nav-top">
        <div class="logo">
            <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
            <div class="logo-name">COACH</div>
        </div>
        <div class="admin-profile">
            <img src="<?php echo htmlspecialchars($mentorIcon); ?>" alt="Mentor Profile Picture" />
            <div class="admin-text">
                <span class="admin-name"><?php echo htmlspecialchars($mentorName); ?></span>
                <span class="admin-role">Mentor</span>
            </div>
            <a href="edit_profile.php?username=<?= urlencode($mentor_username) ?>" class="edit-profile-link" title="Edit Profile">
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
            <li class="navList">
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
            <li class="navList active">
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

        <div class="container">
            <h1 class="section-title">Manage Activities</h1>
            
            <?php if (!empty($message)): ?>
                <div class="inline-msg <?= htmlspecialchars($message_type) ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>


            <div class="tab-navigation">
                <div class="tabs-list">
                    <button class="tab-item" data-tab="create">
                        <i class='bx bx-plus-circle'></i> Create New
                    </button>
                    <button class="tab-item" data-tab="pending">
                        <i class='bx bx-loader-circle'></i> Pending (<?= count($pendingActivities) ?>)
                    </button>
                    <button class="tab-item" data-tab="approved">
                        <i class='bx bx-check-circle'></i> Approved (<?= count($approvedActivities) ?>)
                    </button>
                    <button class="tab-item" data-tab="rejected">
                        <i class='bx bx-x-circle'></i> Rejected (<?= count($rejectedActivities) ?>)
                    </button>
                    <button class="tab-item" data-tab="submissions">
                        <i class='bx bx-layer'></i> Submissions (<?= count($submissions) ?>)
                    </button>
                </div>
            </div>

            <div id="create-tab" class="tab-content">
                <div class="card-large">
                    <h2 class="card-title">Create Activity</h2>
                    <form id="activityForm" method="POST" action="activities.php" enctype="multipart/form-data">
                        <input type="hidden" name="activity_id" id="activity_id">
                        <input type="hidden" name="current_file_path" id="current_file_path">
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="course_id">Course:</label>
                                <select id="course_id" name="course_id" required>
                                    <option value="">Select a Course</option>
                                    <?php foreach ($assignedCourses as $course): ?>
                                        <option value="<?= htmlspecialchars($course['Course_ID']) ?>">
                                            <?= htmlspecialchars($course['Course_Title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group half">
                                <label for="lesson">Lesson:</label>
                                <input type="text" id="lesson" name="lesson" placeholder="e.g., Lesson 1: Introduction to Coaching" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="activity_title">Activity Title:</label>
                            <input type="text" id="activity_title" name="activity_title" placeholder="e.g., Module 1 Mastery Quiz" required>
                        </div>
                        
                        <h3 class="questions-heading">Activity Questions</h3>
                        <p id="question-error-msg" class="small-msg error" style="display: none;"></p>
                        <input type="hidden" name="questions_json" id="questionsJsonInput">
                        
                        <div id="questions-container">
                            </div>

                        <div class="add-question-section">
                            <button type="button" id="addQuestionBtn" class="secondary-button">
                                <i class='bx bx-plus'></i> Add Question
                            </button>
                            <span class="instruction">Add at least one question for the activity.</span>
                        </div>
                        
                        <div class="file-upload-section">
                            <label for="activity_file">Attached File (Optional):</label>
                            <input type="file" id="activity_file" name="activity_file" accept=".pdf, image/*">
                            <p class="instruction" id="file_instruction">Upload a reference file (PDF, JPG, PNG) if necessary (Max 50MB).</p>
                            <div id="currentFileDisplay" style="margin-top: 10px; display: none;">
                                <span style="font-weight: 600; color: var(--secondary-color);">Current File:</span> 
                                <a id="currentFileLink" href="#" target="_blank">View File</a>
                                <button type="button" id="removeFileBtn" class="action-btn" title="Remove current file" style="color: var(--error-color); margin-left: 10px;"><i class='bx bx-trash'></i></button>
                            </div>
                        </div>

                        <div class="form-action-buttons">
                            <button type="submit" name="submit_activity" class="gradient-button">
                                <i class='bx bx-send'></i> Submit for Approval
                            </button>
                        </div>
                    </form>
                </div>
            </div>



            <div id="pending-tab" class="tab-content">

            
                <div class="filter-area" data-tab-name="pending">
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="filter_pending_course">Filter by Course:</label>
                            <select id="filter_pending_course" class="activity-filter form-control">
                                <option value="">All Courses</option>
                                <?php foreach ($assignedCourses as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course['Course_Title']); ?>">
                                        <?php echo htmlspecialchars($course['Course_Title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group half">
                            <label for="filter_pending_search">Search Activity/Lesson:</label>
                            <input type="text" id="filter_pending_search" class="activity-filter form-control" placeholder="Title or Lesson">
                        </div>
                    </div>
                </div>


                <?php if (empty($pendingActivities)): ?>
                    <p class="small-msg warning">You have no activities currently pending admin approval.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table id="pendingTable" class="styled-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course/Lesson</th>
                                    <th>Created On</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingActivities as $activity): ?>
                                <tr>
                                    <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                                    <td data-label="Course/Lesson">
                                        <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                        <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                                    </td>
                                    <td data-label="Created On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                                    <td data-label="Status">
                                        <span class="status-tag <?= strtolower($activity['Status']) ?>">
                                            <?= htmlspecialchars($activity['Status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions" class="action-cell">
                                        <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                            data-id="<?= $activity['Activity_ID'] ?>"
                                            data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                            data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                            data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                            data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                            data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                            data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                            <i class='bx bx-show'></i>
                                        </button>
                                        <button class="action-btn edit-activity-btn" title="Edit Activity"
                                            data-id="<?= $activity['Activity_ID'] ?>"
                                            data-course-id="<?= $activity['Course_ID'] ?>"
                                            data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                            data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                            data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                            data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <button class="action-btn delete-activity-btn" title="Delete Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

                                    
            <div id="approved-tab" class="tab-content">


                <div class="filter-area" data-tab-name="approved">
        <div class="form-row">
            <div class="form-group half">
                <label for="filter_approved_course">Filter by Course:</label>
                <select id="filter_approved_course" class="activity-filter form-control">
                    <option value="">All Courses</option>
                    <?php foreach ($assignedCourses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course['Course_Title']); ?>">
                            <?php echo htmlspecialchars($course['Course_Title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group half">
                <label for="filter_approved_search">Search Activity/Lesson:</label>
                <input type="text" id="filter_approved_search" class="activity-filter form-control" placeholder="Title or Lesson">
            </div>
        </div>
    </div>
                <?php if (empty($approvedActivities)): ?>
                    <p class="small-msg warning">You have no activities currently approved by the admin.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table id= "approvedTable" class="styled-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course/Lesson</th>
                                    <th>Approved On</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvedActivities as $activity): ?>
                                <tr>
                                    <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                                    <td data-label="Course/Lesson">
                                        <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                        <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                                    </td>
                                    <td data-label="Approved On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                                    <td data-label="Status">
                                        <span class="status-tag <?= strtolower($activity['Status']) ?>">
                                            <?= htmlspecialchars($activity['Status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions" class="action-cell">
                                        <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                            data-id="<?= $activity['Activity_ID'] ?>"
                                            data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                            data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                            data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                            data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                            data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                            data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                            <i class='bx bx-show'></i>
                                        </button>
                                        <button class="action-btn edit-activity-btn" title="Edit Activity"
                                            data-id="<?= $activity['Activity_ID'] ?>"
                                            data-course-id="<?= $activity['Course_ID'] ?>"
                                            data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                            data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                            data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                            data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <button class="action-btn assign-activity-btn" title="Assign Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                            <i class='bx bx-user-plus'></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="rejected-tab" class="tab-content">
                <?php if (empty($rejectedActivities)): ?>
                    <p class="small-msg success">You have no activities that were rejected by the admin.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course/Lesson</th>
                                    <th>Rejected On</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejectedActivities as $activity): ?>
                                <tr>
                                    <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                                    <td data-label="Course/Lesson">
                                        <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                        <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                                    </td>
                                    <td data-label="Rejected On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                                    <td data-label="Remarks" class="remarks-cell">
                                        <?= nl2br(htmlspecialchars($activity['Admin_Remarks'] ?? 'No specific remarks provided.')) ?>
                                    </td>
                                    <td data-label="Actions" class="action-cell">
                                        <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                            data-id="<?= $activity['Activity_ID'] ?>"
                                            data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                            data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                            data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                            data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                            data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                            data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                            <i class='bx bx-show'></i>
                                        </button>
                                        <button class="action-btn edit-activity-btn" title="Resubmit (Edit)"
                                            data-id="<?= $activity['Activity_ID'] ?>"
                                            data-course-id="<?= $activity['Course_ID'] ?>"
                                            data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                            data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                            data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                            data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                            <i class='bx bx-refresh'></i>
                                        </button>
                                        <button class="action-btn delete-activity-btn" title="Delete Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div id="submissions-tab" class="tab-content">

<div class="filter-area" data-tab-name="submissions">
    <div class="form-row">
        <div class="form-group one-third">
            <label for="filter_submissions_mentee">Filter by Mentee:</label>
            <select id="filter_submissions_mentee" class="activity-filter form-control">
                <option value="">All Mentees</option>
                <?php 
                    // This uses the $mentee_list fetched at the top of activities.php
                    foreach ($mentee_list as $mentee): 
                ?>
                    <option value="<?php echo htmlspecialchars($mentee['full_name']); ?>">
                        <?php echo htmlspecialchars($mentee['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group one-third">
            <label for="filter_submissions_status">Filter by Status:</label>
            <select id="filter_submissions_status" class="activity-filter form-control">
                <option value="">All Statuses</option>
                <option value="Submitted">Submitted (Needs Grading)</option>
                <option value="Graded">Graded</option>
            </select>
        </div>

        <div class="form-group one-third">
            <label for="filter_submissions_search">Search Activity/Lesson:</label>
            <input type="text" id="filter_submissions_search" class="activity-filter form-control" placeholder="Activity Title or Lesson">
        </div>
    </div>
</div>


                <?php if (empty($submissions)): ?>
                    <p class="small-msg warning">No submissions have been recorded for your assigned activities yet.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table id="submissionsTable" class="styled-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Mentee</th>
                                    <th>Score</th>
                                    <th>Attempt</th>
                                    <th>Submitted At</th>
                                    <th>Final Score</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
              <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td data-label="Activity"><?= htmlspecialchars($submission['Activity_Title']) ?><br><small>Lesson: <?= htmlspecialchars($submission['Lesson']) ?></small></td>
                                    <td data-label="Mentee"><?= htmlspecialchars($submission['Mentee_Name']) ?></td>
                                    <td data-label="Score"><?= htmlspecialchars($submission['Score']) ?></td>
                                    <td data-label="Attempt"><?= htmlspecialchars($submission['Attempt_Number']) ?></td>
                                    <td data-label="Submitted At"><?= date('M d, Y h:i A', strtotime($submission['Submitted_At'])) ?></td> 
                                    <td data-label="Final_Score"><?= htmlspecialchars($submission['Final_Score'] ?? 'N/A') ?></td>
                                    <td data-label="Status">
                                        <span class="status-tag <?= strtolower($submission['Status']) ?>">
                                            <?= htmlspecialchars($submission['Status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions" class="action-cell">
                                        <button class="action-btn regrade-submission-btn" title="Review / Re-grade" 
                                            data-id="<?= $submission['Submission_ID'] ?>"
                                            data-mentee="<?= htmlspecialchars($submission['Mentee_Name']) ?>"
                                            data-activity="<?= htmlspecialchars($submission['Activity_Title']) ?>"
                                            data-lesson="<?= htmlspecialchars($submission['Lesson']) ?>"
                                            data-score="<?= htmlspecialchars($submission['Score']) ?>"
                                            data-feedback="<?= htmlspecialchars($submission['Feedback'] ?? '') ?>"
                                            data-status="<?= htmlspecialchars($submission['Status']) ?>"
                                            data-final-score="<?= htmlspecialchars($submission['Final_Score']) ?>"
                                            data-file="<?= htmlspecialchars($submission['File_Submission'] ?? '') ?>"
                                            data-questions='<?= htmlspecialchars($submission['Questions_JSON'], ENT_QUOTES, 'UTF-8') ?>'
                                            data-answers='<?= htmlspecialchars($submission['Answers_JSON'], ENT_QUOTES, 'UTF-8') ?>'>
                                            <i class='bx bx-check-shield'></i>
                                        </button>
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

    <div id="assignActivityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Activity: <span id="assignModalTitle"></span></h2>
                <button class="action-btn" data-modal-close="#assignActivityModal" title="Close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <form id="assignForm" method="POST" action="activities.php">
                <input type="hidden" name="assign_activity_id" id="activityToAssignID">
                <p class="small-msg warning" style="margin-top: 0;">Select the mentees you wish to assign this activity to. Existing assignments will be skipped.</p>

                <div class="mentee-select-header">
                    <p style="font-weight: 600; color: var(--secondary-color);">Mentee List (<?= count($mentee_list) ?>)</p>
                    <button type="button" id="toggleAllMentees" class="secondary-button small-btn">
                         <i class='bx bx-list-check'></i> Select All
                    </button>
                </div>
                
                <div class="mentee-checklist-container">
                    <?php if (empty($mentee_list)): ?>
                        <p style="text-align: center; color: #777; padding: 10px;">No mentees found in the system.</p>
                    <?php else: ?>
                        <?php foreach ($mentee_list as $mentee): ?>
                            <label class="mentee-item">
                                <input type="checkbox" name="mentee_ids[]" value="<?= htmlspecialchars($mentee['user_id']) ?>">
                                <?= htmlspecialchars($mentee['full_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="secondary-button" data-modal-close="#assignActivityModal">Cancel</button>
                    <button type="submit" name="assign_activity" class="gradient-button">
                        <i class='bx bx-send'></i> Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>


    <div id="previewActivityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="previewModalTitle">Activity Preview</h2>
                <button class="action-btn" data-modal-close="#previewActivityModal" title="Close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            
            <div class="activity-preview-details">
                <p><strong>Title:</strong> <span id="previewActivityName"></span></p>
                <p><strong>Course:</strong> <span id="previewCourseTitle"></span></p>
                <p><strong>Lesson:</strong> <span id="previewLessonName"></span></p>
                <p><strong>Status:</strong> <span id="previewStatusTag" class="status-tag"></span></p>
                <a id="previewFileLink" href="#" target="_blank" class="preview-file-link" style="display: none;">
                    <i class='bx bx-file'></i> View Attached File
                </a>
                <p id="noPreviewFileMsg" style="display: none;">No attached file.</p>
            </div>
            
            <div class="preview-questions-list">
                <h3 class="questions-heading">Questions</h3>
                <div id="previewQuestionsContainer">
                    </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close="#previewActivityModal">Close Preview</button>
            </div>
        </div>
    </div>
    


<div id="submissionRegradeModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2 id="regradeModalTitle">Review & Re-grade Submission</h2>
                <button class="action-btn" data-modal-close="#submissionRegradeModal" title="Close">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            
            <form id="regradeForm" method="POST" action="activities.php">
                <input type="hidden" name="submission_id" id="regradeSubmissionID">
                <input type="hidden" name="update_grade" value="1"> 
                
                <div class="activity-review-summary">
                    <p><strong>Activity:</strong> <span id="reviewActivityTitle"></span></p>
                    <p><strong>Mentee:</strong> <span id="reviewMenteeName"></span></p>
                    <p><strong>Lesson:</strong> <span id="reviewLessonName"></span></p>
                    <p><strong>Status:</strong> <span id="reviewStatusTag" class="status-tag"></span></p>
                    <p><strong>Current Score:</strong> <span id="reviewCurrentScore" style="font-weight: 700; color: var(--secondary-color);"></span></p>
                    <a id="reviewFileLink" href="#" target="_blank" class="preview-file-link" style="display: none; margin-top: 10px;">
                        <i class='bx bx-file'></i> View Submitted File
                    </a>
                    <p id="noReviewFileMsg" style="display: none; color: #777; margin-top: 10px;">No file submitted.</p>
                </div>

                <hr style="margin: 20px 0;">

                <div class="review-questions-container">
                    <h3 class="questions-heading">Submission Details (Mentee's Answers)</h3>
                    <div id="regradeQuestionsContainer">
                        </div>
                </div>

                <div class="form-group" style="margin-top: 30px;">
                    <label for="overall_score">Final Score (Enter total score):</label>
                    <input type="number" step="0.01" min="0" id="overall_score" name="final_score" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="overall_feedback">Overall Feedback:</label>
                    <textarea id="overall_feedback" name="overall_feedback" rows="4" placeholder="Enter overall feedback for the mentee"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="secondary-button" data-modal-close="#submissionRegradeModal">Cancel</button>
                    <button type="submit" class="gradient-button">
                        <i class='bx bx-save'></i> Save Grade & Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
 



    <div id="deleteConfirmDialog" class="logout-dialog" style="display: none;">
        <div class="logout-content">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this activity? This action cannot be undone.</p>
            <div class="dialog-buttons">
                <button id="cancelDelete" type="button" class="secondary-button">Cancel</button>
                <form id="confirmDeleteForm" method="POST" action="activities.php" style="display: inline;">
                    <input type="hidden" name="delete_activity_id" id="activityToDeleteID" value="">
                    <button type="submit" class="gradient-button delete-confirm-btn">Delete</button>
                </form>
            </div>
        </div>
    </div>

  <script src="admin.js"></script>
  <script src="js/navigation.js"></script>
  <script src="js/mentor_activities.js"></script>


<div id="assignActivityModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="assignModalTitle">Assign Activity</h2>
            <button class="action-btn" data-modal-close="#assignActivityModal" title="Close">
                <i class='bx bx-x'></i>
            </button>
        </div>
        
        <form id="assignActivityForm" method="POST" action="activities.php">
            <input type="hidden" name="assign_activity_id" id="activityToAssignID" value="">
            <input type="hidden" name="assign_activity" value="1">
            
            <p>Select the mentees you wish to assign the activity **"<span id="menteeActivityTitle"></span>"** to.</p>

            <div class="form-group">
                <label for="mentee_ids_modal">Select Mentees (Ctrl/Cmd + Click for multiple):</label>
                <select name="mentee_ids[]" id="mentee_ids_modal" multiple required class="full-width-select">
                    <?php foreach ($mentee_list as $mentee): ?>
                        <option value="<?= htmlspecialchars($mentee['user_id']) ?>">
                            <?= htmlspecialchars($mentee['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close="#assignActivityModal">Cancel</button>
                <button type="submit" class="gradient-button assign-confirm-btn">
                    <i class='bx bx-check-circle'></i> Confirm Assignment
                </button>
            </div>
        </form>
    </div>
</div>


<div id="previewActivityModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="previewModalTitle">Activity Preview</h2>
            <button class="action-btn" data-modal-close="#previewActivityModal" title="Close">
                <i class='bx bx-x'></i>
            </button>
        </div>
        
        <div class="activity-preview-details">
            <p><strong>Course:</strong> <span id="previewCourseTitle"></span></p>
            <p><strong>Lesson:</strong> <span id="previewLessonName"></span></p>
            <p><strong>Status:</strong> <span id="previewStatusTag" class="status-tag"></span></p>
            <a id="previewFileLink" href="#" target="_blank" class="preview-file-link" style="display: none;">
                <i class='bx bx-file'></i> View Attached File
            </a>

            <p id="noPreviewFileMsg" style="display: none;">No attached file.</p>
        </div>
        
        <div class="preview-questions-list">
            <h3 class="questions-heading">Questions</h3>
            <div id="previewQuestionsContainer">
                </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="secondary-button" data-modal-close="#previewActivityModal">Close</button>
        </div>
    </div>
</div>

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

<div id="toast-container"></div>
</body>
</html>