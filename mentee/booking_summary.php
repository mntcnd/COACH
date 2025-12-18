<?php
session_start();

// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // If not a Mentee, redirect to the login page.
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
require '../connection/db_connection.php';

$message = "";
$bookingComplete = false;
$username = $_SESSION['username'];

// --- FETCH USER DETAILS ---
// Get mentee user_id and name from the new 'users' table.
// The schema has been updated to use a single 'users' table and user_id as the primary key.
$stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$menteeData = $result->fetch_assoc();
$menteeId = $menteeData['user_id'];
$menteeName = $menteeData['first_name'] . ' ' . $menteeData['last_name'];

// --- PROCESS BOOKING FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['course_title'], $_GET['selected_date'], $_GET['time_slot'])) {
    $courseTitle = $_GET['course_title'];
    $sessionDate = $_GET['selected_date'];
    $timeSlot = $_GET['time_slot'];
    $notes = $_GET['notes'] ?? null;
    
    // --- CHECK FOR EXISTING BOOKING ---
    // The 'session_bookings' table now uses 'user_id' instead of 'mentee_username'.
    $stmt = $conn->prepare("SELECT * FROM session_bookings WHERE user_id = ? AND course_title = ? AND session_date = ? AND time_slot = ?");
    $stmt->bind_param("isss", $menteeId, $courseTitle, $sessionDate, $timeSlot);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $message = "You already have a booking for this session.";
    } else {
        // --- INSERT NEW BOOKING ---
        // The 'session_bookings' table now uses 'user_id'.
        $stmt = $conn->prepare("INSERT INTO session_bookings (user_id, course_title, session_date, time_slot, status, notes) VALUES (?, ?, ?, ?, 'approved', ?)");
        $stmt->bind_param("issss", $menteeId, $courseTitle, $sessionDate, $timeSlot, $notes);
        
        if ($stmt->execute()) {
            $bookingId = $conn->insert_id;
            $bookingComplete = true;
            $message = "Your booking has been confirmed successfully!";
            
            // --- CREATE NOTIFICATIONS FOR ADMINS ---
            $notificationMsg = "$menteeName has booked a $courseTitle session on $sessionDate at $timeSlot";
            
            // Get all admin user_ids from the new 'users' table.
            // The old 'admins' table has been removed.
            $adminResult = $conn->query("SELECT user_id FROM users WHERE user_type = 'Admin'");
            while ($admin = $adminResult->fetch_assoc()) {
                $adminId = $admin['user_id'];
                
                // The 'booking_notifications' table now uses 'user_id' instead of 'recipient_username'.
                $stmt = $conn->prepare("INSERT INTO booking_notifications (booking_id, recipient_type, user_id, message) VALUES (?, 'admin', ?, ?)");
                $stmt->bind_param("iis", $bookingId, $adminId, $notificationMsg);
                $stmt->execute();
            }
            
            // --- FORUM MANAGEMENT ---
            // Check if a forum already exists for this session.
            $forumCheck = $conn->prepare("SELECT id FROM forum_chats WHERE course_title = ? AND session_date = ? AND time_slot = ?");
            $forumCheck->bind_param("sss", $courseTitle, $sessionDate, $timeSlot);
            $forumCheck->execute();
            $forumResult = $forumCheck->get_result();
            
            if ($forumResult->num_rows === 0) {
                // Create a forum for this session if it doesn't exist.
                $forumTitle = "$courseTitle Session";
                $createForum = $conn->prepare("INSERT INTO forum_chats (title, course_title, session_date, time_slot) VALUES (?, ?, ?, ?)");
                $createForum->bind_param("ssss", $forumTitle, $courseTitle, $sessionDate, $timeSlot);
                $createForum->execute();
                $forumId = $conn->insert_id;
                
                // Add the mentee to the forum participants using user_id.
                $addParticipant = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
                $addParticipant->bind_param("ii", $forumId, $menteeId);
                $addParticipant->execute();
                
                // Add to session_participants with active status using user_id.
                $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'active')");
                $insertStatus->bind_param("ii", $forumId, $menteeId);
                $insertStatus->execute();
                
                // Update the booking with the new forum ID.
                $updateBooking = $conn->prepare("UPDATE session_bookings SET forum_id = ? WHERE booking_id = ?");
                $updateBooking->bind_param("ii", $forumId, $bookingId);
                $updateBooking->execute();
            } else {
                // Forum already exists, so add the mentee to it.
                $forumId = $forumResult->fetch_assoc()['id'];
                
                // Check if mentee is already in the forum using user_id.
                $checkParticipant = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
                $checkParticipant->bind_param("ii", $forumId, $menteeId);
                $checkParticipant->execute();
                $participantResult = $checkParticipant->get_result();
                
                // Use INSERT IGNORE for forum_participants to safely add the user without errors if they already exist.
$addParticipant = $conn->prepare("INSERT IGNORE INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
$addParticipant->bind_param("ii", $forumId, $menteeId);
$addParticipant->execute();

// Use INSERT ... ON DUPLICATE KEY UPDATE for session_participants.
// This will insert the new participant or update their status to 'active' if they already exist.
$sql = "INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'active') 
        ON DUPLICATE KEY UPDATE status = 'active'";
$insertOrUpdateStatus = $conn->prepare($sql);
$insertOrUpdateStatus->bind_param("ii", $forumId, $menteeId);
$insertOrUpdateStatus->execute();
                
                // Update the booking with the existing forum ID.
                $updateBooking = $conn->prepare("UPDATE session_bookings SET forum_id = ? WHERE booking_id = ?");
                $updateBooking->bind_param("ii", $forumId, $bookingId);
                $updateBooking->execute();
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
    <link rel="stylesheet" href="sessions.css">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <style>

         * {
            font-family: "Ubuntu", sans-serif;
            text-transform: none;
        }

        body {
            background-color: #b185beff;
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
            <h2>Booking Confirmed!</h2>
            <div class="booking-status">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            
            <div class="booking-details">
                <h3>Booking Details</h3>
                <p><span>Course:</span> <span><?php echo htmlspecialchars($_GET['course_title']); ?></span></p>
                <p><span>Date:</span> <span><?php echo htmlspecialchars($_GET['selected_date']); ?></span></p>
                <p><span>Time:</span> <span><?php echo htmlspecialchars($_GET['time_slot']); ?></span></p>
                <p><span>Status:</span> <span>Confirmed</span></p>
                
                <?php if (!empty($_GET['notes'])): ?>
                    <p><span>Notes:</span> <span><?php echo htmlspecialchars($_GET['notes']); ?></span></p>
                <?php endif; ?>
            </div>
            
            <p>You can now access this session from your bookings page.</p>
        <?php else: ?>
            <h2>Booking Error</h2>
            <div class="booking-status">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="buttons">
            <a href="home.php" class="btn secondary-btn">Back to Home</a>
            <a href="forum-chat.php" class="btn primary-btn">View My Bookings</a>
        </div>
    </div>
</body>
</html>
