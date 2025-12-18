<?php
session_start();

// 1. Set PHP's default timezone for all date/time functions
date_default_timezone_set('Asia/Manila');

// Database connection
require '../connection/db_connection.php'; // This assumes $conn is created here


// Create/update tables if they don't exist (aligned with new user_id schema)
$conn->query("
CREATE TABLE IF NOT EXISTS forum_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    course_title VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    time_slot VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    max_users INT DEFAULT 10
)");

$conn->query("
CREATE TABLE IF NOT EXISTS forum_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id)
)");

$conn->query("
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    display_name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_admin TINYINT(1) DEFAULT 0,
    is_mentor TINYINT(1) DEFAULT 0,
    chat_type ENUM('group', 'forum') DEFAULT 'group',
    forum_id INT NULL,
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL
)");

$conn->query("
CREATE TABLE IF NOT EXISTS session_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('active', 'left', 'review') DEFAULT 'active',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id)
)");

$conn->query("
CREATE TABLE IF NOT EXISTS video_participants (
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    peer_id VARCHAR(100) NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (forum_id, user_id)
)");

// SESSION CHECK: Use a single session variable for username
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}

// Get current user's information from the single 'users' table
$currentUserUsername = $_SESSION['username'];
$stmt = $conn->prepare("SELECT user_id, user_type, first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $currentUserUsername);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $currentUserId = $user['user_id'];
    $displayName = trim($user['first_name'] . ' ' . $user['last_name']);
    $firstName = $user['first_name'];
    $userType = $user['user_type'];
    $userIcon = $user['icon'];

    // Determine roles based on user_type
    $isAdmin = ($userType === 'Admin' || $userType === 'Super Admin');
    $isMentor = ($userType === 'Mentor');
} else {
    // If user not found in DB, log them out
    session_destroy();
    header("Location: ../login.php");
    exit();
}


// Handle message submission for forum chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forum_id']) && isset($_POST['action']) && $_POST['action'] === 'forum_chat') {
    $message = trim($_POST['message']);
    $forumId = $_POST['forum_id'];
    
    // Check if user is in review mode or has left the session
    $checkStatus = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
    $checkStatus->bind_param("ii", $forumId, $currentUserId);
    $checkStatus->execute();
    $statusResult = $checkStatus->get_result();
    
    if ($statusResult->num_rows > 0) {
        $participantStatus = $statusResult->fetch_assoc()['status'];
        if ($participantStatus === 'left' || $participantStatus === 'review') {
            header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
            exit();
        }
    }
    
    // Check if session is still active
    $sessionCheck = $conn->prepare("SELECT * FROM forum_chats WHERE id = ?");
    $sessionCheck->bind_param("i", $forumId);
    $sessionCheck->execute();
    $sessionResult = $sessionCheck->get_result();
    
    if ($sessionResult->num_rows > 0) {
        $session = $sessionResult->fetch_assoc();
        $today = date('Y-m-d');
        $currentTime = date('H:i');
        $timeRange = explode(' - ', $session['time_slot']);
        $endTime = date('H:i', strtotime($timeRange[1]));
        
        $isSessionActive = ($today < $session['session_date']) || ($today == $session['session_date'] && $currentTime <= $endTime);
        
        if (!$isSessionActive && !$isAdmin) {
            header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
            exit();
        }
    }
    
    if (!empty($message)) {
        // Inserts a new message using user_id as the identifier
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, display_name, message, is_admin, is_mentor, chat_type, forum_id) VALUES (?, ?, ?, ?, ?, 'forum', ?)");
        $stmt->bind_param("issiii", $currentUserId, $displayName, $message, $isAdmin, $isMentor, $forumId);
        $stmt->execute();
    }
    
    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
    exit();
}

// Handle forum creation (admin only)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_forum') {
    $title = trim($_POST['forum_title']);
    $courseTitle = $_POST['course_title'];
    $sessionDate = $_POST['session_date'];
    $timeSlot = $_POST['time_slot'];
    
    if (!empty($title)) {
        $stmt = $conn->prepare("INSERT INTO forum_chats (title, course_title, session_date, time_slot) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $courseTitle, $sessionDate, $timeSlot);
        $stmt->execute();
    }
    header("Location: forum-chat.php?view=forums");
    exit();
}

// Handle leaving a chat
if (isset($_GET['action']) && $_GET['action'] === 'leave_chat' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];
    
    $checkParticipant = $conn->prepare("SELECT id FROM session_participants WHERE forum_id = ? AND user_id = ?");
    $checkParticipant->bind_param("ii", $forumId, $currentUserId);
    $checkParticipant->execute();
    
    if ($checkParticipant->get_result()->num_rows > 0) {
        $updateStatus = $conn->prepare("UPDATE session_participants SET status = 'left' WHERE forum_id = ? AND user_id = ?");
        $updateStatus->bind_param("ii", $forumId, $currentUserId);
        $updateStatus->execute();
    } else {
        $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'left')");
        // --- FIX START: Corrected bind_param from "iis" to "ii" to match the 2 placeholders in the query ---
        $insertStatus->bind_param("ii", $forumId, $currentUserId);
        // --- FIX END ---
        $insertStatus->execute();
    }
    
    header("Location: feedback.php?forum_id=" . $forumId);
    exit();
}

// Handle joining a forum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['forum_id']) && $_POST['action'] === 'join_forum') {
    $forumId = $_POST['forum_id'];
    
    $stmt = $conn->prepare("SELECT f.*, COUNT(fp.id) as current_users FROM forum_chats f LEFT JOIN forum_participants fp ON f.id = fp.forum_id WHERE f.id = ? GROUP BY f.id");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $forum = $result->fetch_assoc();
        
        $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $forumId, $currentUserId);
        $stmt->execute();
        $participantResult = $stmt->get_result();
        
        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
        $checkLeft->bind_param("ii", $forumId, $currentUserId);
        $checkLeft->execute();
        $leftResult = $checkLeft->get_result();
        $hasLeft = ($leftResult->num_rows > 0 && $leftResult->fetch_assoc()['status'] === 'left');

        if ($participantResult->num_rows === 0 && !$hasLeft) {
            if ($forum['current_users'] < $forum['max_users'] || $isAdmin || $isMentor) {
                $today = date('Y-m-d');
                $currentTime = date('H:i');
                $timeRange = explode(' - ', $forum['time_slot']);
                $endTime = date('H:i', strtotime($timeRange[1]));
                
                $isSessionOver = ($today > $forum['session_date']) || ($today == $forum['session_date'] && $currentTime > $endTime);

                if ($isAdmin || $isMentor || !$isSessionOver) {
                    $stmt = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $forumId, $currentUserId);
                    $stmt->execute();
                    
                    $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status='active'");
                    $insertStatus->bind_param("ii", $forumId, $currentUserId);
                    $insertStatus->execute();
                    
                    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
                    exit();
                } else {
                    $stmt = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $forumId, $currentUserId);
                    $stmt->execute();
                    
                    $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'review') ON DUPLICATE KEY UPDATE status='review'");
                    $insertStatus->bind_param("ii", $forumId, $currentUserId);
                    $insertStatus->execute();
                    
                    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
                    exit();
                }
            } else {
                $error = "This forum is full (maximum 10 participants)";
            }
        } else {
            $today = date('Y-m-d');
            $currentTime = date('H:i');
            $timeRange = explode(' - ', $forum['time_slot']);
            $endTime = date('H:i', strtotime($timeRange[1]));
            
            $isSessionOver = ($today > $forum['session_date']) || ($today == $forum['session_date'] && $currentTime > $endTime);

            if ($hasLeft || ($isSessionOver && !$isAdmin && !$isMentor)) {
                header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
            } else {
                header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
            }
            exit();
        }
    } else {
        $error = "Forum not found";
    }
}

// Handle admin adding a user to a forum
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['forum_id'], $_POST['user_id']) && $_POST['action'] === 'add_user_to_forum') {
    $forumId = $_POST['forum_id'];
    $userIdToAdd = $_POST['user_id'];
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND user_type = 'Mentee'");
    $stmt->bind_param("i", $userIdToAdd);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $forumId, $userIdToAdd);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $forumId, $userIdToAdd);
            $stmt->execute();
            
            $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status='active'");
            $insertStatus->bind_param("ii", $forumId, $userIdToAdd);
            $insertStatus->execute();
            
            $success = "User added to forum successfully";
        } else {
            $error = "User is already in this forum";
        }
    } else {
        $error = "User not found or is not a mentee";
    }
}

// Handle removing a user from a forum (admin only)
if ($isAdmin && isset($_GET['remove_user_id'], $_GET['forum_id'])) {
    $userIdToRemove = $_GET['remove_user_id'];
    $forumId = $_GET['forum_id'];
    
    $stmt = $conn->prepare("DELETE FROM forum_participants WHERE forum_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $forumId, $userIdToRemove);
    $stmt->execute();
    
    $removeStatus = $conn->prepare("DELETE FROM session_participants WHERE forum_id = ? AND user_id = ?");
    $removeStatus->bind_param("ii", $forumId, $userIdToRemove);
    $removeStatus->execute();
    
    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
    exit();
}

$view = isset($_GET['view']) ? $_GET['view'] : 'forums';

$courses = [];
$coursesResult = $conn->query("SELECT Course_Title FROM courses");
if ($coursesResult) {
    while ($row = $coursesResult->fetch_assoc()) $courses[] = $row['Course_Title'];
}

$sessions = [];
$sessionsResult = $conn->query("SELECT Session_ID, Course_Title, Session_Date, Time_Slot FROM sessions ORDER BY Session_Date ASC");
if ($sessionsResult) {
    while ($row = $sessionsResult->fetch_assoc()) $sessions[] = $row;
}

// Fetch forums for listing
$forums = [];
if ($isAdmin || $isMentor) {
    $forumsResult = $conn->query("SELECT f.*, COUNT(fp.id) as current_users FROM forum_chats f LEFT JOIN forum_participants fp ON f.id = fp.forum_id GROUP BY f.id ORDER BY f.session_date ASC, f.time_slot ASC");
} else {
    $stmt = $conn->prepare("SELECT f.*, COUNT(fp2.id) as current_users FROM forum_chats f INNER JOIN forum_participants fp ON f.id = fp.forum_id AND fp.user_id = ? LEFT JOIN forum_participants fp2 ON f.id = fp2.forum_id GROUP BY f.id ORDER BY f.session_date ASC, f.time_slot ASC");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $forumsResult = $stmt->get_result();
}

if ($forumsResult) {
    while ($row = $forumsResult->fetch_assoc()) $forums[] = $row;
}

$forumDetails = null;
$forumParticipants = [];
$isReviewMode = false;
$hasLeftSession = false;

if ($view === 'forum' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];
    $stmt = $conn->prepare("SELECT f.*, COUNT(fp.id) as current_users FROM forum_chats f LEFT JOIN forum_participants fp ON f.id = fp.forum_id WHERE f.id = ? GROUP BY f.id");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $forumDetails = $result->fetch_assoc();
        
        if (!$isAdmin && !$isMentor) {
            $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $forumId, $currentUserId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                header("Location: forum-chat.php?view=forums");
                exit();
            }
        }
        
        $today = date('Y-m-d');
        $currentTime = date('H:i');
        $timeRange = explode(' - ', $forumDetails['time_slot']);
        $endTime = date('H:i', strtotime($timeRange[1]));
        
        $isSessionOver = ($today > $forumDetails['session_date']) || ($today == $forumDetails['session_date'] && $currentTime > $endTime);
        
        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
        $checkLeft->bind_param("ii", $forumId, $currentUserId);
        $checkLeft->execute();
        $leftResult = $checkLeft->get_result();
        
        if ($leftResult->num_rows > 0) {
            $hasLeftSession = ($leftResult->fetch_assoc()['status'] === 'left');
        }
        
        $isReviewMode = $isSessionOver || $hasLeftSession || (isset($_GET['review']) && $_GET['review'] === 'true');
        
        if ($isReviewMode && !$hasLeftSession) {
            $updateStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'review') ON DUPLICATE KEY UPDATE status = 'review'");
            $updateStatus->bind_param("ii", $forumId, $currentUserId);
            $updateStatus->execute();
        }
        
        // Fetch participants using JOIN with the new 'users' table
        $stmt = $conn->prepare("
            SELECT u.user_id, u.username, CONCAT(u.first_name, ' ', u.last_name) as display_name,
                   LOWER(u.user_type) as user_type, u.icon
            FROM forum_participants fp
            JOIN users u ON fp.user_id = u.user_id
            WHERE fp.forum_id = ?
        ");
        $stmt->bind_param("i", $forumId);
        $stmt->execute();
        $participantsResult = $stmt->get_result();
        
        if ($participantsResult->num_rows > 0) {
            while ($row = $participantsResult->fetch_assoc()) {
                $forumParticipants[] = $row;
            }
        }
    } else {
        header("Location: forum-chat.php?view=forums");
        exit();
    }
}

// Fetch all mentees for admin to add to forums
$allMentees = [];
if ($isAdmin) {
    $menteesResult = $conn->query("SELECT user_id, username, first_name, last_name FROM users WHERE user_type = 'Mentee' ORDER BY first_name, last_name");
    if ($menteesResult) {
        while ($row = $menteesResult->fetch_assoc()) $allMentees[] = $row;
    }
}

// Fetch messages for the current forum
$messages = [];
if ($view === 'forum' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];
    $stmt = $conn->prepare("SELECT cm.*, u.icon FROM chat_messages cm LEFT JOIN users u ON cm.user_id = u.user_id WHERE cm.chat_type = 'forum' AND cm.forum_id = ? ORDER BY cm.timestamp ASC LIMIT 100");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $messagesResult = $stmt->get_result();
    
    if ($messagesResult) {
        while ($row = $messagesResult->fetch_assoc()) $messages[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view === 'session' ? 'Session' : 'Session Chat'; ?></title>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/forum-chats.css" />
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        .logout-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none; /* Start hidden, toggled to 'flex' by JS */
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .logout-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .logout-content h3 {
            margin-top: 0;
            color: #562b63;
            font-family: 'Ubuntu', sans-serif; 
            font-size: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }

        .logout-content p {
            margin-bottom: 1.5rem;
            font-family: 'Ubuntu', sans-serif; 
            line-height: 1.4;
            font-size: 1rem;
        }

        .dialog-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .dialog-buttons button {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Ubuntu', sans-serif; 
            font-size: 1rem;
        }

        #cancelLogout {
            background-color: #f5f5f5;
            color: #333;
        }

        #cancelLogout:hover {
            background-color: #e0e0e0;
        }

        #confirmLogoutBtn {
            background: linear-gradient(to right, #5d2c69, #8a5a96);
            color: white;
        }

        #confirmLogoutBtn:hover {
            background: #5d2c69;
        }
    </style>

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
            <?php if (!empty($userIcon)): ?>
            <img src="<?php echo htmlspecialchars($userIcon); ?>" alt="User Icon" style="width: 35px; height: 35px; border-radius: 50%;">
            <?php else: ?>
            <ion-icon name="person-circle-outline" style="font-size: 35px;"></ion-icon>
            <?php endif; ?>
        </a>
    </div>

    <div class="sub-menu-wrap hide" id="profile-menu">
        <div class="sub-menu">
            <div class="user-info">
                <div class="user-icon">
                    <?php if (!empty($userIcon)): ?>
                    <img src="<?php echo htmlspecialchars($userIcon); ?>" alt="User Icon" style="width: 40px; height: 40px; border-radius: 50%;">
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
  </section>

    <?php if (isset($error)): ?>
        <div class="error-message">
            <ion-icon name="alert-circle-outline"></ion-icon>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success-message">
            <ion-icon name="checkmark-circle-outline"></ion-icon>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($view === 'forums'): ?>
        <div class="forums-container">
            <div class="forums-header">
                <h2>My Sessions</h2>
                <?php if ($isAdmin): ?>
                    <button class="create-forum-btn" onclick="openCreateForumModal()">
                        <ion-icon name="add-outline"></ion-icon>
                        Create Session
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="forums-grid">
                <?php if (empty($forums)): ?>
                    <p>No sessions available. <?php echo (!$isAdmin && !$isMentor) ? 'Book a session to get started.' : 'Create a session to get started.'; ?></p>
                <?php else: ?>
                    <?php foreach ($forums as $forum): ?>
                        <?php
                        $today = date('Y-m-d');
                        $currentTime = date('H:i');
                        $timeRange = explode(' - ', $forum['time_slot']);
                        $endTime = date('H:i', strtotime($timeRange[1]));
                        
                        if ($today < $forum['session_date']) {
                            $sessionStatus = 'Upcoming'; $statusClass = 'status-upcoming';
                        } elseif ($today == $forum['session_date'] && $currentTime < $endTime) {
                            $sessionStatus = 'Active'; $statusClass = 'status-active';
                        } else {
                            $sessionStatus = 'Ended'; $statusClass = 'status-ended';
                        }
                        
                        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
                        $checkLeft->bind_param("ii", $forum['id'], $currentUserId);
                        $checkLeft->execute();
                        $leftResult = $checkLeft->get_result();
                        $hasLeft = ($leftResult->num_rows > 0 && $leftResult->fetch_assoc()['status'] === 'left');
                        ?>
                        <div class="forum-card">
                            <h3>
                                <?php echo htmlspecialchars($forum['title']); ?>
                                <span class="session-status <?php echo $statusClass; ?>"><?php echo $sessionStatus; ?></span>
                            </h3>
                            <div class="details">
                                <p><ion-icon name="book-outline"></ion-icon> <?php echo htmlspecialchars($forum['course_title']); ?></p>
                                <p><ion-icon name="calendar-outline"></ion-icon> <?php echo htmlspecialchars($forum['session_date']); ?></p>
                                <p><ion-icon name="time-outline"></ion-icon> <?php echo htmlspecialchars($forum['time_slot']); ?></p>
                            </div>
                            
                            <div class="capacity">
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo ($forum['current_users'] / $forum['max_users']) * 100; ?>%;"></div>
                                </div>
                                <span class="capacity-text"><?php echo $forum['current_users']; ?>/<?php echo $forum['max_users']; ?></span>
                            </div>
                            
                            <div class="actions">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="join_forum">
                                    <input type="hidden" name="forum_id" value="<?php echo $forum['id']; ?>">
                                    
                                    <?php if ($sessionStatus === 'Ended' || $hasLeft): ?>
                                        <button type="submit" class="review-btn">Review</button>
                                    <?php else: ?>
                                        <button type="submit" class="join-btn" <?php echo ($forum['current_users'] >= $forum['max_users'] && !$isAdmin && !$isMentor) ? 'disabled' : ''; ?>>Join Session</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($isAdmin): ?>
            <div class="modal-overlay" id="createForumModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Create New Session</h3>
                        <button class="modal-close" onclick="closeCreateForumModal()">&times;</button>
                    </div>
                    <form class="modal-form" method="POST" action="">
                        <input type="hidden" name="action" value="create_forum">
                        <div class="form-group"><label for="forum_title">Session Title</label><input type="text" id="forum_title" name="forum_title" required></div>
                        <div class="form-group"><label for="course_title">Course</label><select id="course_title" name="course_title" required><option value="">-- Select Course --</option><?php foreach ($courses as $course): ?><option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label for="session_date">Date</label><input type="date" id="session_date" name="session_date" required min="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="form-group"><label for="time_slot">Time Slot</label><select id="time_slot" name="time_slot" required><option value="">-- Select Time Slot --</option><option value="9:00 AM - 10:00 AM">9:00 AM - 10:00 AM</option><option value="10:00 AM - 11:00 AM">10:00 AM - 11:00 AM</option><option value="11:00 AM - 12:00 PM">11:00 AM - 12:00 PM</option><option value="1:00 PM - 2:00 PM">1:00 PM - 2:00 PM</option><option value="2:00 PM - 3:00 PM">2:00 PM - 3:00 PM</option><option value="3:00 PM - 4:00 PM">3:00 PM - 4:00 PM</option><option value="4:00 PM - 5:00 PM">4:00 PM - 5:00 PM</option></select></div>
                        <div class="modal-actions"><button type="button" class="cancel-btn" onclick="closeCreateForumModal()">Cancel</button><button type="submit" class="submit-btn">Create Session</button></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($view === 'forum' && $forumDetails): ?>
        <div class="chat-container" style="margin-top: 70px;">
            <div class="forum-info">
                <?php if (!$isReviewMode && !$hasLeftSession): ?>
    <a href="#" 
       class="leave-chat-btn" 
       data-leave-url="forum-chat.php?view=forums&action=leave_chat&forum_id=<?php echo $forumDetails['id']; ?>"
       onclick="confirmLeaveChat(event)">
        <ion-icon name="exit-outline"></ion-icon> Leave Chat
    </a>                <?php else: ?>
                    <a href="forum-chat.php" class="leave-chat-btn"><ion-icon name="arrow-back-outline"></ion-icon> Back to Sessions</a>
                <?php endif; ?>
                
                <h2><?php echo htmlspecialchars($forumDetails['title']); ?></h2>
                <div class="details">
                    <div class="detail"><ion-icon name="book-outline"></ion-icon><span><?php echo htmlspecialchars($forumDetails['course_title']); ?></span></div>
                    <div class="detail"><ion-icon name="calendar-outline"></ion-icon><span><?php echo htmlspecialchars($forumDetails['session_date']); ?></span></div>
                    <div class="detail"><ion-icon name="time-outline"></ion-icon><span><?php echo htmlspecialchars($forumDetails['time_slot']); ?></span></div>
                    <div class="detail"><ion-icon name="people-outline"></ion-icon><span><?php echo count($forumParticipants); ?>/<?php echo $forumDetails['max_users']; ?> participants</span></div>
                    <?php
                    $today = date('Y-m-d'); $currentTime = date('H:i'); $timeRange = explode(' - ', $forumDetails['time_slot']); $endTime = date('H:i', strtotime($timeRange[1]));
                    if ($today < $forumDetails['session_date']) { $sessionStatus = 'Upcoming'; $statusClass = 'status-upcoming'; }
                    elseif ($today == $forumDetails['session_date'] && $currentTime < $endTime) { $sessionStatus = 'Active'; $statusClass = 'status-active'; }
                    else { $sessionStatus = 'Ended'; $statusClass = 'status-ended'; }
                    ?>
                    <div class="detail"><ion-icon name="information-circle-outline"></ion-icon><span>Status: <span class="session-status <?php echo $statusClass; ?>"><?php echo $sessionStatus; ?></span></span></div>
                </div>
                
                <div class="participants">
                    <h3>Participants</h3>
                    <div class="participant-list">
                        <?php foreach ($forumParticipants as $participant): ?>
                            <div class="participant <?php echo $participant['user_type']; ?>">
                                <?php if ($participant['user_type'] === 'admin'): ?><ion-icon name="shield-outline"></ion-icon>
                                <?php elseif ($participant['user_type'] === 'mentor'): ?><ion-icon name="school-outline"></ion-icon>
                                <?php else: ?><ion-icon name="person-outline"></ion-icon>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($participant['display_name']); ?></span>
                                
                                <?php if ($isAdmin && $currentUserId !== $participant['user_id'] && $participant['user_type'] === 'mentee'): ?>
                                    <a href="?view=forum&forum_id=<?php echo $forumDetails['id']; ?>&remove_user_id=<?php echo $participant['user_id']; ?>" class="remove-btn" title="Remove user" onclick="return confirm('Are you sure you want to remove this user from the forum?')"><ion-icon name="close-outline"></ion-icon></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if ($isAdmin): ?>
                    <form class="add-user-form" method="POST" action="">
                        <input type="hidden" name="action" value="add_user_to_forum">
                        <input type="hidden" name="forum_id" value="<?php echo $forumDetails['id']; ?>">
                        <div class="form-group">
                            <label for="user_id">Add User to Forum</label>
                            <select id="user_id" name="user_id" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($allMentees as $mentee):
                                    $isParticipant = in_array($mentee['user_id'], array_column($forumParticipants, 'user_id'));
                                    if (!$isParticipant): ?>
                                        <option value="<?php echo htmlspecialchars($mentee['user_id']); ?>"><?php echo htmlspecialchars($mentee['first_name'] . ' ' . $mentee['last_name']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit">Add User</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="messages-area" id="messages-container">
                <?php if ($isReviewMode || $hasLeftSession): ?>
                <div class="review-mode-banner">
                    <?php if ($hasLeftSession): ?>
                        <strong>Review Mode:</strong> You have left this session. You can review the conversation but cannot send new messages.
                    <?php else: ?>
                        <strong>Review Mode:</strong> This session has ended. You can review the conversation but cannot send new messages.
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!$isReviewMode && !$hasLeftSession && $sessionStatus === 'Active'): ?>
                <div class="video-call">
                    <a href="../video-call.php?forum_id=<?php echo $forumDetails['id']; ?>" class="join-video-btn">
                        <ion-icon name="videocam-outline"></ion-icon> Join Video Call
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="message-box">
                    <?php if (empty($messages)): ?>
                        <p class="no-messages">No messages yet. Start the conversation!</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo $msg['user_id'] == $currentUserId ? 'current-user' : ''; ?> <?php echo $msg['is_admin'] ? 'admin' : ($msg['is_mentor'] ? 'mentor' : 'user'); ?>">
                                <div class="sender">
                                    <?php if ($msg['is_admin']): ?><ion-icon name="shield-outline"></ion-icon>
                                    <?php elseif ($msg['is_mentor']): ?><ion-icon name="school-outline"></ion-icon>
                                    <?php else: ?><ion-icon name="person-outline"></ion-icon>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($msg['display_name']); ?>
                                </div>
                                <div class="content"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <?php if (!empty($msg['file_name'])): ?>
                                    <div class="file-attachment">
                                        <ion-icon name="document-outline"></ion-icon>
                                        <a href="<?php echo htmlspecialchars($msg['file_path']); ?>" target="_blank" download><?php echo htmlspecialchars($msg['file_name']); ?></a>
                                    </div>
                                <?php endif; ?>
                                <div class="timestamp"><?php echo date('M d, g:i a', strtotime($msg['timestamp'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="message-input">
                    <?php if (!$isReviewMode && !$hasLeftSession): ?>
                    <form class="message-form" method="POST" action="">
                        <input type="hidden" name="action" value="forum_chat">
                        <input type="hidden" name="forum_id" value="<?php echo $forumDetails['id']; ?>">
                        <div class="message-input-container">
                            <input type="text" name="message" placeholder="Type your message..." autocomplete="off" required>
                            <button type="submit"><ion-icon name="send-outline"></ion-icon></button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="message-form" style="opacity: 0.7;">
                        <div class="message-input-container">
                            <input type="text" placeholder="You cannot send messages in review mode" disabled>
                            <button disabled style="background-color: #ccc;"><ion-icon name="send-outline"></ion-icon></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

     <script src="js/navigation.js"></script>
  <script src="js/mentee.js"></script>


 <script>
    // --- UTILITY FUNCTIONS (PRESERVED) ---
    function scrollToBottom() {
        // --- FIX: Target the correct scrollable container ---
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }
    
    window.onload = scrollToBottom;
    
    function openCreateForumModal() {
        document.getElementById('createForumModal').classList.add('active');
    }
    
    function closeCreateForumModal() {
        document.getElementById('createForumModal').classList.remove('active');
    }
    
    // --- CHAT REFRESH LOGIC (PRESERVED) ---
    <?php if ($view === 'forum'): ?>
    setInterval(function() {
        const messagesContainer = document.getElementById('messages-container');
        if (!messagesContainer) return; // Stop if not on chat view

        const shouldScroll = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 5;

        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                // --- FIX START: Target the correct element '.message-box' for content ---
                const newMessages = doc.querySelector('.message-box');
                const currentMessages = document.querySelector('.message-box');
                
                if (newMessages && currentMessages && newMessages.innerHTML.length !== currentMessages.innerHTML.length) {
                    currentMessages.innerHTML = newMessages.innerHTML;
                    if(shouldScroll) {
                        scrollToBottom();
                    }
                }
                // --- FIX END ---
            })
            .catch(err => console.error('Failed to refresh chat:', err));
    }, 5000); // Refresh every 5 seconds
    <?php endif; ?>

    // --- DOM CONTENT LOADED (MODIFIED TO INCLUDE LOGOUT DIALOG LOGIC) ---
    document.addEventListener("DOMContentLoaded", function () {
        
        // Select all necessary elements for Profile and Logout
        const profileIcon = document.getElementById("profile-icon");
        const profileMenu = document.getElementById("profile-menu");
        // NEW/MODIFIED: Select logout dialog elements
        const logoutDialog = document.getElementById("logoutDialog");
        const cancelLogoutBtn = document.getElementById("cancelLogout");
        const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

        // ==========================================================
        // --- PROFILE MENU TOGGLE LOGIC (REPLACED/FIXED) ---
        // ==========================================================
        if (profileIcon && profileMenu) {
            profileIcon.addEventListener("click", function (e) {
                e.preventDefault();
                profileMenu.classList.toggle("show");
                profileMenu.classList.toggle("hide");
            });

            // Close menu when clicking elsewhere (using document listener for better capture)
            document.addEventListener("click", function (e) {
                if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                    profileMenu.classList.remove("show");
                    profileMenu.classList.add("hide");
                }
            });
        }
        
        // ==========================================================
        // --- LOGOUT DIALOG LOGIC (NEW) ---
        // ==========================================================
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
                // Redirect to the login page (or logout script)
                window.location.href = "../logout.php"; 
            });
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


    <div id="leaveChatDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Leaving Chat</h3>
        <p>Are you sure you want to leave this chat? You will only be able to view messages in read-only mode after leaving.</p>
        <div class="dialog-buttons">
            <button id="cancelLeave" type="button">Cancel</button>
            
            <button id="confirmLeaveBtn" type="button" class="dialog-action-button">Leave Chat</button>
        </div>
    </div>
</div>
</body>
</html>