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

$username = $_SESSION['username'];
$message = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if new passwords match
    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } else {
        // Verify current password
        $sql = "SELECT password FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stored_hashed_password = $row['password'];

            // Verify current password
            if (password_verify($current_password, $stored_hashed_password)) {
                // Hash the new password
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password in database
                $update_sql = "UPDATE users SET password = ? WHERE username = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $hashed_new_password, $username);

                if ($update_stmt->execute()) {
                    $message = "Password updated successfully!";
                } else {
                    $error = "Error updating password: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $error = "Current password is incorrect!";
            }
        } else {
            $error = "User not found!";
        }
        $stmt->close();
    }
}

// Fetch first name and mentee icon
$firstName = '';
$menteeIcon = '';

$sql = "SELECT first_name, icon FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username); // Using $username here
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstName = $row['first_name'];
    $menteeIcon = $row['icon'];
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
  <link rel="stylesheet" href="css/reset-password.css" />
  <link rel="stylesheet" href="css/message.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <title>Reset Password</title>
    <style>
        .logout-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none; /* Start hidden, toggled to 'flex' by JS */
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

        .password-wrapper {
          position: relative;
          display: flex; 
          align-items: center;
        }

        .password-wrapper input {
          flex-grow: 1; 
          padding-right: 40px; 
        }

        .toggle-password {
          margin-top: 9px;
          font-size: 20px;
          position: absolute;
          right: 10px;
          cursor: pointer;
          z-index: 10;
          color: #333; 
        }
 
        /* New styles for password validation - UPDATED */
        .password-field-container {
            position: relative;
            /* Ensure there's enough space for the popup if it's within a flex container */
        }

        .password-popup {
            display: none; /* Hidden by default, shown on focus */
            position: absolute;
            top: 100%; /* Position below the input */
            left: 0;
            width: 100%;
            /* Max width to control size, adjust as needed */
            max-width: 300px; 
            padding: 15px 20px; /* Increased padding */
            background-color: rgba(30, 30, 30, 0.9); /* Darker, semi-transparent background */
            border: none; /* No border for a cleaner look */
            border-radius: 8px; /* Slightly more rounded corners */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3); /* Stronger, darker shadow */
            z-index: 20; /* Ensure it's above other elements */
            font-size: 0.95em; /* Slightly larger font */
            margin-top: 8px; /* More space from the input */
            color: #fff; /* White text for contrast */
            text-align: left; /* Ensure text alignment */
        }

        .password-popup p {
            color: #fff; /* White color for "Password must:" text */
            margin-bottom: 10px; /* Space below the heading */
            font-weight: bold; /* Make heading bold */
            font-size: 1.1em; /* Slightly larger font for heading */
        }

        .password-popup ul {
            list-style: none; /* Remove default bullet points */
            padding-left: 0;
            margin-bottom: 0;
        }

        .password-popup li {
            padding: 5px 0; /* More vertical padding for list items */
            position: relative; /* For custom checkmark/x positioning */
            padding-left: 25px; /* Space for the custom icon */
            color: #eee; /* Light gray for default text color */
        }

        /* Validation Status Styles - UPDATED */
        .password-popup li.pass {
            color: #00ff00; /* Bright green for passed requirement */
            /* Custom checkmark using pseudo-elements */
            list-style-type: none; /* Ensure no default list style interferes */
        }

        .password-popup li.pass::before {
            content: '✓'; /* Checkmark character */
            color: #00ff00; /* Bright green checkmark */
            position: absolute;
            left: 0;
            top: 5px; /* Adjust vertical alignment */
            font-weight: bold;
        }

        .password-popup li.fail {
            color: #ff0000; /* Bright red for failed requirement */
            /* Custom 'x' mark using pseudo-elements */
            list-style-type: none; /* Ensure no default list style interferes */
        }

        .password-popup li.fail::before {
            content: '✗'; /* 'X' character */
            color: #ff0000; /* Bright red 'x' */
            position: absolute;
            left: 0;
            top: 5px; /* Adjust vertical alignment */
            font-weight: bold;
        }
    
    </style>
</head>

<body>
     <!-- Navigation Section -->
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


    <main class="profile-container" style ="margin-top: 80px;">
    <nav class="tabs">
      <button onclick="window.location.href='profile.php'">Profile</button>
      <button onclick="window.location.href='edit-profile.php'">Edit Profile</button>
      <button onclick="window.location.href='verify-email.php'">Edit Email</button>
      <button onclick="window.location.href='verify-phone.php'">Edit Phone</button>
      <button onclick="window.location.href='edit-username.php'">Change Username</button>
      <button class="active" onclick="window.location.href='reset-password.php'">Reset Password</button>
    </nav>

    <div class="container">
      <h2>Reset Password</h2>
      
      <?php if($message): ?>
        <div class="message"><?php echo $message; ?></div>
      <?php endif; ?>
      
      <?php if($error): ?>
        <div class="error"><?php echo $error; ?></div>
      <?php endif; ?>
      
      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="resetPasswordForm">
        <label>Current Password
            <span class="password-wrapper">
                <input type="password" name="current_password" id="current_password" placeholder="Enter current password" required>
                <span class="toggle-password" onclick="togglePasswordVisibility('current_password', 'toggleIcon_current')">
                    <ion-icon name="eye-outline" id="toggleIcon_current"></ion-icon>
                </span>
            </span>
        </label>

        <label>New Password
            <div class="password-field-container">
                <span class="password-wrapper">
                    <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('new_password', 'toggleIcon_new')">
                        <ion-icon name="eye-outline" id="toggleIcon_new"></ion-icon>
                    </span>
                </span>
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
            </div>
            <span id="password-error" class="error-message"></span>
        </label>

        <label>Confirm New Password
            <span class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'toggleIcon_confirm')">
                    <ion-icon name="eye-outline" id="toggleIcon_confirm"></ion-icon>
                </span>
            </span>
            <span id="confirm-password-error" class="error-message"></span>
        </label>
        
        <button type="submit">Reset Password</button>
      </form>
    </div>
  </main>

 <script>
document.addEventListener("DOMContentLoaded", function() {
    // Select all necessary elements (Existing Logic)
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    // --- Profile Menu Toggle Logic (Existing Logic) ---
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide");
        });
        
        // Close menu when clicking outside
        document.addEventListener("click", function (e) {
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    }

    // --- Logout Dialog Logic (Existing Logic) ---
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
    
    // =========================================================
    // --- PASSWORD TOGGLE FUNCTION (Moved inside DOMContentLoaded) ---
    // =========================================================
    window.togglePasswordVisibility = function(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.setAttribute('name', 'eye-off-outline'); // Change icon to 'eye-off'
        } else {
            passwordInput.type = 'password';
            toggleIcon.setAttribute('name', 'eye-outline'); // Change icon back to 'eye'
        }
    }
    
    // =========================================================
    // --- PASSWORD FORMAT VALIDATION (New Code) ---
    // =========================================================
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordError = document.getElementById('password-error');
    const confirmPasswordError = document.getElementById('confirm-password-error');
    const passwordPopup = document.getElementById('password-popup');

    // Password requirement checkers (ensure these IDs exist in your HTML)
    const lengthCheck = document.getElementById('length-check');
    const uppercaseCheck = document.getElementById('uppercase-check');
    const lowercaseCheck = document.getElementById('lowercase-check');
    const numberCheck = document.getElementById('number-check');
    const specialCheck = document.getElementById('special-check');

    function updateCheck(element, isPassed) {
        if (isPassed) {
            element.classList.remove('fail');
            element.classList.add('pass');
        } else {
            element.classList.remove('pass');
            element.classList.add('fail');
        }
    }

    function validatePasswordFormat(password) {
        if (!newPasswordInput || !passwordPopup) return true; // Skip if elements are missing
        
        let isValid = true;

        // 1. Length check (>= 8)
        const lengthRegex = /.{8,}/;
        updateCheck(lengthCheck, lengthRegex.test(password));
        if (!lengthRegex.test(password)) isValid = false;

        // 2. Uppercase check
        const uppercaseRegex = /[A-Z]/;
        updateCheck(uppercaseCheck, uppercaseRegex.test(password));
        if (!uppercaseRegex.test(password)) isValid = false;

        // 3. Lowercase check
        const lowercaseRegex = /[a-z]/;
        updateCheck(lowercaseCheck, lowercaseRegex.test(password));
        if (!lowercaseRegex.test(password)) isValid = false;

        // 4. Number check
        const numberRegex = /[0-9]/;
        updateCheck(numberCheck, numberRegex.test(password));
        if (!numberRegex.test(password)) isValid = false;

        // 5. Special character check (!@#$%^&*)
        const specialRegex = /[!@#$%^&*]/;
        updateCheck(specialCheck, specialRegex.test(password));
        if (!specialRegex.test(password)) isValid = false;

        // Update main error message
        if (isValid) {
            passwordError.textContent = "";
            newPasswordInput.classList.remove('invalid-input');
            newPasswordInput.classList.add('valid-input');
        } else if (password.length > 0) {
            passwordError.textContent = "Password does not meet all requirements.";
            newPasswordInput.classList.add('invalid-input');
            newPasswordInput.classList.remove('valid-input');
        } else {
             passwordError.textContent = "";
             newPasswordInput.classList.remove('invalid-input', 'valid-input');
        }
        return isValid;
    }

    function validateConfirmPassword() {
        if (!confirmPasswordInput || !newPasswordInput) return true;
        
        const password = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (password === confirmPassword && password.length > 0) {
            confirmPasswordError.textContent = "Passwords match.";
            confirmPasswordError.style.color = "#4CAF50"; // Green for match
            confirmPasswordInput.classList.remove('invalid-input');
            confirmPasswordInput.classList.add('valid-input');
            return true;
        } else if (confirmPassword.length > 0) {
            confirmPasswordError.textContent = "Passwords do not match.";
            confirmPasswordError.style.color = "#f44336"; // Red for mismatch
            confirmPasswordInput.classList.add('invalid-input');
            confirmPasswordInput.classList.remove('valid-input');
            return false;
        } else {
            confirmPasswordError.textContent = "";
            confirmPasswordInput.classList.remove('invalid-input', 'valid-input');
            return false;
        }
    }

    // Event Listeners for new_password
    if (newPasswordInput) {
        newPasswordInput.addEventListener('focus', function() {
            if (passwordPopup) passwordPopup.style.display = 'block';
            validatePasswordFormat(this.value);
        });

        newPasswordInput.addEventListener('blur', function() {
            if (passwordPopup) passwordPopup.style.display = 'none';
            validatePasswordFormat(this.value);
        });

        newPasswordInput.addEventListener('input', function() {
            validatePasswordFormat(this.value);
            validateConfirmPassword(); // Re-check confirm password immediately
        });
    }

    // Event Listeners for confirm_password
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        confirmPasswordInput.addEventListener('blur', validateConfirmPassword);
    }

    // =========================================================
    // --- UPDATED FORM SUBMIT HANDLER ---
    // This replaces the old submit logic with the new validation.
    // =========================================================
    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            const newPassword = newPasswordInput.value;
            
            // 1. Check if new password format is valid (using the comprehensive function)
            const isNewPasswordValid = validatePasswordFormat(newPassword);
            
            // 2. Check if passwords match (using the comprehensive function)
            const passwordsMatch = validateConfirmPassword();

            // Prevent submission if the new password does not meet format requirements
            if (!isNewPasswordValid) {
                alert('The new password does not meet all security requirements.');
                e.preventDefault();
                return;
            }

            // Prevent submission if passwords do not match
            if (!passwordsMatch) {
                alert('New password and confirmation password do not match.');
                e.preventDefault();
                return;
            }
            
            // If validation passes on the client side, the form submits for server-side processing.
        });
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


</body>
</html>