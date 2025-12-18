<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../connection/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$mentee_id = $_SESSION['mentee_id'] ?? null;
$activity_id = $_POST['activity_id'] ?? null;
$answers = $_POST['answers'] ?? [];

if (empty($mentee_id) || empty($activity_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid session or activity.']);
    exit;
}

// --- Encode Answers as JSON ---
$answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE);

// --- Get Correct Answers ---
$stmt = $conn->prepare("SELECT Questions_JSON FROM activities WHERE Activity_ID = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();
$activity = $result->fetch_assoc();
$stmt->close();

if (!$activity) {
    echo json_encode(['status' => 'error', 'message' => 'Activity not found.']);
    exit;
}

$questions = json_decode($activity['Questions_JSON'], true);
$total = count($questions);
$correct = 0;

foreach ($questions as $index => $q) {
    $correct_answer = strtolower(trim($q['correct_answer'] ?? ''));
    $user_answer = strtolower(trim($answers[$index] ?? ''));
    if ($user_answer === $correct_answer && $correct_answer !== '') {
        $correct++;
    }
}

$score = ($total > 0) ? round(($correct / $total) * 100, 2) : 0;

// --- Determine Attempt Number ---
$attempt_sql = $conn->prepare("SELECT COUNT(*) AS attempts FROM submissions WHERE Activity_ID = ? AND Mentee_ID = ?");
$attempt_sql->bind_param("ii", $activity_id, $mentee_id);
$attempt_sql->execute();
$attempt_result = $attempt_sql->get_result()->fetch_assoc();
$attempt_sql->close();

$attempt_number = ($attempt_result['attempts'] ?? 0) + 1;

// --- Insert Submission ---
$stmt = $conn->prepare("
    INSERT INTO submissions 
    (Activity_ID, Mentee_ID, Answers_JSON, Score, Attempt_Number, Submission_Status, Submitted_At)
    VALUES (?, ?, ?, ?, ?, 'Submitted', NOW())
");
$stmt->bind_param("iisdi", $activity_id, $mentee_id, $answers_json, $score, $attempt_number);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Submission saved successfully.',
        'score' => $score,
        'attempt' => $attempt_number
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save submission.']);
}

$stmt->close();
$conn->close();
?>
