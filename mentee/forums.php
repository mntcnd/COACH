<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// *** FIX: Set timezone to Philippine Time (PHT) ***
date_default_timezone_set('Asia/Manila');

// ==========================================================
// --- NEW: ANTI-CACHING HEADERS (Security Block) ---
// ==========================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 
// ==========================================================

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    header("Location: ../login.php");
    exit();
}

// --- FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

// SESSION CHECK
if (!isset($_SESSION['username'])) {
  header("Location: ../login.php"); 
  exit();
}

$username = $_SESSION['username'];
$displayName = '';
$userIcon = 'img/default-user.png';
$userId = null;

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $userId = $row['user_id'];
    $displayName = $row['first_name'] . ' ' . $row['last_name'];
    if (!empty($row['icon'])) $userIcon = $row['icon'];
}
$stmt->close();

if ($userId === null) {
    header("Location: login.php");
    exit();
}

// --- ENHANCED BAN CHECK WITH AUTO-UNBAN ---
$isBanned = false;
$ban_details = null;
$banCountdownTime = null;

// Check if user has an active ban
$ban_check_stmt = $conn->prepare("SELECT ban_id, reason, ban_until, ban_duration_text, ban_type FROM banned_users WHERE username = ?");
$ban_check_stmt->bind_param("s", $username);
$ban_check_stmt->execute();
$ban_result = $ban_check_stmt->get_result();

if ($ban_result->num_rows > 0) {
    $ban_details = $ban_result->fetch_assoc();
    
    // Check if ban has expired
    if ($ban_details['ban_until'] !== null && $ban_details['ban_until'] !== '') {
        $unbanTime = strtotime($ban_details['ban_until']);
        $currentTime = time();
        
        if ($currentTime >= $unbanTime) {
            // Ban has expired - remove it
            $remove_ban_stmt = $conn->prepare("DELETE FROM banned_users WHERE ban_id = ?");
            $remove_ban_stmt->bind_param("i", $ban_details['ban_id']);
            $remove_ban_stmt->execute();
            $remove_ban_stmt->close();
            
            // User is no longer banned
            $isBanned = false;
            $ban_details = null;
        } else {
            // Ban is still active
            $isBanned = true;
            $banCountdownTime = $unbanTime - $currentTime;
        }
    } else {
        // Permanent ban
        $isBanned = true;
    }
}
$ban_check_stmt->close();

// --- PROFANITY FILTER ---
function filterProfanity($text) {
    $profaneWords = ['fuck','shit','bitch','asshole','bastard','slut','whore','dick','pussy','faggot','cunt','motherfucker','cock','prick','jerkoff','cum','putangina','tangina','pakshet','gago','ulol','leche','bwisit','pucha','punyeta','hinayupak','lintik','tarantado','inutil','siraulo','bobo','tanga','pakyu','yawa','yati','pisti','buang','pendejo','cabron','maricon','chingada','mierda'];
    foreach ($profaneWords as $word) {
        $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
        $text = preg_replace($pattern, '****', $text);
    }
    return $text;
}

// --- LINK HELPER ---
function makeLinksClickable($text) {
    $urlRegex = '/(https?:\/\/[^\s<]+|www\.[^\s<]+)/i';
    return preg_replace_callback($urlRegex, function($matches) {
        $url = $matches[0];
        $protocol = (strpos($url, '://') === false) ? 'http://' : '';
        return '<a href="' . htmlspecialchars($protocol . $url) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($url) . '</a>';
    }, $text);
}

// --- POST ACTION HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle Like/Unlike
    if (($action === 'like_post' || $action === 'unlike_post') && isset($_POST['post_id'])) {
        $postId = intval($_POST['post_id']);
        $response = ['success' => false, 'message' => '', 'action' => ''];

        if ($postId > 0) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $postId, $userId);
            $stmt->execute();
            $stmt->bind_result($likeCount);
            $stmt->fetch();
            $stmt->close();

            if ($likeCount == 0) {
                // Add like
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $postId, $userId);
                    $stmt->execute();
                    $stmt = $conn->prepare("UPDATE general_forums SET likes = likes + 1 WHERE id = ?");
                    $stmt->bind_param("i", $postId);
                    $stmt->execute();
                    $conn->commit();
                    $response['success'] = true;
                    $response['action'] = 'liked';
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                }
            } else {
                // Remove like
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $postId, $userId);
                    $stmt->execute();
                    $stmt = $conn->prepare("UPDATE general_forums SET likes = GREATEST(0, likes - 1) WHERE id = ?");
                    $stmt->bind_param("i", $postId);
                    $stmt->execute();
                    $conn->commit();
                    $response['success'] = true;
                    $response['action'] = 'unliked';
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                }
            }
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Handle New Post
    elseif ($action === 'create_post' && isset($_POST['post_title'], $_POST['post_content'])) {
        // Check if banned FIRST, before processing
        if ($isBanned) {
            header("Location: forums.php");
            exit();
        }
        
        // Now process the post creation (all your existing code)
        $postTitle = filterProfanity(trim($_POST['post_title']));
        $postContent = filterProfanity($_POST['post_content']);
        $filePath = null;
        $fileName = null;

        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['post_image']['type'], $allowed_types) && $_FILES['post_image']['size'] < 5000000) {
                $uploadDir = '../uploads/forum_images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileName = uniqid() . '_' . basename($_FILES['post_image']['name']);
                $filePath = $uploadDir . $fileName;
                move_uploaded_file($_FILES['post_image']['tmp_name'], $filePath);
            }
        }

        if (!empty($postTitle) && !empty($postContent)) {
            $currentDateTime = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO general_forums (user_id, display_name, message, is_admin, is_mentor, chat_type, title, file_path, file_name, user_icon, timestamp) VALUES (?, ?, ?, ?, ?, 'forum', ?, ?, ?, ?, ?)");
            
            $isAdmin = 0;
            $isMentor = 0;

            $stmt->bind_param("issiisssss", $userId, $displayName, $postContent, $isAdmin, $isMentor, $postTitle, $filePath, $fileName, $userIcon, $currentDateTime);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: forums.php");
        exit();
    }

    // Handle New Comment
    elseif ($action === 'create_comment' && isset($_POST['comment_message'], $_POST['post_id'])) {
        // Check if banned FIRST, before processing
        if ($isBanned) {
            header("Location: forums.php");
            exit();
        }

        // Now process the comment creation
        $commentMessage = filterProfanity(trim($_POST['comment_message']));
        $postId = intval($_POST['post_id']);

        if (!empty($commentMessage) && $postId > 0) {
            $currentDateTime = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
            
            $stmt = $conn->prepare("INSERT INTO general_forums 
                (user_id, display_name, title, message, is_admin, is_mentor, chat_type, forum_id, user_icon, timestamp) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $isAdmin = 0;
            $isMentor = 0;
            $chatType = 'comment';   // Set comment type
            $title = '';             // Comments don’t have titles

            $stmt->bind_param(
                "isssiissss", 
                $userId,         // i
                $displayName,    // s
                $title,          // s
                $commentMessage, // s
                $isAdmin,        // i
                $isMentor,       // i
                $chatType,       // s
                $postId,         // i
                $userIcon,       // s
                $currentDateTime // s
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: forums.php");
        exit();
    }

    // Handle Delete Comment
    elseif ($action === 'delete_comment' && isset($_POST['comment_id'])) {
        $commentId = intval($_POST['comment_id']);
        $response = ['success' => false, 'message' => ''];

        if ($commentId > 0) {
            $stmt = $conn->prepare("DELETE FROM general_forums WHERE id = ? AND user_id = ? AND chat_type = 'comment'");
            $stmt->bind_param("ii", $commentId, $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows === 1) {
                $response['success'] = true;
                $response['message'] = 'Comment deleted.';
            } else {
                $response['message'] = 'Comment not found or you are not the author.';
            }
            $stmt->close();
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Handle Report Post
    elseif ($action === 'submit_report' && isset($_POST['post_id'], $_POST['reason'])) {
        
        // Check if banned FIRST, before processing
        if ($isBanned) {
            header("Location: forums.php");
            exit();
        }
        
        $postId = intval($_POST['post_id']);
        $reason = filterProfanity(trim($_POST['reason']));
        
        // Get the post details to find the reported user and title
        $post_stmt = $conn->prepare("SELECT user_id, display_name, title FROM general_forums WHERE id = ?");
        $post_stmt->bind_param("i", $postId);
        $post_stmt->execute();
        $post_result = $post_stmt->get_result();
        
        if ($post_row = $post_result->fetch_assoc()) {
            $reportedByUsername = $username; // Current logged-in user
            $postTitle = $post_row['title']; // Get the post title
            
            if (!empty($reason)) {
                // Match your actual database columns: post_id, title, reported_by_username, reason, report_date, status
                $stmt = $conn->prepare("INSERT INTO reports (post_id, title, reported_by_username, reason, report_date, status) VALUES (?, ?, ?, ?, NOW(), 'pending')");
                
                $stmt->bind_param("isss", $postId, $postTitle, $reportedByUsername, $reason);

                error_log("Report submission started in forums.php");

                if ($stmt->execute()) {
                    $_SESSION['report_success'] = true;
                    header("Location: forums.php");
                    exit();
                } else {
                    error_log("Error saving report: " . $stmt->error);
                    $_SESSION['report_error'] = "Error saving report. Please try again.";
                    header("Location: forums.php");
                    exit();
                }
                $stmt->close();
            } else {
                $_SESSION['report_error'] = "Please provide a reason for the report.";
                header("Location: forums.php");
                exit();
            }
        } else {
            $_SESSION['report_error'] = "Post not found.";
            header("Location: forums.php");
            exit();
        }
        $post_stmt->close();
        
        header("Location: forums.php");
        exit();
    }

    // Handle Delete Post
    elseif ($action === 'delete_post' && isset($_POST['post_id'])) {
        $postId = intval($_POST['post_id']);
        if ($postId > 0) {
            $conn->begin_transaction();

            try {
                $comments_stmt = $conn->prepare("DELETE FROM general_forums WHERE forum_id = ? AND chat_type = 'comment'");
                $comments_stmt->bind_param("i", $postId);
                $comments_stmt->execute();
                $comments_stmt->close();

                $post_stmt = $conn->prepare("DELETE FROM general_forums WHERE id = ? AND user_id = ?");
                $post_stmt->bind_param("ii", $postId, $userId);
                $post_stmt->execute();
                $post_stmt->close();

                $conn->commit();

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
            }
        }
        header("Location: forums.php");
        exit();
    }

    // Handle Ban Appeal
    elseif ($action === 'submit_appeal' && isset($_POST['appeal_reason'])) {
        // Only process if the user is currently banned
        if (!$isBanned) {
            header("Location: forums.php");
            exit();
        }

        $appealReason = filterProfanity(trim($_POST['appeal_reason']));
        $username = $_SESSION['username'];
        
        if (!empty($appealReason)) {
            $stmt = $conn->prepare("INSERT INTO ban_appeals (username, reason, appeal_date, status) VALUES (?, ?, NOW(), 'pending')");
            
            $stmt->bind_param("ss", $username, $appealReason);

            if ($stmt->execute()) {
                $_SESSION['appeal_success'] = "Your ban appeal has been submitted successfully and is under review.";
            } else {
                error_log("Error saving appeal: " . $stmt->error);
                $_SESSION['appeal_error'] = "Error submitting appeal. Please try again.";
            }
            $stmt->close();
        } else {
            $_SESSION['appeal_error'] = "Please provide a reason for your appeal.";
        }
        
        header("Location: forums.php");
        exit();
    }
}

// --- DATA FETCHING ---
$posts = [];
$postQuery = "SELECT c.*, 
              (SELECT COUNT(*) FROM post_likes WHERE post_id = c.id) as likes,
              (SELECT COUNT(*) FROM post_likes WHERE post_id = c.id AND user_id = ?) as has_liked
              FROM general_forums c
              WHERE c.chat_type = 'forum' AND c.status = 'active'
              ORDER BY c.timestamp DESC";
$postsStmt = $conn->prepare($postQuery);

if ($postsStmt === false) {
    die('SQL preparation failed: ' . htmlspecialchars($conn->error));
}

$postsStmt->bind_param("i", $userId);
$postsStmt->execute();
$postsResult = $postsStmt->get_result();

if ($postsResult && $postsResult->num_rows > 0) {
    while ($row = $postsResult->fetch_assoc()) {
        $comments = [];
        $commentsStmt = $conn->prepare("SELECT * FROM general_forums WHERE chat_type = 'comment' AND forum_id = ? AND status = 'active' ORDER BY timestamp ASC");
        $commentsStmt->bind_param("i", $row['id']);
        $commentsStmt->execute();
        $commentsResult = $commentsStmt->get_result();
        
        if ($commentsResult && $commentsResult->num_rows > 0) {
            while ($commentRow = $commentsResult->fetch_assoc()) {
                $comments[] = $commentRow;
            }
        }
        $commentsStmt->close();
        $row['comments'] = $comments;
        $posts[] = $row;
    }
}
$postsStmt->close();

// Fetch user details for navbar
$navFirstName = '';
$navUserIcon = '';
$isMentee = ($_SESSION['user_type'] === 'Mentee');
if ($isMentee) {
    $sql = "SELECT first_name, icon FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $navFirstName = $row['first_name'];
        $navUserIcon = $row['icon'];
    }
    $stmt->close();
}

$baseUrl = "http://localhost/coachlocal";

// Format ban datetime for JavaScript (ISO 8601 format)
$banDatetimeJS = null;
if ($ban_details && $ban_details['ban_until'] && $ban_details['ban_until'] !== '') {
    $banDatetimeJS = (new DateTime($ban_details['ban_until'], new DateTimeZone('Asia/Manila')))->format('Y-m-d\TH:i:s');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forums</title>
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/navbar.css" />
    <link rel="stylesheet" href="css/forum.css">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <style>
        .banned-message {
            text-align: center;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            padding: 30px;
            border-radius: 12px;
            margin: 20px auto;
            max-width: 600px;
            border: 2px solid #721c24;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;  /* ADD THIS */
            z-index: 1000;      /* ADD THIS - higher than overlay's 999 */
        }
        
        .banned-message h2 {
            color: #721c24;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .banned-message p {
            color: #721c24;
            margin: 10px 0;
            font-size: 16px;
        }
        
        .banned-message .ban-reason {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-style: italic;
        }
        
        .banned-message .ban-duration {
            font-weight: bold;
            color: #c82333;
            font-size: 18px;
            margin-top: 15px;
        }
        
        .banned-message .ban-status {
            background: #ffe0e6;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            color: #721c24;
        }
        
        .ban-countdown {
            font-size: 20px;
            font-weight: bold;
            color: #c82333;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin: 15px 0;
            font-family: monospace;
            border: 2px solid #c82333;
        }
        
        .permanent-ban-label {
            display: inline-block;
            background: #721c24;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .banned-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.3);
            z-index: 999;
            display: none;
        }
        
        .banned-overlay.show {
            display: block;
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
              <?php if (!empty($navUserIcon)): ?>
                <img src="<?php echo htmlspecialchars($navUserIcon); ?>" alt="User Icon" style="width: 35px; height: 35px; border-radius: 50%;">
              <?php else: ?>
                <ion-icon name="person-circle-outline" style="font-size: 35px;"></ion-icon>
              <?php endif; ?>
            </a>
          </div>
          <div class="sub-menu-wrap hide" id="profile-menu">
            <div class="sub-menu">
              <div class="user-info">
                <div class="user-icon">
                  <?php if (!empty($navUserIcon)): ?>
                    <img src="<?php echo htmlspecialchars($navUserIcon); ?>" alt="User Icon" style="width: 40px; height: 40px; border-radius: 50%;">
                  <?php else: ?>
                    <ion-icon name="person-circle-outline" style="font-size: 40px;"></ion-icon>
                  <?php endif; ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($navFirstName); ?></div>
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

    <?php if ($isBanned): ?>
        <div class="banned-overlay show"></div>
    <?php endif; ?>

    <div class="forum-layout">
        <div class="sidebar-left">
            <div class="sidebar-box user-stats-box">
                <h3>My Activity</h3>
                <ul>
                    <?php
                    $post_count = 0;
                    $likes_received = 0;

                    if ($userId) { 
                        $sql_posts = "SELECT COUNT(id) AS total_posts FROM general_forums WHERE user_id = ?";
                        $stmt_posts = $conn->prepare($sql_posts);
                        $stmt_posts->bind_param("i", $userId); 
                        $stmt_posts->execute();
                        $result_posts = $stmt_posts->get_result();
                        
                        if ($row_posts = $result_posts->fetch_assoc()) {
                            $post_count = $row_posts['total_posts'];
                        }
                        $stmt_posts->close();

                        $sql_likes = "SELECT COALESCE(SUM(post_likes.like_count), 0) AS total_likes FROM (SELECT gf.id, COUNT(pl.like_id) AS like_count FROM general_forums gf INNER JOIN post_likes pl ON gf.id = pl.post_id WHERE gf.user_id = ? GROUP BY gf.id) AS post_likes";
                        $stmt_likes = $conn->prepare($sql_likes);
                        $stmt_likes->bind_param("i", $userId);
                        $stmt_likes->execute();
                        $result_likes = $stmt_likes->get_result();
                        
                        if ($row_likes = $result_likes->fetch_assoc()) {
                            $likes_received = $row_likes['total_likes']; 
                        }
                        $stmt_likes->close();
                    }

                    $avatarHtml = '';
                    $avatarSize = '75px';
                    
                    if (!empty($userIcon) && $userIcon !== 'img/default-user.png') {
                        $avatarHtml = '<img src="' . htmlspecialchars($userIcon) . '" alt="' . htmlspecialchars($displayName) . ' Icon" class="user-icon-summary">';
                    } else {
                        $initials = '';
                        $nameParts = explode(' ', $displayName);
                        
                        foreach ($nameParts as $part) {
                            if (!empty($part)) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            if (strlen($initials) >= 2) break;
                        }
                        
                        if (empty($initials)) {
                            $initials = '?';
                        }
                        
                        $avatarHtml = '<div class="user-icon-summary" style="width: ' . $avatarSize . '; height: ' . $avatarSize . '; border-radius: 50%; background: #6a2c70; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; margin: 0 auto 10px auto;">' . htmlspecialchars($initials) . '</div>';
                    }
                    ?>
                    
                    <div class="user-profile-summary">
                        <?php echo $avatarHtml; ?>
                        <p class="user-name-summary"><?php echo htmlspecialchars($displayName); ?></p>
                    </div>

                    <li class="stat-item">
                        <span class="stat-label">Posts:</span>
                        <span class="stat-value"><?php echo $post_count; ?></span>
                    </li>
                    <li class="stat-item">
                        <span class="stat-label">Likes Received:</span>
                        <span class="stat-value"><?php echo $likes_received; ?></span>
                    </li>
                </ul>
            </div>

            <div class="sidebar-box">
              <h3>Pinned</h3>
              <ul>
                <li><a href="#" onclick="openModal('rulesModal')">📌 Forum Rules</a></li>
                <li><a href="#" onclick="openModal('welcomeModal')">📌 Welcome Post</a></li>
              </ul>
            </div>

            <h3>❤️ Recent Likes</h3>
            <ul>
              <?php
              $sql = "SELECT u.first_name, u.last_name, u.icon, gf.title FROM post_likes pl INNER JOIN general_forums gf ON pl.post_id = gf.id INNER JOIN users u ON pl.user_id = u.user_id WHERE gf.user_id = {$userId} ORDER BY pl.like_id DESC LIMIT 4";

              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      $likerName = htmlspecialchars($row['first_name']); 
                      $postTitle = htmlspecialchars($row['title']);
                      $likerIconPath = $row['icon'] ?? ''; 
                      $firstName = $row['first_name'] ?? '';
                      $lastName = $row['last_name'] ?? '';
                      
                      $avatarSize = '25px'; 
                      $avatarMargin = '4px';
                      $likerAvatar = '';
                      
                      if (!empty($likerIconPath)) {
                          $likerAvatar = '<img src="' . htmlspecialchars($likerIconPath) . '" alt="Liker Icon" style="width:' . $avatarSize . '; height:' . $avatarSize . '; border-radius:50%; margin-right: ' . $avatarMargin . ';">';
                      } else {
                          $initials = '';
                          if (!empty($firstName)) $initials .= strtoupper(substr($firstName, 0, 1));
                          if (!empty($lastName)) $initials .= strtoupper(substr($lastName, 0, 1));
                          $initials = substr($initials, 0, 2);
                          if (empty($initials)) $initials = '?';
                      
                          $likerAvatar = '<div style="width:' . $avatarSize . '; height:' . $avatarSize . '; border-radius:50%; background:#6a2c70; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:bold; margin-right: ' . $avatarMargin . ';">' . htmlspecialchars($initials) . '</div>';
                      }

                      if (strlen($postTitle) > 30) {
                          $postTitle = substr($postTitle, 0, 30) . '...';
                      }
                      
                      echo '<li style="display: flex; align-items: center;">' . $likerAvatar . '<div style="flex: 1; min-width: 0; line-height: 1.3; font-size: 14px;"><strong>' . $likerName . '</strong> liked your post: <em>' . $postTitle . '</em></div></li>';
                  }
              } else {
                  echo "<li>No recent likes yet.</li>";
              }
              ?>
            </ul>
        </div>

        <!-- MAIN FORUM CONTENT -->
        <div class="chat-container">
            <?php if (isset($_SESSION['appeal_success'])): ?>
                <div style="background: #d1ecf1; color: #0c5460; padding: 15px; text-align: center; border-radius: 8px; margin: 20px auto; max-width: 600px; border: 1px solid #bee5eb;">
                    📝 <?php echo htmlspecialchars($_SESSION['appeal_success']); ?>
                </div>
                <?php unset($_SESSION['appeal_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['appeal_error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; text-align: center; border-radius: 8px; margin: 20px auto; max-width: 600px; border: 1px solid #f5c6cb;">
                    ❌ <?php echo htmlspecialchars($_SESSION['appeal_error']); ?>
                </div>
                <?php unset($_SESSION['appeal_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['report_success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; text-align: center; border-radius: 8px; margin: 20px auto; max-width: 600px; border: 1px solid #c3e6cb;">
                    ✅ Report submitted successfully!
                </div>
                <?php unset($_SESSION['report_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['report_error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; text-align: center; border-radius: 8px; margin: 20px auto; max-width: 600px; border: 1px solid #f5c6cb;">
                    ❌ <?php echo htmlspecialchars($_SESSION['report_error']); ?>
                </div>
                <?php unset($_SESSION['report_error']); ?>
            <?php endif; ?>

            <?php if ($isBanned): ?>
                <div class="banned-message">
                    <h2>⛔ You have been banned</h2>
                    
                    <?php if ($ban_details['ban_type'] === 'Permanent'): ?>
                        <span class="permanent-ban-label">PERMANENT BAN</span>
                    <?php endif; ?>
                    
                    <div class="ban-reason">
                        <strong>Reason:</strong> <?php echo htmlspecialchars($ban_details['reason']); ?>
                    </div>
                    
                    <div class="ban-status">
                        <strong>Ban Type:</strong> <?php echo htmlspecialchars($ban_details['ban_type']); ?>
                    </div>
                    
                    <?php if ($ban_details['ban_type'] === 'Temporary' && $ban_details['ban_until'] && $ban_details['ban_until'] !== ''): ?>
                        <p style="margin-top: 15px; color: #721c24; font-size: 14px;">
                            <strong>Ban Duration:</strong> <?php echo htmlspecialchars($ban_details['ban_duration_text']); ?>
                        </p>
                        
                        <p style="margin-top: 10px; color: #721c24; font-size: 14px;">
                            <strong>Unban Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($ban_details['ban_until'])); ?>
                        </p>
                        
                        <div class="ban-countdown">
                            <span>Time remaining:</span><br>
                            <span id="countdown-timer">Loading...</span>
                        </div>
                        
                        <script>
                            function updateCountdown() {
                                const unbanTime = new Date('<?php echo $banDatetimeJS; ?>').getTime();
                                const currentTime = new Date().getTime();
                                const timeRemaining = unbanTime - currentTime;
                                
                                if (timeRemaining <= 0) {
                                    document.getElementById('countdown-timer').textContent = 'Ban has expired. Please refresh the page.';
                                    document.getElementById('countdown-timer').style.color = '#28a745';
                                    return;
                                }
                                
                                const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
                                const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
                                const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);
                                
                                document.getElementById('countdown-timer').textContent = 
                                    days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
                            }
                            
                            updateCountdown();
                            setInterval(updateCountdown, 1000);
                        </script>
                    <?php elseif ($ban_details['ban_type'] === 'Permanent'): ?>
                        <p style="margin-top: 20px; color: #721c24; font-weight: bold; font-size: 16px;">
                            This is a permanent ban and cannot be lifted.
                        </p>
                    <?php endif; ?>
                    
                    <p style="margin-top: 20px; font-size: 14px; color: #721c24;">
                        If you believe this is a mistake, please contact an administrator.
                    </p>

                    <button class="appeal-btn" onclick="openModal('appealModal')" style="margin-top: 15px; padding: 10px 20px; background-color: #5d2c69; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;">Submit Appeal</button>
                </div>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
                <p>No posts yet. Be the first to create one!</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-container" id="post-<?php echo $post['id']; ?>"> <div class="post-header">
                           <?php 
            $iconPath = $post['user_icon'] ?? ''; 
            $postDisplayName = $post['display_name'] ?? 'Guest'; 

            if (!empty($iconPath) && $iconPath !== 'img/default-user.png') {
                ?>
                <img src="<?php echo htmlspecialchars($iconPath); ?>" alt="<?php echo htmlspecialchars($postDisplayName); ?> Icon" class="user-avatar">
                <?php
            } else {
                $initials = '';
                $nameParts = explode(' ', $postDisplayName);
                
                foreach ($nameParts as $part) {
                    if (!empty($part)) {
                         $initials .= strtoupper(substr($part, 0, 1));
                    }
                    if (strlen($initials) >= 2) break;
                }
                
                if (empty($initials)) {
                    $initials = '?';
                }
                ?>
                <div class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: #6a2c70; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold;">
                    <?php echo htmlspecialchars($initials); ?>
                </div>
                <?php
            }
            ?>
                            
                            <div class="header-content">
                                <div class="post-author-details">
                                    <div class="post-author"><?php echo htmlspecialchars($post['display_name']); ?></div>
                                    <div class="post-date"><?php echo date("F j, Y, g:i a", strtotime($post['timestamp'])); ?></div>
                                </div>

                                <?php if ($post['user_id'] == $userId): ?>
                                    <div class="post-options">
                                        <button class="options-button" type="button" aria-label="Post options">
                                            <i class="fa-solid fa-ellipsis"></i>
                                        </button>
                                        <form class="delete-post-form" action="forums.php" method="POST">
                                            <input type="hidden" name="action" value="delete_post">
                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                            <button type="button" class="delete-post-button open-delete-post-dialog">Delete post</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>

                        <div class="post-content">
                            <?php
                                $formattedMessage = makeLinksClickable($post['message']);
                                echo $formattedMessage;
                            ?>
                            <br>
                            <?php if (!empty($post['file_path'])): ?>
                                <img src="<?php echo htmlspecialchars($post['file_path']); ?>" alt="Post Image">
                            <?php endif; ?>
                        </div>

                        <div class="post-actions">
                            <button class="action-btn like-btn <?php echo $post['has_liked'] ? 'liked' : ''; ?>" data-post-id="<?php echo $post['id']; ?>" <?php if($isBanned) echo 'disabled'; ?>>
                                ❤️ <span class="like-count"><?php echo $post['likes']; ?></span>
                            </button>
                            <button class="action-btn" onclick="toggleCommentForm(this)" <?php if($isBanned) echo 'disabled'; ?>>💬 Comment</button>
                            <button class="report-btn" onclick="openReportModal(<?php echo $post['id']; ?>)" <?php if($isBanned) echo 'disabled'; ?>>
                                <i class="fa fa-flag"></i> Report
                            </button>
                        </div>

                        <form class="join-convo-form" style="display:none;" action="forums.php" method="POST">
                            <input type="hidden" name="action" value="create_comment">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <input type="text" name="comment_message" placeholder="Join the conversation" required>
                            <button type="submit">Post</button>
                        </form>

                         <div class="comment-section">
        <?php 
        $current_user_id = $userId; 
        
        $commentAvatarSize = '30px'; 
        $commentFontSize = '14px';

        foreach ($post['comments'] as $comment): 
        ?>
            <div class="comment" data-comment-id="<?php echo $comment['id']; ?>">
                
                <?php
                $commentAvatarHtml = '';
                $commenterIcon = $comment['user_icon'];
                $commenterName = $comment['display_name'];

                if (!empty($commenterIcon) && $commenterIcon !== 'img/default-user.png') {
                    $commentAvatarHtml = '<img src="' . htmlspecialchars($commenterIcon) . '" alt="' . htmlspecialchars($commenterName) . ' Icon" class="user-avatar" style="width: ' . $commentAvatarSize . '; height: ' . $commentAvatarSize . ';">';
                } else {
                    $initials = '';
                    $nameParts = explode(' ', $commenterName);
                    
                    foreach ($nameParts as $part) {
                        if (!empty($part)) {
                             $initials .= strtoupper(substr($part, 0, 1));
                        }
                        if (strlen($initials) >= 2) break;
                    }
                    
                    if (empty($initials)) {
                        $initials = '?';
                    }
                    
                    $commentAvatarHtml = '<div class="user-avatar" style="width: ' . $commentAvatarSize . '; height: ' . $commentAvatarSize . '; border-radius: 50%; background: #6a2c70; color: #fff; display: flex; align-items: center; justify-content: center; font-size: ' . $commentFontSize . '; font-weight: bold; line-height: 1;">' . htmlspecialchars($initials) . '</div>';
                }

                echo $commentAvatarHtml;
                ?>

                <div class="comment-author-details">
                    <div class="comment-bubble">
                        <strong><?php echo htmlspecialchars($commenterName); ?></strong>
                        <?php echo htmlspecialchars($comment['message']); ?>
                    </div>
                    <div class="comment-timestamp">
                        <?php echo date("F j, Y, g:i a", strtotime($comment['timestamp'])); ?>
                        
                       <?php if ($current_user_id && $current_user_id == $comment['user_id'] && !$isBanned): ?>
                       <button class="delete-btn" onclick="deleteComment(<?php echo htmlspecialchars($comment['id']); ?>)" title="Delete Comment">
                         🗑️ </button>
                        <?php endif; ?>
                        
                        <?php if (!$isBanned): ?>
                        <button class="report-btn" onclick="openReportModal(<?php echo htmlspecialchars($comment['id']); ?>)" title="Report Comment">
                            <i class="fa fa-flag"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!$isBanned): ?>
            <button class="create-post-btn">+</button>
        <?php endif; ?>

        <div class="modal-overlay" id="create-post-modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2>Create a post</h2>
                <button class="close-btn">&times;</button>
            </div>
            <form id="post-form" action="forums.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_post">
                <input type="text" name="post_title" placeholder="Title" class="title-input" required>
                <div class="content-editor">
                    <div class="toolbar">
                        <button type="button" class="btn" data-element="bold"><i class="fa fa-bold"></i></button>
                        <button type="button" class="btn" data-element="italic"><i class="fa fa-italic"></i></button>
                        <button type="button" class="btn" data-element="underline"><i class="fa fa-underline"></i></button>
                        <button type="button" class="btn" data-element="insertUnorderedList"><i class="fa fa-list-ul"></i></button>
                        <button type="button" class="btn" data-element="insertOrderedList"><i class="fa fa-list-ol"></i></button>
                        <button type="button" class="btn" data-element="link"><i class="fa fa-link"></i></button>
                    </div>
                    <div class="text-content" contenteditable="true"></div>
                </div>
                <input type="hidden" name="post_content" id="post-content-input">

                <div id="image-upload-container">
                    <label for="post_image" class="image-upload-area" id="initial-upload-box">
                        <span id="upload-text"><i class="fa fa-cloud-upload"></i> Upload an Image (optional)</span>
                    </label>
                    <input type="file" name="post_image" id="post_image" accept="image/*" style="display: none;">
                </div>

                <button type="submit" class="post-btn">Post</button>
            </form>
        </div>
    </div>

        <div class="modal-overlay" id="report-modal-overlay" style="display:none;">
            <div class="modal">
                <div class="modal-header">
                    <h2>Report Content</h2>
                    <button class="close-btn" onclick="closeReportModal()">&times;</button>
                </div>
                <form id="report-form" action="forums.php" method="POST">
                    <input type="hidden" name="action" value="submit_report">
                    <input type="hidden" id="report-post-id" name="post_id" value="">
                    <p>Please provide a reason for reporting this content:</p>
                    <textarea name="reason" rows="4" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 1rem;"></textarea>
                    <button type="submit" class="post-btn">Submit Report</button>
                </form>
            </div>
        </div>

        <div class="modal-overlay" id="appealModal" style="display:none; z-index: 99999;">
            <div class="modal" style="max-width: 450px;">
                <div class="modal-header">
                    <h2>Submit Ban Appeal</h2>
                    <button class="close-btn" onclick="closeModal('appealModal')">&times;</button>
                </div>
                <form id="appeal-form" action="forums.php" method="POST">
                    <input type="hidden" name="action" value="submit_appeal">
                    <p style="margin-bottom: 10px;">Please provide a detailed, respectful reason why your ban should be lifted.</p>
                    <textarea name="appeal_reason" rows="6" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 1rem;" placeholder="Your appeal reason..."></textarea>
                    <button type="submit" class="post-btn" style="width: 100%;">Submit Appeal</button>
                </form>
            </div>
        </div>

        <div class="sidebar-right">
            <div class="sidebar-box ad-box" style="background-color: #f4e4fcff; border: 1px solid #4e036fff; padding: 10px; border-radius: 6px; text-align: center; margin-bottom: 15px;">
                <h3 style="font-size: 14px; margin-bottom: 5px;">🏆 Level Up Your Skills Today!</h3>
                <p style="font-size: 12px; margin-bottom: 10px; color: #4a148c; font-weight: 500;">Explore our curated collection of online courses.</p>
                <a href="course.php" style="display: block; padding: 8px; background-color: #6f2c9fff; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 600;" onmouseover="this.style.backgroundColor='#4a148c'" onmouseout="this.style.backgroundColor='#6f2c9fff'">Check Now!</a>
            </div>

            <h3>⭐ Top Contributors</h3>
            <div class="contributors">
                <?php
                $sql = "SELECT gf.user_id, gf.display_name, COUNT(gf.id) AS post_count, u.icon FROM general_forums gf LEFT JOIN users u ON gf.user_id = u.user_id GROUP BY gf.user_id, gf.display_name, u.icon ORDER BY post_count DESC LIMIT 3";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $avatar = '';
                        
                if (!empty($row['icon'])) {
                    $fullIconUrl = htmlspecialchars($row['icon']); 
                    $avatar = '<img src="' . $fullIconUrl . '" alt="User Avatar" width="35" height="35" style="border-radius:50%; object-fit: cover;">'; 
                } else {
                    $initials = '';
                    $nameParts = explode(' ', $row['display_name']);
                    foreach ($nameParts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    $initials = substr($initials, 0, 2);
                    $avatar = '<div style="width:35px; height:35px; border-radius:50%; background:#6a2c70; color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:bold;">' . htmlspecialchars($initials) . '</div>';
                }
        ?>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                    <?php echo $avatar; ?>
                    <span><?php echo htmlspecialchars($row['display_name']); ?> (<?php echo $row['post_count']; ?>)</span>
                </div>
        <?php
                    }
                } else {
                    echo "<p>No contributors yet.</p>";
                }
                ?>
            </div>

            <div class="sidebar-box updates-box">
              <h3>📋 Latest Updates</h3>

              <?php
              $sql = "SELECT gf.display_name, gf.title, gf.message, gf.timestamp, u.icon FROM general_forums gf LEFT JOIN users u ON gf.user_id = u.user_id WHERE gf.chat_type = 'forum' AND gf.status = 'active' ORDER BY gf.timestamp DESC LIMIT 3";
              $result = $conn->query($sql);

              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      $avatar = '';
                      if (!empty($row['icon'])) {
                          $iconPath = $row['icon'];
                          $avatar = '<img src="' . htmlspecialchars($iconPath) . '" alt="User" width="30" height="30" style="border-radius:50%; object-fit: cover;">';
                      } else {
                          $initials = '';
                          $nameParts = explode(' ', $row['display_name']);
                          foreach ($nameParts as $part) {
                              $initials .= strtoupper(substr($part, 0, 1));
                          }
                          $initials = substr($initials, 0, 2);
                          $avatar = '<div style="width:30px; height:30px; border-radius:50%; background:#6f42c1; color:#fff; display:flex; align-items:center; justify-content:center; font-size:7px; font-weight:bold;">' . htmlspecialchars($initials) . '</div>';
                      }

                      $timeAgo = date("M d, Y H:i", strtotime($row['timestamp']));
              ?>
                  <div class="update" style="display:flex; gap:8px; align-items:flex-start; margin-bottom:8px;">
                    <?php echo $avatar; ?>
                    <div style="flex: 1; min-width: 0; line-height: 1.3;">
                      <p style="margin:0;"><strong><?php echo htmlspecialchars($row['display_name']); ?></strong> posted "<?php echo htmlspecialchars($row['title']); ?>"</p>
                      <span class="time" style="font-size:12px; color:#666;"><?php echo $timeAgo; ?></span>
                    </div>
                  </div>
              <?php
                  }
              } else {
                  echo "<p>No recent updates.</p>";
              }
              ?>
            </div>

            <div id="rulesModal" class="modal-overlay"> 
                <div class="modal-content-box"> 
                    <span class="close" onclick="closeModal('rulesModal')">&times;</span>
                    <h2>📌 Forum Rules: Community Guidelines</h2>
                    <div class="modal-body">
                        <p>Welcome to our community! To ensure a positive and productive environment for everyone, please adhere to these core rules:</p>
                        
                        <h3>1. Be Respectful and Professional</h3>
                        <ul>
                            <li><strong>No Harassment:</strong> Do not attack, insult, or harass other members. Keep criticism constructive and focused on the topic, not the person.</li>
                            <li><strong>Respect Privacy:</strong> Do not share personal information (yours or others') without explicit consent.</li>
                        </ul>

                        <h3>2. Keep Content Relevant</h3>
                        <ul>
                            <li><strong>Stay on Topic:</strong> Posts should relate to the subject matter of the forum (e.g., mentorship, career, technology, etc.).</li>
                            <li><strong>No Spam or Self-Promotion:</strong> Excessive self-promotion, repeated posting of the same content, or link-dropping is prohibited.</li>
                        </ul>

                        <h3>3. Maintain Integrity</h3>
                        <ul>
                            <li><strong>Honesty:</strong> Do not post false or misleading information.</li>
                            <li><strong>Report Issues:</strong> If you see a post that violates these rules, please use the 'Report Post' function instead of engaging in an argument.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div id="welcomeModal" class="modal-overlay"> 
                <div class="modal-content-box"> 
                    <span class="close" onclick="closeModal('welcomeModal')">&times;</span>
                    <h2>📣 Welcome to the COACH Forum!</h2>
                    <div class="modal-body">
                        <p>We're thrilled to have you join the <strong>COACH Forum</strong>, your dedicated hub for <strong>guidance, mentorship, and professional development</strong>. This space is designed to foster valuable connections, offer actionable advice, and support your journey towards personal and professional growth.</p>

                        <h3>What You'll Find Here:</h3>
                        <ul>
                            <li><strong>Expert Guidance:</strong> Connect with experienced coaches and mentors across various industries who are ready to share their insights and perspectives.</li>
                            <li><strong>Goal Setting & Strategy:</strong> Discuss career roadmaps, personal challenges, and effective strategies for achieving your long-term objectives.</li>
                            <li><strong>Peer Support:</strong> Engage with a community of ambitious individuals who are facing similar challenges and celebrating successes together.</li>
                            <li><strong>Resource Sharing:</strong> Access curated articles, tools, and recommended readings shared by members to enhance your skills and knowledge base.</li>
                        </ul>

                        <p>Remember to check the <strong>Forum Rules</strong> before posting. Let's start achieving your goals!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="mentee.js"></script>
<script>
    let deletePostFormToSubmit = null; 
    let commentIdToDelete = null;

    function openReportModal(postId) {
        document.getElementById('report-post-id').value = postId;
        document.querySelector('#report-form textarea[name="reason"]').value = '';
        document.getElementById('report-modal-overlay').style.display = 'flex';
    }

    function closeReportModal() {
        document.getElementById('report-modal-overlay').style.display = 'none';
    }

    document.addEventListener("DOMContentLoaded", function() {
        const profileIcon = document.getElementById("profile-icon");
        const profileMenu = document.getElementById("profile-menu");
        const logoutDialog = document.getElementById("logoutDialog");
        const cancelLogoutBtn = document.getElementById("cancelLogout");
        const confirmLogoutBtn = document.getElementById("confirmLogoutBtn");

        if (profileIcon && profileMenu) {
            profileIcon.addEventListener("click", function (e) {
                e.preventDefault();
                profileMenu.classList.toggle("show");
                profileMenu.classList.toggle("hide");
            });
            
            document.addEventListener("click", function (e) {
                if (!profileIcon.contains(e.target) && !profileMenu.contains(e.target) && !e.target.closest('#profile-menu')) {
                    profileMenu.classList.remove("show");
                    profileMenu.classList.add("hide");
                }
            });
        }

        window.confirmLogout = function(e) { 
            if (e) e.preventDefault();
            if (logoutDialog) {
                logoutDialog.style.display = "flex";
            }
        }

        if (cancelLogoutBtn && logoutDialog) {
            cancelLogoutBtn.addEventListener("click", function(e) {
                e.preventDefault(); 
                logoutDialog.style.display = "none";
            });
        }

        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener("click", function(e) {
                e.preventDefault(); 
                window.location.href = "../logout.php"; 
            });
        }

        const postImageInput = document.getElementById('post_image');
        const uploadText = document.getElementById('upload-text');
        let defaultUploadText = '';
        if (uploadText) {
            defaultUploadText = uploadText.innerHTML; 
            postImageInput.addEventListener('change', function(event) {
                if (event.target.files.length > 0) {
                    const fileName = event.target.files[0].name;
                    uploadText.innerHTML = `<i class="fa fa-check-circle"></i> ${fileName}`;
                } else {
                    uploadText.innerHTML = defaultUploadText;
                }
            });
        }

        const createPostBtn = document.querySelector('.create-post-btn');
        const createPostModal = document.querySelector('#create-post-modal-overlay');
        if (createPostBtn && createPostModal) {
            const closeBtn = createPostModal.querySelector('.close-btn');

            createPostBtn.addEventListener('click', () => {
                const titleInput = createPostModal.querySelector('.title-input');
                const contentDiv = createPostModal.querySelector('.text-content');
                if (titleInput) titleInput.value = '';
                if (contentDiv) contentDiv.innerHTML = '';
                
                if (uploadText) {
                    postImageInput.value = ''; 
                    uploadText.innerHTML = defaultUploadText; 
                }
                
                createPostModal.style.display = 'flex';
            });

            closeBtn.addEventListener('click', () => {
                createPostModal.style.display = 'none';
            });

            createPostModal.addEventListener('click', (e) => {
                if (e.target === createPostModal) {
                    createPostModal.style.display = 'none';
                }
            });
        }

        const deletePostDialog = document.getElementById('deletePostDialog');
        const cancelDeletePostBtn = document.getElementById('cancelDeletePost');
        const confirmDeletePostBtn = document.getElementById('confirmDeletePostBtn');

        if (cancelDeletePostBtn && deletePostDialog) {
            cancelDeletePostBtn.addEventListener('click', function() {
                deletePostDialog.style.display = 'none';
                deletePostFormToSubmit = null;
            });
        }

        if (confirmDeletePostBtn && deletePostDialog) {
            confirmDeletePostBtn.addEventListener('click', function() {
                if (deletePostFormToSubmit) {
                    deletePostFormToSubmit.onsubmit = null; 
                    deletePostFormToSubmit.submit();
                }
                deletePostDialog.style.display = 'none';
            });
        }

        const deleteCommentDialog = document.getElementById('deleteCommentDialog');
        const cancelDeleteCommentBtn = document.getElementById('cancelDeleteComment');
        const confirmDeleteCommentBtn = document.getElementById('confirmDeleteCommentBtn');

        if (cancelDeleteCommentBtn && deleteCommentDialog) {
            cancelDeleteCommentBtn.addEventListener('click', function() {
                deleteCommentDialog.style.display = 'none';
                commentIdToDelete = null;
            });
        }

        if (confirmDeleteCommentBtn && deleteCommentDialog) {
            confirmDeleteCommentBtn.addEventListener('click', function() {
                processDeleteComment();
            });
        }
        
        const formatBtns = document.querySelectorAll('.modal .toolbar .btn');
        const contentDiv = document.querySelector('.modal .text-content');
        if (contentDiv) {
            formatBtns.forEach(element => {
                element.addEventListener('click', () => {
                    let command = element.dataset['element'];
                    contentDiv.focus();
                    if (command === 'link') {
                        let url = prompt('Enter the link here:', 'https://');
                        if (url) document.execCommand('createLink', false, url);
                    } else {
                        document.execCommand(command, false, null);
                    }
                });
            });
        }

        const postForm = document.getElementById('post-form');
        const contentInput = document.getElementById('post-content-input');
        if (postForm && contentDiv && contentInput) {
            postForm.addEventListener('submit', function() {
                contentInput.value = contentDiv.innerHTML;
            });
        }

        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function() {
                if (this.disabled) return;
                
                const postId = this.getAttribute('data-post-id');
                const likeCountElement = this.querySelector('.like-count');
                const hasLiked = this.classList.contains('liked');
                let action = hasLiked ? 'unlike_post' : 'like_post';

                fetch('forums.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=${action}&post_id=${postId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let currentLikes = parseInt(likeCountElement.textContent);
                        if (data.action === 'liked') {
                            likeCountElement.textContent = currentLikes + 1;
                            this.classList.add('liked');
                        } else if (data.action === 'unliked') {
                            likeCountElement.textContent = currentLikes - 1;
                            this.classList.remove('liked');
                        }
                    }
                })
                .catch(error => console.error('Error handling like:', error));
            });
        });
        
        document.addEventListener("click", function (event) {
            const optionsButton = event.target.closest(".options-button");
            if (optionsButton) {
                event.stopPropagation();
                const deleteForm = optionsButton.nextElementSibling;

                document.querySelectorAll(".delete-post-form.show").forEach(form => {
                    if (form !== deleteForm) {
                        form.classList.remove("show");
                    }
                });

                if (deleteForm) {
                    deleteForm.classList.toggle("show");
                }
                return;
            }

            const innerDeleteButton = event.target.closest(".open-delete-post-dialog");
            if (innerDeleteButton) {
                event.preventDefault();
                event.stopPropagation();

                deletePostFormToSubmit = innerDeleteButton.closest(".delete-post-form");
                
                const deletePostDialog = document.getElementById("deletePostDialog");
                if (deletePostDialog) {
                    deletePostDialog.style.display = "flex";
                }
                
                innerDeleteButton.closest(".delete-post-form").classList.remove("show");
                return;
            }

            document.querySelectorAll(".delete-post-form.show").forEach(form => {
                if (!form.contains(event.target)) {
                    form.classList.remove("show");
                }
            });
        });

        // *** ADD: Report modal close button handler ***
        const reportModalCloseBtn = document.querySelector('#report-modal-overlay .close-btn');
        if (reportModalCloseBtn) {
            reportModalCloseBtn.addEventListener('click', closeReportModal);
        }
        
        // *** ADD: Close report modal when clicking outside ***
        const reportModal = document.getElementById('report-modal-overlay');
        if (reportModal) {
            reportModal.addEventListener('click', function(e) {
                if (e.target === reportModal) {
                    closeReportModal();
                }
            });
        }
    });

    function toggleCommentForm(btn) {
        const form = btn.closest('.post-container').querySelector('.join-convo-form');
        form.style.display = form.style.display === 'none' ? 'flex' : 'none';
    }

    function openModal(id) {
        document.getElementById(id).style.display = 'flex'; 
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    window.onclick = function(event) {
        let modals = document.querySelectorAll(".modal-overlay, .logout-dialog");
        modals.forEach(m => {
            if (event.target == m) {
                m.style.display = "none";
            }
        });
    }

    function refreshSidebarLikes() {
        fetch('forums.php?action=get_likes') 
            .then(response => {
                if (!response.ok) {
                        throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.total_likes !== undefined) {
                    const likesElement = document.getElementById('likes-received-count');
                    if (likesElement) {
                        likesElement.textContent = data.total_likes;
                    }
                }
            })
            .catch(error => console.error('Error refreshing sidebar likes:', error));
    }

    function deleteComment(commentId) {
        commentIdToDelete = commentId; 
        document.getElementById('deleteCommentDialog').style.display = 'flex';
    }

    function processDeleteComment() {
        const commentId = commentIdToDelete;

        const formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', commentId);

        fetch('forums.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const commentElement = document.querySelector(`.comment[data-comment-id="${commentId}"]`);
                if (commentElement) {
                    commentElement.remove();
                }
            } else {
                alert("Error: " + (data.message || "Could not delete comment."));
            }
            document.getElementById('deleteCommentDialog').style.display = 'none';
        })
        .catch(error => {
            console.error('Error deleting comment:', error);
            alert("An error occurred while trying to delete the comment.");
            document.getElementById('deleteCommentDialog').style.display = 'none';
        });
    }
</script>

<div id="deletePostDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Post Deletion</h3>
        <p>Are you sure you want to permanently delete this post and all its comments?</p>
        <div class="dialog-buttons">
            <button id="cancelDeletePost" type="button">Cancel</button>
            <button id="confirmDeletePostBtn" type="button" style="background-color: #5d2c69; color: white;">Delete Permanently</button>
        </div>
    </div>
</div>

<div id="deleteCommentDialog" class="logout-dialog" style="display: none;">
    <div class="logout-content">
        <h3>Confirm Comment Deletion</h3>
        <p>Are you sure you want to delete this comment?</p>
        <div class="dialog-buttons">
            <button id="cancelDeleteComment" type="button">Cancel</button>
            <button id="confirmDeleteCommentBtn" type="button" style="background-color: #5d2c69; color: white;">Delete</button>
        </div>
    </div>
    <input type="hidden" id="comment-to-delete-id" value="">
</div>

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