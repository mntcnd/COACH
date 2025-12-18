<?php
session_start();

// *** FIX: Set timezone to Philippine Time (PHT) ***
date_default_timezone_set('Asia/Manila');

// ==========================================================
// --- NEW: ANTI-CACHING HEADERS (Security Block) ---
// These headers prevent the browser from caching the page, 
// forcing a server check on back button press.
// ==========================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
// ==========================================================


// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // FIX: Redirect to the correct unified login page (one directory up)
    header("Location: ../login.php");
    exit();
}

// --- FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

// SESSION CHECK
if (!isset($_SESSION['username'])) {
  // FIX: Use the correct unified login page path (one directory up)
  header("Location: ../login.php"); 
  exit();
}

// --- HANDLE AJAX REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    require '../connection/db_connection.php';
    
    if ($_POST['action'] === 'send_otp') {
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        
        // Validate phone number (10 digits)
        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
            exit();
        }
        
        // Convert to +63 format for SMS
        $fullPhone = '+63' . $phone;
        
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in session
        $_SESSION['phone_otp'] = $otp;
        $_SESSION['phone_otp_phone'] = $fullPhone;
        $_SESSION['phone_otp_expiry'] = time() + 120; // 2 minutes
        
        // Semaphore API configuration
        $apikey = '55628b35a664abb55e0f93b86b448f35';
        $sendername = 'BPSUCOACH';
        
        // SMS message
        $message = "Your COACH verification code is: $otp\n\nThis code will expire in 2 minutes. Do not share this code with anyone.";
        
        // Send SMS
        $ch = curl_init();
        $parameters = array(
            'apikey' => $apikey,
            'number' => $fullPhone,
            'message' => $message,
            'sendername' => $sendername
        );
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode(['success' => false, 'message' => 'Failed to send SMS']);
            exit();
        }
        
        if ($httpCode == 200 || $httpCode == 201) {
            echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'verify_otp') {
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
        
        // Convert to +63 format
        $fullPhone = '+63' . $phone;
        
        // Check if OTP exists and is valid
        if (!isset($_SESSION['phone_otp']) || !isset($_SESSION['phone_otp_expiry'])) {
            echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
            exit();
        }
        
        // Check if OTP expired
        if (time() > $_SESSION['phone_otp_expiry']) {
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
            exit();
        }
        
        // Verify OTP and phone number match
        if ($_SESSION['phone_otp'] !== $otp || $_SESSION['phone_otp_phone'] !== $fullPhone) {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code']);
            exit();
        }
        
        // Update phone number in database (store with +63 format)
        $username = $_SESSION['username'];
        $stmt = $conn->prepare("UPDATE users SET contact_number = ? WHERE username = ?");
        $stmt->bind_param("ss", $fullPhone, $username);
        
        if ($stmt->execute()) {
            // Clear OTP session data
            unset($_SESSION['phone_otp'], $_SESSION['phone_otp_phone'], $_SESSION['phone_otp_expiry']);
            echo json_encode(['success' => true, 'message' => 'Phone number updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update phone number']);
        }
        
        $stmt->close();
        $conn->close();
        exit();
    }
}

// --- FETCH USER DATA ---
require '../connection/db_connection.php';

$username = $_SESSION['username'];
$firstName = '';
$menteeIcon = '';
$currentPhone = '';

$sql = "SELECT first_name, icon, contact_number FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstName = $row['first_name'];
    $menteeIcon = $row['icon'];
    $currentPhone = $row['contact_number'];
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/verify-phone.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <title>Edit Phone Number</title>
    <style>
        .logout-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .logout-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .logout-content h3 {
            margin-top: 0;
            color: #562b63;
            font-family: 'Ubuntu', sans-serif; 
            font-size: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        .logout-content p {
            margin-bottom: 1.5rem;
            font-family: 'Ubuntu', sans-serif; 
            line-height: 1.4;
            font-size: 1rem;
        }

        .dialog-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .dialog-buttons button {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Ubuntu', sans-serif; 
            font-size: 1rem;
        }

        #cancelLogout {
            background-color: #f5f5f5;
            color: #333;
        }

        #cancelLogout:hover {
            background-color: #e0e0e0;
        }

        #confirmLogoutBtn {
            background: linear-gradient(to right, #5d2c69, #8a5a96);
            color: white;
        }

        #confirmLogoutBtn:hover {
            background: #5d2c69;
        }

        .otp-section {
            display: none;
            margin-top: 1rem;
        }

        .otp-section.active {
            display: block;
        }

        .timer {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .error-message {
            color: #d32f2f;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background-color: #ffebee;
            border-radius: 4px;
        }

        .success-message {
            color: #388e3c;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background-color: #e8f5e9;
            border-radius: 4px;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .phone-input-container small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<!-- Navigation -->
<section class="background" id="home">
  <nav class="navbar">
    <div class="logo">
      <img src="../uploads/img/LogoCoach.png" alt="Logo">
      <span>COACH</span>
    </div>

    <div class="nav-center">
        <ul class="nav_items" id="nav_links">
          <li><a href="home.php">Home</a></li>
          <li><a href="course.php">Courses</a></li>
          <li><a href="resource_library.php">Resource Library</a></li>
          <li><a href="activities.php">Activities</a></li>
          <li><a href="taskprogress.php">Progress</a></li>
          <li><a href="forum-chat.php">Sessions</a></li>
          <li><a href="forums.php">Forums</a></li>
        </ul>
      </div>

      <div class="nav-profile">
  <a href="#" id="profile-icon">
    <?php if (!empty($menteeIcon)): ?>
      <img src="<?php echo htmlspecialchars($menteeIcon); ?>" alt="User Icon" style="width: 35px; height: 35px; border-radius: 50%;">
    <?php else: ?>
      <ion-icon name="person-circle-outline" style="font-size: 35px;"></ion-icon>
    <?php endif; ?>
  </a>
</div>

<div class="sub-menu-wrap hide" id="profile-menu">
  <div class="sub-menu">
    <div class="user-info">
      <div class="user-icon">
        <?php if (!empty($menteeIcon)): ?>
          <img src="<?php echo htmlspecialchars($menteeIcon); ?>" alt="User Icon" style="width: 40px; height: 40px; border-radius: 50%;">
        <?php else: ?>
          <ion-icon name="person-circle-outline" style="font-size: 40px;"></ion-icon>
        <?php endif; ?>
      </div>
      <div class="user-name"><?php echo htmlspecialchars($firstName); ?></div>
    </div>
    <ul class="sub-menu-items">
      <li><a href="profile.php">Profile</a></li>
      <li><a href="taskprogress.php">Progress</a></li>
      <li><a href="#" onclick="confirmLogout()">Logout</a></li>
    </ul>
  </div>
</div>
        </nav>
</section>
 
<!-- Main Content -->
<main class="profile-container" style ="margin-top: 70px;">
    <nav class="tabs">
      <button onclick="window.location.href='profile.php'">Profile</button>
      <button onclick="window.location.href='edit-profile.php'">Edit Profile</button>
      <button onclick="window.location.href='verify-email.php'">Edit Email</button>
      <button class="active" onclick="window.location.href='verify-phone.php'">Edit Phone</button>
      <button onclick="window.location.href='edit-username.php'">Edit Username</button>
      <button onclick="window.location.href='reset-password.php'">Reset Password</button>
    </nav>

  <div class="container">
    <h2>Update Phone Number</h2>
    <p>Update your phone number and verify it with OTP to receive SMS notifications.</p>

    <div id="messageBox"></div>

    <form id="phoneUpdateForm">
      <div class="phone-input-container">
        <label for="currentPhone">Current Phone Number</label>
        <input type="text" id="currentPhone" value="<?php echo htmlspecialchars($currentPhone ?? 'No phone number set'); ?>" disabled>
      </div>

      <div class="phone-input-container">
        <label for="newPhone">New Phone Number</label>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <input type="text" value="+63" disabled style="width: 60px; background-color: #f5f5f5;">
          <input type="text" id="newPhone" name="newPhone" placeholder="9XXXXXXXXX" pattern="[0-9]{10}" maxlength="10" required style="flex: 1;">
        </div>
        <small>Enter 10-digit number starting with 9 (e.g., 9171234567)</small>
      </div>

      <button type="button" id="sendOtpBtn" onclick="sendOTP()" style="background-color: #693b69; color: white; border: 1px solid #693b69; 
        border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer;">Send OTP</button>

      <div class="otp-section" id="otpSection">
        <div class="phone-input-container">
          <label for="otpCode">Enter OTP Code</label>
          <input type="text" id="otpCode" name="otpCode" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}">
          <div class="timer" id="timer"></div>
        </div>
        <button type="button" id="verifyOtpBtn" onclick="verifyOTP()" style="background-color: #693b69; color: white; border: 1px solid #693b69; 
        border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer;">Verify & Update</button>
        <button type="button" id="resendOtpBtn" onclick="sendOTP()" style="display:none;" style="background-color: #693b69; color: white; border: 1px solid #693b69; 
        border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer;">Resend OTP</button>
      </div>
    </form>
  </div>
</main>

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

<script>
let countdownTimer;
let canResend = true;

document.addEventListener("DOMContentLoaded", function() {
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    if (profileIcon && profileMenu) {
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide");
        });
        
        document.addEventListener("click", function (e) {
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    }

    window.confirmLogout = function(e) { 
        if (e) e.preventDefault();
        if (logoutDialog) {
            logoutDialog.style.display = "flex";
        }
    }

    if (cancelLogoutBtn && logoutDialog) {
        cancelLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            logoutDialog.style.display = "none";
        });
    }

    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            // FIX: Redirect to the dedicated logout script in the parent folder (root)
            window.location.href = "../logout.php"; 
        });
    }
});

function showMessage(message, type) {
    const messageBox = document.getElementById('messageBox');
    messageBox.className = type === 'success' ? 'success-message' : 'error-message';
    messageBox.textContent = message;
    setTimeout(() => {
        messageBox.textContent = '';
        messageBox.className = '';
    }, 5000);
}

function validatePhoneNumber(phone) {
    const phoneRegex = /^9[0-9]{9}$/;
    return phoneRegex.test(phone);
}

function sendOTP() {
    const newPhone = document.getElementById('newPhone').value.trim();
    
    if (!newPhone) {
        showMessage('Please enter a phone number', 'error');
        return;
    }

    if (!validatePhoneNumber(newPhone)) {
        showMessage('Please enter a valid 10-digit number starting with 9', 'error');
        return;
    }

    if (!canResend) {
        showMessage('Please wait before requesting another OTP', 'error');
        return;
    }

    const sendOtpBtn = document.getElementById('sendOtpBtn');
    sendOtpBtn.disabled = true;
    sendOtpBtn.textContent = 'Sending...';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'verify-phone.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function () {
        if (this.status === 200) {
            const response = JSON.parse(this.responseText);
            if (response.success) {
                showMessage('OTP sent successfully to +63' + newPhone, 'success');
                document.getElementById('otpSection').classList.add('active');
                startCountdown(120);
                sendOtpBtn.style.display = 'none';
            } else {
                showMessage(response.message || 'Failed to send OTP', 'error');
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send OTP';
            }
        } else {
            showMessage('Server error. Please try again.', 'error');
            sendOtpBtn.disabled = false;
            sendOtpBtn.textContent = 'Send OTP';
        }
    };
    xhr.send('action=send_otp&phone=' + encodeURIComponent(newPhone));
}

function verifyOTP() {
    const newPhone = document.getElementById('newPhone').value.trim();
    const otpCode = document.getElementById('otpCode').value.trim();

    if (!otpCode || otpCode.length !== 6) {
        showMessage('Please enter the 6-digit OTP code', 'error');
        return;
    }

    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    verifyOtpBtn.disabled = true;
    verifyOtpBtn.textContent = 'Verifying...';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'verify-phone.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function () {
        if (this.status === 200) {
            const response = JSON.parse(this.responseText);
            if (response.success) {
                showMessage('Phone number updated successfully!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showMessage(response.message || 'Invalid OTP code', 'error');
                verifyOtpBtn.disabled = false;
                verifyOtpBtn.textContent = 'Verify & Update';
            }
        } else {
            showMessage('Server error. Please try again.', 'error');
            verifyOtpBtn.disabled = false;
            verifyOtpBtn.textContent = 'Verify & Update';
        }
    };
    xhr.send('action=verify_otp&phone=' + encodeURIComponent(newPhone) + '&otp=' + encodeURIComponent(otpCode));
}

function startCountdown(seconds) {
    canResend = false;
    const timerElement = document.getElementById('timer');
    const resendBtn = document.getElementById('resendOtpBtn');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    
    let remainingTime = seconds;
    
    countdownTimer = setInterval(() => {
        const minutes = Math.floor(remainingTime / 60);
        const secs = remainingTime % 60;
        timerElement.textContent = `Resend OTP in ${minutes}:${secs.toString().padStart(2, '0')}`;
        
        remainingTime--;
        
        if (remainingTime < 0) {
            clearInterval(countdownTimer);
            timerElement.textContent = 'OTP expired. Please request a new one.';
            resendBtn.style.display = 'inline-block';
            resendBtn.disabled = false;
            sendOtpBtn.style.display = 'inline-block';
            sendOtpBtn.disabled = false;
            sendOtpBtn.textContent = 'Send OTP';
            canResend = true;
        }
    }, 1000);
}
</script>

</body>
</html>