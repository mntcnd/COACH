<?php
session_start();

// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Super Admin'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Super Admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
require '../connection/db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if 'check' parameter exists
if (!isset($_POST['check'])) {
    echo json_encode(['error' => 'Missing check parameter']);
    exit;
}

$check_type = $_POST['check'];

try {
    switch ($check_type) {
        case 'username':
            if (!isset($_POST['username']) || empty(trim($_POST['username']))) {
                echo json_encode(['error' => 'Username is required']);
                exit;
            }
            
            $username = trim($_POST['username']);
            
            // Check if username exists in the users table
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $exists = $result->num_rows > 0;
            
            echo json_encode([
                'exists' => $exists,
                'valid' => true
            ]);
            
            $stmt->close();
            break;
            
        case 'email':
            if (!isset($_POST['email']) || empty(trim($_POST['email']))) {
                echo json_encode(['error' => 'Email is required']);
                exit;
            }
            
            $email = trim($_POST['email']);
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'valid' => false,
                    'exists' => false,
                    'verified' => false
                ]);
                exit;
            }
            
            // Check if email exists in the users table
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $exists = $result->num_rows > 0;
            
            // Basic domain verification (optional - you can disable this if it causes issues)
            $domain = substr(strrchr($email, "@"), 1);
            $verified = true; // Set to true by default, or implement actual domain verification
            
            // If you want to implement domain verification, uncomment below:
            /*
            $verified = false;
            if (function_exists('checkdnsrr')) {
                $verified = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
            } else {
                $verified = true; // Fallback if checkdnsrr is not available
            }
            */
            
            echo json_encode([
                'valid' => true,
                'exists' => $exists,
                'verified' => $verified
            ]);
            
            $stmt->close();
            break;
            
        default:
            echo json_encode(['error' => 'Invalid check type']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error in check_user_data.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>