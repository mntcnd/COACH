<?php
session_start();

// Load SendGrid and environment variables
require __DIR__ . '/../vendor/autoload.php';
use SendGrid\Mail\Mail;

// Load database connection
require '../connection/db_connection.php';

// Load environment variables
try {
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } else {
        throw new \Exception(".env file not found");
    }
} catch (\Exception $e) {
    error_log("Configuration Error: " . $e->getMessage());
    die("Configuration error. Please contact administrator.");
}

// Check if a user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); 
    exit();
}

$updated = false;
$error = '';
$user_data = null;
$imageUploaded = false;
$otpSent = false;

// Determine the username of the profile to be edited from the URL
$username_to_edit = $_GET['username'] ?? '';

if (empty($username_to_edit)) {
    die("No username provided to edit.");
}

// Security Check: Ensure the logged-in user can only edit their own profile
if ($username_to_edit !== $_SESSION['username'] && $_SESSION['user_type'] !== 'Super Admin') {
    die("You are not authorized to edit this profile.");
}

// Generate OTP
function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Send OTP Email
function sendOTPEmail($email, $username, $otp) {
    try {
        if (!isset($_ENV['SENDGRID_API_KEY']) || empty($_ENV['SENDGRID_API_KEY'])) {
            throw new Exception("SendGrid API key not set.");
        }
        
        $sender_email = $_ENV['FROM_EMAIL'] ?? 'noreply@coach-hub.online';
        
        $email_content = new Mail();
        $email_content->setFrom($sender_email, 'BPSU - COACH');
        $email_content->setSubject("Password Change Verification Code");
        $email_content->addTo($email, $username);

        $html_body = "
        <html>
        <head>
          <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color:rgb(241, 223, 252); }
            .header { background-color: #562b63; padding: 15px; color: white; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .otp-box { background-color: #fff; border: 2px solid #562b63; padding: 20px; margin: 15px 0; border-radius: 5px; text-align: center; }
            .otp-code { font-size: 32px; font-weight: bold; color: #562b63; letter-spacing: 5px; }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
            .warning { background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 15px 0; border-radius: 5px; color: #856404; }
          </style>
        </head>
        <body>
          <div class='container'>
            <div class='header'>
              <h2>Password Change Verification</h2>
            </div>
            <div class='content'>
              <p>Dear <b>$username</b>,</p>
              <p>You have requested to change your password. Please use the following verification code:</p>
              
              <div class='otp-box'>
                <div class='otp-code'>$otp</div>
              </div>
              
              <div class='warning'>
                <p><strong>⚠️ IMPORTANT:</strong> This code will expire in 10 minutes. If you did not request this change, please ignore this email and ensure your account is secure.</p>
              </div>
              
              <p>Enter this code on the profile page to complete your password change.</p>
            </div>
            <div class='footer'>
              <p>&copy; " . date("Y") . " COACH. All rights reserved.</p>
            </div>
          </div>
        </body>
        </html>
        ";
        
        $email_content->addContent("text/html", $html_body);

        $sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
        $response = $sendgrid->send($email_content);

        if ($response->statusCode() < 200 || $response->statusCode() >= 300) {
            throw new Exception("Email failed to send. Status: " . $response->statusCode());
        }
        
        return true;

    } catch (\Exception $e) {
        error_log("SendGrid Error: " . $e->getMessage());
        return false;
    }
}

// Handle form submission for profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_SESSION['username'];

    // --- Handle OTP Request ---
    if (isset($_POST['request_otp'])) {
        $new_password = $_POST['new_password'] ?? '';
        
        // Note: Password validation (length, complexity) is handled by JavaScript on the client side,
        // but a basic check is good practice here. However, to keep adaptation minimal, we rely on client-side alerts.
        // The check for empty password remains crucial.
        if (empty($new_password)) {
            $error = "Please enter a new password.";
        } else {
            // Generate OTP
            $otp = generateOTP();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in session
            $_SESSION['password_otp'] = $otp;
            $_SESSION['password_otp_expiry'] = $otp_expiry;
            $_SESSION['new_password_temp'] = $new_password;
            
            // Get user email
            $stmt = $conn->prepare("SELECT email, username FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user && sendOTPEmail($user['email'], $user['username'], $otp)) {
                $otpSent = true;
            } else {
                $error = "Failed to send verification code. Please try again.";
            }
        }
    }
    
    // --- Handle OTP Verification and Password Update ---
    if (isset($_POST['verify_otp'])) {
        $entered_otp = $_POST['otp_code'] ?? '';
        
        if (empty($entered_otp)) {
            $error = "Please enter the verification code.";
        } elseif (!isset($_SESSION['password_otp'])) {
            $error = "No verification code found. Please request a new one.";
        } elseif (strtotime($_SESSION['password_otp_expiry']) < time()) {
            $error = "Verification code has expired. Please request a new one.";
            unset($_SESSION['password_otp'], $_SESSION['password_otp_expiry'], $_SESSION['new_password_temp']);
        } elseif ($entered_otp !== $_SESSION['password_otp']) {
            $error = "Invalid verification code. Please try again.";
        } else {
            // OTP is valid, update password
            $new_password = $_SESSION['new_password_temp'];
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt->bind_param("ss", $hashed_password, $username);
            
            if ($stmt->execute()) {
                $updated = true;
                // Clear OTP session data
                unset($_SESSION['password_otp'], $_SESSION['password_otp_expiry'], $_SESSION['new_password_temp']);
            } else {
                $error = "Error updating password: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // --- Handle Profile Text Information Update ---
    if (isset($_POST['update_profile'])) {
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';

        if (empty($first_name) || empty($last_name)) {
            $error = "First name and last name are required.";
        } else {
            $sql = "UPDATE users SET first_name = ?, last_name = ? WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $first_name, $last_name, $username);

            if ($stmt->execute()) {
                $updated = true;
                $_SESSION['user_full_name'] = $first_name . ' ' . $last_name;
            } else {
                $error = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // --- Handle Profile Image Upload ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filesize = $_FILES['profile_image']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $error = "Invalid image format. Please use JPG, JPEG, PNG, or GIF.";
        } elseif ($filesize > 5000000) {
            $error = "File size exceeds the 5MB limit.";
        } else {
            $new_filename = $upload_dir . 'profile_' . uniqid() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $new_filename)) {
                $sql = "UPDATE users SET icon = ? WHERE username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $new_filename, $username);
                
                if ($stmt->execute()) {
                    $imageUploaded = true;
                    $_SESSION['user_icon'] = $new_filename;
                    header("Location: edit_profile.php?username=" . urlencode($username) . "&upload_success=1");
                    exit();
                } else {
                    $error = "Error updating profile image in the database.";
                }
                $stmt->close();
            } else {
                $error = "Failed to upload the image. Check folder permissions.";
            }
        }
    }
}

// Fetch the latest user data to display
$sql = "SELECT user_id, username, first_name, last_name, email, password, icon, user_type FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username_to_edit);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

if (!$user_data) {
    die("User not found.");
}
$stmt->close();

$user_data['icon'] = !empty($user_data['icon']) ? $user_data['icon'] : '../uploads/img/default_pfp.png';
$user_data['full_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];

// Update session variables for the nav bar
$_SESSION['user_full_name'] = $user_data['full_name'];
$_SESSION['user_icon'] = $user_data['icon'];
$_SESSION['user_type'] = $user_data['user_type'];

if(isset($_GET['upload_success'])) {
    $imageUploaded = true;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css"/>
  <link rel="stylesheet" href="../superadmin/css/profile.css" />
  <link rel="stylesheet" href="css/navigation.css"/>
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Edit Profile</title>
  <style>
    .otp-section {
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        padding: 20px;
        border-radius: 5px;
        margin: 20px 0;
    }
    .otp-input {
        width: 100%;
        padding: 10px;
        font-size: 24px;
        letter-spacing: 10px;
        text-align: center;
        border: 2px solid #562b63;
        border-radius: 5px;
        margin: 10px 0;
    }
    .info-message {
        background-color: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
        padding: 12px;
        border-radius: 5px;
        margin: 10px 0;
    }
    .password-strength {
        font-size: 12px;
        margin-top: 5px;
    }
    /* START: ADDED PASSWORD POPUP STYLES FROM SIGNUP_MENTEE */
    .password-popup {
        display: none;
        position: absolute;
        background-color: #ffffff;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        z-index: 10;
        margin-top: 5px;
        min-width: 250px;
        font-size: 14px;
        line-height: 1.4;
    }
    .password-popup ul {
        list-style: none;
        padding: 0;
        margin: 5px 0 0 0;
    }
    .password-popup li {
        padding-left: 20px;
        position: relative;
    }
    .password-popup li::before {
        content: "•";
        position: absolute;
        left: 0;
        font-weight: bold;
    }
    .password-popup li.valid::before {
        content: "✓";
        color: #28a745; /* Green */
    }
    .password-popup li.invalid::before {
        content: "✕";
        color: #dc3545; /* Red */
    }
    .password-container {
        position: relative;
    }
    /* END: ADDED PASSWORD POPUP STYLES FROM SIGNUP_MENTEE */
  </style>
</head>
<body>
<nav>
  <div class="nav-top">
    <div class="logo">
      <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
      <div class="logo-name">COACH</div>
    </div>

    <div class="admin-profile">
      <img src="<?php echo htmlspecialchars($_SESSION['user_icon']); ?>" alt="Profile Picture" />
      <div class="admin-text">
        <span class="admin-name"><?php echo htmlspecialchars($_SESSION['user_full_name']); ?></span>
        <span class="admin-role"><?php echo htmlspecialchars($_SESSION['user_type']); ?></span>
      </div>
      <a href="edit_profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link active" title="Edit Profile">
        <ion-icon name="create-outline"></ion-icon>
      </a>
    </div>
  </div>

  <div class="menu-items">
    <ul class="navLinks">
        <li class="navList">
            <a href="dashboard.php"> <ion-icon name="home-outline"></ion-icon>
                <span class="links">Home</span>
            </a>
        </li>
        <li class="navList">
            <a href="courses.php"> <ion-icon name="book-outline"></ion-icon>
                <span class="links">Courses</span>
            </a>
        </li>
        <li class="navList">
            <a href="manage_mentees.php"> <ion-icon name="person-outline"></ion-icon>
                <span class="links">Mentees</span>
            </a>
        </li>
         <li class="navList">
            <a href="manage_mentors.php"> <ion-icon name="people-outline"></ion-icon>
                <span class="links">Mentors</span>
            </a>
        </li>
         <li class="navList">
            <a href="manage_session.php"> <ion-icon name="calendar-outline"></ion-icon>
                <span class="links">Sessions</span>
            </a>
        </li>
        <li class="navList"> <a href="feedbacks.php"> <ion-icon name="star-outline"></ion-icon>
                <span class="links">Feedback</span>
            </a>
        </li>
        <li class="navList">
            <a href="channels.php"> <ion-icon name="chatbubbles-outline"></ion-icon>
                <span class="links">Channels</span>
            </a>
        </li>
         <li class="navList">
            <a href="activities.php"> <ion-icon name="clipboard"></ion-icon>
                <span class="links">Activities</span>
            </a>
        </li>
         <li class="navList">
            <a href="resource.php"> <ion-icon name="library-outline"></ion-icon>
                <span class="links">Resource Library</span>
            </a>
        </li>
        <li class="navList">
            <a href="reports.php"><ion-icon name="folder-outline"></ion-icon>
                <span class="links">Reported Posts</span>
            </a>
        </li>
        <li class="navList">
            <a href="banned-users.php"><ion-icon name="person-remove-outline"></ion-icon>
                <span class="links">Banned Users</span>
            </a>
        </li>
    </ul>
    <ul class="bottom-link">
      <li class="logout-link">
        <a href="#" onclick="confirmLogout(event)">
          <ion-icon name="log-out-outline"></ion-icon>
          <span class="links">Logout</span>
        </a>
      </li>
    </ul>
  </div>
</nav>

<section class="dashboard">
    <div class="top">
      <ion-icon class="navToggle" name="menu-outline"></ion-icon>
      <img src="../uploads/img/logo.png" alt="Logo"> 
    </div>

<div class="main-content">
    <h1 class="page-title">Profile Settings</h1>
    
    <div class="profile-container">
        <div class="profile-image-section">
            <div class="profile-img-container">
                <img src="<?= htmlspecialchars($user_data['icon']) ?>" class="profile-img" alt="Profile Picture" id="profileImage">
                <div class="edit-icon" onclick="document.getElementById('profileImageUpload').click()">
                    <ion-icon name="camera"></ion-icon>
                </div>
            </div>
            
            <form id="imageUploadForm" method="post" action="edit_profile.php?username=<?= urlencode($user_data['username']) ?>" enctype="multipart/form-data">
                <input type="file" name="profile_image" id="profileImageUpload" class="hidden-file-input" accept="image/*" onchange="submitImageForm()">
            </form>
            
            <?php if ($imageUploaded): ?>
                <div class="message">Profile image updated successfully!</div>
            <?php endif; ?>
        </div>

        <div class="profile-info">
            <?php if ($updated): ?>
                <div class="message">Profile updated successfully!</div>
            <?php elseif ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($otpSent): ?>
                <div class="info-message">
                    <strong>✓ Verification code sent!</strong> Check your email (<?= htmlspecialchars($user_data['email']) ?>) for the 6-digit code.
                </div>
            <?php endif; ?>

            <form method="post" action="edit_profile.php?username=<?= urlencode($user_data['username']) ?>" id="profileForm">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" value="<?= htmlspecialchars($user_data['username']) ?>" class="disabled-input" readonly disabled>
                </div>

                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($user_data['first_name']) ?>" class="disabled-input" readonly>
                </div>

                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($user_data['last_name']) ?>" class="disabled-input" readonly>
                </div>

                <input type="hidden" name="update_profile" value="1">
                <button type="button" id="editButton" class="action-btn" onclick="toggleEditMode()">Edit Profile</button>
                <button type="submit" id="updateButton" class="action-btn" style="display: none;">Update Profile</button>
            </form>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
                <h3>Change Password</h3>
                
                <?php if (!isset($_SESSION['password_otp']) || strtotime($_SESSION['password_otp_expiry']) < time()): ?>
                    <form method="post" action="edit_profile.php?username=<?= urlencode($user_data['username']) ?>" id="passwordForm">
                        <div class="form-group">
                            <label>New Password:</label>
                            <div class="password-container">
                                <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required minlength="8">
                                <span class="toggle-password" onclick="togglePasswordVisibility('new_password')">
                                    <ion-icon name="eye-outline"></ion-icon>
                                </span>
                            </div>
                            <div id="password-popup" class="password-popup">
                                <p>Password must:</p>
                                <ul>
                                    <li id="length-check">Be at least 8 characters long</li>
                                    <li id="uppercase-check">Include at least one uppercase letter</li>
                                    <li id="lowercase-check">Include at least one lowercase letter</li>
                                    <li id="number-check">Include at least one number</li>
                                    <li id="special-check">Include at least one special character (!@#$%^&*)</li>
                                </ul>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password:</label>
                            <div class="password-container">
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required minlength="8">
                                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                    <ion-icon name="eye-outline"></ion-icon>
                                </span>
                            </div>
                        </div>

                        <input type="hidden" name="request_otp" value="1">
                        <button type="submit" class="action-btn" id="requestOtpBtn">Send Verification Code</button>
                    </form>
                <?php else: ?>
                    <div class="otp-section">
                        <p><strong>Verification Required</strong></p>
                        <p>Enter the 6-digit code sent to: <strong><?= htmlspecialchars($user_data['email']) ?></strong></p>
                        
                        <form method="post" action="edit_profile.php?username=<?= urlencode($user_data['username']) ?>">
                            <input type="text" name="otp_code" class="otp-input" placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus>
                            <input type="hidden" name="verify_otp" value="1">
                            <button type="submit" class="action-btn">Verify & Update Password</button>
                        </form>
                        
                        <p style="margin-top: 15px; font-size: 14px;">
                            Code expires at: <strong><?= date('h:i A', strtotime($_SESSION['password_otp_expiry'])) ?></strong>
                        </p>
                        
                        <form method="post" action="edit_profile.php?username=<?= urlencode($user_data['username']) ?>" style="margin-top: 10px;">
                            <button type="button" class="action-btn" style="background-color: #6c757d;" onclick="window.location.reload()">Cancel</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</section>

<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
<script src="js/navigation.js"></script>
<script>
// Navigation Toggle
const navBar = document.querySelector("nav");
const navToggle = document.querySelector(".navToggle");
if (navToggle) {
    navToggle.addEventListener('click', () => {
        navBar.classList.toggle('close');
    });
}

function toggleEditMode() {
    const editButton = document.getElementById('editButton');
    const updateButton = document.getElementById('updateButton');
    const inputs = document.querySelectorAll('#profileForm .disabled-input:not([disabled])');
    
    inputs.forEach(input => {
        input.readOnly = false;
        input.classList.remove('disabled-input');
    });
    
    editButton.style.display = 'none';
    updateButton.style.display = 'inline-block';
}

function togglePasswordVisibility(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const toggleIcon = passwordField.parentElement.querySelector('.toggle-password ion-icon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.setAttribute('name', 'eye-off-outline');
    } else {
        passwordField.type = 'password';
        toggleIcon.setAttribute('name', 'eye-outline');
    }
}

// START: ADAPTED PASSWORD VALIDATION LOGIC FROM SIGNUP_MENTEE.PHP

// DOM elements for password validation
const passwordInput = document.getElementById('new_password');
const confirmPasswordInput = document.getElementById('confirm_password');
const passwordPopup = document.getElementById('password-popup');
const passwordStrength = document.getElementById('passwordStrength');

// Password requirement checkers
const lengthCheck = document.getElementById('length-check');
const uppercaseCheck = document.getElementById('uppercase-check');
const lowercaseCheck = document.getElementById('lowercase-check');
const numberCheck = document.getElementById('number-check');
const specialCheck = document.getElementById('special-check');
const passwordForm = document.getElementById('passwordForm');


function updatePasswordStrengthDisplay(password) {
    if (!passwordStrength) return;

    let strength = 0;
    // Check 1: Length
    if (password.length >= 8) strength++;
    // Check 2: Lowercase
    if (password.match(/[a-z]/)) strength++;
    // Check 3: Uppercase
    if (password.match(/[A-Z]/)) strength++;
    // Check 4: Number
    if (password.match(/[0-9]/)) strength++;
    // Check 5: Special Character
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const strengthColor = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#17a2b8'];
    
    if (password.length === 0) {
        passwordStrength.textContent = '';
    } else {
        passwordStrength.textContent = 'Password Strength: ' + strengthText[strength];
        passwordStrength.style.color = strengthColor[strength];
    }
}

function validatePassword() {
    if (!passwordInput || !passwordPopup) return true;

    const password = passwordInput.value;
    let isValid = true;

    // Check 1: Length
    const hasValidLength = password.length >= 8;
    if (lengthCheck) lengthCheck.className = hasValidLength ? 'valid' : 'invalid';
    isValid = isValid && hasValidLength;

    // Check 2: Uppercase
    const hasUppercase = /[A-Z]/.test(password);
    if (uppercaseCheck) uppercaseCheck.className = hasUppercase ? 'valid' : 'invalid';
    isValid = isValid && hasUppercase;

    // Check 3: Lowercase
    const hasLowercase = /[a-z]/.test(password);
    if (lowercaseCheck) lowercaseCheck.className = hasLowercase ? 'valid' : 'invalid';
    isValid = isValid && hasLowercase;

    // Check 4: Number
    const hasNumber = /[0-9]/.test(password);
    if (numberCheck) numberCheck.className = hasNumber ? 'valid' : 'invalid';
    isValid = isValid && hasNumber;

    // Check 5: Special Character
    const hasSpecial = /[!@#$%^&*]/.test(password);
    if (specialCheck) specialCheck.className = hasSpecial ? 'valid' : 'invalid';
    isValid = isValid && hasSpecial;

    // Update the simplified password strength indicator
    updatePasswordStrengthDisplay(password);

    return isValid;
}

function showPasswordPopup() {
    if (passwordPopup) passwordPopup.style.display = 'block';
}

function hidePasswordPopup() {
    // Hide if all validation is passed OR if the field is empty
    if (passwordPopup && (validatePassword() || passwordInput.value.length === 0)) {
         passwordPopup.style.display = 'none';
    }
}

// Event Listeners for real-time feedback and visibility
passwordInput?.addEventListener('focus', showPasswordPopup);
passwordInput?.addEventListener('keyup', validatePassword);
passwordInput?.addEventListener('blur', hidePasswordPopup);


// Full form submission validation (Replaces old simple validation)
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    // This listener only runs when the "Send Verification Code" button is clicked
    const newPassword = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    if (!validatePassword()) {
        e.preventDefault();
        alert('Please ensure your new password meets all the requirements (at least 8 characters, with uppercase, lowercase, number, and special character).');
        passwordInput.focus();
        showPasswordPopup();
        return false;
    }

    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirmPasswordInput.focus();
        return false;
    }
    // If all checks pass, the form submits for OTP request
});

// END: ADAPTED PASSWORD VALIDATION LOGIC FROM SIGNUP_MENTEE.PHP


function submitImageForm() {
    const fileInput = document.getElementById('profileImageUpload');
    const imagePreview = document.getElementById('profileImage');
    
    if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            document.getElementById('imageUploadForm').submit();
        }
        
        reader.readAsDataURL(fileInput.files[0]);
    }
}

// Clean URL from success messages
document.addEventListener('DOMContentLoaded', function() {
    if (history.replaceState) {
        const url = new URL(window.location);
        if (url.searchParams.has('upload_success')) {
            url.searchParams.delete('upload_success');
            history.replaceState(null, '', url.toString());
        }
    }
});
</script>
<div id="logoutDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out?</p>
        <div class="dialog-buttons">
            <button id="cancelLogout" type="button">Cancel</button>
            <button id="confirmLogoutBtn" type="button">Logout</button>
        </div>
    </div>
</div>
</body>
</html>