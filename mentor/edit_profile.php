<?php
session_start();
require '../connection/db_connection.php';

// Check if a mentor is logged in
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'Mentor') {
    header("Location: ../login.php"); // Redirect to your main login page
    exit();
}

$updated = false;
$error = '';
$user = null; // Use a more generic variable name
$imageUploaded = false;

// Determine username from GET parameter or form submission
$username = $_GET['username'] ?? ($_POST['original_username'] ?? '');

if (empty($username)) {
    die("No username provided.");
}

// If form was submitted, process the update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_username = $_POST['original_username'] ?? '';
    $new_username = $_POST['username'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check if the main profile form was submitted
    if (isset($_POST['update_profile'])) {
        // Basic validation
        if (empty($new_username) || empty($first_name) || empty($last_name) || empty($email) || empty($contact_number)) {
            $error = "All fields except password are required.";
        } else {
            // Check if a new password was entered. If so, hash it.
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                // CHANGE: SQL query now updates the 'users' table with the new hashed password
                $sql = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, contact_number = ?, password = ? WHERE username = ? AND user_type = 'Mentor'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $new_username, $first_name, $last_name, $email, $contact_number, $hashed_password, $original_username);
            } else {
                // If password field is empty, do not update the password
                // CHANGE: SQL query now updates the 'users' table without touching the password column
                $sql = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE username = ? AND user_type = 'Mentor'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $new_username, $first_name, $last_name, $email, $contact_number, $original_username);
            }

            if ($stmt->execute()) {
                $updated = true;
                // Update session username if it was changed
                if ($new_username !== $_SESSION['username']) {
                    $_SESSION['username'] = $new_username;
                }
                $username = $new_username; // Re-fetch data with the new username
            } else {
                $error = "Error updating profile. The username might already be taken.";
            }
            $stmt->close();
        }
    }
    
    // Check if a profile image was uploaded
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($ext), $allowed)) {
            $error = "Error: Please select a valid image format (JPG, JPEG, PNG, GIF).";
        } elseif ($_FILES['profile_image']['size'] > 5000000) { // 5MB max
            $error = "Error: File size must be less than 5MB.";
        } else {
            $new_filename = '../uploads/' . uniqid('profile_') . '.' . $ext;
            
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $new_filename)) {
                // CHANGE: SQL query now updates the 'icon' column in the 'users' table
                $sql = "UPDATE users SET icon = ? WHERE username = ? AND user_type = 'Mentor'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $new_filename, $username);
                
                if ($stmt->execute()) {
                    $imageUploaded = true;
                    $_SESSION['mentor_icon'] = $new_filename; // Update session icon immediately
                } else {
                    $error = "Error updating profile image in the database.";
                }
                $stmt->close();
            } else {
                $error = "Error uploading image.";
            }
        }
    }
}

// Fetch the latest user data for display
// CHANGE: Query now selects from the 'users' table with updated column names
$sql = "SELECT username, first_name, last_name, dob, gender, email, contact_number, password, icon FROM users WHERE username = ? AND user_type = 'Mentor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("Mentor not found.");
}

// Set a default icon if one is not present
// CHANGE: Updated column name from 'Mentor_Icon' to 'icon'
$user['icon'] = !empty($user['icon']) ? $user['icon'] : '../uploads/img/default_pfp.png';

// The password field will be empty for editing unless a new one is typed.
$masked_password = "••••••••";

// CLEANUP: Removed redundant queries that re-fetched session data.
// Session variables like 'mentor_name' and 'mentor_icon' should be set on login or on the main dashboard page.
// This page will use the existing session variables for the layout.

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/edit-profile.css" />
  <link rel="stylesheet" href="css/navigation.css"/>
   <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Mentor Profile</title>
</head>
<body>
<nav>
<div class="nav-top">
    <div class="logo">
      <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
      <div class="logo-name">COACH</div>
    </div>

    <div class="admin-profile">
      <img src="<?php echo htmlspecialchars($_SESSION['mentor_icon'] ?? '../uploads/img/default_pfp.png'); ?>" alt="Mentor Profile Picture" />
      <div class="admin-text">
        <span class="admin-name">
          <?php echo htmlspecialchars($_SESSION['mentor_name'] ?? 'Mentor'); ?>
        </span>
        <span class="admin-role">Mentor</span>
      </div>
      <a href="edit_profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
        <ion-icon name="create-outline" class="verified-icon"></ion-icon>
      </a>
    </div>

  <div class="menu-items">
    <ul class="navLinks">
       <li class="navList">
        <a href="dashboard.php">
          <ion-icon name="home-outline"></ion-icon>
          <span class="links">Home</span>
        </a>
      </li>
      <li class="navList">
        <a href="courses.php">
          <ion-icon name="book-outline"></ion-icon>
          <span class="links">Course</span>
        </a>
      </li>
      <li class="navList">
        <a href="sessions.php">
          <ion-icon name="calendar-outline"></ion-icon>
          <span class="links">Sessions</span>
        </a>
      </li>
      <li class="navList">
        <a href="feedbacks.php">
          <ion-icon name="star-outline"></ion-icon>
          <span class="links">Feedbacks</span>
        </a>
      </li>
       <li class="navList">
        <a href="activities.php">
          <ion-icon name="clipboard"></ion-icon>
          <span class="links">Activities</span>
        </a>
      </li>
      <li class="navList">
        <a href="resource.php">
          <ion-icon name="library-outline"></ion-icon>
          <span class="links">Resource Library</span>
        </a>
      </li>
            <li class="navList">
        <a href="achievement.php">
          <ion-icon name="trophy-outline"></ion-icon>
          <span class="links">Achievement</span>
        </a>
      </li>
    </ul>

    <ul class="bottom-link">
      <li class="logout-link">
        <a href="#" onclick="confirmLogout(event)" style="color: white; text-decoration: none; font-size: 18px;">
          <ion-icon name="log-out-outline"></ion-icon>
          Logout
        </a>
      </li>
    </ul>
  </div>
</nav>

<section class="dashboard">
    <div class="top">
      <ion-icon class="navToggle" name="menu-outline"></ion-icon>
      <img src="../uploads/img/logo.png" alt="Logo"> </div>

<div class="main-content">
    <h1 class="page-title">Mentor Profile Settings</h1>

<div class="profile-container">
    <div class="profile-image-section">
        <div class="profile-img-container">
             <img src="<?= htmlspecialchars($user['icon']) ?>" class="profile-img" alt="Profile Picture" id="profileImage">
            <div class="edit-icon" onclick="document.getElementById('profileImageUpload').click()">
                <ion-icon name="camera"></ion-icon>
            </div>
        </div>
        
        <form id="imageUploadForm" method="post" action="edit_profile.php?username=<?= htmlspecialchars($user['username']) ?>" enctype="multipart/form-data">
            <input type="file" name="profile_image" id="profileImageUpload" class="hidden-file-input" accept="image/*" onchange="submitImageForm()">
             <input type="hidden" name="original_username" value="<?= htmlspecialchars($user['username']) ?>">
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

        <form method="post" action="edit_profile.php?username=<?= htmlspecialchars($user['username']) ?>" id="profileForm">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" class="disabled-input" readonly>
            </div>

            <div class="form-group">
                <label>First Name:</label>
                <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="disabled-input" readonly>
            </div>

            <div class="form-group">
                <label>Last Name:</label>
                <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="disabled-input" readonly>
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" class="disabled-input" readonly>
            </div>

            <div class="form-group">
                <label>Contact Number:</label>
                <input type="text" name="contact_number" id="contact_number" value="<?= htmlspecialchars($user['contact_number']) ?>" class="disabled-input" readonly>
            </div>

            <div class="form-group">
                <label>Date of Birth:</label>
                <input type="text" name="dob" id="dob" value="<?= htmlspecialchars($user['dob']) ?>" class="disabled-input" readonly disabled>
            </div>

            <div class="form-group">
                <label>Gender:</label>
                <input type="text" name="gender" id="gender" value="<?= htmlspecialchars($user['gender']) ?>" class="disabled-input" readonly disabled>
            </div>

            <div class="form-group">
                <label>New Password (leave blank to keep current):</label>
                <div class="password-container">
                    <input type="password" name="password" id="password" value="" placeholder="<?= $masked_password ?>" class="disabled-input" readonly>
                    <span class="toggle-password" onclick="togglePasswordVisibility()">
                        <ion-icon name="eye-outline"></ion-icon>
                    </span>
                </div>
            </div>

            <input type="hidden" name="original_username" value="<?= htmlspecialchars($user['username']) ?>">
            <input type="hidden" name="update_profile" value="1">
            <button type="button" id="editButton" class="action-btn" onclick="toggleEditMode()">Edit Profile</button>
            <button type="submit" id="updateButton" class="action-btn" style="display: none;">Update Profile</button>
        </form>
    </div>
</div>

</div>

<script src="js/admin.js"></script>
<script src="js/navigation.js"></script>
 <script>

function toggleEditMode() {
    const inputs = document.querySelectorAll('#profileForm .disabled-input');
    const editButton = document.getElementById('editButton');
    const updateButton = document.getElementById('updateButton');
    const passwordInput = document.getElementById('password');

    if (editButton.style.display !== 'none') {
        inputs.forEach(input => {
            if (!input.disabled) { // Don't enable DOB and Gender
                input.readOnly = false;
                input.classList.remove('disabled-input');
            }
        });
        
        // Clear the password field for security and show placeholder
        passwordInput.value = '';
        
        editButton.style.display = 'none';
        updateButton.style.display = 'inline-block';
    }
}


function togglePasswordVisibility() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-password ion-icon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.setAttribute('name', 'eye-off-outline');
    } else {
        passwordField.type = 'password';
        toggleIcon.setAttribute('name', 'eye-outline');
    }
}

function submitImageForm() {
    const fileInput = document.getElementById('profileImageUpload');
    const imagePreview = document.getElementById('profileImage');
    
    if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            // The form will now be submitted, and the page will reload with the new image.
            document.getElementById('imageUploadForm').submit();
        }
        
        reader.readAsDataURL(fileInput.files[0]);
    }
}
</script>

<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
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