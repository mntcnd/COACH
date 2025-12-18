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
$username = $_SESSION['username'];

// Prepare SQL statement to prevent SQL injection
$sql = "SELECT first_name, last_name, username, dob, gender, email, email_verification, contact_number, contact_verification, icon 
        FROM users 
        WHERE username = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['first_name'] . " " . $row['last_name'];
    $username = $row['username'];
    $dob = $row['dob'];
    $gender = $row['gender'];
    $email = $row['email'];
    $email_verification = $row['email_verification'];
    $contact = $row['contact_number'];
    $mobile_verification = $row['contact_verification'];
    $profile_picture = $row['icon'];
} else {
    echo "Error: User profile not found";
    exit();
}

$stmt->close();

// Function to display verification status
function getVerificationStatus($status) {
  if (is_null($status) || strtolower($status) === 'pending' || $status == 0) {
      return '<span class="pending">Pending</span>';
  } elseif (strtolower($status) === 'active' || $status == 1) {
      return '<span class="active">Active</span>';
  } else {
      return '<span class="pending">Pending</span>';
  }
}

// Function to get profile picture path
function getProfilePicture($profile_picture) {
    // Check if profile picture exists and is not empty
    if (!empty($profile_picture) && file_exists($profile_picture)) {
        return $profile_picture;
    }
    // Return default profile picture
    return "../uploads/img/default_pfp.png";
}

// Get the correct profile picture path (use this after fetching user data)
$profile_picture_path = getProfilePicture($profile_picture);

// Fetch first name and mentee icon again
$firstName = '';
$menteeIcon = '';

$sql = "SELECT first_name, icon FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $firstName = $row['first_name'];
    $menteeIcon = $row['icon'];
}

$stmt->close();
$conn->close(); // Close the connection only after all queries are done
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/view-profile.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>My Profile</title>
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
      <li><a href="#" onclick="confirmLogout(event)">Logout</a></li>
    </ul>
  </div>
</div>
        </nav>
    </section>


    <main class="profile-container" style ="margin-top: 200px;">
    <nav class="tabs">
      <button class="active" onclick="window.location.href='profile.php'">Profile</button>
      <button onclick="window.location.href='edit-profile.php'">Edit Profile</button>
      <button onclick="window.location.href='verify-email.php'">Edit Email</button>
      <button onclick="window.location.href='verify-phone.php'">Edit Phone</button>
      <button onclick="window.location.href='edit-username.php'">Edit Username</button>
      <button onclick="window.location.href='reset-password.php'">Reset Password</button>
    </nav>
 
<section class="profile-card">
  <div class="profile-left">
    <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" 
         alt="Profile Picture" 
         style="object-fit: cover;"
         onerror="this.src='../uploads/img/default_pfp.png';" />
    <h2><?php echo htmlspecialchars($name); ?></h2>
    <p><?php echo htmlspecialchars($username); ?></p>
  </div>


        <div class="profile-right">
          <div class="info-row"><span>Name</span><span><?php echo $name; ?></span></div>
          <div class="info-row"><span>Username</span><span><?php echo $username; ?></span></div>
          <div class="info-row"><span>Date of Birth</span><span><?php echo $dob; ?></span></div>
          <div class="info-row"><span>Gender</span><span><?php echo $gender; ?></span></div>
          <div class="info-row"><span>Email</span><span><?php echo $email; ?></span></div>
          <div class="info-row"><span>Contact</span><span><?php echo $contact; ?></span></div>
        </div>
      </section>
    </main>

<script src="mentee.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Select all necessary elements for Profile and Logout
    const profileIcon = document.getElementById('profile-icon');
    const profileMenu = document.getElementById('profile-menu');
    // NEW/MODIFIED: Select logout dialog elements
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

    // --- Profile Menu Toggle Logic (PRESERVED/FIXED) ---
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener('click', function(e) {
            e.preventDefault();
            // Using toggle('show') and toggle('hide') for consistency
            profileMenu.classList.toggle('show');
            profileMenu.classList.toggle('hide');
        });
        
        // Close menu when clicking elsewhere
        document.addEventListener('click', function(e) {
            // Check if click is outside both the icon and the menu
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove('show');
                profileMenu.classList.add('hide');
            }
        });
    }

    // --- Logout Dialog Logic (NEW) ---
    
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