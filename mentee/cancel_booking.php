<?php
session_start();

// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // If not a Mentee, redirect to the login page.
    header("Location: login.php");
    exit();
}

// --- FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

$username = $_SESSION['username'];
$message = "";

if (isset($_GET['id'])) {
    $bookingId = $_GET['id'];
    
    // Verify this booking belongs to the logged-in user
    $stmt = $conn->prepare("SELECT * FROM session_bookings WHERE booking_id = ? AND user_id = ?");
    $stmt->bind_param("is", $bookingId, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        
        // Only allow cancellation of pending bookings
        if ($booking['status'] === 'pending') {
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
        $message = "Invalid booking or you don't have permission to cancel it.";
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
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
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
</html>