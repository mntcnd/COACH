<?php
header("Content-Type: text/html; charset=UTF-8");
session_start();

// --- ACCESS CONTROL ---
// Check if the user is logged in and if their user_type is 'Mentee'
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    // If not a Mentee, redirect to the login page.
    header("Location: ../login.php");
    exit();
}

// --- DATABASE CONNECTION & FETCH USER ACCOUNT ---
require '../connection/db_connection.php';

$query = isset($_POST['query']) ? trim($_POST['query']) : "";

// Query to fetch resources with 'Approved' status
if ($query === "") {
  // Select all approved resources if the search query is empty
  $stmt = $conn->prepare("SELECT * FROM resources WHERE Status = 'Approved'");
} else {
  // Select approved resources that match the search query
  $stmt = $conn->prepare("SELECT * FROM resources WHERE (Resource_Title LIKE CONCAT('%', ?, '%') OR Resource_Type LIKE CONCAT('%', ?, '%')) AND Status = 'Approved'");
  $stmt->bind_param("ss", $query, $query);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  while ($resource = $result->fetch_assoc()) {
    echo '<div class="course-card">';
    if (!empty($resource['Resource_Icon'])) {
      echo '<img src="../uploads/' . htmlspecialchars($resource['Resource_Icon']) . '" alt="Resource Icon">';
    }
    echo '<h2>' . htmlspecialchars($resource['Resource_Title']) . '</h2>';
    echo '<p><strong>' . htmlspecialchars($resource['Resource_Type']) . '</strong></p>';
    $filePath = $resource['Resource_File'];
    $fileTitle = $resource['Resource_Title'];

    // This link now points to the view_resource.php inside the /mentor/ folder
    $viewUrl = '../mentor/view_resource_mentee.php?file=' . urlencode($filePath) . '&title=' . urlencode($fileTitle);

    echo '<a href="' . htmlspecialchars($viewUrl) . '" class="view-btn" target="_blank">View</a>';
    echo '</div>';
  }
} else {
  echo '<p>No results found.</p>';
}

$stmt->close();
$conn->close();
?>