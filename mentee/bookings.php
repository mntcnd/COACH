<?php
session_start();

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
$bookings = [];

// Fetch all necessary mentee details (ID, name, icon) in one query for efficiency.
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$menteeData = $result->fetch_assoc();

// Exit if user data isn't found to prevent errors
if (!$menteeData) {
    // Optional: Add more robust error handling or redirection
    die("Error: Could not find user data.");
}

$menteeId = $menteeData['user_id'];
$firstName = $menteeData['first_name'];
$menteeIcon = $menteeData['icon'];


// --- FETCH BOOKINGS ---
// The query correctly uses user_id, but the parameter binding must be an integer.
$stmt = $conn->prepare("SELECT sb.*, fc.id as forum_id 
                        FROM session_bookings sb 
                        LEFT JOIN forum_chats fc ON sb.course_title = fc.course_title 
                                                AND sb.session_date = fc.session_date 
                                                AND sb.time_slot = fc.time_slot 
                        WHERE sb.user_id = ? 
                        ORDER BY sb.session_date DESC, sb.booking_time DESC");
// Bind the integer user_id ('i') instead of the username string ('s').
$stmt->bind_param("i", $menteeId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

// --- COUNT UNREAD NOTIFICATIONS ---
// The query correctly uses user_id, but the parameter binding must be an integer.
$notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM booking_notifications WHERE user_id = ? AND recipient_type = 'mentee' AND is_read = 0");
// Bind the integer user_id ('i') instead of the username string ('s').
$notifStmt->bind_param("i", $menteeId);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifCount = $notifResult->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/bookings.css">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
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
                <p class="empty-text">You haven't booked any sessions yet. Start learning by booking a session now!</p>
                <a href="CoachMenteeHome.php#courses" class="book-now-btn">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="bookings-container">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-title"><?php echo htmlspecialchars($booking['course_title']); ?></div>
                            <div class="booking-status status-<?php echo htmlspecialchars($booking['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="booking-detail">
                                <div class="detail-label">Date:</div>
                                <div class="detail-value"><?php echo htmlspecialchars(date('F j, Y', strtotime($booking['session_date']))); ?></div>
                            </div>
                            
                            <div class="booking-detail">
                                <div class="detail-label">Time:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['time_slot']); ?></div>
                            </div>
                            
                            <div class="booking-detail">
                                <div class="detail-label">Booked on:</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($booking['booking_time'])); ?></div>
                            </div>
                            
                            <?php if (!empty($booking['notes'])): ?>
                                <div class="booking-detail">
                                    <div class="detail-label">Notes:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($booking['notes']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($booking['status'] === 'pending'): ?>
                                <a href="cancel_booking.php?id=<?php echo $booking['booking_id']; ?>" class="action-btn cancel-btn" onclick="return confirm('Are you sure you want to cancel this booking?')">Cancel</a>
                            <?php elseif ($booking['status'] === 'approved' && $booking['forum_id']): ?>
                                <?php
                                // Logic to determine if the session is over to show "Review" instead of "Join"
                                $sessionDateTimeString = $booking['session_date'] . ' ' . explode(' - ', $booking['time_slot'])[1];
                                $sessionEndDateTime = new DateTime($sessionDateTimeString);
                                $now = new DateTime();
                                
                                $isSessionOver = ($now > $sessionEndDateTime);
                                ?>
                                
                                <?php if ($isSessionOver): ?>
                                    <a href="forum-chat.php?view=forum&forum_id=<?php echo $booking['forum_id']; ?>&review=true" class="action-btn view-btn" style="background-color: #e2e3e5; color: #333;">Review Session</a>
                                <?php else: ?>
                                    <a href="forum-chat.php?view=forum&forum_id=<?php echo $booking['forum_id']; ?>" class="action-btn view-btn">Join Session</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

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