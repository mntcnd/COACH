<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // If not a Mentee, redirect to the login page.
    header("Location: login.php");
    exit();
}

// --- FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

$forum_id = $_GET['forum_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the form submission
    $mentee_experience = $_POST['mentee_experience'] ?? '';
    $experience_star = $_POST['experience_star'] ?? 0; // This will be the star value (1-5)

    // Basic server-side validation (although client-side is added below)
    if (empty($mentee_experience) || $experience_star == 0) {
        // Handle validation error if client-side is bypassed
        // You might want to show an error message to the user here
        echo "<script>alert('Please select a star rating and provide feedback.');</script>";
    } else {
        // Calculate the percentage
        $experience_star_percentage = ($experience_star / 5) * 100;

        // Store the data in session to pass to feedbackmentor.php
        $_SESSION['feedback_data'] = [
            'forum_id' => $forum_id,
            'mentee_experience' => $mentee_experience,
            'experience_star' => $experience_star, // Store the raw star value too if needed
            'experience_star_percentage' => $experience_star_percentage,
        ];

        // Redirect to feedbackmentor.php
        header("Location: feedback-mentor.php");
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="css/feedback.css" />
 <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <title>Feedback Form</title>
</head>

<body>

    <section class="feedback-section">
        <div class="feedback-card">
          <h2>Rate your experience with COACH</h2>
          <p>
            We highly value your feedback! Kindly take a moment to rate your experience
            and provide us with your valuable feedback.
          </p>

          <form method="POST" action="" onsubmit="return validateFeedbackForm()">
            <div class="stars" id="starContainer">
              <span class="star" data-value="1">&#9733;</span>
              <span class="star" data-value="2">&#9733;</span>
              <span class="star" data-value="3">&#9733;</span>
              <span class="star" data-value="4">&#9733;</span>
              <span class="star" data-value="5">&#9733;</span>
            </div>
            <input type="hidden" name="experience_star" id="experience_star" value="0">

            <textarea placeholder="Tell us about your experience!" name="mentee_experience" id="mentee_experience"></textarea>

            <button type="submit">Next</button>
          </form>
        </div>
      </section>

      <script>
        document.addEventListener('DOMContentLoaded', () => {
          const stars = document.querySelectorAll('.star');
          const experienceStarInput = document.getElementById('experience_star');
          let currentRating = 0;

          function updateStars(rating) {
            stars.forEach(star => {
              const value = parseInt(star.getAttribute('data-value'));
              if (value <= rating) {
                star.classList.add('filled');
              } else {
                star.classList.remove('filled');
              }
            });
          }

          stars.forEach(star => {
            star.addEventListener('click', () => {
              currentRating = parseInt(star.getAttribute('data-value'));
              updateStars(currentRating);
              experienceStarInput.value = currentRating; // Set the hidden input value
            });

            star.addEventListener('mouseover', () => {
              updateStars(parseInt(star.getAttribute('data-value')));
            });

            star.addEventListener('mouseout', () => {
              updateStars(currentRating);
            });
          });
        });

        // --- Validation Function ---
        function validateFeedbackForm() {
            const experienceStar = document.getElementById('experience_star').value;
            const menteeExperience = document.getElementById('mentee_experience').value.trim(); // .trim() removes leading/trailing whitespace

            if (experienceStar == 0) {
                alert("Please select a star rating.");
                return false; // Prevent form submission
            }

            if (menteeExperience === '') {
                alert("Please provide feedback in the text area.");
                return false; // Prevent form submission
            }

            return true; // Allow form submission
        }
        // --- End Validation Function ---

      </script>
    </body>
    </html>