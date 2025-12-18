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

$menteeUsername = $_SESSION['username'];
$courseTitle = $_GET['course'] ?? '';

// Get mentee details
$stmt = $conn->prepare("SELECT first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $menteeUsername);
$stmt->execute();
$result = $stmt->get_result();
$menteeData = $result->fetch_assoc();
$firstName = $menteeData['first_name'];
$lastName = $menteeData['last_name'];
$menteeIcon = $menteeData['icon'];

// Count unread notifications
$notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM booking_notifications WHERE user_id = ? AND recipient_type = 'mentee' AND is_read = 0");
$notifStmt->bind_param("s", $menteeUsername);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifCount = $notifResult->fetch_assoc()['count'];

// Create booking_notifications table if it doesn't exist
$conn->query("
CREATE TABLE IF NOT EXISTS `booking_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `recipient_type` enum('admin','mentor','mentee') NOT NULL,
  `user_id` varchar(70) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// Create session_bookings table if it doesn't exist
$conn->query("
CREATE TABLE IF NOT EXISTS `session_bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `mentee_username` varchar(70) NOT NULL,
  `course_title` varchar(200) NOT NULL,
  `session_date` date NOT NULL,
  `time_slot` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `booking_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `forum_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Book A Session</title>
  <link rel="stylesheet" href="css/sessions.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

  <!-- FullCalendar Styles and Scripts -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

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

  <div class="booking-container">
    <div class="calendar-section">
      <h2>Book A Session</h2>
      <p><strong>Course:</strong> <?= htmlspecialchars($courseTitle) ?></p>

      <div id="calendar"></div>
    </div>

    <div class="timeslot-section">
      <h3 id="slot-heading">Available Time Slots</h3>

      <!-- FORM -->
      <form id="booking-form" method="GET" action="booking_summary.php">
        <div id="time-slots">
          <p>Select a date to view available time slots.</p>
        </div>

        <!-- Notes field -->
        <div class="notes-field" style="display: none;" id="notes-container">
          <label for="booking_notes">Additional Notes (Optional):</label>
          <textarea name="notes" id="booking_notes" placeholder="Any specific topics you'd like to cover?"></textarea>
        </div>

        <!-- Hidden fields -->
        <input type="hidden" name="selected_date" id="selected_date" required>
        <input type="hidden" name="course_title" value="<?= htmlspecialchars($courseTitle) ?>">

        <!-- Book Session Button -->
        <button type="submit" class="book-button" onclick="return validateBooking();" id="book-button" style="display: none;">
          Book Session
        </button>
      </form>
    </div>
  </div>

  <!-- Create booking_summary.php file -->
  <?php
  // Create the file if it doesn't exist
  $bookingSummaryFile = 'booking_summary.php';
  if (!file_exists($bookingSummaryFile)) {
    $bookingSummaryContent = '<?php
session_start();
$conn = new mysqli("localhost", "root", "", "coach");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION[\'username\'])) {
    header("Location: login_mentee.php");
    exit();
}

$message = "";
$bookingComplete = false;
$username = $_SESSION[\'username\'];

// Get mentee details
$stmt = $conn->prepare("SELECT First_Name, Last_Name FROM mentee_profiles WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$menteeData = $result->fetch_assoc();
$menteeName = $menteeData[\'First_Name\'] . \' \' . $menteeData[\'Last_Name\'];

// Process booking form submission
if ($_SERVER[\'REQUEST_METHOD\'] === \'GET\' && isset($_GET[\'course_title\'], $_GET[\'selected_date\'], $_GET[\'time_slot\'])) {
    $courseTitle = $_GET[\'course_title\'];
    $sessionDate = $_GET[\'selected_date\'];
    $timeSlot = $_GET[\'time_slot\'];
    $notes = $_GET[\'notes\'] ?? null;
    
    // Check if this mentee already has a booking for this session
    $stmt = $conn->prepare("SELECT * FROM session_bookings WHERE mentee_username = ? AND course_title = ? AND session_date = ? AND time_slot = ?");
    $stmt->bind_param("ssss", $username, $courseTitle, $sessionDate, $timeSlot);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "You already have a booking for this session.";
    } else {
        // Insert the booking with automatic approval
        $stmt = $conn->prepare("INSERT INTO session_bookings (mentee_username, course_title, session_date, time_slot, status, notes) VALUES (?, ?, ?, ?, \'approved\', ?)");
        $stmt->bind_param("sssss", $username, $courseTitle, $sessionDate, $timeSlot, $notes);
        
        if ($stmt->execute()) {
            $bookingId = $conn->insert_id;
            $bookingComplete = true;
            $message = "Your booking has been confirmed successfully!";
            
            // Create notification for all admins
            $notificationMsg = "$menteeName has booked a $courseTitle session on $sessionDate at $timeSlot";
            
            // Get all admin usernames
            $adminResult = $conn->query("SELECT Admin_Username FROM admins");
            while ($admin = $adminResult->fetch_assoc()) {
                $adminUsername = $admin[\'Admin_Username\'];
                $stmt = $conn->prepare("INSERT INTO booking_notifications (booking_id, recipient_type, user_id, message) VALUES (?, \'admin\', ?, ?)");
                $stmt->bind_param("iss", $bookingId, $adminUsername, $notificationMsg);
                $stmt->execute();
            }
        } else {
            $message = "Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Summary</title>
    <link rel="stylesheet" href="mentee_sessions.css">
    <link rel="icon" href="coachicon.svg" type="image/svg+xml">
    <style>
    
        * {
            font-family: "Ubuntu", sans-serif;
            text-transform: none;
        }

        body {
            background: linear-gradient(to right, #290c26, #562b63, #38243e);
        }

        .booking-summary {
            max-width: 600px;
            margin: 50px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .booking-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            background-color: #f9f3fc;
        }
        
        .booking-details {
            margin: 25px 0;
            text-align: left;
            padding: 20px;
            background: #f9f3fc;
            border-radius: 8px;
        }
        
        .booking-details p {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
        }
    
        .booking-details p span:first-child {
            font-weight: bold;
            color: #6b2a7a;
        }
        
        .buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .primary-btn {
            background-color: #6b2a7a;
            color: white;
        }
        
        .secondary-btn {
            background-color: #e0e0e0;
            color: #333;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .success-icon {
            font-size: 60px;
            color: #6b2a7a;
            margin-bottom: 20px;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }

        
    </style>
</head>
<body>
    <div class="booking-summary">
        <?php if ($bookingComplete): ?>
            <div class="success-icon pulse">✓</div>
            <h2>Booking Request Submitted!</h2>
            <div class="booking-status">
                <p><?php echo $message; ?></p>
            </div>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <p><span>Course:</span> <span><?php echo htmlspecialchars($_GET[\'course_title\']); ?></span></p>
                <p><span>Date:</span> <span><?php echo htmlspecialchars($_GET[\'selected_date\']); ?></span></p>
                <p><span>Time:</span> <span><?php echo htmlspecialchars($_GET[\'time_slot\']); ?></span></p>
                <p><span>Status:</span> <span>Pending Approval</span></p>
                
                <?php if (!empty($_GET[\'notes\'])): ?>
                    <p><span>Notes:</span> <span><?php echo htmlspecialchars($_GET[\'notes\']); ?></span></p>
                <?php endif; ?>
            </div>
            
            <p>You will receive a notification once your booking is approved or rejected.</p>
        <?php else: ?>
            <h2>Booking Error</h2>
            <div class="booking-status">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="buttons">
            <a href="CoachMenteeHome.php" class="btn secondary-btn">Back to Home</a>
            <a href="mentee_bookings.php" class="btn primary-btn">View My Bookings</a>
        </div>
    </div>
</body>
</html>';
    file_put_contents($bookingSummaryFile, $bookingSummaryContent);
  }
  
  // Create mentee_bookings.php file
  $menteeBookingsFile = 'mentee_bookings.php';
  if (!file_exists($menteeBookingsFile)) {
    $menteeBookingsContent = '<?php
session_start();
$conn = new mysqli("localhost", "root", "", "coach");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION[\'username\'])) {
    header("Location: login_mentee.php");
    exit();
}

$username = $_SESSION[\'username\'];
$bookings = [];

// Fetch all bookings for this mentee
$stmt = $conn->prepare("SELECT * FROM session_bookings WHERE mentee_username = ? ORDER BY session_date DESC, booking_time DESC");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

// Get mentee details
$stmt = $conn->prepare("SELECT First_Name, Last_Name, Mentee_Icon FROM mentee_profiles WHERE Username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$menteeData = $result->fetch_assoc();
$firstName = $menteeData[\'First_Name\'];
$menteeIcon = $menteeData[\'Mentee_Icon\'];

// Count unread notifications
$notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM booking_notifications WHERE user_id = ? AND recipient_type = \'mentee\' AND is_read = 0");
$notifStmt->bind_param("s", $username);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifCount = $notifResult->fetch_assoc()[\'count\'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
    <link rel="stylesheet" href="css/mentee_navbarstyle.css">
    <link rel="icon" href="coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        body {
            font-family: \'Arial\', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 80px auto 30px;
            padding: 20px;
        }
        
        h1 {
            color:rgb(122, 42, 42);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .bookings-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .booking-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-5px);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .booking-title {
            font-size: 18px;
            font-weight: bold;
            color: #6b2a7a;
        }
        
        .booking-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .booking-details {
            margin-bottom: 20px;
        }
        
        .booking-detail {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            width: 100px;
            font-weight: bold;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .booking-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .cancel-btn {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .cancel-btn:hover {
            background-color: #e5c3c6;
        }
        
        .view-btn {
            background-color: #e2e3e5;
            color: #383d41;
            margin-right: 10px;
        }
        
        .view-btn:hover {
            background-color: #d6d8db;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 60px;
            color: #6b2a7a;
            margin-bottom: 20px;
        }
        
        .empty-text {
            color: #555;
            margin-bottom: 30px;
        }
        
        .book-now-btn {
            background-color: #6b2a7a;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .book-now-btn:hover {
            background-color: #5a2366;
            transform: translateY(-2px);
        }
        
        .notification-badge {
          position: absolute;
          top: -5px;
          right: -5px;
          background-color: #dc3545;
          color: white;
          border-radius: 50%;
          width: 18px;
          height: 18px;
          font-size: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        .notification-icon {
          position: relative;
          margin-left: 15px;
        }
        
        .nav-profile {
          display: flex;
          align-items: center;
        }
        
        @media (max-width: 768px) {
            .bookings-container {
                grid-template-columns: 1fr;
            }
        }


        
    </style>
</head>
<body>
    <section class="background" id="home">
        <nav class="navbar">
            <div class="logo">
                <img src="img/LogoCoach.png" alt="Logo">
                <span>COACH</span>
            </div>

            <div class="nav-center">
                <ul class="nav_items" id="nav_links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="course.php">Courses</a></li>
                    <li><a href="resource_library.php">Resource Library</a></li>
                    <li><a href="activities.php">Activities</a></li>
                    <li><a href="forum-chat.php">Sessions</a></li>
                    <li><a href="forums.php">Forums</a></li>
                </ul>
            </div>

            <div class="nav-profile">
                <!-- Notification Icon -->
                <a href="mentee_notifications.php" class="notification-icon">
                    <ion-icon name="notifications-outline" style="font-size: 24px;"></ion-icon>
                    <?php if ($notifCount > 0): ?>
                        <span class="notification-badge"><?php echo $notifCount; ?></span>
                    <?php endif; ?>
                </a>
                
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
        <h1>My Session Bookings</h1>
        
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <ion-icon name="calendar-outline"></ion-icon>
                </div>
                <h2>No Bookings Yet</h2>
                <p class="empty-text">You haven\'t booked any sessions yet. Start learning by booking a session now!</p>
                <a href="CoachMenteeHome.php#courses" class="book-now-btn">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="bookings-container">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-title"><?php echo htmlspecialchars($booking[\'course_title\']); ?></div>
                            <div class="booking-status status-<?php echo htmlspecialchars($booking[\'status\']); ?>">
                                <?php echo ucfirst(htmlspecialchars($booking[\'status\'])); ?>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="booking-detail">
                                <div class="detail-label">Date:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking[\'session_date\']); ?></div>
                            </div>
                            
                            <div class="booking-detail">
                                <div class="detail-label">Time:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking[\'time_slot\']); ?></div>
                            </div>
                            
                            <div class="booking-detail">
                                <div class="detail-label">Booked on:</div>
                                <div class="detail-value"><?php echo date(\'M d, Y\', strtotime($booking[\'booking_time\'])); ?></div>
                            </div>
                            
                            <?php if (!empty($booking[\'notes\'])): ?>
                                <div class="booking-detail">
                                    <div class="detail-label">Notes:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($booking[\'notes\']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($booking[\'status\'] === \'pending\'): ?>
                                <a href="cancel_booking.php?id=<?php echo $booking[\'booking_id\']; ?>" class="action-btn cancel-btn" onclick="return confirm(\'Are you sure you want to cancel this booking?\')">Cancel</a>
                            <?php elseif ($booking[\'status\'] === \'approved\' && $booking[\'forum_id\']): ?>
                                <a href="forum-chat.php?view=forum&forum_id=<?php echo $booking[\'forum_id\']; ?>" class="action-btn view-btn">Join Session</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="mentee.js"></script>
    <script>
        function confirmLogout() {
            var confirmation = confirm("Are you sure you want to log out?");
            if (confirmation) {
                window.location.href = "../login.php";
            } else {
                return false;
            }
        }
        
        document.addEventListener(\'DOMContentLoaded\', function() {
            const profileIcon = document.getElementById(\'profile-icon\');
            const profileMenu = document.getElementById(\'profile-menu\');
            
            profileIcon.addEventListener(\'click\', function(e) {
                e.preventDefault();
                profileMenu.classList.toggle(\'hide\');
            });
            
            document.addEventListener(\'click\', function(e) {
                if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.add(\'hide\');
                }
            });
        });
    </script>
</body>
</html>';
    file_put_contents($menteeBookingsFile, $menteeBookingsContent);
  }
  
  // Create cancel_booking.php file
  $cancelBookingFile = 'cancel_booking.php';
  if (!file_exists($cancelBookingFile)) {
    $cancelBookingContent = '<?php
session_start();
$conn = new mysqli("localhost", "root", "", "coach");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION[\'username\'])) {
    header("Location: login_mentee.php");
    exit();
}

$username = $_SESSION[\'username\'];
$message = "";

if (isset($_GET[\'id\'])) {
    $bookingId = $_GET[\'id\'];
    
    // Verify this booking belongs to the logged-in user
    $stmt = $conn->prepare("SELECT * FROM session_bookings WHERE booking_id = ? AND mentee_username = ?");
    $stmt->bind_param("is", $bookingId, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        
        // Only allow cancellation of pending bookings
        if ($booking[\'status\'] === \'pending\') {
            // Delete the booking
            $stmt = $conn->prepare("DELETE FROM session_bookings WHERE booking_id = ?");
            $stmt->bind_param("i", $bookingId);
            
            if ($stmt->execute()) {
                $message = "Your booking has been cancelled successfully.";
                
                // Delete related notifications
                $stmt = $conn->prepare("DELETE FROM booking_notifications WHERE booking_id = ?");
                $stmt->bind_param("i", $bookingId);
                $stmt->execute();
            } else {
                $message = "Error cancelling booking: " . $stmt->error;
            }
        } else {
            $message = "Only pending bookings can be cancelled.";
        }
    } else {
        $message = "Invalid booking or you don\'t have permission to cancel it.";
    }
} else {
    $message = "No booking specified.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cancel Booking</title>
    <link rel="stylesheet" href="mentee_sessions.css">
    <link rel="icon" href="coachicon.svg" type="image/svg+xml">
    <style>
        .cancel-container {
            max-width: 500px;
            margin: 100px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        h2 {
            color: #6b2a7a;
            margin-bottom: 20px;
        }
        
        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            background-color: #f9f3fc;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6b2a7a;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background-color: #5a2366;
            transform: translateY(-2px);
        }
            
    </style>
</head>
<body>
    <div class="cancel-container">
        <h2>Booking Cancellation</h2>
        <div class="message">
            <p><?php echo $message; ?></p>
        </div>
        <a href="mentee_bookings.php" class="btn">Back to My Bookings</a>
    </div>
</body>
</html>';
    file_put_contents($cancelBookingFile, $cancelBookingContent);
  }
  ?>

  <!-- Scripts -->
       <script src="mentee.js"></script>
       
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- DIALOG ELEMENTS ---
    const logoutDialog = document.getElementById('logoutDialog');
    const cancelLogoutBtn = document.getElementById('cancelLogout');
    const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
    
    const alertDialog = document.getElementById('alertValidationDialog');
    const closeAlertBtn = document.getElementById('closeAlertBtn');
    const alertMessage = document.getElementById('alertMessage');

    const bookingDialog = document.getElementById('bookingConfirmationDialog');
    const cancelBookingBtn = document.getElementById('cancelBookingBtn');
    const confirmBookingBtn = document.getElementById('confirmBookingBtn');
    
    // --- FULLCALENDAR LOGIC ---
    const today = new Date().toISOString().split('T')[0];

    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      selectable: true,
      height: "auto",
      events: `fetch_dates.php?course=<?= urlencode($courseTitle) ?>`,
      eventColor: '#6b2a7a',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },
      dayCellDidMount: function(info) {
        const date = info.date;
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);

        if (date < todayDate) {
          info.el.style.backgroundColor = "#f5f5f5";
          info.el.style.color = "#ccc";
          info.el.style.cursor = "not-allowed";
          info.el.classList.add("fc-day-past");
        }
      },
      // *** This is the SINGLE, CORRECT dateClick function ***
      dateClick: function(info) {
        const clickedDate = new Date(info.dateStr);
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);

        if (clickedDate < todayDate) {
          return;
        }

        document.getElementById('selected_date').value = info.dateStr;

        fetch(`get_timeslots.php?course=<?= urlencode($courseTitle) ?>&date=${info.dateStr}`)
          .then(response => response.json())
          .then(data => {
            const slotsDiv = document.getElementById('time-slots');
            slotsDiv.innerHTML = "";

            if (data.length === 0) {
              slotsDiv.innerHTML = "<p>No available slots for this date.</p>";
              document.getElementById('notes-container').style.display = 'none';
              document.getElementById('book-button').style.display = 'none';
              return;
            }
            
            // Hide notes and book button by default until a valid slot is selected
            document.getElementById('notes-container').style.display = 'none';
            document.getElementById('book-button').style.display = 'none';
            
            data.forEach(slotData => {
                const slot = slotData.slot;
                const status = slotData.status;
                const slotsLeft = slotData.slotsLeft;

                const radio = document.createElement("input");
                radio.type = "radio";
                radio.name = "time_slot";
                // Pass the full time slot string as the value for form submission
                radio.value = slot; 
                radio.required = true;
                
                const label = document.createElement("label");

                let statusText = '';
                let isDisabled = false;

                switch (status) {
                    case "past":
                        statusText = `(This Session Is Done)`;
                        isDisabled = true;
                        break;
                    case "already_booked":
                        statusText = `(Already Booked)`;
                        isDisabled = true;
                        break;
                    case "full":
                        statusText = `(No Slots Available)`;
                        isDisabled = true;
                        break;
                    case "available":
                    default:
                        statusText = `(Slots Available: ${slotsLeft})`;
                        isDisabled = false;
                        // Add change listener only to available slots
                        radio.addEventListener('change', function() {
                            document.getElementById('notes-container').style.display = 'block';
                            document.getElementById('book-button').style.display = 'block';
                        });
                        break;
                }

                label.textContent = `${slot} ${statusText}`;
                radio.disabled = isDisabled;

                const wrapper = document.createElement("div");
                wrapper.classList.add("time-option");
                
                // Apply the custom disabled style
                if (isDisabled) {
                    wrapper.classList.add("disabled-slot");
                }
                
                wrapper.appendChild(radio);
                wrapper.appendChild(label);
                
                // Append the wrapper to the time slots container
                slotsDiv.appendChild(wrapper);
            });
          });
      }
      // *** End of FullCalendar options object ***
    }); 

    // *** calendar.render() must be called outside the configuration object ***
    calendar.render();
    
    // --- PROFILE MENU TOGGLE (PRESERVED) ---
    const profileIcon = document.getElementById('profile-icon');
    const profileMenu = document.getElementById('profile-menu');
    
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener('click', function(e) {
          e.preventDefault();
          profileMenu.classList.toggle('show');
          profileMenu.classList.toggle('hide');
        });
        
        document.addEventListener('click', function(e) {
          if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
            profileMenu.classList.remove('show');
            profileMenu.classList.add('hide');
          }
        });
    }

    // ==========================================================
    // --- LOGOUT DIALOG LOGIC ---
    // ==========================================================
    window.confirmLogout = function(e) {
      if (e) e.preventDefault();
      if (logoutDialog) logoutDialog.style.display = "flex";
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
            // FIX: Redirect to the dedicated logout script in the parent folder (root)
            window.location.href = "../logout.php"; 
        });
    }

    // ==========================================================
    // --- BOOKING DIALOG LOGIC ---
    // ==========================================================

    // Function to show a generic alert dialog with a specific message
    function showAlert(message) {
        alertMessage.textContent = message;
        alertDialog.style.display = 'flex';
    }

    // Close alert dialog
    if (closeAlertBtn && alertDialog) {
        closeAlertBtn.addEventListener('click', function() {
            alertDialog.style.display = 'none';
        });
    }

    // Handle form submission via the dialog
    if (cancelBookingBtn && bookingDialog) {
        cancelBookingBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            bookingDialog.style.display = "none";
        });
    }

    if (confirmBookingBtn) {
        confirmBookingBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            // Hide the confirmation dialog
            bookingDialog.style.display = "none";
            // Submit the actual booking form (assuming your form has id="booking-form")
            const form = document.getElementById('booking-form');
            if (form) {
                form.submit();
            } else {
                console.error("Booking form with id='booking-form' not found.");
            }
        });
    }

    // Replaced the original validateBooking function
    window.validateBooking = function() {
        const selectedDate = document.getElementById('selected_date').value;
        const timeSlotSelected = document.querySelector('input[name="time_slot"]:checked');

        if (!selectedDate) {
            showAlert("Please select a date first.");
            return false;
        }

        if (!timeSlotSelected) {
            showAlert("Please select a time slot.");
            return false;
        }

        // All checks passed, show the confirmation dialog
        bookingDialog.style.display = 'flex';
        return false; // Prevent default form submission immediately
    }

});
</script>

<div id="logoutDialog" class="dialog-overlay" style="display: none;">
    <div class="dialog-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out?</p>
        <div class="dialog-buttons">
            <button id="cancelLogout" type="button" class="cancel-btn">Cancel</button>
            <button id="confirmLogoutBtn" type="button" class="confirm-btn">Logout</button>
        </div>
    </div>
</div>

<div id="bookingConfirmationDialog" class="dialog-overlay" style="display: none;">
    <div class="dialog-content">
        <h3>Confirm Session Booking</h3>
        <p>Are you sure you want to book this session?</p>
        <div class="dialog-buttons">
            <button id="cancelBookingBtn" type="button" class="cancel-btn">Cancel</button>
            <button id="confirmBookingBtn" type="button" class="confirm-btn">Book Now</button>
        </div>
    </div>
</div>

<div id="alertValidationDialog" class="dialog-overlay" style="display: none;">
    <div class="dialog-content">
        <h3>Validation Error</h3>
        <p id="alertMessage"></p>
        <div class="dialog-buttons">
            <button id="closeAlertBtn" type="button" class="confirm-btn">OK</button>
        </div>
    </div>
</div>

</body>
</html>
