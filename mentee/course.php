<?php
session_start();

// ==========================================================
// --- NEW: ANTI-CACHING HEADERS (Security Block) ---
// This is the critical block that prevents the browser from 
// caching the page content.
// ==========================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
// ==========================================================


// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // Redirect to the unified login page (one directory up)
    header("Location: ../login.php");
    exit();
}

// --- SESSION CHECK ---
if (!isset($_SESSION['username'])) {
  // Use the correct unified login page path (one directory up)
  header("Location: ../login.php"); 
  exit();
}

// --- FETCH USER ACCOUNT (if courses.php needs database access) ---
require '../connection/db_connection.php'; 


$firstName = '';
$menteeIcon = '';
$showWelcome = false;

if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
  $showWelcome = true;
  unset($_SESSION['login_success']); // prevent showing again
}

// Get username from session
$username = $_SESSION['username'];

// Fetch First_Name and Mentee_Icon from the database
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

// --- SEARCH HANDLER ---
$searchQuery = "";
if (isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
}

// --- FETCH COURSES ---
$sql = "SELECT * FROM courses";
$params = [];

if (!empty($searchQuery)) {
    $sql .= " WHERE Course_Title LIKE ?";
    $stmt = $conn->prepare($sql);
    $searchTerm = "%" . $searchQuery . "%";
    $stmt->bind_param("s", $searchTerm);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/navbar.css" />
 <link rel="stylesheet" href="css/courses.css?v=2">
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <title>Courses</title>
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
      <li><a href="#" onclick="confirmLogout()">Logout</a></li>
    </ul>
  </div>
</div>
    </nav>
  </section>

  <!-- Course Section -->
  <!-- Add this section right after the title "Choose what you want to learn!" -->
<section id="courses">
  <div class="title">Choose what you want to learn!</div>
  
<div class="main-container">
  <div class="filter-section">
      <div class="search-bar">
    <input type="text" id="courseSearch" placeholder="Search courses..." />
    <button type="button" id="searchBtn">Search</button>
  </div>
    <div class="filter-group">
      <h3>Category</h3>
      <div class="category-filters">
        <button class="course-filter-btn active" data-filter-type="category" data-filter-value="all">All</button>
        <button class="course-filter-btn" data-filter-type="category" data-filter-value="IT">Information Technology</button>
        <button class="course-filter-btn" data-filter-type="category" data-filter-value="CS">Computer Science</button>
        <button class="course-filter-btn" data-filter-type="category" data-filter-value="DS">Data Science</button>
        <button class="course-filter-btn" data-filter-type="category" data-filter-value="GD">Game Development</button>
        <button class="course-filter-btn" data-filter-type="category" data-filter-value="DAT">Digital Animation</button>
      </div>
    </div>
    
    <div class="filter-group">
      <h3>Level</h3>
      <div class="level-filters">
        <button class="course-filter-btn level-active" data-filter-type="level" data-filter-value="all">All Levels</button>
        <button class="course-filter-btn" data-filter-type="level" data-filter-value="Beginner">Beginner</button>
        <button class="course-filter-btn" data-filter-type="level" data-filter-value="Intermediate">Intermediate</button>
        <button class="course-filter-btn" data-filter-type="level" data-filter-value="Advanced">Advanced</button>
      </div>
    </div>
  </div>

  <!-- Courses Section -->
  <div class="course-area">
    <div class="course-grid">
      <?php
// Course section with filtering for Active status only
$sql = "SELECT * FROM courses WHERE Course_Status = 'Active' OR Course_Status IS NULL";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
  while ($course = $result->fetch_assoc()):
  ?>
    <div class="course-card" 
         data-category="<?php echo htmlspecialchars($course['Category'] ?? 'all'); ?>" 
         data-level="<?php echo htmlspecialchars($course['Skill_Level'] ?? 'Beginner'); ?>">
      <?php if (!empty($course['Course_Icon'])): ?>
        <img src="../uploads/<?php echo htmlspecialchars($course['Course_Icon']); ?>" alt="Course Icon">
      <?php endif; ?>
      <h2><?php echo htmlspecialchars($course['Course_Title']); ?></h2>
      <p><?php echo htmlspecialchars($course['Course_Description']); ?></p>
      <p><strong><?php echo htmlspecialchars($course['Skill_Level']); ?></strong></p>
      <form method="GET" action="sessions.php">
        <input type="hidden" name="course" value="<?= htmlspecialchars($course['Course_Title']) ?>">
        <button class="choose-btn">Choose</button>
      </form>
    </div>
  <?php endwhile; 
} else {
  echo '<div class="no-courses"><p>No active courses available at the moment.</p></div>';
}
?>
    </div>
  </section>

  <!-- Resource Library Section -->
<section id="resourcelibrary" class="resource-library" style="display: none;">
  <div class="resource-container">
    <div class="resource-left">
      <img src="../uploads/img/book.png" alt="Stack of Books" class="books"/>
    </div>
    <div class="resource-right">
      <h1>Learn more by reading files shared by your mentors!</h1>
      <p>
        Access valuable PowerPoint presentations, PDF files, and Video tutorials to enhance your learning journey 🚀
      </p>
      <div class="search-container">
        <input type="text" id="search-box" placeholder="Find resources by title or keyword...">
        <button onclick="performSearch()"><ion-icon name="search-outline"></ion-icon></button>
      </div>
    </div>
  </div>

  <div class="title">Check out the resources you can learn from!</div>
   <div class="button-wrapper">
  <div id="categoryButtons" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="category-btn active" data-category="all">All</button>
        <button class="category-btn" data-category="IT">Information Technology</button>
        <button class="category-btn" data-category="CS">Computer Science</button>
        <button class="category-btn" data-category="DS">Data Science</button>
        <button class="category-btn" data-category="GD">Game Development</button>
        <button class="category-btn" data-category="DAT">Digital Animation</button>
</div>
</div>
  <div class="resource-grid" id="resource-results">
    <?php
      // Fetch resources from the database
      $sql_resources = "SELECT Resource_ID, Resource_Title, Resource_Icon, Resource_Type, Resource_File, Category FROM resources WHERE Status = 'Approved'";
      $result_resources = $conn->query($sql_resources);

      if ($result_resources && $result_resources->num_rows > 0) {
        // Output data for each resource
        while ($resource = $result_resources->fetch_assoc()) {
          echo '<div class="course-card" data-category="' . htmlspecialchars($resource['Category']) . '" data-status="Approved">';
          if (!empty($resource['Resource_Icon'])) {
            // Ensure the path is correct, assuming icons are in an 'uploads' folder
            echo '<img src="../uploads/' . htmlspecialchars($resource['Resource_Icon']) . '" alt="Resource Icon">';
          }
          echo '<h2>' . htmlspecialchars($resource['Resource_Title']) . '</h2>';
          echo '<p><strong>Type: ' . htmlspecialchars($resource['Resource_Type']) . '</strong></p>';

          // --- FIXED VIEW BUTTON ---
          // Ensure Resource_File contains only the filename, not the full path yet
          $filePath = $resource['Resource_File']; // Get the filename from DB
          $fileTitle = $resource['Resource_Title'];

          // Construct the URL for view_resource.php
          // urlencode() is crucial for filenames/titles with spaces or special characters
          $viewUrl = 'view_resource.php?file=' . urlencode($filePath) . '&title=' . urlencode($fileTitle);

          // Create the link
          echo '<a href="' . htmlspecialchars($viewUrl) . '" class="view-btn" target="_blank">View</a>'; // Updated class from btn-view to view-btn
          // --- END FIXED VIEW BUTTON ---

          echo '</div>';
        }
      } else {
        echo "<p>No resources found.</p>";
      }
    ?>
  </div>
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

  <script src="js/mentee.js"></script>
  <script>
    const buttons = document.querySelectorAll('.category-btn');
    const resourceCards = document.querySelectorAll('#resource-results .course-card');

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
          cardCategory === 'all' ||
          cardStatus === selected
        ) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

      if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            // FIX: Redirect to the dedicated logout script in the parent folder (root)
            window.location.href = "../logout.php"; 
        });
    }

  function performSearch() {
  const query = document.getElementById('search-box').value;

  fetch('search_resources.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'query=' + encodeURIComponent(query)
  })
  .then(response => response.text())
  .then(data => {
    document.getElementById('resource-results').innerHTML = data;
  })
  .catch(error => console.error('Search error:', error));
}

// 👇 Real-time search as you type
document.getElementById('search-box').addEventListener('input', function () {
  performSearch();
});

document.addEventListener('DOMContentLoaded', function() {
    // Initialize course filtering
    initializeCourseFilters();
});

function initializeCourseFilters() {
    const filterButtons = document.querySelectorAll('.course-filter-btn');
    const courseCards = document.querySelectorAll('.course-card');
    
    // Track current filters
    let currentCategoryFilter = 'all';
    let currentLevelFilter = 'all';
    
    // Add event listeners to all filter buttons
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            const filterType = this.getAttribute('data-filter-type');
            const filterValue = this.getAttribute('data-filter-value');
            
            // Update active states
            if (filterType === 'category') {
                // Remove active class from all category buttons
                document.querySelectorAll('[data-filter-type="category"]').forEach(btn => 
                    btn.classList.remove('active')
                );
                // Add active class to clicked button
                this.classList.add('active');
                currentCategoryFilter = filterValue;
            } else if (filterType === 'level') {
                // Remove active class from all level buttons
                document.querySelectorAll('[data-filter-type="level"]').forEach(btn => 
                    btn.classList.remove('level-active')
                );
                // Add active class to clicked button
                this.classList.add('level-active');
                currentLevelFilter = filterValue;
            }
            
            // Apply filters
            applyFilters(currentCategoryFilter, currentLevelFilter);
        });
    });
    
    // Apply initial filter (show all)
    applyFilters('all', 'all');
}

function applyFilters(categoryFilter, levelFilter) {
    const courseCards = document.querySelectorAll('.course-card');
    let visibleCount = 0;
    
    courseCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        const cardLevel = card.getAttribute('data-level');
        
        // Check if card matches both filters
        const categoryMatch = categoryFilter === 'all' || 
                             cardCategory === categoryFilter || 
                             cardCategory === 'all';
        
        const levelMatch = levelFilter === 'all' || cardLevel === levelFilter;
        
        if (categoryMatch && levelMatch) {
            card.style.display = 'block';
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.style.display = 'none';
            card.classList.add('hidden');
        }
    });

// Show/hide "no courses" message
    updateNoCourseMessage(visibleCount);
}

function updateNoCourseMessage(visibleCount) {
    let noCourseMsg = document.querySelector('.no-courses-filtered');
    
    if (visibleCount === 0) {
        // Create or show "no courses" message
        if (!noCourseMsg) {
            noCourseMsg = document.createElement('div');
            noCourseMsg.className = 'no-courses-filtered';
            noCourseMsg.innerHTML = '<p>No courses match the selected filters.</p>';
            noCourseMsg.style.cssText = `
                text-align: center;
    padding: 20px;
    color: #6c757d;
    font-size: 18px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px dashed #dee2e6;
    margin-left: 400px;
    display: inline-block;   /* keep it as one box */
    min-width: 370px;        /* prevent text from wrapping */
    white-space: nowrap;     /* force text to stay in one line */
            `;
            document.querySelector('.course-grid').appendChild(noCourseMsg);
        }
        noCourseMsg.style.display = 'block';
    } else {
        // Hide "no courses" message
        if (noCourseMsg) {
            noCourseMsg.style.display = 'none';
        }
    }
}

// Category mapping for better display
const categoryMap = {
    'all': 'All Categories',
    'IT': 'Information Technology',
    'CS': 'Computer Science',
    'DS': 'Data Science',
    'GD': 'Game Development',
    'DAT': 'Digital Animation'
};

// Function to reset all filters
function resetFilters() {
    // Reset category filter
    document.querySelectorAll('[data-filter-type="category"]').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-filter-value') === 'all') {
            btn.classList.add('active');
        }
    });
    
    // Reset level filter
    document.querySelectorAll('[data-filter-type="level"]').forEach(btn => {
        btn.classList.remove('level-active');
        if (btn.getAttribute('data-filter-value') === 'all') {
            btn.classList.add('level-active');
        }
    });
    
    // Apply filters
    applyFilters('all', 'all');
}

// Optional: Add search functionality for courses
function addCourseSearch() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search courses...';
    searchInput.style.cssText = `
        padding: 10px 15px;
        border: 2px solid #dee2e6;
        border-radius: 25px;
        width: 100%;
        max-width: 300px;
        margin: 10px auto;
        display: block;
        font-size: 14px;
    `;
    
    // Insert search box before filters
    const filterSection = document.querySelector('.filter-section');
    if (filterSection) {
        filterSection.insertBefore(searchInput, filterSection.firstChild);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const courseCards = document.querySelectorAll('.course-card');
            
            courseCards.forEach(card => {
                const title = card.querySelector('h2').textContent.toLowerCase();
                const description = card.querySelector('p').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
}
// --- COURSE SEARCH FUNCTIONALITY ---
document.getElementById('searchBtn').addEventListener('click', function() {
    // Get the search term from the input field and convert to lowercase for a case-insensitive search
    const searchTerm = document.getElementById('courseSearch').value.toLowerCase().trim();

    // Select all course cards on the page
    const courseCards = document.querySelectorAll('.course-card');

    let visibleCount = 0;

    // Loop through each course card to check for a match
    courseCards.forEach(card => {
        // Get the title and description of the current card
        const title = card.querySelector('h2').textContent.toLowerCase();
        const description = card.querySelector('p').textContent.toLowerCase();

        // Check if the search term is included in either the course title or its description
        if (title.includes(searchTerm) || description.includes(searchTerm)) {
            card.style.display = 'block'; // Show the card if it's a match
            visibleCount++;
        } else {
            card.style.display = 'none'; // Hide the card if it doesn't match
        }
    });

    // Call the existing function to update the "no courses" message based on the search results
    updateNoCourseMessage(visibleCount);

});

  </script>

</body>
</html>

<?php $conn->close(); ?>
