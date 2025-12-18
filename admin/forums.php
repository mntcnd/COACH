<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Admin') {
    // If not a Mentee, redirect to the login page.
    header("Location: login.php");
    exit();
}

// --- FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

$username = $_SESSION['username'];
$displayName = '';
$userIcon = 'img/default-user.png'; // Default icon path
$userId = null; // New variable for user_id

$stmt = $conn->prepare("SELECT user_id, first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $userId = $row['user_id'];
    $displayName = $row['first_name'] . ' ' . $row['last_name'];
    if (!empty($row['icon'])) $userIcon = $row['icon'];
}
$stmt->close(); // Close the statement after use

// Check if user ID was found
if ($userId === null) {
    // Handle the case where the user's ID couldn't be found (e.g., redirect to login)
    header("Location: login.php");
    exit();
}

// --- BAN CHECK ---
$isBanned = false;
$ban_check_stmt = $conn->prepare("SELECT reason, ban_until FROM banned_users WHERE username = ? AND (ban_until IS NULL OR ban_until > NOW())");
$ban_check_stmt->bind_param("s", $username);
$ban_check_stmt->execute();
$ban_result = $ban_check_stmt->get_result();
if ($ban_result->num_rows > 0) {
    $isBanned = true;
    $ban_details = $ban_result->fetch_assoc();
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBanned) {
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
    $stmt = $conn->prepare("INSERT INTO general_forums (user_id, display_name, message, is_admin, is_mentor, chat_type, title, file_path, file_name, user_icon) VALUES (?, ?, ?, ?, ?, 'forum', ?, ?, ?, ?)");
    
    // Initialize admin/mentor flags as this page is for Mentees
    $isAdmin = 0;
    $isMentor = 0;

    // CORRECTED: The type string now matches the variables, and the variable $userIcon is spelled correctly.
    $stmt->bind_param("issiissss", $userId, $displayName, $postContent, $isAdmin, $isMentor, $postTitle, $filePath, $fileName, $userIcon);
    $stmt->execute();
}
        header("Location: forums.php");
        exit();
    }

    // Handle New Comment
    elseif ($action === 'create_comment' && isset($_POST['comment_message'], $_POST['post_id'])) {
        $commentMessage = filterProfanity(trim($_POST['comment_message']));
        $postId = intval($_POST['post_id']);
        if (!empty($commentMessage) && $postId > 0) {
            $stmt = $conn->prepare("INSERT INTO general_forums (user_id, title, display_name, message, is_admin, is_mentor, chat_type, forum_id, user_icon) VALUES (?, 'User commented', ?, ?, ?, ?, 'comment', ?, ?)");
            $isAdmin = 0;
            $isMentor = 0;
            $stmt->bind_param("issiiis", $userId, $displayName, $commentMessage, $isAdmin, $isMentor, $postId, $userIcon);
            $stmt->execute();
        }
        header("Location: forums.php");
        exit();
    }
    
    // Handle Report
    elseif ($action === 'report_post' && isset($_POST['post_id'], $_POST['reason'])) {
        $postId = intval($_POST['post_id']);
        $reason = trim($_POST['reason']);
        if ($postId > 0 && !empty($reason)) {
            $stmt = $conn->prepare("INSERT INTO reports (post_id, reported_by_username, reason) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $postId, $username, $reason);
            $stmt->execute();
        }
        header("Location: forums.php");
        exit();
    }

    // Handle Delete Post
    // Handle Delete Post
    elseif ($action === 'delete_post' && isset($_POST['post_id'])) {
        $postId = intval($_POST['post_id']);
        if ($postId > 0) {
            // Start a database transaction
            $conn->begin_transaction();

            try {
                // Step 1: Delete all comments linked to the post's ID
                $comments_stmt = $conn->prepare("DELETE FROM general_forums WHERE forum_id = ? AND chat_type = 'comment'");
                $comments_stmt->bind_param("i", $postId);
                $comments_stmt->execute();
                $comments_stmt->close();

                // Step 2: Delete the main post (includes security check for ownership)
                $post_stmt = $conn->prepare("DELETE FROM general_forums WHERE id = ? AND user_id = ?");
                $post_stmt->bind_param("ii", $postId, $userId);
                $post_stmt->execute();
                $post_stmt->close();

                // If both queries succeed, commit the changes
                $conn->commit();

            } catch (mysqli_sql_exception $exception) {
                // If any part fails, roll back all changes
                $conn->rollback();
                // You can add error logging here if needed
            }
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
              WHERE c.chat_type = 'forum'
              ORDER BY c.timestamp DESC";
$postsStmt = $conn->prepare($postQuery);

if ($postsStmt === false) {
    // Handle the SQL preparation error
    die('SQL preparation failed: ' . htmlspecialchars($conn->error));
}

$postsStmt->bind_param("i", $userId);
$postsStmt->execute();
$postsResult = $postsStmt->get_result();

if ($postsResult && $postsResult->num_rows > 0) {
    while ($row = $postsResult->fetch_assoc()) {
        $comments = [];
        $commentsStmt = $conn->prepare("SELECT * FROM general_forums WHERE chat_type = 'comment' AND forum_id = ? ORDER BY timestamp ASC");
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
    $username = $_SESSION['username'];
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
$returnUrl = "dashboard.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forums | Admin</title>
        <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">

    <link rel="stylesheet" href="css/navbar.css" />
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="css/forums.css"/>


</head>

<body>

    <header class="chat-header">
        <h1>General Chat</h1>
        <div class="actions">
            <button onclick="window.location.href='<?php echo $returnUrl; ?>'">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Back to Dashboard
            </button>
        </div>
    </header>

    <div class="chat-container">
        <?php if ($isBanned): ?>
            <div class="banned-message" style="text-align: center; background-color: #f8d7da; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h2 style="color: #721c24;">You have been banned.</h2>
                <p><strong>Reason:</strong> <?php echo htmlspecialchars($ban_details['reason']); ?></p>
                <?php if ($ban_details['ban_until']): ?>
                    <p>Your ban will be lifted on <?php echo date("F j, Y, g:i a", strtotime($ban_details['ban_until'])); ?>.</p>
                <?php else: ?>
                    <p>This is a permanent ban.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <p>No posts yet. Be the first to create one!</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-container">
                    <div class="post-header">
                        <img src="<?php echo htmlspecialchars(!empty($post['user_icon']) ? $post['user_icon'] : 'img/default-user.png'); ?>" alt="Author Icon" class="user-avatar">
                        
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
                                    <form class="delete-post-form" action="forums.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this post?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" class="delete-post-button">Delete post</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>

                    <div class="post-content">
                        <?php
                            // This part displays the text and makes links clickable
                            $formattedMessage = makeLinksClickable($post['message']);
                            echo $formattedMessage;
                        ?>
                        <br>
                        <?php if (!empty($post['file_path'])): ?>
                            <img src="<?php echo htmlspecialchars($post['file_path']); ?>" alt="Post Image">
                        <?php endif; ?>
                    </div>

                    <div class="post-actions">
                        <button class="action-btn like-btn <?php echo $post['has_liked'] ? 'liked' : ''; ?>" data-post-id="<?php echo $post['id']; ?>">
                            ❤️ <span class="like-count"><?php echo $post['likes']; ?></span>
                        </button>
                        <button class="action-btn" onclick="toggleCommentForm(this)">💬 Comment</button>
                        <button class="report-btn" onclick="openReportModal(<?php echo $post['id']; ?>)">
                            <i class="fa fa-flag"></i> Report
                        </button>
                    </div>

                    <form class="join-convo-form" style="display:none;" action="forums.php" method="POST">
                        <input type="hidden" name="action" value="create_comment">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <input type="text" name="comment_message" placeholder="Join the conversation" required <?php if($isBanned) echo 'disabled'; ?>>
                        <button type="submit" <?php if($isBanned) echo 'disabled'; ?>>Post</button>
                    </form>

                    <div class="comment-section">
                        <?php foreach ($post['comments'] as $comment): ?>
                            <div class="comment">
                                <img src="<?php echo htmlspecialchars(!empty($comment['user_icon']) ? $comment['user_icon'] : 'img/default-user.png'); ?>" alt="Commenter Icon" class="user-avatar" style="width: 30px; height: 30px;">
                                <div class="comment-author-details">
                                    <div class="comment-bubble">
                                        <strong><?php echo htmlspecialchars($comment['display_name']); ?></strong>
                                        <?php echo htmlspecialchars($comment['message']); ?>
                                    </div>
                                    <div class="comment-timestamp">
                                        <?php echo date("F j, Y, g:i a", strtotime($comment['timestamp'])); ?>
                                        <button class="report-btn" onclick="openReportModal(<?php echo $comment['id']; ?>)">
                                            <i class="fa fa-flag"></i>
                                        </button>
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
            <form action="forums.php" method="POST" onsubmit="return confirm('Are you sure you want to report this content?');">
                <input type="hidden" name="action" value="report_post">
                <input type="hidden" id="report-post-id" name="post_id" value="">
                <p>Please provide a reason for reporting this content:</p>
                <textarea name="reason" rows="4" required></textarea>
                <button type="submit" class="post-btn">Submit Report</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="ban-modal-overlay" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h2>Ban User</h2>
                <button class="close-btn" onclick="closeBanModal()">&times;</button>
            </div>
            <form action="forums.php" method="POST">
                <input type="hidden" name="admin_action" value="ban_user">
                <input type="hidden" id="ban-username" name="username_to_ban" value="">
                <p>You are about to ban <strong id="ban-username-display"></strong>.</p>
                <label for="ban_reason">Reason for ban (optional):</label>
                <textarea id="ban_reason" name="ban_reason" class="ban-modal-reason" rows="3"></textarea>
                <button type="submit" class="post-btn" style="background-color: #d9534f;">Confirm Ban</button>
            </form>
        </div>
    </div>

<script src="mentee.js"></script>
<script>
    // --- NEW: MODAL FUNCTIONS (REPORT & BAN) ---
    function openReportModal(postId) {
        document.getElementById('report-post-id').value = postId;
        document.getElementById('report-modal-overlay').style.display = 'flex';
    }
    function closeReportModal() {
        document.getElementById('report-modal-overlay').style.display = 'none';
    }
    function openBanModal(username) {
        document.getElementById('ban-username').value = username;
        document.getElementById('ban-username-display').innerText = username;
        document.getElementById('ban-modal-overlay').style.display = 'flex';
    }
    function closeBanModal() {
        document.getElementById('ban-modal-overlay').style.display = 'none';
    }

    // --- ORIGINAL FUNCTIONS (LOGOUT & COMMENT) ---
    function confirmLogout() {
        if (confirm("Are you sure you want to log out?")) {
            window.location.href = "../login.php";
        }
    }
    function toggleCommentForm(btn) {
        const form = btn.closest('.post-container').querySelector('.join-convo-form');
        form.style.display = form.style.display === 'none' ? 'flex' : 'none';
    }

    // This runs after the entire page is loaded to prevent errors
    document.addEventListener("DOMContentLoaded", function () {
        // --- NEW: FILE NAME DISPLAY LOGIC ---
        const postImageInput = document.getElementById('post_image');
        const uploadText = document.getElementById('upload-text');
        let defaultUploadText = '';
        if (uploadText) {
            defaultUploadText = uploadText.innerHTML; // Save the original text
            postImageInput.addEventListener('change', function(event) {
                if (event.target.files.length > 0) {
                    const fileName = event.target.files[0].name;
                    uploadText.innerHTML = `<i class="fa fa-check-circle"></i> ${fileName}`;
                } else {
                    uploadText.innerHTML = defaultUploadText;
                }
            });
        }
        
        // --- MERGED "CREATE POST" MODAL LOGIC ---
        const createPostBtn = document.querySelector('.create-post-btn');
        const createPostModal = document.querySelector('#create-post-modal-overlay'); // Specific ID for this modal
        if (createPostBtn && createPostModal) {
            const closeBtn = createPostModal.querySelector('.close-btn');

            createPostBtn.addEventListener('click', () => {
                // Reset form fields
                const titleInput = createPostModal.querySelector('.title-input');
                const contentDiv = createPostModal.querySelector('.text-content');
                if (titleInput) titleInput.value = '';
                if (contentDiv) contentDiv.innerHTML = '';
                
                // MERGED: Reset file upload text
                if (uploadText) {
                    postImageInput.value = ''; 
                    uploadText.innerHTML = defaultUploadText; 
                }
                
                // Show modal
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

        // --- ORIGINAL TEXT FORMATTING ---
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

        // --- ORIGINAL FORM SUBMISSION FOR RICH TEXT ---
        const postForm = document.getElementById('post-form');
        const contentInput = document.getElementById('post-content-input');
        if (postForm && contentDiv && contentInput) {
            postForm.addEventListener('submit', function() {
                contentInput.value = contentDiv.innerHTML;
            });
        }

        // --- ORIGINAL PROFILE MENU ---
        const profileIcon = document.getElementById("profile-icon");
        const profileMenu = document.getElementById("profile-menu");
        if (profileIcon && profileMenu) {
            profileIcon.addEventListener("click", function (e) {
                e.preventDefault();
                profileMenu.classList.toggle("show");
                profileMenu.classList.remove("hide");
            });
            window.addEventListener("click", function (e) {
                if (!profileMenu.contains(e.target) && !profileIcon.contains(e.target)) {
                    profileMenu.classList.remove("show");
                    profileMenu.classList.add("hide");
                }
            });
        }

        // --- ORIGINAL LIKE/UNLIKE FUNCTIONALITY ---
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function() {
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
    });

        // --- NEW: POST OPTIONS MENU LOGIC ---
        document.querySelectorAll('.options-button').forEach(button => {
            button.addEventListener('click', function (event) {
                event.stopPropagation(); // Prevents the window click event from firing immediately
                const deleteForm = this.nextElementSibling;

                // Close all other open delete buttons first
                document.querySelectorAll('.delete-post-form').forEach(form => {
                    if (form !== deleteForm) {
                        form.classList.remove('show');
                    }
                });

                // Toggle the current delete button
                deleteForm.classList.toggle('show');
            });
        });

        // Close the delete button if clicking anywhere else on the page
        window.addEventListener('click', function (event) {
            document.querySelectorAll('.delete-post-form.show').forEach(form => {
                // Hide if the click is outside the form and its sibling kebab button
                if (!form.contains(event.target) && !form.previousElementSibling.contains(event.target)) {
                    form.classList.remove('show');
                }
            });
        });
</script>
</body>
</html>