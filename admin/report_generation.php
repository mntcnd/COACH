<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// SESSION CHECK: Allow Admin and Moderator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['Admin','Moderator'])) {
    header("Location: ../login.php");
    exit();
}

// CONNECT TO DATABASE
require '../connection/db_connection.php'; // Use your existing connection script


// ====================
// CHART DATA FETCHING ENDPOINT (Users Growth)
// ====================
if (isset($_GET['start']) && isset($_GET['end'])) {
    $start = $_GET['start'];
    $end   = $_GET['end'];
    $sql = "
        SELECT LOWER(user_type) as user_type, DATE(created_at) as date, COUNT(*) as total
        FROM users
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY LOWER(user_type), DATE(created_at)
        ORDER BY date ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    error_log(print_r($data, true));
    exit;
}


$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, first_name, last_name, icon FROM users WHERE user_id = ? AND user_type IN ('Admin', 'Moderator')");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // CORRECTED CODE:
    $row = $result->fetch_assoc();
    // Set session variables for use in the HTML
    $_SESSION['admin_username'] = $row['username']; // New variable name
    $_SESSION['admin_name'] = $row['first_name'] . ' ' . $row['last_name']; // New variable name
    
    if (isset($row['icon']) && !empty($row['icon'])) {
        $_SESSION['admin_icon'] = $row['icon']; // New variable name
    } else {
        $_SESSION['admin_icon'] = "../uploads/img/default_pfp.png"; // New variable name
    }
} else {
    // If user not found (e.g., deleted), log them out
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$stmt->close();


// ====================
// PERFORMANCE TRACKER: MENTEES PER COURSE
// ====================
$sql = "SELECT course_title, COUNT(*) as total_mentees 
        FROM session_bookings 
        GROUP BY course_title 
        ORDER BY total_mentees DESC";

$result = $conn->query($sql);

$courses = [];
$mentees_count = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $courses[] = $row['course_title'];
        $mentees_count[] = $row['total_mentees'];
    }
}


// ========================
// Total Counts for Info Cards
// ========================

// Count forum posts
$sql_forum = "SELECT COUNT(*) AS total_forum FROM general_forums WHERE chat_type = 'forum'";
$result_forum = $conn->query($sql_forum);
$row_forum = $result_forum->fetch_assoc();
$forum_count = $row_forum['total_forum'];

// Count forum comments
$sql_comment = "SELECT COUNT(*) AS total_comment FROM general_forums WHERE chat_type = 'comment'";
$result_comment = $conn->query($sql_comment);
$row_comment = $result_comment->fetch_assoc();
$comment_count = $row_comment['total_comment'];


// =================================================================
// 🔥 TOP CONTRIBUTORS LEADERBOARD FETCHING (THE FIX)
// =================================================================
$sql_contributors = "
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS display_name,
        u.icon,
        u.user_type,
        -- Count posts and comments made by the user
        COALESCE(SUM(CASE WHEN gf_posts.chat_type = 'forum' THEN 1 ELSE 0 END), 0) AS total_posts,
        COALESCE(SUM(CASE WHEN gf_posts.chat_type = 'comment' THEN 1 ELSE 0 END), 0) AS total_comments,
        -- Count total likes received for posts/comments created by this user
        COALESCE(COUNT(pl.post_id), 0) AS total_likes_received 
    FROM
        users u
    LEFT JOIN
        general_forums gf_posts ON u.user_id = gf_posts.user_id -- Used to count posts/comments made by 'u'
    LEFT JOIN
        post_likes pl ON pl.post_id = gf_posts.id -- <<< FIXED: Join likes to the post via post_id
    GROUP BY
        u.user_id, u.first_name, u.last_name, u.icon, u.user_type
    HAVING
        total_posts > 0 OR total_comments > 0 OR total_likes_received > 0
    ORDER BY
        (total_posts * 5) + (total_comments * 2) + total_likes_received DESC
    LIMIT 10
";

$result_contributors = $conn->query($sql_contributors); 
$contributors = $result_contributors->fetch_all(MYSQLI_ASSOC);
// =================================================================


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="css/dashboard.css" />
    <link rel="stylesheet" href="css/adminhomestyle.css" />
    <link rel="stylesheet" href="css/reportstyle.css" />
    <link rel="stylesheet" href="css/navigation.css"/>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css"/>
    <title>Report Analysis</title>

    <style>
        /* ADDED/MODIFIED STYLES for professional look */
        .table-card table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .table-card th, .table-card td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0; /* light gray */
        }
        .table-card th {
            background-color: #f7fafc; /* very light gray */
            font-weight: 600;
            color: #4a5568; /* darker text */
        }
        .table-card tr:last-child td {
            border-bottom: none;
        }
        .leaderboard-profile {
            display: flex;
            align-items: center;
        }
        .leaderboard-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        .rank-badge {
            display: inline-block;
            width: 24px;
            text-align: center;
            font-weight: bold;
            color: white;
            border-radius: 4px;
            margin-right: 10px;
        }
        .rank-1 { background-color: #FFD700; color: #333; } /* Gold */
        .rank-2 { background-color: #C0C0C0; color: #333; } /* Silver */
        .rank-3 { background-color: #CD7F32; } /* Bronze */

        /* Loading Spinner CSS (Simple version) */
        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #6d28d9; /* Indigo/Purple color */
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom button style class using Tailwind classes as base */
        .leaderboard-btn {
            /* Applied classes: px-6 py-2 bg-purple-600 text-lg font-medium text-white rounded-lg shadow-md */
            /* Added for reliability in case Tailwind is not fully set up to use @apply */
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            background-color: #7c3aed; /* bg-purple-600 */
            font-size: 1.125rem; /* text-lg */
            font-weight: 500; /* font-medium */
            color: #ffffff; /* text-white */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); /* shadow-md */
            transition-property: all;
            transition-duration: 200ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Hover styles (hover:bg-purple-700 transition duration-200 ease-in-out transform hover:scale-105) */
        .leaderboard-btn:hover {
            background-color: #6d28d9; /* hover:bg-purple-700 */
            transform: scale(1.05);
        }
        
        /* Loading State - replaces hover styles */
        .leaderboard-btn:disabled {
            background-color: #6b7280; /* bg-gray-500 */
            cursor: not-allowed;
            transform: none;
            pointer-events: none; /* ensures click won't re-trigger */
        }
        
        /* Loaded State - replaces hover styles */
        .leaderboard-btn.loaded {
            background-color: #10b981; /* bg-green-500 */
            transform: none;
            cursor: default;
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
                <span class="admin-name"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                <span class="admin-role">Moderator</span>
            </div>
            <a href="edit_profile.php?username=<?= urlencode($_SESSION['admin_username']) ?>" class="edit-profile-link" title="Edit Profile">
                <ion-icon name="create-outline" class="verified-icon"></ion-icon>
            </a>
        </div>
    </div>

    <div class="menu-items">
        <ul class="navLinks">
            <li><a href="dashboard.php"><ion-icon name="home-outline"></ion-icon><span class="links">Home</span></a></li>
            <li><a href="manage_mentees.php"><ion-icon name="person-outline"></ion-icon><span class="links">Mentees</span></a></li>
            <li><a href="manage_mentors.php"><ion-icon name="people-outline"></ion-icon><span class="links">Mentors</span></a></li>
            <li><a href="courses.php"><ion-icon name="book-outline"></ion-icon><span class="links">Courses</span></a></li>
            <li><a href="manage_session.php"><ion-icon name="calendar-outline"></ion-icon><span class="links">Sessions</span></a></li>
            <li><a href="feedbacks.php"><ion-icon name="star-outline"></ion-icon><span class="links">Feedback</span></a></li>
            <li><a href="channels.php"><ion-icon name="chatbubbles-outline"></ion-icon><span class="links">Channels</span></a></li>
            <li class="navList"><a href="activities.php"><ion-icon name="clipboard-outline"></ion-icon><span class="links">Activities</span></a></li>
            <li><a href="resource.php"><ion-icon name="library-outline"></ion-icon><span class="links">Resource Library</span></a></li>
            <li class="navList"><a href="reports.php"><ion-icon name="folder-outline"></ion-icon><span class="links">Reported Posts</span></a></li>
            <li class="navList"><a href="banned-users.php"><ion-icon name="person-remove-outline"></ion-icon><span class="links">Banned Users</span></a></li>
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
        <img src="../uploads/img/logo.png" alt="Logo"> 
    </div>
    
<div class="container" id="report-content">
    <div class="header">
        <div class="logo1">
            <div class="logo-image1"><img src="../uploads/img/coach3d.png" alt="Logo"></div>
            <div class="logo-name1">COACH Report Analysis</div>
            </div>
            <div style="margin: 20px 0; text-align: right;">
        <button id="save-pdf" class="btn">Save Report as PDF</button>
    
    <form method="POST" style="margin: 20px 0; text-align: right;">
            <input type="text" name="daterange" class="date-range" value="16 Mar 2020 - 21 Mar 2020" />
        </form>
    </div>
    </div>

<div class="top-cards">
    <div class="card1 performance-card">
        <h3>Performance Tracker (Top 5 Courses)</h3>
        <?php 
            // Combine courses and counts into one array
            $data = [];
            foreach ($courses as $i => $course) {
                $data[] = [
                    'course' => $course,
                    'count'  => (int)$mentees_count[$i]
                ];
            }

            // Sort by count (descending)
            usort($data, function($a, $b) {
                return $b['count'] <=> $a['count'];
            });

            // Slice top 5
            $top5 = array_slice($data, 0, 5);

            // Find max value for percentage width
            $maxValue = !empty($top5) ? max(array_column($top5, 'count')) : 0;

            // Loop through Top 5 only
            foreach ($top5 as $item): 
                $course  = htmlspecialchars($item['course']);
                $count   = $item['count'];
                $percent = ($maxValue > 0) ? ($count / $maxValue) * 100 : 0;
        ?>
            <div class="progress">
                <label><?php echo $course; ?> &nbsp; <?php echo $count; ?> mentees</label>
                <div class="progress-bar">
                    <div class="bar purple" style="width: <?php echo $percent; ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="view-all-container">
            <button id="showAllBtn" class="btn btn-view-all">View All Courses</button>
        </div>
    </div>

<div id="allCoursesModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span id="closeModal" class="close-btn">&times;</span>
        <h3>All Booked Sessions</h3>

        <?php 
            $maxValueAll = !empty($data) ? max(array_column($data, 'count')) : 0;
            foreach ($data as $item): 
                $course  = htmlspecialchars($item['course']);
                $count   = $item['count'];
                $percent = ($maxValueAll > 0) ? ($count / $maxValueAll) * 100 : 0;
        ?>
            <div class="progress">
                <label><?php echo $course; ?> &nbsp; <?php echo $count; ?> mentees</label>
                <div class="progress-bar">
                    <div class="bar green" style="width: <?php echo $percent; ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>


    <div class="card">
        <h3>New Users</h3>
        <canvas id="userChart"></canvas>
    </div>
</div>

    <div class="grid">
    <div class="card" style="text-align:center;">
        <h3>Forum Posts</h3>
        <div class="big-number"><?php echo number_format($forum_count); ?></div>
    </div>

    <div class="card" style="text-align:center;">
        <h3>Forum Comments</h3>
        <div class="big-number"><?php echo number_format($comment_count); ?></div>
    </div>
</div>

    <div class="container mx-auto p-4 md:p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Forum Contributor Leaderboard</h1>

        <div id="setup-panel" class="bg-white p-6 rounded-xl mb-6 shadow-lg"> 
            <h2 class="text-xl font-semibold mb-3 text-indigo-700">Display Leaderboard Data</h2>
            <p class="text-sm text-gray-600 mb-3" id="user-info">Current User: <?php echo htmlspecialchars($_SESSION['admin_name']); ?> (<?php echo htmlspecialchars($_SESSION['user_type']); ?>)</p> 
            
            <div id="mock-data-loader">
                <p class="text-sm text-gray-700 mb-4" id="load-message">Click below to load the Top Contributor data from the database:</p>
                
                <button id="insert-data-btn" class="leaderboard-btn">
                    View Top Contributors
                </button>
            </div>
        </div>
        <div class="card table-card bg-white rounded-xl" id="leaderboard-container" style="display:none;">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="rounded-tl-xl">Top Contributors - Forum</th>
                        <th>Forum Posts</th>
                        <th>Comments</th>
                        <th class="rounded-tr-xl">Likes Received</th>
                    </tr>
                </thead>
                <tbody id="leaderboard-body">
                    </tbody>
            </table>
        </div>
        </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="admin.js"></script>
    <script src="js/navigation.js"></script>
    <script>
    // ----------------------------------------------------
    // START: Leaderboard Show/Hide Logic with Loading State
    // ----------------------------------------------------
    document.addEventListener("DOMContentLoaded", function () {
        // Pass PHP data to a JavaScript variable
        const contributorsData = <?php echo json_encode($contributors); ?>;
        const insertButton = document.getElementById('insert-data-btn');
        const leaderboardBody = document.getElementById('leaderboard-body');
        const leaderboardContainer = document.getElementById('leaderboard-container');
        const loadMessage = document.getElementById('load-message');
        
        // Function to render the table rows
        function renderLeaderboard(data) {
            leaderboardBody.innerHTML = ''; // Clear existing content
            
            if (data.length === 0) {
                leaderboardBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-8 text-gray-500">
                            No forum contributions found.
                        </td>
                    </tr>
                `;
                return;
            }

            data.forEach((contributor, index) => {
                const rank = index + 1;
                let rankClass = '';
                if (rank === 1) rankClass = 'rank-1';
                else if (rank === 2) rankClass = 'rank-2';
                else if (rank === 3) rankClass = 'rank-3';

                const userIcon = contributor.icon && contributor.icon.trim() !== '' ? contributor.icon : "../uploads/img/default_pfp.png";
                const displayName = contributor.display_name;

                const row = `
                    <tr>
                        <td>
                            <div class="leaderboard-profile">
                                <span class="rank-badge ${rankClass}">${rank}</span>
                                <img src="${userIcon}" alt="PFP" />
                                <span>${displayName}</span>
                            </div>
                        </td>
                        <td>${contributor.total_posts}</td>
                        <td>${contributor.total_comments}</td>
                        <td>${contributor.total_likes_received}</td>
                    </tr>
                `;
                leaderboardBody.insertAdjacentHTML('beforeend', row);
            });
        }
        
        // Click event listener for the button
        if (insertButton) {
            insertButton.addEventListener('click', function() {
                // 1. Enter Loading State
                insertButton.disabled = true;
                // Add the loading spinner and change button text
                insertButton.innerHTML = '<span class="loading-spinner"></span> Loading Contributors...';
                
                // Add custom styles for the disabled/loading state (defined in <style> block)
                insertButton.classList.remove('loaded'); 
                
                loadMessage.textContent = 'Fetching and processing data. Please wait...';

                // 2. Simulate Delay (1.5 seconds)
                setTimeout(() => {
                    // 3. Render and Show Data
                    renderLeaderboard(contributorsData);
                    leaderboardContainer.style.display = 'block';
                    
                    // 4. Update Button/Message State
                    insertButton.innerHTML = 'Data Loaded';
                    
                    // Add custom class for the loaded state (defined in <style> block)
                    insertButton.classList.add('loaded');
                    loadMessage.textContent = 'Data successfully loaded from the database.';

                }, 1500); // 1500 milliseconds = 1.5 seconds simulation
            });
        }

        // Initially ensure the leaderboard is cleared (it's hidden by CSS)
        leaderboardBody.innerHTML = '';
    });
    // ----------------------------------------------------
    // END: Leaderboard Show/Hide Logic with Loading State
    // ----------------------------------------------------


    // Save PDF (MODIFIED)
document.getElementById("save-pdf").addEventListener("click", () => {
    const report = document.getElementById("report-content");
    const savePdfButton = document.getElementById("save-pdf"); // Get the Save PDF button
    const insertDataButton = document.getElementById("insert-data-btn"); // Get the View Top Contributors button
    const showAllBtn = document.getElementById("showAllBtn"); // Get the View All Courses button

    // --- 1. Temporarily hide the buttons and any other unwanted elements ---
    savePdfButton.style.display = 'none';
    if (insertDataButton) { // Check if the button exists before trying to hide it
        insertDataButton.style.display = 'none';
    }
    if (showAllBtn) { // Check if the button exists before trying to hide it
        showAllBtn.style.display = 'none';
    }
    // Add any other elements you want to hide:
    // const someOtherElement = document.getElementById("some-other-id");
    // if (someOtherElement) { someOtherElement.style.display = 'none'; }


    // Use html2canvas to capture the content
    html2canvas(report, { scale: 2 }).then(canvas => {
        const imgData = canvas.toDataURL("image/png");
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF("p", "pt", "a4");
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const imgWidth = pageWidth - 40;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;

        let heightLeft = imgHeight;
        let position = 20;

        pdf.addImage(imgData, "PNG", 20, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft > 0) {
            position = heightLeft - imgImgHeight + 20; // Corrected variable name from imgHeight
            pdf.addPage();
            pdf.addImage(imgData, "PNG", 20, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        pdf.save("report-analysis.pdf");

        // --- 2. Re-show the buttons after PDF generation ---
        savePdfButton.style.display = 'inline-block'; // Or 'block', 'flex', etc., depending on its original display type
        if (insertDataButton) {
            insertDataButton.style.display = 'inline-block'; // Revert to its original display style
        }
        if (showAllBtn) {
            showAllBtn.style.display = 'inline-block'; // Revert to its original display style
        }
        // Re-show any other elements you hid:
        // if (someOtherElement) { someOtherElement.style.display = 'block'; }

    });
});

    // jQuery: Users chart + date range (MODIFIED FOR INITIAL LOAD FIX)
    $(function() {
        const ctxUsers = document.getElementById('userChart').getContext('2d');
        let userChart = new Chart(ctxUsers, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    { label: 'Mentees', data: [], backgroundColor: '#6a0dad' },
                    { label: 'Mentors', data: [], backgroundColor: '#0d6efd' },
                    { label: 'Admins',  data: [], backgroundColor: '#28a745' }
                ]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        // Define the function that fetches and updates the chart data
        const updateChart = (start, end) => {
            $.getJSON("<?php echo basename(__FILE__); ?>", {
                start: start.format('YYYY-MM-DD'),
                end:   end.format('YYYY-MM-DD')
            }, function(response) {
                let labels = [];
                let current = start.clone();
                while (current <= end) {
                    labels.push(current.format('DD MMM'));
                    current.add(1, 'days');
                }
                let menteeData = Array(labels.length).fill(0);
                let mentorData = Array(labels.length).fill(0);
                let adminData  = Array(labels.length).fill(0);

                response.forEach(row => {
                    let dateLabel = moment(row.date).format('DD MMM');
                    let idx = labels.indexOf(dateLabel);
                    if (idx !== -1) {
                        if (row.user_type === 'mentee') menteeData[idx] = parseInt(row.total);
                        if (row.user_type === 'mentor') mentorData[idx] = parseInt(row.total);
                        if (row.user_type === 'admin')  adminData[idx]  = parseInt(row.total);
                    }
                });

                userChart.data.labels = labels;
                userChart.data.datasets[0].data = menteeData;
                userChart.data.datasets[1].data = mentorData;
                userChart.data.datasets[2].data = adminData;
                userChart.update();
            });
        }

        $('input[name="daterange"]').daterangepicker({
            opens: 'left',
            locale: { format: 'DD MMM YYYY' },
            ranges: {
                'Yesterday':   [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 days': [moment().subtract(6, 'days'), moment()],
                'Last 14 days':[moment().subtract(13, 'days'), moment()],
                'Last 28 days':[moment().subtract(27, 'days'), moment()],
                'Last 30 days':[moment().subtract(29, 'days'), moment()],
            }
        }, function(start, end) {
            // This function runs when the dates are applied (manually changed)
            updateChart(start, end);
        });
        
        // =========================================================
        // THE CRITICAL INITIAL LOAD FIX
        // =========================================================

        // 1. Get the current date range picker instance
        const drp = $('input[name="daterange"]').data('daterangepicker');

        // 2. Set the default range (Last 7 days)
        const startDate = moment().subtract(6, 'days');
        const endDate = moment();
        
        drp.setStartDate(startDate);
        drp.setEndDate(endDate);
        
        // 3. Manually set the input value to the default range for display
        $('input[name="daterange"]').val(
            startDate.format('DD MMM YYYY') + ' - ' + endDate.format('DD MMM YYYY')
        );

        // 4. Call the data fetch function directly with the default dates
        updateChart(startDate, endDate);

    });

    // 🔧 Modal functionality (kept)
    document.addEventListener("DOMContentLoaded", function () {
        const showAllBtn = document.getElementById("showAllBtn");
        const modal = document.getElementById("allCoursesModal");
        const closeModal = document.getElementById("closeModal");

        if (!showAllBtn || !modal || !closeModal) return;

        showAllBtn.addEventListener("click", function () {
            modal.style.display = "block";
        });

        closeModal.addEventListener("click", function () {
            modal.style.display = "none";
        });

        window.addEventListener("click", function (event) {
            if (event.target === modal) modal.style.display = "none";
        });
    });


            // --- Element Selection ---
        const navBar = document.querySelector("nav");
        const navToggle = document.querySelector(".navToggle");
        const body = document.body;

// --- Navigation Toggle ---
        if (navToggle && navBar) {
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