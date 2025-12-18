<?php
session_start();

date_default_timezone_set('Asia/Manila');

// Standard session check for a logged-in admin user
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'Admin' && $_SESSION['user_type'] !== 'Super Admin')) {
    header("Location: ../login.php"); // Redirect to a central login page if not authorized
    exit();
}

// Use your standard database connection script
require '../connection/db_connection.php';

// Load SendGrid and environment variables
require '../vendor/autoload.php';

// Load environment variables using phpdotenv
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Exception $e) {
    // Optionally log this error if the .env file is missing/unreadable
    // For now, we'll let the SendGrid block handle the resulting missing key
}


$message = "";
$sessions = [];

/**
 * Function to send session notification emails using SendGrid.
 * It builds the HTML content based on status (approved/rejected) and sends the email.
 *
 * NOTE: This function now requires the SENDGRID_API_KEY to be set in the environment
 * (e.g., in a .env file one directory above this script).
 */
function sendSessionNotificationEmail($mentorEmail, $mentorName, $courseTitle, $sessionDate, $timeSlot, $status, $adminNotes = '') {
    $emailBody = "";
    $subject = "";

    // 1. Build the HTML Email Body (Logic remains the same)
    if ($status === 'approved') {
        $subject = "Session Request Approved - " . $courseTitle;
        $emailBody = "
        <html>
        <head>
          <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: rgb(241, 223, 252); }
            .header { background-color: #562b63; padding: 15px; color: white; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .session-details { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
          </style>
        </head>
        <body>
          <div class='container'>
            <div class='header'>
              <h2>Session Request Approved</h2>
            </div>
            <div class='content'>
              <p>Dear <b>" . htmlspecialchars($mentorName) . "</b>,</p>
              <p>Congratulations! Your session request has been <b>approved</b>. 🎉</p>
              
              <div class='session-details'>
                <h3>Session Details:</h3>
                <p><strong>Course:</strong> " . htmlspecialchars($courseTitle) . "</p>
                <p><strong>Date:</strong> " . date('F j, Y', strtotime($sessionDate)) . "</p>
                <p><strong>Time:</strong> " . htmlspecialchars($timeSlot) . "</p>
              </div>

              <p>You can now access your session forum and start preparing for your mentoring session. Please log in to your account at <a href='https://coach-hub.online/login.php'>COACH</a> to view more details.</p>
              <p>We're excited to have you conduct this session. Best of luck in guiding your mentees!</p>
            </div>
            <div class='footer'>
              <p>&copy; " . date("Y") . " COACH. All rights reserved.</p>
            </div>
          </div>
        </body>
        </html>
        ";
    } else { // rejected
        $subject = "Session Request Update - " . $courseTitle;
        $emailBody = "
        <html>
        <head>
          <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color: rgb(241, 223, 252); }
            .header { background-color: #562b63; padding: 15px; color: white; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .session-details { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .notes-box { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
          </style>
        </head>
        <body>
          <div class='container'>
            <div class='header'>
              <h2>Session Request Update</h2>
            </div>
            <div class='content'>
              <p>Dear <b>" . htmlspecialchars($mentorName) . "</b>,</p>
              <p>We regret to inform you that your session request could not be approved at this time.</p>
              
              <div class='session-details'>
                <h3>Session Details:</h3>
                <p><strong>Course:</strong> " . htmlspecialchars($courseTitle) . "</p>
                <p><strong>Date:</strong> " . date('F j, Y', strtotime($sessionDate)) . "</p>
                <p><strong>Time:</strong> " . htmlspecialchars($timeSlot) . "</p>
              </div>";
              
        if (!empty($adminNotes)) {
            $emailBody .= "
              <div class='notes-box'>
                <h4>Admin Notes:</h4>
                <p>" . nl2br(htmlspecialchars($adminNotes)) . "</p>
              </div>";
        }

        $emailBody .= "
              <p>You are welcome to submit a new session request with different timing. Please log in to your account at <a href='https://coach-hub.online/login.php'>COACH</a> to submit a new request.</p>
              <p>Thank you for your understanding.</p>
            </div>
            <div class='footer'>
              <p>&copy; " . date("Y") . " COACH. All rights reserved.</p>
            </div>
          </div>
        </body>
        </html>
        ";
    }

    // 2. Send the Email via SendGrid (New Logic)
    try {
        // Check if the API key is available
        if (!isset($_ENV['SENDGRID_API_KEY']) || empty($_ENV['SENDGRID_API_KEY'])) {
            // Log an error if the key is missing for debugging purposes
            error_log("SendGrid API key is missing. Email not sent to " . $mentorEmail);
            // Optionally, you might want to return true here if non-critical, but returning false is safer.
            return false;
        }
        
        // SendGrid configuration using environment variables
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom('coach.hub2025@gmail.com', 'COACH Team'); // Use your verified sender email
        $email->setSubject($subject);
        $email->addTo($mentorEmail, $mentorName);
        $email->addContent("text/html", $emailBody);

        $sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
        $response = $sendgrid->send($email);

        // Check for non-2xx status code from SendGrid API
        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
             $error_message = "SendGrid API failed with status code " . $response->statusCode() . ". Body: " . $response->body();
             error_log($error_message);
             return false;
        }

        return true;
    } catch (\Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Create tables (same as before)
$conn->query("
CREATE TABLE IF NOT EXISTS pending_sessions (
    Pending_ID INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    Course_Title VARCHAR(250) NOT NULL,
    Session_Date DATE NOT NULL,
    Time_Slot VARCHAR(200) NOT NULL,
    Submission_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    Admin_Notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)");

$conn->query("
CREATE TABLE IF NOT EXISTS forum_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_admin TINYINT(1) DEFAULT 0,
    chat_type ENUM('group', 'forum', 'comment') DEFAULT 'group',
    forum_id INT NULL,
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)
");

$conn->query("
CREATE TABLE IF NOT EXISTS `session_bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_title` varchar(200) NOT NULL,
  `session_date` date NOT NULL,
  `time_slot` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `booking_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `forum_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`booking_id`),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$conn->query("
CREATE TABLE IF NOT EXISTS `booking_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `recipient_type` enum('admin','mentor','mentee') NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `booking_id` (`booking_id`),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

// AUTHENTICATION AND USER INFO
// Check if user is logged in and get user details
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

$currentUser = $_SESSION['username'];
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, icon, user_type FROM users WHERE username = ?");
$stmt->bind_param("s", $currentUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (!in_array($user['user_type'], ['Admin', 'Super Admin'])) {
        // If user is not an Admin or Super Admin, deny access
        header("Location: ../login.php");
        exit();
    }
    
    // Store essential user info in session with consistent naming
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['admin_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['admin_icon'] = $user['icon'] ?: '../uploads/img/default_pfp.png';
    $_SESSION['user_type'] = $user['user_type'];
    
    // Set display role based on user type
    $displayRole = $user['user_type'] === 'Super Admin' ? 'Super Admin' : 'Moderator';
} else {
    // If user not found in DB, destroy session and redirect to login
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$currentUserId = $_SESSION['user_id'];


// Handle time slot update
if (isset($_POST['update_slot_id'], $_POST['new_time_slot'])) {
    $updateId = $_POST['update_slot_id'];
    $newSlot = trim($_POST['new_time_slot']);
    $stmt = $conn->prepare("UPDATE sessions SET Time_Slot = ? WHERE Session_ID = ?");
    $stmt->bind_param("si", $newSlot, $updateId);
    if ($stmt->execute()) {
        $message = "✅ Session updated successfully.";
    }
    $stmt->close();
}

// Handle time slot deletion
if (isset($_POST['delete_slot_id'])) {
    $deleteId = $_POST['delete_slot_id'];
    $stmt = $conn->prepare("DELETE FROM sessions WHERE Session_ID = ?");
    $stmt->bind_param("i", $deleteId);
    if ($stmt->execute()) {
        $message = "🗑️ Session deleted successfully.";
    }
    $stmt->close();
}

// Handle form submission for new session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_title'], $_POST['available_date'], $_POST['start_time'], $_POST['end_time']) && !isset($_POST['update_slot_id'])) {
    $course = $_POST['course_title'];
    $date = $_POST['available_date'];
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];

    $startTime12hr = date("g:i A", strtotime($startTime));
    $endTime12hr = date("g:i A", strtotime($endTime));
    $timeSlot = $startTime12hr . " - " . $endTime12hr;
    $today = date('Y-m-d');
    // Get the current time in H:i format (PHT) for comparison
    $now = date('H:i'); 

    // --- NEW TIME VALIDATION LOGIC START ---

    // 1. Check for past date (original logic)
    if ($date < $today) {
        $message = "⚠️ Cannot set sessions for past dates.";
    } 
    // 2. Disallow same start and end time
    elseif ($startTime === $endTime) {
        $message = "⚠️ Start time and End time cannot be the same.";
    }
    // 3. Check if End Time is before Start Time
    elseif ($endTime <= $startTime) {
        $message = "⚠️ End time must be later than the start time.";
    }
    // 4. Disallow a start time that is in the past if the session date is today
    elseif ($date === $today && $startTime < $now) {
        // NOTE: The timezone is set to 'Asia/Manila' (PHT) at the top of sessions.php
        $message = "⚠️ Cannot set a session with a start time that is already past today. Current time is " . date('g:i A', strtotime($now)) . " PHT.";
    }
    
    // --- NEW TIME VALIDATION LOGIC END ---

    else {
        // Check for duplicate approved sessions (EXISTING LOGIC)
        $stmt = $conn->prepare("SELECT * FROM sessions WHERE Session_Date = ? AND Time_Slot = ?");
        $stmt->bind_param("ss", $date, $timeSlot);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "⚠️ Session time slot already exists for this date.";
        } else {
            // Insert new session
            $stmt = $conn->prepare("INSERT INTO sessions (Course_Title, Session_Date, Time_Slot) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $course, $date, $timeSlot);
            if ($stmt->execute()) {
                $message = "✅ Session added successfully.";
                
                // Insert corresponding forum (EXISTING LOGIC)
                $forumTitle = "$course Session";
                $stmt = $conn->prepare("INSERT INTO forum_chats (title, course_title, session_date, time_slot) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $forumTitle, $course, $date, $timeSlot);
                $stmt->execute();
            }
        }
        $stmt->close();
    }
}

// Handle pending session approval/rejection WITH EMAIL NOTIFICATIONS
if (isset($_POST['approve_pending_id'])) {
    $pendingId = $_POST['approve_pending_id'];
    
    $stmt = $conn->prepare("SELECT ps.*, CONCAT(u.first_name, ' ', u.last_name) as mentor_name, u.email as mentor_email 
                           FROM pending_sessions ps
                           JOIN users u ON ps.user_id = u.user_id
                           WHERE ps.Pending_ID = ?");
    $stmt->bind_param("i", $pendingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $pendingSession = $result->fetch_assoc();
        $course = $pendingSession['Course_Title'];
        $date = $pendingSession['Session_Date'];
        $timeSlot = $pendingSession['Time_Slot'];
        $mentorName = $pendingSession['mentor_name'];
        $mentorEmail = $pendingSession['mentor_email'];
        
        $stmt_check = $conn->prepare("SELECT * FROM sessions WHERE Session_Date = ? AND Time_Slot = ?");
        $stmt_check->bind_param("ss", $date, $timeSlot);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $message = "⚠️ Cannot approve: Session time slot already exists for this date.";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO sessions (Course_Title, Session_Date, Time_Slot) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $course, $date, $timeSlot);
            
            if ($stmt_insert->execute()) {
                $stmt_update = $conn->prepare("UPDATE pending_sessions SET Status = 'approved' WHERE Pending_ID = ?");
                $stmt_update->bind_param("i", $pendingId);
                $stmt_update->execute();
                
                $forumTitle = "$course Session";
                $stmt_forum = $conn->prepare("INSERT INTO forum_chats (title, course_title, session_date, time_slot) VALUES (?, ?, ?, ?)");
                $stmt_forum->bind_param("ssss", $forumTitle, $course, $date, $timeSlot);
                $stmt_forum->execute();
                
                // Send approval email
                if (sendSessionNotificationEmail($mentorEmail, $mentorName, $course, $date, $timeSlot, 'approved')) {
                    $message = "✅ Session request approved successfully and email notification sent.";
                } else {
                    $message = "✅ Session request approved successfully, but email notification failed to send.";
                }
            } else {
                $message = "❌ Error approving session: " . $stmt_insert->error;
            }
        }
    } else {
        $message = "❌ Pending session not found.";
    }
}

if (isset($_POST['reject_pending_id'])) {
    $pendingId = $_POST['reject_pending_id'];
    $adminNotes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
    
    // Get mentor details before updating
    $stmt_get = $conn->prepare("SELECT ps.*, CONCAT(u.first_name, ' ', u.last_name) as mentor_name, u.email as mentor_email 
                               FROM pending_sessions ps
                               JOIN users u ON ps.user_id = u.user_id
                               WHERE ps.Pending_ID = ?");
    $stmt_get->bind_param("i", $pendingId);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    
    if ($result_get->num_rows > 0) {
        $pendingSession = $result_get->fetch_assoc();
        $course = $pendingSession['Course_Title'];
        $date = $pendingSession['Session_Date'];
        $timeSlot = $pendingSession['Time_Slot'];
        $mentorName = $pendingSession['mentor_name'];
        $mentorEmail = $pendingSession['mentor_email'];
        
        $stmt = $conn->prepare("UPDATE pending_sessions SET Status = 'rejected', Admin_Notes = ? WHERE Pending_ID = ?");
        $stmt->bind_param("si", $adminNotes, $pendingId);
        
        if ($stmt->execute()) {
            // Send rejection email
            if (sendSessionNotificationEmail($mentorEmail, $mentorName, $course, $date, $timeSlot, 'rejected', $adminNotes)) {
                $message = "✅ Session request rejected and email notification sent.";
            } else {
                $message = "✅ Session request rejected, but email notification failed to send.";
            }
        } else {
            $message = "❌ Error rejecting session: " . $stmt->error;
        }
    } else {
        $message = "❌ Pending session not found.";
    }
}

// Handle forum deletion
if (isset($_GET['delete_forum'])) {
    $forumId = $_GET['delete_forum'];
    
    $conn->query("DELETE FROM forum_participants WHERE forum_id = $forumId");
    $conn->query("DELETE FROM chat_messages WHERE forum_id = $forumId AND chat_type = 'forum'");
    
    $stmt = $conn->prepare("DELETE FROM forum_chats WHERE id = ?");
    $stmt->bind_param("i", $forumId);
    if ($stmt->execute()) {
        $message = "🗑️ Forum deleted successfully.";
    }
}

// Handle adding a user to a forum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['forum_id'], $_POST['user_id']) && $_POST['action'] === 'add_user_to_forum') {
    $forumId = $_POST['forum_id'];
    $userIdToAdd = $_POST['user_id'];
    
    $stmt_check_user = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt_check_user->bind_param("i", $userIdToAdd);
    $stmt_check_user->execute();
    
    if ($stmt_check_user->get_result()->num_rows > 0) {
        $stmt_check_participant = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
        $stmt_check_participant->bind_param("ii", $forumId, $userIdToAdd);
        $stmt_check_participant->execute();
        
        if ($stmt_check_participant->get_result()->num_rows === 0) {
            $stmt_insert = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $forumId, $userIdToAdd);
            $stmt_insert->execute();
            $message = "✅ User added to forum successfully.";
        } else {
            $message = "⚠️ User is already in this forum.";
        }
    } else {
        $message = "⚠️ User not found.";
    }
}

// Handle removing a user from a forum
if (isset($_GET['remove_user_id'], $_GET['forum_id'])) {
    $userIdToRemove = $_GET['remove_user_id'];
    $forumId = $_GET['forum_id'];
    
    $stmt = $conn->prepare("DELETE FROM forum_participants WHERE forum_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $forumId, $userIdToRemove);
    if ($stmt->execute()) {
        $message = "✅ User removed from forum successfully.";
    }
}


// DATA FETCHING FOR DISPLAY

// Fetch existing sessions
$sql = "SELECT * FROM sessions ORDER BY Session_Date ASC";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $course = $row['Course_Title'];
        $date = $row['Session_Date'];
        $slot = $row['Time_Slot'];
        $sessions[$course][$date][] = ['id' => $row['Session_ID'], 'slot' => $slot];
    }
}

// Fetch pending session requests
$pendingRequests = [];
$pendingResult = $conn->query("
    SELECT ps.*, CONCAT(u.first_name, ' ', u.last_name) as mentor_name 
    FROM pending_sessions ps
    JOIN users u ON ps.user_id = u.user_id
    WHERE ps.Status = 'pending' AND u.user_type = 'Mentor'
    ORDER BY ps.Submission_Date ASC
");
if ($pendingResult && $pendingResult->num_rows > 0) {
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
}

// Fetch active courses grouped by category
$courses_by_category = [];
$res = $conn->query("
    SELECT Course_Title, Category 
    FROM courses 
    WHERE Course_Status = 'Active' 
    ORDER BY Category, Course_Title
");

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $courses_by_category[$row['Category']][] = $row['Course_Title'];
    }
}

// Map category codes to readable labels
$category_labels = [
    'IT'  => 'Information Technology',
    'CS'  => 'Computer Science',
    'DS'  => 'Data Science',
    'GD'  => 'Game Development',
    'DAT' => 'Digital Animation'
];

// Fetch forums for listing
$forums = [];
$forumsResult = $conn->query("
SELECT f.*, COUNT(fp.id) as current_users 
FROM forum_chats f 
LEFT JOIN forum_participants fp ON f.id = fp.forum_id 
GROUP BY f.id 
ORDER BY f.session_date ASC, f.time_slot ASC
");
if ($forumsResult && $forumsResult->num_rows > 0) {
    while ($row = $forumsResult->fetch_assoc()) {
        $forums[] = $row;
    }
}

// Fetch all mentees for admin to add to forums
$allMentees = [];
$menteesResult = $conn->query("SELECT user_id, username, first_name, last_name FROM users WHERE user_type = 'Mentee' ORDER BY first_name, last_name");
if ($menteesResult && $menteesResult->num_rows > 0) {
    while ($row = $menteesResult->fetch_assoc()) {
        $allMentees[] = $row;
    }
}

// Get forum participants if viewing a specific forum
$forumParticipants = [];
if (isset($_GET['view_forum'])) {
    $forumId = $_GET['view_forum'];
    $stmt = $conn->prepare("
        SELECT fp.user_id, u.username,
               CONCAT(u.first_name, ' ', u.last_name) as display_name,
               u.user_type
        FROM forum_participants fp
        JOIN users u ON fp.user_id = u.user_id
        WHERE fp.forum_id = ?
    ");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $participantsResult = $stmt->get_result();
    if ($participantsResult->num_rows > 0) {
        while ($row = $participantsResult->fetch_assoc()) {
            $forumParticipants[] = $row;
        }
    }
}

// Count unread notifications for Super Admin
$notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM booking_notifications WHERE user_id = ? AND recipient_type IN ('admin', 'superadmin') AND is_read = 0");
$notifStmt->bind_param("i", $currentUserId);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifCount = $notifResult->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <link rel="stylesheet" href="css/dashboard.css" />
        <link rel="stylesheet" href="css/session.css"/>
        <link rel="stylesheet" href="css/navigation.css"/>
            <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">

        <title>Session | Admin</title>
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    </head>
    <body>
        <nav>
            <div class="nav-top">
                <div class="logo">
                    <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
                    <div class="logo-name">COACH</div>
                </div>

                <div class="admin-profile">
                    <img src="<?php echo htmlspecialchars($_SESSION['admin_icon']); ?>" alt="Admin Profile Picture" />
                    <div class="admin-text">
                        <span class="admin-name">
                            <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                        </span>
                        <span class="admin-role"><?php echo $displayRole; ?></span>
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
             <li class="navList active">
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
                <img src="../uploads/img/logo.png" alt="Logo">
            </div>

            <div class="container">
                <h1 style="margin-bottom: 20px;">Session Management</h1>
                
                <?php if ($message): ?>
                    <div class="message"><?= $message ?></div>
                <?php endif; ?>
                
                <div class="tabs">
                    <div class="tab <?= !isset($_GET['view_forum']) && (!isset($_GET['tab']) || $_GET['tab'] === 'pending') ? 'active' : '' ?>" data-tab="pending">Pending Approvals</div>
                    <div class="tab <?= isset($_GET['tab']) && $_GET['tab'] === 'scheduler' ? 'active' : '' ?>" data-tab="scheduler">Session Scheduler</div>
                    <div class="tab <?= isset($_GET['tab']) && $_GET['tab'] === 'forums' ? 'active' : '' ?>" data-tab="forums">Session Forums</div>
                </div>
                
                <div class="tab-content <?= !isset($_GET['view_forum']) && (!isset($_GET['tab']) || $_GET['tab'] === 'pending') ? 'active' : '' ?>" id="pending-tab">
                    <h2>Pending Session Requests</h2>
                    
                    <?php if (empty($pendingRequests)): ?>
                        <p>No pending session requests at this time.</p>
                    <?php else: ?>
                        <?php foreach ($pendingRequests as $request): ?>
                            <div class="pending-request">
                                <div class="pending-header">
                                    <div class="pending-title"><?= htmlspecialchars($request['Course_Title']) ?> Session Request</div>
                                    <div class="pending-mentor">Mentor: <?= htmlspecialchars($request['mentor_name']) ?></div>
                                </div>
                                <div class="pending-details">
                                    <div class="pending-detail">
                                        <ion-icon name="calendar-outline"></ion-icon>
                                        <span>Date: <?= date('F j, Y', strtotime($request['Session_Date'])) ?></span>
                                    </div>
                                    <div class="pending-detail">
                                        <ion-icon name="time-outline"></ion-icon>
                                        <span>Time: <?= htmlspecialchars($request['Time_Slot']) ?></span>
                                    </div>
                                    <div class="pending-detail">
                                        <ion-icon name="hourglass-outline"></ion-icon>
                                        <span>Submitted: <?= date('M j, Y g:i A', strtotime($request['Submission_Date'])) ?></span>
                                    </div>
                                </div>
                                <div class="pending-actions">
                                    <form method="POST">
                                        <input type="hidden" name="approve_pending_id" value="<?= $request['Pending_ID'] ?>">
                                        <button type="submit" class="approve-btn">
                                            <ion-icon name="checkmark-outline"></ion-icon>
                                            Approve
                                        </button>
                                    </form>
                                    <button type="button" class="reject-btn" onclick="openRejectModal(<?= $request['Pending_ID'] ?>)">
                                        <ion-icon name="close-outline"></ion-icon>
                                        Reject
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="tab-content <?= isset($_GET['tab']) && $_GET['tab'] === 'scheduler' ? 'active' : '' ?>" id="scheduler-tab">
                    <h2>Session Scheduler</h2>
                    
<div class="session-scheduler">
    <h3>Add New Session</h3>
    <form method="POST">
        <div class="form-row">
            <label>Course:</label>
            <select name="course_title" required>
                <option value="">-- Select Course --</option>

                <?php foreach ($courses_by_category as $category => $course_list): ?>
                    <optgroup label="<?= htmlspecialchars($category_labels[$category] ?? $category) ?>" style="font-weight:bold;">
                        <?php foreach ($course_list as $course): ?>
                            <option value="<?= htmlspecialchars($course) ?>">
                                <?= htmlspecialchars($course) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
        </div>
    </form>
</div>
</select>

                                <label>Date:</label>
                                <input type="date" name="available_date" required min="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-row">
                                <label>(PHT) Start Time:</label>
                                <input type="time" name="start_time" required>

                                <label>End Time:</label>
                                <input type="time" name="end_time" required>

                                <button type="submit">Add Session</button>
                            </div>
                        </form>
                    </div>
                    
                    <h3>Available Sessions</h3>
                    <?php if (empty($sessions)): ?>
                        <p>No sessions available yet.</p>
                    <?php else: ?>
                        <?php foreach ($sessions as $course => $dates): ?>
                            <div class="session-block">
                                <h4><?= htmlspecialchars($course) ?></h4>
                                <?php foreach ($dates as $date => $slots): ?>
                                    <strong><?= htmlspecialchars(date('F j, Y', strtotime($date))) ?></strong>
                                    <ul>
                                        <?php foreach ($slots as $slotData): ?>
                                            <li>
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="update_slot_id" value="<?= $slotData['id'] ?>">
                                                    <input type="text" name="new_time_slot" value="<?= htmlspecialchars($slotData['slot']) ?>" readonly class="slot-input">
                                                    <button type="button" onclick="enableEdit(this)">Edit</button>
                                                    <button type="submit" style="display:none;">Confirm</button>
                                                </form>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this session?');">
                                                    <input type="hidden" name="delete_slot_id" value="<?= $slotData['id'] ?>">
                                                    <button type="submit">Delete</button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="tab-content <?= isset($_GET['tab']) && $_GET['tab'] === 'forums' ? 'active' : '' ?>" id="forums-tab">
                    <?php if (isset($_GET['view_forum'])): ?>
                        <a href="?tab=forums" class="back-button">
                            <ion-icon name="arrow-back-outline"></ion-icon>
                            Back to Forums
                        </a>
                        
                        <?php
                        // Get forum details
                        $forumId = $_GET['view_forum'];
                        $stmt = $conn->prepare("SELECT * FROM forum_chats WHERE id = ?");
                        $stmt->bind_param("i", $forumId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $forum = $result->fetch_assoc();
                        ?>
                        
                        <h2>Forum: <?= htmlspecialchars($forum['title']) ?></h2>
                        
                        <div class="forum-details">
                            <div class="forum-detail">
                                <ion-icon name="book-outline"></ion-icon>
                                <span>Course: <?= htmlspecialchars($forum['course_title']) ?></span>
                            </div>
                            <div class="forum-detail">
                                <ion-icon name="calendar-outline"></ion-icon>
                                <span>Date: <?= date('F j, Y', strtotime($forum['session_date'])) ?></span>
                            </div>
                            <div class="forum-detail">
                                <ion-icon name="time-outline"></ion-icon>
                                <span>Time: <?= htmlspecialchars($forum['time_slot']) ?></span>
                            </div>
                            <div class="forum-detail">
                                <ion-icon name="people-outline"></ion-icon>
                                <span>Max Participants: <?= $forum['max_users'] ?></span>
                            </div>
                        </div>
                        
                        <h3>Participants</h3>
                        <?php if (empty($forumParticipants)): ?>
                            <p>No participants have joined this forum yet.</p>
                        <?php else: ?>
                            <div class="participant-list">
                                <?php foreach ($forumParticipants as $participant): ?>
                                    <div class="participant-item">
                                        <div class="participant-info">
                                            <span class="participant-name"><?= htmlspecialchars($participant['display_name']) ?></span>
                                            <?php if (in_array($participant['user_type'], ['Admin', 'Super Admin'])): ?>
                                                <span class="participant-badge admin">Admin</span>
                                            <?php elseif ($participant['user_type'] == 'Mentor'): ?>
                                                <span class="participant-badge mentor">Mentor</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="participant-actions">
                                            <a href="?view_forum=<?= $forumId ?>&remove_user_id=<?= $participant['user_id'] ?>&tab=forums" onclick="return confirm('Are you sure you want to remove this user from the forum?');" title="Remove User">
                                                <ion-icon name="close-circle-outline"></ion-icon>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="add-participant-form">
                            <h4>Add Participant</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_user_to_forum">
                                <input type="hidden" name="forum_id" value="<?= $forumId ?>">
                                <div class="form-row">
                                    <select name="user_id" required>
                                        <option value="">-- Select Mentee --</option>
                                        <?php foreach ($allMentees as $mentee): ?>
                                            <option value="<?= htmlspecialchars($mentee['user_id']) ?>">
                                                <?= htmlspecialchars($mentee['first_name'] . ' ' . $mentee['last_name']) ?> (<?= htmlspecialchars($mentee['username']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Add to Forum</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="forum-actions" style="margin-top: 30px;">
                            <a href="forum-chat-admin.php?view=forum&forum_id=<?= $forumId ?>" class="forum-button">
                                <ion-icon name="chatbubbles-outline"></ion-icon>
                                Join Forum Chat
                            </a>
                        </div>
                    <?php else: ?>
                        <h2>Session Forums</h2>
                        
                        <?php if (empty($forums)): ?>
                            <p>No forums available yet.</p>
                        <?php else: ?>
                            <div class="forum-grid">
                                <?php foreach ($forums as $forum): ?>
                                    <div class="forum-card">
                                        <div class="forum-header">
                                            <div class="forum-title"><?= htmlspecialchars($forum['title']) ?></div>
                                            <div class="forum-actions">
                                                <a href="?delete_forum=<?= $forum['id'] ?>&tab=forums" onclick="return confirm('Are you sure you want to delete this forum? All messages will be lost.');" title="Delete Forum">
                                                    <ion-icon name="trash-outline"></ion-icon>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="forum-content">
                                            <div class="forum-detail">
                                                <ion-icon name="book-outline"></ion-icon>
                                                <span><?= htmlspecialchars($forum['course_title']) ?></span>
                                            </div>
                                            <div class="forum-detail">
                                                <ion-icon name="calendar-outline"></ion-icon>
                                                <span><?= date('F j, Y', strtotime($forum['session_date'])) ?></span>
                                            </div>
                                            <div class="forum-detail">
                                                <ion-icon name="time-outline"></ion-icon>
                                                <span><?= htmlspecialchars($forum['time_slot']) ?></span>
                                            </div>
                                            <div class="forum-detail">
                                                <ion-icon name="people-outline"></ion-icon>
                                                <span>Participants: <?= $forum['current_users'] ?>/<?= $forum['max_users'] ?></span>
                                            </div>
                                        </div>
                                        <div class="forum-footer">
                                            <a href="?view_forum=<?= $forum['id'] ?>&tab=forums" class="forum-button">
                                                <ion-icon name="settings-outline"></ion-icon>
                                                Manage
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <div id="rejectModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Reject Session Request</h3>
                    <button type="button" class="modal-close" onclick="closeRejectModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm" method="POST">
                        <input type="hidden" id="reject_pending_id" name="reject_pending_id" value="">
                        <div class="form-group">
                            <label for="admin_notes">Reason for Rejection (optional):</label>
                            <textarea id="admin_notes" name="admin_notes" placeholder="Provide feedback to the mentor about why this session was rejected..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeRejectModal()">Cancel</button>
                    <button type="button" onclick="submitRejectForm()" class="reject-btn">Reject Session</button>
                </div>
            </div>
        </div> 
        <script src="js/navigation.js"></script>
        <script>
            function enableEdit(button) {
                const form = button.closest('form');
                const input = form.querySelector('input[name="new_time_slot"]');
                const confirmButton = form.querySelector('button[type="submit"]');
                input.removeAttribute('readonly');
                input.focus();
                button.style.display = 'none';
                confirmButton.style.display = 'inline';
            }

            
            document.addEventListener('DOMContentLoaded', function() {
                const tabs = document.querySelectorAll('.tab');
                const tabContents = document.querySelectorAll('.tab-content');
                
                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        tabs.forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        
                        tabContents.forEach(content => content.classList.remove('active'));
                        
                        const tabId = tab.getAttribute('data-tab') + '-tab';
                        document.getElementById(tabId).classList.add('active');
                        
                        const url = new URL(window.location);
                        url.searchParams.set('tab', tab.getAttribute('data-tab'));
                        url.searchParams.delete('view_forum'); 
                        window.history.pushState({}, '', url);
                    });
                });

                // Ensure correct tab is active on page load based on URL
                const urlParams = new URLSearchParams(window.location.search);
                const activeTab = urlParams.get('tab') || 'pending';
                document.querySelector(`.tab[data-tab="${activeTab}"]`).click();
            });
            
            function openRejectModal(pendingId) {
                document.getElementById('reject_pending_id').value = pendingId;
                document.getElementById('rejectModal').classList.add('active');
            }
            
            function closeRejectModal() {
                document.getElementById('rejectModal').classList.remove('active');
            }
            
            function submitRejectForm() {
                document.getElementById('rejectForm').submit();
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
    </body>
</html>