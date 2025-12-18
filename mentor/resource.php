<?php

// START: Temporary Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// PHP configuration for file uploads
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '55M');
ini_set('max_execution_time', 300); // 5 minutes for large file uploads
ini_set('memory_limit', '256M');

session_start();
require '../connection/db_connection.php';

// Create error log directory if it doesn't exist
$error_log_dir = '../error_logs';
if (!is_dir($error_log_dir)) {
    mkdir($error_log_dir, 0755, true);
}

// File size limit constant (50MB in bytes)
define('MAX_FILE_SIZE', 50 * 1024 * 1024);

// Function to format file size for display
function formatFileSize($bytes) {
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// SESSION CHECK: Updated to use user_id and user_type from the 'users' table
// This assumes your login script now sets $_SESSION['user_id'] and $_SESSION['user_type']
if (!isset($_SESSION['user_id']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'Mentor')) {
  // Redirect to a unified login page if not logged in or not a mentor
  header("Location: ../login.php");
  exit();
}

// DELETE resource: This logic remains largely the same as it relies on Resource_ID
if (isset($_GET['delete_resource'])) {
  $deleteResourceID = $_GET['delete_resource'];

  // First, get the file names to delete them from the server
  $sql_fetch_files = "SELECT Resource_Icon, Resource_File FROM resources WHERE Resource_ID = ?";
  $stmt_fetch = $conn->prepare($sql_fetch_files);
  $stmt_fetch->bind_param("i", $deleteResourceID);
  $stmt_fetch->execute();
  $result_fetch = $stmt_fetch->get_result();
  $files_to_delete = $result_fetch->fetch_assoc();
  $stmt_fetch->close();

  // Then, delete the record from the database
  $stmt = $conn->prepare("DELETE FROM resources WHERE Resource_ID = ?");
  $stmt->bind_param("i", $deleteResourceID);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    // If database deletion is successful, delete the actual files
    if ($files_to_delete) {
      $icon_path = "../uploads/" . $files_to_delete['Resource_Icon'];
      $file_path = "../uploads/" . $files_to_delete['Resource_File'];
      if (!empty($files_to_delete['Resource_Icon']) && file_exists($icon_path)) {
        unlink($icon_path);
      }
      if (!empty($files_to_delete['Resource_File']) && file_exists($file_path)) {
        unlink($file_path);
      }
    }
    // REFACTORED SUCCESS MESSAGE
    $_SESSION['popup_type'] = 'success';
    $_SESSION['popup_title'] = 'Deletion Success';
    $_SESSION['popup_body'] = 'Resource successfully deleted!';
    header("Location: resource.php");
    exit();
  } else {
    // REFACTORED ERROR MESSAGE
    $_SESSION['popup_type'] = 'error';
    $_SESSION['popup_title'] = 'Deletion Error';
    $_SESSION['popup_body'] = 'Error: Resource not found or could not be deleted.';
    header("Location: resource.php");
    exit();
  }
  $stmt->close();
}

// CREATE resource with file size validation: Updated to align with the 'users' table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
  $user_id = $_SESSION['user_id']; // Use the user_id from the session

  // Get the mentor's full name from the 'users' table to store in 'UploadedBy'
  $getMentor = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
  $getMentor->bind_param("i", $user_id);
  $getMentor->execute();
  $mentorResult = $getMentor->get_result();
  if ($mentorResult->num_rows === 1) {
    $mentor = $mentorResult->fetch_assoc();
    $uploadedBy = $mentor['first_name'] . ' ' . $mentor['last_name'];
  } else {
    // Fallback name if user not found
    $uploadedBy = "Unknown Mentor";
  }
  $getMentor->close();

  // Retrieve form data
  $title = $_POST['resource_title'];
  $type = $_POST['resource_type'];
  $category = $_POST['resource_category'];

  // Check file size first before processing
  $fileSizeError = false;
  $errorMessage = '';
  
  // Check icon file size
  if (isset($_FILES['resource_icon']) && $_FILES['resource_icon']['error'] === UPLOAD_ERR_OK) {
      if ($_FILES['resource_icon']['size'] > MAX_FILE_SIZE) {
          $fileSizeError = true;
          $actualSize = formatFileSize($_FILES['resource_icon']['size']);
          $maxSize = formatFileSize(MAX_FILE_SIZE);
          $errorMessage .= "Icon file size ($actualSize) exceeds the maximum limit of $maxSize. ";
      }
  }
  
  // Check resource file size
  if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
      if ($_FILES['resource_file']['size'] > MAX_FILE_SIZE) {
          $fileSizeError = true;
          $actualSize = formatFileSize($_FILES['resource_file']['size']);
          $maxSize = formatFileSize(MAX_FILE_SIZE);
          $errorMessage .= "Resource file size ($actualSize) exceeds the maximum limit of $maxSize. ";
      }
  }
  
  // If file size error, set message and stop processing
  if ($fileSizeError) {
    // REFACTORED FILE SIZE ERROR
    $_SESSION['popup_type'] = 'error';
    $_SESSION['popup_title'] = 'Upload Error: Files Too Large';
    $_SESSION['popup_body'] = "Upload Error: " . trim($errorMessage) . "\n\nPlease compress your files or choose smaller files.";
    header("Location: resource.php");
    exit();
  }

  // Handle icon file upload
  $icon = null;
  if (isset($_FILES['resource_icon']) && $_FILES['resource_icon']['error'] === UPLOAD_ERR_OK) {
    $icon_ext = strtolower(pathinfo($_FILES["resource_icon"]["name"], PATHINFO_EXTENSION));
    $icon_name = uniqid('icon_') . '.' . $icon_ext;
    $icon_target_path = "../uploads/" . $icon_name;
    if (move_uploaded_file($_FILES["resource_icon"]["tmp_name"], $icon_target_path)) {
      $icon = $icon_name;
    } else {
      // REFACTORED ICON UPLOAD ERROR
      $_SESSION['popup_type'] = 'error';
      $_SESSION['popup_title'] = 'Upload Error';
      $_SESSION['popup_body'] = 'Error uploading icon file.';
      header("Location: resource.php");
      exit();
    }
  }

  // Handle resource file upload
  $fileName = null;
  if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['resource_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = uniqid('file_') . '.' . $file_ext;
    $targetDir = "../uploads/";
    $targetPath = $targetDir . $fileName;

    if (!file_exists($targetDir)) {
      mkdir($targetDir, 0777, true);
    }

    if (move_uploaded_file($file["tmp_name"], $targetPath)) {
      // UPDATED INSERT statement: uses 'user_id' instead of 'Applicant_Username'
      $stmt = $conn->prepare("INSERT INTO resources (user_id, UploadedBy, Resource_Title, Resource_Icon, Resource_Type, Category, Resource_File, Status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Under Review')");
      // UPDATED bind_param: 'i' for integer user_id
      $stmt->bind_param("issssss", $user_id, $uploadedBy, $title, $icon, $type, $category, $fileName);

      if ($stmt->execute()) {
        // REFACTORED INSERT SUCCESS
        $_SESSION['popup_type'] = 'success';
        $_SESSION['popup_title'] = 'Resource Uploaded';
        $_SESSION['popup_body'] = 'Resource successfully uploaded and submitted for review!';
        header("Location: resource.php");
        exit();
      } else {
        // REFACTORED INSERT ERROR
        $_SESSION['popup_type'] = 'error';
        $_SESSION['popup_title'] = 'Database Error';
        $_SESSION['popup_body'] = "Error uploading resource: " . $stmt->error;
        header("Location: resource.php");
        exit();
      }
      $stmt->close();
    } else {
      // REFACTORED MOVE FILE ERROR
      $_SESSION['popup_type'] = 'error';
      $_SESSION['popup_title'] = 'File Transfer Error';
      $_SESSION['popup_body'] = 'Error moving uploaded file. Check directory permissions.';
      header("Location: resource.php");
      exit();
    }
  } else {
    // REFACTORED UPLOAD FAILED ERROR
    $_SESSION['popup_type'] = 'error';
    $_SESSION['popup_title'] = 'Upload Failed';
    $_SESSION['popup_body'] = 'Resource file upload failed. The file may be corrupt or missing.';
    header("Location: resource.php");
    exit();
  }
}

// FETCH resources: Updated to use 'user_id'
$resources = [];
if (isset($_SESSION['user_id'])) {
  $user_id = $_SESSION['user_id'];
  // UPDATED SELECT query to filter resources by the logged-in mentor's user_id
  $res = $conn->prepare("SELECT * FROM resources WHERE user_id = ? ORDER BY Resource_ID DESC");

  if ($res) {
    $res->bind_param("i", $user_id); // 'i' for integer
    if ($res->execute()) {
      $result = $res->get_result();
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $resources[] = $row;
        }
      }
    }
    $res->close();
  }
} else {
  error_log("Session variable 'user_id' is not set.");
}

// FETCH mentor details for navbar: Updated for the 'users' table
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // UPDATED query to get name, icon, and username from the 'users' table
    $sql = "SELECT username, CONCAT(first_name, ' ', last_name) AS mentor_name, icon FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $_SESSION['mentor_name'] = $row['mentor_name'];
        $_SESSION['username'] = $row['username']; // Stored for use in profile links
        // The icon column in 'users' table is named 'icon'
        $_SESSION['mentor_icon'] = !empty($row['icon']) ? $row['icon'] : "../uploads/img/default_pfp.png";
    } else {
        // Set default values if user is not found
        $_SESSION['mentor_name'] = "Unknown Mentor";
        $_SESSION['username'] = "unknown";
        $_SESSION['mentor_icon'] = "../uploads/img/default_pfp.png";
    }
    $stmt->close();
}
$conn->close();

// PHP block to pass session data to JavaScript and clear session
$popup_data = null;
if (isset($_SESSION['popup_type'])) {
    // Pass session data to a PHP array
    $popup_data = [
        'type' => htmlspecialchars($_SESSION['popup_type']),
        'title' => htmlspecialchars($_SESSION['popup_title']),
        'body' => htmlspecialchars($_SESSION['popup_body'])
    ];
    // Clear the session variables after retrieval
    unset($_SESSION['popup_type']);
    unset($_SESSION['popup_title']);
    unset($_SESSION['popup_body']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/resources.css" /> 
  <link rel="stylesheet" href="css/home.css" />
  <link rel="stylesheet" href="../superadmin/css/clock.css" />
  <link rel="stylesheet" href="css/navigation.css"/>
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Mentor Dashboard</title>
  <style>
    /* File Size Warning CSS */
    .size-warning {
        color: #e74c3c !important;
        font-size: 12px !important;
        margin-top: 5px !important;
        padding: 8px !important;
        background-color: #fdf2f2 !important;
        border: 1px solid #e74c3c !important;
        border-radius: 4px !important;
        display: flex !important;
        align-items: center !important;
        gap: 5px !important;
        animation: fadeIn 0.3s ease-in-out !important;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Style for file input labels to show file size limit */
    label[for="resourceIcon"]::after,
    label[for="resourceFile"]::after {
        content: " (Max: 50MB)";
        color: #666;
        font-size: 12px;
        font-weight: normal;
    }

    /* Success state for valid files */
    .file-valid {
        color: #27ae60 !important;
    }

    /* === General Dialog Styles (NEW CODE) === */
    .general-dialog {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: none; /* Hidden by default */
        justify-content: center;
        align-items: center;
        z-index: 1000; /* Above everything else */
    }

    .dialog-content {
        background: var(--bg-color, #ffffff);
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        max-width: 400px;
        width: 90%;
        text-align: center;
        color: var(--text-color);
        animation: fadeInScale 0.2s ease-out;
    }

    /* Specific styles for success/error states */
    .dialog-content.success {
        border-left: 5px solid #27ae60;
    }
    .dialog-content.error {
        border-left: 5px solid #e74c3c;
    }

    .dialog-content h3 {
        margin-top: 0;
        font-size: 20px;
        font-weight: bold;
        color: var(--title-icon-color);
    }

    .dialog-content p {
        margin: 15px 0 25px;
        font-size: 14px;
        white-space: pre-wrap; /* Allows line breaks from PHP content */
    }

    .dialog-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    #closeMessageDialogBtn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        transition: opacity 0.2s ease;
        width: 100%; /* Make button full width for single button */
    }

    /* General OK button appearance */
    #closeMessageDialogBtn.error-btn {
        background-color: #e74c3c;
        color: #ffffff;
    }
    #closeMessageDialogBtn.success-btn {
        background-color: #27ae60;
        color: #ffffff;
    }

    #closeMessageDialogBtn:hover {
        opacity: 0.8;
    }
    
    @keyframes fadeInScale {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
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
      <img src="<?php echo htmlspecialchars($_SESSION['mentor_icon']); ?>" alt="Mentor Profile Picture" />
      <div class="admin-text">
        <span class="admin-name">
          <?php echo htmlspecialchars($_SESSION['mentor_name']); ?>
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
        <a href="#" onclick="window.location='dashboard.php'">
          <ion-icon name="home-outline"></ion-icon>
          <span class="links">Home</span>
        </a>
      </li>
      <li class="navList">
        <a href="#" onclick="window.location='courses.php'">
          <ion-icon name="book-outline"></ion-icon>
          <span class="links">Course</span>
        </a>
      </li>
      <li class="navList">
        <a href="#" onclick="window.location='sessions.php'">
          <ion-icon name="calendar-outline"></ion-icon>
          <span class="links">Sessions</span>
        </a>
      </li>
      <li class="navList">
        <a href="#" onclick="window.location='feedbacks.php'">
          <ion-icon name="star-outline"></ion-icon>
          <span class="links">Feedbacks</span>
        </a>
      </li>
      <li class="navList">
        <a href="#" onclick="window.location='activities.php'">
          <ion-icon name="clipboard"></ion-icon>
          <span class="links">Activities</span>
        </a>
      </li>
      <li class="navList active">
        <a href="#" onclick="window.location='resource.php'">
          <ion-icon name="library-outline"></ion-icon>
          <span class="links">Resource Library</span>
        </a>
      </li>
      <li class="navList">
        <a href="#" onclick="window.location='achievement.php'">
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

     <div id="resourceLibraryContent" style="padding: 50px;">
        <h1 class="section-title" id="resourceTitle" style="display: none;">Manage Resource Library</h1>

    <h1 class="section-title" id="resourceTitle">Manage Resource Library</h1>
    <div id="addResourceSection" style="padding: 20px; display: flex; flex-wrap: wrap; gap: 20px;">
        <div class="form-container" style="flex: 1; min-width: 300px;">
            <h1>ADD A NEW RESOURCE</h1>
            <form method="POST" enctype="multipart/form-data" id="resourceForm">

              <label for="resourceTitleInput">Resource Title</label>
              <input type="text" id="resourceTitleInput" name="resource_title" placeholder="Enter Resource Title" required />

              <label for="resourceType">Resource Type</label>
              <select id="resourceType" name="resource_type" required>
                <option value="">Select Type</option>
                <option value="Video">Video</option>
                <option value="PDF">PDF</option>
                <option value="PPT">PPT</option>
              </select>

              <label for="resourceCategory">Category</label>
              <select id="resourceCategory" name="resource_category" required>
                <option value="">Select Category</option>
                <option value="all">All</option>
                <option value="IT">Information Technology</option>
                <option value="CS">Computer Science</option>
                <option value="DS">Data Science</option>
                <option value="GD">Game Development</option>
                <option value="DAT">Digital Animation</option>
              </select>

              <label for="resourceIcon">Resource Icon/Image</label>
              <input type="file" id="resourceIcon" name="resource_icon" accept="image/*" />

              <label for="resourceFile">Upload Resource File</label>
              <input type="file" id="resourceFile" name="resource_file" accept=".pdf,.ppt,.pptx,.mp4,.avi,.mov,.wmv" required />

              <button type="submit" name="submit">SUBMIT</button>
            </form>
        </div>

        <div class="preview-container" style="flex: 1; min-width: 300px;">
            <h1>Preview</h1>
            <div class="resource-card" id="resourcePreview">
              <img src="" id="resourcePreviewImage" alt="Resource Icon Preview" style="display:none; max-width: 100%; height: auto; margin-bottom: 10px;" />
              <h2 id="resourcePreviewTitle">Resource Title</h2>
              <p><strong>Type:</strong> <span id="resourcePreviewType">Resource Type</span></p>
              <p id="resourceFileName" style="font-style: italic; color: #555;">No file selected</p>
              <button class="choose-btn">View</button>
            </div>
        </div>
    </div>

<h1 class="section-title">All Resources</h1>

<div class="button-wrapper">
<div id="categoryButtons" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
    <button class="category-btn active" data-category="all">All</button>
    <button class="category-btn" data-category="IT">Information Technology</button>
    <button class="category-btn" data-category="CS">Computer Science</button>
    <button class="category-btn" data-category="DS">Data Science</button>
    <button class="category-btn" data-category="GD">Game Development</button>
    <button class="category-btn" data-category="DAT">Digital Animation</button>
    <button class="category-btn" data-category="Approved">Approved</button>
    <button class="category-btn" data-category="Under Review">Under Review</button>
    <button class="category-btn" data-category="Rejected">Rejected</button>
</div>
</div>

<div id="submittedResources" style="padding: 20px; display: flex; flex-wrap: wrap; gap: 20px;">
  <?php if (empty($resources)): ?>
    <p>No resources found.</p>
  <?php else: ?>
    <?php foreach ($resources as $resource): ?>
      <div class="resource-card"
           data-category="<?php echo htmlspecialchars($resource['Category']); ?>"
           data-status="<?php echo htmlspecialchars($resource['Status']); ?>"
           style="border: 1px solid #eee; padding: 15px; border-radius: 8px; flex: 1; min-width: 250px; max-width: 300px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        
        <?php if (!empty($resource['Resource_Icon']) && file_exists("../uploads/" . $resource['Resource_Icon'])): ?>
          <img src="../uploads/<?php echo htmlspecialchars($resource['Resource_Icon']); ?>" alt="Resource Icon" style="max-width: 100%; height: auto; margin-bottom: 10px; border-radius: 4px;" />
        <?php else: ?>
          <div style="height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999; margin-bottom: 10px; border-radius: 4px;">No Image</div>
        <?php endif; ?>

        <h2><?php echo htmlspecialchars($resource['Resource_Title']); ?></h2>
        <p><strong>Type:</strong> <?php echo htmlspecialchars($resource['Resource_Type']); ?></p>
        
        <?php if (!empty($resource['Resource_File']) && file_exists("../uploads/" . $resource['Resource_File'])): ?>
            <p><strong>File:</strong> <a href="view_resource.php?file=<?php echo urlencode($resource['Resource_File']); ?>&title=<?php echo urlencode($resource['Resource_Title']); ?>" target="_blank" class="view-button">View</a></p>
        <?php else: ?>
             <p><strong>File:</strong> No file uploaded or file not found</p>
        <?php endif; ?>

        <?php 
          $status = htmlspecialchars($resource['Status']); 
          $statusClass = strtolower($status); // approved, rejected, pending
        ?>
        <p class="status-label <?php echo $statusClass; ?>">
          <strong>STATUS:</strong> <span class="status"><?php echo $status; ?></span>
        </p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
<script src="js/navigation.js"></script>
  <script>
// File size limit (50MB in bytes)
const MAX_FILE_SIZE = 50 * 1024 * 1024;

// Function to format file size for display
function formatFileSize(bytes) {
    if (bytes >= 1024 * 1024) {
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return bytes + ' bytes';
    }
}

// Function to check file size and show warning
function checkFileSize(fileInput, fileType = 'File') {
    const file = fileInput.files[0];
    const warningElement = fileInput.nextElementSibling?.classList.contains('size-warning') ? 
                          fileInput.nextElementSibling : null;
    
    // Remove existing warning
    if (warningElement) {
        warningElement.remove();
    }
    
    if (file && file.size > MAX_FILE_SIZE) {
        // Create warning message
        const warning = document.createElement('div');
        warning.className = 'size-warning';
        warning.style.cssText = `
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            padding: 8px;
            background-color: #fdf2f2;
            border: 1px solid #e74c3c;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        `;
        warning.innerHTML = `
            <span style="font-weight: bold;">⚠️ File too large!</span>
            ${fileType} size: ${formatFileSize(file.size)} 
            (Max: ${formatFileSize(MAX_FILE_SIZE)})
        `;
        
        // Insert warning after the file input
        fileInput.parentNode.insertBefore(warning, fileInput.nextSibling);
        
        // Clear the file input
        fileInput.value = '';
        
        // Also update the preview
        if (fileType === 'Resource file') {
            const resFileName = document.getElementById("resourceFileName");
            if (resFileName) {
                resFileName.textContent = "File too large - please select a smaller file";
                resFileName.style.color = '#e74c3c';
            }
        }
        
        return false;
    } else if (file) {
        // File is within limit, update preview normally
        if (fileType === 'Resource file') {
            const resFileName = document.getElementById("resourceFileName");
            if (resFileName) {
                resFileName.textContent = file.name + ` (${formatFileSize(file.size)})`;
                resFileName.style.color = '#555';
            }
        }
        return true;
    }
    
    return true;
}

// --- NEW GENERAL MESSAGE DIALOG FUNCTIONS ---
const POPUP_DATA = <?= $popup_data ? json_encode($popup_data) : 'null' ?>;

function showMessageDialog(type, title, body) {
    const dialog = document.getElementById('generalMessageDialog');
    const content = document.getElementById('generalMessageContent');
    const titleEl = document.getElementById('messageTitle');
    const bodyEl = document.getElementById('messageBody');
    const btn = document.getElementById('closeMessageDialogBtn');

    // Set content and class
    titleEl.textContent = title;
    bodyEl.textContent = body;
    content.className = 'dialog-content ' + type;
    
    // Set button style
    btn.className = type === 'success' ? 'success-btn' : 'error-btn';

    // Display the dialog
    dialog.style.display = 'flex';
}

function closeMessageDialog() {
    document.getElementById('generalMessageDialog').style.display = 'none';
}
// ------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
    // --- Message Dialog Launch ---
    if (POPUP_DATA) {
        showMessageDialog(POPUP_DATA.type, POPUP_DATA.title, POPUP_DATA.body);
    }
    
    const closeBtn = document.getElementById('closeMessageDialogBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMessageDialog);
    }
    
    // --- Element Selection ---
    const names = document.querySelector(".names");
    const email = document.querySelector(".email");
    const joined = document.querySelector(".joined");
    const navBar = document.querySelector("nav");
    const navToggle = document.querySelector(".navToggle");
    const navLinks = document.querySelectorAll(".navList");
    const darkToggle = document.querySelector(".darkToggle");
    const body = document.querySelector("body");

    const homeContent = document.getElementById("homeContent");
    const addCourseSection = document.getElementById("addCourseSection");
    const courseTitle = document.getElementById("courseTitle");
    const submittedCoursesTitle = document.getElementById("submittedCoursesTitle");
    const submittedCourses = document.getElementById("submittedCourses");
    const sessionsContent = document.getElementById("sessionsContent");
    const forumContent = document.getElementById("forumContent");
    const resourceLibraryContent = document.getElementById("resourceLibraryContent");
    const applicationsContent = document.getElementById("applicationsContent");

    // --- Function to Update Visible Sections ---
    function updateVisibleSections() {
        const activeLink = document.querySelector(".navList.active");
        const activeText = activeLink ? activeLink.querySelector("span")?.textContent.trim() : null;

        // Hide all sections
        if (homeContent) homeContent.style.display = "none";
        if (addCourseSection) addCourseSection.style.display = "none";
        if (courseTitle) courseTitle.style.display = "none";
        if (submittedCoursesTitle) submittedCoursesTitle.style.display = "none";
        if (submittedCourses) submittedCourses.style.display = "none";
        if (sessionsContent) sessionsContent.style.display = "none";
        if (forumContent) forumContent.style.display = "none";
        if (resourceLibraryContent) resourceLibraryContent.style.display = "none";
        if (applicationsContent) applicationsContent.style.display = "none";

        // Show based on active
        switch (activeText) {
            case "Home":
                if (homeContent) homeContent.style.display = "block";
                break;
            case "Courses":
                if (addCourseSection) addCourseSection.style.display = "flex";
                if (courseTitle) courseTitle.style.display = "block";
                if (submittedCoursesTitle) submittedCoursesTitle.style.display = "block";
                if (submittedCourses) submittedCourses.style.display = "flex";
                break;
            case "Sessions":
                if (sessionsContent) sessionsContent.style.display = "block";
                break;
            case "Forum":
                if (forumContent) forumContent.style.display = "block";
                break;
            case "Resource Library":
                if (resourceLibraryContent) resourceLibraryContent.style.display = "block";
                break;
            case "Applications":
                if (applicationsContent) applicationsContent.style.display = "block";
                break;
            default:
                if (homeContent) homeContent.style.display = "block";
                console.warn("No content section defined for active link:", activeText);
        }
    }

    // --- Modal Logic ---
    function openEditResourceModal(resourceID, resourceTitle, resourceType, uploadedBy = '') {
        document.getElementById('editResourceID').value = resourceID;
        document.getElementById('editResourceTitle').value = resourceTitle;
        document.getElementById('editResourceType').value = resourceType;

        // Set the new mentor and uploader values
        document.getElementById('editUploadedBy').value = uploadedBy;

        document.getElementById('editResourceModal').style.display = 'flex';
    }

    function closeEditResourceModal() {
        document.getElementById('editResourceModal').style.display = 'none';
    }

    if(document.getElementById('editResourceModal')){
        document.getElementById('editResourceModal').style.display = 'none';
    }

    // --- Navbar Toggle ---
    if (navToggle && navBar) {
        navToggle.addEventListener('click', () => {
            navBar.classList.toggle('close');
        });
    }

    // --- Dark Mode ---
    if (darkToggle && body) {
        darkToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            if (body.classList.contains('dark')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.removeItem('darkMode');
            }
        });
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark');
        }
    }

    // --- Nav Link Clicks ---
    if (navLinks.length > 0) {
        navLinks.forEach((element) => {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                navLinks.forEach((e) => e.classList.remove('active'));
                this.classList.add('active');
                updateVisibleSections();
            });
        });
    }

    updateVisibleSections(); // On load

    // --- Data Fetching Example ---
    fetch("./data.json")
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data && Array.isArray(data.item)) {
                let nameHtml = "", emailHtml = "", joinedHtml = "";
                data.item.forEach(element => {
                    nameHtml += `<span class="data-list">${element.name || ''}</span>`;
                    emailHtml += `<span class="data-list">${element.email || ''}</span>`;
                    joinedHtml += `<span class="data-list">${element.joined || ''}</span>`;
                });
                if (names) names.innerHTML += nameHtml;
                if (email) email.innerHTML += emailHtml;
                if (joined) joined.innerHTML += joinedHtml;
            } else {
                console.warn("Data does not contain expected 'item' array.");
            }
        })
        .catch(error => {
            console.error("Error fetching data.json:", error);
        });

    // --- Edit Button Click Handler ---
    document.body.addEventListener('click', function (e) {
        if (e.target && e.target.matches('.edit-btn')) {
            const resourceID = e.target.getAttribute('data-resource-id');
            const resourceTitle = e.target.getAttribute('data-resource-title');
            const resourceType = e.target.getAttribute('data-resource-type');
            const uploadedBy = e.target.getAttribute('data-uploaded-by') || '';

            openEditResourceModal(resourceID, resourceTitle, resourceType, uploadedBy);
        }
    });

    // File size validation setup
    const resourceIconInput = document.getElementById("resourceIcon");
    const resourceFileInput = document.getElementById("resourceFile");
    
    if (resourceIconInput) {
        resourceIconInput.addEventListener('change', function() {
            checkFileSize(this, 'Icon');
        });
    }
    
    if (resourceFileInput) {
        resourceFileInput.addEventListener('change', function() {
            const isValid = checkFileSize(this, 'Resource file');
            
            // Only update preview if file is valid
            if (isValid) {
                const file = this.files[0];
                const resFileName = document.getElementById("resourceFileName");
                if (resFileName) {
                    resFileName.textContent = file.name + ` (${formatFileSize(file.size)})`;
                    resFileName.style.color = '#555';
                }
            }
        });
    }
    
    // Form submission validation
    const resourceForm = document.getElementById("resourceForm");
    if (resourceForm) {
        resourceForm.addEventListener('submit', function(e) {
            const resourceFileInput = document.getElementById("resourceFile");
            const resourceIconInput = document.getElementById("resourceIcon");
            
            let hasError = false;
            let errorMessages = [];
            
            // Client-side validation for file size (prevent submission if failed)
            // Note: Server-side validation (PHP code at the top) handles the message display via session/redirect.
            
            // Check resource file
            if (resourceFileInput && resourceFileInput.files[0]) {
                if (resourceFileInput.files[0].size > MAX_FILE_SIZE) {
                    errorMessages.push(`Resource file is too large (${formatFileSize(resourceFileInput.files[0].size)}). Maximum allowed size is ${formatFileSize(MAX_FILE_SIZE)}.`);
                    hasError = true;
                }
            }
            
            // Check icon file
            if (resourceIconInput && resourceIconInput.files[0]) {
                if (resourceIconInput.files[0].size > MAX_FILE_SIZE) {
                    errorMessages.push(`Icon file is too large (${formatFileSize(resourceIconInput.files[0].size)}). Maximum allowed size is ${formatFileSize(MAX_FILE_SIZE)}.`);
                    hasError = true;
                }
            }
            
            if (hasError) {
                // Keep the JavaScript alert() for client-side validation to prevent form submission
                alert('Upload Error:\n' + errorMessages.join('\n') + '\n\nPlease choose smaller files and try again.');
                e.preventDefault();
                return false;
            }
        });
    }
});
  </script>

  <script>
  // This script block contains the inline JavaScript from the original file
  // It's generally better practice to move this to mentor_resource.js,
  // but keeping it here for now as it was in the original.

  // Live Preview for Resource Form
  const resTitleInput = document.getElementById("resourceTitleInput");
  const resTypeSelect = document.getElementById("resourceType");
  const resIconInput = document.getElementById("resourceIcon");
  const resFileInput = document.getElementById("resourceFile");

  const resPreviewTitle = document.getElementById("resourcePreviewTitle");
  const resPreviewType = document.getElementById("resourcePreviewType");
  const resPreviewImage = document.getElementById("resourcePreviewImage");
  const resFileName = document.getElementById("resourceFileName");

const buttons = document.querySelectorAll('.category-btn');
  const resourceCards = document.querySelectorAll('#submittedResources .resource-card');

  buttons.forEach(button => {
    button.addEventListener('click', () => {
      // Remove active class from all buttons, then add to the clicked one
      buttons.forEach(btn => btn.classList.remove('active'));
      button.classList.add('active');

      const selected = button.getAttribute('data-category');

      resourceCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        const cardStatus = card.getAttribute('data-status');

        // Show card if:
        // - selected is "all", or
        // - it matches the category, or
        // - it matches the status
        if (
          selected === 'all' ||
          cardCategory === selected ||
          cardStatus === selected
        ) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

  // Event listener to update file input accept attribute based on resource type
  if(document.getElementById('resourceType')){
    document.getElementById('resourceType').addEventListener('change', function () {
        const fileInput = document.getElementById('resourceFile');
        const type = this.value.toLowerCase();

        let acceptTypes = '';
        if (type === 'pdf') acceptTypes = '.pdf';
        if (type === 'ppt') acceptTypes = '.ppt,.pptx';
        if (type === 'video') acceptTypes = '.mp4,.avi,.mov,.wmv';

        fileInput.value = ''; // Clear current file when type changes
        fileInput.setAttribute('accept', acceptTypes);
        if(resFileName) resFileName.textContent = "No file selected"; // Also clear preview text
    });
  }

  // Function to update file input accept attribute and hint for edit modal
  function updateFileAcceptType(prefix) {
    const typeSelect = document.getElementById(`${prefix}ResourceType`);
    const fileInput = document.getElementById(`${prefix}ResourceFile`);
    const hint = document.getElementById(`${prefix}FileHint`);

    let acceptTypes = '';
    let hintText = '';

    switch (typeSelect.value.toLowerCase()) {
      case 'pdf':
        acceptTypes = '.pdf';
        hintText = 'Allowed: .pdf';
        break;
      case 'ppt':
        acceptTypes = '.ppt,.pptx';
        hintText = 'Allowed: .ppt, .pptx';
        break;
      case 'video':
        acceptTypes = '.mp4,.avi,.mov,.wmv';
        hintText = 'Allowed: .mp4, .avi, .mov, .wmv';
        break;
      default:
        acceptTypes = '';
        hintText = '';
    }

    fileInput.setAttribute('accept', acceptTypes);
    if (hint) hint.textContent = hintText;
  }

  // Function to preview image file before upload
  function previewImage(event, previewId) {
    const file = event.target.files[0];
    const imgPreview = document.getElementById(previewId);
    if (file) {
      const reader = new FileReader();
      reader.onload = () => {
        imgPreview.src = reader.result;
        imgPreview.style.display = "block";
      };
      reader.readAsDataURL(file);
    } else {
      imgPreview.src = "";
      imgPreview.style.display = "none";
    }
  }

  // Function to preview different file types
  function previewFileByType(type, filePath, containerId) {
    const previewContainer = document.getElementById(containerId);
    previewContainer.innerHTML = ''; // Clear previous preview

    if (!filePath || !type) return;

    const fileExtension = filePath.split('.').pop().toLowerCase();

    if (type.toLowerCase() === 'pdf' && fileExtension === 'pdf') {
      previewContainer.innerHTML = `<embed src="${filePath}" type="application/pdf" width="100%" height="300px" />`;
    } else if (type.toLowerCase() === 'ppt' && ['ppt', 'pptx'].includes(fileExtension)) {
      previewContainer.innerHTML = `<p>📄 Current File: <a href="${filePath}" target="_blank">Download/View PPT</a></p>`;
    } else if (type.toLowerCase() === 'video' && ['mp4', 'avi', 'mov', 'wmv'].includes(fileExtension)) {
      previewContainer.innerHTML = `
        <video controls width="100%">
          <source src="${filePath}" type="video/${fileExtension}">
          Your browser does not support the video tag.
        </video>
      `;
    } else {
        // Fallback for other file types or if file doesn't match type
        previewContainer.innerHTML = `<p>🔗 Current File: <a href="${filePath}" target="_blank">${filePath.split('/').pop()}</a></p>`;
    }
  }

  // Function to preview newly uploaded file (before saving)
  function previewUploadedFile(event, containerId) {
    const file = event.target.files[0];
    const previewContainer = document.getElementById(containerId);
    previewContainer.innerHTML = ''; // Clear previous preview

    if (!file) return;

    const fileType = file.type;
    const fileURL = URL.createObjectURL(file);

    if (fileType === 'application/pdf') {
      previewContainer.innerHTML = `<embed src="${fileURL}" type="application/pdf" width="100%" height="300px" />`;
    } else if (fileType.startsWith('video/')) {
      previewContainer.innerHTML = `
        <video controls width="100%">
          <source src="${fileURL}" type="${fileType}">
        </video>
      `;
    } else {
      previewContainer.innerHTML = `<p>📄 New File Selected: ${file.name}</p>`;
    }
  }

  // Live preview for the add resource form
  if (resTitleInput) {
    resTitleInput.addEventListener("input", function () {
      resPreviewTitle.textContent = this.value.trim() || "Resource Title";
    });
  }

  if (resTypeSelect) {
    resTypeSelect.addEventListener("change", function () {
      resPreviewType.textContent = this.value || "Resource Type";
    });
  }

  if (resIconInput) {
    resIconInput.addEventListener("change", function () {
      const file = this.files[0];
      if (file) {
        // Check file size first
        if (checkFileSize(this, 'Icon')) {
          const reader = new FileReader();
          reader.onload = function (e) {
            resPreviewImage.src = e.target.result;
            resPreviewImage.style.display = "block";
          };
          reader.readAsDataURL(file);
        } else {
          resPreviewImage.src = "";
          resPreviewImage.style.display = "none";
        }
      } else {
        resPreviewImage.src = "";
        resPreviewImage.style.display = "none";
      }
    });
  }

  if (resFileInput) {
    resFileInput.addEventListener("change", function () {
      const isValid = checkFileSize(this, 'Resource file');
      if (isValid) {
        const file = this.files[0];
        if (resFileName && file) {
          resFileName.textContent = file.name + ` (${formatFileSize(file.size)})`;
          resFileName.style.color = '#555';
        }
      }
    });
  }

  // Resource Library Link functionality (if it exists)
  const resourceLibraryLink = document.getElementById("resourceLibraryLink");
  if (resourceLibraryLink) {
    resourceLibraryLink.addEventListener("click", function(e) {
      e.preventDefault(); // Prevent default link behavior

      // Load the resource page content via fetch
      fetch("resource.php")
        .then(res => {
          if (!res.ok) {
            console.error('Error fetching resource content:', res.statusText);
            return;
          }
          return res.text();
        })
        .then(data => {
          const mainContent = document.getElementById("mainContent");
          if (mainContent) {
               mainContent.innerHTML = data;

              setTimeout(() => {
                const addSection = document.getElementById("addResourceSection");
                if (addSection) {
                  // Hide other sections if needed
                  const allSections = mainContent.querySelectorAll(":scope > div");
                  allSections.forEach(section => section.style.display = "none");

                  // Show the desired one
                  addSection.style.display = "block";
                  // Also show related titles if they exist within the loaded content
                  const resourceTitleLoaded = mainContent.querySelector("#resourceTitle");
                  const submittedResourcesLoaded = mainContent.querySelector("#submittedResources");
                   if(resourceTitleLoaded) resourceTitleLoaded.style.display = "block";
                   if(submittedResourcesLoaded) submittedResourcesLoaded.style.display = "flex";
                }
              }, 50);
          }
        })
        .catch(error => {
            console.error('Error fetching resource content:', error);
        });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
      // Make sure all .navList elements are available
      const navLinks = document.querySelectorAll(".navList");
      // Find the Resource Library link specifically to set it as default active if needed
      const resourceLibraryLinkElement = Array.from(navLinks).find(link => link.querySelector('span.links')?.textContent.trim() === "Resource Library");

      if(resourceLibraryLinkElement) {
        // Remove 'active' from all
        navLinks.forEach(link => link.classList.remove("active"));
        // Set default active tab to Resource Library
        resourceLibraryLinkElement.classList.add("active");
      }
      
      // This function is defined in the other script tag but we call it here to ensure sections are shown/hidden correctly on page load
      if (typeof updateVisibleSections === 'function') {
        updateVisibleSections();
      }
  });

  </script>

<div id="generalMessageDialog" class="general-dialog">
    <div class="dialog-content" id="generalMessageContent">
        <h3 id="messageTitle"></h3>
        <p id="messageBody"></p>
        <div class="dialog-buttons">
            <button id="closeMessageDialogBtn" type="button">OK</button>
        </div>
    </div>
</div>
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