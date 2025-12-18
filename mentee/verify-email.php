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

// --- SENDGRID/DOTENV SETUP ---
// Load SendGrid and environment variables
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables using phpdotenv
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Exception $e) {
    error_log("Dotenv failed to load in verify-email.php: " . $e->getMessage());
}
// -----------------------------

$username = $_SESSION['username'];
$status_message = "";
$message_type = ""; // 'success' or 'error'

// Fetch current user data
$sql = "SELECT first_name, email, email_verification, icon 
        FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstName = $row['first_name'];
    $current_email = $row['email'];
    $email_verification = $row['email_verification'];
    $menteeIcon = $row['icon'];
} else {
    echo "User not found.";
    exit();
}
$stmt->close();

// State variables for the multi-step form
$show_email_input = true; // Step 1: Input new email
$show_code_input = false;  // Step 2: Input OTP code
$new_email = $_SESSION['new_email'] ?? ''; // Store new email in session temporarily


// --- FUNCTION TO SEND OTP EMAIL (SENDGRID IMPLEMENTATION) ---
function sendVerificationEmail($recipient_email, $code) {
    // 1. Get API key and sender email from environment variables
    $sendgrid_api_key = $_ENV['SENDGRID_API_KEY'] ?? null;
    $from_email = $_ENV['FROM_EMAIL'] ?? 'noreply@coach.com';
    
    // 2. Validate required environment variables
    if (!$sendgrid_api_key) {
        error_log("SendGrid API key is missing. Cannot send verification email to " . $recipient_email);
        return false;
    }
    
    if (empty($from_email)) {
        error_log("FROM_EMAIL is missing in .env file. Cannot send email to " . $recipient_email);
        return false;
    }

    try {
        $email = new \SendGrid\Mail\Mail();
        $sender_name = $_ENV['FROM_NAME'] ?? "BPSUCOACH";
        
        $email->setFrom($from_email, $sender_name);
        $email->setSubject("Your Email Update Verification Code");
        $email->addTo($recipient_email);
        
        $html_body = "
        <html>
        <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color:rgb(241, 223, 252); }
            .header { background-color: #562b63; padding: 15px; color: white; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .credentials { background-color: #fff; border: 1px dashed #562b63; padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; color: #562b63; }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
        </style>
        </head>
        <body>
        <div class='container'>
            <div class='header'>
            <h2>Email Verification</h2>
            </div>
            <div class='content'>
            <p>Hello,</p>
            <p>You requested to update your email address for the COACH system. Please use the code below to complete the process.</p>
            <p>Your <strong>verification code</strong> is:</p>
            <div class='credentials'>$code</div>
            <p>This code will expire in 5 minutes. Please enter it on the verification page.</p>
            <p>If you did not request this change, please ignore this email.</p>
            </div>
            <div class='footer'>
            <p>&copy; " . date("Y") . " COACH. All rights reserved.</p>
            </div>
        </div>
        </body>
        </html>
        ";
        
        $email->addContent("text/html", $html_body);
        
        $sendgrid = new \SendGrid($sendgrid_api_key);
        $response = $sendgrid->send($email);

        // Check for success status code (200-299)
        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            return true;
        } else {
            // Log detailed error from SendGrid API
            $error_message = "SendGrid API failed to send verification email. Status: " . $response->statusCode() . ". Body: " . ($response->body() ?: 'No body response');
            error_log($error_message);
            return false;
        }

    } catch (\Exception $e) {
        error_log("SendGrid Exception in verify-email.php: " . $e->getMessage());
        return false;
    }
}
// -----------------------------


// --- HANDLE FORM ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ==========================================================
    // 1. UPDATE EMAIL STEP (Initiate the change)
    // ==========================================================
    if (isset($_POST['update_email_submit'])) {
        $new_email_input = trim($_POST['new_email'] ?? '');
        
        // Basic validation
        if (empty($new_email_input) || !filter_var($new_email_input, FILTER_VALIDATE_EMAIL)) {
            $status_message = "Please enter a valid email address.";
            $message_type = "error";
        } elseif ($new_email_input === $current_email) {
            $status_message = "The new email is the same as your current email.";
            $message_type = "error";
        } else {
            // Check if email is already taken by another user
            $check_sql = "SELECT username FROM users WHERE email = ? AND username != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $new_email_input, $username);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $status_message = "This email address is already in use by another account.";
                $message_type = "error";
                $check_stmt->close();
            } else {
                // Generate and send code
                $code = rand(100000, 999999);
                $_SESSION['new_email'] = $new_email_input; // Temporarily store the email
                $_SESSION['email_code'] = $code;
                $_SESSION['email_code_time'] = time(); // 5 minutes expiration
                
                $check_stmt->close();

                if (sendVerificationEmail($new_email_input, $code)) {
                    $status_message = "Verification code sent to $new_email_input. Please check your inbox.";
                    $message_type = "success";
                    $show_email_input = false; // Move to Step 2
                    $show_code_input = true;
                    $new_email = $new_email_input; // Update $new_email for display
                } else {
                    // SendGrid failed, display an error message
                    $status_message = "Failed to send verification email. Please check server logs for SendGrid errors or contact support.";
                    $message_type = "error";
                }
            }
        }
    }
    
    // ==========================================================
    // 2. VERIFY CODE STEP (Confirm the change)
    // ==========================================================
    elseif (isset($_POST['verify_code_submit'])) {
        $input_code = trim($_POST['code'] ?? '');
        $saved_code = $_SESSION['email_code'] ?? null;
        $timestamp = $_SESSION['email_code_time'] ?? 0;
        $email_to_update = $_SESSION['new_email'] ?? null;

        $show_email_input = false; // Keep on code step
        $show_code_input = true;
        
        if ($email_to_update === null) {
            $status_message = "Session expired. Please start the email change process again.";
            $message_type = "error";
            $show_email_input = true;
            $show_code_input = false;
        } elseif ($saved_code && time() - $timestamp <= 300) { // Code valid for 5 minutes (300 seconds)
            if ($input_code == $saved_code) {
                // Code is correct and valid: FINAL UPDATE
                $update_sql = "UPDATE users SET email = ?, email_verification = 'Pending' WHERE username = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $email_to_update, $username);
                
                if ($update_stmt->execute()) {
                    $status_message = "Email updated successfully to $email_to_update! The new email now requires verification.";
                    $message_type = "success";
                    $current_email = $email_to_update; // Update display variable
                    $email_verification = 'Pending';
                    
                    // Clear session data for security
                    unset($_SESSION['new_email']);
                    unset($_SESSION['email_code']);
                    unset($_SESSION['email_code_time']);

                    $show_email_input = true; // Go back to start
                    $show_code_input = false;
                } else {
                    $status_message = "Error saving new email to database.";
                    $message_type = "error";
                }
                $update_stmt->close();

            } else {
                $status_message = "Invalid verification code. Please try again.";
                $message_type = "error";
            }
        } else {
            $status_message = "Code expired or not found. Please resend code.";
            $message_type = "error";
        }
    }

    // ==========================================================
    // 3. RESEND CODE STEP
    // ==========================================================
    elseif (isset($_POST['resend_code_submit'])) {
        $email_to_update = $_SESSION['new_email'] ?? null;
        
        if ($email_to_update) {
            $code = rand(100000, 999999);
            $_SESSION['email_code'] = $code;
            $_SESSION['email_code_time'] = time();

            if (sendVerificationEmail($email_to_update, $code)) {
                $status_message = "New verification code resent to $email_to_update. (Expires in 5 minutes)";
                $message_type = "success";
            } else {
                $status_message = "Failed to resend email. Please check server logs or contact support.";
                $message_type = "error";
            }
            $show_email_input = false;
            $show_code_input = true;
        } else {
             $status_message = "No pending email change. Please enter a new email first.";
             $message_type = "error";
             $show_email_input = true;
             $show_code_input = false;
        }
    }
}

// Re-check state after POST processing
if ($show_code_input) {
    $show_email_input = false;
}
if ($show_email_input) {
    $show_code_input = false;
}

// Close connection before HTML output
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/verify-email.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Edit Email</title>
</head>
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

        /* Status messages */
        .status-msg {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .status-msg.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-msg.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>

<body>
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
 

    <main class="profile-container">
    <nav class="tabs">
      <button onclick="window.location.href='profile.php'">Profile</button>
      <button onclick="window.location.href='edit-profile.php'">Edit Profile</button>
      <button class="active" onclick="window.location.href='verify-email.php'">Edit Email</button>
      <button onclick="window.location.href='verify-phone.php'">Edit Phone</button>
      <button onclick="window.location.href='edit-username.php'">Edit Username</button>
      <button onclick="window.location.href='reset-password.php'">Reset Password</button>
    </nav>

    <div class="container">
      <h2>Update Email Address</h2>
      <p>Your current email address: <strong><?php echo htmlspecialchars($current_email); ?></strong></p>
      
      <?php if (!empty($status_message)) : ?>
        <p class="status-msg <?php echo $message_type; ?>"><?php echo htmlspecialchars($status_message); ?></p>
      <?php endif; ?>
      
      <?php if ($show_email_input) : ?>
        <form method="POST">
          <label>
            New Email Address:
            <input type="email" name="new_email" placeholder="Enter new email address" style="text-transform: none" value="<?php echo htmlspecialchars($new_email); ?>" required>
          </label>
          <button type="submit" name="update_email_submit">Send Verification Code</button>
        </form>
      
      <?php elseif ($show_code_input) : ?>
        <div style="margin-bottom: 20px;">
            <p>A verification code was sent to: <strong><?php echo htmlspecialchars($new_email); ?></strong></p>
            <p>The code is valid for 5 minutes.</p>
        </div>
        
        <form method="POST">
          <label>
            Enter Verification Code:
            <input type="text" name="code" placeholder="Enter 6-digit code" required pattern="\d{6}">
          </label>
          <button type="submit" name="verify_code_submit">Confirm New Email</button>
        </form>

        <form method="POST" class="code-buttons">
          <button type="submit" name="resend_code_submit" id="resendBtn" class="resend-btn">Resend Code</button>
        </form>

      <?php endif; ?>

    </div>
  </main>

<script src="js/mentee.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Select all necessary elements
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");
    
    // --- Profile Menu Toggle Logic ---
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

    // --- Logout Dialog Logic ---
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
</script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

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