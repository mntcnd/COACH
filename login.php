<?php
session_start();

require 'connection/db_connection.php';

// Handle Login
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action'])) {
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password, user_type, first_name, status, password_changed FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            
            // Check if user is a Mentor and verify their status
            if ($user['user_type'] === 'Mentor') {
                if ($user['status'] === 'Under Review') {
                    echo "<script>alert('Your account is currently under review. Please wait for admin approval.'); window.location.href='login.php';</script>";
                    exit();
                } elseif ($user['status'] === 'Rejected') {
                    echo "<script>alert('Your account application has been rejected. Please contact the administrator for more information.'); window.location.href='login.php';</script>";
                    exit();
                } elseif ($user['status'] !== 'Approved') {
                    echo "<script>alert('Your account is not approved. Please contact the administrator.'); window.location.href='login.php';</script>";
                    exit();
                }
            }
            
            // Check if Admin user needs to change password on first login
            if ($user['user_type'] === 'Admin' && $user['password_changed'] == 0) {
                $_SESSION['temp_user_id'] = $user['user_id'];
                $_SESSION['temp_username'] = $user['username'];
                $_SESSION['temp_first_name'] = $user['first_name'];
                $_SESSION['temp_user_type'] = $user['user_type'];
                echo "<script>
                    window.location.href='login.php?force_change=1';
                </script>";
                exit();
            }
            
            // If all checks pass, create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['login_success'] = true;

            switch ($user['user_type']) {
                case 'Mentee':
                    header("Location: mentee/home.php");
                    break;
                case 'Mentor':
                    header("Location: mentor/dashboard.php");
                    break;
                case 'Admin':
                    header("Location: admin/dashboard.php");
                    break;
                case 'Super Admin':
                    header("Location: superadmin/dashboard.php");
                    break;
                default:
                    header("Location: login.php"); 
                    break;
            }
            exit();

        } else {
            echo "<script>alert('Incorrect password.'); window.location.href='login.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Username not found.'); window.location.href='login.php';</script>";
        exit();
    }
    
    $stmt->close();
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'change_first_password') {
        $new_password = trim($_POST['new_password']);
        $user_id = $_SESSION['temp_user_id'];
        
        // Enhanced password validation
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
            exit();
        }
        
        if (!preg_match('/[A-Z]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
            exit();
        }
        
        if (!preg_match('/[a-z]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter.']);
            exit();
        }
        
        if (!preg_match('/[0-9]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
            exit();
        }
        
        if (!preg_match('/[!@#$%^&*]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character (!@#$%^&*).']);
            exit();
        }
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and set password_changed = 1
        $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Set session variables
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['username'] = $_SESSION['temp_username'];
            $_SESSION['first_name'] = $_SESSION['temp_first_name'];
            $_SESSION['user_type'] = $_SESSION['temp_user_type'];
            $_SESSION['login_success'] = true;
            
            // Clear temp session variables
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_username']);
            unset($_SESSION['temp_first_name']);
            unset($_SESSION['temp_user_type']);
            
            echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'send_otp') {
        $username = trim($_POST['username']);
        
        // Check if username exists and get phone number
        $stmt = $conn->prepare("SELECT user_id, contact_number FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $contact_number = $user['contact_number'];
            
            // Generate 6-digit OTP
            $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store OTP in session with timestamp
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_user_id'] = $user['user_id'];
            $_SESSION['reset_username'] = $username;
            $_SESSION['otp_timestamp'] = time();
            
            // Send OTP via Semaphore
            sendOTPSMS($contact_number, $otp);
            
            // Always assume successful since you're receiving the SMS
            echo json_encode(['success' => true, 'message' => 'OTP sent to your registered phone number.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Username not found.']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'verify_otp') {
        $otp = trim($_POST['otp']);
        
        // Check if OTP session data exists
        if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['otp_timestamp'])) {
            echo json_encode(['success' => false, 'message' => 'OTP session expired. Please request a new OTP.']);
            exit();
        }
        
        // Check if OTP is not expired (5 minutes = 300 seconds)
        $timeElapsed = time() - $_SESSION['otp_timestamp'];
        if ($timeElapsed > 300) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new OTP.']);
            exit();
        }
        
        // Check if OTP matches
        $sessionOtp = (string)$_SESSION['reset_otp'];
        $enteredOtp = (string)$otp;
        
        if ($sessionOtp === $enteredOtp) {
            $_SESSION['otp_verified'] = true;
            echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please check and try again.']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'reset_password') {
        if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
            echo json_encode(['success' => false, 'message' => 'OTP not verified.']);
            exit();
        }
        
        $new_password = trim($_POST['new_password']);
        $user_id = $_SESSION['reset_user_id'];
        
        // Enhanced password validation
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
            exit();
        }
        
        if (!preg_match('/[A-Z]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
            exit();
        }
        
        if (!preg_match('/[a-z]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter.']);
            exit();
        }
        
        if (!preg_match('/[0-9]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
            exit();
        }
        
        if (!preg_match('/[!@#$%^&*]/', $new_password)) {
            echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character (!@#$%^&*).']);
            exit();
        }
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and mark as changed
        $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Clear reset session variables
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_username']);
            unset($_SESSION['otp_timestamp']);
            unset($_SESSION['otp_verified']);
            
            echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password.']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'resend_otp') {
        // Check if 60 seconds have passed since last OTP
        if (isset($_SESSION['otp_timestamp']) && (time() - $_SESSION['otp_timestamp']) < 60) {
            $remaining = 60 - (time() - $_SESSION['otp_timestamp']);
            echo json_encode(['success' => false, 'message' => "Please wait {$remaining} seconds before requesting a new OTP."]);
            exit();
        }
        
        if (isset($_SESSION['reset_username'])) {
            $username = $_SESSION['reset_username'];
            
            // Get phone number
            $stmt = $conn->prepare("SELECT contact_number FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $contact_number = $user['contact_number'];
                
                // Generate new OTP
                $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Update session
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['otp_timestamp'] = time();
                
                // Send OTP
                sendOTPSMS($contact_number, $otp);
                
                // Always assume successful
                echo json_encode(['success' => true, 'message' => 'New OTP sent successfully.']);
            }
        }
        exit();
    }
}

function sendOTPSMS($contact_number, $otp) {
    $apikey = '55628b35a664abb55e0f93b86b448f35';
    
    $ch = curl_init();
    $parameters = array(
        'apikey' => $apikey,
        'number' => $contact_number,
        'message' => "Your COACH password reset OTP is: {$otp}. This code will expire in 5 minutes.",
        'sendername' => 'BPSUCOACH'
    );
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $output = curl_exec($ch);
    curl_close($ch);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link rel="stylesheet" href="css/login.css">
    <link rel="icon" href="uploads/img/coachicon.svg" type="image/svg+xml">
    <style>
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        
        .modal h3 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .modal input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        
        .modal button {
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            min-width: 100px;
        }
        
        .modal button:hover {
            background: #ae84baff;
        }
        
        .modal .cancel-btn {
            background: #6c757d;
        }
        
        .modal .cancel-btn:hover {
            background: #545b62;
        }
        
        .resend-btn {
            background: #ae84baff !important;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            min-width: 100px;
        }
        
        .resend-btn:hover {
            background: #562b63 !important;
        }
        
        .countdown {
            color: #666;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .warning-icon {
            font-size: 50px;
            color: #ffc107;
            margin-bottom: 10px;
        }
        
        /* Password validation styles */
        .password-field-container {
            position: relative;
            width: 100%;
        }
        
        .password-popup {
            display: none;
            position: absolute;
            left: 0;
            top: 100%;
            margin-top: 5px;
            background: rgba(6, 6, 6, 0.9);
            border-radius: 5px;
            padding: 15px;
            width: 100%;
            color: #fff;
            z-index: 100;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            text-align: left;
        }
        
        .password-popup p {
            margin-top: 0;
            color: #fff;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .password-popup ul {
            margin: 0;
            padding-left: 20px;
            list-style: none;
        }
        
        .password-popup li {
            margin-bottom: 5px;
            transition: color 0.3s;
        }
        
        .password-popup li::before {
            content: "✗ ";
            margin-right: 5px;
        }
        
        .password-popup li.valid {
            color: #4CAF50;
        }
        
        .password-popup li.valid::before {
            content: "✓ ";
        }
        
        .password-popup li.invalid {
            color: #f44336;
        }
        
        .valid-input {
            border: 1px solid #4CAF50 !important;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.5) !important;
        }
        
        .invalid-input {
            border: 1px solid #f44336 !important;
            box-shadow: 0 0 5px rgba(244, 67, 54, 0.5) !important;
        }
    </style>
</head>
<body>
    <div class="page-bg"></div>

    <div class="login-container">
        
        <div class="login-box">
            <div class="logo">
                <img src="uploads/img/Logo3D.png" alt="Logo" />
            </div>
            <div class="login-header">
                <h2>LOGIN</h2>
                <p>Please sign in to continue</p>
            </div>
            
            <form action="" method="post">
                <input type="text" name="username" placeholder="Username or Email" required>
                <input type="password" name="password" placeholder="Password" required>
                
                <div class="options">
                    <a href="#" class="forgot-link" onclick="openForgotModal()">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="register-section">
                <span>Don't have an account? Join as</span><br>
                <a href="signup_mentee.php">Mentee</a> | <a href="signup_mentor.php">Mentor</a>
            </div>
        </div>

        <div class="info-box">
            <img src="uploads/progress.png" alt="Project Illustration" class="illustration">
           <div class="coach-welcome">
                <h3 class="coach-header">Welcome to COACH</h3>
                <p class="coach-subtext">Your hub for mentorship, learning, and staying connected.</p>
            </div>
        </div>
    </div>

    <!-- Change Password Modal (First Login) -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="warning-icon">⚠️</div>
            <h3>Change Your Password</h3>
            <p>For security reasons, you must change your password before accessing the system.</p>
            
            <div class="password-field-container">
                <input type="password" id="firstNewPassword" placeholder="New Password" required>
                <div id="firstPasswordPopup" class="password-popup">
                    <p>Password Requirements:</p>
                    <ul>
                        <li id="firstLengthCheck" class="invalid">At least 8 characters</li>
                        <li id="firstUppercaseCheck" class="invalid">One uppercase letter (A-Z)</li>
                        <li id="firstLowercaseCheck" class="invalid">One lowercase letter (a-z)</li>
                        <li id="firstNumberCheck" class="invalid">One number (0-9)</li>
                        <li id="firstSpecialCheck" class="invalid">One special character (!@#$%^&*)</li>
                    </ul>
                </div>
            </div>
            
            <input type="password" id="firstConfirmPassword" placeholder="Confirm New Password" required>
            <div id="changePasswordMessage" class="message" style="display: none;"></div>
            <button onclick="changeFirstPassword()" style="background-color: #562b63;">Change Password</button>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal">
        <div class="modal-content" style="margin-top: 170px;">
            <!-- Step 1: Enter Username -->
            <div id="step1" style="display: block;">
                <div class="warning-icon">🔐</div>
                <h3>Forgot Password</h3>
                <p>Enter your username to receive an OTP.</p>
                <input type="text" id="resetUsername" placeholder="Username" required>
                <div id="step1Message" class="message" style="display: none;"></div>
                <button onclick="sendOTP()" style="background-color: #562b63;">Send OTP</button>
                <button class="cancel-btn" onclick="closeForgotModal()">Cancel</button>
            </div>
            
            <!-- Step 2: Enter OTP -->
            <div id="step2" style="display: none;">
                <div class="warning-icon">🔢</div>
                <h3>Enter OTP</h3>
                <p>We've sent a 6-digit code to your registered phone number</p>
                <input type="text" id="otpCode" placeholder="Enter 6-digit OTP" maxlength="6" required>
                <div id="step2Message" class="message" style="display: none;"></div>
                <div class="countdown" id="countdown"></div>
                <button onclick="verifyOTP()" style="background-color: #562b63;">Verify OTP</button>
                <button class="resend-btn" id="resendBtn" onclick="resendOTP()" style="background-color: #562b63;">Resend OTP</button>
                <button class="cancel-btn" onclick="closeForgotModal()">Cancel</button>
            </div>
            
            <!-- Step 3: Reset Password -->
            <div id="step3" style="display: none;">
                <div class="warning-icon">🔑</div>
                <h3>Reset Password</h3>
                <p>Enter your new password</p>
                
                <div class="password-field-container">
                    <input type="password" id="newPassword" placeholder="New Password" required>
                    <div id="resetPasswordPopup" class="password-popup">
                        <p>Password Requirements:</p>
                        <ul>
                            <li id="resetLengthCheck" class="invalid">At least 8 characters</li>
                            <li id="resetUppercaseCheck" class="invalid">One uppercase letter (A-Z)</li>
                            <li id="resetLowercaseCheck" class="invalid">One lowercase letter (a-z)</li>
                            <li id="resetNumberCheck" class="invalid">One number (0-9)</li>
                            <li id="resetSpecialCheck" class="invalid">One special character (!@#$%^&*)</li>
                        </ul>
                    </div>
                </div>
                
                <input type="password" id="confirmPassword" placeholder="Confirm Password" required>
                <div id="step3Message" class="message" style="display: none;"></div>
                <button onclick="resetPassword()" style="background-color: #562b63;">Reset Password</button>
                <button class="cancel-btn" onclick="closeForgotModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let countdownInterval;
        
        // Password validation for First Login Modal
        const firstNewPasswordInput = document.getElementById('firstNewPassword');
        const firstPasswordPopup = document.getElementById('firstPasswordPopup');
        const firstLengthCheck = document.getElementById('firstLengthCheck');
        const firstUppercaseCheck = document.getElementById('firstUppercaseCheck');
        const firstLowercaseCheck = document.getElementById('firstLowercaseCheck');
        const firstNumberCheck = document.getElementById('firstNumberCheck');
        const firstSpecialCheck = document.getElementById('firstSpecialCheck');
        const firstConfirmPasswordInput = document.getElementById('firstConfirmPassword');
        
        // Password validation for Reset Password Modal
        const newPasswordInput = document.getElementById('newPassword');
        const resetPasswordPopup = document.getElementById('resetPasswordPopup');
        const resetLengthCheck = document.getElementById('resetLengthCheck');
        const resetUppercaseCheck = document.getElementById('resetUppercaseCheck');
        const resetLowercaseCheck = document.getElementById('resetLowercaseCheck');
        const resetNumberCheck = document.getElementById('resetNumberCheck');
        const resetSpecialCheck = document.getElementById('resetSpecialCheck');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        
        // Validation functions for First Login
        function validateFirstPassword() {
            const password = firstNewPasswordInput.value;
            let isValid = true;
            
            const hasValidLength = password.length >= 8;
            firstLengthCheck.className = hasValidLength ? 'valid' : 'invalid';
            isValid = isValid && hasValidLength;
            
            const hasUppercase = /[A-Z]/.test(password);
            firstUppercaseCheck.className = hasUppercase ? 'valid' : 'invalid';
            isValid = isValid && hasUppercase;
            
            const hasLowercase = /[a-z]/.test(password);
            firstLowercaseCheck.className = hasLowercase ? 'valid' : 'invalid';
            isValid = isValid && hasLowercase;
            
            const hasNumber = /[0-9]/.test(password);
            firstNumberCheck.className = hasNumber ? 'valid' : 'invalid';
            isValid = isValid && hasNumber;
            
            const hasSpecial = /[!@#$%^&*]/.test(password);
            firstSpecialCheck.className = hasSpecial ? 'valid' : 'invalid';
            isValid = isValid && hasSpecial;
            
            // Update input styling
            if (password.length > 0) {
                firstNewPasswordInput.className = isValid ? 'valid-input' : 'invalid-input';
            } else {
                firstNewPasswordInput.className = '';
            }
            
            return isValid;
        }
        
        function validateFirstConfirmPassword() {
            const password = firstNewPasswordInput.value;
            const confirmPassword = firstConfirmPasswordInput.value;
            
            if (confirmPassword.length > 0) {
                const matches = password === confirmPassword;
                firstConfirmPasswordInput.className = matches ? 'valid-input' : 'invalid-input';
                return matches;
            }
            firstConfirmPasswordInput.className = '';
            return false;
        }
        
        // Validation functions for Reset Password
        function validateResetPassword() {
            const password = newPasswordInput.value;
            let isValid = true;
            
            const hasValidLength = password.length >= 8;
            resetLengthCheck.className = hasValidLength ? 'valid' : 'invalid';
            isValid = isValid && hasValidLength;
            
            const hasUppercase = /[A-Z]/.test(password);
            resetUppercaseCheck.className = hasUppercase ? 'valid' : 'invalid';
            isValid = isValid && hasUppercase;
            
            const hasLowercase = /[a-z]/.test(password);
            resetLowercaseCheck.className = hasLowercase ? 'valid' : 'invalid';
            isValid = isValid && hasLowercase;
            
            const hasNumber = /[0-9]/.test(password);
            resetNumberCheck.className = hasNumber ? 'valid' : 'invalid';
            isValid = isValid && hasNumber;
            
            const hasSpecial = /[!@#$%^&*]/.test(password);
            resetSpecialCheck.className = hasSpecial ? 'valid' : 'invalid';
            isValid = isValid && hasSpecial;
            
            // Update input styling
            if (password.length > 0) {
                newPasswordInput.className = isValid ? 'valid-input' : 'invalid-input';
            } else {
                newPasswordInput.className = '';
            }
            
            return isValid;
        }
        
        function validateResetConfirmPassword() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length > 0) {
                const matches = password === confirmPassword;
                confirmPasswordInput.className = matches ? 'valid-input' : 'invalid-input';
                return matches;
            }
            confirmPasswordInput.className = '';
            return false;
        }
        
        // Event listeners for First Login
        firstNewPasswordInput.addEventListener('focus', function() {
            firstPasswordPopup.style.display = 'block';
        });
        
        firstNewPasswordInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (validateFirstPassword() || firstNewPasswordInput.value.length === 0) {
                    firstPasswordPopup.style.display = 'none';
                }
            }, 200);
        });
        
        firstNewPasswordInput.addEventListener('input', validateFirstPassword);
        firstConfirmPasswordInput.addEventListener('input', validateFirstConfirmPassword);
        
        // Event listeners for Reset Password
        newPasswordInput.addEventListener('focus', function() {
            resetPasswordPopup.style.display = 'block';
        });
        
        newPasswordInput.addEventListener('blur', function() {
            setTimeout(() => {
                if (validateResetPassword() || newPasswordInput.value.length === 0) {
                    resetPasswordPopup.style.display = 'none';
                }
            }, 200);
        });
        
        newPasswordInput.addEventListener('input', validateResetPassword);
        confirmPasswordInput.addEventListener('input', validateResetConfirmPassword);
        
        // Check if force password change is required
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('force_change') === '1') {
                document.getElementById('changePasswordModal').style.display = 'block';
            }
        };
        
        function changeFirstPassword() {
            const newPassword = firstNewPasswordInput.value;
            const confirmPassword = firstConfirmPasswordInput.value;
            
            if (!validateFirstPassword()) {
                showMessage('changePasswordMessage', 'Please meet all password requirements.', true);
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('changePasswordMessage', 'Passwords do not match.', true);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_first_password');
            formData.append('new_password', newPassword);
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('changePasswordMessage', data.message, false);
                    setTimeout(() => {
                        window.location.href = 'admin/dashboard.php';
                    }, 1500);
                } else {
                    showMessage('changePasswordMessage', data.message, true);
                }
            })
            .catch(error => {
                showMessage('changePasswordMessage', 'An error occurred. Please try again.', true);
            });
        }
        
        function openForgotModal() {
            document.getElementById('forgotModal').style.display = 'block';
            resetModal();
        }
        
        function closeForgotModal() {
            document.getElementById('forgotModal').style.display = 'none';
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            resetModal();
        }
        
        function resetModal() {
            // Reset all steps
            document.getElementById('step1').style.display = 'block';
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'none';
            
            // Clear all inputs
            document.getElementById('resetUsername').value = '';
            document.getElementById('otpCode').value = '';
            newPasswordInput.value = '';
            confirmPasswordInput.value = '';
            
            // Reset input styling
            newPasswordInput.className = '';
            confirmPasswordInput.className = '';
            
            // Clear messages
            hideMessage('step1Message');
            hideMessage('step2Message');
            hideMessage('step3Message');
        }
        
        function showMessage(elementId, message, isError = false) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.className = `message ${isError ? 'error' : 'success'}`;
            element.style.display = 'block';
        }
        
        function hideMessage(elementId) {
            document.getElementById(elementId).style.display = 'none';
        }
        
        function sendOTP() {
            const username = document.getElementById('resetUsername').value.trim();
            
            if (!username) {
                showMessage('step1Message', 'Please enter your username.', true);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'send_otp');
            formData.append('username', username);
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('step1Message', data.message, false);
                    setTimeout(() => {
                        document.getElementById('step1').style.display = 'none';
                        document.getElementById('step2').style.display = 'block';
                        startCountdown();
                    }, 1500);
                } else {
                    showMessage('step1Message', data.message, true);
                }
            })
            .catch(error => {
                showMessage('step1Message', 'An error occurred. Please try again.', true);
            });
        }
        
        function verifyOTP() {
            const otp = document.getElementById('otpCode').value.trim();
            
            if (!otp || otp.length !== 6) {
                showMessage('step2Message', 'Please enter a valid 6-digit OTP.', true);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'verify_otp');
            formData.append('otp', otp);
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('step2Message', data.message, false);
                    if (countdownInterval) {
                        clearInterval(countdownInterval);
                    }
                    setTimeout(() => {
                        document.getElementById('step2').style.display = 'none';
                        document.getElementById('step3').style.display = 'block';
                    }, 1500);
                } else {
                    showMessage('step2Message', data.message, true);
                }
            })
            .catch(error => {
                showMessage('step2Message', 'An error occurred. Please try again.', true);
            });
        }
        
        function resetPassword() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (!validateResetPassword()) {
                showMessage('step3Message', 'Please meet all password requirements.', true);
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('step3Message', 'Passwords do not match.', true);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('new_password', newPassword);
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('step3Message', data.message, false);
                    setTimeout(() => {
                        closeForgotModal();
                        alert('Password reset successfully! You can now login with your new password.');
                    }, 2000);
                } else {
                    showMessage('step3Message', data.message, true);
                }
            })
            .catch(error => {
                showMessage('step3Message', 'An error occurred. Please try again.', true);
            });
        }
        
        function resendOTP() {
            const formData = new FormData();
            formData.append('action', 'resend_otp');
            
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('step2Message', data.message, false);
                    document.getElementById('resendBtn').style.display = 'none';
                    startCountdown();
                } else {
                    showMessage('step2Message', data.message, true);
                }
            })
            .catch(error => {
                showMessage('step2Message', 'An error occurred. Please try again.', true);
            });
        }
        
        function startCountdown() {
            let timeLeft = 60;
            const countdownElement = document.getElementById('countdown');
            const resendBtn = document.getElementById('resendBtn');
            
            resendBtn.style.display = 'none';
            
            countdownInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = `Resend available in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    countdownElement.textContent = '';
                    resendBtn.style.display = 'inline-block';
                }
                
                timeLeft--;
            }, 1000);
        }
        
        // Close modal when clicking outside (except for force change modal)
        window.onclick = function(event) {
            const forgotModal = document.getElementById('forgotModal');
            if (event.target === forgotModal) {
                closeForgotModal();
            }
        }
    </script>
</body>
</html>