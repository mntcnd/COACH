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
$firstName = $menteeData['First_Name'];
$menteeIcon = $menteeData['Mentee_Icon'];

// Count unread notifications
$notifStmt = $conn->prepare("SELECT COUNT(*) as count FROM booking_notifications WHERE user_id = ? AND recipient_type = 'mentee' AND is_read = 0");
$notifStmt->bind_param("s", $username);
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
    <link rel="stylesheet" href="css/mentee_navbarstyle.css">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
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
                <img src="img/LogoCoach.png" alt="Logo">
                <span>COACH</span>
            </div>

            <div class="nav-center">
                <ul class="nav_items" id="nav_links">
                    <li><a href="CoachMenteeHome.php">Home</a></li>
                    <li><a href="CoachMenteeHome.php#courses">Courses</a></li>
                    <li><a href="resource_library.php">Resource Library</a></li>
                    <li><a href="CoachMenteeHome.php#mentors">Activities</a></li>
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
                        <li><a href="mentee_bookings.php">My Bookings</a></li>
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
                                <div class="detail-value"><?php echo htmlspecialchars($booking['session_date']); ?></div>
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
                                <a href="forum-chat.php?view=forum&forum_id=<?php echo $booking['forum_id']; ?>" class="action-btn view-btn">Join Session</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<script src="mentee.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Select all necessary elements for Profile and Logout
    const profileIcon = document.getElementById('profile-icon');
    const profileMenu = document.getElementById('profile-menu');
    // NEW/MODIFIED: Select logout dialog elements
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    // --- Profile Menu Toggle Logic (PRESERVED/FIXED) ---
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener('click', function(e) {
            e.preventDefault();
            // Using toggle('show') and toggle('hide') for consistency
            profileMenu.classList.toggle('show');
            profileMenu.classList.toggle('hide');
        });
        
        document.addEventListener('click', function(e) {
            // Check if click is outside both the icon and the menu
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove('show');
                profileMenu.classList.add('hide');
            }
        });
    }

    // ==========================================================
    // --- LOGOUT DIALOG LOGIC (NEW) ---
    // ==========================================================
    
    // Make confirmLogout function globally accessible (called from the anchor tag in HTML)
    window.confirmLogout = function(e) { 
        if (e) e.preventDefault(); // FIX: Prevent the default anchor behavior (# in URL)
        if (logoutDialog) {
            logoutDialog.style.display = "flex";
        }
    }

    // FIX: Attach event listeners to the dialog buttons
    if (cancelLogoutBtn && logoutDialog) {
        cancelLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            logoutDialog.style.display = "none";
        });
    }

    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            // Redirect to the login page (or logout script). Use "login.php" if it's in the same directory.
            // If this file is *not* in a subdirectory, the path might be correct as "login.php".
            // If it *is* in a subdirectory (like other files were), you might need "../login.php". 
            // I'm using the original relative path provided ("login.php") but advise checking if a ../ is needed.
            window.location.href = "login.php"; 
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