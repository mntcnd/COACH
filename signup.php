<?php
// Connect to your database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "coach";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process AJAX request for username check
if (isset($_POST['check_username'])) {
    $username = $_POST['check_username'];
    
    // Prepare statement to prevent SQL injection
    // Changed "users" to "mentee_profiles" to match your actual table
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM mentee_profiles WHERE Username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Return result as JSON
    header('Content-Type: application/json');
    echo json_encode(['exists' => ($row['count'] > 0)]);
    exit;
}

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $firstName = trim($_POST['fname']);
    $lastName = trim($_POST['lname']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm-password'];
    $email = trim($_POST['email']);
    $contactNumber = $_POST['full-contact'];
    $fullAddress = trim($_POST['address']);
    $student = isset($_POST['student']) ? $_POST['student'] : '';
    $studentYearLevel = trim($_POST['grade']);
    $occupation = trim($_POST['occupation']);
    $toLearn = trim($_POST['learning']);
    $terms = isset($_POST['terms']) ? 1 : 0;
    $consent = isset($_POST['consent']) ? 1 : 0;

    // Validate passwords match
    if ($password !== $confirmPassword) {
        echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
        exit();
    }

    // Optional: Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert into database
    $sql = "INSERT INTO mentee_profiles (First_Name, Last_Name, DOB, Gender, Username, Password, Email, Contact_Number, Full_Address, Student, Student_YearLevel, Occupation, ToLearn) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssss", $firstName, $lastName, $dob, $gender, $username, $hashedPassword, $email, $contactNumber, $fullAddress, $student, $studentYearLevel, $occupation, $toLearn);

    if ($stmt->execute()) {
        echo "<script>
            if (confirm('Registration successful! Do you want to go to the login page now?')) {
                window.location.href = 'login_mentee.php';
            }
        </script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
