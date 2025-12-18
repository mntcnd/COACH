<?php
// done_activity.php - store submission (via POST) and show result
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../connection/db_connection.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$menteeUserId = (int)$_SESSION['user_id'];

// helper compute score
// done_activity.php (UPDATED compute_score function)
function compute_score($questions, $answers_arr) {
    $correct = 0;
    $total_questions = count($questions); // Total number of questions
    
    foreach ($questions as $i => $q) {
        $key = "answer_$i";
        $user = isset($answers_arr[$key]) ? (string)$answers_arr[$key] : '';
        $user_l = mb_strtolower(trim($user));

        if (($q['type'] ?? '') === 'Multiple Choice' || strtolower($q['type'] ?? '') === 'multiple_choice' || isset($q['choices'])) {
            // ... (Multiple Choice Logic remains the same)
            $correct_letter = '';
            if (!empty($q['correct_answer']) && preg_match('/Choice\s*(\d+)/i', $q['correct_answer'], $m)) {
                $idx = (int)$m[1] - 1;
                $correct_letter = chr(65 + $idx);
            }
            if ($correct_letter !== '' && strtoupper($user_l) === strtoupper($correct_letter)) $correct++;
        } else {
            $acceptable = $q['acceptable_answers'] ?? [];
            
            // --- MODIFIED LOGIC START ---
            if (is_string($acceptable)) {
                // Split the comma-separated string into an array, and trim spaces
                $acceptable = array_map('trim', explode(',', $acceptable));
            } elseif (!is_array($acceptable) && $acceptable !== null) {
                // Handle cases where it might be a non-array, non-string value (e.g., an integer)
                $acceptable = [(string)$acceptable]; 
            }
            // --- MODIFIED LOGIC END ---

            foreach ($acceptable as $acc) {
                if (mb_strtolower(trim((string)$acc)) === $user_l && $user_l !== '') { $correct++; break; }
            }
        }
    }
    
    // Return an array with the count of correct answers and the total number of questions
    return ['correct' => $correct, 'total' => $total_questions];
}

// If POST: handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
    if (!$activity_id) { echo "Missing activity id"; exit(); }

    // fetch activity for type & questions
    $stmt = $conn->prepare("SELECT Activity_Type, Questions_JSON FROM activities WHERE Activity_ID = ?");
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $act = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$act) { echo "Activity not found"; exit(); }

    $activity_type = $act['Activity_Type'];
    $questions = json_decode($act['Questions_JSON'], true) ?: [];

    $answers_arr = [];
    $file_path = null;

    if (strtolower($activity_type) === 'file submission') {
        if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
            echo "File upload required."; exit();
        }
        $uploadDir = '../uploads/activities/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $fn = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($_FILES['submission_file']['name']));
        $target = $uploadDir . $fn;
        if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $target)) {
            echo "Failed to move uploaded file."; exit();
        }
        $file_path = $target;
    } else {
        $answers_json = $_POST['answers_json'] ?? '{}';
        $answers_arr = json_decode($answers_json, true) ?: [];
    }

    $score = null;
    $total_questions = 0; // Initialize total questions
    
    if (strtolower($activity_type) !== 'file submission') {
        // --- UPDATED SCORE CALCULATION LOGIC START ---
        $score_data = compute_score($questions, $answers_arr);
        
        // Store the correct count as the score
        $score = (float)$score_data['correct'];
        $total_questions = $score_data['total'];
        // --- UPDATED SCORE CALCULATION LOGIC END ---
    }
    // IMPORTANT: Since the total questions count is needed for the message logic later,
    // we need a way to pass it to the GET block. 
    // The current submission table only stores Score. We will recalculate the total questions 
    // in the GET block, so no need to store it now.

    // attempt number
    $stmt = $conn->prepare("SELECT COUNT(*) AS attempts FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ?");
    $stmt->bind_param("ii", $activity_id, $menteeUserId);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $attempt_number = ((int)$cnt['attempts']) + 1;

    $answers_json_to_store = !empty($answers_arr) ? json_encode($answers_arr, JSON_UNESCAPED_UNICODE) : null;
    $file_bind = $file_path ?: null;
    $score_bind = $score === null ? null : $score; // Score is now the raw correct count

    $stmt = $conn->prepare("INSERT INTO submissions (Activity_ID, Mentee_ID, File_Submission, Answers_JSON, Score, Attempt_Number, Submission_Status, Submitted_At) VALUES (?, ?, ?, ?, ?, ?, 'Submitted', NOW())");
    $stmt->bind_param("iissdi", $activity_id, $menteeUserId, $file_bind, $answers_json_to_store, $score_bind, $attempt_number);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        echo "DB error: " . htmlspecialchars($err); exit();
    }
    $stmt->close();

    // redirect to GET result view
    header("Location: done_activity.php?activity_id=" . urlencode($activity_id) . "&attempt=" . urlencode($attempt_number));
    exit();
}

// if GET: display result for activity_id (most recent or specific attempt)
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
$attempt = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
if (!$activity_id) { header("Location: activities.php"); exit(); }

// get activity title and questions JSON
$stmt = $conn->prepare("SELECT Activity_Title, Activity_Type, Questions_JSON FROM activities WHERE Activity_ID = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$act = $stmt->get_result()->fetch_assoc();
$stmt->close();
$activityTitle = $act['Activity_Title'] ?? 'Activity Submission';
$activityType = $act['Activity_Type'] ?? '';

// --- Get Total Questions for comparison ---
$activityQuestions = json_decode($act['Questions_JSON'] ?? '[]', true) ?: [];
$totalQuestions = count($activityQuestions);
// ------------------------------------------


// fetch submission
if ($attempt) {
    $stmt = $conn->prepare("SELECT Score, Submitted_At, Attempt_Number FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ? AND Attempt_Number = ? LIMIT 1");
    $stmt->bind_param("iii", $activity_id, $menteeUserId, $attempt);
} else {
    $stmt = $conn->prepare("SELECT Score, Submitted_At, Attempt_Number FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ? ORDER BY Attempt_Number DESC, Submitted_At DESC LIMIT 1");
    $stmt->bind_param("ii", $activity_id, $menteeUserId);
}
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Prepare score and message
$displayScore = null;
$resultMessage = '';

if ($submission) {
    $rawScore = $submission['Score'];
    if ($rawScore !== null) {
        // --- UPDATED DISPLAY AND MESSAGE LOGIC START ---
        $scoreValue = (int)round(floatval($rawScore)); // The score is the integer count of correct answers
        
        // Display score as "2/2"
        $displayScore = htmlspecialchars("{$scoreValue}");
        
        // Calculate the actual percentage for grading logic
        $percentage = ($totalQuestions > 0) ? ($scoreValue / $totalQuestions) * 100 : 100;

        if ($percentage >= 90) {
            $resultMessage = "Fantastic job! You've mastered this activity. 🎉";
        } elseif ($percentage >= 70) {
            $resultMessage = "Great effort! You performed well. 👍";
        } else {
            $resultMessage = "Good attempt. Review the material and try again!";
        }
        // --- UPDATED DISPLAY AND MESSAGE LOGIC END ---
    } elseif (strtolower($activityType) === 'file submission') {
        $displayScore = 'Pending Review';
        $resultMessage = "Your file has been submitted successfully and is awaiting review by your mentor. 📄";
    } else {
        $resultMessage = "Submission recorded. Score calculation is unavailable. ⚠️";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<title>Submission Result</title>
<style>
/* Color Palette from start_activity.php */
:root {
    --primary-purple: #6a2c70; /* Deep Purple - Primary Button, Headings */
    --primary-hover: #9724b0ff; /* Slightly darker primary */
    --secondary-purple: #91489bff; /* Light Purple - Secondary Button (Review) */
    --secondary-hover: #60225dff; /* Slightly darker secondary */
    --text-color: #424242;        /* Default text */
    --light-bg: #fdfdff;          /* Page background */
    --container-bg: #fff;
    --border-color: #E1BEE7;
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

.wrapper { 
    max-width: 780px; 
    margin: 60px auto; 
    padding: 40px; 
    background: var(--container-bg); 
    border-radius: 14px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.08); 
    text-align: center;
}

h2 {
    color: var(--primary-purple);
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 5px;
}
.small { 
    color: var(--text-color); 
    font-size: 1.5rem; 
    margin-bottom: 30px; 
    font-weight: 500;
}

/* --- Score Result Enhancements (Centered Alignment) --- */

.score-area {
    margin: 30px 0;
    padding: 40px 30px; /* Increased padding for more emphasis */
    border: 2px solid var(--border-color);
    border-radius: 10px;
    background: #fcfaff;
    
    /* Use Flexbox for centering */
    display: flex;
    flex-direction: column;
    align-items: center; 
    justify-content: center;
}

/* 🚀 BIGGER SCORE NUMBER */
.score-result {
    font-size: 5rem; /* Increased size significantly */
    color: var(--primary-purple); 
    font-weight: 800;
    line-height: 1;
    margin: 0;
}

.result-message {
    color: var(--text-color);
    font-size: 1.5rem; /* Slightly larger message */
    font-weight: 600;
    margin-top: 20px;
    text-align: center;
}

/* Button styles */
.actions {
    margin-top: 35px;
    text-align: center;
    display: flex;
    justify-content: center;
    gap: 15px;
}
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
.btn { 
    background: var(--primary-purple); 
    color: white;
}
.btn:hover { 
    background: var(--primary-hover); 
    transform: translateY(-1px); 
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}
.btn-secondary {
    background: var(--secondary-purple); 
    color: white; 
    box-shadow: none;
}
.btn-secondary:hover { 
    background: var(--secondary-hover); 
    transform: translateY(-1px);
}
.submission-details {
    color: var(--text-color);
    font-size: 1rem;
    margin-top: 15px;
}

/* Logo container for the result page */
.logo-result-container {
    margin-bottom: 20px;
}
.logo-result-container img {
    height: 70px;
}

/* Media Query */
@media (max-width: 760px) {
    .wrapper {
        margin: 10px;
        padding: 20px;
    }
    .actions {
        flex-direction: column;
        gap: 10px;
    }
    .btn, .btn-secondary {
        min-width: unset;
        width: 100%;
    }
    .score-result {
        font-size: 4rem; /* Adjusted for smaller screens */
    }
    .result-message {
        font-size: 1.2rem;
    }
}
</style>
</head>
<body>
<div class="wrapper">
    <div class="logo-result-container">
        <img src="../uploads/img/LogoCoach.png" alt="LogoCoach">
    </div>
    
    <h2>Submission Complete</h2>
    <p class="small"><?= htmlspecialchars($activityTitle) ?></p>

    <?php if ($submission): ?>
        <div class="score-area">
            <span class="score-result"><?= $displayScore ?></span>
            
            <p class="result-message"><?= htmlspecialchars($resultMessage) ?></p>
        </div>
        
        <p class="submission-details">
            Attempt #: <?= htmlspecialchars((string)$submission['Attempt_Number']) ?> 
            &nbsp; • &nbsp; 
            Submitted: <?= htmlspecialchars((string)$submission['Submitted_At']) ?>
        </p>
    <?php else: ?>
        <div class="score-area">
            <span class="score-result">Error</span>
            <p class="result-message">An error occurred or no submission data was found.</p>
        </div>
    <?php endif; ?>

    <div class="actions">
        <a class="btn" href="review_activity.php?activity_id=<?= $activity_id ?>">Review Activity</a>
        <a class="btn-secondary" href="activities.php">Back to Activities</a>
    </div>
</div>

</body>
</html>