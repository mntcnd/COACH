<?php
// check_ban_status.php
// Include this file in your login script

function checkAndUpdateBanStatus($username, $conn) {
    // Check if user is banned
    $stmt = $conn->prepare("SELECT ban_id, unban_datetime FROM banned_users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $ban = $result->fetch_assoc();
            
            // If unban_datetime exists and has passed, remove the ban
            if ($ban['unban_datetime'] !== null) {
                $unbanTime = strtotime($ban['unban_datetime']);
                $currentTime = time();
                
                if ($currentTime >= $unbanTime) {
                    // Ban has expired, remove it
                    $deleteStmt = $conn->prepare("DELETE FROM banned_users WHERE ban_id = ?");
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("i", $ban['ban_id']);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                    }
                    return false; // User is no longer banned
                }
            }
            
            // User is still banned
            return true;
        }
        $stmt->close();
    }
    
    return false; // User is not banned
}
?>