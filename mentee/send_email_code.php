<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "coach");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];
$sql = "SELECT email FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$email = $row['Email'];
$stmt->close();

if (isset($_POST['send_code'])) {
    // Generate a random 6-digit code
    $verification_code = rand(100000, 999999);
    $_SESSION['email_verification_code'] = $verification_code;

    // Send email using PHP mail()
    $subject = "Your COACH Email Verification Code";
    $message = "Your verification code is: <strong>$verification_code</strong>";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@coach.com" . "\r\n";

    // Send email
    if (mail($email, $subject, $message, $headers)) {
        echo "<script>alert('Verification code sent to your email.'); window.location.href='emailverify.php';</script>";
    } else {
        echo "<script>alert('Failed to send verification code.'); window.location.href='emailverify.php';</script>";
    }

} elseif (isset($_POST['verify'])) {
    // Check if the entered code matches the generated one
    $entered_code = $_POST['entered_code'];
    if ($entered_code == $_SESSION['email_verification_code']) {
        // Update the email verification status in the database
        $update = $conn->prepare("UPDATE users SET email_verification = 'Active' WHERE username = ?");
        $update->bind_param("s", $username);
        $update->execute();
        $update->close();

        // Clear the session variable for the verification code
        unset($_SESSION['email_verification_code']);
        echo "<script>alert('Email verified successfully!'); window.location.href='emailverify.php';</script>";
    } else {
        echo "<script>alert('Invalid verification code.'); window.location.href='emailverify.php';</script>";
    }
}

$conn->close();
?>
