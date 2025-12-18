<?php
require '../connection/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_POST['id'];
    $newStatus = $_POST['status'];
    $reason = isset($_POST['reason']) ? $_POST['reason'] : '';

    // Use a prepared statement to prevent SQL injection
    $sql = "UPDATE users SET status = ?, reason = ? WHERE user_id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Bind the parameters: s = string, i = integer
        $stmt->bind_param("ssi", $newStatus, $reason, $userId);
        
        if ($stmt->execute()) {
            echo "Mentor status has been updated successfully.";
        } else {
            echo "Error updating status: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        echo "Error preparing statement: " . $conn->error;
    }
    
    $conn->close();
}
?>