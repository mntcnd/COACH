<?php
session_start();

// Database connection
require '../connection/db_connection.php';

// SESSION CHECK - Updated for Super Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: ../login.php"); // Redirect if not a super admin
    exit();
}

// Get the logged-in super admin's information from the 'users' table
$currentUserId = $_SESSION['user_id'];
$displayName = "Unknown Super Admin"; // Default display name

$sql = "SELECT first_name, last_name FROM users WHERE user_id = ? AND user_type = 'Super Admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    // Construct the display name from first and last names
    $displayName = trim($row['first_name'] . ' ' . $row['last_name']);
    $_SESSION['super_admin_name'] = $displayName;
}

// Create session_participants table if it doesn't exist (to track who has left a session)
// This query is updated to use user_id instead of username
$conn->query("
CREATE TABLE IF NOT EXISTS session_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    status ENUM('active', 'left', 'review') DEFAULT 'active',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id)
)");

// Create forum_chats table if it doesn't exist
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

// Create forum_participants table if it doesn't exist - Updated to use user_id
$conn->query("
CREATE TABLE IF NOT EXISTS forum_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id)
)");

// Handle creating a new forum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_forum') {
    $title = trim($_POST['title']);
    $course_title = trim($_POST['course_title']);
    $session_date = $_POST['session_date'];
    $time_slot = trim($_POST['time_slot']);
    $max_users = (int)$_POST['max_users'];

    $stmt = $conn->prepare("INSERT INTO forum_chats (title, course_title, session_date, time_slot, max_users) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $course_title, $session_date, $time_slot, $max_users);
    
    if ($stmt->execute()) {
        $success = "Forum created successfully!";
    } else {
        $error = "Failed to create forum. Please try again.";
    }
}

// Handle editing a forum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_forum') {
    $forum_id = $_POST['forum_id'];
    $title = trim($_POST['title']);
    $course_title = trim($_POST['course_title']);
    $session_date = $_POST['session_date'];
    $time_slot = trim($_POST['time_slot']);
    $max_users = (int)$_POST['max_users'];

    $stmt = $conn->prepare("UPDATE forum_chats SET title = ?, course_title = ?, session_date = ?, time_slot = ?, max_users = ? WHERE id = ?");
    $stmt->bind_param("ssssii", $title, $course_title, $session_date, $time_slot, $max_users, $forum_id);
    
    if ($stmt->execute()) {
        $success = "Forum updated successfully!";
    } else {
        $error = "Failed to update forum. Please try again.";
    }
}

// Handle deleting a forum
if (isset($_GET['action']) && $_GET['action'] === 'delete_forum' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];
    $stmt = $conn->prepare("DELETE FROM forum_chats WHERE id = ?");
    $stmt->bind_param("i", $forumId);
    
    if ($stmt->execute()) {
        // Also delete associated participants and messages for cleanup
        $conn->prepare("DELETE FROM forum_participants WHERE forum_id = ?")->execute([$forumId]);
        $conn->prepare("DELETE FROM chat_messages WHERE forum_id = ?")->execute([$forumId]);
        $success = "Forum deleted successfully!";
    } else {
        $error = "Failed to delete forum. Please try again.";
    }
}

// Handle leaving a chat - Updated to use user_id
if (isset($_GET['action']) && $_GET['action'] === 'leave_chat' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];

    $checkParticipant = $conn->prepare("SELECT id FROM session_participants WHERE forum_id = ? AND user_id = ?");
    $checkParticipant->bind_param("ii", $forumId, $currentUserId);
    $checkParticipant->execute();
    $participantResult = $checkParticipant->get_result();

    if ($participantResult->num_rows > 0) {
        $updateStatus = $conn->prepare("UPDATE session_participants SET status = 'left' WHERE forum_id = ? AND user_id = ?");
        $updateStatus->bind_param("ii", $forumId, $currentUserId);
        $updateStatus->execute();
    } else {
        $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'left')");
        $insertStatus->bind_param("ii", $forumId, $currentUserId);
        $insertStatus->execute();
    }
    header("Location: forum-chat.php?view=forums");
    exit();
}

// Handle message submission for forum chat - Updated to use user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forum_id']) && isset($_POST['action']) && $_POST['action'] === 'forum_chat') {
    $message = trim($_POST['message']);
    $forumId = $_POST['forum_id'];

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

    $fileName = null;
    $filePath = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $uploadDir = '../uploads/chat_files/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = basename($_FILES['attachment']['name']);
        $uniqueName = uniqid() . '_' . $fileName;
        $filePath = $uploadDir . $uniqueName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $filePath)) {
            // File uploaded successfully
        } else {
            $filePath = null;
            $fileName = null;
        }
    }

    if (!empty($message) || $fileName) {
        $isAdmin = 1; 
        $isMentor = 0; // Admins are not mentors in this context
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, display_name, message, is_admin, is_mentor, chat_type, forum_id, file_name, file_path) VALUES (?, ?, ?, ?, ?, 'forum', ?, ?, ?)");
        $stmt->bind_param("issiiiss", $currentUserId, $displayName, $message, $isAdmin, $isMentor, $forumId, $fileName, $filePath);
        $stmt->execute();
    }

    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
    exit();
}

// Handle adding a user to a forum - Updated to use user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user_to_forum' && isset($_POST['forum_id']) && isset($_POST['user_id'])) {
    $forumId = $_POST['forum_id'];
    $userIdToAdd = $_POST['user_id'];

    $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $forumId, $userIdToAdd);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $forumId, $userIdToAdd);
        if ($stmt->execute()) {
            $success = "User added successfully!";
        } else {
            $error = "Failed to add user.";
        }
    } else {
        $error = "User is already in the forum.";
    }
    // Redirect to prevent issues with browser refresh
    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
    exit();
}

// Handle removing a user from a forum - Updated to use user_id
if (isset($_GET['action']) && $_GET['action'] === 'remove_user' && isset($_GET['forum_id']) && isset($_GET['user_id'])) {
    $forumId = $_GET['forum_id'];
    $userIdToRemove = $_GET['user_id'];

    $stmt = $conn->prepare("DELETE FROM forum_participants WHERE forum_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $forumId, $userIdToRemove);
    if ($stmt->execute()) {
        $success = "User removed successfully!";
    } else {
        $error = "Failed to remove user.";
    }
    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
    exit();
}


// Determine which view to show
$view = isset($_GET['view']) ? $_GET['view'] : 'forums';

// Fetch all mentees for admin to add to forums - Updated query for 'users' table
$allUsers = [];
$usersResult = $conn->query("SELECT user_id, first_name, last_name, username FROM users WHERE user_type IN ('Mentee', 'Mentor')");
if ($usersResult && $usersResult->num_rows > 0) {
    while ($row = $usersResult->fetch_assoc()) {
        $allUsers[] = $row;
    }
}

// Fetch forums for listing
$forums = [];
$forumsResult = $conn->query("
SELECT f.*, COUNT(fp.id) as current_users 
FROM forum_chats f 
LEFT JOIN forum_participants fp ON f.id = fp.forum_id 
GROUP BY f.id 
ORDER BY f.session_date ASC, f.time_slot ASC
");
if ($forumsResult && $forumsResult->num_rows > 0) {
    while ($row = $forumsResult->fetch_assoc()) {
        $forums[] = $row;
    }
}

// Fetch forum details if viewing a specific forum
$forumDetails = null;
$forumParticipants = [];
$isReviewMode = false;
$hasLeftSession = false;
$isAdmin = true; // This page is for admins

if ($view === 'forum' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];
    $stmt = $conn->prepare("
        SELECT f.*, COUNT(fp.id) as current_users 
        FROM forum_chats f 
        LEFT JOIN forum_participants fp ON f.id = fp.forum_id 
        WHERE f.id = ? 
        GROUP BY f.id
    ");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $forumDetails = $result->fetch_assoc();
        
        $today = date('Y-m-d');
        $currentTime = date('H:i');
        $timeRange = explode(' - ', $forumDetails['time_slot']);
        $endTime = date('H:i', strtotime($timeRange[1]));

        $isSessionOver = ($today > $forumDetails['session_date']) || 
                        ($today == $forumDetails['session_date'] && $currentTime > $endTime);

        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
        $checkLeft->bind_param("ii", $forumId, $currentUserId);
        $checkLeft->execute();
        $leftResult = $checkLeft->get_result();

        if ($leftResult->num_rows > 0) {
            $participantStatus = $leftResult->fetch_assoc()['status'];
            $hasLeftSession = ($participantStatus === 'left');
        }

        $isReviewMode = $isSessionOver || $hasLeftSession || (isset($_GET['review']) && $_GET['review'] === 'true');

        if ($isReviewMode && !$hasLeftSession) {
            if ($leftResult->num_rows > 0) {
                $updateStatus = $conn->prepare("UPDATE session_participants SET status = 'review' WHERE forum_id = ? AND user_id = ?");
                $updateStatus->bind_param("ii", $forumId, $currentUserId);
                $updateStatus->execute();
            } else {
                $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, 'review')");
                $insertStatus->bind_param("ii", $forumId, $currentUserId);
                $insertStatus->execute();
            }
        }

        // Fetch participants - Updated query for 'users' table
        $stmt = $conn->prepare("
            SELECT fp.user_id,
                   u.user_type,
                   CONCAT(u.first_name, ' ', u.last_name) as display_name
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

// Fetch messages for the current forum
$messages = [];
if ($view === 'forum' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];
    $stmt = $conn->prepare("
        SELECT * FROM chat_messages 
        WHERE chat_type = 'forum' AND forum_id = ? 
        ORDER BY timestamp ASC 
        LIMIT 500
    ");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $messagesResult = $stmt->get_result();

    if ($messagesResult->num_rows > 0) {
        while ($row = $messagesResult->fetch_assoc()) {
            $messages[] = $row;
        }
    }
}

$returnUrl = "dashboard.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view === 'forums' ? 'Forums' : 'Forum Chat'; ?> | SuperAdmin</title>
     <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
         /* Base Styles */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        
        :root {
            --primary-color: #6a2c70;
            --nav-color: #693B69;
            --dash-color: #fff;
            --logo-color: #fff;
            --text-color: #000;
            --text-color-light: #333;
            --white: #fff;
            --border-color: #ccc;
            --toggle-color: #fff;
            --title-icon-color: #fff;
            --admin-message-bg: #f0e6f5;
            --mentor-message-bg: #e6f0f5;
            --user-message-bg: #e6f5f0;
            --time-03: all 0.3s linear;
            --time-02: all 0.2s linear;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            width: 100%;
            min-height: 100vh;
            background-color: var(--dash-color);
            display: flex;
            flex-direction: column;
        }
        
        body.dark {
            --primary-color: #3a3b3c;
            --nav-color: #181919;
            --dash-color: #262629;
            --logo-color: #ecd4ea;
            --text-color: #ecd4ea;
            --text-color-light: #ccc;
            --white: #aaa;
            --border-color: #404040;
            --toggle-color: #693b69;
            --title-icon-color: #ddd;
            --admin-message-bg: #3a3a3a;
            --mentor-message-bg: #2a3a4a;
            --user-message-bg: #2a2a2a;
        }
        
        /* Header Styles */
        .chat-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .chat-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .chat-header .actions {
            display: flex;
            gap: 15px;
        }
        
        .chat-header button {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }
        
        .chat-header button:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .chat-header button ion-icon {
            font-size: 18px;
        }
        
        /* Forums List Styles */
        .forums-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
        }
        
        .forums-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .forums-header h2 {
            font-size: 1.5rem;
            color: var(--text-color);
        }
        
        .forums-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .forum-card {
            background-color: var(--dash-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .forum-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .forum-card h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        
        .forum-card .details {
            margin-bottom: 15px;
        }
        
        .forum-card .details p {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-color-light);
            font-size: 0.9rem;
        }
        
        .forum-card .details ion-icon {
            font-size: 16px;
            color: var(--primary-color);
        }
        
        .forum-card .capacity {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .forum-card .capacity-bar {
            flex: 1;
            height: 8px;
            background-color: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .forum-card .capacity-fill {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 4px;
        }
        
        .forum-card .capacity-text {
            font-size: 0.8rem;
            color: var(--text-color-light);
        }
        
        .forum-card .actions {
            display: flex;
            justify-content: space-between;
        }
        
        .forum-card .join-btn, .forum-card .review-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .forum-card .review-btn {
            background-color: #6c757d;
        }
        
        .forum-card .join-btn:hover {
            background-color: #5a2460;
        }
        
        .forum-card .review-btn:hover {
            background-color: #5a6268;
        }
        
        /* Session Status Badge */
        .session-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 10px;
            text-transform: uppercase;
        }
        
        .status-upcoming {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-ended {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Chat Container Styles */
        .chat-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(2, auto);
            gap: 24px;
            max-width: 1500px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
            height: 90vh;
            max-height: 2000px;
        }
        
        /* Forum Info */
        .forum-info {
            background-color: rgba(0, 0, 0, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            height: 100%;

        }
        
        .forum-info h2 {
            font-size: 1.5rem;
            margin-bottom: 16px;
            padding-top: 12px;
            color: var(--text-color);
            border-top: 1px solid var(--border-color);
        }
        
        .forum-info .details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .forum-info .detail {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--text-color-light);
            font-size: 0.9rem;
        }
        
        .forum-info .detail ion-icon {
            font-size: 16px;
            color: var(--primary-color);
        }
        
        .forum-info .participants {
            margin-top: 16px;
        }
        
        .forum-info .participants h3 {
            font-size: 1rem;
            margin-bottom: 12px;
            color: var(--text-color);
        }
        
        .forum-info .participant-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .forum-info .participant {
            display: flex;
            align-items: center;
            gap: 5px;
            background-color: #f5f5f5;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .forum-info .participant.admin {
            background-color: var(--admin-message-bg);
        }
        
        .forum-info .participant.mentor {
            background-color: var(--mentor-message-bg);
        }
        
        /* Messages Area */
        .messages-area {
            flex: 1;
            display: flex;             
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
            padding: 16px;
            background-color: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            grid-column: span 2;
            grid-row: span 2; 
            row-gap: 12px;
        }

        .message-box {
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .video-call {
            display: flex;
            justify-content: flex-end;
            padding: 0 16px 8px;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid var(--border-color);
        }

        .join-video-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #1e67f0;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .join-video-btn ion-icon {
            font-size: 16px;
        }

        .join-video-btn:hover {
            transform: translateY(-2px);
        }

        .join-video-btn:active {
            transform: scale(0.97); 
        }

        .message-input {
            padding: 16px 8px;
        }
        
        .message {
            margin-bottom: 15px;
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 8px;
            position: relative;
        }
        
        
        .message.admin {
            background-color: var(--admin-message-bg);
            align-self: flex-start;
            margin-left: auto;
            border-bottom-right-radius: 0;
        }
        
        .message.mentor {
            background-color: var(--mentor-message-bg);
            align-self: flex-start;
            margin-left: auto;
            border-bottom-right-radius: 0;
        }
        
        .message.user {
            background-color: var(--user-message-bg);
            align-self: flex-start;
            margin-right: auto;
            border-bottom-left-radius: 0;
        }
        
        .message .sender {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .message .sender ion-icon {
            font-size: 16px;
        }
        
        .message .content {
            word-break: break-word;
        }
        
        .message .timestamp {
            font-size: 0.7rem;
            color: #777;
            margin-top: 5px;
            text-align: right;
        }
        
        .message .file-attachment {
            margin-top: 10px;
            padding: 8px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message .file-attachment ion-icon {
            font-size: 20px;
            color: var(--primary-color);
        }
        
        .message .file-attachment a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .message .file-attachment a:hover {
            text-decoration: underline;
        }
        
        /* Message Form */
        .message-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .file-upload-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-upload-btn {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .file-upload-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .file-name {
            font-size: 0.9rem;
            color: var(--text-color-light);
        }
        
        .message-input-container {
            display: flex;
            gap: 10px;
        }
        
        .message-form input[type="text"] {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            background-color: var(--dash-color);
            color: var(--text-color);
        }
        
        .message-form input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(106, 44, 112, 0.2);
        }
        
        .message-form button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        
        .message-form button:hover {
            background-color: #5a2460;
        }
        
        .message-form button ion-icon {
            font-size: 20px;
        }
        
        /* Error Message */
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message ion-icon {
            font-size: 20px;
        }
        
        /* Review Mode Banner */
        .review-mode-banner {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        /* Leave Chat Button */
        .leave-chat-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: transparent;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .leave-chat-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .message {
                max-width: 100%;
            }
            
            .chat-container {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            grid-template-rows: repeat(3, 1fr);
            gap: 24px;
            max-width: 1500px;
            margin: 0 auto;
            width: 100%;
            padding: 20px;
            height: 950px;
            }

            .messages-area {
                grid-column: span 1;
                grid-row: span 2;
            }
        }
        
        @media (max-width: 480px) {
            .message-form {
                flex-direction: column;
            }
            
            .message-form button {
                width: 100%;
                padding: 10px;
            }
        }

        </style>
</head>
<body>
    <header class="chat-header">
        <h1><?php echo $view === 'forums' ? 'Forums' : htmlspecialchars($forumDetails['title']); ?></h1>
        <div class="actions">
            <button onclick="window.location.href='<?php echo $returnUrl; ?>'">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Back to Dashboard
            </button>
        </div>
    </header>

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
                <h2>Available Forums</h2>
                <button class="create-forum-btn" onclick="document.getElementById('create-forum-modal').classList.add('active')">
                    <ion-icon name="add-outline"></ion-icon>
                    Create Forum
                </button>
            </div>
            
            <div class="forums-grid">
                <?php if (empty($forums)): ?>
                    <p>No forums available yet.</p>
                <?php else: ?>
                    <?php foreach ($forums as $forum): ?>
                        <div class="forum-card">
                            <h3>
                                <?php echo htmlspecialchars($forum['title']); ?>
                                <span class="session-status <?php
                                    $today = date('Y-m-d');
                                    $currentTime = date('H:i');
                                    $timeRange = explode(' - ', $forum['time_slot']);
                                    $startTime = date('H:i', strtotime($timeRange[0]));
                                    $endTime = date('H:i', strtotime($timeRange[1]));
                                    if ($today < $forum['session_date']) {
                                        echo 'status-upcoming';
                                    } elseif ($today == $forum['session_date'] && $currentTime < $endTime) {
                                        echo 'status-active';
                                    } else {
                                        echo 'status-ended';
                                    }
                                ?>">
                                    <?php
                                        if ($today < $forum['session_date']) {
                                            echo 'Upcoming';
                                        } elseif ($today == $forum['session_date'] && $currentTime < $endTime) {
                                            echo 'Active';
                                        } else {
                                            echo 'Ended';
                                        }
                                    ?>
                                </span>
                            </h3>
                            <div class="details">
                                <p><ion-icon name="book-outline"></ion-icon> <span><?php echo htmlspecialchars($forum['course_title']); ?></span></p>
                                <p><ion-icon name="calendar-outline"></ion-icon> <span><?php echo date('F j, Y', strtotime($forum['session_date'])); ?></span></p>
                                <p><ion-icon name="time-outline"></ion-icon> <span><?php echo htmlspecialchars($forum['time_slot']); ?></span></p>
                                <p><ion-icon name="people-outline"></ion-icon> <span>Participants: <?php echo $forum['current_users']; ?>/<?php echo $forum['max_users']; ?></span></p>
                            </div>
                            
                            <div class="capacity">
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo ($forum['max_users'] > 0) ? (($forum['current_users'] / $forum['max_users']) * 100) : 0; ?>%;"></div>
                                </div>
                                <span class="capacity-text"><?php echo $forum['current_users']; ?>/<?php echo $forum['max_users']; ?></span>
                            </div>
                            
                            <div class="actions">
                                <button class="join-btn" onclick="window.location.href='forum-chat.php?view=forum&forum_id=<?php echo $forum['id']; ?>'">
                                    <ion-icon name="enter-outline"></ion-icon>
                                    View Forum
                                </button>
                                <div class="admin-actions">
                                    <button class="edit-btn" onclick="openEditModal(<?php echo $forum['id']; ?>, '<?php echo htmlspecialchars($forum['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($forum['course_title'], ENT_QUOTES); ?>', '<?php echo $forum['session_date']; ?>', '<?php echo htmlspecialchars($forum['time_slot'], ENT_QUOTES); ?>', <?php echo $forum['max_users']; ?>)">
                                        <ion-icon name="create-outline"></ion-icon> Edit
                                    </button>
                                    <a href="?action=delete_forum&forum_id=<?php echo $forum['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this forum? This action cannot be undone.')">
                                        <ion-icon name="trash-outline"></ion-icon> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="create-forum-modal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Create New Forum</h3>
                    <button class="modal-close" onclick="document.getElementById('create-forum-modal').classList.remove('active')">&times;</button>
                </div>
                <form class="modal-form" method="POST" action="">
                    <input type="hidden" name="action" value="create_forum">
                    <div class="form-group"><label for="title">Forum Title</label><input type="text" id="title" name="title" required></div>
                    <div class="form-group"><label for="course_title">Course Title</label><input type="text" id="course_title" name="course_title" required></div>
                    <div class="form-group"><label for="session_date">Session Date</label><input type="date" id="session_date" name="session_date" required></div>
                    <div class="form-group"><label for="time_slot">Time Slot</label><input type="text" id="time_slot" name="time_slot" placeholder="e.g., 10:00 AM - 11:00 AM" required></div>
                    <div class="form-group"><label for="max_users">Max Participants</label><input type="number" id="max_users" name="max_users" min="1" value="10" required></div>
                    <div class="modal-actions">
                        <button type="button" class="cancel-btn" onclick="document.getElementById('create-forum-modal').classList.remove('active')">Cancel</button>
                        <button type="submit" class="submit-btn">Create Forum</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="edit-forum-modal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header"><h3>Edit Forum</h3><button class="modal-close" onclick="document.getElementById('edit-forum-modal').classList.remove('active')">&times;</button></div>
                <form class="modal-form" method="POST" action="">
                    <input type="hidden" name="action" value="edit_forum">
                    <input type="hidden" name="forum_id" id="edit_forum_id">
                    <div class="form-group"><label for="edit_title">Forum Title</label><input type="text" id="edit_title" name="title" required></div>
                    <div class="form-group"><label for="edit_course_title">Course Title</label><input type="text" id="edit_course_title" name="course_title" required></div>
                    <div class="form-group"><label for="edit_session_date">Session Date</label><input type="date" id="edit_session_date" name="session_date" required></div>
                    <div class="form-group"><label for="edit_time_slot">Time Slot</label><input type="text" id="edit_time_slot" name="time_slot" placeholder="e.g., 10:00 AM - 11:00 AM" required></div>
                    <div class="form-group"><label for="edit_max_users">Max Participants</label><input type="number" id="edit_max_users" name="max_users" min="1" required></div>
                    <div class="modal-actions">
                        <button type="button" class="cancel-btn" onclick="document.getElementById('edit-forum-modal').classList.remove('active')">Cancel</button>
                        <button type="submit" class="submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($view === 'forum' && $forumDetails): ?>
        <div class="chat-container">
            <div class="forum-info">
                <?php if (!$isReviewMode && !$hasLeftSession): ?>
                    <a href="forum-chat.php?view=forum&forum_id=<?php echo $forumDetails['id']; ?>&action=leave_chat" class="leave-chat-btn" onclick="return confirm('Are you sure you want to leave this chat session? You can only review messages after leaving.')">
                        <ion-icon name="exit-outline"></ion-icon> Leave Chat
                    </a>
                <?php else: ?>
                    <a href="channels.php" class="leave-chat-btn"><ion-icon name="arrow-back-outline"></ion-icon> Back to Forums</a>
                <?php endif; ?>

                <h2><?php echo htmlspecialchars($forumDetails['title']); ?></h2>
                <div class="details">
                    <div class="detail"><ion-icon name="book-outline"></ion-icon> <span><?php echo htmlspecialchars($forumDetails['course_title']); ?></span></div>
                    <div class="detail"><ion-icon name="calendar-outline"></ion-icon> <span><?php echo date('F j, Y', strtotime($forumDetails['session_date'])); ?></span></div>
                    <div class="detail"><ion-icon name="time-outline"></ion-icon> <span><?php echo htmlspecialchars($forumDetails['time_slot']); ?></span></div>
                    <div class="detail"><ion-icon name="people-outline"></ion-icon> <span><?php echo count($forumParticipants); ?>/<?php echo $forumDetails['max_users']; ?> participants</span></div>
                    <?php
                        $sessionStatus = ''; $statusClass = '';
                        if ($today < $forumDetails['session_date']) {
                            $sessionStatus = 'Upcoming'; $statusClass = 'status-upcoming';
                        } elseif ($today == $forumDetails['session_date'] && $currentTime < $endTime) {
                            $sessionStatus = 'Active'; $statusClass = 'status-active';
                        } else {
                            $sessionStatus = 'Ended'; $statusClass = 'status-ended';
                        }
                    ?>
                    <div class="detail"><ion-icon name="information-circle-outline"></ion-icon> <span>Status: <span class="session-status <?php echo $statusClass; ?>"><?php echo $sessionStatus; ?></span></span></div>
                </div>

                <div class="participants">
                    <h3>Participants</h3>
                    <div class="participant-list">
                        <?php foreach ($forumParticipants as $participant): ?>
                            <div class="participant <?php echo ($participant['user_type'] === 'Admin') ? 'admin' : ''; ?>">
                                <?php if ($participant['user_type'] === 'Admin'): ?><ion-icon name="shield-outline"></ion-icon>
                                <?php elseif ($participant['user_type'] === 'Mentor'): ?><ion-icon name="school-outline"></ion-icon>
                                <?php else: ?><ion-icon name="person-outline"></ion-icon><?php endif; ?>
                                <span><?php echo htmlspecialchars($participant['display_name']); ?></span>
                                <?php if ($currentUserId !== $participant['user_id']): ?>
                                    <a href="?view=forum&forum_id=<?php echo $forumDetails['id']; ?>&action=remove_user&user_id=<?php echo $participant['user_id']; ?>" class="remove-btn" title="Remove user" onclick="return confirm('Remove this user from the forum?')">
                                        <ion-icon name="close-outline"></ion-icon>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form class="add-user-form" method="POST" action="">
                    <input type="hidden" name="action" value="add_user_to_forum">
                    <input type="hidden" name="forum_id" value="<?php echo $forumDetails['id']; ?>">
                    <div class="form-group">
                        <label for="user_id">Add User to Forum</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">-- Select User --</option>
                            <?php 
                            $participantIds = array_column($forumParticipants, 'user_id');
                            foreach ($allUsers as $user): 
                                if (!in_array($user['user_id'], $participantIds)): ?>
                                    <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <button type="submit">Add User</button>
                </form>
            </div>

            <div class="messages-area" id="messages">
                <div>
                    <?php if ($isReviewMode || $hasLeftSession): ?>
                        <div class="review-mode-banner">
                            <?php if ($hasLeftSession): ?>
                                <strong>Review Mode:</strong> You have left this session. You can review the conversation but cannot send new messages.
                            <?php else: ?>
                                <strong>Review Mode:</strong> This session has ended. You can review the conversation but cannot send new messages.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="video-call">
                        <?php if (!$isReviewMode && !$hasLeftSession && $sessionStatus === 'Active'): ?>
                            <a href="../video-call.php?forum_id=<?php echo $forumDetails['id']; ?>" class="join-video-btn">
                                <ion-icon name="videocam-outline"></ion-icon>
                                Join Video Call
                            </a>
                        <?php endif; ?>
                    </div>
                <div class="message-box">
                        <?php if (empty($messages)): ?>
                            <p class="no-messages">No messages yet. Start the conversation!</p>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message <?php echo ($msg['user_id'] == $currentUserId) ? 'sent' : 'received'; ?> <?php echo $msg['is_admin'] ? 'admin' : 'user'; ?>">
                                    <div class="sender">
                                        <?php if ($msg['is_admin']): ?><ion-icon name="shield-outline"></ion-icon>
                                        <?php elseif ($msg['is_mentor']): ?><ion-icon name="school-outline"></ion-icon>
                                        <?php else: ?><ion-icon name="person-outline"></ion-icon><?php endif; ?>
                                        <?php echo htmlspecialchars($msg['display_name']); ?>
                                    </div>
                                    <div class="content"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    <?php if (!empty($msg['file_path'])): ?>
                                        <div class="attachment">
                                            <a href="<?php echo htmlspecialchars($msg['file_path']); ?>" target="_blank" download>
                                                <ion-icon name="document-attach-outline"></ion-icon>
                                                <?php echo htmlspecialchars($msg['file_name']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="timestamp"><?php echo date('M d, g:i a', strtotime($msg['timestamp'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                </div>
                <div class="message-input">
                    <?php if (!$isReviewMode && !$hasLeftSession): ?>
                        <form class="message-form" method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="forum_chat">
                            <input type="hidden" name="forum_id" value="<?php echo $forumDetails['id']; ?>">
                            <div class="attachment-container">
                                <label for="attachment" class="attachment-label"><ion-icon name="attach-outline"></ion-icon>Attach File</label>
                                <input type="file" id="attachment" name="attachment" style="display: none;" onchange="updateFileName(this)">
                                <span id="file-name" class="attachment-name"></span>
                            </div>
                            <div class="message-input-container">
                                <input type="text" name="message" placeholder="Type your message..." autocomplete="off" required>
                                <button type="submit"><ion-icon name="send-outline"></ion-icon></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="message-form" style="opacity: 0.7;">
                            <div class="attachment-container">
                                <label class="attachment-label" style="cursor: not-allowed;"><ion-icon name="attach-outline"></ion-icon>Attach File</label>
                            </div>
                            <div class="message-input-container">
                                <input type="text" placeholder="You cannot send messages in review mode" disabled>
                                <button disabled><ion-icon name="send-outline"></ion-icon></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function scrollToBottom() {
            const messageBox = document.querySelector('.message-box');
            if (messageBox) {
                messageBox.scrollTop = messageBox.scrollHeight;
            }
        }
        window.onload = scrollToBottom;

        function updateFileName(input) {
            const fileNameSpan = document.getElementById('file-name');
            if (input.files.length > 0) {
                fileNameSpan.textContent = input.files[0].name;
            } else {
                fileNameSpan.textContent = '';
            }
        }

        <?php if ($view === 'forum' && !$isReviewMode): ?>
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newMessageBox = doc.querySelector('.message-box');
                    const currentMessageBox = document.querySelector('.message-box');
                    if (newMessageBox && currentMessageBox.innerHTML !== newMessageBox.innerHTML) {
                        currentMessageBox.innerHTML = newMessageBox.innerHTML;
                        scrollToBottom();
                    }
                })
                .catch(err => console.error('Failed to auto-refresh chat:', err));
        }, 5000);
        <?php endif; ?>

        function openEditModal(id, title, course_title, session_date, time_slot, max_users) {
            document.getElementById('edit_forum_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_course_title').value = course_title;
            document.getElementById('edit_session_date').value = session_date;
            document.getElementById('edit_time_slot').value = time_slot;
            document.getElementById('edit_max_users').value = max_users;
            document.getElementById('edit-forum-modal').classList.add('active');
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