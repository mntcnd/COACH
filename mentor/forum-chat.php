<?php
session_start();

// *** FIX: Set timezone to Philippine Time (PHT) ***
date_default_timezone_set('Asia/Manila');

// Database connection
require '../connection/db_connection.php';

// Create session_participants table if it doesn't exist (to track who has left a session)
// UPDATED: Changed username to user_id
$conn->query("
CREATE TABLE IF NOT EXISTS session_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('active', 'left', 'review') DEFAULT 'active',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

// SESSION CHECK - UPDATED to use user_id
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'Mentor') {
  // Redirect to a general login page if the user is not a logged-in mentor.
  header("Location: ../login.php");
  exit();
}

// Get user and mentor information from the new 'users' table
$userId = $_SESSION['user_id'];
$username = $_SESSION['username']; // Assuming username is also stored in session after login

$sql = "SELECT first_name, last_name, icon FROM users WHERE user_id = ? AND user_type = 'Mentor'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $displayName = $row['first_name'] . ' ' . $row['last_name'];
    $_SESSION['mentor_name'] = $displayName;

    // Check if icon exists and is not empty
    if (isset($row['icon']) && !empty($row['icon'])) {
        $_SESSION['mentor_icon'] = $row['icon'];
        $mentorIcon = $row['icon'];
    } else {
        $_SESSION['mentor_icon'] = "../uploads/img/default_pfp.png";
        $mentorIcon = "../uploads/img/default_pfp.png";
    }
} else {
    // Fallback if user is not found or not a mentor
    $_SESSION['mentor_name'] = "Unknown Mentor";
    $_SESSION['mentor_icon'] = "../uploads/img/default_pfp.png";
    $displayName = "Unknown Mentor";
    $mentorIcon = "../uploads/img/default_pfp.png";
}


// Create other necessary tables if they don't exist
$conn->query("
CREATE TABLE IF NOT EXISTS forum_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    course_title VARCHAR(200) NOT NULL,
    session_date DATE NOT NULL,
    time_slot VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    max_users INT DEFAULT 10
)
");

// UPDATED: Changed username to user_id
$conn->query("
CREATE TABLE IF NOT EXISTS forum_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    forum_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (forum_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)
");

// Handle leaving a chat - UPDATED to use user_id
if (isset($_GET['action']) && $_GET['action'] === 'leave_chat' && isset($_GET['forum_id'])) {
    $forumId = $_GET['forum_id'];

   // Update the session_participants table to mark user as having left
    $checkParticipant = $conn->prepare("SELECT id FROM session_participants WHERE forum_id = ? AND user_id = ?");
    $checkParticipant->bind_param("ii", $forumId, $userId);
    $checkParticipant->execute();
    $participantResult = $checkParticipant->get_result();
    
    if ($participantResult->num_rows > 0) {
        // Update existing record
        $updateStatus = $conn->prepare("UPDATE session_participants SET status = 'left' WHERE forum_id = ? AND user_id = ?");
        $updateStatus->bind_param("ii", $forumId, $userId);
        $updateStatus->execute();
    } else {
        // Insert new record
        // FIXED: Using a placeholder for consistency and safety.
        $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, ?)");
        $status = 'left';
        $insertStatus->bind_param("iis", $forumId, $userId, $status);
        $insertStatus->execute();
    }

    // Redirect to forums list
    header("Location: forum-chat.php?view=forums");
    exit();
}

// Handle message submission for forum chat - UPDATED to use user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forum_id']) && isset($_POST['action']) && $_POST['action'] === 'forum_chat') {
    $message = trim($_POST['message']);
    $forumId = $_POST['forum_id'];

    // Check if user is in review mode or has left the session
    $checkStatus = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
    $checkStatus->bind_param("ii", $forumId, $userId);
    $checkStatus->execute();
    $statusResult = $checkStatus->get_result();

    if ($statusResult->num_rows > 0) {
        $participantStatus = $statusResult->fetch_assoc()['status'];
        if ($participantStatus === 'left' || $participantStatus === 'review') {
            // User has left the session or is in review mode, redirect back
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

        $isSessionActive = ($today < $session['session_date']) ||
                           ($today == $session['session_date'] && $currentTime <= $endTime);

        if (!$isSessionActive) {
            header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
            exit();
        }
    }

    // Handle file upload
    $fileName = null;
    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $uploadDir = '../uploads/chat_files/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = basename($_FILES['file']['name']);
        $uniqueName = uniqid() . '_' . $fileName;
        $filePath = $uploadDir . $uniqueName;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            // File uploaded successfully
        } else {
           $fileName = null;
           $filePath = null;
        }
    }

    if (!empty($message) || $fileName) {
        $isMentor = 1; // This is a mentor message
        $isAdmin = 0;  // Not an admin message

        // Insert message into database - UPDATED to use user_id
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, display_name, message, is_admin, is_mentor, chat_type, forum_id, file_name, file_path) VALUES (?, ?, ?, ?, ?, 'forum', ?, ?, ?)");
        $stmt->bind_param("issiiiss", $userId, $displayName, $message, $isAdmin, $isMentor, $forumId, $fileName, $filePath);
        $stmt->execute();
    }

    // Redirect to prevent form resubmission
    header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
    exit();
}

// Handle joining a forum - UPDATED to use user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['forum_id']) && $_POST['action'] === 'join_forum') {
    $forumId = $_POST['forum_id'];

    $stmt = $conn->prepare("SELECT * FROM forum_chats WHERE id = ?");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $forum = $result->fetch_assoc();

        // Check if mentor is already in the forum
        $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $forumId, $userId);
        $stmt->execute();
        $participantResult = $stmt->get_result();

        // Check if user has left the session before
        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
        $checkLeft->bind_param("ii", $forumId, $userId);
        $checkLeft->execute();
        $leftResult = $checkLeft->get_result();
        $hasLeft = false;

        if ($leftResult->num_rows > 0) {
            $participantStatus = $leftResult->fetch_assoc()['status'];
            $hasLeft = ($participantStatus === 'left');
        }

        // Check session status
        $today = date('Y-m-d');
        $currentTime = date('H:i');
        $timeRange = explode(' - ', $forum['time_slot']);
        $endTime = date('H:i', strtotime($timeRange[1]));

        $isSessionOver = ($today > $forum['session_date']) ||
                         ($today == $forum['session_date'] && $currentTime > $endTime);

        if ($participantResult->num_rows === 0 && !$hasLeft) {
            // Add mentor to forum
            $stmt = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $forumId, $userId);
            $stmt->execute();

            if ($isSessionOver) {
                // Session is over, add to session_participants with review status
                // FIXED: Use a placeholder for status and bind it
                $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, ?)");
                $status = 'review';
                $insertStatus->bind_param("iis", $forumId, $userId, $status);
                $insertStatus->execute();
                header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
            } else {
                // Session is active, add with active status
                // FIXED: Use a placeholder for status and bind it
                $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, ?)");
                $status = 'active';
                $insertStatus->bind_param("iis", $forumId, $userId, $status);
                $insertStatus->execute();
                header("Location: forum-chat.php?view=forum&forum_id=" . $forumId);
            }
            exit();
        } elseif ($hasLeft) {
            header("Location: forum-chat.php?view=forum&forum_id=" . $forumId . "&review=true");
            exit();
        } else {
            // User is already in, redirect appropriately
            if ($isSessionOver) {
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


// Determine which view to show
$view = isset($_GET['view']) ? $_GET['view'] : 'forums';

// Fetch forums for listing - UPDATED subquery to use user_id
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

// Fetch forum details if viewing a specific forum - ALL QUERIES UPDATED to use user_id
$forumDetails = null;
$forumParticipants = [];
$isReviewMode = false;
$hasLeftSession = false;

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
        
        // Add mentor to forum participants if not already there
        $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $forumId, $userId);
        $stmt->execute();
        $participantResult = $stmt->get_result();
        
        if ($participantResult->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO forum_participants (forum_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $forumId, $userId);
            $stmt->execute();
        }
        
        // Check session status
        $today = date('Y-m-d');
        $currentTime = date('H:i');
        $timeRange = explode(' - ', $forumDetails['time_slot']);
        $endTime = date('H:i', strtotime($timeRange[1]));
        
        $isSessionOver = ($today > $forumDetails['session_date']) || 
                         ($today == $forumDetails['session_date'] && $currentTime > $endTime);
        
        // Check if user has explicitly left this session
        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
        $checkLeft->bind_param("ii", $forumId, $userId);
        $checkLeft->execute();
        $leftResult = $checkLeft->get_result();
        
        if ($leftResult->num_rows > 0) {
            $participantStatus = $leftResult->fetch_assoc()['status'];
            $hasLeftSession = ($participantStatus === 'left');
        }
        
        $isReviewMode = $isSessionOver || $hasLeftSession || (isset($_GET['review']) && $_GET['review'] === 'true');
        
        // If in review mode, update status in DB
        if ($isReviewMode && !$hasLeftSession) {
            if ($leftResult->num_rows > 0) {
                $updateStatus = $conn->prepare("UPDATE session_participants SET status = 'review' WHERE forum_id = ? AND user_id = ?");
                $updateStatus->bind_param("ii", $forumId, $userId);
                $updateStatus->execute();
            } else {
                $insertStatus = $conn->prepare("INSERT INTO session_participants (forum_id, user_id, status) VALUES (?, ?, ?)");
                $status = 'review';
                $insertStatus->bind_param("iis", $forumId, $userId, $status);
                $insertStatus->execute();
            }
        }
        
        // Fetch participants - REWRITTEN to use new 'users' table
        $stmt = $conn->prepare("
            SELECT 
                u.username,
                CONCAT(u.first_name, ' ', u.last_name) as display_name,
                u.user_type
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

$returnUrl = "sessions.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view === 'forums' ? 'Forums' : 'Forum Chat'; ?> | Mentor</title>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/forum-chat.css" />
    <link rel="stylesheet" href="css/navigation.css"/>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
    <header class="chat-header">
        <h1><?php echo $view === 'forums' ? 'Available Sessions' : htmlspecialchars($forumDetails['title']); ?></h1>
        <div class="actions">
            <button onclick="window.location.href='<?php echo $returnUrl; ?>'">
                <ion-icon name="exit-outline"></ion-icon>
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

    <?php if ($view === 'forums'): ?>
        <div class="forums-container">
            <div class="forums-header">
                <h2>Available Sessions</h2>
            </div>
            
            <div class="forums-grid">
                <?php if (empty($forums)): ?>
                    <p>No sessions available yet.</p>
                <?php else: ?>
                    <?php foreach ($forums as $forum): ?>
                        <?php
                        // Determine session status
                        $today = date('Y-m-d');
                        $currentTime = date('H:i');
                        $timeRange = explode(' - ', $forum['time_slot']);
                        $endTime = date('H:i', strtotime($timeRange[1]));
                        
                        $sessionStatus = '';
                        $statusClass = '';
                        
                        if ($today < $forum['session_date']) {
                            $sessionStatus = 'Upcoming';
                            $statusClass = 'status-upcoming';
                        } elseif ($today == $forum['session_date'] && $currentTime < $endTime) {
                            $sessionStatus = 'Active';
                            $statusClass = 'status-active';
                        } else {
                            $sessionStatus = 'Ended';
                            $statusClass = 'status-ended';
                        }
                        
                        // Check if user has left this session - UPDATED for user_id
                        $hasLeft = false;
                        $checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
                        $checkLeft->bind_param("ii", $forum['id'], $userId);
                        $checkLeft->execute();
                        $leftResult = $checkLeft->get_result();
                        
                        if ($leftResult->num_rows > 0) {
                            $participantStatus = $leftResult->fetch_assoc()['status'];
                            $hasLeft = ($participantStatus === 'left');
                        }
                        
                        $isSessionOver = ($sessionStatus === 'Ended');
                        ?>
                        <div class="forum-card">
                            <h3>
                                <?php echo htmlspecialchars($forum['title']); ?>
                                <span class="session-status <?php echo $statusClass; ?>"><?php echo $sessionStatus; ?></span>
                            </h3>
                            <div class="details">
                                <p><ion-icon name="book-outline"></ion-icon> <span><?php echo htmlspecialchars($forum['course_title']); ?></span></p>
                                <p><ion-icon name="calendar-outline"></ion-icon> <span><?php echo date('F j, Y', strtotime($forum['session_date'])); ?></span></p>
                                <p><ion-icon name="time-outline"></ion-icon> <span><?php echo htmlspecialchars($forum['time_slot']); ?></span></p>
                                <p><ion-icon name="people-outline"></ion-icon> <span>Participants: <?php echo $forum['current_users']; ?>/<?php echo $forum['max_users']; ?></span></p>
                            </div>
                            
                            <div class="capacity">
                                <div class="capacity-bar">
                                    <div class="capacity-fill" style="width: <?php echo ($forum['max_users'] > 0 ? ($forum['current_users'] / $forum['max_users']) * 100 : 0); ?>%;"></div>
                                </div>
                                <span class="capacity-text"><?php echo $forum['current_users']; ?>/<?php echo $forum['max_users']; ?></span>
                            </div>
                            
                            <div class="actions">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="join_forum">
                                    <input type="hidden" name="forum_id" value="<?php echo $forum['id']; ?>">
                                    
                                    <?php if ($isSessionOver || $hasLeft): ?>
                                        <button type="submit" class="review-btn"><ion-icon name="eye-outline"></ion-icon> Review</button>
                                    <?php else: ?>
                                        <button type="submit" class="join-btn"><ion-icon name="enter-outline"></ion-icon> Join Session</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($view === 'forum' && $forumDetails): ?>
        <div class="chat-container">
            <div class="forum-info">
                <?php if (!$isReviewMode && !$hasLeftSession): ?>
    <a href="#" 
       class="leave-chat-btn" 
       data-leave-url="forum-chat.php?view=forums&action=leave_chat&forum_id=<?php echo $forumDetails['id']; ?>"
       onclick="confirmLeaveChat(event)">
        <ion-icon name="exit-outline"></ion-icon> Leave Chat
    </a>
<?php else: ?>
                    <a href="forum-chat.php?view=forums" class="leave-chat-btn">
                        <ion-icon name="arrow-back-outline"></ion-icon> Back to Sessions
                    </a>
                <?php endif; ?>
                
                <h2><?php echo htmlspecialchars($forumDetails['title']); ?></h2>
                <div class="details">
                    <div class="detail"><ion-icon name="book-outline"></ion-icon> <span><?php echo htmlspecialchars($forumDetails['course_title']); ?></span></div>
                    <div class="detail"><ion-icon name="calendar-outline"></ion-icon> <span><?php echo date('F j, Y', strtotime($forumDetails['session_date'])); ?></span></div>
                    <div class="detail"><ion-icon name="time-outline"></ion-icon> <span><?php echo htmlspecialchars($forumDetails['time_slot']); ?></span></div>
                    <div class="detail"><ion-icon name="people-outline"></ion-icon> <span><?php echo count($forumParticipants); ?>/<?php echo $forumDetails['max_users']; ?> participants</span></div>
                    <?php
                    // Determine session status
                    $today = date('Y-m-d');
                    $currentTime = date('H:i');
                    $timeRange = explode(' - ', $forumDetails['time_slot']);
                    $endTime = date('H:i', strtotime($timeRange[1]));
                    
                    $sessionStatus = '';
                    $statusClass = '';
                    
                    if ($today < $forumDetails['session_date']) {
                        $sessionStatus = 'Upcoming';
                        $statusClass = 'status-upcoming';
                    } elseif ($today == $forumDetails['session_date'] && $currentTime < $endTime) {
                        $sessionStatus = 'Active';
                        $statusClass = 'status-active';
                    } else {
                        $sessionStatus = 'Ended';
                        $statusClass = 'status-ended';
                    }
                    ?>
                    <div class="detail">
                        <ion-icon name="information-circle-outline"></ion-icon>
                        <span>Status: <span class="session-status <?php echo $statusClass; ?>"><?php echo $sessionStatus; ?></span></span>
                    </div>
                </div>
                
                <div class="participants">
                    <h3>Participants</h3>
                    <div class="participant-list">
                        <?php foreach ($forumParticipants as $participant): ?>
                            <?php 
                                // UPDATED: Logic to handle new user_type values from 'users' table
                                $userTypeClass = strtolower(str_replace(' ', '', $participant['user_type'])); 
                            ?>
                            <div class="participant <?php echo $userTypeClass; ?>">
                                <?php if (strtolower($participant['user_type']) === 'admin'): ?>
                                    <ion-icon name="shield-outline"></ion-icon>
                                <?php elseif (strtolower($participant['user_type']) === 'mentor'): ?>
                                    <ion-icon name="school-outline"></ion-icon>
                                <?php else: ?>
                                    <ion-icon name="person-outline"></ion-icon>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($participant['display_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="messages-area" id="messages-area">
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
                
                <div class="message-box" id="message-box">
                    <?php if (empty($messages)): ?>
                        <p class="no-messages">No messages yet in this forum. Start the conversation!</p>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo $msg['is_admin'] ? 'admin' : ($msg['is_mentor'] ? 'mentor' : 'user'); ?>">
                                <div class="sender">
                                    <?php if ($msg['is_admin']): ?>
                                        <ion-icon name="shield-outline"></ion-icon>
                                    <?php elseif ($msg['is_mentor']): ?>
                                        <ion-icon name="school-outline"></ion-icon>
                                    <?php else: ?>
                                        <ion-icon name="person-outline"></ion-icon>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($msg['display_name']); ?>
                                </div>
                                <div class="content"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <?php if (!empty($msg['file_name']) && !empty($msg['file_path'])): ?>
                                    <div class="file-attachment">
                                        <ion-icon name="document-outline"></ion-icon>
                                        <a href="<?php echo htmlspecialchars($msg['file_path']); ?>" target="_blank" download>
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
                            <div class="file-upload-container">
                                <label for="file-upload" class="file-upload-btn">
                                    <ion-icon name="attach-outline"></ion-icon> Attach File
                                </label>
                                <input type="file" id="file-upload" name="file" style="display: none;" onchange="updateFileName(this)">
                                <span class="file-name" id="file-name"></span>
                            </div>
                            <div class="message-input-container">
                                <input type="text" name="message" placeholder="Type your message..." autocomplete="off">
                                <button type="submit"><ion-icon name="send-outline"></ion-icon></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="message-form" style="opacity: 0.7;">
                            <div class="file-upload-container">
                                <label class="file-upload-btn" style="cursor: not-allowed; background-color: #f5f5f5;">
                                    <ion-icon name="attach-outline"></ion-icon> Attach File
                                </label>
                                <span class="file-name">You cannot send files in review mode</span>
                            </div>
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
    <script>
        function scrollToBottom() {
            const messagesContainer = document.getElementById('message-box');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
        
        window.onload = function() {
            scrollToBottom();
        };
        
        function updateFileName(input) {
            const fileNameDisplay = document.getElementById('file-name');
            if (input.files.length > 0) {
                fileNameDisplay.textContent = input.files[0].name;
            } else {
                fileNameDisplay.textContent = '';
            }
        }
        
        <?php if ($view === 'forum' && !$isReviewMode): ?>
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newMessages = doc.getElementById('message-box');
                    const currentMessages = document.getElementById('message-box');
                    
                    if (newMessages && currentMessages && newMessages.innerHTML !== currentMessages.innerHTML) {
                        const isScrolledToBottom = currentMessages.scrollHeight - currentMessages.clientHeight <= currentMessages.scrollTop + 1;
                        currentMessages.innerHTML = newMessages.innerHTML;
                        if (isScrolledToBottom) {
                            scrollToBottom();
                        }
                    }
                })
                .catch(error => console.error('Error fetching new messages:', error));
        }, 1000);
        <?php endif; ?>
        
    </script>

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