<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Set JSON response header
header('Content-Type: application/json');

session_start();

// Check if SuperAdmin is logged in - FIXED SESSION CHECK
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Super Admin') {
    echo json_encode(['error' => 'Unauthorized access', 'exists' => false]);
    exit();
}

try {
    // Check if username is provided
    if (!isset($_POST['username']) || empty(trim($_POST['username']))) {
        echo json_encode(['exists' => false, 'error' => 'Username not provided']);
        exit();
    }

    $username = trim($_POST['username']);

    // Validate username format (basic validation)
    if (strlen($username) < 3) {
        echo json_encode(['exists' => false, 'error' => 'Username must be at least 3 characters']);
        exit();
    }

    // Connect to database
    require __DIR__ . '/connection/db_connection.php';

    // Check if username exists - using COUNT for better performance
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['exists' => false, 'error' => 'Database error']);
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // Return whether username exists
    echo json_encode(['exists' => ($row['count'] > 0)]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Username check error: " . $e->getMessage());
    echo json_encode(['exists' => false, 'error' => 'An error occurred']);
    exit();
}
?>