<?php
session_start();

// Database connection (no changes needed here)
require '../connection/db_connection.php';
// UPDATED: Session check now looks for a generic user_id and verifies the user_type.
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'Admin' && $_SESSION['user_type'] !== 'Super Admin')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get the current user's info from the session
$currentUserId = $_SESSION['user_id'];
$currentUser = $_SESSION['username']; 
$displayName = $_SESSION['user_full_name'] ?? $currentUser; // Use full name from session if available

// Handle message submission for forum chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['forum_id'])) {
    $message = trim($_POST['message']);
    $forumId = $_POST['forum_id'];
    $isAdmin = 1; // The session check has already confirmed this user is an Admin or Super Admin.
    
    if (!empty($message)) {
        // UPDATED: The INSERT query now uses `user_id` instead of `username`.
        // The chat_messages table in your new schema correctly links to users via user_id.
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, display_name, message, is_admin, chat_type, forum_id) VALUES (?, ?, ?, ?, 'forum', ?)");
        
        // UPDATED: The bind_param types are changed from "sssii" to "issii" to match the new data types (user_id is an integer).
        $stmt->bind_param("issii", $currentUserId, $displayName, $message, $isAdmin, $forumId);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
        }
        $stmt->close();
        exit();
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>