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

// Variable to store messages
$message = "";
$messageType = "";

// Handle form submission for profile updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Get form data
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    // REMOVED: email and contact_number from update logic
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    
    // Prepare SQL statement to update user data (Email and Contact Number removed)
    $update_sql = "UPDATE users SET 
                   first_name = ?, 
                   last_name = ?, 
                   dob = ?, 
                   gender = ? 
                   WHERE username = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    // REMOVED: $email and $contact_number from bind_param
    $update_stmt->bind_param("sssss", $first_name, $last_name, $dob, $gender, $username);
    
    if ($update_stmt->execute()) {
        $message = "Profile updated successfully!";
        $messageType = "success";
    } else {
        $message = "Error updating profile: " . $conn->error;
        $messageType = "error";
    }
    
    $update_stmt->close();
}

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_profile_pic'])) {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $filesize = $_FILES['profile_pic']['size'];
        
        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), $allowed)) {
            $message = "Error: Please upload an image file (jpg, jpeg, png, gif)";
            $messageType = "error";
        } else {
            // Check filesize (limit to 5MB)
            if ($filesize > 5 * 1024 * 1024) {
                $message = "Error: File size exceeds the limit (5MB)";
                $messageType = "error";
            } else {
                // Define the correct upload directory path
                $upload_dir = '../uploads/';
                
                // Create a unique filename
                $new_filename_relative = "profile_" . $username . "_" . time() . "." . $ext;
                $new_filename_full_path = $upload_dir . $new_filename_relative;
                
                // Make sure the uploads directory exists
                if (!file_exists($upload_dir)) {
                    // Use recursive true
                    if (!mkdir($upload_dir, 0777, true)) {
                        $message = "Error: Could not create upload directory.";
                        $messageType = "error";
                    }
                }
                
                // Move the uploaded file
                if ($messageType !== "error" && move_uploaded_file($_FILES['profile_pic']['tmp_name'], $new_filename_full_path)) {
                    // Update the database with the new profile picture path
                    // Store path relative to the root/project folder
                    $update_pic_sql = "UPDATE users SET icon = ? WHERE username = ?";
                    $update_pic_stmt = $conn->prepare($update_pic_sql);
                    
                    // The path saved in the DB should be relative to the file accessing it or the root. 
                    // Keeping the original logic of saving "../uploads/..." 
                    $db_path = $upload_dir . $new_filename_relative; 
                    
                    $update_pic_stmt->bind_param("ss", $db_path, $username);
                    
                    if ($update_pic_stmt->execute()) {
                        $message = "Profile picture updated successfully!";
                        $messageType = "success";
                        // Update session/local variable to reflect change immediately
                        $profile_picture = $db_path; 
                    } else {
                        $message = "Error updating profile picture in database: " . $conn->error;
                        $messageType = "error";
                    }
                    
                    $update_pic_stmt->close();
                } else if ($messageType !== "error") {
                    $message = "Error uploading file";
                    $messageType = "error";
                }
            }
        }
    } else if (isset($_POST['upload_profile_pic'])) { // Only show error if the form was actually submitted
        $message = "Error: No file uploaded or an error occurred. Error code: " . $_FILES['profile_pic']['error'];
        $messageType = "error";
    }
}

// Fetch current user data
// REMOVED: email, contact_number from select query
$sql = "SELECT first_name, last_name, username, dob, gender, icon 
        FROM users 
        WHERE username = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Fetch data
    $row = $result->fetch_assoc();
    
    // Assign values to variables
    $first_name = $row['first_name'];
    $last_name = $row['last_name'];
    $name = $first_name . " " . $last_name;
    $username = $row['username'];
    $dob = $row['dob'];
    $gender = $row['gender'];
    // REMOVED: $email and $contact
    $profile_picture = $row['icon'];
    
} else {
    // No user found with that username
    // Use an error page or simple message instead of raw echo
    error_log("User profile not found for username: " . $username);
    header("Location: error.php?code=404"); // Redirect to a generic error page
    exit();
}

// Fetch first name and mentee icon again (Can be consolidated with above query but preserved structure for minimal change)
$firstName = $first_name;
$menteeIcon = $profile_picture;

// Close main statement and connection only if they are open
if (isset($stmt) && $stmt->close() && isset($conn)) {
    $conn->close();
}


// Function to get profile picture path
function getProfilePicture($profile_picture) {
    if ($profile_picture && !empty($profile_picture)) {
        return $profile_picture; // Return the path stored in the database
    } else {
        return "../uploads/img/default_pfp.png"; // Return the default image path (Updated path for consistency)
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
    <link rel="stylesheet" href="css/edit-profile.css" />
    <link rel="stylesheet" href="css/message.css" />
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <title>Edit Profile</title>
</head>
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
                        <img src="<?php echo htmlspecialchars($menteeIcon); ?>" alt="User Icon" style="width: 35px; height: 35px; border-radius: 50%;" onerror="this.src='../uploads/img/default_pfp.png';">
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
                                <img src="<?php echo htmlspecialchars($menteeIcon); ?>" alt="User Icon" style="width: 40px; height: 40px; border-radius: 50%;" onerror="this.src='../uploads/img/default_pfp.png';">
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

    <main class="profile-container">
        <nav class="tabs">
            <button onclick="window.location.href='profile.php'">Profile</button>
            <button class="active" onclick="window.location.href='edit-profile.php'">Edit Profile</button>
            <button onclick="window.location.href='verify-email.php'">Edit Email</button>
            <button onclick="window.location.href='verify-phone.php'">Edit Phone</button>
            <button onclick="window.location.href='edit-username.php'">Edit Username</button>
            <button onclick="window.location.href='reset-password.php'">Reset Password</button>
        </nav>

        <div class="container">
            <h2>Edit Profile</h2>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-image-section">
                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Profile Picture" id="profilePreview" onerror="this.src='../uploads/img/default_pfp.png';" />
                
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <label for="profile_pic" class="upload-btn">Choose Profile Picture</label>
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/*" hidden onchange="previewImageAndSubmit(this)">
                    <input type="submit" name="upload_profile_pic" id="submit-pic" hidden>
                </form>
            </div>

            <form method="POST" id="profile-edit-form">
                <button type="button" id="edit-save-btn" class="edit-btn" style="background-color: #693b69; color: white; border: 1px solid #693b69; 
                border-radius: 6px; padding: 8px 18px; font-size: 14px; cursor: pointer; margin-left: 800px; margin-top: 10px; margin-bottom: 20px;">Edit Profile</button><br>
                <label>First Name <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required disabled></label>
                <label>Last Name <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required disabled></label>
                <label>Date of Birth <input type="date" name="dob" value="<?php echo htmlspecialchars($dob); ?>" required disabled></label>
                <label>Gender
                    <select name="gender" required disabled>
                        <option value="Male" <?php if ($gender == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if ($gender == 'Female') echo 'selected'; ?>>Female</option>
                        <option value="Other" <?php if ($gender == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </label>
                <button type="submit" name="update_profile" id="hidden-submit-btn" style="display: none;">Save Changes</button>
            </form>
        </div>
    </main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Select all necessary elements for Profile and Logout
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    // Removed direct reference to logoutDialog elements as it's defined globally

    // NEW ELEMENTS FOR EDIT/SAVE FUNCTIONALITY
    const editSaveBtn = document.getElementById("edit-save-btn");
    const profileForm = document.getElementById("profile-edit-form");
    const formFields = profileForm.querySelectorAll('input, select');
    const hiddenSubmitBtn = document.getElementById("hidden-submit-btn");

    // Function to set the disabled state of all form fields
    function setFormFieldsDisabled(disabledState) {
        formFields.forEach(field => {
            field.disabled = disabledState;
        });
    }

    // Initialize: Ensure fields are disabled on page load
    setFormFieldsDisabled(true); 

    // Add event listener to the main Edit/Save button
    if (editSaveBtn) {
        editSaveBtn.addEventListener("click", function(e) {
            e.preventDefault(); // Prevent default button behavior

            if (editSaveBtn.textContent === "Edit Profile") {
                // Change to Edit Mode
                editSaveBtn.textContent = "Save Changes";
                editSaveBtn.classList.add("save-btn"); // Optional: Add a class for different styling
                editSaveBtn.classList.remove("edit-btn");
                setFormFieldsDisabled(false);
            } else {
                // Change to Save Mode - Submit the form
                // Trigger the hidden submit button to post the form data
                hiddenSubmitBtn.click();
            }
        });
    }

    // ==========================================================
    // --- PROFILE MENU TOGGLE LOGIC (FIXED & MERGED) ---
    // ==========================================================
    if (profileIcon && profileMenu) {
        // Toggle profile menu on click
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide"); // Ensure toggle works consistently
        });
        
        // Close menu when clicking elsewhere (using window/document listener)
        document.addEventListener("click", function (e) {
            // Use e.target.closest('#profile-menu') to check if the click was inside the menu itself
            // Also check if the click was on the icon itself
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    }

    // ==========================================================
    // --- LOGOUT DIALOG LOGIC (FIXED) ---
    // ==========================================================
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

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
    
    // ==========================================================
    // --- ORIGINAL IMAGE PREVIEW LOGIC (PRESERVED) ---
    // ==========================================================
    // Note: The function is defined globally so it can be called from the HTML input element's onchange attribute.
    window.previewImageAndSubmit = function(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
                // Auto-submit the form when a file is selected
                document.getElementById('submit-pic').click();
            }
            
            reader.readAsDataURL(input.files[0]);
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