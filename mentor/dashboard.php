<?php
session_start();

require '../connection/db_connection.php';

// SESSION CHECK - UPDATED for the new 'users' table structure
// We now check for a general 'username' and ensure the 'user_type' is 'Mentor'.
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'Mentor') {
  // Redirect to a general login page if the user is not a logged-in mentor.
  header("Location: ../login.php"); // CHNAGE: Redirect to your main login page
  exit();
}

// FETCH Mentor_Name AND icon BASED ON username from the 'users' table
$username = $_SESSION['username']; // CHANGE: Using the new 'username' session variable
// CHANGE: The SQL query now targets the 'users' table instead of 'applications'.
// Column names are updated from First_Name, Last_Name, Mentor_Icon to first_name, last_name, icon.
$sql = "SELECT CONCAT(first_name, ' ', last_name) AS Mentor_Name, icon FROM users WHERE username = ? AND user_type = 'Mentor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username); // CHANGE: Binding the new $username variable
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $row = $result->fetch_assoc();
  $_SESSION['mentor_name'] = $row['Mentor_Name'];

  // Check if icon exists and is not empty
  // CHANGE: Updated column name from 'Mentor_Icon' to 'icon'
  if (isset($row['icon']) && !empty($row['icon'])) {
    $_SESSION['mentor_icon'] = $row['icon'];
  } else {
    // Provide a default icon if none is set
    $_SESSION['mentor_icon'] = "../uploads/img/default_pfp.png";
  }
} else {
  // Handle case where mentor is not found, though session check should prevent this
  $_SESSION['mentor_name'] = "Unknown Mentor";
  $_SESSION['mentor_icon'] = "../uploads/img/default_pfp.png";
}

// NO CHANGE NEEDED BELOW THIS POINT FOR DATA FETCHING
// The following queries correctly use the mentor's full name, which we retrieved above.

// FETCH Course_Title AND Skill_Level BASED ON Mentor_Name
$mentorName = $_SESSION['mentor_name'];
$courseSql = "SELECT Course_Title, Skill_Level FROM courses WHERE Assigned_Mentor = ?";
$courseStmt = $conn->prepare($courseSql);
$courseStmt->bind_param("s", $mentorName);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();

if ($courseResult->num_rows === 1) {
    $courseRow = $courseResult->fetch_assoc();
    $_SESSION['course_title'] = $courseRow['Course_Title'];
    $_SESSION['skill_level'] = $courseRow['Skill_Level'];
} else {
    $_SESSION['course_title'] = "No Assigned Course";
    $_SESSION['skill_level'] = "-";
}

$courseStmt->close();

// Query to count approved resources uploaded by the mentor
$resourceSql = "SELECT COUNT(*) AS approved_count FROM resources WHERE UploadedBy = ? AND Status = 'Approved'";
$resourceStmt = $conn->prepare($resourceSql);
$resourceStmt->bind_param("s", $mentorName);
$resourceStmt->execute();
$resourceResult = $resourceStmt->get_result();

if ($resourceResult->num_rows === 1) {
    $resourceRow = $resourceResult->fetch_assoc();
    $approvedResourcesCount = $resourceRow['approved_count'];
} else {
    $approvedResourcesCount = 0;
}

$resourceStmt->close();

// FETCH Number of Sessions BASED ON Course_Title
$courseTitle = $_SESSION['course_title'];
$sessionCount = 0;

if ($courseTitle !== "No Assigned Course") {
    $sessionSql = "SELECT COUNT(*) AS session_count FROM sessions WHERE Course_Title = ?";
    $sessionStmt = $conn->prepare($sessionSql);
    $sessionStmt->bind_param("s", $courseTitle);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();

    if ($sessionResult->num_rows === 1) {
        $sessionRow = $sessionResult->fetch_assoc();
        $sessionCount = $sessionRow['session_count'];
    }

    $sessionStmt->close();
}

// FETCH Number of Feedbacks BASED ON Session_Mentor
$feedbackCount = 0;
$feedbackSql = "SELECT COUNT(*) AS feedback_count FROM feedback WHERE Session_Mentor = ?";
$feedbackStmt = $conn->prepare($feedbackSql);
$feedbackStmt->bind_param("s", $mentorName);
$feedbackStmt->execute();
$feedbackResult = $feedbackStmt->get_result();

if ($feedbackResult->num_rows === 1) {
    $feedbackRow = $feedbackResult->fetch_assoc();
    $feedbackCount = $feedbackRow['feedback_count'];
}

$feedbackStmt->close();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/courses.css" />
  <link rel="stylesheet" href="css/home.css" />
  <link rel="stylesheet" href="../superadmin/css/clock.css"/>
  <link rel="stylesheet" href="css/navigation.css"/>
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Home | Mentor</title>
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
  </div>

  <div class="menu-items">
    <ul class="navLinks">
      <li class="navList active">
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
      <img src="../uploads/img/logo.png" alt="Logo"> </div>

    <div id="homeContent" style="padding: 20px;">

    <section class="widget-section">
  <h2>Mentor <span class="preview">Home page</span></h2>

  <section class="clock-section">
  <div class="clock-container">
    <div class="time">
      <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
      <span id="ampm">AM</span>
    </div>
    <div class="date" id="date">
      Wed, 11 January 2023
    </div>
  </div>
</section>

<script src="js/navigation.js"></script>
<script>
  function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12;
    hours = hours ? hours : 12;

    document.getElementById('hours').textContent = String(hours).padStart(2, '0');
    document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
    document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
    document.getElementById('ampm').textContent = ampm;

    const options = { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric' };
    document.getElementById('date').textContent = now.toLocaleDateString('en-US', options);
  }

  setInterval(updateClock, 1000);
  updateClock();
</script>

<div class="widget-grid">

      <div class="widget blue full">
      <div class="details1">
     <h1>TITLE COURSE</h1>
        <h1><?php echo htmlspecialchars($_SESSION['course_title']); ?></h1>
        <p>Skill Level: <?php echo htmlspecialchars($_SESSION['skill_level']); ?></p>
        <span></span>
      </div>
    </div>
    <div class="widget green full">
    <img src="../uploads/img/mentor.png" alt="Bookmark Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo htmlspecialchars($sessionCount); ?></h3>
        <p>SESSIONS</p>
        <span class="note">Total Sessions</span>
      </div>
    </div>
    <div class="widget orange full">
    <img src="../uploads/img/resources.png" alt="Bookmark Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo htmlspecialchars($approvedResourcesCount); ?></h3>
        <p>RESOURCES</p>
        <span class="note">Total Resources</span>
      </div>
  </div>
  <div class="widget orange full">
    <img src="../uploads/img/star.png" alt="Bookmark Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo htmlspecialchars($feedbackCount); ?></h3>
        <p>FEEDBACKS</p>
        <span class="note">Total Feedbacks</span>
      </div>
  </div>
</section>
    

        </div>
    
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

  <script src="js/admin.js"></script>

  <script>
    // Modal Logic
    function openEditModal(id, title, description, level) { // Removed image path - handle display separately if needed
      document.getElementById('editModal').style.display = 'block';
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_title').value = title;
      document.getElementById('edit_description').value = description;
      document.getElementById('edit_level').value = level;
      document.getElementById('edit_image').value = ''; // Clear file input
      // Optional: Display current image name/thumbnail if needed (requires fetching it or passing it)
      // document.getElementById('current_image_display').innerHTML = `Current Image: ${imageFilename}`;
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
       document.getElementById('editCourseForm').reset(); // Reset form on close
    }

    // Live Preview Logic for Add Form
    const titleInput = document.getElementById("title");
    const descriptionInput = document.getElementById("description");
    const levelSelect = document.getElementById("level");
    const imageInput = document.getElementById("image");
    const previewTitle = document.getElementById("previewTitle");
    const previewDescription = document.getElementById("previewDescription");
    const previewLevel = document.getElementById("previewLevel");
    const previewImage = document.getElementById("previewImage");

    if(titleInput) {
        titleInput.addEventListener("input", function() {
         previewTitle.textContent = this.value.trim() || "Course Title";
        });
    }
    if(descriptionInput) {
        descriptionInput.addEventListener("input", function() {
          previewDescription.textContent = this.value.trim() || "Course Description";
        });
    }
    if(levelSelect) {
        levelSelect.addEventListener("change", function() {
          previewLevel.textContent = this.value || "Skill Level";
        });
    }
    if(imageInput) {
        imageInput.addEventListener("change", function() {
          const file = this.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
              previewImage.src = e.target.result;
              previewImage.style.display = "block";
            };
            reader.onerror = function() {
                console.error("Error reading file for preview.");
                previewImage.src = "";
                previewImage.style.display = "none";
            }
            reader.readAsDataURL(file);
          } else {
              previewImage.src = "";
              previewImage.style.display = "none";
          }
        });
    }

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