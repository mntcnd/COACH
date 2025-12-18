<?php
// mentor_assessment.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Start session

require 'connection/db_connection.php';

// --- Handle AJAX request to save assessment state ---
if (isset($_POST['action']) && $_POST['action'] === 'save_state') {
    header('Content-Type: application/json');
    if (isset($_POST['state'])) {
        $_SESSION['assessment_state'] = json_decode($_POST['state'], true); // Decode the JSON string
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No state data provided']);
    }
    exit; // Stop execution after saving state
}

// --- Handle AJAX request to clear assessment state ---
if (isset($_POST['action']) && $_POST['action'] === 'clear_state') {
    header('Content-Type: application/json');
    unset($_SESSION['assessment_state']);
    echo json_encode(['success' => true]);
    exit; // Stop execution after clearing state
}


// --- Handle AJAX request to fetch questions (only if no state in session) ---
if (isset($_GET['fetch']) && $_GET['fetch'] === 'questions' && !isset($_SESSION['assessment_state']['questions'])) {
    header('Content-Type: application/json');

    // Get course from session
    $course = $_SESSION['mentor_signup_data']['expertise'] ?? '';

    if (!$course) {
        echo json_encode(['success' => false, 'error' => 'Course not specified in session.']);
        exit;
    }

    // Prepare and execute query to fetch 10 random questions for the selected course
    $stmt = $conn->prepare("SELECT ITEM_ID, Question, Choice1, Choice2, Choice3, Choice4, Correct_Answer FROM mentors_assessment WHERE Course_Title = ? ORDER BY RAND() LIMIT 10");

    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $course);

    if ($stmt->execute() === false) {
        echo json_encode(['success' => false, 'error' => 'Database execute error: ' . $stmt->error]);
        exit;
    }

    $result = $stmt->get_result();
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        // Store correct answer as part of the question data for JS review
        $questions[] = $row;
    }

    // Store fetched questions in session immediately
    $_SESSION['assessment_state'] = [
        'questions' => $questions,
        'answers' => array_fill(0, count($questions), null), // Initialize answers array
        'current' => 0 // Start at the first question
    ];


    echo json_encode(['success' => true, 'state' => $_SESSION['assessment_state']]);
    $stmt->close();
    $conn->close();
    exit; // Stop execution after fetching questions
}

// --- Handle the initial POST request from signup_mentor.php ---
// This block is only executed on the first arrival from signup
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_SESSION['mentor_signup_data'])) {

     // Basic validation for required fields from POST
     $required_fields = ['fname', 'lname', 'birthdate', 'gender', 'email', 'full-contact', 'username', 'password', 'confirm-password', 'mentored', 'expertise'];
     $missing_fields = [];
     foreach ($required_fields as $field) {
         if (empty($_POST[$field])) {
             $missing_fields[] = $field;
         }
     }

    // Handle file uploads validation separately as $_FILES check is needed
    if (empty($_FILES['resume']['name']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $missing_fields[] = 'resume';
    }
    if (empty($_FILES['certificates']['name'][0]) || $_FILES['certificates']['error'][0] !== UPLOAD_ERR_OK) {
         $missing_fields[] = 'certificates';
     }


     if (!empty($missing_fields)) {
         // Redirect back to signup with an error message
         $_SESSION['error_message'] = "Please fill out all required fields in the signup form.";
         // Optionally preserve some data in session to pre-fill the form
         $_SESSION['temp_signup_data'] = $_POST; // Be cautious with storing passwords directly
         header("Location: signup_mentor.php");
         exit();
     }

     // Check password match
     if ($_POST['password'] !== $_POST['confirm-password']) {
         $_SESSION['error_message'] = "Passwords do not match.";
          $_SESSION['temp_signup_data'] = $_POST;
          header("Location: signup_mentor.php");
          exit();
     }


    // Store signup data in session
    $_SESSION['mentor_signup_data'] = $_POST;

    // Handle file uploads immediately and store paths in session
     $resume_path = '';
     if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
         $resume_name = uniqid('resume_') . '_' . basename($_FILES['resume']['name']); // Add unique prefix
         $resume_tmp = $_FILES['resume']['tmp_name'];
         $upload_dir = "uploads/applications/resume/";
          if (!is_dir($upload_dir)) {
             mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
         }
         $resume_path = $upload_dir . $resume_name;
         if (move_uploaded_file($resume_tmp, $resume_path)) {
              $_SESSION['mentor_signup_data']['Resume'] = $resume_path;
         } else {
             error_log("Failed to move uploaded resume file.");
              $_SESSION['mentor_signup_data']['Resume'] = null; // Store null on failure
              $_SESSION['error_message'] = "Failed to upload resume file.";
              // Consider redirecting back or showing error differently
              header("Location: signup_mentor.php"); exit();
         }
     } else {
          $_SESSION['mentor_signup_data']['Resume'] = null; // Should not happen with required check above, but for safety
          if (empty($_FILES['resume']['name'])) { // Check if file was actually missing
               $_SESSION['error_message'] = "Resume file is required.";
               header("Location: signup_mentor.php"); exit();
          } else {
               $_SESSION['error_message'] = "Error uploading resume file: Code " . $_FILES['resume']['error'];
               header("Location: signup_mentor.php"); exit();
          }
     }


     $cert_paths = [];
     if (isset($_FILES['certificates']['tmp_name']) && is_array($_FILES['certificates']['tmp_name'])) {
         $upload_dir = "uploads/applications/certificates/";
          if (!is_dir($upload_dir)) {
             mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
         }
         $upload_success = true;
         foreach ($_FILES['certificates']['tmp_name'] as $key => $tmp_name) {
              if ($_FILES['certificates']['error'][$key] === UPLOAD_ERR_OK) {
                 $cert_name = uniqid('cert_') . '_' . basename($_FILES['certificates']['name'][$key]); // Add unique prefix
                 $cert_path = $upload_dir . $cert_name;
                 if (move_uploaded_file($tmp_name, $cert_path)) {
                     $cert_paths[] = $cert_path;
                 } else {
                      error_log("Failed to move uploaded certificate file: " . $_FILES['certificates']['name'][$key]);
                      $upload_success = false; // Mark failure
                 }
              } else if ($_FILES['certificates']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                  error_log("Certificate upload error for file " . $_FILES['certificates']['name'][$key] . ": " . $_FILES['certificates']['error'][$key]);
                   $upload_success = false; // Mark failure for other errors
              }
         }
         if (!$upload_success || empty($cert_paths)) {
              $_SESSION['error_message'] = "Failed to upload one or more certificate files, or no certificates were uploaded.";
              // Clean up already moved files if any failed? Depends on desired strictness.
              // For now, redirect back.
              header("Location: signup_mentor.php"); exit();
         }
     } else {
          // Should not happen with required check, but for safety
           $_SESSION['error_message'] = "Certificate files are required.";
           header("Location: signup_mentor.php"); exit();
     }
     $_SESSION['mentor_signup_data']['Certificates'] = implode(", ", $cert_paths);


    // Redirect to the same page but without POST data
    header("Location: mentor_assessment.php");
    exit();
}

// --- Handle the final submission from the assessment page (AJAX) ---
if (isset($_POST['final_submit']) && isset($_SESSION['mentor_signup_data']) && isset($_POST['assessment_score'])) {
    header('Content-Type: application/json'); // Respond with JSON for AJAX

    // Retrieve username and expertise for duplicate check
    $username = $_SESSION['mentor_signup_data']['username'] ?? '';
    $expertise = $_SESSION['mentor_signup_data']['expertise'] ?? '';

    if ($username && $expertise) {
        // UPDATED: Check for duplicates in the new 'users' table for user_type 'Mentor'
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND area_of_expertise = ? AND user_type = 'Mentor'");
         if ($check_stmt === false) {
             echo json_encode(['success' => false, 'error' => 'Database check prepare error: ' . $conn->error]);
             $conn->close();
             exit;
         }
        $check_stmt->bind_param("ss", $username, $expertise);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_row();
        $check_stmt->close();

        if ($check_result[0] > 0) {
            // Application already exists, prevent resubmission
            unset($_SESSION['mentor_signup_data']);
            unset($_SESSION['assessment_state']);
            echo json_encode(['success' => false, 'error' => 'An application for this course has already been submitted for this user.']);
            $conn->close();
            exit;
        }
    } else {
         echo json_encode(['success' => false, 'error' => 'User data not found in session for uniqueness check.']);
         $conn->close();
         exit;
    }


    // Retrieve data from session and POST
    $signup_data = $_SESSION['mentor_signup_data'];
    $assessment_score = $_POST['assessment_score']; // Score passed from JavaScript

    // Extract data from the stored signup_data
    $fname = $signup_data['fname'] ?? '';
    $lname = $signup_data['lname'] ?? '';
    $dob = $signup_data['birthdate'] ?? '';
    $gender = $signup_data['gender'] ?? '';
    $email = $signup_data['email'] ?? '';
    $contact = $signup_data['full-contact'] ?? '';
    $username = $signup_data['username'] ?? '';
    $password = $signup_data['password'] ?? '';
    $mentored_before = $signup_data['mentored'] ?? '';
    $experience = $signup_data['experience'] ?? '';
    $expertise = $signup_data['expertise'] ?? '';
    $resume_path = $signup_data['Resume'] ?? null;
    $certificates = $signup_data['Certificates'] ?? null;
    
    // NEW: Define the user_type and initial status for the new unified table structure
    $user_type = "Mentor";
    $status = "Under Review";


    // Hash the password before inserting
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // UPDATED: Insert into the new 'users' table
    $stmt = $conn->prepare("INSERT INTO users 
    (first_name, last_name, dob, gender, email, contact_number, username, password, user_type, mentored_before, mentoring_experience, area_of_expertise, resume, certificates, assessment_score, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
         echo json_encode(['success' => false, 'error' => 'Database insert prepare error: ' . $conn->error]);
         $conn->close();
         exit;
     }

    // UPDATED: Bind parameters for the new 'users' table insert statement
    $stmt->bind_param("ssssssssssssssss",
    $fname, $lname, $dob, $gender, $email, $contact,
    $username, $hashed_password, $user_type, $mentored_before,
    $experience, $expertise, $resume_path, $certificates, $assessment_score, $status);

    if ($stmt->execute()) {
        // Clear ALL session data related to this signup/assessment after successful insertion
        unset($_SESSION['mentor_signup_data']);
        unset($_SESSION['assessment_state']); // Clear assessment state

        echo json_encode(['success' => true, 'redirect' => 'signup_mentor_thankyou.php']);
        exit();
    } else {
         echo json_encode(['success' => false, 'error' => 'Database insert execute error: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit; // Stop further execution after handling submission
}


// --- Display the assessment page ---

// Check if signup data exists in session, if not, redirect to signup
// This prevents direct access to mentor_assessment.php without going through signup
if (!isset($_SESSION['mentor_signup_data'])) {
    // Optionally show an error message before redirecting
     $_SESSION['error_message'] = "Please complete the mentor signup form first.";
    header("Location: signup_mentor.php");
    exit();
}


// Check if assessment state exists in session to determine initial view and load data
$assessment_state = $_SESSION['assessment_state'] ?? null;
$initial_questions = json_encode($assessment_state['questions'] ?? []);
$initial_answers = json_encode($assessment_state['answers'] ?? []);
$initial_current = $assessment_state['current'] ?? 0;
$is_assessment_started = isset($assessment_state['questions']); // Flag to check if assessment is in progress


// Get mentor name and course from session for display
$selected_course = $_SESSION['mentor_signup_data']['expertise'] ?? 'Unknown Course';
$mentor_fname = $_SESSION['mentor_signup_data']['fname'] ?? 'Mentor';


?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="uploads/img/coachicon.svg" type="image/svg+xml">
    <title>Mentor Assessment</title>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
         background: linear-gradient(135deg, #4b2354, #8a5a96);
        padding: 20px;
        color: #333;
    }

    .container {
        max-width: 900px;
        margin: auto;
        background: #fff;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
        margin-top: 50px;
        margin-bottom:100px;
    }

    #welcome-message {
        margin-top: 10px;
         margin-bottom: 30px;
         font-size: 55px;
         color: var(--primary-color, #6a0dad);
         text-align: center;
         text-shadow: 1px 1px 2px #caa0ff;
    }

    .question-box {
        font-size: 1.25em;
        margin-bottom: 90px;
        font-weight: 500;
    }

    .option {
        background: #e5d8f5;
        padding: 12px 15px;
        margin: 10px 0;
        border-radius: 10px;
        transition: background 0.3s ease;
        cursor: pointer;
    }

    .option:hover {
        background-color: #d8c6f0;
    }

    .option input {
        margin-right: 10px;
    }

    .button-container {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }

    button {
        padding: 12px 24px;
        font-size: 1em;
         background: linear-gradient(135deg, #4b2354, #8a5a96);
        border: none;
        border-radius: 6px;
        color: white;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    button:hover {
        background-color: #8c6ac0;
    }

    #progressBar {
        height: 25px;
        background-color: #e0d5f2;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 25px;
    }

    #progressBarFill {
        height: 100%;
        width: 0;
        background-color: #a383d8;
        transition: width 0.4s ease;
    }

    #quiz, #result {
        display: none;
    }

    #welcome {
        display: block;
    }

    <?php if ($is_assessment_started): ?>
    #welcome {
        display: none;
    }

    #quiz {
        display: block;
    }
    <?php endif; ?>

    #score {
        font-size: 2.2em;
        font-weight: bold;
        text-align: center;
        margin-bottom: 20px;
        color: #5a3e85;
    }

    #resultChart {
        display: block;
        margin: 0 auto 30px auto;
        max-width: 280px;
    }

    #reviewSummary {
        margin-top: 30px;
        border-top: 2px solid #ccc;
        padding-top: 25px;
    }

    .review-item {
        background: #f9f4ff;
        border: 1px solid #d4c2ec;
        padding: 15px 20px;
        margin-bottom: 15px;
        border-radius: 10px;
    }

    .correct {
        color: #2e7d32;
        font-weight: 500;
    }

    .incorrect {
        color: #c62828;
        font-weight: 500;
    }

    #completion-message {
        text-align: center;
        margin-top: 25px;
        font-size: 1.2em;
        color: #444;
    }

    #submitApplicationBtn {
        display: block;
        width: 220px;
        margin: 30px auto;
        padding: 15px;
        background: linear-gradient(135deg, #4b2354, #8a5a96);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 1.2em;
        cursor: pointer;
        text-align: center;
        transition: background-color 0.3s ease;
    }

    #submitApplicationBtn:hover {
        background-color:#4b2354
    }

    #loading-message {
        display: none;
        text-align: center;
        color: #2196F3;
        font-size: 1em;
        margin-top: 20px;
    }
</style>

</head>
<body>
<div class="container">

<div id="welcome" style="
    background: linear-gradient(145deg, #f5f0fa, #ffffff);
    border: 1px solid #e0dff5;
    border-radius: 16px;
    padding: 40px 30px;
    max-width: 700px;
    margin: 50px auto;
    box-shadow: 0 8px 24px rgba(90, 62, 133, 0.15);
    transition: all 0.3s ease;
    animation: fadeIn 0.8s ease-in-out;
">

    <h2 id="welcome-message" style="
        font-size: 4em;
        font-weight: 700;
        margin-bottom: 20px;
        color: #5a3e85;
        text-align: center;
    ">
        Welcome, <?= htmlspecialchars($mentor_fname) ?>!
    </h2>

    <p style="text-align: center; font-size: 1.1em; color: #555; margin-bottom: 20px;">
        We're excited to have you as a mentor in 
        <strong style="color: #6b4fb3;"><?= htmlspecialchars($selected_course) ?></strong>.
        This assessment will help us tailor the mentorship journey by identifying your strengths in programming.
    </p>

    <p style="text-align: center; font-size: 1em; color: #666; margin-bottom: 15px;">
        The quiz consists of multiple-choice questions designed to evaluate your current knowledge. There is no time limit, so take your time and answer to the best of your ability.
    </p>

    <p style="text-align: center; font-size: 1em; color: #666; margin-bottom: 30px;">
        After completing the assessment, you'll receive a performance summary and suggestions for your mentor assignments.
    </p>

    <div style="text-align: center;">
        <button onclick="startQuiz()" style="
            padding: 14px 30px;
            background-color: #a383d8;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 12px rgba(163, 131, 216, 0.3);
        " 
        onmouseover="this.style.backgroundColor='#8c6ac0'; this.style.transform='scale(1.03)'"
        onmouseout="this.style.backgroundColor='#a383d8'; this.style.transform='scale(1)'">
            Start Assessment
        </button>
    </div>
</div>

<style>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>




    <div id="quiz">
        <div id="progressBar"><div id="progressBarFill"></div></div>
        <div id="questionBox"></div>
        <div class="button-container">
            <button onclick="prevQuestion()" id="prevBtn">Back</button>
            <button onclick="nextQuestion()" id="nextBtn">Next</button>
        </div>
    </div>

    <div id="result">
        <h2>Assessment Result</h2>
        <p id="score"></p>
        <canvas id="resultChart"></canvas>
        <div id="reviewSummary"></div>

        <div id="completion-message">
            <p>You have completed the assessment. You can now submit your application.</p>
        </div>
        <button id="submitApplicationBtn" onclick="submitApplication()">Submit Application</button>
         <div id="loading-message">Submitting your application...</div> </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Load initial state from PHP
let questions = <?= $initial_questions ?>;
let answers = <?= $initial_answers ?>;
let current = <?= $initial_current ?>;
let finalScore = 0; // Variable to store the final score percentage
const isAssessmentStarted = <?= $is_assessment_started ? 'true' : 'false' ?>; // Flag from PHP


document.addEventListener('DOMContentLoaded', function() {
    // If assessment was started, show the quiz immediately and load the current question
    if (isAssessmentStarted) {
        document.getElementById('welcome').style.display = 'none';
        document.getElementById('quiz').style.display = 'block';
        showQuestion(); // Load the current question
    }
});


function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// Function to save the current state to the session via AJAX
function saveAssessmentState() {
    const state = {
        questions: questions,
        answers: answers,
        current: current
    };

    const formData = new FormData();
    formData.append('action', 'save_state');
    formData.append('state', JSON.stringify(state)); // Stringify the state object

    // Use fetch to save the state asynchronously
    fetch('mentor_assessment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to save assessment state:', data.error);
        } else {
             // console.log('Assessment state saved.'); // Optional success logging
        }
    })
    .catch(error => {
        console.error('Error saving assessment state:', error);
    });
}


function startQuiz() {
    // Only fetch questions if they haven't been loaded into the session yet
    if (questions.length === 0) {
         fetch(`mentor_assessment.php?fetch=questions`)
             .then(res => res.json())
             .then(data => {
                 if (data.error || !data.success || !data.state || !data.state.questions || data.state.questions.length === 0) {
                     alert("Error fetching questions: " + (data.error || "No questions found for this course."));
                     // Optionally redirect back to signup or show an error state
                     return;
                 }
                 questions = data.state.questions;
                 answers = data.state.answers; // Load initial answers (all null)
                 current = data.state.current; // Load initial current question (0)

                 document.getElementById('welcome').style.display = 'none';
                 document.getElementById('quiz').style.display = 'block';
                 showQuestion(); // Load the first question
             })
             .catch(error => {
                  alert("An error occurred while starting the quiz.");
                  console.error('Fetch Error:', error);
                  // Optionally redirect back to signup or show an error state
              });
    } else {
        // If questions are already loaded (from session on page load), just show the quiz
        document.getElementById('welcome').style.display = 'none';
        document.getElementById('quiz').style.display = 'block';
        showQuestion(); // Show the current question based on loaded state
    }
}


function showQuestion() {
    if (questions.length === 0) {
         document.getElementById('questionBox').innerHTML = "<p>No questions available for this course.</p>";
         document.getElementById('prevBtn').style.display = 'none'; // Hide buttons if no questions
         document.getElementById('nextBtn').style.display = 'none';
         updateProgress(); // Still update progress bar (will show 0%)
         return;
    }

    const q = questions[current];
    let html = `<div class='question-box'>${current + 1}. ${escapeHtml(q.Question)}</div>`;
    ['Choice1', 'Choice2', 'Choice3', 'Choice4'].forEach(c => {
        const choiceText = escapeHtml(q[c]);
        // Ensure radio button value is the choice key (e.g., 'Choice1')
        html += `<div class='option'>
                    <label>
                        <input type='radio' name='choice' value='${c}' ${answers[current] === c ? 'checked' : ''}>
                        ${choiceText}
                    </label>
                 </div>`;
    });
    document.getElementById('questionBox').innerHTML = html;

    // Update button visibility
    document.getElementById('prevBtn').style.display = (current > 0) ? 'block' : 'none';
    document.getElementById('nextBtn').innerText = (current < questions.length - 1) ? 'Next' : 'Finish Assessment';


    updateProgress();

    // Add event listener to the newly created radio buttons to save the answer and state
    // Use event delegation on the questionBox for efficiency if many questions
    // Alternatively, re-add listeners after innerHTML update
    document.querySelectorAll('input[name="choice"]').forEach(radio => {
        radio.addEventListener('change', function() {
            answers[current] = this.value;
            saveAssessmentState(); // Save state whenever an answer changes
        });
    });
}


function nextQuestion() {
    if (answers[current] === null) {
        alert("Please select an answer.");
        return;
    }
    if (current < questions.length - 1) {
        current++;
        showQuestion();
        saveAssessmentState(); // Save state after moving to the next question
    } else {
        // Reached the end of the quiz
        showResult();
        // State will be cleared on final submission, no need to clear here immediately
        // unless you want to prevent re-reviewing results page and resubmitting
        // Clearing after showResult is added in the code.
    }
}


function prevQuestion() {
    if (current > 0) {
        current--;
        showQuestion();
        saveAssessmentState(); // Save state after moving to the previous question
    } else {
        alert("You are on the first question.");
    }
}


function updateProgress() {
    if (questions.length > 0) {
         document.getElementById('progressBarFill').style.width = ((current + 1) / questions.length * 100) + '%';
    } else {
         document.getElementById('progressBarFill').style.width = '0%';
    }
}

function showResult() {
    document.getElementById('quiz').style.display = 'none';
    document.getElementById('result').style.display = 'block';

    let score = 0;
    questions.forEach((q, i) => {
        const userAnswerKey = answers[i];
        // Check if the text corresponding to the user's chosen key matches the Correct_Answer text
        if (userAnswerKey !== null && q[userAnswerKey] === q.Correct_Answer) {
            score++;
        }
    });

    const percent = (questions.length > 0) ? Math.round((score / questions.length) * 100) : 0;
    finalScore = percent; // Store the final score

    let level = percent >= 80 ? "Advanced" : percent >= 50 ? "Intermediate" : "Beginner";
    document.getElementById('score').innerText = `Score: ${percent}% (${level})`;

    // Destroy previous chart instance if it exists
     const existingChart = Chart.getChart('resultChart');
     if (existingChart) {
         existingChart.destroy();
     }

    new Chart(document.getElementById('resultChart'), {
        type: 'doughnut',
        data: {
            labels: ['Correct', 'Incorrect'],
            datasets: [{
                data: [score, questions.length - score],
                backgroundColor: ['#7bc96f', '#e57373']
            }]
        }
    });

    generateReviewSummary();

    // At this point, the assessment is completed. Clear the assessment state
    // from the session so they cannot retake *this specific* assessment.
    // However, the signup data remains for the final submission.
    clearAssessmentState(); // Call a function to clear the assessment state

}


function generateReviewSummary() {
    const reviewDiv = document.getElementById('reviewSummary');
    reviewDiv.innerHTML = "<h3>Answer Review</h3>";
    if (questions.length === 0) {
        reviewDiv.innerHTML += "<p>No questions to review.</p>";
        return;
    }

    questions.forEach((q, i) => {
        const userAnswerKey = answers[i];
        const userAnswer = escapeHtml(q[userAnswerKey] || 'No Answer');
        const correctAnswer = escapeHtml(q.Correct_Answer);
        const questionText = escapeHtml(q.Question);
        // Determine if the stored answer key's corresponding text matches the correct answer text
        const isCorrect = userAnswerKey !== null && q[userAnswerKey] === q.Correct_Answer;
        const colorClass = isCorrect ? 'correct' : 'incorrect';

        reviewDiv.innerHTML += `
            <div class="review-item">
                <strong>Q${i + 1}:</strong> ${questionText}<br>
                <strong>Your Answer:</strong> <span class="${colorClass}">${userAnswer}</span><br>
                <strong>Correct Answer:</strong> ${correctAnswer}
            </div>
        `;
    });
}

// Function to clear assessment state from session via AJAX
function clearAssessmentState() {
     const formData = new FormData();
     formData.append('action', 'clear_state');

     fetch('mentor_assessment.php', {
         method: 'POST',
         body: formData
     })
     .then(response => response.json())
     .then(data => {
         if (!data.success) {
             console.error('Failed to clear assessment state:', data.error);
         } else {
              // console.log('Assessment state cleared from session.'); // Optional logging
         }
     })
     .catch(error => {
         console.error('Error clearing assessment state:', error);
     });
}


function submitApplication() {
     if (finalScore === null) {
          alert("Please complete the assessment first.");
          return;
     }

    // Show loading message and disable button
    document.getElementById('submitApplicationBtn').style.display = 'none';
     document.getElementById('loading-message').style.display = 'block';

    // Send the final score back to the server to be saved with mentor data
    const formData = new FormData();
    formData.append('final_submit', 'true'); // Indicate this is the final submission
    formData.append('assessment_score', finalScore); // Pass the calculated score

    fetch('mentor_assessment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // Assuming server responds with JSON
    .then(data => {
        if (data.success) {
            alert('Application submitted successfully!');
            window.location.href = data.redirect; // Redirect to thankyou.php
        } else {
            alert('Error submitting application: ' + data.error);
            console.error('Submission Error Details:', data.error);
            // Re-show the submit button and hide loading message on error
             document.getElementById('submitApplicationBtn').style.display = 'block';
             document.getElementById('loading-message').style.display = 'none';
        }
    })
    .catch(error => {
        alert('An error occurred during application submission.');
        console.error('Submission Fetch Error:', error);
         // Re-show the submit button and hide loading message on error
         document.getElementById('submitApplicationBtn').style.display = 'block';
         document.getElementById('loading-message').style.display = 'none';
    });
}


// If the assessment was already started (state loaded from session),
// we need to display the quiz immediately on page load.
// The DOMContentLoaded listener already handles this.
// We also need to ensure the progress bar is updated correctly on load if resuming.
if (isAssessmentStarted) {
     updateProgress();
} else {
    // If assessment is not started, show the welcome section initially
    document.getElementById('welcome').style.display = 'block';
}


</script>
</body>
</html>