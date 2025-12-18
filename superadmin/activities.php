<?php
session_start();

// Standard session check for an admin user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: ../login.php");
    exit();
}

// Use your standard database connection
require '../connection/db_connection.php';

$currentUsername = $_SESSION['username'];
$stmtUser = $conn->prepare("SELECT user_id, first_name, last_name, icon, user_type FROM users WHERE username = ?");
$stmtUser->bind_param("s", $currentUsername);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows > 0) {
    $user = $resultUser->fetch_assoc();
    if (!in_array($user['user_type'], ['Admin', 'Super Admin'])) {
        header("Location: ../login.php");
        exit();
    }
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['admin_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['admin_icon'] = $user['icon'] ?: '../uploads/img/default-admin.png';
} else {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$stmtUser->close();

// --- Action Handling ---

// 1. APPROVE Activity
if (isset($_POST['approve_activity'])) {
    $activity_id = (int)$_POST['activity_id'];
    
    $sql = "UPDATE activities SET Status = 'Approved', Admin_Remarks = NULL WHERE Activity_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $activity_id);
    
    if ($stmt->execute()) {
        $message = "✅ Activity approved successfully!";
        $message_type = 'success';
        $active_tab = 'pending';
    } else {
        $message = "❌ Error approving activity: " . $stmt->error;
        $message_type = 'error';
    }
    $stmt->close();
}

// 2. REJECT Activity
if (isset($_POST['reject_activity'])) {
    $activity_id = (int)$_POST['activity_id'];
    $admin_remarks = trim($_POST['admin_remarks']);
    
    if (empty($admin_remarks)) {
        $message = "⚠️ Please provide a reason for rejection.";
        $message_type = 'warning';
    } else {
        $sql = "UPDATE activities SET Status = 'Rejected', Admin_Remarks = ? WHERE Activity_ID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $admin_remarks, $activity_id);
        
        if ($stmt->execute()) {
            $message = "✅ Activity rejected with remarks.";
            $message_type = 'success';
            $active_tab = 'pending';
        } else {
            $message = "❌ Error rejecting activity: " . $stmt->error;
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// 3. DELETE Activity (Admin can delete any activity)
if (isset($_POST['delete_activity_id'])) {
    $activity_id = (int)$_POST['delete_activity_id'];
    
    $conn->begin_transaction();
    try {
        // Delete related submissions
        $stmt = $conn->prepare("DELETE FROM submissions WHERE Activity_ID = ?");
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        
        // Delete assigned activities
        $stmt = $conn->prepare("DELETE FROM assigned_activities WHERE Activity_ID = ?");
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        
        // Delete the activity itself
        $stmt = $conn->prepare("DELETE FROM activities WHERE Activity_ID = ?");
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        
        $conn->commit();
        $message = "✅ Activity deleted successfully.";
        $message_type = 'success';
        $active_tab = 'all';
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $message = "❌ Error deleting activity: " . $e->getMessage();
        $message_type = 'error';
        $active_tab = 'all';
    }
}

// --- Fetch Tab Data for Display ---

// Fetch all courses for filtering
$allCourses = [];
$courses_sql = "SELECT DISTINCT Course_ID, Course_Title FROM courses ORDER BY Course_Title ASC";
$courses_result = $conn->query($courses_sql);
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $allCourses[] = $row;
    }
}

// Fetch all mentors for filtering
$allMentors = [];
$mentors_sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE user_type = 'Mentor' ORDER BY full_name ASC";
$mentors_result = $conn->query($mentors_sql);
if ($mentors_result) {
    while ($row = $mentors_result->fetch_assoc()) {
        $allMentors[] = $row;
    }
}

if (isset($conn) && $conn->ping()) {
    
    // Pending Activities (Status = 'Pending')
    $pendingActivities = [];
    $pending_sql = "
        SELECT a.*, c.Course_Title, u.first_name, u.last_name 
        FROM activities a 
        JOIN courses c ON a.Course_ID = c.Course_ID 
        JOIN users u ON a.Mentor_ID = u.user_id
        WHERE a.Status = 'Pending' 
        ORDER BY a.Created_At DESC
    ";
    $pendingResult = $conn->query($pending_sql);
    while ($row = $pendingResult->fetch_assoc()) {
        $row['Mentor_Name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $pendingActivities[] = $row;
    }
    
    // Approved Activities
    $approvedActivities = [];
    $approved_sql = "
        SELECT a.*, c.Course_Title, u.first_name, u.last_name,
               (SELECT COUNT(DISTINCT Mentee_ID) FROM assigned_activities aa WHERE aa.Activity_ID = a.Activity_ID) as Assigned_Count
        FROM activities a 
        JOIN courses c ON a.Course_ID = c.Course_ID 
        JOIN users u ON a.Mentor_ID = u.user_id
        WHERE a.Status = 'Approved' 
        ORDER BY a.Created_At DESC
    ";
    $approvedResult = $conn->query($approved_sql);
    while ($row = $approvedResult->fetch_assoc()) {
        $row['Mentor_Name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $approvedActivities[] = $row;
    }
    
    // Rejected Activities
    $rejectedActivities = [];
    $rejected_sql = "
        SELECT a.*, c.Course_Title, u.first_name, u.last_name 
        FROM activities a 
        JOIN courses c ON a.Course_ID = c.Course_ID 
        JOIN users u ON a.Mentor_ID = u.user_id
        WHERE a.Status = 'Rejected' 
        ORDER BY a.Created_At DESC
    ";
    $rejectedResult = $conn->query($rejected_sql);
    while ($row = $rejectedResult->fetch_assoc()) {
        $row['Mentor_Name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $rejectedActivities[] = $row;
    }
    
    // All Activities
    $allActivities = [];
    $all_sql = "
        SELECT a.*, c.Course_Title, u.first_name, u.last_name,
               (SELECT COUNT(DISTINCT Mentee_ID) FROM assigned_activities aa WHERE aa.Activity_ID = a.Activity_ID) as Assigned_Count
        FROM activities a 
        JOIN courses c ON a.Course_ID = c.Course_ID 
        JOIN users u ON a.Mentor_ID = u.user_id
        ORDER BY a.Created_At DESC
    ";
    $allResult = $conn->query($all_sql);
    while ($row = $allResult->fetch_assoc()) {
        $row['Mentor_Name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        $allActivities[] = $row;
    }
    
    $conn->close();
}

// Determine the initial active tab
$initial_tab = $active_tab ?? ($_GET['active_tab'] ?? 'pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activities | SuperAdmin</title>
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/superadmin_activities.css">
<link rel="stylesheet" href="css/courses.css" />
<link rel="stylesheet" href="css/navigation.css"/>
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body data-initial-tab="<?= htmlspecialchars($initial_tab) ?>">

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
        <li class="navList">
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
        <li class="navList active">
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
  <li class="navList logout-link">
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

    <div class="container">
        <h1 class="section-title">Activities Management</h1>
        
        <?php if (!empty($message)): ?>
            <div class="inline-msg <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="tab-navigation">
            <div class="tabs-list">
                <button class="tab-item" data-tab="pending">
                    <i class='bx bx-loader-circle'></i> Pending Review (<?= count($pendingActivities) ?>)
                </button>
                <button class="tab-item" data-tab="approved">
                    <i class='bx bx-check-circle'></i> Approved (<?= count($approvedActivities) ?>)
                </button>
                <button class="tab-item" data-tab="rejected">
                    <i class='bx bx-x-circle'></i> Rejected (<?= count($rejectedActivities) ?>)
                </button>
                <button class="tab-item" data-tab="all">
                    <i class='bx bx-list-ul'></i> All Activities (<?= count($allActivities) ?>)
                </button>
            </div>
        </div>

        <!-- PENDING TAB -->
        <div id="pending-tab" class="tab-content">
            <div class="filter-area" data-tab-name="pending">
                <div class="form-row">
                    <div class="form-group one-third">
                        <label for="filter_pending_mentor">Filter by Mentor:</label>
                        <select id="filter_pending_mentor" class="activity-filter form-control">
                            <option value="">All Mentors</option>
                            <?php foreach ($allMentors as $mentor): ?>
                                <option value="<?php echo htmlspecialchars($mentor['full_name']); ?>">
                                    <?php echo htmlspecialchars($mentor['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group one-third">
                        <label for="filter_pending_course">Filter by Course:</label>
                        <select id="filter_pending_course" class="activity-filter form-control">
                            <option value="">All Courses</option>
                            <?php foreach ($allCourses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['Course_Title']); ?>">
                                    <?php echo htmlspecialchars($course['Course_Title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group one-third">
                        <label for="filter_pending_search">Search Activity/Lesson:</label>
                        <input type="text" id="filter_pending_search" class="activity-filter form-control" placeholder="Title or Lesson">
                    </div>
                </div>
            </div>

            <?php if (empty($pendingActivities)): ?>
                <p class="small-msg success">No activities are currently pending review.</p>
            <?php else: ?>
                <div class="table-container">
                    <table id="pendingTable" class="styled-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Mentor</th>
                                <th>Course/Lesson</th>
                                <th>Created On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingActivities as $activity): ?>
                            <tr>
                                <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                                <td data-label="Mentor"><?= htmlspecialchars($activity['Mentor_Name']) ?></td>
                                <td data-label="Course/Lesson">
                                    <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                    <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                                </td>
                                <td data-label="Created On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                                <td data-label="Status">
                                    <span class="status-tag <?= strtolower($activity['Status']) ?>">
                                        <?= htmlspecialchars($activity['Status']) ?>
                                    </span>
                                </td>
                                <td data-label="Actions" class="action-cell">
                                    <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                        data-id="<?= $activity['Activity_ID'] ?>"
                                        data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                        data-mentor="<?= htmlspecialchars($activity['Mentor_Name']) ?>"
                                        data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                        data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                        data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                        data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                        data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    <button class="action-btn approve-activity-btn" title="Approve Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                        <i class='bx bx-check' style="color: #28a745;"></i>
                                    </button>
                                    <button class="action-btn reject-activity-btn" title="Reject Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                        <i class='bx bx-x' style="color: #dc3545;"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- APPROVED TAB -->
        <div id="approved-tab" class="tab-content">
            <div class="filter-area" data-tab-name="approved">
                <div class="form-row">
                    <div class="form-group one-third">
                        <label for="filter_approved_mentor">Filter by Mentor:</label>
                        <select id="filter_approved_mentor" class="activity-filter form-control">
                            <option value="">All Mentors</option>
                            <?php foreach ($allMentors as $mentor): ?>
                                <option value="<?php echo htmlspecialchars($mentor['full_name']); ?>">
                                    <?php echo htmlspecialchars($mentor['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group one-third">
                        <label for="filter_approved_course">Filter by Course:</label>
                        <select id="filter_approved_course" class="activity-filter form-control">
                            <option value="">All Courses</option>
                            <?php foreach ($allCourses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['Course_Title']); ?>">
                                    <?php echo htmlspecialchars($course['Course_Title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group one-third">
                        <label for="filter_approved_search">Search Activity/Lesson:</label>
                        <input type="text" id="filter_approved_search" class="activity-filter form-control" placeholder="Title or Lesson">
                    </div>
                </div>
            </div>

            <?php if (empty($approvedActivities)): ?>
                <p class="small-msg warning">No activities have been approved yet.</p>
            <?php else: ?>
                <div class="table-container">
                    <table id="approvedTable" class="styled-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Mentor</th>
                                <th>Course/Lesson</th>
                                <th>Approved On</th>
                                <th>Assigned</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvedActivities as $activity): ?>
                            <tr>
                                <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                                <td data-label="Mentor"><?= htmlspecialchars($activity['Mentor_Name']) ?></td>
                                <td data-label="Course/Lesson">
                                    <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                    <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                                </td>
                                <td data-label="Approved On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                                <td data-label="Assigned"><?= $activity['Assigned_Count'] ?> mentee(s)</td>
                                <td data-label="Actions" class="action-cell">
                                    <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                        data-id="<?= $activity['Activity_ID'] ?>"
                                        data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                        data-mentor="<?= htmlspecialchars($activity['Mentor_Name']) ?>"
                                        data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                        data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                        data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                        data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                        data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                        <i class='bx bx-show'></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- REJECTED TAB -->
        <div id="rejected-tab" class="tab-content">
            <?php if (empty($rejectedActivities)): ?>
                <p class="small-msg success">No activities have been rejected.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Mentor</th>
                                <th>Course/Lesson</th>
                                <th>Rejected On</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rejectedActivities as $activity): ?>
                            <tr>
                                <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                                <td data-label="Mentor"><?= htmlspecialchars($activity['Mentor_Name']) ?></td>
                                <td data-label="Course/Lesson">
                                    <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                    <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                                </td>
                                <td data-label="Rejected On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                                <td data-label="Remarks" class="remarks-cell">
                                    <?= nl2br(htmlspecialchars($activity['Admin_Remarks'] ?? 'No remarks provided.')) ?>
                                </td>
                                <td data-label="Actions" class="action-cell">
                                    <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                        data-id="<?= $activity['Activity_ID'] ?>"
                                        data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                        data-mentor="<?= htmlspecialchars($activity['Mentor_Name']) ?>"
                                        data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                        data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                        data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                        data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                        data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                        <i class='bx bx-show'></i>
                                    </button>
                                    <button class="action-btn delete-activity-btn" title="Delete Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ALL ACTIVITIES TAB -->
        <div id="all-tab" class="tab-content">
            <div class="filter-area" data-tab-name="all">
                <div class="form-row">
                    <div class="form-group one-third">
                        <label for="filter_all_mentor">Filter by Mentor:</label>
                        <select id="filter_all_mentor" class="activity-filter form-control">
                            <option value="">All Mentors</option>
                            <?php foreach ($allMentors as $mentor): ?>
                                <option value="<?php echo htmlspecialchars($mentor['full_name']); ?>">
                                    <?php echo htmlspecialchars($mentor['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group one-third">
                        <label for="filter_all_status">Filter by Status:</label>
                        <select id="filter_all_status" class="activity-filter form-control">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group one-third">
                        <label for="filter_all_search">Search Activity:</label>
                        <input type="text" id="filter_all_search" class="activity-filter form-control" placeholder="Title or Lesson">
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table id="allTable" class="styled-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Mentor</th>
                            <th>Course/Lesson</th>
                            <th>Created On</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allActivities as $activity): ?>
                        <tr>
                            <td data-label="Title"><?= htmlspecialchars($activity['Activity_Title']) ?></td>
                            <td data-label="Mentor"><?= htmlspecialchars($activity['Mentor_Name']) ?></td>
                            <td data-label="Course/Lesson">
                                <strong><?= htmlspecialchars($activity['Course_Title']) ?></strong><br>
                                <small><?= htmlspecialchars($activity['Lesson']) ?></small>
                            </td>
                            <td data-label="Created On"><?= date('M d, Y', strtotime($activity['Created_At'])) ?></td>
                            <td data-label="Status">
                                <span class="status-tag <?= strtolower($activity['Status']) ?>">
                                    <?= htmlspecialchars($activity['Status']) ?>
                                </span>
                            </td>
                            <td data-label="Assigned"><?= $activity['Assigned_Count'] ?> mentee(s)</td>
                            <td data-label="Actions" class="action-cell">
                                <button class="action-btn preview-activity-btn" title="Preview Activity" 
                                    data-id="<?= $activity['Activity_ID'] ?>"
                                    data-title="<?= htmlspecialchars($activity['Activity_Title']) ?>"
                                    data-mentor="<?= htmlspecialchars($activity['Mentor_Name']) ?>"
                                    data-course="<?= htmlspecialchars($activity['Course_Title']) ?>"
                                    data-lesson="<?= htmlspecialchars($activity['Lesson']) ?>"
                                    data-status="<?= htmlspecialchars($activity['Status']) ?>"
                                    data-file="<?= htmlspecialchars($activity['File_Path'] ?? '') ?>"
                                    data-questions="<?= htmlspecialchars($activity['Questions_JSON']) ?>">
                                    <i class='bx bx-show'></i>
                                </button>
                                <?php if ($activity['Status'] === 'Pending'): ?>
                                    <button class="action-btn approve-activity-btn" title="Approve" data-id="<?= $activity['Activity_ID'] ?>">
                                        <i class='bx bx-check' style="color: #28a745;"></i>
                                    </button>
                                    <button class="action-btn reject-activity-btn" title="Reject" data-id="<?= $activity['Activity_ID'] ?>">
                                        <i class='bx bx-x' style="color: #dc3545;"></i>
                                    </button>
                                <?php endif; ?>
                                <button class="action-btn delete-activity-btn" title="Delete Activity" data-id="<?= $activity['Activity_ID'] ?>">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

<!-- APPROVE CONFIRMATION MODAL -->
<div id="approveConfirmDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Approval</h3>
        <p>Are you sure you want to approve this activity?</p>
        <div class="dialog-buttons">
            <button id="cancelApprove" type="button" class="secondary-button">Cancel</button>
            <form id="confirmApproveForm" method="POST" action="activities.php" style="display: inline;">
                <input type="hidden" name="activity_id" id="activityToApproveID" value="">
                <button type="submit" name="approve_activity" class="gradient-button">Approve</button>
            </form>
        </div>
    </div>
</div>

<!-- REJECT MODAL -->
<div id="rejectActivityModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Reject Activity</h2>
            <button class="action-btn" data-modal-close="#rejectActivityModal" title="Close">
                <i class='bx bx-x'></i>
            </button>
        </div>
        <form id="rejectForm" method="POST" action="activities.php">
            <input type="hidden" name="activity_id" id="activityToRejectID">
            
            <div class="form-group">
                <label for="admin_remarks">Reason for Rejection (Required):</label>
                <textarea id="admin_remarks" name="admin_remarks" rows="5" required placeholder="Provide a detailed reason for rejection..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close="#rejectActivityModal">Cancel</button>
                <button type="submit" name="reject_activity" class="gradient-button" style="background-color: #dc3545;">
                    <i class='bx bx-x'></i> Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PREVIEW ACTIVITY MODAL -->
<div id="previewActivityModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="previewModalTitle">Activity Preview</h2>
            <button class="action-btn" data-modal-close="#previewActivityModal" title="Close">
                <i class='bx bx-x'></i>
            </button>
        </div>
        
        <div class="activity-preview-details">
            <p><strong>Title:</strong> <span id="previewActivityName"></span></p>
            <p><strong>Mentor:</strong> <span id="previewMentorName"></span></p>
            <p><strong>Course:</strong> <span id="previewCourseTitle"></span></p>
            <p><strong>Lesson:</strong> <span id="previewLessonName"></span></p>
            <p><strong>Status:</strong> <span id="previewStatusTag" class="status-tag"></span></p>
            <a id="previewFileLink" href="#" target="_blank" class="preview-file-link" style="display: none;">
                <i class='bx bx-file'></i> View Attached File
            </a>
            <p id="noPreviewFileMsg" style="display: none;">No attached file.</p>
        </div>
        
        <div class="preview-questions-list">
            <h3 class="questions-heading">Questions</h3>
            <div id="previewQuestionsContainer"></div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="secondary-button" data-modal-close="#previewActivityModal">Close Preview</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION DIALOG -->
<div id="deleteConfirmDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete this activity? This action cannot be undone and will also delete all related submissions.</p>
        <div class="dialog-buttons">
            <button id="cancelDelete" type="button" class="secondary-button">Cancel</button>
            <form id="confirmDeleteForm" method="POST" action="activities.php" style="display: inline;">
                <input type="hidden" name="delete_activity_id" id="activityToDeleteID" value="">
                <button type="submit" class="gradient-button" style="background-color: #dc3545;">Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- LOGOUT DIALOG -->
<div id="logoutDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to log out?</p>
        <div class="dialog-buttons">
            <button id="cancelLogout" type="button" class="secondary-button">Cancel</button>
            <button id="confirmLogoutBtn" type="button" class="gradient-button">Logout</button>
        </div>
    </div>
</div>

<script src="js/navigation.js"></script>
<script src="js/superadmin_activities.js"></script>

</body>
</html>