<?php
session_start();
// Updated and more secure access control check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: ../login.php");
    exit();
}

require '../connection/db_connection.php';

$updated = false;
$error = '';
$user = null;
$imageUploaded = false;

// Determine the username from the URL or a form post
$username = $_GET['username'] ?? ($_POST['original_username'] ?? $_SESSION['username']);

if (empty($username)) {
    die("No username provided.");
}

// If the form was submitted, process the update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? ''; // Changed from admin_name
    $password_input = $_POST['password'] ?? '';
    $original_username = $_POST['original_username'] ?? '';

    // --- Process Profile Info Update ---
    if (isset($_POST['update_profile'])) {
        if (empty($new_username) || empty($full_name) || empty($password_input)) {
            $error = "All fields are required.";
        } else {
            // Split the full name into first and last names
            $name_parts = explode(' ', $full_name, 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

            // Check if the password has been changed
            $check_sql = "SELECT password FROM users WHERE username = ? AND user_type = 'Super Admin'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $original_username);
            $check_stmt->execute();
            $current_data = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            // If password has changed (or is not just dots), hash the new password
            if ($password_input !== "••••••••" && !password_verify($password_input, $current_data['password'])) {
                $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, first_name = ?, last_name = ?, password = ? WHERE username = ? AND user_type = 'Super Admin'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $new_username, $first_name, $last_name, $hashed_password, $original_username);
            } else {
                // Password hasn't changed, update other fields
                $sql = "UPDATE users SET username = ?, first_name = ?, last_name = ? WHERE username = ? AND user_type = 'Super Admin'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $new_username, $first_name, $last_name, $original_username);
            }

            if ($stmt->execute()) {
                $updated = true;
                $username = $new_username; // Use the new username for re-fetching data
                $_SESSION['username'] = $new_username; // Update session
            } else {
                $error = "Error updating profile.";
            }
            $stmt->close();
        }
    }
    
    // --- Process Image Upload ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        // (Image validation and upload logic remains the same)
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filesize = $_FILES['profile_image']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $error = "Error: Invalid image format (JPG, JPEG, PNG, GIF).";
        } elseif ($filesize > 5000000) { // 5MB max
            $error = "Error: File size must be less than 5MB.";
        } else {
            $new_filename = '../uploads/superadmin_' . uniqid() . '.' . $ext;
            if (!file_exists('uploads')) mkdir('uploads', 0777, true);
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $new_filename)) {
                // Update the 'icon' column in the 'users' table
                $sql = "UPDATE users SET icon = ? WHERE username = ? AND user_type = 'Super Admin'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $new_filename, $username);
                if ($stmt->execute()) {
                    $imageUploaded = true;
                } else {
                    $error = "Error updating profile image in database.";
                }
                $stmt->close();
            } else {
                $error = "Error uploading image.";
            }
        }
    }
}

// Fetch the latest Super Admin data from the 'users' table
$sql = "SELECT user_id, username, first_name, last_name, password, icon FROM users WHERE username = ? AND user_type = 'Super Admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("SuperAdmin not found.");
}

// Set default icon if one is not set
$user['icon'] = !empty($user['icon']) ? $user['icon'] : '../uploads/img/default_pfp.png';
$masked_password = "••••••••"; // Display password as masked for security

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/profile.css" />
  <link rel="stylesheet" href="css/navigation.css"/>
   <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>SuperAdmin Profile</title>
</head>
<body>
<nav>
    <div class="nav-top">
      <div class="logo">
        <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
        <div class="logo-name">COACH</div>
      </div>

      <div class="admin-profile">
        <img src="<?php echo htmlspecialchars($_SESSION['superadmin_icon']); ?>" alt="SuperAdmin Profile Picture" />
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
        <li class="navList">
          <a href="moderators.php">
            <ion-icon name="lock-closed-outline"></ion-icon>
            <span class="links">Moderators</span>
          </a>
        </li>
      </ul>

      <ul class="bottom-link">
        <li class="logout-link">
          <a href="#" onclick="confirmLogout(event)">
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
      <img src="../uploads/img/logo.png" alt="Logo">
    </div>

    <div class="main-content">
        <h1 class="page-title">SuperAdmin Profile Settings</h1>

        <div class="profile-container">
            <div class="profile-image-section">
                <div class="profile-img-container">
                    <img src="<?= htmlspecialchars($user['icon']) ?>" class="profile-img" alt="Profile Picture" id="profileImage">
                    <div class="edit-icon" onclick="document.getElementById('profileImageUpload').click()">
                        <ion-icon name="camera"></ion-icon>
                    </div>
                </div>
                
                <form id="imageUploadForm" method="post" action="profile.php" enctype="multipart/form-data">
                    <input type="file" name="profile_image" id="profileImageUpload" class="hidden-file-input" accept="image/*" onchange="submitImageForm()">
                    <input type="hidden" name="original_username" value="<?= htmlspecialchars($user['username']) ?>">
                </form>
                
                <?php if ($imageUploaded): ?>
                    <div class="image-upload-message">Profile image updated successfully!</div>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <?php if ($updated): ?>
                    <div class="message">Profile updated successfully!</div>
                <?php elseif (!empty($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="profile.php" id="profileForm">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" class="disabled-input" readonly>
                    </div>

                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>" class="disabled-input" readonly>
                    </div>

                    <div class="form-group">
                        <label>Password:</label>
                        <div class="password-container">
                            <input type="password" name="password" id="password" value="<?= $masked_password ?>" class="disabled-input" readonly>
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
</section>
<script src="js/navigation.js"></script>
<script>


function toggleEditMode() {
    const editButton = document.getElementById('editButton');
    const updateButton = document.getElementById('updateButton');
    const username = document.getElementById('username');
    const full_name = document.getElementById('full_name'); // CORRECTED
    const password = document.getElementById('password');
    
    // Toggle between edit and update modes
    if (editButton.textContent === 'Edit Profile') {
        // Enable editing
        username.readOnly = false;
        full_name.readOnly = false; // CORRECTED
        password.readOnly = false;
        
        username.classList.remove('disabled-input');
        full_name.classList.remove('disabled-input'); // CORRECTED
        password.classList.remove('disabled-input');
        
        // Clear the password field for security
        password.value = '';
        
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
    // Show a preview of the selected image before submitting
    const fileInput = document.getElementById('profileImageUpload');
    const imagePreview = document.getElementById('profileImage');
    
    if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            
            // Submit the form after a short delay to show the preview
            setTimeout(function() {
                document.getElementById('imageUploadForm').submit();
            }, 500);
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
