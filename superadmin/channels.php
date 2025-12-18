<?php
session_start();

// Standard session check for an Super Admin user
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Super Admin') {
    header("Location: ../login.php");
    exit();
}

// Use your standard database connection
require '../connection/db_connection.php';

// Fetch user details from the unified 'users' table
$currentUser = $_SESSION['username'];
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, icon, user_type FROM users WHERE username = ?");
$stmt->bind_param("s", $currentUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    // Check if the user is an Admin or Super Admin
    if (!in_array($user['user_type'], ['Admin', 'Super Admin'])) {
        header("Location: ../login.php"); // Redirect non-admins
        exit();
    }
    // Set session variables for the view
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['admin_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['admin_icon'] = $user['icon'] ?: '../uploads/img/default-admin.png';
} else {
    // If user from session is not in DB, log out
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$stmt->close();


// --- TABLE CREATION (for setup and reference) ---
// Note: These should align with your 'coach(updated).sql' schema.
$conn->query("
CREATE TABLE IF NOT EXISTS chat_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_general TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

// --- ACTION HANDLERS ---

// Handle forum deletion
if (isset($_GET['delete_forum'])) {
    $forumId = intval($_GET['delete_forum']);
    
    // Using transaction for data integrity
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM forum_participants WHERE forum_id = $forumId");
        $conn->query("DELETE FROM chat_messages WHERE chat_type = 'forum' AND forum_id = $forumId");
        $conn->query("DELETE FROM forum_chats WHERE id = $forumId");
        $conn->commit();
        $success = "Forum deleted successfully!";
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $error = "Error deleting forum: " . $exception->getMessage();
    }
}

// Create default general channel if it doesn't exist
$result = $conn->query("SELECT id FROM chat_channels WHERE is_general = 1");
if ($result->num_rows === 0) {
    $conn->query("INSERT INTO chat_channels (name, description, is_general) VALUES ('general', 'General discussion channel', 1)");
}

// Handle channel creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_channel') {
    $channelName = trim($_POST['channel_name']);
    $channelDescription = trim($_POST['channel_description']);
    
    if (!empty($channelName)) {
        $stmt = $conn->prepare("INSERT INTO chat_channels (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $channelName, $channelDescription);
        if ($stmt->execute()) {
             $success = "Channel created successfully!";
        } else {
            $error = "Failed to create channel.";
        }
    }
}

// Handle channel update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_channel') {
    $channelId = intval($_POST['channel_id']);
    $channelName = trim($_POST['channel_name']);
    $channelDescription = trim($_POST['channel_description']);
    
    if (!empty($channelName)) {
        $stmt = $conn->prepare("UPDATE chat_channels SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $channelName, $channelDescription, $channelId);
        if ($stmt->execute()) {
            $success = "Channel updated successfully!";
        } else {
            $error = "Failed to update channel.";
        }
    }
}

// Handle channel deletion
if (isset($_GET['delete_channel'])) {
    $channelId = intval($_GET['delete_channel']);
    
    $stmt = $conn->prepare("SELECT is_general FROM chat_channels WHERE id = ?");
    $stmt->bind_param("i", $channelId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['is_general'] == 0) {
            $stmt_delete = $conn->prepare("DELETE FROM chat_channels WHERE id = ?");
            $stmt_delete->bind_param("i", $channelId);
            if ($stmt_delete->execute()) {
                $success = "Channel deleted successfully!";
            } else {
                $error = "Failed to delete channel.";
            }
        } else {
            $error = "Cannot delete the general channel!";
        }
    }
}

// --- DATA FETCHING FOR DISPLAY ---

// Get all public channels
$channels = [];
$result = $conn->query("SELECT * FROM chat_channels ORDER BY is_general DESC, name ASC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $channels[] = $row;
    }
}

// Get all private forum channels
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

// Get message counts for each public channel
$channelMessageCounts = [];
foreach ($channels as $channel) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE chat_type = 'group' AND forum_id = ?");
    $stmt->bind_param("i", $channel['id']);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $channelMessageCounts[$channel['id']] = $countRow['count'];
}

// Get message counts and participants for each private forum
$forumMessageCounts = [];
$forumParticipants = [];
foreach ($forums as $forum) {
    $forumId = $forum['id'];
    
    // Message count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE chat_type = 'forum' AND forum_id = ?");
    $stmt->bind_param("i", $forumId);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $forumMessageCounts[$forumId] = $countResult->fetch_assoc()['count'];

    // Participants list (Updated Query)
    $stmt_participants = $conn->prepare("
        SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as display_name, u.user_type
        FROM forum_participants fp
        JOIN users u ON fp.user_id = u.user_id
        WHERE fp.forum_id = ?
    ");
    $stmt_participants->bind_param("i", $forumId);
    $stmt_participants->execute();
    $participantsResult = $stmt_participants->get_result();
    $participants = [];
    if ($participantsResult->num_rows > 0) {
        while ($p_row = $participantsResult->fetch_assoc()) {
            $participants[] = $p_row;
        }
    }
    $forumParticipants[$forumId] = $participants;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/dashboard.css"/>
    <link rel="stylesheet" href="css/channels.css"/>
    <link rel="stylesheet" href="css/navigation.css"/>
     <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <title>Channels | SuperAdmin</title>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
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
      <a href="edit-profile.php?username=<?= urlencode($_SESSION['username']) ?>" class="edit-profile-link" title="Edit Profile">
        <ion-icon name="create-outline" class="verified-icon"></ion-icon>
      </a>
    </div>
  </div>

  <div class="menu-items">
        <ul class="navLinks">
            <li class="navList"><a href="dashboard.php"><ion-icon name="home-outline"></ion-icon><span class="links">Home</span></a></li>
            <li class="navList"><a href="moderators.php"><ion-icon name="lock-closed-outline"></ion-icon><span class="links">Moderators</span></a></li>
            <li class="navList"><a href="manage_mentees.php"><ion-icon name="person-outline"></ion-icon><span class="links">Mentees</span></a></li>
            <li class="navList"><a href="manage_mentors.php"><ion-icon name="people-outline"></ion-icon><span class="links">Mentors</span></a></li>
             <li class="navList"><a href="courses.php"><ion-icon name="book-outline"></ion-icon><span class="links">Courses</span></a></li>
            <li class="navList"><a href="manage_session.php"><ion-icon name="calendar-outline"></ion-icon><span class="links">Sessions</span></a></li>
            <li class="navList"><a href="feedbacks.php"><ion-icon name="star-outline"></ion-icon><span class="links">Feedback</span></a></li>
            <li class="navList active"><a href="channels.php"><ion-icon name="chatbubbles-outline"></ion-icon><span class="links">Channels</span></a></li>
            <li class="navList"><a href="activities.php"><ion-icon name="clipboard-outline"></ion-icon><span class="links">Activities</span></a></li>
            <li class="navList"><a href="resource.php"><ion-icon name="library-outline"></ion-icon><span class="links">Resource Library</span></a></li>
            <li class="navList"><a href="reports.php"><ion-icon name="folder-outline"></ion-icon><span class="links">Reported Posts</span></a></li>
        <li class="navList"><a href="banned-users.php"><ion-icon name="person-remove-outline"></ion-icon><span class="links">Banned Users</span></a></li>
        </ul>
        <ul class="bottom-link">
            <li class="logout-link"><a href="#" onclick="confirmLogout(event)"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
        </ul>
  </div>
</nav>

    <section class="dashboard">
        <div class="top">
            <ion-icon class="navToggle" name="menu-outline"></ion-icon>
            <img src="../uploads/img/logo.png" alt="Logo">
        </div>

        <div class="container">
            <h1 class="section-title">Manage Channels</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert success"><ion-icon name="checkmark-circle-outline"></ion-icon><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert error"><ion-icon name="alert-circle-outline"></ion-icon><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <div class="tab active" data-tab="channels">Public Channel</div>
                <div class="tab" data-tab="forums">Private Session Channels</div>
            </div>
            
            <div class="tab-content active" id="channels-content">

                <div class="card-grid">
                    <?php foreach ($channels as $channel): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">General Chat</h3>
                            </div>
                            <div class="card-content">
                                <div class="card-detail"><ion-icon name="star-outline"></ion-icon><span>General Channel</span></div>
                                <div class="card-detail"><ion-icon name="information-circle-outline"></ion-icon><span>Visit and post updates</span></div>
                                
                            </div>
                            <div class="card-footer">
                                <div class="card-stats"></div>
                                <a href="forums.php?channel=<?php echo $channel['id']; ?>" class="card-button"><ion-icon name="enter-outline"></ion-icon>Join General Forum</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="tab-content" id="forums-content">
                <div class="card-grid">
                    <?php if (empty($forums)): ?>
                        <p>No private session channels found. They are created when you approve a session.</p>
                    <?php else: ?>
                        <?php foreach ($forums as $forum): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title"><?php echo htmlspecialchars($forum['title']); ?></h3>
                                    <div class="card-actions">
                                        <a href="?delete_forum=<?php echo $forum['id']; ?>" onclick="return confirm('Are you sure you want to delete this forum? All associated messages will be lost.')" title="Delete Forum"><ion-icon name="trash-outline"></ion-icon></a>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <div class="card-detail"><ion-icon name="book-outline"></ion-icon><span><?php echo htmlspecialchars($forum['course_title']); ?></span></div>
                                    <div class="card-detail"><ion-icon name="calendar-outline"></ion-icon><span><?php echo date('F j, Y', strtotime($forum['session_date'])); ?></span></div>
                                    <div class="card-detail"><ion-icon name="time-outline"></ion-icon><span><?php echo htmlspecialchars($forum['time_slot']); ?></span></div>
                                    <div class="card-detail"><ion-icon name="people-outline"></ion-icon><span>Participants: <?php echo $forum['current_users']; ?>/<?php echo $forum['max_users']; ?></span></div>
                                    
                                    <?php if (!empty($forumParticipants[$forum['id']])): ?>
                                        <div class="participants-list">
                                            <h4>Participants:</h4>
                                            <?php foreach ($forumParticipants[$forum['id']] as $participant): ?>
                                                <div class="participant">
                                                    <span class="participant-name"><?php echo htmlspecialchars($participant['display_name']); ?></span>
                                                    <span class="participant-badge <?php echo strtolower($participant['user_type']); ?>"><?php echo htmlspecialchars($participant['user_type']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="card-stats"><div class="stat"><ion-icon name="chatbubble-outline"></ion-icon><span><?php echo $forumMessageCounts[$forum['id']] ?? 0; ?> messages</span></div></div>
                                    <a href="forum-chat.php?view=forum&forum_id=<?php echo $forum['id']; ?>" class="card-button"><ion-icon name="enter-outline"></ion-icon>Join Forum</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Modals (Create/Edit Channel) -->
    <div class="modal-overlay" id="createChannelModal"><div class="modal-content"><div class="modal-header"><h3>Create New Channel</h3><button class="modal-close" onclick="closeCreateChannelModal()">&times;</button></div><form class="modal-form" method="POST" action=""><input type="hidden" name="action" value="create_channel"><div class="form-group"><label for="channel_name">Channel Name</label><input type="text" id="channel_name" name="channel_name" required></div><div class="form-group"><label for="channel_description">Channel Description</label><textarea id="channel_description" name="channel_description" rows="3"></textarea></div><div class="modal-actions"><button type="button" class="cancel-btn" onclick="closeCreateChannelModal()">Cancel</button><button type="submit" class="submit-btn">Create Channel</button></div></form></div></div>
    <div class="modal-overlay" id="editChannelModal"><div class="modal-content"><div class="modal-header"><h3>Edit Channel</h3><button class="modal-close" onclick="closeEditChannelModal()">&times;</button></div><form class="modal-form" method="POST" action=""><input type="hidden" name="action" value="update_channel"><input type="hidden" id="edit_channel_id" name="channel_id"><div class="form-group"><label for="edit_channel_name">Channel Name</label><input type="text" id="edit_channel_name" name="channel_name" required></div><div class="form-group"><label for="edit_channel_description">Channel Description</label><textarea id="edit_channel_description" name="channel_description" rows="3"></textarea></div><div class="modal-actions"><button type="button" class="cancel-btn" onclick="closeEditChannelModal()">Cancel</button><button type="submit" class="submit-btn">Update Channel</button></div></form></div></div>
    
    <script src="js/navigation.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    tab.classList.add('active');
                    document.getElementById(`${tabId}-content`).classList.add('active');
                });
            });
        });
        
        function openCreateChannelModal() { document.getElementById('createChannelModal').classList.add('active'); }
        function closeCreateChannelModal() { document.getElementById('createChannelModal').classList.remove('active'); }
        function openEditChannelModal(id, name, description) {
            document.getElementById('edit_channel_id').value = id;
            document.getElementById('edit_channel_name').value = name;
            document.getElementById('edit_channel_description').value = description;
            document.getElementById('editChannelModal').classList.add('active');
        }
        function closeEditChannelModal() { document.getElementById('editChannelModal').classList.remove('active'); }
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
