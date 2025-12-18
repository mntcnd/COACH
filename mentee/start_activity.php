<?php
// start_activity.php - full-screen answering page (no navbar)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../connection/db_connection.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$menteeUserId = (int)$_SESSION['user_id'];

$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$retry = isset($_GET['retry']) ? 1 : 0;
if (!$activity_id) {
    header("Location: activities.php");
    exit();
}

// fetch activity details
$stmt = $conn->prepare("SELECT Activity_Title, Lesson, Activity_Type, Questions_JSON, File_Path FROM activities WHERE Activity_ID = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$activity) {
    echo "Activity not found.";
    exit();
}

// next attempt number
$stmt = $conn->prepare("SELECT COUNT(*) AS attempts FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ?");
$stmt->bind_param("ii", $activity_id, $menteeUserId);
$stmt->execute();
$cnt = $stmt->get_result()->fetch_assoc();
$stmt->close();
$nextAttempt = ((int)$cnt['attempts']) + 1;

$questions_json = $activity['Questions_JSON'] ?? '[]';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($activity['Activity_Title']) ?> — Start</title>
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
/* Color Palette */
:root {
    --primary-purple: #6a2c70; /* Deep Purple - Primary Button, Headings */
    --primary-hover: #9724b0ff; /* Slightly darker primary */
    --secondary-purple: #91489bff; /* Light Purple - Secondary Button (View File) */
    --secondary-hover: #60225dff; /* Slightly darker secondary */
    --text-color: #424242;        /* Default text */
    --light-bg: #fdfdff;          /* Page background */
    --container-bg: #fff;
    --border-color: #E1BEE7;
    --flat-line-color: #EEEEEE;
}

/* Base styles */
body { 
    margin: 0; 
    padding: 0;
    background: var(--light-bg); 
    font-family: "Poppins", sans-serif; 
    color: var(--text-color); 
    min-height: 100vh;
}

.full-activity-wrapper { 
    max-width: 900px; 
    margin: 40px auto; 
    padding: 30px; 
    background: var(--container-bg); 
    border-radius: 14px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.08);
}

.full-activity-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: flex-start;
    margin-bottom: 20px; 
    padding-bottom: 15px;
    border-bottom: 2px solid var(--border-color);
}
.full-activity-header .logo-and-title {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-grow: 1;
    max-width: 70%;
}
.full-activity-header .title-details {
    display: flex;
    flex-direction: column;
}
.full-activity-header h1 { 
    margin: 0; 
    color: var(--primary-purple); 
    font-size: 2rem; 
    font-weight: 750;
    line-height: 1.2;
}
.full-activity-header .logo-container img {
    height: 90px;
    flex-shrink: 0;
}

.small { color: #480055ff; font-size: 15px; }


/* Reminders Box - Flat style */
.reminders {
    background: #fff;
    border: 1px solid var(--border-color);
    padding: 18px;
    border-radius: 8px;
    margin: 25px 0;
    color: var(--text-color);
    line-height: 1.6;
}
.reminders strong { color: var(--primary-purple); }
.reminders ul { 
    list-style-type: none; 
    padding-left: 0;
    margin: 10px 0 0 0;
}
.reminders ul li {
    margin-bottom: 8px;
    font-size: 1.1rem;
    position: relative;
    padding-left: 1.8em;
}
.reminders ul li::before {
    content: '✔';
    color: var(--primary-purple); 
    font-weight: bold;
    display: inline-block;
    width: 1.5em; 
    margin-left: -1.8em;
}


/* Question Styles - Flat Look */
.question-item {
    padding: 25px 0; 
    margin: 0;
    border-top: 1px solid var(--flat-line-color);
}
.question-item:first-of-type {
    border-top: none;
    padding-top: 5px;
}

.question-item h4 { 
    margin: 0 0 18px 0; 
    color: var(--primary-purple); 
    font-size: 1.25rem;
    font-weight: 600;
}
.question-item label { 
    display: flex; 
    align-items: center;
    margin: 14px 0; 
    font-size: 1.15rem;
    color: var(--text-color); 
    cursor: pointer;
}


/* 🚨 RADIO BUTTON CSS 🚨 */
.question-item input[type="radio"] {
    opacity: 0;
    position: absolute;
}
.question-item label span {
    /* Custom radio indicator */
    display: inline-block;
    width: 22px;
    height: 22px;
    border: 2px solid var(--primary-purple);
    border-radius: 50%;
    margin-right: 15px;
    position: relative;
    flex-shrink: 0;
}
.question-item input[type="radio"]:checked + span::after {
    /* Inner dot when checked */
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--primary-purple);
    transform: translate(-50%, -50%);
}


/* Text Area Style */
.text-input {
    width: 100%; 
    padding: 15px;
    border-radius: 10px;
    border: 1px solid #ccc; 
    font-size: 1.1rem;
    box-sizing: border-box;
    transition: border-color 0.2s;
    min-height: 100px;
    resize: vertical;
}
.text-input:focus { 
    border-color: var(--primary-purple); 
    outline: 2px solid var(--border-color); 
}


/* Buttons */
.btn, .btn-secondary {
    cursor: pointer;
    border: none;
    padding: 14px 24px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.15s ease-in-out;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 180px; 
    text-decoration: none;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
.btn { /* Primary Purple (Submit) */
    background: var(--primary-purple); 
    color: white;
}
.btn:hover { 
    background: var(--primary-hover); 
    transform: translateY(-1px); 
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

/* Secondary Button - Back to Activities */
.btn-secondary {
    background: var(--secondary-purple); 
    color: white; 
    box-shadow: none;
}
.btn-secondary:hover { 
    background: var(--secondary-hover); 
    transform: translateY(-1px);
}

.footer-actions {
    margin-top: 35px;
    text-align: center;
    display: flex;
    justify-content: center;
    gap: 15px;
}


/* Modal Styles */
.modal-inline {
    display: none; 
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 4000;
    align-items: center;
    justify-content: center;
}
.modal-card {
    background: #fff;
    border-radius: 12px;
    padding: 30px 35px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    z-index: 4001;
}
.modal-card h3 {
    color: var(--primary-purple);
    margin-top: 0;
}

/* Specific styling for the new View Attached File button */
.file-action {
    display: block;
    min-width: 180px; 
}
.file-action a {
    text-decoration: none;
    padding: 10px 16px; 
    border-radius: 10px; 
    font-weight: 500;
    min-width: auto;
    /* Uses the secondary button styling from the primary definition */
    background: var(--secondary-purple);
    color: white;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s ease-in-out;
}
.file-action a:hover {
    background: var(--secondary-hover);
    transform: translateY(-1px);
}

@media (max-width: 760px) {
    .full-activity-wrapper {
        margin: 10px;
        padding: 20px;
    }
    .full-activity-header .logo-and-title {
        flex-direction: column;
        align-items: flex-start;
        max-width: 100%;
        gap: 10px;
    }
    .full-activity-header .logo-container {
        margin-bottom: 5px;
    }
    .footer-actions {
        flex-direction: column;
        gap: 10px;
    }
    .btn, .btn-secondary {
        min-width: unset;
        width: 100%;
    }
}
</style>
</head>
<body class="full-mode">
<div class="full-activity-wrapper" role="main" aria-labelledby="activityTitle">
    <div class="full-activity-header">
        <div class="logo-and-title">
            <div class="logo-container">
                <img src="../uploads/img/LogoCoach.png" alt="LogoCoach">
            </div>
            <div class="title-details">
                <h1 id="activityTitle"><?= htmlspecialchars($activity['Activity_Title']) ?></h1>
                <div class="small"><strong>Lesson:</strong> <?= htmlspecialchars($activity['Lesson'] ?? '') ?></div>
                <div class="small" style="margin-top:6px;"><strong>Attempt:</strong> <?= $nextAttempt ?></div>
            </div>
        </div>
        
        <div class="file-action">
            <?php if (!empty($activity['File_Path'])): ?>
                <a href="view_file.php?file=<?= urlencode($activity['File_Path']) ?>" target="_blank">
                    <i class='bx bx-file'></i> View Attached File
                </a>

            <?php else: ?>
                <p id="noPreviewFileMsg" style="color:var(--text-color); font-size:16px;">No attached file.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="reminders">
        <strong>Reminders:</strong>
        <ul>
            <li>Ensure a stable internet connection.</li>
            <li>Do not refresh or close the browser during the assessment.</li>
            <li>Your progress will be tracked per attempt.</li>
        </ul>
        <div class="small" style="margin-top:10px; color:#666;"><strong>Good luck!</strong> Show what you’ve learned and give your best effort.</div>
    </div>

    <form id="activityForm" method="POST" action="done_activity.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="activity_id" value="<?= $activity_id ?>">
        <input type="hidden" name="attempt_number" value="<?= $nextAttempt ?>">
        <input type="hidden" name="activity_type" value="<?= htmlspecialchars($activity['Activity_Type']) ?>">
        <input type="hidden" name="answers_json" value=""> 

        <div id="questionsContainer" style="margin-top:12px;">
            </div>

        <div class="footer-actions">
            <button type="button" id="confirmSubmit" class="btn">Submit</button>
            <a href="activities.php" class="btn-secondary" style="text-decoration:none;">Back to Activities</a>
        </div>
    </form>
</div>

<div class="modal-inline" id="confirmModal">
    <div class="modal-card">
        <h3>Confirm Submission</h3>
        <p>Are you sure you want to submit this attempt? You will be able to review your answers afterwards.</p>
        <div style="text-align:right;margin-top:18px;">
            <button id="cancelModal" class="btn-secondary" style="min-width:100px;">Cancel</button>
            <button id="submitNow" class="btn" style="min-width:140px;">Submit Now</button>
        </div>
    </div>
</div>

<script>
// Questions data injected safely
const QUESTIONS = <?php echo json_encode(json_decode($questions_json, true) ?: [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const activityId = <?= json_encode($activity_id) ?>;
const DRAFT_KEY = 'activity_draft_' + activityId;

const questionsContainer = document.getElementById('questionsContainer');
const form = document.getElementById('activityForm');

// --- Draft Logic (Standard) ---
function loadDraft() {
    try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}'); } catch (e) { return {}; }
}
function saveDraft() {
    const draft = {};
    document.querySelectorAll('[name^="answer_"]').forEach(el => {
        if (el.type === 'radio') {
            if (el.checked) draft[el.name] = el.value;
        } else if (el.tagName === 'TEXTAREA' || el.type === 'text') {
            draft[el.name] = el.value;
        }
    });
    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
}
function clearDraft() { localStorage.removeItem(DRAFT_KEY); }

// --- Question Rendering (Updated for custom radio CSS) ---
function renderQuestions() {
    questionsContainer.innerHTML = '';
    const draft = loadDraft();
    if (!Array.isArray(QUESTIONS) || QUESTIONS.length === 0) {
        questionsContainer.innerHTML = '<p style="color:var(--text-color); text-align:center;">No questions for this activity.</p>';
        return;
    }

    QUESTIONS.forEach((q, i) => {
        const div = document.createElement('div');
        div.className = 'question-item';
        const h = document.createElement('h4');
        h.textContent = (i+1) + '. ' + (q.question || '');
        div.appendChild(h);

        const t = (q.type || '').toLowerCase();
        if (t === 'multiple choice' || t === 'multiple_choice' || Array.isArray(q.choices)) {
            const choices = q.choices || q.options || [];
            choices.forEach((choice, ci) => {
                if (choice === null || choice === undefined || String(choice).trim() === '') return;
                const label = document.createElement('label');
                const input = document.createElement('input');
                
                input.type = 'radio';
                input.name = 'answer_' + i;
                input.value = String.fromCharCode(65 + ci);
                input.required = true;
                if (draft['answer_' + i] && draft['answer_' + i] === input.value) input.checked = true;
                input.addEventListener('change', saveDraft);
                
                // Append input and a custom span for the radio indicator
                label.appendChild(input);
                const indicatorSpan = document.createElement('span');
                label.appendChild(indicatorSpan);
                
                // Add the text content
                label.insertAdjacentHTML('beforeend', String.fromCharCode(65 + ci) + '. ' + choice);
                
                div.appendChild(label);
            });
        } else {
            // Using textarea for short answers
            const textarea = document.createElement('textarea');
            textarea.name = 'answer_' + i;
            textarea.className = 'text-input';
            textarea.placeholder = 'Enter your answer here...';
            textarea.value = draft['answer_' + i] || '';
            textarea.rows = 4;
            textarea.required = true;
            textarea.addEventListener('input', saveDraft);
            div.appendChild(textarea);
        }
        questionsContainer.appendChild(div);
    });
}

// --- Modal and Submission Logic (Ensures validation runs) ---
const confirmModal = document.getElementById('confirmModal');

document.getElementById('confirmSubmit').addEventListener('click', () => {
    // Check form validity before showing modal
    if (form.reportValidity()) { 
        confirmModal.style.display = 'flex';
    }
});
document.getElementById('cancelModal').addEventListener('click', () => confirmModal.style.display = 'none');

document.getElementById('submitNow').addEventListener('click', () => {
    const type = form.querySelector('[name="activity_type"]').value || '';
    if (type.toLowerCase() !== 'file submission') {
        const answers = {};
        document.querySelectorAll('[name^="answer_"]').forEach(el => {
            if (el.type === 'radio') {
                if (el.checked) answers[el.name] = el.value;
            } else if (el.tagName === 'TEXTAREA' || el.type === 'text') {
                answers[el.name] = el.value;
            }
        });
        // Update the hidden input field
        form.querySelector('input[name="answers_json"]').value = JSON.stringify(answers);
    }

    clearDraft();
    confirmModal.style.display = 'none';
    form.submit();
});

// initial render
renderQuestions();
</script>
</body>
</html>