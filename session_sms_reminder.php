<?php
/**
 * COACH Tutoring System - Session SMS Reminder
 * This script sends SMS reminders 15 minutes before scheduled sessions
 * Run this script via cron job every minute
 */

// Include database connection
require 'connection/db_connection.php';

// Semaphore API configuration
$apikey = '55628b35a664abb55e0f93b86b448f35'; // Replace with your actual API key
$sendername = 'BPSUCOACH';

// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Get current date and time
$currentDateTime = new DateTime('now', new DateTimeZone('Asia/Manila')); // Adjust timezone as needed
$reminderDateTime = clone $currentDateTime;
$reminderDateTime->modify('+15 minutes');

// Format for comparison
$targetDate = $reminderDateTime->format('Y-m-d');
$targetTimeStart = $reminderDateTime->format('H:i');
$targetTimeEnd = $reminderDateTime->modify('+1 minute')->format('H:i');

// Query to get sessions that need reminders (within 15-16 minutes from now)
// AFTER: (ADD THE BOLDED LINE)
$query = "
    SELECT 
        sb.booking_id,  /* 🛑 IMPORTANT: ADD THIS PRIMARY KEY! */
        sb.user_id,
        sb.course_title,
        sb.session_date,
        sb.time_slot,
        u.first_name,
        u.last_name,
        u.contact_number
    FROM 
        session_bookings sb
    INNER JOIN 
        users u ON sb.user_id = u.user_id
    WHERE 
        sb.session_date = ?
        AND u.contact_number IS NOT NULL
        AND u.contact_number != ''
        AND sb.sms_reminder_sent = 0  /* 🛑 THIS IS THE CRITICAL FILTER */
";

// Execute query
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $targetDate);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($session = $result->fetch_assoc()) {
        // Parse the time slot (e.g., "4:00 PM - 5:00 PM")
        $timeSlotParts = explode(' - ', $session['time_slot']);
        if (count($timeSlotParts) < 2) {
            continue; // Skip if time slot format is invalid
        }
        
        $sessionStartTime = $timeSlotParts[0];
        
        // Convert session start time to 24-hour format for comparison
        $sessionStartDateTime = DateTime::createFromFormat('g:i A', trim($sessionStartTime));
        if (!$sessionStartDateTime) {
            continue; // Skip if time parsing fails
        }
        
        $sessionStart24h = $sessionStartDateTime->format('H:i');
        
        // Check if this session starts in approximately 15 minutes
        $timeDiff = abs(strtotime($sessionStart24h) - strtotime($targetTimeStart));
        
        // If the time difference is less than 2 minutes (allowing for cron job execution variance)
        if ($timeDiff <= 120) {
            // Format the session date for display
            $sessionDateFormatted = date('F j, Y', strtotime($session['session_date']));
            
            // Prepare the SMS message
            $message = "Good day, Mentee {$session['first_name']}! Just a friendly nudge from COACH—your tutoring session is only 15 minutes away.\n\n";
            $message .= "Course: {$session['course_title']}\n";
            $message .= "Date: {$sessionDateFormatted}\n";
            $message .= "Time: {$session['time_slot']}\n\n";
            $message .= "Get ready to learn something new and make the most out of your session. See you shortly, your mentor is excited to see you soon!";
            
            // Clean the contact number (remove spaces, dashes, etc.)
            $contactNumber = preg_replace('/[^0-9]/', '', $session['contact_number']);
            
            // Ensure the number starts with 0 for Philippine format
            if (!str_starts_with($contactNumber, '0') && strlen($contactNumber) == 10) {
                $contactNumber = '0' . $contactNumber;
            }
            
            // Send SMS via Semaphore API
           $smsResult = sendSMS($contactNumber, $message, $apikey, $sendername);

            // Log the result
            if ($smsResult['success']) {
                echo "SMS sent successfully to {$session['first_name']} {$session['last_name']} ({$contactNumber})\n";
                error_log("SMS reminder sent to user_id: {$session['user_id']} for session on {$session['session_date']} at {$session['time_slot']}");

                // START: CRITICAL ADDITION TO PREVENT DUPLICATE SMS 
                $updateQuery = "UPDATE session_bookings SET sms_reminder_sent = 1 WHERE booking_id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("i", $session['booking_id']); // Assuming booking_id is integer (i)
                $updateStmt->execute();
                $updateStmt->close();
                // END: CRITICAL ADDITION 
            } else {
                echo "Failed to send SMS to {$session['first_name']} {$session['last_name']} ({$contactNumber}): {$smsResult['message']}\n";
                error_log("Failed to send SMS to user_id: {$session['user_id']} - Error: {$smsResult['message']}");
            }
        }
    }
}

$stmt->close();
$conn->close();

/**
 * Send SMS via Semaphore API
 *
 * @param string $number Recipient's phone number
 * @param string $message SMS message content
 * @param string $apikey Semaphore API key
 * @param string $sendername Sender name
 * @return array Result array with 'success' boolean and 'message' string
 */
function sendSMS($number, $message, $apikey, $sendername) {
    $ch = curl_init();
    $parameters = array(
        'apikey' => $apikey,
        'number' => $number,
        'message' => $message,
        'sendername' => $sendername
    );
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $output = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'message' => "cURL Error: $error"
        ];
    }
    
    $response = json_decode($output, true);
    
    if ($httpCode == 200 || $httpCode == 201) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'response' => $response
        ];
    } else {
        return [
            'success' => false,
            'message' => "HTTP Error $httpCode: " . ($response['message'] ?? $output)
        ];
    }
}

echo "SMS reminder check completed at " . date('Y-m-d H:i:s') . "\n";
?>