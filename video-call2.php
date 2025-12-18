<?php
session_start();

/* --------------------------- DB CONNECTION --------------------------- */
require 'connection/db_connection.php';

/* --------------------------- SESSION CHECK --------------------------- */
// This logic is preserved to identify which user type is logged in,
// assuming the session variable names have not been unified yet.
if (!isset($_SESSION['username']) && !isset($_SESSION['admin_username']) && !isset($_SESSION['applicant_username'])) {
    header("Location: login.php");
    exit();
}

/* --------------------------- UNIFIED USER FETCHING (NEW) --------------------------- */
// Determine the current user's username from the session variables.
$currentUserUsername = '';
if (isset($_SESSION['admin_username'])) {
    $currentUserUsername = $_SESSION['admin_username'];
} elseif (isset($_SESSION['applicant_username'])) {
    $currentUserUsername = $_SESSION['applicant_username'];
} elseif (isset($_SESSION['username'])) {
    $currentUserUsername = $_SESSION['username'];
}

// Fetch all user details from the single 'users' table using the username.
$stmt = $conn->prepare("SELECT user_id, user_type, first_name, last_name, icon FROM users WHERE username = ?");
$stmt->bind_param("s", $currentUserUsername);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows === 0) {
    // If the user in the session doesn't exist in the database, log them out.
    session_destroy();
    header("Location: login.php");
    exit();
}

$userData = $userResult->fetch_assoc();
$currentUserId = $userData['user_id'];
$userType = $userData['user_type'];
$displayName = trim($userData['first_name'] . ' ' . $userData['last_name']);
$originalIcon = $userData['icon'];
$newIcon = str_replace('../', '', $originalIcon);
$profilePicture = !empty($newIcon) ? $newIcon : 'uploads/img/default_pfp.png';

// Set boolean flags for user type to maintain compatibility with existing logic.
$isAdmin = in_array($userType, ['Admin', 'Super Admin']);
$isMentor = ($userType === 'Mentor');

/* --------------------------- REQUIRE FORUM --------------------------- */
if (!isset($_GET['forum_id'])) {
    // Preserving original redirect logic
    $redirect_url = "mentee/forum-chat.php?view=forums"; // Default for Mentee
    if ($isMentor) {
        $redirect_url = "mentor/forum-chat.php?view=forums";
    } elseif ($isAdmin) {
        $redirect_url = "admin/forum-chat.php?view=forums";
    }
    header("Location: " . $redirect_url);
    exit();
}
$forumId = intval($_GET['forum_id']);

/* --------------------------- ACCESS CHECK (UPDATED) --------------------------- */
// Mentees can only access forums they have joined. Admins and Mentors have full access.
if (!$isAdmin && !$isMentor) {
    // The query now uses the integer `user_id`.
    $stmt = $conn->prepare("SELECT id FROM forum_participants WHERE forum_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $forumId, $currentUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        header("Location: forum-chat.php?view=forums");
        exit();
    }
}

/* --------------------------- FETCH FORUM --------------------------- */
$stmt = $conn->prepare("SELECT * FROM forum_chats WHERE id = ?");
$stmt->bind_param("i", $forumId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $redirect_url = "mentee/forum-chat.php?view=forums";
    if ($isMentor) {
        $redirect_url = "mentor/forum-chat.php?view=forums";
    } elseif ($isAdmin) {
        $redirect_url = "admin/forum-chat.php?view=forums";
    }
    header("Location: " . $redirect_url);
    exit();
}
$forumDetails = $res->fetch_assoc();

/* --------------------------- SESSION STATUS (UPDATED) --------------------------- */
$today = date('Y-m-d');
$currentTime = date('H:i');
list($startTime, $endTimeStr) = explode(' - ', $forumDetails['time_slot']);
$endTime = date('H:i', strtotime($endTimeStr));
$isSessionOver = ($today > $forumDetails['session_date']) ||
    ($today == $forumDetails['session_date'] && $currentTime > $endTime);

// Check if the user has already left the session, now using `user_id`.
$hasLeftSession = false;
$checkLeft = $conn->prepare("SELECT status FROM session_participants WHERE forum_id = ? AND user_id = ?");
$checkLeft->bind_param("ii", $forumId, $currentUserId);
$checkLeft->execute();
$leftResult = $checkLeft->get_result();
if ($leftResult->num_rows > 0) {
    $participantStatus = $leftResult->fetch_assoc()['status'];
    $hasLeftSession = in_array($participantStatus, ['left', 'review']);
}

if ($isSessionOver || $hasLeftSession) {
    $redirect_url = "mentee/forum-chat.php";
    if ($isMentor) {
        $redirect_url = "mentor/forum-chat.php";
    } elseif ($isAdmin) {
        $redirect_url = "admin/forum-chat.php";
    }
    header("Location: " . $redirect_url . "?view=forum&forum_id=" . $forumId . "&review=true");
    exit();
}

/* --------------------------- HANDLE CHAT POST (UPDATED) --------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'video_chat') {
    $message = trim($_POST['message'] ?? '');
    if ($message !== '') {
        // The `chat_messages` table now uses `user_id`.
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, display_name, message, is_admin, is_mentor, chat_type, forum_id) VALUES (?, ?, ?, ?, ?, 'forum', ?)");
        $isAdminBit = $isAdmin ? 1 : 0;
        $isMentorBit = $isMentor ? 1 : 0;
        $stmt->bind_param("issiii", $currentUserId, $displayName, $message, $isAdminBit, $isMentorBit, $forumId);
        $stmt->execute();
    }
    exit(); // Required for AJAX request
}

/* --------------------------- PARTICIPANTS (UPDATED & SIMPLIFIED) --------------------------- */
// This query is now simpler, joining only with the `users` table.
$participants = [];
$stmt = $conn->prepare("
    SELECT u.username,
           CONCAT(u.first_name, ' ', u.last_name) as display_name,
           COALESCE(u.icon, 'uploads/img/default_pfp.png') as profile_picture,
           LOWER(u.user_type) as user_type
    FROM forum_participants fp
    JOIN users u ON fp.user_id = u.user_id
    WHERE fp.forum_id = ?
");
$stmt->bind_param("i", $forumId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    // Clean the path before adding the participant to the array
    $row['profile_picture'] = str_replace('../', '', $row['profile_picture']);

    $participants[] = $row;
}
/* --------------------------- MESSAGES --------------------------- */
// This query remains the same as it doesn't depend on the user table structure.
$messages = [];
$stmt = $conn->prepare("SELECT * FROM chat_messages WHERE chat_type = 'forum' AND forum_id = ? ORDER BY timestamp ASC LIMIT 200");
$stmt->bind_param("i", $forumId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $messages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Video Call - COACH</title>
<link rel="icon" href="uploads/img/coachicon.svg" type="image/svg+xml">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/video-call.css" />
<style>
#ws-status {
  position: fixed;
  top: 12px;
  left: 50%;
  transform: translateX(-50%);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  color: white;
  z-index: 1000;
  transition: all 0.3s ease;
}
.status-connected { background-color: #28a745; }
.status-disconnected { background-color: #dc3545; }
.status-connecting { background-color: #ffc107; color: #333; }

/* -------------------- NEW CSS FOR TILE FULLSCREEN BUTTON -------------------- */
.tile-fullscreen-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 15;
    opacity: 0; /* Hide by default */
    pointer-events: none; /* Make it unclickable when invisible */
    transition: opacity 0.2s ease-in-out;
    width: 36px; /* Smaller size for tile overlay */
    height: 36px;
    font-size: 18px;
}

.video-container:hover .tile-fullscreen-btn {
    opacity: 1; /* Show on hover */
    pointer-events: auto; /* Make it clickable on hover */
}
/* -------------------- END OF NEW CSS -------------------- */

@media (max-width: 768px) {
  #ws-status {
    top: calc(16px + var(--safe-area-top));
    left: 50%;
    right: calc(16px + var(--safe-area-right));
    transform: none;
    width: 12px;
    height: 12px;
    padding: 0;
    border-radius: 50%;
    font-size: 0;
    overflow: hidden;
  }
}
</style>
</head>
<body>
  <nav id="top-bar">
    <div class="left">
      <img src="uploads/img/LogoCoach.png" alt="Logo" style="width:36px;height:36px;object-fit:contain;">
      <div>
        <div class="meeting-title"><?php echo htmlspecialchars($forumDetails['title'] ?? 'Video Meeting'); ?></div>
        <div style="font-size:12px;color:var(--muted)"><?php echo htmlspecialchars($forumDetails['session_date'] ?? ''); ?> &middot; <?php echo htmlspecialchars($forumDetails['time_slot'] ?? ''); ?></div>
      </div>
    </div>
    <div class="right">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="font-size:13px;color:var(--muted)"><?php date_default_timezone_set('Asia/Manila'); echo date('g:i A'); ?></div>
        <img class="profile" src="<?php echo htmlspecialchars($profilePicture); ?>" alt="User">
      </div>
    </div>
  </nav>

  <div id="ws-status" class="status-connecting">Connecting...</div>

  <div class="app-shell">
    <div id="video-area" role="main">
      <div id="video-grid" aria-live="polite"></div>

      <div id="controls-bar" aria-hidden="false">
        <button id="toggle-audio" class="control-btn" title="Mute / Unmute"><ion-icon name="mic-outline"></ion-icon></button>
        <button id="toggle-video" class="control-btn" title="Camera On / Off"><ion-icon name="videocam-outline"></ion-icon></button>
        <button id="toggle-screen" class="control-btn" title="Share Screen"><ion-icon name="desktop-outline"></ion-icon></button>
        <button id="toggle-chat" class="control-btn" title="Chat"><ion-icon name="chatbubbles-outline"></ion-icon></button>
        <button id="end-call" class="control-btn end-call" title="Leave call"><ion-icon name="call-outline"></ion-icon></button>
      </div>
    </div>

    <aside id="chat-sidebar" class="hidden">
      <div id="chat-header">
        <span class="chat-title">In-call messages</span>
        <button id="close-chat-btn" title="Close chat"><ion-icon name="close-outline"></ion-icon></button>
      </div>
      <div id="chat-messages">
        <?php foreach ($messages as $msg): ?>
          <div class="message">
            <div class="sender"><?php echo htmlspecialchars($msg['display_name']); ?></div>
            <div class="content"><?php echo htmlspecialchars($msg['message']); ?></div>
            <div class="timestamp"><?php echo date('M d, g:i a', strtotime($msg['timestamp'])); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="chat-input">
        <input type="text" id="chat-message" placeholder="Send a message..." />
        <button id="send-chat-btn"><ion-icon name="send-outline"></ion-icon></button>
      </div>
    </aside>
  </div>

<script>
/* -------------------- SERVER-SIDE DATA -------------------- */
const currentUser = <?php echo json_encode($currentUserUsername); ?>;
const displayName = <?php echo json_encode($displayName); ?>;
const profilePicture = <?php echo json_encode($profilePicture); ?>;
const forumId = <?php echo json_encode($forumId); ?>;
let participants = <?php echo json_encode($participants); ?>;

/* -------------------- MEDIA / RTC STATE -------------------- */
let localStream = null;
let screenStream = null;
let peerConnections = {};
let isVideoOn = true;
let isAudioOn = true;
let isScreenSharing = false;

const configuration = {
   iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        {
            urls: 'turn:174.138.18.220:3478',
            username: 'coachuser',
            credential: 'coach2025Hub!'
        }
    ]
};

/* -------------------- SIGNALING (WebSocket) -------------------- */
let socket = null;
const statusIndicator = document.getElementById('ws-status');

function initWebSocket() {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsHost = window.location.hostname;
    const wsUrl = `wss://${window.location.host}/ws`; // Replace with your WebSocket server URL

    console.log('Attempting to connect to WebSocket:', wsUrl);
    statusIndicator.textContent = 'Connecting...';
    statusIndicator.className = 'status-connecting';

    socket = new WebSocket(wsUrl);

    socket.onopen = () => {
        console.log('WebSocket connection established.');
        statusIndicator.textContent = 'Connected';
        statusIndicator.className = 'status-connected';

        // Announce our arrival to the server
        sendSignal({
            type: 'join',
            forumId,
            username: currentUser,
            displayName,
            profilePicture
        });
    };

    socket.onmessage = handleSignalingData;

    socket.onclose = () => {
        console.warn('WebSocket closed. Attempting to reconnect in 3 seconds...');
        statusIndicator.textContent = 'Disconnected';
        statusIndicator.className = 'status-disconnected';
        setTimeout(initWebSocket, 3000);
    };

    socket.onerror = (err) => {
        console.error('WebSocket error:', err);
        statusIndicator.textContent = 'Error';
        statusIndicator.className = 'status-disconnected';
    };
}

function sendSignal(payload) {
    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify(payload));
    } else {
        console.warn('WebSocket is not open. Could not send message:', payload);
    }
}

/* -------------------- SIGNALING HANDLER -------------------- */
async function handleSignalingData(ev) {
    let data;
    try {
        data = JSON.parse(ev.data);
    } catch (e) {
        console.error('Error parsing signaling message:', ev.data);
        return;
    }

    // Ensure message is for this forum
    if (String(data.forumId) !== String(forumId)) return;

    console.log('Received signal:', data);

    // Ignore messages broadcasted by ourself
    if (data.from === currentUser) return;

    switch (data.type) {
        case 'existing-users':
            console.log('Found existing users:', data.users);
            for (const user of data.users) {
                if (user.username !== currentUser) {
                    if (!participants.find(p => p.username === user.username)) {
                        participants.push({
                            username: user.username,
                            display_name: user.displayName,
                            profile_picture: user.profilePicture || 'uploads/img/default_pfp.png',
                        });
                    }
                    addVideoStream(user.username, null);
                    // The NEW user proactively creates a peer connection and sends an offer
                    const pc = ensurePeerConnection(user.username);
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    sendSignal({
                        type: 'offer',
                        offer: pc.localDescription,
                        to: user.username,
                        from: currentUser,
                        forumId
                    });
                }
            }
            break;

        case 'join':
            console.log(`User '${data.from}' has joined the call.`);
            
            // Add new user to the participants list if they aren't already there
            if (!participants.find(p => p.username === data.from)) {
                participants.push({
                    username: data.from,
                    display_name: data.displayName,
                    profile_picture: data.profilePicture || 'uploads/img/default_pfp.png',
                });
            }
            
            // Add a video tile placeholder for the new user
            addVideoStream(data.from, null);
        
            // The EXISTING users now proactively create a peer connection and send an offer to the NEW user
            console.log(`This client (${currentUser}) is creating a peer connection for new user '${data.from}'`);
            const pc = ensurePeerConnection(data.from); 
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            sendSignal({
                type: 'offer',
                offer: pc.localDescription,
                to: data.from,
                from: currentUser,
                forumId
            });
            
            break;

        case 'offer':
            try {
                const pc = ensurePeerConnection(data.from);
                await pc.setRemoteDescription(new RTCSessionDescription(data.offer));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                sendSignal({
                    type: 'answer',
                    answer: pc.localDescription,
                    to: data.from,
                    from: currentUser,
                    forumId
                });
            } catch (e) {
                console.error('Error handling offer from', data.from, e);
            }
            break;

        case 'answer':
            try {
                const pc = ensurePeerConnection(data.from);
                await pc.setRemoteDescription(new RTCSessionDescription(data.answer));
            } catch (e) {
                console.error('Error handling answer from', data.from, e);
            }
            break;

        case 'ice-candidate':
            try {
                const pc = ensurePeerConnection(data.from);
                await pc.addIceCandidate(new RTCIceCandidate(data.candidate));
            } catch (e) {
                console.warn('Error adding ICE candidate:', e);
            }
            break;

        case 'toggle-video':
        case 'toggle-audio':
            updateParticipantStatus(data.from, data.type, data.enabled);
            break;
            
        case 'speaker-changed': // NEW: Handle active speaker event from server
            updateActiveSpeaker(data.username);
            break;

        case 'leave':
            console.log(`User '${data.from}' has left the call.`);
            removeVideoStream(data.from);
            if (peerConnections[data.from]) {
                peerConnections[data.from].close();
                delete peerConnections[data.from];
            }
            participants = participants.filter(p => p.username !== data.from);
            break;
    }
}

/* -------------------- MEDIA ACCESS -------------------- */
async function getMedia() {
    console.log('Requesting user media (camera and microphone)...');
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        console.log('Media stream acquired successfully.');
        addVideoStream(currentUser, localStream, true); // Add our own video

        localStream.getVideoTracks().forEach(t => t.enabled = isVideoOn);
        localStream.getAudioTracks().forEach(t => t.enabled = isAudioOn);

        updateParticipantStatus(currentUser, 'toggle-video', isVideoOn);
        updateParticipantStatus(currentUser, 'toggle-audio', isAudioOn);

        Object.values(peerConnections).forEach(pc => {
            localStream.getTracks().forEach(track => pc.addTrack(track, localStream));
        });

    } catch (err) {
        console.error('Error accessing media devices:', err.name, err.message);
        alert(`Could not access camera/microphone: ${err.message}. You can still watch and listen.`);
        addVideoStream(currentUser, null, true);
    }
}

/* -------------------- PEER CONNECTION MANAGEMENT -------------------- */
function ensurePeerConnection(username) {
    if (peerConnections[username]) {
        return peerConnections[username];
    }
    console.log(`Creating new peer connection for '${username}'`);
    const pc = new RTCPeerConnection(configuration);
    peerConnections[username] = pc;

    if (localStream) {
        localStream.getTracks().forEach(track => {
            pc.addTrack(track, localStream);
        });
    }

    pc.ontrack = (event) => {
        console.log(`Received remote track from '${username}'`);
        addVideoStream(username, event.streams[0]);
    };

    pc.onicecandidate = (event) => {
        if (event.candidate) {
            sendSignal({
                type: 'ice-candidate',
                candidate: event.candidate,
                to: username,
                from: currentUser,
                forumId
            });
        }
    };

    pc.onnegotiationneeded = async () => {
        try {
            console.log(`Negotiation needed for '${username}'. Creating offer...`);
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            sendSignal({
                type: 'offer',
                offer: pc.localDescription,
                to: username,
                from: currentUser,
                forumId
            });
        } catch (e) {
            console.error(`Error during negotiation for '${username}':`, e);
        }
    };

    pc.onconnectionstatechange = () => {
        console.log(`Connection state with ${username}: ${pc.connectionState}`);
    };

    return pc;
}

/* -------------------- UI: VIDEO TILES & LAYOUT -------------------- */
// NEW: Auto Layout Function
function updateGridLayout() {
    const grid = document.getElementById('video-grid');
    if (!grid) return;

    const participantCount = grid.children.length;
    const tiles = grid.querySelectorAll('.video-container');

    // Reset styles that might have been set for the single-participant case
    grid.style.display = 'grid';
    tiles.forEach(tile => {
        tile.style.maxWidth = '';
        tile.style.maxHeight = '';
    });

    if (participantCount === 0) {
        grid.style.gridTemplateColumns = '';
        return;
    }

    // Special case for 1 participant: center it, but don't make it huge.
    if (participantCount === 1) {
        grid.style.display = 'flex'; // Use flexbox for simple centering
        grid.style.gridTemplateColumns = ''; // Unset grid property
        if (tiles[0]) {
            // Constrain the single tile so it doesn't fill the screen
            tiles[0].style.maxWidth = 'min(80vw, 142vh)'; // 142vh is approx 80vw at 16:9 aspect ratio
            tiles[0].style.maxHeight = '80vh';
        }
        return;
    }

    // Apply specific grid rules for 2 or more participants
    let columns;
    switch (participantCount) {
        case 2:  columns = 2; break;
        case 3:  columns = 3; break;
        case 4:  columns = 2; break;
        case 5:
        case 6:  columns = 3; break;
        case 7:
        case 8:  columns = 4; break;
        case 9:  columns = 3; break;
        case 10:
        case 11:
        case 12: columns = 4; break;
        case 13:
        case 14:
        case 15: columns = 5; break;
        case 16: columns = 4; break;
        case 17:
        case 18:
        case 19:
        case 20: columns = 5; break;
        default:
            // A sensible default for more than 20 participants
            columns = 5;
            break;
    }
    grid.style.gridTemplateColumns = `repeat(${columns}, 1fr)`;
}


// NEW: Active Speaker Function
function updateActiveSpeaker(activeUsername) {
    document.querySelectorAll('.video-container').forEach(container => {
        container.classList.remove('active-speaker');
    });
    const activeContainer = document.getElementById(`video-container-${activeUsername}`);
    if (activeContainer) {
        activeContainer.classList.add('active-speaker');
    }
}

function addVideoStream(username, stream, isLocal = false) {
    console.log(`Adding video stream for: ${username}`);
    const grid = document.getElementById('video-grid');
    let container = document.getElementById(`video-container-${username}`);
    const isScreen = username.endsWith('-screen');

    if (!container) {
        container = document.createElement('div');
        container.id = `video-container-${username}`;
        container.className = 'video-container';
        if (isScreen) {
            container.classList.add('is-screen-share');
        }
        grid.appendChild(container);
    } else {
        container.innerHTML = '';
    }

    const video = document.createElement('video');
    video.autoplay = true;
    video.playsInline = true;
    if (isLocal) video.muted = true;

    if (stream) {
        video.srcObject = stream;
        video.onloadedmetadata = () => video.play().catch(e => console.error('Video play failed:', e));
    }
    container.appendChild(video);

    const participant = participants.find(p => p.username === username.replace('-screen', '')) || { display_name: username, profile_picture: 'uploads/img/default_pfp.png' };

    const overlay = document.createElement('div');
    overlay.className = 'profile-overlay';
    overlay.innerHTML = `<img src="${participant.profile_picture}" alt="Profile" /><div class="name-tag">${participant.display_name}</div>`;
    overlay.style.display = stream ? 'none' : 'flex';
    container.appendChild(overlay);

    const label = document.createElement('div');
    label.className = 'video-label';
    label.innerHTML = `<ion-icon name="mic-outline"></ion-icon><span>${participant.display_name} ${isScreen ? '(Screen)' : ''}</span>`;
    container.appendChild(label);

    // Only add the fullscreen button if it's NOT the local user's own screen share
    if (!(isLocal && isScreen)) {
        const fullscreenBtn = document.createElement('button');
        fullscreenBtn.className = 'control-btn tile-fullscreen-btn';
        fullscreenBtn.title = 'View Fullscreen';
        fullscreenBtn.innerHTML = `<ion-icon name="scan-outline"></ion-icon>`;
        container.appendChild(fullscreenBtn);

        fullscreenBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (document.fullscreenElement === container) {
                document.exitFullscreen();
            } else {
                container.requestFullscreen().catch(err => {
                    console.error(`Error attempting to enable full-screen mode for tile: ${err.message}`);
                });
            }
        });
    }
    
    updateGridLayout(); // Update layout when a new stream is added

    if (isScreen) {
          updateParticipantStatus(username, 'toggle-video', true);
    } else {
          updateParticipantStatus(username, 'toggle-video', !!stream);
    }
}

function removeVideoStream(username) {
    console.log(`Removing video stream for: ${username}`);
    const el = document.getElementById(`video-container-${username}`);
    if (el) el.remove();
    updateGridLayout(); // Update layout when a stream is removed
}

function updateParticipantStatus(username, type, enabled) {
    const container = document.getElementById(`video-container-${username}`);
    if (!container) return;

    if (type === 'toggle-video') {
        const overlay = container.querySelector('.profile-overlay');
        const video = container.querySelector('video');
        if (overlay) overlay.style.display = enabled ? 'none' : 'flex';
        if (video) video.style.display = enabled ? 'block' : 'none';
    } else if (type === 'toggle-audio') {
        const micIcon = container.querySelector('.video-label ion-icon');
        if (micIcon) micIcon.setAttribute('name', enabled ? 'mic-outline' : 'mic-off-outline');
    }
}

/* -------------------- CONTROLS -------------------- */
function updateFullscreenIcons() {
    document.querySelectorAll('.tile-fullscreen-btn').forEach(btn => {
        btn.innerHTML = `<ion-icon name="scan-outline"></ion-icon>`;
        btn.title = 'View Fullscreen';
    });

    if (document.fullscreenElement && document.fullscreenElement.classList.contains('video-container')) {
        const btn = document.fullscreenElement.querySelector('.tile-fullscreen-btn');
        if (btn) {
            btn.innerHTML = `<ion-icon name="contract-outline"></ion-icon>`;
            btn.title = 'Exit Fullscreen';
        }
    }
}

document.getElementById('toggle-audio').onclick = () => {
    isAudioOn = !isAudioOn;
    // FIX: Explicitly toggle the audio tracks on the local stream.
    if (localStream) { 
        localStream.getAudioTracks().forEach(t => t.enabled = isAudioOn);
    }
    sendSignal({ type: 'toggle-audio', enabled: isAudioOn, from: currentUser, forumId });
    updateParticipantStatus(currentUser, 'toggle-audio', isAudioOn);
    const btn = document.getElementById('toggle-audio');
    btn.innerHTML = `<ion-icon name="${isAudioOn ? 'mic-outline' : 'mic-off-outline'}"></ion-icon>`;
    btn.classList.toggle('toggled-off', !isAudioOn);
};

document.addEventListener('fullscreenchange', updateFullscreenIcons);

document.getElementById('toggle-video').onclick = async () => {
    const shouldBeOn = !isVideoOn;

    if (shouldBeOn) {
        try {
            const newStream = await navigator.mediaDevices.getUserMedia({ video: true });
            const newVideoTrack = newStream.getVideoTracks()[0];
            if (localStream) {
                localStream.getVideoTracks().forEach(track => localStream.removeTrack(track));
                localStream.addTrack(newVideoTrack);
            } else {
                localStream = newStream;
            }
            const localVideoEl = document.querySelector(`#video-container-${currentUser} video`);
            if (localVideoEl) localVideoEl.srcObject = localStream;

            for (const peerUsername in peerConnections) {
                const pc = peerConnections[peerUsername];
                const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                if (sender) await sender.replaceTrack(newVideoTrack);
            }
            isVideoOn = true;
        } catch (err) {
            console.error("Error starting camera:", err);
            return;
        }
    } else {
        if (localStream) localStream.getVideoTracks().forEach(track => track.stop());
        isVideoOn = false;
    }

    sendSignal({ type: 'toggle-video', enabled: isVideoOn, from: currentUser, forumId });
    updateParticipantStatus(currentUser, 'toggle-video', isVideoOn);
    const btn = document.getElementById('toggle-video');
    btn.innerHTML = `<ion-icon name="${isVideoOn ? 'videocam-outline' : 'videocam-off-outline'}"></ion-icon>`;
    btn.classList.toggle('toggled-off', !isVideoOn); // NEW: Toggle active state class
};

document.getElementById('toggle-screen').onclick = async () => {
    if (!isScreenSharing) {
        try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
            const screenTrack = screenStream.getVideoTracks()[0];
            addVideoStream(currentUser + '-screen', screenStream, true);
            
            // **FIX: REPLACE THE VIDEO TRACK FOR ALL PEERS.**
            Object.values(peerConnections).forEach(pc => {
                const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                if (sender) sender.replaceTrack(screenTrack);
            });
            isScreenSharing = true;
            document.getElementById('toggle-screen').classList.add('active'); // Use active state
            screenTrack.onended = stopScreenShare;
        } catch (err) {
            console.error('Screen share failed:', err);
        }
    } else {
        stopScreenShare();
    }
};

function stopScreenShare() {
    if (!isScreenSharing) return;
    removeVideoStream(currentUser + '-screen');
    screenStream.getTracks().forEach(track => track.stop());
    const cameraTrack = localStream?.getVideoTracks()[0];
    if (cameraTrack) {
        // **FIX: REPLACE THE SCREEN TRACK WITH THE CAMERA TRACK.**
        Object.values(peerConnections).forEach(pc => {
            const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
            if (sender) sender.replaceTrack(cameraTrack);
        });
    }
    isScreenSharing = false;
    document.getElementById('toggle-screen').classList.remove('active'); // Remove active state
}

document.getElementById('toggle-chat').onclick = () => {
    document.getElementById('chat-sidebar').classList.toggle('hidden');
};
document.getElementById('close-chat-btn').onclick = () => {
    document.getElementById('chat-sidebar').classList.add('hidden');
};

document.getElementById('end-call').onclick = () => {
    if (confirm('Are you sure you want to leave the call?')) {
        window.location.href = `<?php echo $isAdmin ? 'admin/forum-chat.php' : ($isMentor ? 'mentor/forum-chat.php' : 'mentee/forum-chat.php'); ?>?view=forum&forum_id=${forumId}`;
    }
};

/* -------------------- CHAT (AJAX) -------------------- */
const chatInput = document.getElementById('chat-message');
const sendChatBtn = document.getElementById('send-chat-btn');

function sendChatMessage() {
    const msg = chatInput.value.trim();
    if (!msg) return;
    const formData = new FormData();
    formData.append('action', 'video_chat');
    formData.append('forum_id', forumId);
    formData.append('message', msg);
    fetch('', { method: 'POST', body: new URLSearchParams(formData) })
        .then(response => { if (response.ok) chatInput.value = ''; })
        .catch(error => console.error('Error sending chat message:', error));
}
sendChatBtn.onclick = sendChatMessage;
chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); sendChatMessage(); }
});

function pollChatMessages() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newMessagesContainer = doc.getElementById('chat-messages');
            const currentMessagesContainer = document.getElementById('chat-messages');
            if (newMessagesContainer && currentMessagesContainer) {
                if (newMessagesContainer.innerHTML !== currentMessagesContainer.innerHTML) {
                    currentMessagesContainer.innerHTML = newMessagesContainer.innerHTML;
                    currentMessagesContainer.scrollTop = currentMessagesContainer.scrollHeight;
                }
            }
        })
        .catch(err => console.error('Error polling for chat messages:', err));
}

/* -------------------- LIFECYCLE & INITIALIZATION -------------------- */
function cleanup() {
    console.log('Cleaning up connections...');
    sendSignal({ type: 'leave', from: currentUser, forumId });
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (screenStream) screenStream.getTracks().forEach(t => t.stop());
    Object.values(peerConnections).forEach(pc => pc.close());
    peerConnections = {};
    if(socket) socket.close();
}
window.addEventListener('beforeunload', cleanup);

// --- Start the application ---
console.log(`Initializing video call for user '${currentUser}' in forum '${forumId}'`);

if (!('getDisplayMedia' in navigator.mediaDevices)) {
    console.warn('Screen sharing is not supported in this browser.');
    document.getElementById('toggle-screen').style.display = 'none';
}

initWebSocket();
getMedia();
setInterval(pollChatMessages, 1000); // Polling interval can be slightly longer

// DEMO: Simulate active speaker changes. In a real app, this would be driven
// by audio analysis from a server or the local AudioContext API.
setInterval(() => {
    const allUsers = [currentUser, ...participants.map(p => p.username)];
    const randomSpeaker = allUsers[Math.floor(Math.random() * allUsers.length)];
    updateActiveSpeaker(randomSpeaker);
}, 2500);

</script>
</body>
</html>