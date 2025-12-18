<?php
session_start();
require '../connection/db_connection.php';

// Security: Ensure an admin is performing this action
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'Admin' && $_SESSION['user_type'] !== 'Super Admin')) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit();
}

// --- Get and validate POST data ---
$resourceID = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : null;
$newStatus = isset($_POST['action']) ? trim($_POST['action']) : null;
$rejectionReason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;

$validStatuses = ['Approved', 'Rejected'];

if (!$resourceID || !in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo "Invalid input.";
    exit();
}

// --- Get resource details and user info using the user_id ---
$userInfo = null;
$stmt = $conn->prepare("
    SELECT r.Resource_Title, u.email, u.first_name, u.last_name
    FROM resources r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.Resource_ID = ?
");
$stmt->bind_param("i", $resourceID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $userInfo = $result->fetch_assoc();
}
$stmt->close();

// --- Update the resource status ---
if ($newStatus === 'Rejected' && !empty($rejectionReason)) {
    $updateStmt = $conn->prepare("UPDATE resources SET Status = ?, Reason = ? WHERE Resource_ID = ?");
    $updateStmt->bind_param("ssi", $newStatus, $rejectionReason, $resourceID);
} else {
    $updateStmt = $conn->prepare("UPDATE resources SET Status = ? WHERE Resource_ID = ?");
    $updateStmt->bind_param("si", $newStatus, $resourceID);
}

if ($updateStmt->execute()) {
    if ($updateStmt->affected_rows > 0) {
        // --- Send email notification if user info was found ---
        if ($userInfo && !empty($userInfo['email'])) {
            $subject = "Your Uploaded Resource Status Has Been Updated";
            $recipientName = htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']);
            $resourceTitle = htmlspecialchars($userInfo['Resource_Title']);
            $email = $userInfo['email'];

            $statusMessage = '';
            $additionalContent = '';

            if ($newStatus === 'Approved') {
                $statusMessage = "Congratulations! The status of your uploaded resource titled \"$resourceTitle\" has been approved and is now available in our resource library.";
            } else if ($newStatus === 'Rejected') {
                $statusMessage = "Thank you for contributing. The status of your uploaded resource titled \"$resourceTitle\" has been updated to: Rejected.";
                $additionalContent = '
                    <div style="background-color: #F8F9FA; border: 1px solid #E5E5E5; border-radius: 4px; padding: 15px; margin: 20px 0;">
                        <p style="margin: 5px 0;"><strong>Reason for rejection:</strong> '.htmlspecialchars($rejectionReason).'</p>
                    </div>
                    <p>If you have any questions, please don\'t hesitate to contact us.</p>
                ';
            }

            $message = '
            <!DOCTYPE html><html><body style="font-family: Arial, sans-serif; color: #333;">
                <div style="max-width: 600px; margin: auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                    <div style="background-color: #512A72; padding: 20px; text-align: center; color: white;">
                        <h1 style="margin: 0; font-size: 24px;">COACH Resource Update</h1>
                    </div>
                    <div style="padding: 20px 30px;">
                        <p>Dear '.$recipientName.',</p>
                        <p>'.$statusMessage.'</p>
                        '.$additionalContent.'
                        <p>Best regards,<br>The COACH Team</p>
                    </div>
                    <div style="background-color: #f2f2f2; padding: 15px; font-size: 12px; color: #777; text-align: center;">
                        © '.date("Y").' COACH. All rights reserved.
                    </div>
                </div>
            </body></html>';
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: COACH System <no-reply@coach.com>" . "\r\n";

            mail($email, $subject, $message, $headers);
        }
        
        // Redirect back to the resources page
        header("Location: resource.php");
        exit();

    } else {
        echo "No changes were made. The status may have already been updated.";
    }
} else {
    http_response_code(500);
    echo "Failed to update status: " . $updateStmt->error;
}

$updateStmt->close();
$conn->close();
?>
