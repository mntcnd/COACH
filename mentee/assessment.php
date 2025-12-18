<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    header("Location: ../login.php");
    exit();
}

require '../connection/db_connection.php';

if (!isset($_SESSION['username'])) {
  header("Location: ../login.php");
  exit();
}

$userId = $_SESSION['user_id'];

// --- GET COURSE + ACTIVITY + LEVEL ---
$courseTitle     = $_GET['course_title']     ?? $_POST['course_title']     ?? '';
$activityTitle   = $_GET['activity_title']   ?? $_POST['activity_title']   ?? '';
$difficultyLevel = $_GET['difficulty_level'] ?? $_POST['difficulty_level'] ?? '';

if (!$courseTitle || !$activityTitle || !$difficultyLevel) {
    die("Invalid access. Course, Activity, or Difficulty missing.");
}

$sessionKey = "{$userId}_{$courseTitle}_{$activityTitle}_{$difficultyLevel}";

// --- LOAD QUESTIONS IF NEW SESSION ---
if (!isset($_SESSION['assessment'][$sessionKey])) {
    $stmt = $conn->prepare("
        SELECT * FROM mentee_assessment
        WHERE Course_Title = ? 
          AND Activity_Title = ? 
          AND Difficulty_Level = ?
          AND Status = 'approved'
        ORDER BY RAND() LIMIT 20
    ");
    $stmt->bind_param("sss", $courseTitle, $activityTitle, $difficultyLevel);
    $stmt->execute();
    $result = $stmt->get_result();

    $_SESSION['assessment'][$sessionKey] = [
        'questions' => $result->fetch_all(MYSQLI_ASSOC),
        'index'     => 0,
        'answers'   => [],
        'started'   => false
    ];
    $stmt->close();
}

$quiz = &$_SESSION['assessment'][$sessionKey];

// --- Handle Proceed ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed'])) {
    $quiz['started'] = true;
}

// --- Handle Answer Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $quiz['answers'][$quiz['index']] = $_POST['answer'];
    $quiz['index']++;
}

if (isset($_GET['question_index'])) {
    $quiz['index'] = (int) $_GET['question_index'];
}

$questions = $quiz['questions'];
$index     = $quiz['index'];
$total     = count($questions);
$completed = $index >= $total;
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title>Assessment - <?= htmlspecialchars($courseTitle) ?> <?= htmlspecialchars($activityTitle) ?> <?= htmlspecialchars($difficultyLevel) ?></title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f3e5f5; padding: 20px; color: #4a148c; }
    .container { max-width: 900px; margin: auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 6px 15px rgba(0,0,0,0.15); }
    .btn { background: #8e24aa; color: white; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
    .btn:hover { background: #6a1b9a; }
    .choice-container { background: #f3e5f5; border: 2px solid #ce93d8; border-radius: 10px; padding: 12px 16px; margin-bottom: 15px; cursor: pointer; }
    .choice-container:hover { background: #e1bee7; border-color: #ab47bc; }
    label { font-size: 18px; }
    .progress-bar-container { background: #e1bee7; border-radius: 20px; height: 20px; margin-bottom: 30px; }
    .progress-bar { background: #8e24aa; height: 100%; border-radius: 20px; transition: width 0.3s ease; }
    .button-container { display: flex; justify-content: space-between; }
    .review-box { border-left: 4px solid #ccc; padding-left: 10px; margin-bottom: 15px; }
  </style>
</head>
<body>
<div class="container">
<?php if (!$quiz['started']): ?>
  <div style="background-color:#ede7f6; border-left:6px solid #7b1fa2; padding:20px; border-radius:10px; margin-bottom:25px;">
    <h2>Welcome to <strong><?= htmlspecialchars("$courseTitle $activityTitle") ?>!</strong></h2>
    <h3 style="color:#6a1b9a;">(<?= htmlspecialchars($difficultyLevel) ?> Level)</h3>

    <p>You will answer <strong><?= $total ?></strong> questions.</p>
    <p>Reminders:</p>
    <ul>
      <li>✔ Ensure a stable internet connection.</li>
      <li>✔ Do not refresh or close the browser during the assessment.</li>
      <li>✔ Your progress will be tracked per attempt.</li>
    </ul>
    <p style="margin-top:15px; font-weight:bold; color:#4a148c;">
      Good luck! Show what you’ve learned and give your best effort. 
    </p>
  </div>
  <form method="POST">
    <input type="hidden" name="course_title" value="<?= htmlspecialchars($courseTitle) ?>">
    <input type="hidden" name="activity_title" value="<?= htmlspecialchars($activityTitle) ?>">
    <input type="hidden" name="difficulty_level" value="<?= htmlspecialchars($difficultyLevel) ?>">

    <button type="submit" name="proceed" class="btn">Proceed</button>
  </form>
<?php elseif ($completed): ?>

  <?php
    $score = 0;
    $output = "";

    foreach ($questions as $i => $q) {
      $correct = trim($q['Correct_Answer']);
      $given   = isset($quiz['answers'][$i]) ? trim($quiz['answers'][$i]) : 'No answer';
      $isCorrect = strcasecmp($correct, $given) === 0;
      if ($isCorrect) $score++;

      $output .= "<div class='review-box'>
        <strong>Q" . ($i+1) . ":</strong> " . htmlspecialchars($q['Question']) . "<br>
        Your Answer: <span style='color:" . ($isCorrect ? "green" : "red") . "'>" . htmlspecialchars($given) . "</span><br>
        Correct Answer: <strong>" . htmlspecialchars($correct) . "</strong>
      </div>";
    }

    // --- Get Attempt Number ---
    $attemptQuery = $conn->prepare("
        SELECT COALESCE(MAX(Attempt_Number), 0) + 1 AS NextAttempt
        FROM menteescores
        WHERE user_id = ? AND Course_Title = ? AND Activity_Title = ? AND Difficulty_Level = ?
    ");
    $attemptQuery->bind_param("isss", $userId, $courseTitle, $activityTitle, $difficultyLevel);
    $attemptQuery->execute();
    $attemptQuery->bind_result($nextAttempt);
    $attemptQuery->fetch();
    $attemptQuery->close();

    // --- Insert attempt into menteescores ---
    $stmt = $conn->prepare("INSERT INTO menteescores 
        (user_id, Course_Title, Activity_Title, Difficulty_Level, Attempt_Number, Score, Total_Questions, Date_Taken)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssiii", $userId, $courseTitle, $activityTitle, $difficultyLevel, $nextAttempt, $score, $total);
    $stmt->execute();
    $attemptId = $stmt->insert_id;
    $stmt->close();

    // --- Insert answers into mentee_answers ---
    $insertAnswer = $conn->prepare("INSERT INTO mentee_answers 
        (Attempt_ID, user_id, Course_Title, Activity_Title, Difficulty_Level, Question, Selected_Answer, Correct_Answer, Is_Correct, Attempt_Number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($questions as $i => $q) {
      $questionText = $q['Question'];
      $correct = trim($q['Correct_Answer']);
      $given   = isset($quiz['answers'][$i]) ? trim($quiz['answers'][$i]) : 'No answer';
      $isCorrect = strcasecmp($correct, $given) === 0 ? 1 : 0;

      $insertAnswer->bind_param("iissssssii", 
          $attemptId, 
          $userId, 
          $courseTitle, 
          $activityTitle, 
          $difficultyLevel, 
          $questionText, 
          $given, 
          $correct, 
          $isCorrect,
          $nextAttempt
      );
      $insertAnswer->execute();
    }
    $insertAnswer->close();

    unset($_SESSION['assessment'][$sessionKey]);
  ?>
  <h1>Assessment Complete</h1>
  <h2>You scored <strong><?= $score ?></strong> out of <strong><?= $total ?></strong>.</h2>
  <h3>Review:</h3>
  <?= $output ?>
  <div class="button-container">
    <a href="activities.php" class="btn back-btn">Back to Activities</a>
  </div>

<?php else: ?>
  <?php $progressPercent = round(($index / $total) * 100); ?>
  <h2><?= htmlspecialchars("$courseTitle $activityTitle") ?> - <?= htmlspecialchars($difficultyLevel) ?> Level  
      <br>Question <?= $index + 1 ?> of <?= $total ?></h2>
  <div class="progress-bar-container">
    <div class="progress-bar" style="width: <?= $progressPercent ?>%;"></div>
  </div>
  <form method="POST" action="assessment.php">
    <input type="hidden" name="course_title" value="<?= htmlspecialchars($courseTitle) ?>">
    <input type="hidden" name="activity_title" value="<?= htmlspecialchars($activityTitle) ?>">
    <input type="hidden" name="difficulty_level" value="<?= htmlspecialchars($difficultyLevel) ?>">

    <h3><?= htmlspecialchars($questions[$index]['Question']) ?></h3>
    <?php
      $savedAnswer = $quiz['answers'][$index] ?? null;
      foreach (['Choice1', 'Choice2', 'Choice3', 'Choice4'] as $choice):
        $choiceText = htmlspecialchars($questions[$index][$choice]);
        $isChecked = ($savedAnswer === $questions[$index][$choice]) ? "checked" : "";
    ?>
      <div class="choice-container">
        <label>
          <input type="radio" name="answer" value="<?= $choiceText ?>" <?= $isChecked ?> required>
          <?= $choiceText ?>
        </label>
      </div>
    <?php endforeach; ?>
    <div class="button-container">
      <?php if ($index > 0): ?>
        <a href="assessment.php?course_title=<?= htmlspecialchars($courseTitle) ?>&activity_title=<?= htmlspecialchars($activityTitle) ?>&difficulty_level=<?= htmlspecialchars($difficultyLevel) ?>&question_index=<?= $index - 1 ?>" class="btn back-btn">Back</a>
      <?php endif; ?>
      <button type="submit" class="btn next-btn"><?= ($index + 1 == $total) ? 'Finish' : 'Next' ?></button>
    </div>
  </form>
<?php endif; ?>
</div>
</body>
</html>
