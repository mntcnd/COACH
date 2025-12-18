<?php
session_start();

/* --------------------------- DB CONNECTION --------------------------- */
require 'connection/db_connection.php';

/* --------------------------- SESSION CHECK & USER FETCHING --------------------------- */
if (!isset($_SESSION['username']) && !isset($_SESSION['admin_username']) && !isset($_SESSION['applicant_username'])) {
    header("Location: login.php");
    exit();
}

$currentUserUsername = '';
if (isset($_SESSION['admin_username'])) {
    $currentUserUsername = $_SESSION['admin_username'];
} elseif (isset($_SESSION['applicant_username'])) {
    $currentUserUsername = $_SESSION['applicant_username'];
} elseif (isset($_SESSION['username'])) {
    $currentUserUsername = $_SESSION['username'];
}

$stmt = $conn->prepare("SELECT user_id, user_type, first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $currentUserUsername);
$stmt->execute();
$userResult = $stmt->get_result();
if ($userResult->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$userData = $userResult->fetch_assoc();
$displayName = trim($userData['first_name'] . ' ' . $userData['last_name']);
$profilePicture = !empty($userData['icon']) ? str_replace('../', '', $userData['icon']) : 'uploads/img/default_pfp.png';
$userType = $userData['user_type'];
$isAdmin = in_array($userType, ['Admin', 'Super Admin']);
$isMentor = ($userType === 'Mentor');

/* --------------------------- FORUM FETCHING & ACCESS CHECKS --------------------------- */
if (!isset($_GET['forum_id'])) {
    header("Location: login.php"); 
    exit();
}
$forumId = intval($_GET['forum_id']);
$stmt = $conn->prepare("SELECT * FROM forum_chats WHERE id = ?");
$stmt->bind_param("i", $forumId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header("Location: login.php");
    exit();
}
$forumDetails = $res->fetch_assoc();

/* --------------------------- JWT DISABLED --------------------------- */
$jwtToken = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Video Call - COACH</title>
<link rel="icon" href="uploads/img/coachicon.svg" type="image/svg+xml"
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/video-call.css" />
</head>

<body>
  <nav id="top-bar">
    <div class="left">
      <img src="uploads/img/LogoCoach.png" alt="Logo" style="width:36px;height:36px;object-fit:contain;">
      <div>
        <div class="meeting-title"><?php echo htmlspecialchars($forumDetails['title'] ?? 'Video Meeting'); ?></div>
        <div style="font-size:12px;color:var(--muted)">
          <?php date_default_timezone_set('Asia/Manila'); echo htmlspecialchars($forumDetails['session_date'] ?? ''); ?> · 
          <?php echo htmlspecialchars($forumDetails['time_slot'] ?? ''); ?>
        </div>
      </div>
    </div>
    <div class="right">
      <div style="display:flex;align-items:center;gap:16px;">
        <div style="font-size:13px;color:var(--muted);"><?php echo date('g:i A'); ?></div>
        <img class="profile" src="<?php echo htmlspecialchars($profilePicture); ?>" alt="User">
      </div>
    </div>
  </nav>

  <div class="app-shell">
    <div id="video-area" role="main">
        <div id="jitsi-container" style="width: 100%; height: 100%;"></div>
    </div>
  </div>

<script src="https://meet.coach-hub.online/external_api.js"></script>

<script>
    const displayName = <?php echo json_encode($displayName); ?>;
    const forumId = <?php echo json_encode($forumId); ?>;
    const forumTitle = <?php echo json_encode($forumDetails['title'] ?? 'Video Meeting'); ?>;
    const isAdmin = <?php echo json_encode($isAdmin); ?>;
    const isMentor = <?php echo json_encode($isMentor); ?>;

    let api;

    document.addEventListener('DOMContentLoaded', () => {
        const roomName = `CoachHubOnlineForumSession${forumId}`;
        const domain = "meet.coach-hub.online";  // ✅ point to your own server

        const options = {
            roomName: roomName,
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#jitsi-container'),
            userInfo: { displayName: displayName },
            configOverwrite: {
                prejoinPageEnabled: false,
                startWithAudioMuted: false,
                startWithVideoMuted: false,
                subject: forumTitle,
                enableWelcomePage: false,
                disableModeratorIndicator: true,
                enableLobby: false,
                requireDisplayName: false,
                startWithModeratorMuted: false,
                toolbarButtons: [
                    'microphone', 'camera', 'chat', 'desktop', 'raisehand', 'hangup'
                ]
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                // Hide the "End meeting for all" option, keep only "Leave meeting"
                HIDE_DEEP_LINKING_CONFIG: false,
                CLOSE_PAGE_GUEST: false
            }
        };

        api = new JitsiMeetExternalAPI(domain, options);

        api.addEventListener('videoConferenceJoined', (event) => {
            console.log('Joined conference with role:', event.role);
        });

        // Handle participant count changes
        api.addEventListener('participantJoined', () => {
            console.log('Participant joined');
        });

        api.addEventListener('participantLeft', () => {
            console.log('Participant left');
            checkAndRedirectIfEmpty();
        });

        // Handle leaving the conference
        api.addEventListener('videoConferenceLeft', () => {
            console.log('User left the conference');
            redirectToForum();
            // Dispose of the Jitsi API instance to prevent default behavior
            if (api) {
                api.dispose();
            }
        });

        // Initial check after joining
        setTimeout(checkAndRedirectIfEmpty, 2000);

        function checkAndRedirectIfEmpty() {
            api.getNumberOfParticipants().then((numParticipants) => {
                console.log('Current participants:', numParticipants);
                if (numParticipants <= 1) { // Only the current user or no users
                    redirectToForum();
                }
            }).catch((err) => {
                console.error('Error getting participant count:', err);
            });
        }

        function redirectToForum() {
            const redirectUrl = isAdmin 
                ? 'admin/forum-chat.php' 
                : (isMentor ? 'mentor/forum-chat.php' : 'mentee/forum-chat.php');
            window.location.href = `${redirectUrl}?view=forum&forum_id=${forumId}`;
        }

        // Override the hangup command to ensure it only leaves (not ends for all)
        const originalExecuteCommand = api.executeCommand;
        api.executeCommand = function(command, ...args) {
            if (command === 'hangup') {
                // Trigger leave and redirect
                originalExecuteCommand.call(this, 'hangup');
                redirectToForum();
            } else {
                return originalExecuteCommand.call(this, command, ...args);
            }
        };
    });

</script>
</body>
</html>
