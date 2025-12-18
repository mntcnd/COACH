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

$username = $_SESSION['username'];
$notifications = [];
$message = "";

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notificationId = $_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE booking_notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("is", $notificationId, $username);
    $stmt->execute();
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE booking_notifications SET is_read = 1 WHERE user_id = ? AND recipient_type = 'mentee'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $message = "All notifications marked as read.";
}

// Fetch notifications for this mentee
$stmt = $conn->prepare("
    SELECT n.*, b.* 
    FROM booking_notifications n
    LEFT JOIN session_bookings b ON n.booking_id = b.booking_id
    WHERE n.user_id = ? AND n.recipient_type = 'mentee'
    ORDER BY n.created_at DESC
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get mentee details
$stmt = $conn->prepare("SELECT first_Name, last_Name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$menteeData = $result->fetch_assoc();
$firstName = $menteeData['first_Name'];
$menteeIcon = $menteeData['icon'];

// Count unread notifications
$unreadCount = 0;
foreach ($notifications as $notification) {
    if ($notification['is_read'] == 0) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
   
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
                    <li><a href="CoachMenteeHome.php">Home</a></li>
                    <li><a href="CoachMenteeHome.php#courses">Courses</a></li>
                    <li><a href="CoachMenteeHome.php#resourcelibrary">Resource Library</a></li>
                    <li><a href="CoachMenteeHome.php#mentors">Activities</a></li>
                    <li><a href="forum-chat.php">Sessions</a></li>
                    <li><a href="forums.php">Forums</a></li>
                </ul>
            </div>

            <div class="nav-profile">
                <!-- Notification Icon -->
                <a href="mentee_notifications.php" class="notification-icon">
                    <ion-icon name="notifications-outline" style="font-size: 24px;"></ion-icon>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
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
                        <li><a href="mentee_bookings.php">My Bookings</a></li>
                        <li><a href="#settings">Settings</a></li>
                        <li><a href="#" onclick="confirmLogout()">Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </section>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
        
        <div class="notification-header">
            <h1>My Notifications</h1>
            <?php if (!empty($notifications)): ?>
                <a href="?mark_all_read=1" class="mark-all-btn">Mark All as Read</a>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <ion-icon name="notifications-outline"></ion-icon>
                </div>
                <h3>No Notifications</h3>
                <p class="empty-text">You don't have any notifications at the moment.</p>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-header">
                            <div class="notification-time">
                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <span class="unread-indicator">New</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        
                        <?php if (isset($notification['course_title'])): ?>
                            <div class="booking-details">
                                <div class="booking-detail">
                                    <ion-icon name="book-outline"></ion-icon>
                                    <span><?php echo htmlspecialchars($notification['course_title']); ?></span>
                                </div>
                                <div class="booking-detail">
                                    <ion-icon name="calendar-outline"></ion-icon>
                                    <span><?php echo htmlspecialchars($notification['session_date']); ?></span>
                                </div>
                                <div class="booking-detail">
                                    <ion-icon name="time-outline"></ion-icon>
                                    <span><?php echo htmlspecialchars($notification['time_slot']); ?></span>
                                </div>
                                <div class="booking-detail">
                                    <ion-icon name="checkmark-circle-outline"></ion-icon>
                                    <span><?php echo ucfirst(htmlspecialchars($notification['status'])); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notification-actions">
                            <?php if (isset($notification['booking_id']) && $notification['status'] === 'approved' && $notification['forum_id']): ?>
                                <a href="forum-chat.php?view=forum&forum_id=<?php echo $notification['forum_id']; ?>" class="action-btn view-btn">
                                    <ion-icon name="chatbubbles-outline"></ion-icon> Join Session
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!$notification['is_read']): ?>
                                <a href="?mark_read=<?php echo $notification['notification_id']; ?>" class="action-btn mark-read-btn">
                                    <ion-icon name="checkmark-done-outline"></ion-icon> Mark as Read
                                </a>
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
                window.location.href = "login.php";
            } else {
                return false;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const profileIcon = document.getElementById('profile-icon');
            const profileMenu = document.getElementById('profile-menu');
            
            profileIcon.addEventListener('click', function(e) {
                e.preventDefault();
                profileMenu.classList.toggle('hide');
            });
            
            document.addEventListener('click', function(e) {
                if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.add('hide');
                }
            });
        });
    </script>
</body>
</html>