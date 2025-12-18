<?php
session_start();

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

// Get username from session
$current_username = $_SESSION['username'];

// Variable to store messages
$message = "";
$messageType = "";

// Handle form submission for username change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_username'])) {
    // Get form data
    $new_username = trim($_POST['new_username']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($new_username)) {
        $message = "New username cannot be empty";
        $messageType = "error";
    } else {
        // Check if the new username already exists
        $check_sql = "SELECT username FROM users WHERE username = ? AND username != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $new_username, $current_username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "Username already exists. Please choose another one.";
            $messageType = "error";
        } else {
            // Get the hashed password from database to verify
            $get_password_sql = "SELECT password FROM users WHERE username = ?";
            $get_password_stmt = $conn->prepare($get_password_sql);
            $get_password_stmt->bind_param("s", $current_username);
            $get_password_stmt->execute();
            $password_result = $get_password_stmt->get_result();
            
            if ($password_result->num_rows > 0) {
                $row = $password_result->fetch_assoc();
                $hashed_password = $row['password'];
                
                // Verify password
                if (password_verify($password, $hashed_password)) {
                    // Update username in database
                    $update_sql = "UPDATE users SET username = ? WHERE username = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $new_username, $current_username);
                    
                    if ($update_stmt->execute()) {
                        // Update session with new username
                        $_SESSION['username'] = $new_username;
                        
                        $message = "Username updated successfully!";
                        $messageType = "success";
                        $current_username = $new_username; // Update for display on page
                    } else {
                        $message = "Error updating username: " . $conn->error;
                        $messageType = "error";
                    }
                    
                    $update_stmt->close();
                } else {
                    $message = "Incorrect password. Please try again.";
                    $messageType = "error";
                }
                
                $get_password_stmt->close();
            } else {
                $message = "Error retrieving account information.";
                $messageType = "error";
            }
            
            $check_stmt->close();
        }
    }
}

// Fetch current user data
$sql = "SELECT first_name, last_name, username, icon 
        FROM users 
        WHERE username = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Fetch data
    $row = $result->fetch_assoc();
    
    // Assign values to variables
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $name = $first_name . " " . $last_name;
    $profile_picture = $row['icon'];
    
} else {
    // No user found with that username
    echo "Error: User profile not found";
    exit();
}

// Fetch first name and mentee icon again
$firstName = '';
$menteeIcon = '';

$sql = "SELECT first_name, icon FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_username); // FIXED: use the correct username
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstName = $row['first_name'];
    $menteeIcon = $row['icon'];
}

$stmt->close();
$conn->close();

// Function to get profile picture path
function getProfilePicture($profile_picture) {
    if ($profile_picture && !empty($profile_picture)) {
        return $profile_picture; // Return the path stored in the database
    } else {
        return "../img/default_pfp.png"; // Return the default image path
    }
}

// Get the correct profile picture path
$profile_picture_path = getProfilePicture($profile_picture);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/edit-username.css" />
  <link rel="stylesheet" href="css/message.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <title>Edit Username</title>
</head>
    <style>
        .password-wrapper {
          position: relative;
          display: flex; /* Helps align the input and the icon */
          align-items: center;
        } 

        .password-wrapper input {
          flex-grow: 1; 
          padding-right: 40px; /* Make space for the icon */
        }

        .toggle-password {
          margin-top: 7px;
          font-size: 20px;
          position: absolute;
          right: 10px; /* Position icon inside the input field */
          cursor: pointer;
          z-index: 10;
          color: #333; /* Dark color for visibility */
        }
    </style>
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
      <li><a href="#" onclick="confirmLogout(event)">Logout</a></li>
    </ul>
  </div>
</div>
        </nav>
    </section>


    <main class="profile-container" style ="margin-top: 80px;">
    <nav class="tabs">
      <button onclick="window.location.href='profile.php'">Profile</button>
      <button onclick="window.location.href='edit-profile.php'">Edit Profile</button>
      <button onclick="window.location.href='emailverify.php'">Edit Email</button>
      <button onclick="window.location.href='verify-phone.php'">Edit Phone</button>
      <button class="active" onclick="window.location.href='edit-username.php'">Edit Username</button>
      <button onclick="window.location.href='reset-password.php'">Reset Password</button>
    </nav>

    <div class="container">
      <h2>Change Username</h2>
      
      <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>
      
      <form method="POST">
        <label>Current Username 
          <input type="text" value="<?php echo $current_username; ?>" disabled>
        </label>
        <label>New Username 
          <input type="text" name="new_username" placeholder="Enter new username" required>
        </label>
        <label>Confirm Password 
          <span class="password-wrapper">
            <input type="password" name="password" id="password_input" placeholder="Enter your password" required>
            <span class="toggle-password" onclick="togglePasswordVisibility()">
              <ion-icon name="eye-outline" id="toggleIcon"></ion-icon>
            </span>
          </span>
        </label>
        <button type="submit" name="update_username">Update Username</button>
      </form>
    </div>
  </main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Select all necessary elements for Profile and Logout
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    // ==========================================================
    // --- PROFILE MENU TOGGLE LOGIC (FIXED & MERGED) ---
    // ==========================================================
    if (profileIcon && profileMenu) {
        // Toggle profile menu on click
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide");
        });
        
        // Close menu when clicking elsewhere (using document listener)
        document.addEventListener("click", function (e) {
            // Check if the click was outside the icon and the menu
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    }

        window.togglePasswordVisibility = function() {
        const passwordInput = document.getElementById('password_input');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.setAttribute('name', 'eye-off-outline'); // Change icon to 'eye-off'
        } else {
            passwordInput.type = 'password';
            toggleIcon.setAttribute('name', 'eye-outline'); // Change icon back to 'eye'
        }
    }
    // ==========================================================
    // --- LOGOUT DIALOG LOGIC (FIXED) ---
    // ==========================================================
    // Make confirmLogout function globally accessible (called from the anchor tag in HTML)
    window.confirmLogout = function(e) { 
        if (e) e.preventDefault(); // FIX: Prevent the default anchor behavior (# in URL)
        if (logoutDialog) {
            logoutDialog.style.display = "flex";
        }
    }

    // FIX: Attach event listeners to the dialog buttons
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