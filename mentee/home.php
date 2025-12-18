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

$firstName = '';
$menteeIcon = '';
$showWelcome = false;

if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
    $showWelcome = true;
    unset($_SESSION['login_success']); // Show only once
}

$username = $_SESSION['username'] ?? '';

// Fetch user data
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


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
  <link rel="stylesheet" href="css/course.css" />
  <link rel="stylesheet" href="css/home.css" />
  <link rel="stylesheet" href="css/footer.css">
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <title>Home</title>
</head>

<?php if ($showWelcome): ?>
<div id="welcomeModal" class="modal-overlay">
    <div class="modal-card">
        <h2>Welcome,<br><?php echo htmlspecialchars($firstName); ?>!</h2>
        <p>Ready to learn, grow, and shine? Let’s go! ✨</p>
        <button id="closeModalBtn">Okay</button>
    </div>
</div>
<?php endif; ?>

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




  <header class="hero">
    <video autoplay muted loop playsinline class="bg-video">
      <source src="../uploads/img/bgheader.mp4" type="video/mp4" />
      Your browser does not support the video tag.
    </video>
  
    <div class="hero-content">
      <h1>Welcome to Coach</h1>
      <p>
        We’re excited to have you here! At COACH, your journey of growth, learning, and connection begins. Let’s take that first step—together.
      </p>
    </div>
  </header>

  
  <section class="about-process-section">
    <div class="about-container">
      <div class="about-image">
        <img src="../uploads/img/pencil.gif" alt="Tools in cart">
      </div>
      <div class="about-content">
        <h4>START WITH COACH</h4>
        <p class="top-text">Welcome to the COACH Mentoring Community! We’re thrilled you’ve joined us, and your growth is at the heart of everything we do. Our dedicated mentors and supportive peers will guide you and encourage you every step of the way as you pursue your personal and professional goals.</p>
        <p class="bottom-text">Together, we will help you achieve these goals and celebrate each milestone. We believe in your potential, and at COACH, your success is our success.</p>
      </div>
    </div>

    <section class="skills-hunt-section">
      <div class="skills-container">
        <div class="skills-text">
          <p class="intro">New Here? COACH is Ready to Guide You</p>
          <h1>Begin Your Growth Journey Today.</h1>
          <p class="description">
            As a new member of the COACH community, you're stepping into a platform designed to elevate your skills, connect with mentors, and unlock your potential. Whether you're here to learn, grow, or lead—we're with you every step of the way.
          </p>
    
          <div class="startingstudent">
            <div class="feature-item">
              <img src="../uploads/img/logo1.png" alt="Icon 1" />
              <span>Start learning from our experts.</span>
            </div>
            <div class="feature-item">
              <img src="../uploads/img/logo2.png" alt="Icon 2" />
              <span>Enhance your skills with us now.</span>
            </div>
            <div class="feature-item">
              <img src="../uploads/img/logo3.png" alt="Icon 3" />
              <span>Do the professional level Course.</span>
            </div>
            <div class="feature-item">
              <img src="../uploads/img/logo4.png" alt="Icon 4" />
              <span>Develop real-world skills tailored to your goals.</span>
            </div>
          </div>
    
          <a href="course.php" class="cta-button">View All Courses</a>
        </div>
    
        <div class="skills-images">
          <div class="image-wrapper">
            <div class="shape-border"></div>
            <div class="shape-circle"></div>
            <img src="../uploads/img/laptop1.png" alt="Main Student" class="image-1" />
            <img src="../uploads/img/code1.gif" alt="Overlay Student" class="image-2" />
          </div>
        </div>
      </div>
    </section>
    

  
  <div class="process-section">
    <div class="process-box">
      <div class="step">
        <h3>01</h3>
        <p>EXPLORE &<br>DEFINE YOUR GOALS</p>
      </div>
      <div class="step">
        <h3>02</h3>
        <p>LEARN &<br>BUILD YOUR SKILLS</p>
      </div>
      <div class="step">
        <h3>03</h3>
        <p>APPLY &<br>SHOWCASE YOUR GROWTH</p>
      </div>
    </div>
  </div>
</section>


<section class="services">
  <div class="left">
    <h4>SERVICES</h4>
    <h2>Ready to Learn with COACH?</h2>
    <p>Before you dive in, take a moment to explore the features we’ve built just for you. From personalized mentorship matches to progress tracking and learning resources, everything here is designed to support your growth and success. Let’s make every step count—start by discovering what COACH has to offer!</p>
  </div>
  <div class="right">
      <div class="accordion">
        <div class="accordion-item">
          <div class="accordion-header">📚 Courses <span class="arrow">&#9654;</span></div>
          <div class="accordion-content">Dive into structured learning paths crafted by experts. Whether you're brushing up on basics or diving deep into advanced topics, our courses help you learn at your own pace and stay on track.</div>
        </div>
        <div class="accordion-item">
          <div class="accordion-header">📖 Resource Library <span class="arrow">&#9654;</span></div>
          <div class="accordion-content">Access a curated collection of articles, videos, guides, and tools. Everything you need to support your learning journey is just a click away.</div>
        </div>
        <div class="accordion-item">
          <div class="accordion-header">📝 Activities <span class="arrow">&#9654;</span></div>
          <div class="accordion-content">Put your knowledge into practice! From quizzes to reflection exercises, our activities are here to challenge and engage you in meaningful ways.</div>
        </div>
        <div class="accordion-item">
          <div class="accordion-header">📅 Sessions <span class="arrow">&#9654;</span></div>
          <div class="accordion-content">Connect with your mentor through scheduled group sessions. These are your opportunities to ask questions, get guidance, and grow through real conversations.</div>
        </div>
        <div class="accordion-item">
          <div class="accordion-header">💬 Forums <span class="arrow">&#9654;</span></div>
          <div class="accordion-content">Join the conversation! Share ideas, ask questions, and learn from fellow mentees and mentors in a safe, supportive community space.</div>
        </div>
      </div>
    </div>
</section>



<footer class="footer fade-in">
  <div class="footer-container">
    <div class="footer-section about">
      <h2 class="logo">COACH</h2>
      <p>COACH connects tech learners with industry mentors, empowering future tech leaders through personalized guidance, real-world insights, and hands-on collaboration.</p>
    </div>

    <div class="footer-section office">
      <h2>Location</h2>
      <p>BPSU - Main<br>Balanga, Bataan<br>Tenejero Capitol Drive 2100, Philippines</p>
      <p>Email: <a href="mailto:avinashdm@outlook.com">coachtech@gmail.com</a></p>
      <p>Phone: +63 - 9666592022</p>
    </div>

    <div class="footer-section links">
      <h2>Terms and Privacy</h2>
      <ul>
        <li><a href="#">About Coach</a></li>
        <li><a href="#">FAQs</a></li>
        <li><a href="#">Privacy Policy</a></li>
        <li><a href="#">Terms of Use</a></li>
        <li><a href="#">Code of Conduct</a></li>
      </ul>
    </div>

    <div class="footer-section newsletter">
      <h2>Subscription</h2>
      <form action="#">
        <div class="email-box">
          <i class="fas fa-envelope"></i>
          <input type="email" placeholder="Enter your email id" required>
          <button type="submit">Submit<i class="fas fa-arrow-right"></i></button>
        </div>
      </form>
      <div class="social-icons">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-whatsapp"></i></a>
        <a href="#"><i class="fab fa-pinterest"></i></a>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <p>© 2025-2026 COACH. All rights reserved</p>
  </div>
</footer>

 
<script src="mentee.js"></script>
<script>
// --- GLOBAL UTILITY FUNCTIONS ---

// Function to close the welcome modal (PRESERVED)
function closeModal() {
    const modal = document.getElementById("welcomeModal");
    if (modal) {
        modal.classList.remove("show");
    }
}

// NOTE: The old confirmLogout function is REMOVED and replaced by the fixed logic inside DOMContentLoaded.

// Expose closeModal to window object (PRESERVED)
window.closeModal = closeModal;


document.addEventListener("DOMContentLoaded", function () {
    console.log("✅ Main script and Navigation menu loaded");

    // ==========================================================
    // --- LOGOUT & PROFILE MENU LOGIC (MODIFIED SECTION) ---
    // ==========================================================

    // Select all necessary elements for Profile and Logout
    const profileIcon = document.getElementById("profile-icon");
    const profileMenu = document.getElementById("profile-menu");
    const logoutDialog = document.getElementById("logoutDialog");
    const cancelLogoutBtn = document.getElementById("cancelLogout");
    const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");
    
    // --- Profile Menu Toggle Logic (FIXED & MERGED) ---
    if (profileIcon && profileMenu) {
        profileIcon.addEventListener("click", function (e) {
            e.preventDefault();
            console.log("Profile icon clicked");
            profileMenu.classList.toggle("show");
            profileMenu.classList.toggle("hide");
        });
        
        // Close menu when clicking outside
        document.addEventListener("click", function (e) {
            if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                profileMenu.classList.remove("show");
                profileMenu.classList.add("hide");
            }
        });
    } else {
        console.error("Profile menu elements not found");
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
    



    // ==========================================================
    // --- ACCORDION LOGIC (PRESERVED) ---
    // ==========================================================
    const accordionItems = document.querySelectorAll(".accordion-item");
    
    accordionItems.forEach(item => {
        const header = item.querySelector(".accordion-header");
        const content = item.querySelector(".accordion-content");
        const arrow = header.querySelector(".arrow");
        
        header.addEventListener("click", () => {
            // Toggle active class
            item.classList.toggle("active");
            
            // Rotate arrow
            if (arrow) {
                arrow.style.transform = item.classList.contains("active") ? "rotate(90deg)" : "rotate(0deg)";
            }
            
            // Toggle content visibility
            if (content) {
                if (item.classList.contains("active")) {
                    content.style.maxHeight = content.scrollHeight + "px";
                } else {
                    content.style.maxHeight = "0px";
                }
            }
        });
    });


    // ==========================================================
    // --- WELCOME MODAL LOGIC (PRESERVED & CONSOLIDATED) ---
    // ==========================================================
    const modal = document.getElementById("welcomeModal");
    const closeBtn = document.getElementById("closeModalBtn");

    if(modal) {
        // Show modal
        modal.classList.add("show");

        // Auto-hide after 5 seconds
        setTimeout(() => {
            modal.classList.remove("show");
        }, 5000);

        // Close button click
        if(closeBtn) {
            closeBtn.addEventListener("click", () => {
                modal.classList.remove("show");
            });
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
