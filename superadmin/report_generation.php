<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// SESSION CHECK: SuperAdmin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: ../login.php");
    exit();
}

// CONNECT TO DATABASE
require '../connection/db_connection.php';

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

// Fetch SuperAdmin details
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, first_name, last_name, icon FROM users WHERE user_id = ? AND user_type = 'Super Admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $_SESSION['superadmin_username'] = $row['username'];
    $_SESSION['superadmin_name'] = $row['first_name'] . ' ' . $row['last_name'];
    
    if (isset($row['icon']) && !empty($row['icon'])) {
        $_SESSION['superadmin_icon'] = $row['icon'];
    } else {
        $_SESSION['superadmin_icon'] = "../uploads/img/default_pfp.png";
    }
} else {
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
// TOP CONTRIBUTORS LEADERBOARD FETCHING
// =================================================================
$sql_contributors = "
    SELECT
        u.user_id,
        CONCAT(u.first_name, ' ', u.last_name) AS display_name,
        u.icon,
        u.user_type,
        COALESCE(SUM(CASE WHEN gf_posts.chat_type = 'forum' THEN 1 ELSE 0 END), 0) AS total_posts,
        COALESCE(SUM(CASE WHEN gf_posts.chat_type = 'comment' THEN 1 ELSE 0 END), 0) AS total_comments,
        COALESCE(COUNT(pl.post_id), 0) AS total_likes_received 
    FROM
        users u
    LEFT JOIN
        general_forums gf_posts ON u.user_id = gf_posts.user_id
    LEFT JOIN
        post_likes pl ON pl.post_id = gf_posts.id
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
    <title>Report Analysis | SuperAdmin</title>

    <style>
        .table-card table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .table-card th, .table-card td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-card th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
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
        .rank-1 { background-color: #FFD700; color: #333; }
        .rank-2 { background-color: #C0C0C0; color: #333; }
        .rank-3 { background-color: #CD7F32; }

        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #6d28d9;
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

        .leaderboard-btn {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            background-color: #7c3aed;
            font-size: 1.125rem;
            font-weight: 500;
            color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            transition-property: all;
            transition-duration: 200ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }

        .leaderboard-btn:hover {
            background-color: #6d28d9;
            transform: scale(1.05);
        }
        
        .leaderboard-btn:disabled {
            background-color: #6b7280;
            cursor: not-allowed;
            transform: none;
            pointer-events: none;
        }
        
        .leaderboard-btn.loaded {
            background-color: #10b981;
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
            <li><a href="dashboard.php"><ion-icon name="home-outline"></ion-icon><span class="links">Home</span></a></li>
            <li><a href="moderators.php"><ion-icon name="lock-closed-outline"></ion-icon><span class="links">Moderators</span></a></li>
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
                $data = [];
                foreach ($courses as $i => $course) {
                    $data[] = [
                        'course' => $course,
                        'count'  => (int)$mentees_count[$i]
                    ];
                }

                usort($data, function($a, $b) {
                    return $b['count'] <=> $a['count'];
                });

                $top5 = array_slice($data, 0, 5);
                $maxValue = !empty($top5) ? max(array_column($top5, 'count')) : 0;

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
            <p class="text-sm text-gray-600 mb-3" id="user-info">Current User: <?php echo htmlspecialchars($_SESSION['superadmin_name']); ?> (<?php echo htmlspecialchars($_SESSION['user_type']); ?>)</p> 
            
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
    document.addEventListener("DOMContentLoaded", function () {
        const contributorsData = <?php echo json_encode($contributors); ?>;
        const insertButton = document.getElementById('insert-data-btn');
        const leaderboardBody = document.getElementById('leaderboard-body');
        const leaderboardContainer = document.getElementById('leaderboard-container');
        const loadMessage = document.getElementById('load-message');
        
        function renderLeaderboard(data) {
            leaderboardBody.innerHTML = '';
            
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
        
        if (insertButton) {
            insertButton.addEventListener('click', function() {
                insertButton.disabled = true;
                insertButton.innerHTML = '<span class="loading-spinner"></span> Loading Contributors...';
                insertButton.classList.remove('loaded'); 
                loadMessage.textContent = 'Fetching and processing data. Please wait...';

                setTimeout(() => {
                    renderLeaderboard(contributorsData);
                    leaderboardContainer.style.display = 'block';
                    insertButton.innerHTML = 'Data Loaded';
                    insertButton.classList.add('loaded');
                    loadMessage.textContent = 'Data successfully loaded from the database.';
                }, 1500);
            });
        }

        leaderboardBody.innerHTML = '';
    });

    document.getElementById("save-pdf").addEventListener("click", () => {
        const report = document.getElementById("report-content");
        const savePdfButton = document.getElementById("save-pdf");
        const insertDataButton = document.getElementById("insert-data-btn");
        const showAllBtn = document.getElementById("showAllBtn");

        savePdfButton.style.display = 'none';
        if (insertDataButton) {
            insertDataButton.style.display = 'none';
        }
        if (showAllBtn) {
            showAllBtn.style.display = 'none';
        }

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
                position = heightLeft - imgHeight + 20;
                pdf.addPage();
                pdf.addImage(imgData, "PNG", 20, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }

            pdf.save("report-analysis.pdf");

            savePdfButton.style.display = 'inline-block';
            if (insertDataButton) {
                insertDataButton.style.display = 'inline-block';
            }
            if (showAllBtn) {
                showAllBtn.style.display = 'inline-block';
            }
        });
    });

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
            updateChart(start, end);
        });

        const drp = $('input[name="daterange"]').data('daterangepicker');
        const startDate = moment().subtract(6, 'days');
        const endDate = moment();
        
        drp.setStartDate(startDate);
        drp.setEndDate(endDate);
        
        $('input[name="daterange"]').val(
            startDate.format('DD MMM YYYY') + ' - ' + endDate.format('DD MMM YYYY')
        );

        updateChart(startDate, endDate);
    });

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

    const navBar = document.querySelector("nav");
    const navToggle = document.querySelector(".navToggle");
    const body = document.body;

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
</section>
</body>
</html>