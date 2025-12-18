<?php
session_start();

// SESSION CHECK: Verify if the user is logged in and is an 'Admin'
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
  header("Location: ../login.php"); // Redirect to a generic login page
  exit();
}

// CONNECT TO DATABASE
require '../connection/db_connection.php'; // Use your existing connection script

// FETCH LOGGED-IN ADMIN'S INFORMATION
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, first_name, last_name, icon FROM users WHERE user_id = ? AND user_type = 'Admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
  $row = $result->fetch_assoc();
  // Set session variables for use in the HTML
  $_SESSION['username'] = $row['username'];
  $_SESSION['user_full_name'] = $row['first_name'] . ' ' . $row['last_name'];
  
  if (isset($row['icon']) && !empty($row['icon'])) {
    $_SESSION['user_icon'] = $row['icon'];
  } else {
    $_SESSION['user_icon'] = "../uploads/img/default_pfp.png";
  }
} else {
  // If user not found (e.g., deleted), log them out
  session_destroy();
  header("Location: ../login.php");
  exit();
}
$stmt->close();

// --- GET COUNTS FOR DASHBOARD WIDGETS ---

// Count Mentees
$menteeQuery = "SELECT COUNT(*) as mentee_count FROM users WHERE user_type = 'Mentee'";
$menteeResult = $conn->query($menteeQuery);
$menteeCount = ($menteeResult) ? $menteeResult->fetch_assoc()['mentee_count'] : 0;

// Count Approved Mentors
$mentorQuery = "SELECT COUNT(*) as mentor_count FROM users WHERE user_type = 'Mentor' AND Status = 'Approved'";
$mentorResult = $conn->query($mentorQuery);
$mentorCount = ($mentorResult) ? $mentorResult->fetch_assoc()['mentor_count'] : 0;

// Count Applicants (Mentors not yet approved)
$applicantQuery = "SELECT COUNT(*) as applicant_count FROM users WHERE user_type = 'Mentor' AND Status != 'Approved'";
$applicantResult = $conn->query($applicantQuery);
$applicantCount = ($applicantResult) ? $applicantResult->fetch_assoc()['applicant_count'] : 0;

// Count Approved Resources
$resourceQuery = "SELECT COUNT(*) as resource_count FROM resources WHERE Status = 'Approved'";
$resourceResult = $conn->query($resourceQuery);
$resourceCount = ($resourceResult) ? $resourceResult->fetch_assoc()['resource_count'] : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/dashboard.css" />
  <link rel="stylesheet" href="css/home.css"/>
  <link rel="stylesheet" href="../superadmin/css/clock.css"/>
  <link rel="stylesheet" href="css/navigation.css"/>
   <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Home | Admin</title>
</head>
<body>
<nav>
  <div class="nav-top">
    <div class="logo">
      <div class="logo-image"><img src="../uploads/img/logo.png" alt="Logo"></div>
      <div class="logo-name">COACH</div>
    </div>

    <div class="admin-profile">
      <img src="<?php echo htmlspecialchars($_SESSION['user_icon']); ?>" alt="Admin Profile Picture" />
      <div class="admin-text">
        <span class="admin-name">
          <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>
        </span>
        <span class="admin-role">Moderator</span>
      </div>
      <a href="edit_profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
        <ion-icon name="create-outline" class="verified-icon"></ion-icon>
      </a>
    </div>
  </div>

    <div class="menu-items">
        <ul class="navLinks">
            <li class="navList active">
                <a href="dashboard.php"> <ion-icon name="home-outline"></ion-icon>
                    <span class="links">Home</span>
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
            <li class="navList"> <a href="feedbacks.php"> <ion-icon name="star-outline"></ion-icon>
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

    <div id="homeContent" style="padding: 20px;">
    <section class="widget-section">
  <h2>Moderator <span class="preview">Home page</span></h2>

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

<div class="widget-grid">

      <div class="widget blue full">
      <img src="../uploads/img/mentee.png" alt="Mentees Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo $menteeCount; ?></h3>
        <p>MENTEES</p>
        <span class="note">Total Mentees</span>
      </div>
    </div>
    <div class="widget green full">
    <img src="../uploads/img/mentor.png" alt="Mentors Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo $mentorCount; ?></h3>
        <p>MENTORS</p>
        <span class="note">Total Mentors</span>
      </div>
    </div>
    <div class="widget orange full">
    <img src="../uploads/img/applicants.png" alt="Applicants Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo $applicantCount; ?></h3>
        <p>APPLICANTS</p>
        <span class="note">Total Applicants</span>
      </div>
    </div>
    <div class="widget red full">
    <img src="../uploads/img/resources.png" alt="Resources Icon" class="img-icon" />
      <div class="details">
        <h3><?php echo $resourceCount; ?></h3>
        <p>RESOURCES</p>
        <span class="note">Approved Resources</span>
      </div>
    </div>
  </div>
</section>

<section class="quick-links" style="margin-top: 170px;">
  <h3>Quick Links</h3>
  <div class="links-container">
    <a href="manage_mentors.php" class="quick-link">
      <span class="icon1">🧑🏻‍🏫</span>
      <span>Approval Applicants</span>
    </a>
    <a href="manage_mentees.php" class="quick-link">
      <span class="icon1">👥</span>
      <span>Manage Mentees</span>
    </a>
     <a href="report_generation.php" class="quick-link">
      <span class="icon1">📊</span>
      <span>Report Analysis</span>
    </a>
  </div>
</section>

    </div>
    
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
  <script src="js/navigation.js"></script>
  <script>
    // Live Clock Update
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes();
        const seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';

        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'

        document.getElementById('hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
        document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        document.getElementById('ampm').textContent = ampm;

        const options = { weekday: 'short', day: '2-digit', month: 'long', year: 'numeric' };
        document.getElementById('date').textContent = now.toLocaleDateString('en-US', options);
    }
    setInterval(updateClock, 1000);
    updateClock(); // initial call

    // Navigation Toggle
    const navBar = document.querySelector("nav");
    const navToggle = document.querySelector(".navToggle");
    if (navToggle) {
        navToggle.addEventListener('click', () => {
            navBar.classList.toggle('close');
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
