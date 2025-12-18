<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

session_start();

// Load SendGrid and environment variables
require __DIR__ . '/../vendor/autoload.php';
use SendGrid\Mail\Mail;

// Password generation function
function generateSecurePassword($length = 8) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()-_=+[]{}|;:,.<>?';
    
    // Ensure at least one character from each set
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest randomly
    $allChars = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}

// Load environment variables with proper error handling and halt (FIXED BLOCK)
try {
    // Check for the .env file in the correct location (one level up)
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } else {
        // Throw a specific error if the file isn't found
        throw new \Exception(".env file not found at " . realpath(__DIR__ . '/../'));
    }
} catch (\Exception $e) {
    // LOG the error to a file
    // Note: You must ensure /admin/error_log.txt is writable by the web server
    $log_file = __DIR__ . '/error_log.txt';
    error_log(date('[Y-m-d H:i:s] ') . "Configuration Error (Dotenv): " . $e->getMessage() . "\n", 3, $log_file);
    
    // DIE with the original error message to force the user to check the log
    die("Configuration error. Check error_log.txt in the 'admin' folder."); 
}

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: login.php");
    exit();
}

require '../connection/db_connection.php';

// Handle Create
if (isset($_POST['create'])) {
    $username_user = $_POST['username'];
    $first_name = $_POST['first_name']; 
    $last_name = $_POST['last_name']; 
    $email = $_POST['email'];
    
    // --- NEW: Server-Side Username Uniqueness Check to prevent Fatal Error ---
    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username_user);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        // Username is already taken, log error and redirect
        $error_message = "Error: The username '" . htmlspecialchars($username_user) . "' is already taken. Please choose another one.";
        error_log($error_message); // Log the error
        $check_stmt->close();
        
        // Redirect with status=failed so the error message is displayed
        header("Location: moderators.php?status=failed&error=" . urlencode($error_message));
        exit(); 
    }
    $check_stmt->close();
    // --- END NEW CHECK ---
    
    // GENERATE SECURE PASSWORD AUTOMATICALLY
    $password = generateSecurePassword(8);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_type = 'Admin';

    $stmt = $conn->prepare("INSERT INTO users (username, password, email, user_type, first_name, last_name, password_changed) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("ssssss", $username_user, $hashed_password, $email, $user_type, $first_name, $last_name);
    
    if ($stmt->execute()) {
        // Send Email with SendGrid (WORKING CODE FROM FIRST FILE)
            try {
            if (!isset($_ENV['SENDGRID_API_KEY']) || empty($_ENV['SENDGRID_API_KEY'])) {
                error_log("SendGrid API key is missing. Email not sent to " . $email);
                throw new Exception("SendGrid API key not set in .env file.");
            }
            $sender_email = $_ENV['FROM_EMAIL'] ?? 'noreply@coach-hub.online'; 
            if (empty($sender_email) || $sender_email == 'noreply@coach-hub.online') {
                 error_log("FROM_EMAIL is missing in .env file or fallback is used. Email not sent to " . $email);
                 throw new Exception("FROM_EMAIL not set in .env file or is invalid.");
            }


            $email_content = new Mail();
            $email_content->setFrom($sender_email, 'BPSU - COACH');
            $email_content->setSubject("Your COACH Admin Access Credentials");
            $email_content->addTo($email, $username_user);

            // Content
            $html_body = "
            <html>
            <head>
              <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background-color:rgb(241, 223, 252); }
                .header { background-color: #562b63; padding: 15px; color: white; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .credentials { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .footer { text-align: center; padding: 10px; font-size: 12px; color: #777; }
                .warning { background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 15px 0; border-radius: 5px; color: #856404; }
              </style>
            </head>
            <body>
              <div class='container'>
                <div class='header'>
                  <h2>Welcome to COACH Admin Panel</h2>
                </div>
                <div class='content'>
                  <p>Dear Mr./Ms. <b>$first_name $last_name</b>,</p>
                  <p>You have been granted administrator access to the COACH system. Below are your login credentials:</p>
                  
                  <div class='credentials'>
                    <p><strong>Username:</strong> $username_user</p>
                    <p><strong>Temporary Password:</strong> $password</p>
                  </div>
                  
                  <div class='warning'>
                    <p><strong>⚠️ IMPORTANT:</strong> For security reasons, you will be required to change your password upon your first login. You cannot access the system until you create a new password.</p>
                  </div>
                  
                  <p>Please log in at <a href='https://coach-hub.online/login.php'>COACH</a> using these credentials.</p>
                  <p>If you have any questions or need assistance, please contact the system administrator.</p>
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
                 $error_message = "SendGrid API failed with status code " . $response->statusCode() . ". Body: " . $response->body() . ". Headers: " . print_r($response->headers(), true);
                 error_log($error_message); 
                 throw new Exception("Email failed to send. Status: " . $response->statusCode() . ". Check logs for details.");
            }
            
            header("Location: moderators.php?status=created&email=sent");
            exit();

        } catch (\Exception $e) {
            error_log("SendGrid Error: " . $e->getMessage());
            header("Location: moderators.php?status=created&email=failed&error=" . urlencode("SendGrid failed. See logs. Details: " . $e->getMessage()));
            exit();
        }

    } else {
        $error = "Error creating user: " . $conn->error;
        error_log($error);
    }
    $stmt->close();
}

// --- Update ---
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $username_user = $_POST['username'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param("ssssi", $first_name, $last_name, $username_user, $email, $id);
    
    if ($stmt->execute()) {
        header("Location: moderators.php?status=updated");
        exit();
    } else {
        $error = "Error updating user: " . $conn->error;
    }
    $stmt->close();
}

// --- Delete ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: moderators.php?status=deleted");
        exit();
    } else {
        $error = "Error deleting user: " . $conn->error;
    }
    $stmt->close();
}

// --- Fetch Admins ---
$result = $conn->query("SELECT * FROM users WHERE user_type = 'Admin'");

// --- Fetch SuperAdmin Data ---
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
} elseif (isset($_SESSION['superadmin'])) {
    $username = $_SESSION['superadmin'];
} elseif (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT username, first_name, last_name, icon FROM users WHERE user_id = ? AND user_type = 'Super Admin'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    
    if ($admin_result->num_rows === 1) {
        $row = $admin_result->fetch_assoc();
        $username = $row['username'];
        $_SESSION['superadmin_name'] = $row['first_name'] . ' ' . $row['last_name'];
        $_SESSION['superadmin_icon'] = !empty($row['icon']) ? $row['icon'] : "../uploads/img/default_pfp.png";
        $_SESSION['first_name'] = $row['first_name'];
        $_SESSION['username'] = $username;
    } else {
        $_SESSION['superadmin_name'] = "SuperAdmin";
        $_SESSION['superadmin_icon'] = "../uploads/img/default_pfp.png";
    }
    $stmt->close();
    goto skip_username_query;
} else {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT first_name, last_name, icon FROM users WHERE username = ? AND user_type = 'Super Admin'");
$stmt->bind_param("s", $username);
$stmt->execute();
$admin_result = $stmt->get_result();

skip_username_query:
if (isset($admin_result) && $admin_result->num_rows === 1) {
    $row = $admin_result->fetch_assoc();
    $_SESSION['superadmin_name'] = $row['first_name'] . ' ' . $row['last_name'];
    $_SESSION['superadmin_icon'] = !empty($row['icon']) ? $row['icon'] : "../uploads/img/default_pfp.png";
    $_SESSION['first_name'] = $row['first_name'];
} else {
    $_SESSION['superadmin_name'] = $_SESSION['superadmin_name'] ?? "SuperAdmin";
    $_SESSION['superadmin_icon'] = $_SESSION['superadmin_icon'] ?? "../uploads/img/default_pfp.png";
}
if (isset($stmt)) {
    $stmt->close();
}

$admin_icon = $_SESSION['superadmin_icon'];
$admin_name = $_SESSION['first_name'];

// Status messages
$message = '';
$error = $error ?? '';

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'created') {
        if (isset($_GET['email']) && $_GET['email'] == 'sent') {
            $message = "Moderator created successfully! Login credentials sent via email.";
        } elseif (isset($_GET['email']) && $_GET['email'] == 'failed') {
            $error_detail = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'Unknown error';
            $message = "Moderator created successfully, but email failed to send. Error: " . $error_detail;
        } else {
            $message = "Moderator created successfully!";
        }
    } elseif ($_GET['status'] == 'failed') { // <<< NEW: Handle server-side validation error
        if (isset($_GET['error'])) {
            $error = htmlspecialchars($_GET['error']);
        } else {
            $error = "An unknown error occurred during submission.";
        }
    } elseif ($_GET['status'] == 'updated') {
        $message = "Moderator details updated successfully!";
    } elseif ($_GET['status'] == 'deleted') {
        $message = "Moderator deleted successfully!";
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/dashboard.css"/>
    <link rel="stylesheet" href="css/navigation.css"/>
    <title>Manage Moderators | SuperAdmin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #562b63;
            color: #e0e0e0;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .sidebar-header {
            text-align: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }
        .sidebar-header img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #7a4a87;
            margin-bottom: 8px;
        }
        .sidebar-header h4 {
            margin: 0;
            font-weight: 500;
            color: #fff;
        }
        .sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        .sidebar nav ul li a {
            display: block;
            color: #e0e0e0;
            text-decoration: none;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 0;
            transition: background-color 0.2s, border-left-color 0.2s;
            display: flex;
            align-items: center;
            border-left: 5px solid transparent;
        }
        .sidebar nav ul li a i {
            margin-right: 12px;
            font-size: 18px;
        }
        .sidebar nav ul li a:hover {
            background-color: #37474f;
            color: #fff;
        }
        .sidebar nav ul li a.active {
            background-color: #7a4a87;
            border-left: 5px solid #00bcd4;
            color: #00bcd4;
        }
        .logout-container {
            margin-top: auto;
            padding: 20px;
            border-top: 1px solid #37474f;
        }
        .logout-btn {
            background-color: #e53935;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: bold;
        }
        .logout-btn:hover {
            background-color: #c62828;
        }

        .main-content {
            flex-grow: 1;
            padding: 20px 30px;
        }

        header {
            padding: 10px 0;
            border-bottom: 2px solid #562b63;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            color: #562b63;
            margin: 0;
            font-size: 28px;
            margin-top: 30px;
        }
        
        .new-moderator-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
            font-weight: 600;
            margin-top: 30px;
        }
        .new-moderator-btn:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }
        
        .table-container {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            background-color: #fff;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: none;
            padding: 15px;
            text-align: left;
        }
        th {
            background-color: #562b63;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 14px;
        }
        tr:nth-child(even) {
            background-color: #f8f8f8;
        }
        tr:hover:not(.no-data) {
            background-color: #f1f1f1;
        }
        
        .action-button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 600;
        }
        .action-button:hover {
            background-color: #5a6268;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-box input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 300px;
            font-size: 16px;
        }

        .details-view, .form-container {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }
        .details-view h3, .form-container h3 {
            color: #562b63;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
            margin-bottom: 20px;
        }
        .details-view p {
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        .details-view strong {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 0.95em;
        }
        .details-view input[type="text"], .details-view input[type="email"], .details-view input[type="date"], .details-view textarea, .details-view select, .details-view input[type="password"] {
            flex-grow: 1;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-sizing: border-box;
            background-color: #f8f9fa;
            cursor: default;
        }
        .details-view textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .action-buttons {
            margin-top: 20px;
            text-align: right;
            border-top: 1px solid #eee;
            padding-top: 15px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        .action-buttons.between {
            justify-content: space-between;
        }
        .action-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            margin-left: 0;
        }
        .back-btn { 
            background-color: #6c757d;
            color: white;
            margin-left: 300px;
        }
        .back-btn:hover {
            background-color: #5a6268;
        }
        .edit-btn, .update-btn {
            background-color: #00bcd4;
            color: white;
        }
        .edit-btn:hover, .update-btn:hover {
            background-color: #0097a7;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .create-btn {
            background-color: #28a745;
            color: white;
        }
        .create-btn:hover {
            background-color: #218838;
        }

        .hidden {
            display: none;
        }
        
        .message-box {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-field {
            display: flex; 
            align-items: flex-start;
            margin-bottom: 20px;
            position: relative;
        }

        .form-field label {
            width: 120px; 
            padding-right: 15px; 
            font-size: 16px; 
            font-weight: normal;
            flex-shrink: 0;
            padding-top: 10px;
        }

        .form-field-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .form-field input[type="text"],
        .form-field input[type="email"],
        .form-field input[type="password"] {
            flex-grow: 1;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            line-height: 1.5;
            transition: all 0.2s ease-in-out;
        }

        .form-field input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .username-status {
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .username-status.available {
            color: #28a745;
        }

        .username-status.taken {
            color: #dc3545;
        }

        .username-status.checking {
            color: #ffc107;
        }

        .username-icon {
            font-size: 14px;
        }

        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-box i {
            color: #0066cc;
            font-size: 20px;
        }

        .info-box p {
            margin: 0;
            color: #004085;
            font-size: 14px;
        }
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
        <img src="<?php echo htmlspecialchars($admin_icon); ?>" alt="SuperAdmin Profile Picture" />
        <div class="admin-text">
          <span class="admin-name"><?php echo htmlspecialchars($_SESSION['superadmin_name']); ?></span>
          <span class="admin-role">SuperAdmin</span>
        </div>
        <a href="profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
          <ion-icon name="create-outline" class="verified-icon"></ion-icon>
        </a>
      </div>
    </div>

    <div class="menu-items">
      <ul class="navLinks">
        <li class="navList">
          <a href="dashboard.php">
            <ion-icon name="home-outline"></ion-icon>
            <span class="links">Home</span>
          </a>
        </li>
        <li class="navList active">
          <a href="moderators.php">
            <ion-icon name="lock-closed-outline"></ion-icon>
            <span class="links">Moderators</span>
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
            <a href="courses.php"> <ion-icon name="book-outline"></ion-icon>
                <span class="links">Courses</span>
            </a>
        </li>
        <li class="navList">
            <a href="manage_session.php"> <ion-icon name="calendar-outline"></ion-icon>
              <span class="links">Sessions</span>
            </a>
        </li>
        <li class="navList"> 
            <a href="feedbacks.php"> <ion-icon name="star-outline"></ion-icon>
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
        <li class="navList logout-link">
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
    
    <header>
        <h1>Manage Moderators</h1>
    </header>

    <?php if ($message): ?>
        <div class="message-box <?= strpos($message, 'failed') !== false ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (isset($error) && $error): ?>
        <div class="message-box error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="controls">
        <button onclick="showCreateForm()" class="new-moderator-btn"><i class="fas fa-plus-circle"></i> Create New Moderator</button>

        <div class="search-box">
            <input type="text" id="searchInput" onkeyup="searchModerators()" placeholder="Search moderators by ID, Username, or Name...">
        </div>
    </div>
    
    <div class="form-container hidden" id="createForm">
        <h2 class="form-title">Create New Moderator</h2>

        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <p><strong>Note:</strong> A secure 8-character password will be automatically generated and sent to the moderator's email address.</p>
        </div>

        <form method="POST" id="createModeratorForm">
            <input type="hidden" name="create" value="1">
            
            <div class="form-grid">
                
                <div class="form-field">
                    <label for="create_first_name">First Name</label>
                    <div class="form-field-wrapper">
                        <input type="text" id="create_first_name" name="first_name" required placeholder="Enter first name">
                    </div>
                </div>
                
                <div class="form-field">
                    <label for="create_last_name">Last Name</label>
                    <div class="form-field-wrapper">
                        <input type="text" id="create_last_name" name="last_name" required placeholder="Enter last name">
                    </div>
                </div>
                
                <div class="form-field">
                    <label for="create_email">Email</label>
                    <div class="form-field-wrapper">
                        <input type="email" id="create_email" name="email" required placeholder="user@example.com">
                    </div>
                </div>
                
                <div class="form-field">
                    <label for="create_username">Username</label>
                    <div class="form-field-wrapper">
                        <input type="text" id="create_username" name="username" required placeholder="Choose a username" onkeyup="checkUsernameAvailability()">
                        <div class="username-status hidden" id="usernameStatus"></div>
                    </div>
                </div>
                
            </div>
            <div class="action-buttons">
                <button type="button" onclick="hideCreateForm()" class="back-btn"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="create-btn" id="submitBtn" disabled><i class="fas fa-save"></i> Create & Send Credentials</button>
            </div>
        </form>
    </div>

    <div id="tableContainer" class="table-container">
        <table id="moderatorsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="data-row">
                            <td><?= $row['user_id'] ?></td>
                            <td class="username"><?= htmlspecialchars($row['username']) ?></td>
                            <td class="name"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td class="email"><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <button class="action-button" onclick='viewModerator(this)' 
                                    data-info='<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="no-data"><td colspan="5" style="text-align:center;">No Moderators found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="detailView" class="details-view hidden">
        <h3>Moderator Details</h3>
        <form method="POST" id="moderatorForm">
            <input type="hidden" name="id" id="user_id">
            
            <div class="details-grid">
                <p><strong>User ID</strong>
                    <input type="text" id="display_user_id" readonly>
                </p>
                <p><strong>Username</strong>
                    <input type="text" name="username" id="username" required readonly>
                </p>
                <p><strong>First Name</strong>
                    <input type="text" name="first_name" id="first_name" required readonly>
                </p>
                <p><strong>Last Name</strong>
                    <input type="text" name="last_name" id="last_name" required readonly>
                </p>
                <p><strong>Email</strong>
                    <input type="email" name="email" id="email" required readonly>
                </p>
            </div>

            <div class="action-buttons between">
                <div>
                    <button type="button" onclick="goBack()" class="back-btn"><i class="fas fa-arrow-left"></i> Back</button>
                    <button type="button" id="editButton" class="edit-btn"><i class="fas fa-edit"></i> Edit</button>
                    <button type="submit" name="update" value="1" id="updateButton" class="update-btn hidden"><i class="fas fa-sync-alt"></i> Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script src="js/navigation.js"></script>
<script>
let currentModeratorId = null;
let usernameCheckTimeout;

function goBack() {
    document.getElementById('detailView').classList.add('hidden');
    document.getElementById('tableContainer').classList.remove('hidden');
    document.getElementById('createForm').classList.add('hidden');
}

function showCreateForm() {
    document.getElementById('tableContainer').classList.add('hidden');
    document.getElementById('detailView').classList.add('hidden');
    document.getElementById('createForm').classList.remove('hidden');
    document.getElementById('createModeratorForm').reset();
    document.getElementById('usernameStatus').classList.add('hidden');
    document.getElementById('submitBtn').disabled = true;
}

function hideCreateForm() {
    document.getElementById('createForm').classList.add('hidden');
    document.getElementById('tableContainer').classList.remove('hidden');
}

function viewModerator(button) {
    const moderatorData = JSON.parse(button.getAttribute('data-info'));
    currentModeratorId = moderatorData.user_id;

    document.getElementById('user_id').value = moderatorData.user_id;
    document.getElementById('display_user_id').value = moderatorData.user_id;
    document.getElementById('first_name').value = moderatorData.first_name;
    document.getElementById('last_name').value = moderatorData.last_name;
    document.getElementById('email').value = moderatorData.email;
    document.getElementById('username').value = moderatorData.username;

    const formFields = document.querySelectorAll('#moderatorForm input:not([type="hidden"])');
    formFields.forEach(field => field.readOnly = true);
    
    document.getElementById('updateButton').classList.add('hidden');
    document.getElementById('editButton').classList.remove('hidden');

    document.getElementById('tableContainer').classList.add('hidden');
    document.getElementById('createForm').classList.add('hidden');
    document.getElementById('detailView').classList.remove('hidden');
}

document.getElementById('editButton').addEventListener('click', function() {
    const formFields = document.querySelectorAll('#moderatorForm input:not([type="hidden"])');
    formFields.forEach(field => {
        if (field.id !== 'display_user_id') {
            field.readOnly = false;
        }
    });

    document.getElementById('editButton').classList.add('hidden');
    document.getElementById('updateButton').classList.remove('hidden');
});

// USERNAME AVAILABILITY CHECK FUNCTION
function checkUsernameAvailability() {
    const username = document.getElementById('create_username').value.trim();
    const statusDiv = document.getElementById('usernameStatus');
    const submitBtn = document.getElementById('submitBtn');

    // Clear previous timeout
    clearTimeout(usernameCheckTimeout);

    if (username.length === 0) {
        statusDiv.classList.add('hidden');
        submitBtn.disabled = true;
        return;
    }

    // Show checking status
    statusDiv.classList.remove('hidden');
    statusDiv.className = 'username-status checking';
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin username-icon"></i> Checking availability...';

    // Debounce the check
    usernameCheckTimeout = setTimeout(() => {
        const formData = new FormData();
        formData.append('username', username);

        fetch('../check_username.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                statusDiv.className = 'username-status taken';
                statusDiv.innerHTML = '<i class="fas fa-times-circle username-icon"></i> Username is already taken';
                submitBtn.disabled = true;
            } else {
                statusDiv.className = 'username-status available';
                statusDiv.innerHTML = '<i class="fas fa-check-circle username-icon"></i> Username is available';
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error checking username:', error);
            statusDiv.className = 'username-status error';
            statusDiv.innerHTML = '<i class="fas fa-exclamation-circle username-icon"></i> Error checking availability';
            submitBtn.disabled = true;
        });
    }, 500); // Wait 500ms after user stops typing
}

const navBar = document.querySelector("nav");
const navToggle = document.querySelector(".navToggle");
if (navToggle) {
    navToggle.addEventListener('click', () => {
        navBar.classList.toggle('close');
    });
}

function confirmDelete() {
    if (currentModeratorId && confirm(`Are you sure you want to permanently delete the Moderator with ID ${currentModeratorId}? This action cannot be undone.`)) {
        window.location.href = `moderators.php?delete=${currentModeratorId}`;
    }
}

function searchModerators() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#moderatorsTable tbody tr.data-row');
    const noDataRow = document.querySelector('#moderatorsTable tbody tr.no-data');

    let found = false;
    rows.forEach(row => {
        const id = row.cells[0].innerText.toLowerCase();
        const username = row.cells[1].innerText.toLowerCase();
        const name = row.cells[2].innerText.toLowerCase();

        if (id.includes(input) || username.includes(input) || name.includes(input)) {
            row.style.display = '';
            found = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    if (noDataRow) {
        noDataRow.style.display = found ? 'none' : (rows.length === 0 ? '' : 'none');
    }
}
</script>
<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
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