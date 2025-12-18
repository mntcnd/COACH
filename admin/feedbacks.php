<?php
session_start(); // Start the session

// Standard session check for an admin user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Use your standard database connection
require '../connection/db_connection.php';

// --- FETCH USER DETAILS FROM 'users' TABLE ---
$currentUsername = $_SESSION['username'] ?? '';

if (empty($currentUsername)) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$sqlUser = "SELECT user_id, first_name, last_name, icon, user_type FROM users WHERE username = ?";
$stmtUser = $conn->prepare($sqlUser);

if ($stmtUser === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmtUser->bind_param("s", $currentUsername);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows === 1) {
    $user = $resultUser->fetch_assoc();
    
    if ($user['user_type'] !== 'Admin' && $user['user_type'] !== 'Super Admin') {
        header("Location: ../login.php");
        exit();
    }

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['admin_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['admin_icon'] = !empty($user['icon']) ? $user['icon'] : "../uploads/img/default_pfp.png";

} else {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$stmtUser->close();


// --- FETCH ALL FEEDBACK RECORDS ---
$queryFeedback = "SELECT * FROM feedback ORDER BY Feedback_ID DESC";
$result = $conn->query($queryFeedback);

if ($result === false) {
    die("Error fetching feedback records: " . $conn->error);
}

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
    <title>Feedback | Admin</title>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

<style>
/* ========================================
COLORS 
========================================
*/

:root {
    
    --accent-color: #995BCC; /* Lighter Purple/Active Link */
    --text-color: #333;
    --body-bg: #F7F7F7;
    --table-row-hover: #F0F0F0;
    --header-color: #444;
    --nav-icon-color: white;
    --purple-header: #562b63;
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
            color: #333;
            font-size: 30px;
            margin-top: 50px;
            margin-bottom: 20px;
        }

.logo {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
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
    padding-top: 5px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* ========================================
    MAIN CONTENT (DASHBOARD)
    ======================================== */
.dashboard {
    width: calc(100% - 250px);
    padding: 20px;
}

/* ========================================
    TABLE STYLES - MODIFIED FOR FULL TEXT WRAPPING & NO OVERLAP
    ======================================== */

#tableContainer {
    background-color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 5px;
    overflow-x: auto; 
}

#tableContainer table {
    table-layout: fixed; /* Crucial for respecting column widths */
    width: 100%; 
    min-width: 1300px; /* Ensures space for all content */
}


#tableContainer thead {
    color: white;
}

#tableContainer th {
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    background-color: var(--purple-header);
    white-space: nowrap; 
}

#tableContainer td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    color: var(--text-color);
    text-align: left;
    vertical-align: top;
    
    /* Allow text to wrap and prevent single long words from breaking the table */
    word-wrap: break-word; 
    white-space: normal; /* KEY: Allow all text to wrap! */
    max-width: 250px; 
    min-width: 100px;
}

/* Session Name (1st td), Session Mentor (2nd td), Mentee Name (4th td) */
#tableContainer td:nth-child(1),
#tableContainer td:nth-child(2),
#tableContainer td:nth-child(4) {
    max-width: 150px; 
    min-width: 100px;
}

/* Session Details (3rd td - for the icon) */
#tableContainer td:nth-child(3) {
    max-width: 80px;
    min-width: 60px;
    text-align: center !important;
    white-space: nowrap; /* Keep the icon centered */
}


/* Mentee Experience (5th td) & Mentor Reviews (7th td) - The largest text blocks */
#tableContainer td:nth-child(5),
#tableContainer td:nth-child(7) { 
    max-width: 350px; /* Increased width to give plenty of room for wrapping */
    min-width: 200px;
    white-space: normal; 
}

/* Star Rating Columns: Exp. Star (6th td) & Mentor Star (8th td) */
#tableContainer td:nth-child(6), 
#tableContainer td:nth-child(8) { 
    max-width: 80px; 
    min-width: 60px; 
    text-align: center;
    /* Crucial: Prevents rating from wrapping and defines the rigid boundary */
    white-space: nowrap; 
}

#tableContainer tbody tr:hover {
    background-color: var(--table-row-hover);
}

/* ========================================
    HOVER TOOLTIP STYLES (MODIFIED TO SHOW BELOW)
    ======================================== */

.hover-details-cell {
    position: relative;
    text-align: center !important; 
    padding: 12px 15px;
}

/* Tooltip container (hidden by default) */
.session-tooltip {
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s, visibility 0.3s;
    width: 200px;
    background-color: var(--purple-header); 
    color: white;
    text-align: left;
    border-radius: 6px;
    padding: 10px;
    
    position: absolute;
    z-index: 10;
    
    /* Position the tooltip BELOW the icon */
    top: 120%; 
    left: 50%;
    margin-left: -100px; /* Center the tooltip relative to its cell */
    
    font-size: 0.9em;
    line-height: 1.4;
    white-space: normal;
}

/* Tooltip arrow/pointer */
.session-tooltip::after {
    content: "";
    position: absolute;
    /* Position the arrow on top of the tooltip, pointing up */
    bottom: 100%; 
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    /* Create an upward-pointing triangle */
    border-color: transparent transparent var(--purple-header) transparent; 
}

/* Show the tooltip on hover over the cell */
.hover-details-cell:hover .session-tooltip {
    visibility: visible;
    opacity: 1;
}

/* Style the icon */
.hover-details-cell ion-icon {
    font-size: 1.5em;
    color: var(--accent-color); 
    cursor: pointer;
}

/* Nav styles for structure (Unchanged) */
nav {
    display: flex; 
    flex-direction: column; 
    height: 100vh; 
}

.menu-items {
    flex-grow: 1; 
    overflow-y: auto; 
    display: flex; 
    flex-direction: column; 
    justify-content: space-between; 
}

.navLinks {
    margin-bottom: auto; 
}

.admin-profile {
    margin-top: 0; 
    padding-top: 0;
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
            <img src="<?php echo htmlspecialchars($_SESSION['admin_icon']); ?>" alt="Admin Profile Picture" />
            <div class="admin-text">
                <span class="admin-name">
                    <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </span>
                <span class="admin-role">Moderator</span> </div>
            <a href="edit_profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
                <ion-icon name="create-outline"></ion-icon>
            </a>
        </div>
    </div>

    <div class="menu-items">
        <ul class="navLinks">
            <li class="navList">
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
            <li class="navList active"> <a href="feedbacks.php"> <ion-icon name="star-outline"></ion-icon>
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

<header>
          <h1>Manage Feedback</h1>
    </header>

    <div id="tableContainer">
        <table>
            <thead>
                <tr>
                    <th>Session Name</th>
                    <th>Session Mentor</th>
                    <th>Session Details</th>
                    <th>Mentee Name</th>
                    <th>Mentee Experience</th>
                    <th>COACH Exp Star</th>
                    <th>Mentor Reviews</th>
                    <th>Mentor Star</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="data-row">
                            
                            <td><?= htmlspecialchars($row['Session']) ?></td>
                            <td><?= htmlspecialchars($row['Session_Mentor']) ?></td>
                            
                            <td class="hover-details-cell">
                                <ion-icon name="information-circle-outline"></ion-icon>
                                <div class="session-tooltip">
                                    <strong>Date:</strong> <?= htmlspecialchars($row['Session_Date']) ?><br>
                                    <strong>Time Slot:</strong> <?= htmlspecialchars($row['Time_Slot']) ?>
                                </div>
                            </td>
                            
                            <td><?= htmlspecialchars($row['Mentee']) ?></td>
                            <td><?= htmlspecialchars($row['Mentee_Experience']) ?></td>
                            <td><?= htmlspecialchars($row['Experience_Star']) ?>⭐</td>
                            <td><?= htmlspecialchars($row['Mentor_Reviews']) ?></td>
                            <td><?= htmlspecialchars($row['Mentor_Star']) ?>⭐</td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No feedback records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<script src="js/navigation.js"></script>
    <script>
        // Placeholder for logout confirmation logic
        function confirmLogout(event) {
            event.preventDefault();
            const dialog = document.getElementById('logoutDialog');
            dialog.style.display = 'flex';

            document.getElementById('cancelLogout').onclick = function() {
                dialog.style.display = 'none';
            };

            document.getElementById('confirmLogoutBtn').onclick = function() {
                window.location.href = '../logout.php'; // Assuming your logout script is here
            };
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
// Close the database connection at the very end of the script
if (isset($conn)) {
    $conn->close();
}
?>