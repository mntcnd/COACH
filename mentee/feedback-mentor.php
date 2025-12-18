<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    header("Location: login.php");
    exit();
}

// --- DATABASE CONNECTION ---
require '../connection/db_connection.php';

// --- FETCH DATA FROM PREVIOUS PAGE ---
$feedback_data = $_SESSION['feedback_data'] ?? null;

if (!$feedback_data) {
    header("Location: feedback.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mentor_reviews = $_POST['mentor_reviews'] ?? '';
    $mentor_star = $_POST['mentor_star'] ?? 0;

    if (empty($mentor_reviews) || $mentor_star == 0) {
        echo "<script>alert('Please select a star rating and provide a mentor review.');</script>";
    } else {
        $mentor_star_percentage = ($mentor_star / 5) * 100;

        // Retrieve session data from previous step
        $forum_id = $feedback_data['forum_id'] ?? null;
        $mentee_experience = $feedback_data['mentee_experience'] ?? '';
        $experience_star_value = isset($feedback_data['experience_star']) ? (int)$feedback_data['experience_star'] : 0;
        $experience_star_percentage = $feedback_data['experience_star_percentage'] ?? 0;

        // Variables to fill
        $session_title = null;
        $forum_course_title = null;
        $fetched_session_date = null;
        $forum_time_slot = null;
        $session_mentor = null;
        $mentee_name = null;

        $present_date = date('Y-m-d');

        // --- FETCH SESSION DETAILS FROM forum_chats ---
        $stmt = $conn->prepare("SELECT title, course_title, session_date, time_slot FROM forum_chats WHERE id = ?");
        $stmt->bind_param("i", $forum_id);
        $stmt->execute();
        $stmt->bind_result($session_title, $forum_course_title, $fetched_session_date, $forum_time_slot);
        $stmt->fetch();
        $stmt->close();

        if (empty($session_title)) {
            echo "<script>alert('Error: Session title not found for forum_id $forum_id');</script>";
            exit();
        }

        // --- FETCH MENTOR NAME USING forum_participants ---
        // forum_participants (id, forum_id, user_id, joined_at)
        // users (user_id, first_name, last_name, user_type)
        $stmt = $conn->prepare("
            SELECT u.first_name, u.last_name
            FROM forum_participants fp
            INNER JOIN users u ON fp.user_id = u.user_id
            WHERE fp.forum_id = ? AND u.user_type = 'Mentor'
            LIMIT 1
        ");
        $stmt->bind_param("i", $forum_id);
        $stmt->execute();
        $stmt->bind_result($mentor_first_name, $mentor_last_name);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found && !empty($mentor_first_name)) {
            $session_mentor = trim("$mentor_first_name $mentor_last_name");
        } else {
            $session_mentor = "Unknown Mentor";
        }

        // --- FETCH MENTEE NAME ---
        $loggedInUsername = $_SESSION['username'] ?? null;
        if ($loggedInUsername) {
            $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE username = ?");
            $stmt->bind_param("s", $loggedInUsername);
            $stmt->execute();
            $stmt->bind_result($mentee_first_name, $mentee_last_name);
            $stmt->fetch();
            $stmt->close();
            $mentee_name = trim("$mentee_first_name $mentee_last_name");
        } else {
            $mentee_name = "Unknown Mentee";
        }

        // --- INSERT FEEDBACK ---
        $stmt = $conn->prepare("
            INSERT INTO feedback (
                Session,
                Forum_ID,
                Session_Mentor,
                Mentee,
                Mentee_Experience,
                Experience_Star,
                Mentor_Reviews,
                Mentor_Star,
                Session_Date,
                Time_Slot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sisssissss",
            $session_title,
            $forum_id,
            $session_mentor,
            $mentee_name,
            $mentee_experience,
            $experience_star_value,
            $mentor_reviews,
            $mentor_star,
            $present_date,
            $forum_time_slot
        );

        if ($stmt->execute()) {
            echo '<script>alert("Feedback submitted successfully!"); window.location.href = "forum-chat.php";</script>';
            exit();
        } else {
            echo "<script>alert('Error submitting feedback: " . addslashes($stmt->error) . "');</script>";
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/feedback-mentor.css" />
  <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.7.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.7.0/dist/ionicons/ionicons.js"></script>
  <title>Feedback Form</title>
</head>
<body>
  <section class="feedback-section">
    <div class="feedback-card">
      <h2>Rate your Mentor</h2>
      <p>We value your learning journey! Please take a moment to rate your mentoring experience and share your feedback.</p>

      <form method="POST" action="" onsubmit="return validateMentorFeedbackForm()">
        <div class="stars" id="starContainer">
          <span class="star" data-value="1">&#9733;</span>
          <span class="star" data-value="2">&#9733;</span>
          <span class="star" data-value="3">&#9733;</span>
          <span class="star" data-value="4">&#9733;</span>
          <span class="star" data-value="5">&#9733;</span>
        </div>
        <input type="hidden" name="mentor_star" id="mentor_star" value="0">
        <textarea placeholder="Tell us about your experience!" name="mentor_reviews" id="mentor_reviews"></textarea>
        <button type="submit">Send</button>
      </form>
    </div>
  </section>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const stars = document.querySelectorAll('.star');
      const mentorStarInput = document.getElementById('mentor_star');
      let currentRating = 0;

      function updateStars(rating) {
        stars.forEach(star => {
          const value = parseInt(star.dataset.value);
          star.classList.toggle('filled', value <= rating);
        });
      }

      stars.forEach(star => {
        star.addEventListener('click', () => {
          currentRating = parseInt(star.dataset.value);
          updateStars(currentRating);
          mentorStarInput.value = currentRating;
        });
        star.addEventListener('mouseover', () => updateStars(parseInt(star.dataset.value)));
        star.addEventListener('mouseout', () => updateStars(currentRating));
      });
    });

    function validateMentorFeedbackForm() {
      const mentorStar = document.getElementById('mentor_star').value;
      const mentorReviews = document.getElementById('mentor_reviews').value.trim();

      if (mentorStar == 0) {
        alert("Please select a star rating for the mentor.");
        return false;
      }
      if (mentorReviews === '') {
        alert("Please provide a review for the mentor.");
        return false;
      }
      return true;
    }
  </script>
</body>
</html>
