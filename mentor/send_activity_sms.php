<?php
require_once('../db/connection.php');

// Semaphore API Configuration
$apiKey = '55628b35a664abb55e0f93b86b448f35'; // Replace with your actual API key
$apiUrl = 'https://api.semaphore.co/api/v4/messages';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['mentee_ids']) || !isset($data['activity_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$menteeIds = $data['mentee_ids'];
$activityId = $data['activity_id'];

// Fetch activity details
$activityQuery = "SELECT a.title, c.course_title, l.lesson_title 
                  FROM activities a
                  JOIN courses c ON a.course_id = c.course_id
                  JOIN lessons l ON a.lesson_id = l.lesson_id
                  WHERE a.activity_id = ?";
$stmt = $conn->prepare($activityQuery);
$stmt->bind_param("i", $activityId);
$stmt->execute();
$activityResult = $stmt->get_result();
$activity = $activityResult->fetch_assoc();

if (!$activity) {
    echo json_encode(['success' => false, 'message' => 'Activity not found']);
    exit;
}

$activityTitle = $activity['title'];
$courseTitle = $activity['course_title'];
$lessonTitle = $activity['lesson_title'];

// Fetch mentees' details
$placeholders = str_repeat('?,', count($menteeIds) - 1) . '?';
$menteeQuery = "SELECT firstname, lastname, contact_number 
                FROM users 
                WHERE user_id IN ($placeholders) 
                AND contact_number IS NOT NULL 
                AND contact_number != ''";

$stmt = $conn->prepare($menteeQuery);
$types = str_repeat('i', count($menteeIds));
$stmt->bind_param($types, ...$menteeIds);
$stmt->execute();
$menteesResult = $stmt->get_result();

$successCount = 0;
$failedCount = 0;
$errors = [];

// Send SMS to each mentee
while ($mentee = $menteesResult->fetch_assoc()) {
    $menteeName = $mentee['firstname'] . ' ' . $mentee['lastname'];
    $phoneNumber = $mentee['contact_number'];
    
    // Format phone number (ensure it starts with +63 for Philippines)
    if (substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    } elseif (substr($phoneNumber, 0, 3) !== '+63') {
        $phoneNumber = '+63' . $phoneNumber;
    }
    
    // Compose SMS message
    $message = "Hi {$menteeName}!\n\n";
    $message .= "You have been assigned a new activity:\n\n";
    $message .= "Activity: {$activityTitle}\n";
    $message .= "Course: {$courseTitle}\n";
    $message .= "Lesson: {$lessonTitle}\n\n";
    $message .= "Please complete this activity as soon as possible. Log in to your account to view details and submit your work.\n\n";
    $message .= "Thank you!";
    
    // Prepare SMS data
    $smsData = [
        'apikey' => $apiKey,
        'number' => $phoneNumber,
        'message' => $message,
        'sendername' => 'MENTORSHIP' // Change this to your registered sender name
    ];
    
    // Send SMS via cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($smsData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check for cURL errors (network, timeout, etc.)
if ($response === false) {
    $failedCount++;
    $errors[] = "Failed to send SMS to {$menteeName} ({$phoneNumber}). cURL Error: " . $curlError;
} 
// Check for non-200 HTTP response
elseif ($httpCode !== 200) {
    $failedCount++;
    $errors[] = "Failed to send SMS to {$menteeName} ({$phoneNumber}). HTTP Code: " . $httpCode . ", Response: " . $response;
}
// Check for API success response
else {
    $apiResponse = json_decode($response, true);
    
    if (is_array($apiResponse) && !empty($apiResponse)) {
        // Check for error response
        if (isset($apiResponse['error']) || isset($apiResponse['code'])) {
            $failedCount++;
            $errorMsg = $apiResponse['error'] ?? $apiResponse['message'] ?? 'Unknown error';
            $errors[] = "Failed to send SMS to {$menteeName} ({$phoneNumber}). API Error: " . $errorMsg;
        }
        // Success: Array format
        elseif (isset($apiResponse[0]['message_id'])) {
            $successCount++;
        }
        // Success: Object format
        elseif (isset($apiResponse['message_id'])) {
            $successCount++;
        }
        else {
            $failedCount++;
            $errors[] = "Failed to send SMS to {$menteeName} ({$phoneNumber}). Unexpected API Response: " . $response;
        }
    } else {
        $failedCount++;
        $errors[] = "Failed to send SMS to {$menteeName} ({$phoneNumber}). Invalid API Response: " . $response;
    }
}
}

// Return response
if ($successCount > 0 && $failedCount === 0) {
    echo json_encode([
        'success' => true, 
        'message' => "SMS sent successfully to {$successCount} mentee(s)"
    ]);
} elseif ($successCount > 0 && $failedCount > 0) {
    echo json_encode([
        'success' => true, 
        'message' => "SMS sent to {$successCount} mentee(s). {$failedCount} failed.",
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => "Failed to send SMS to all mentees",
        'errors' => $errors
    ]);
}

// Also log to file for debugging
$debug_log = __DIR__ . '/sms_debug_log.txt';
$debug_message = date('Y-m-d H:i:s') . " - ";
$debug_message .= "Activity ID: $activityId | ";
$debug_message .= "Mentees: " . count($menteeIds) . " | ";
$debug_message .= "Success: $successCount | Failed: $failedCount\n";

if (!empty($errors)) {
    $debug_message .= "Errors: " . implode('; ', $errors) . "\n";
}

file_put_contents($debug_log, $debug_message, FILE_APPEND);

$conn->close();
?>