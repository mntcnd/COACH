<?php
session_start();

// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Super Admin'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Super Admin') {
    // If not a Super Admin, redirect to the login page.
    header("Location: ../login.php");
    exit();
}

require '../connection/db_connection.php';

// Fetch Super Admin data from the 'users' table
$username = $_SESSION['username']; // Use the generic 'username' session from login
$stmt = $conn->prepare("SELECT first_name, last_name, icon FROM users WHERE username = ? AND user_type = 'Super Admin'");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    // Set specific session variables for display
    $_SESSION['superadmin_name'] = $row['first_name'] . ' ' . $row['last_name'];
    $_SESSION['superadmin_icon'] = !empty($row['icon']) ? $row['icon'] : 'img/default_pfp.png';
} else {
    // Default values if something goes wrong
    $_SESSION['superadmin_name'] = "Super Admin";
    $_SESSION['superadmin_icon'] = 'img/default_pfp.png';
}
$stmt->close();

// Get the total number of admins ('Moderators')
$adminCountQuery = "SELECT COUNT(*) AS total FROM users WHERE user_type = 'Admin'";
$adminCountResult = $conn->query($adminCountQuery);
$adminCount = 0;
if ($adminCountResult) {
    $row = $adminCountResult->fetch_assoc();
    $adminCount = $row['total'];
}
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
  <link rel="stylesheet" href="css/clock.css" />
  <link rel="stylesheet" href="css/navigation.css"/>
   <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Home | SuperAdmin</title>
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
        <li class="navList active">
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
        <li class="navList"> 
            <a href="feedbacks.php"> <ion-icon name="star-outline"></ion-icon>
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

    <div id="homeContent" style="padding: 20px;">
      <section class="widget-section">
        <h2>SuperAdmin <span class="preview">Dashboard</span></h2>

        <section class="clock-section">
          <div class="clock-container">
            <div class="time">
              <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
              <span id="ampm">AM</span>
            </div>
            <div class="date" id="date"></div>
          </div>
        </section>

        <div class="widget-grid">
          <div class="widget blue full">
            <div class="details1">
              <h1>COACH Admin Security Hub</h1>
              <p>SuperAdmin Credential Management Panel</p>
              <p>Access Level: Restricted to Admin Account Control</p>
              <p class="note">
                This panel is strictly reserved for secure handling of Admin credentials. 
              </p>
            </div>
          </div>
          <div class="widget green full">
            <img src="../uploads/img/mentor.png" alt="Icon" class="img-icon" />
            <div class="details">
              <h3><?php echo $adminCount; ?></h3>
              <p>MODERATORS</p>
              <span class="note">Total Moderators</span>
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
  </section>

  <script src="js/navigation.js"></script>
  <script>
    const names = document.querySelector(".names")
    const email = document.querySelector(".email")
    const joined = document.querySelector(".joined")
    const navBar = document.querySelector("nav")
    const navToggle = document.querySelector(".navToggle")
    const navLinks = document.querySelectorAll(".navList")
    const darkToggle = document.querySelector(".darkToggle")
    const body = document.querySelector("body")


    navToggle.addEventListener('click',()=>{
        navBar.classList.toggle('close')
    })

    navLinks.forEach(function (element){
        element.addEventListener('click',function (){
            navLinks.forEach((e)=>{
                e.classList.remove('active')
                this.classList.add('active')
            })
        })
    })


    darkToggle.addEventListener('click',()=>{
        body.classList.toggle('dark')
    })


    const fetchedData = fetch("./data.json")
                        .then((data)=>{
                            return data.json()
                        })
                        .then(response=>{
                            console.log(response);
                            const items = response.item
                            let Name = ""
                            let Email = ""
                            let Joined = ""
                            
                            items.forEach((element)=>{
                                Name += `<span class="data-list">${element.name}</span>`
                                Email += `<span class="data-list">${element.email}</span>`
                                Joined += `<span class="data-list">${element.joined}</span>`
                            })
                            names.innerHTML += Name 
                            email.innerHTML += Email 
                            joined.innerHTML += Joined 
                        })
  </script>
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

  <script src="js/navigation.js"></script>
  <script>
    function updateClock() {
      const now = new Date();
      let hours = now.getHours();
      const minutes = now.getMinutes();
      const seconds = now.getSeconds();
      const ampm = hours >= 12 ? 'PM' : 'AM';

      hours = hours % 12 || 12;

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