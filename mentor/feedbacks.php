<?php
session_start(); 

require '../connection/db_connection.php';

// Check for a valid database connection immediately after inclusion
if ($conn->connect_error) {
    // Log the error for the administrator, but don't show details to the user.
    error_log("Database connection failed: " . $conn->connect_error);
    // Display a generic error message and stop the script.
    die("A database connection error occurred. Please try again later.");
}

// SESSION CHECK: Verify user is logged in and is a Mentor
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentor') {
    header("Location: ../login.php"); 
    exit();
}

// --- PHP LOGIC ---

$mentor_id = $_SESSION['user_id'];
$mentor_username = $_SESSION['username'];

// Fetch current Mentor's details from the `users` table to ensure session data is accurate
$user_sql = "SELECT CONCAT(first_name, ' ', last_name) AS Mentor_Name, icon FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);

// **FIX ADDED**: Check if the prepare statement failed for the user query
if ($stmt === false) {
    error_log("Error preparing user details statement: " . $conn->error);
    die("An error occurred while fetching user data. Please contact support.");
}

$stmt->bind_param("i", $mentor_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 1) {
    $user_row = $user_result->fetch_assoc();
    $_SESSION['mentor_name'] = $user_row['Mentor_Name'];
    $_SESSION['mentor_icon'] = (!empty($user_row['icon'])) ? $user_row['icon'] : "../uploads/img/default_pfp.png";
} else {
    // Fallback if mentor details are not found
    $_SESSION['mentor_name'] = "Unknown Mentor";
    $_SESSION['mentor_icon'] = "../uploads/img/default_pfp.png";
}
$stmt->close();

// Get the logged-in mentor's name from the session
$loggedInMentorName = $_SESSION['mentor_name'];

// Prepare the SQL query to fetch feedback records ONLY for the logged-in mentor
$query = "SELECT * FROM feedback WHERE Session_Mentor = ?";
$stmt = $conn->prepare($query);

// This error check was already correctly in place
if ($stmt === false) {
    error_log("Error preparing feedback statement: " . $conn->error);
    die("Error preparing statement: " . $conn->error);
}

// Bind the mentor's name parameter and execute
$stmt->bind_param("s", $loggedInMentorName);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/dashboard.css" />
    <link rel="stylesheet" href="css/navigation.css"/>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <title>Feedback | Mentor</title>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <style>
/* ========================================
COLORS (Based on the image)
========================================
*/

:root {
   
    --accent-color: #995BCC; /* Lighter Purple/Active Link */
    --text-color: #333;
    --body-bg: #F7F7F7;
    --table-row-hover: #F0F0F0;
    --action-btn-color: #4CAF50; 
    --detail-view-bg: white;
    --header-color: #444;
    --nav-icon-color: white;
}



body {
    background-color: var(--body-bg);
    display: flex;
     overflow-x: hidden;
}

a {
    text-decoration: none;
    color: inherit;
}

header h1 {
            color: #562b63;

            font-size: 28px;
            margin-top: 50px;
        }

.logo {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}

.logo-image img {
    width: 30px; 
    margin-right: 10px;
}

.logo-name {
    font-size: 1.5rem;
    font-weight: 700;
}


.edit-profile-link {
    margin-left: auto;
    color: var(--nav-icon-color);
}

.menu-items {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}


.bottom-link {
    padding-top: -5px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* ========================================
    MAIN CONTENT (DASHBOARD)
    ======================================== */
.dashboard {
  
    width: calc(100% - 250px);
    padding: 20px;
}

.dashboard .top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.dashboard .top img {
    width: 40px;
}

.dashboard h1 {
    font-size: 2em;
    color: var(--header-color);
    margin-bottom: 20px;
}

/* ========================================
    TABLE STYLES - MODIFIED
    ======================================== */

#tableContainer {
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow-x: auto;
    
}

#tableContainer table {
    table-layout: auto; 
    width: 100%; 
}


#tableContainer thead {
    background-color: var(--sidebar-bg-color); 
    color: white;
}

#tableContainer th {
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    background-color: #562b63;
}

#tableContainer td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    color: var(--text-color);
    text-align: left; 
    word-wrap: break-word; 
    max-width: 200px; 
    vertical-align: top;
}

#tableContainer tbody tr:last-child td {
    border-bottom: none;
}

#tableContainer tbody tr:hover {
    background-color: var(--table-row-hover);
}


/* ========================================
    NAVIGATION & LAYOUT STYLES
    ======================================== */

nav {
    display: flex; 
    flex-direction: column; 
    height: 100vh; 
}

.menu-items {
    flex-grow: 1; 
    display: flex; 
    flex-direction: column; 
    justify-content: space-between; 
}

.navLinks {
    margin-bottom: auto; 
}
/* ========================================
    DETAIL VIEW STYLES - HIDE THIS SECTION
    ======================================== */
#detailView {
    display: none; /* CRITICAL: Hiding the detail view as requested */
    padding: 20px;
    max-width: 700px;
    margin: 0 auto;
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
      <li class="navList active">
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
        <img src="../uploads/img/logo.png" alt="Logo">
    </div>
<header>
    <h1>Manage Feedback</h1>
    </header>

    <div id="tableContainer">
        <table>
            <thead>
                <tr>
                    <th>Session Name</th>
                    <th>Session Date</th>
                    <th>Session Time Slot</th>
                    <th>Mentor Reviews</th>
                    <th>Mentor Star Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="data-row">
                            <td><?= htmlspecialchars($row['Session']) ?></td>
                            <td><?= htmlspecialchars($row['Session_Date']) ?></td>
                            <td><?= htmlspecialchars($row['Time_Slot']) ?></td>
                            <td><?= htmlspecialchars($row['Mentor_Reviews']) ?></td>
                            <td><?= htmlspecialchars($row['Mentor_Star']) ?>⭐</td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                         <td colspan="7" style="text-align: center;">No feedback records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="detailView" style="display:none;">
        <div id="feedbackDetails" class="form-container">
            <h2>View Feedback Details</h2>
            <form id="feedbackForm">
                 <div class="form-buttons">
                    <button type="button" onclick="goBack()" class="cancel-btn">Back</button>
                </div>

                <div class="form-group"><label>Feedback ID:</label><input type="text" id="feedback_id" readonly></div>
                <div class="form-group"><label>Session:</label><input type="text" id="session" readonly></div>
                <div class="form-group"><label>Forum ID:</label><input type="text" id="forum_id" readonly></div>
                <div class="form-group"><label>Session Date:</label><input type="text" id="session_date" readonly></div>
                <div class="form-group"><label>Time Slot:</label><input type="text" id="time_slot_detail" readonly></div>
                <div class="form-group"><label>Session Mentor:</label><input type="text" id="session_mentor" readonly></div>
                <div class="form-group"><label>Mentee Username:</label><input type="text" id="mentee_from_db" readonly></div>
                <div class="form-group"><label>Mentee Experience:</label><textarea id="mentee_experience" rows="4" readonly></textarea></div>
                <div class="form-group"><label>Experience Star Rating:</label><input type="text" id="experience_star_detail" readonly></div>
                <div class="form-group"><label>Mentor Reviews:</label><textarea id="mentor_reviews" rows="4" readonly></textarea></div>
                <div class="form-group"><label>Mentor Star Rating:</label><input type="text" id="mentor_star_detail" readonly></div>
            </form>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="js/navigation.js"></script>
    <script>
        // Placeholder function to prevent errors if the goBack button is clicked
        function goBack() {
             document.getElementById('detailView').style.display = 'none';
             document.getElementById('tableContainer').style.display = 'block';
        }

    </script>
</section>
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

<?php
// Close the database connection at the end of the script
$conn->close();
?>