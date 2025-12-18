<?php
// signup_mentor.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start(); // Start session

require 'connection/db_connection.php';

// Check username availability (AJAX request)
if (isset($_POST['check_username'])) {
    $username = $_POST['check_username'];
    
    // Updated query to check the unified 'users' table.
    // Using a prepared statement for better security.
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode(['exists' => $row['count'] > 0]);
    
    $stmt->close();
    $conn->close(); // Close connection for AJAX request
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['check_username'])) {
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
    
    // FIXED: Certificate validation - Added isset and is_array checks to prevent "Trying to access array offset on int"
    if (!isset($_FILES['certificates']['name']) || 
        !is_array($_FILES['certificates']['name']) || 
        empty($_FILES['certificates']['name'][0]) || 
        $_FILES['certificates']['error'][0] !== UPLOAD_ERR_OK) {
        $missing_fields[] = 'certificates';
    }
    
    // FIXED: Credentials validation - Added isset and is_array checks to prevent "Trying to access array offset on int"
    if (!isset($_FILES['credentials']['name']) || 
        !is_array($_FILES['credentials']['name']) || 
        empty($_FILES['credentials']['name'][0]) || 
        $_FILES['credentials']['error'][0] !== UPLOAD_ERR_OK) {
        $missing_fields[] = 'credentials';
    }
    // END FIXES

    if (!empty($missing_fields)) {
        $_SESSION['error_message'] = "Please fill out all required fields: " . implode(', ', $missing_fields);
        // Preserve form data for re-population (except sensitive data)
        $_SESSION['form_data'] = array_diff_key($_POST, array_flip(['password', 'confirm-password']));
    } else {
        // Check password match
        if ($_POST['password'] !== $_POST['confirm-password']) {
            $_SESSION['error_message'] = "Passwords do not match.";
            $_SESSION['form_data'] = array_diff_key($_POST, array_flip(['password', 'confirm-password']));
        } else {
            // Check for duplicate username
            $username = $_POST['username'];
            // NOTE: $conn is assumed to be defined and connected here
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_row = $check_result->fetch_assoc();
            $check_stmt->close();

            if ($check_row['count'] > 0) {
                $_SESSION['error_message'] = "Username already exists. Please choose a different username.";
                $_SESSION['form_data'] = array_diff_key($_POST, array_flip(['password', 'confirm-password']));
            } else {
                // Process file uploads
                $upload_success = true;
                $resume_path = '';
                $cert_paths = [];
                $credential_paths = []; 
                $error_messages = [];

                // Handle resume upload
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                    $resume_name = uniqid('resume_') . '_' . basename($_FILES['resume']['name']);
                    $resume_tmp = $_FILES['resume']['tmp_name'];
                    $upload_dir = "uploads/applications/resume/";
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $resume_path = $upload_dir . $resume_name;
                    if (!move_uploaded_file($resume_tmp, $resume_path)) {
                        $upload_success = false;
                        $error_messages[] = "Failed to upload resume file.";
                    }
                } else {
                    // This block should theoretically not run if the validation above caught it, 
                    // but it acts as a secondary safety check.
                    $upload_success = false;
                    $error_messages[] = "Resume file is required.";
                }

                // Handle certificates upload
                if (isset($_FILES['certificates']['tmp_name']) && is_array($_FILES['certificates']['tmp_name'])) {
                    $upload_dir = "uploads/applications/certificates/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    foreach ($_FILES['certificates']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['certificates']['error'][$key] === UPLOAD_ERR_OK) {
                            $cert_name = uniqid('cert_') . '_' . basename($_FILES['certificates']['name'][$key]);
                            $cert_path = $upload_dir . $cert_name;
                            if (move_uploaded_file($tmp_name, $cert_path)) {
                                $cert_paths[] = $cert_path;
                            } else {
                                $upload_success = false;
                                $error_messages[] = "Failed to upload certificate file: " . $_FILES['certificates']['name'][$key];
                            }
                        } else if ($_FILES['certificates']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            $upload_success = false;
                            $error_messages[] = "Certificate upload error for file " . $_FILES['certificates']['name'][$key];
                        }
                    }
                    
                    if (empty($cert_paths)) {
                        $upload_success = false;
                        // This message is already covered by initial validation but left for consistency
                        $error_messages[] = "At least one certificate file is required.";
                    }
                } else if ($upload_success) { // Only set to false if it hasn't failed for other reasons
                    $upload_success = false;
                    $error_messages[] = "Certificate files are required.";
                }

                // Handle credentials upload (Portfolio and Credentials)
                if (isset($_FILES['credentials']['tmp_name']) && is_array($_FILES['credentials']['tmp_name'])) {
                    $upload_dir = "uploads/applications/credentials/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    foreach ($_FILES['credentials']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['credentials']['error'][$key] === UPLOAD_ERR_OK) {
                            $credential_name = uniqid('cred_') . '_' . basename($_FILES['credentials']['name'][$key]);
                            $credential_path = $upload_dir . $credential_name;
                            if (move_uploaded_file($tmp_name, $credential_path)) {
                                $credential_paths[] = $credential_path;
                            } else {
                                $upload_success = false;
                                $error_messages[] = "Failed to upload credential file: " . $_FILES['credentials']['name'][$key];
                            }
                        } else if ($_FILES['credentials']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            $upload_success = false;
                            $error_messages[] = "Credential upload error for file " . $_FILES['credentials']['name'][$key];
                        }
                    }
                    
                    if (empty($credential_paths)) {
                        $upload_success = false;
                        $error_messages[] = "At least one credential file is required.";
                    }
                } else if ($upload_success) { // Only set to false if it hasn't failed for other reasons
                    $upload_success = false;
                    $error_messages[] = "Credential files are required.";
                }

                if (!$upload_success) {
                    $_SESSION['error_message'] = implode(' ', $error_messages);
                    $_SESSION['form_data'] = array_diff_key($_POST, array_flip(['password', 'confirm-password']));
                } else {
                    // All validations passed, save to database
                    $fname = $_POST['fname'];
                    $lname = $_POST['lname'];
                    $dob = $_POST['birthdate'];
                    $gender = $_POST['gender'];
                    $email = $_POST['email'];
                    $contact = $_POST['full-contact'];
                    $username = $_POST['username'];
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $mentored_before = $_POST['mentored'];
                    $experience = $_POST['experience'] ?? '';
                    
                    // Process expertise - combine selected buttons and other expertise
                    $expertise_list = [];
                    if (!empty($_POST['expertise'])) {
                        $expertise_list[] = $_POST['expertise'];
                    }
                    // Add other expertise if provided
                    if (!empty($_POST['otherExpertise'])) {
                        $other_expertise = array_map('trim', explode(',', $_POST['otherExpertise']));
                        $other_expertise = array_filter($other_expertise); // Remove empty values
                        $expertise_list = array_merge($expertise_list, $other_expertise);
                    }
                    $final_expertise = implode(', ', $expertise_list);
                    
                    $certificates = implode(", ", $cert_paths);
                    $credentials_db = implode(", ", $credential_paths);
                    $user_type = "Mentor";
                    $status = "Under Review";

                    // Insert into database
                    $stmt = $conn->prepare("INSERT INTO users 
                        (first_name, last_name, dob, gender, email, contact_number, username, password, user_type, mentored_before, mentoring_experience, area_of_expertise, resume, certificates, credentials, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    if ($stmt === false) {
                        $_SESSION['error_message'] = "Database error occurred. Please try again.";
                        $_SESSION['form_data'] = array_diff_key($_POST, array_flip(['password', 'confirm-password']));
                    } else {
                        $stmt->bind_param("ssssssssssssssss",
                            $fname, $lname, $dob, $gender, $email, $contact,
                            $username, $password, $user_type, $mentored_before,
                            $experience, $final_expertise, $resume_path, $certificates, $credentials_db, $status);

                        if ($stmt->execute()) {
                            // Clear any error messages and form data
                            unset($_SESSION['error_message']);
                            unset($_SESSION['form_data']);
                            
                            // Set success message for thank you page
                            $_SESSION['success_message'] = "Your mentor application has been submitted successfully!";
                            $_SESSION['mentor_name'] = $fname . ' ' . $lname;
                            
                            $stmt->close();
                            $conn->close();
                            
                            // Redirect to thank you page
                            header("Location: signup_mentor_thankyou.php");
                            exit();
                        } else {
                            $_SESSION['error_message'] = "Failed to save your application. Please try again.";
                            $_SESSION['form_data'] = array_diff_key($_POST, array_flip(['password', 'confirm-password']));
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
    // Only close the connection if the script reaches here without an early redirect/exit
    $conn->close();
}

// Get form data for re-population if available
$form_data = $_SESSION['form_data'] ?? [];
$error_message = $_SESSION['error_message'] ?? '';

// Clear the session data after using it
unset($_SESSION['form_data']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mentor Sign Up</title>
    <link rel="stylesheet" href="css/signupstyle.css">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <style>
        .password-field-container {
            position: relative;
            width: 100%;
        }
        .password-popup {
            display: none; /* Hidden by default */
            position: absolute;
            right: 0;
            top: 100%; /* Position below the password field */
            margin-top: 5px;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 5px;
            padding: 15px;
            width: 100%; /* Full width of parent container */
            max-width: 300px;
            color: #fff;
            z-index: 100;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .password-popup p {
            margin-top: 0;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .password-popup ul {
            margin: 0;
            padding-left: 20px;
        }
        .password-popup li {
            margin-bottom: 5px;
            transition: color 0.3s;
        }
        .password-popup li.valid {
            color: #4CAF50;
        }
        .password-popup li.invalid {
            color: #f44336;
        }
        .valid-input {
            border: 1px solid #4CAF50 !important;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.5) !important;
        }
        .invalid-input {
            border: 1px solid #f44336 !important;
            box-shadow: 0 0 5px rgba(244, 67, 54, 0.5) !important;
        }
        .phone-input-container {
            position: relative;
            width: 100%;
        }
        .phone-input-container input[type="tel"] {
            padding-left: 45px; /* Make space for the prefix */
            width: 100%;
        }
        .phone-input-container::before {
            content: "+63";
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: bold;
            color: #333;
            pointer-events: none; /* Makes the pseudo-element unclickable */
        }
        .error-message {
            color: #f44336;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="page-content">
        <section class="welcomeb">
            <div class="welcome-container">
                <div class="welcome-box">
                    <h3 class="typing-gradient">
                       <span class="typed-text">Hi Mentor! Time to inspire.</span>
                    </h3>
                </div>
            </div>
        </section>

        <div class="container">
            <h1>SIGN UP</h1>
            <p>Join as a mentor and share your expertise.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <form action="signup_mentor.php" method="POST" enctype="multipart/form-data" id="signupForm">
                <div class="top-section">
                    <div class="form-box">
                        <h2>Personal Information</h2>
                        <label for="fname">First Name</label>
                        <input type="text" id="fname" name="fname" placeholder="First Name" value="<?= htmlspecialchars($form_data['fname'] ?? '') ?>" required>

                        <label for="lname">Last Name</label>
                        <input type="text" id="lname" name="lname" placeholder="Last Name" value="<?= htmlspecialchars($form_data['lname'] ?? '') ?>" disabled required>

                        <label for="birthdate">Date of Birth</label>
                        <input type="date" id="birthdate" name="birthdate" value="<?= htmlspecialchars($form_data['birthdate'] ?? '') ?>" disabled required>
                        <span id="dob-error" class="error-message"></span>

                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" disabled required>
                            <option value="" disabled <?= empty($form_data['gender']) ? 'selected' : '' ?>>Select Gender</option>
                            <option <?= ($form_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option <?= ($form_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option <?= ($form_data['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>

                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Email" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" disabled required>
                        <span id="email-error" class="error-message"></span>

                        <label for="contact">Contact Number</label>
                        <div class="phone-input-container">
                            <input type="tel" id="contact" name="contact" placeholder="90XXXXXXXXX" value="<?= htmlspecialchars($form_data['contact'] ?? '') ?>" disabled required pattern="9[0-9]{9}" title="Please enter a valid Philippine mobile number starting with 9 followed by 9 digits">
                            <span id="phone-error" class="error-message"></span>
                            <input type="hidden" id="full-contact" name="full-contact" value="<?= htmlspecialchars($form_data['full-contact'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-box">
                        <h2>Username and Password</h2>
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" disabled required>
                        <span id="username-error" class="error-message"></span>

                        <label for="password">Password</label>
                        <div class="password-field-container">
                            <input type="password" id="password" name="password" placeholder="Password" disabled required>
                            <div id="password-popup" class="password-popup">
                                <p>Password must:</p>
                                <ul>
                                    <li id="length-check">Be at least 8 characters long</li>
                                    <li id="uppercase-check">Include at least one uppercase letter</li>
                                    <li id="lowercase-check">Include at least one lowercase letter</li>
                                    <li id="number-check">Include at least one number</li>
                                    <li id="special-check">Include at least one special character (!@#$%^&*)</li>
                                </ul>
                            </div>
                        </div>
                        <span id="password-error" class="error-message"></span>

                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm Password" disabled required>
                        <span id="confirm-password-error" class="error-message"></span>
                    </div>
                </div>

                <div class="form-box full-width">
                    <h2>Mentoring Information</h2>

                  <label>Have you mentored or taught before?</label>
<div class="student-options">
    <div class="radio-option">
        <input type="radio" id="mentored-yes" name="mentored" value="yes" 
               <?= ($form_data['mentored'] ?? '') === 'yes' ? 'checked' : '' ?> disabled required>
        <label for="mentored-yes">Yes</label>
    </div>
    
    <div class="radio-option">
        <input type="radio" id="mentored-no" name="mentored" value="no" 
               <?= ($form_data['mentored'] ?? '') === 'no' ? 'checked' : '' ?> disabled>
        <label for="mentored-no">No</label>
    </div>
</div>

                    <label for="experience">If yes, please describe your experience</label>
                    <textarea id="experience" name="experience" rows="3" placeholder="Share your mentoring or teaching background..." disabled><?= htmlspecialchars($form_data['experience'] ?? '') ?></textarea>

                    <div class="form-group">
                        <label for="expertise" style="margin-bottom: 8px">Area of Expertise (What you can coach) <span class="required-asterisk">*</span></label>
                        
                        <input type="hidden" name="expertise" id="expertiseInput" value="<?= htmlspecialchars($form_data['expertise'] ?? '') ?>" required disabled>

                        <div class="categories-grid">
                            <div class="category-section" data-category="programming">
                                <div class="category-header">
                                    <span class="category-icon">💻</span>
                                    <span class="category-title">Programming Languages</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="JavaScript">JavaScript</div>
                                        <div class="tech-button" data-tech="Python">Python</div>
                                        <div class="tech-button" data-tech="Java">Java</div>
                                        <div class="tech-button" data-tech="C#">C#</div>
                                        <div class="tech-button" data-tech="PHP">PHP</div>
                                        <div class="tech-button" data-tech="Ruby">Ruby</div>
                                        <div class="tech-button" data-tech="Swift">Swift</div>
                                        <div class="tech-button" data-tech="Kotlin">Kotlin</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="web">
                                <div class="category-header">
                                    <span class="category-icon">🌐</span>
                                    <span class="category-title">Web Development</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="Frontend Development">Frontend Development</div>
                                        <div class="tech-button" data-tech="Backend Development">Backend Development</div>
                                        <div class="tech-button" data-tech="Responsive Design">Responsive Design</div>
                                        <div class="tech-button" data-tech="Web Accessibility">Web Accessibility</div>
                                        <div class="tech-button" data-tech="API Integration">API Integration</div>
                                        <div class="tech-button" data-tech="Web Security">Web Security</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="mobile">
                                <div class="category-header">
                                    <span class="category-icon">📱</span>
                                    <span class="category-title">Mobile Development</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="Cross-Platform Apps">Cross-Platform Apps</div>
                                        <div class="tech-button" data-tech="UI/UX for Mobile Applications">UI/UX for Mobile</div>
                                        <div class="tech-button" data-tech="Performance Optimization">Performance Optimization</div>
                                        <div class="tech-button" data-tech="Push Notifications">Push Notifications</div>
                                        <div class="tech-button" data-tech="App Deployment">App Deployment</div>
                                        <div class="tech-button" data-tech="Mobile Testing">Mobile Testing</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="database">
                                <div class="category-header">
                                    <span class="category-icon">🗃️</span>
                                    <span class="category-title">Databases</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="Database Design">Database Design</div>
                                        <div class="tech-button" data-tech="Data Modeling">Data Modeling</div>
                                        <div class="tech-button" data-tech="Query Optimization">Query Optimization</div>
                                        <div class="tech-button" data-tech="Stored Procedures">Stored Procedures</div>
                                        <div class="tech-button" data-tech="Backup & Recovery">Backup & Recovery</div>
                                        <div class="tech-button" data-tech="Database Security">Database Security</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="digital-animation">
                                <div class="category-header">
                                    <span class="category-icon">🎨</span>
                                    <span class="category-title">Digital Animation</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="Storyboarding">Storyboarding</div>
                                        <div class="tech-button" data-tech="Algorithm Thinking">Algorithm Thinking</div>
                                        <div class="tech-button" data-tech="Animation Production Workflow">Animation Production Workflow</div>
                                        <div class="tech-button" data-tech="Character Rigging">Character Rigging</div>
                                        <div class="tech-button" data-tech="3D Environment Design">3D Environment Design</div>
                                        <div class="tech-button" data-tech="UI/UX for Animation Tools">UI/UX for Animation Tools</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="game-dev">
                                <div class="category-header">
                                    <span class="category-icon">🎮</span>
                                    <span class="category-title">Game Development</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="2D Game Design">2D Game Design</div>
                                        <div class="tech-button" data-tech="3D Modelling">3D Modelling</div>
                                        <div class="tech-button" data-tech="Game Physics">Game Physics</div>
                                        <div class="tech-button" data-tech="Level Design">Level Design</div>
                                        <div class="tech-button" data-tech="Audio & Sound Effects">Audio & Sound Effects</div>
                                        <div class="tech-button" data-tech="Game Programming">Game Programming</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="data-science">
                                <div class="category-header">
                                    <span class="category-icon">📊</span>
                                    <span class="category-title">Data Science</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="Data Wrangling">Data Wrangling</div>
                                        <div class="tech-button" data-tech="Exploratory Data Analysis">EDA</div>
                                        <div class="tech-button" data-tech="Statistical Modeling">Statistical Modeling</div>
                                        <div class="tech-button" data-tech="Data Visualization">Data Visualization</div>
                                        <div class="tech-button" data-tech="Time Series Analysis">Time Series Analysis</div>
                                        <div class="tech-button" data-tech="A/B Version Testing">A/B Testing</div>
                                    </div>
                                </div>
                            </div>

                            <div class="category-section" data-category="artificial-intelligence">
                                <div class="category-header">
                                    <span class="category-icon">🤖</span>
                                    <span class="category-title">Artificial Intelligence (AI)</span>
                                    <span class="expand-icon">▼</span>
                                </div>
                                <div class="subcategories">
                                    <div class="subcategory-grid">
                                        <div class="tech-button" data-tech="Machine Learning">Machine Learning</div>
                                        <div class="tech-button" data-tech="Deep Learning">Deep Learning</div>
                                        <div class="tech-button" data-tech="Natural Language Processing">NLP</div>
                                        <div class="tech-button" data-tech="Computer Vision">Computer Vision</div>
                                        <div class="tech-button" data-tech="Reinforcement Learning">Reinforcement Learning</div>
                                        <div class="tech-button" data-tech="Generative AI">Generative AI</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="selected-interests">
                            <h4>Selected Expertise:</h4>
                            <div class="selected-tags" id="selectedTags">
                                <span class="no-selection">No expertise selected yet</span>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label for="otherExpertise">Other Areas of Expertise (Optional):</label>
                            <textarea id="otherExpertise" name="otherExpertise" rows="2" placeholder="Enter other skills, separated by commas (e.g., WordPress, Cloud Computing, Technical Writing)" class="form-control" disabled><?= htmlspecialchars($form_data['otherExpertise'] ?? '') ?></textarea>
                            <small class="form-text text-muted">Separate multiple skills with a comma.</small>
                        </div>
                    </div>

                <div class="file-upload-group">
    <label for="resume">Upload Resume</label>
    <div class="custom-file-input-container">
        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" disabled required>
        <label for="resume" class="custom-file-button">Choose File</label>
        <span class="file-name" id="resume-file-name">No file chosen</span>
    </div>
</div>

<div class="file-upload-group">
    <label for="certificates">Upload Certificates</label>
    <div class="custom-file-input-container">
        <input type="file" id="certificates" name="certificates[]" accept=".pdf,.jpg,.png,.doc,.docx" disabled required>
        <label for="certificates" class="custom-file-button">Choose Files</label>
        <span class="file-name" id="certificates-file-name">No file chosen</span>
    </div>
</div>

<div class="file-upload-group">
    <label for="credentials">Upload Portfolio and Credentials</label>
    <div class="custom-file-input-container">
        <input type="file" id="credentials" name="credentials[]" accept=".pdf,.jpg,.png,.doc,.docx" disabled required>
        <label for="credentials" class="custom-file-button">Choose Files</label>
        <span class="file-name" id="credentials-file-name">No file chosen</span>
    </div>
</div>

                <div class="terms-container">
    <label>
        <input type="checkbox" id="terms" required>
        <span>
            I agree to the <a href="termscondition.php">Terms & Conditions</a> and <a href="dataprivacy.php">Data Privacy Policy</a>.
        </span>
    </label>
    <label>
        <input type="checkbox" id="consent" required>
        <span>
            I consent to receive updates and communications from COACH, trusting that all shared information will be used responsibly to support my growth and development. I understand that COACH values my privacy and that I can opt out of communications at any time.
        </span>
    </label>
</div>

                <div class="form-buttons">
                    <button type="button" class="cancel-btn"><a href="login.php" style="color: #290c26;">Cancel</a></button>
                    <button type="submit" class="submit-btn">Submit Application</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM elements for DOB validation
        const dobInput = document.getElementById('birthdate');
        const dobError = document.getElementById('dob-error');

        // DOM elements for password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const passwordError = document.getElementById('password-error');
        const confirmPasswordError = document.getElementById('confirm-password-error');
        const passwordPopup = document.getElementById('password-popup');

        // DOM elements for username validation
        const usernameInput = document.getElementById('username');
        const usernameError = document.getElementById('username-error');

        // DOM elements for phone validation
        const contactInput = document.getElementById('contact');
        const fullContactInput = document.getElementById('full-contact');
        const phoneError = document.getElementById('phone-error');

        // Password requirement checkers
        const lengthCheck = document.getElementById('length-check');
        const uppercaseCheck = document.getElementById('uppercase-check');
        const lowercaseCheck = document.getElementById('lowercase-check');
        const numberCheck = document.getElementById('number-check');
        const specialCheck = document.getElementById('special-check');

        const form = document.getElementById('signupForm');

        // Make sure the popup is hidden initially
        passwordPopup.style.display = 'none';

        // Username validation
        let usernameTimer;
        let isUsernameValid = false;

        function checkUsername() {
            const username = usernameInput.value.trim();

            if (username.length === 0) {
                usernameError.textContent = "Username is required.";
                usernameError.style.color = "#f44336";
                usernameInput.classList.remove('valid-input');
                usernameInput.classList.add('invalid-input');
                isUsernameValid = false;
                return;
            }

            if (username.length < 3) {
                usernameError.textContent = "Username must be at least 3 characters.";
                usernameError.style.color = "#f44336";
                usernameInput.classList.remove('valid-input');
                usernameInput.classList.add('invalid-input');
                isUsernameValid = false;
                return;
            }

            // Show loading indicator
            usernameError.textContent = "Checking username...";
            usernameError.style.color = "#2196F3";
            usernameInput.classList.remove('valid-input', 'invalid-input');

            // Create form data
            const formData = new FormData();
            formData.append('check_username', username);

            // Send AJAX request to check username availability
            fetch('signup_mentor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.exists) {
                    usernameError.textContent = "This username is already taken.";
                    usernameError.style.color = "#f44336";
                    usernameInput.classList.remove('valid-input');
                    usernameInput.classList.add('invalid-input');
                    isUsernameValid = false;
                } else {
                    usernameError.textContent = "Username is available!";
                    usernameError.style.color = "#4CAF50";
                    usernameInput.classList.remove('invalid-input');
                    usernameInput.classList.add('valid-input');
                    isUsernameValid = true;
                }
            })
            .catch(error => {
                usernameError.textContent = "Error checking username. Please try again.";
                usernameError.style.color = "#f44336";
                console.error('Error:', error);
                usernameInput.classList.remove('valid-input');
                usernameInput.classList.add('invalid-input');
                isUsernameValid = false;
            });
        }

        usernameInput.addEventListener('input', function() {
            clearTimeout(usernameTimer);
            if (this.value.trim().length > 0) {
                usernameTimer = setTimeout(checkUsername, 500);
            } else {
                checkUsername();
            }
        });
        
        usernameInput.addEventListener('blur', checkUsername);

        // Calculate dates for DOB validation
        const today = new Date();
        const minAllowedYear = today.getFullYear() - 100;
        const maxAllowedYear = today.getFullYear() - 18;

        const maxDate = new Date(maxAllowedYear, 11, 31).toISOString().split('T')[0];
        const minDate = new Date(minAllowedYear, 0, 1).toISOString().split('T')[0];

        dobInput.setAttribute('max', maxDate);
        dobInput.setAttribute('min', minDate);

        // Date of birth validation function
        function validateDOB() {
            if (!dobInput.value) {
                dobError.textContent = "Date of birth is required.";
                return false;
            }
            const selectedDate = new Date(dobInput.value);
            const today = new Date();
            const selectedYear = selectedDate.getFullYear();
            const currentYear = today.getFullYear();

            if (selectedDate > today) {
                dobError.textContent = "Date of birth cannot be in the future.";
                return false;
            } else if (selectedYear > currentYear - 18) {
                dobError.textContent = "You must be at least 18 years old to register as a mentor.";
                return false;
            } else {
                dobError.textContent = "";
                return true;
            }
        }

        function validatePassword() {
            const password = passwordInput.value;
            let isValid = true;

            const rules = [
                { test: password.length >= 8, element: lengthCheck },
                { test: /[A-Z]/.test(password), element: uppercaseCheck },
                { test: /[a-z]/.test(password), element: lowercaseCheck },
                { test: /[0-9]/.test(password), element: numberCheck },
                { test: /[!@#$%^&*]/.test(password), element: specialCheck }
            ];

            rules.forEach(rule => {
                if (rule.test) {
                    rule.element.className = 'valid';
                } else {
                    rule.element.className = 'invalid';
                    isValid = false;
                }
            });

            if (!isValid && password.length > 0) {
                passwordError.textContent = "Please meet all password requirements.";
                passwordInput.classList.remove('valid-input');
                passwordInput.classList.add('invalid-input');
            } else {
                passwordError.textContent = "";
                if (password.length > 0 && isValid) {
                    passwordInput.classList.remove('invalid-input');
                    passwordInput.classList.add('valid-input');
                } else {
                    passwordInput.classList.remove('valid-input', 'invalid-input');
                }
            }
            return isValid;
        }

        function validateConfirmPassword() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length === 0 && password.length > 0) {
                confirmPasswordError.textContent = "Please confirm your password.";
                confirmPasswordInput.classList.remove('valid-input');
                confirmPasswordInput.classList.add('invalid-input');
                return false;
            } else if (confirmPassword.length > 0 && password !== confirmPassword) {
                confirmPasswordError.textContent = "Passwords do not match.";
                confirmPasswordInput.classList.remove('valid-input');
                confirmPasswordInput.classList.add('invalid-input');
                return false;
            } else {
                confirmPasswordError.textContent = "";
                if (confirmPassword.length > 0 && password === confirmPassword) {
                    confirmPasswordInput.classList.remove('invalid-input');
                    confirmPasswordInput.classList.add('valid-input');
                } else {
                    confirmPasswordInput.classList.remove('valid-input', 'invalid-input');
                }
                return true;
            }
        }

        // Password popup events
        passwordInput.addEventListener('focus', function() {
            passwordPopup.style.display = 'block';
        });

        passwordInput.addEventListener('input', validatePassword);

        passwordInput.addEventListener('blur', function() {
            passwordPopup.style.display = 'none';
            validatePassword();
        });

        dobInput.addEventListener('change', validateDOB);
        dobInput.addEventListener('blur', validateDOB);

        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        confirmPasswordInput.addEventListener('blur', validateConfirmPassword);

        // Phone number validation
        contactInput.addEventListener('input', function(e) {
            let inputValue = e.target.value;
            inputValue = inputValue.replace(/[^0-9]/g, '');

            if (inputValue.length > 0 && !inputValue.startsWith('9')) {
                inputValue = '9' + inputValue.substring(1);
            }

            if (inputValue.length > 10) {
                inputValue = inputValue.slice(0, 10);
            }

            e.target.value = inputValue;

            if (inputValue.length === 10) {
                fullContactInput.value = '+63' + inputValue;
            } else {
                fullContactInput.value = '';
            }

            validatePhoneNumber(inputValue);
        });

        contactInput.addEventListener('blur', function() {
            validatePhoneNumber(this.value);
        });

        function validatePhoneNumber(number) {
            const isValid = number.length === 10 && number.startsWith('9');

            if (number.length === 0) {
                phoneError.textContent = 'Phone number is required.';
                contactInput.classList.remove('valid-input');
                contactInput.classList.add('invalid-input');
                return false;
            } else if (!isValid) {
                phoneError.textContent = 'Must be a valid 10-digit Philippine mobile number (starts with 9).';
                contactInput.classList.remove('valid-input');
                contactInput.classList.add('invalid-input');
                return false;
            } else {
                phoneError.textContent = '';
                contactInput.classList.remove('invalid-input');
                contactInput.classList.add('valid-input');
                return true;
            }
        }

        // Field progression logic - FIXED ORDER
        const fieldOrder = [
            'fname',
            'lname',
            'birthdate',
            'gender',
            'email',
            'contact',
            'username',
            'password',
            'confirm-password',
            'mentored-yes',
            'experience',
            'expertise-selection',
            'resume',
            'certificates',
            'credentials',
            'terms',
            'consent'
        ];

        function isExpertiseSelected() {
            const hiddenInput = document.getElementById('expertiseInput');
            const otherExpertise = document.getElementById('otherExpertise');
            
            const hasButtonSelection = hiddenInput.value.trim() !== '';
            const hasOtherInput = otherExpertise.value.trim() !== '';
            
            return hasButtonSelection || hasOtherInput;
        }

        function checkExpertiseAndEnableNextFields() {
            if (isExpertiseSelected()) {
                const resumeField = document.getElementById('resume');
                if (resumeField) resumeField.disabled = false;
                
                if (resumeField && resumeField.files.length > 0) {
                    const certificatesField = document.getElementById('certificates');
                    if (certificatesField) certificatesField.disabled = false;
                    
                    if (certificatesField.files.length > 0) {
                        const termsField = document.getElementById('terms');
                        if (termsField) termsField.disabled = false;
                        
                        if (termsField.checked) {
                            const consentField = document.getElementById('consent');
                            if (consentField) consentField.disabled = false;
                            
                            if (consentField.checked) {
                                const submitButton = document.querySelector('.submit-btn');
                                if (submitButton) submitButton.disabled = false;
                            }
                        }
                    }
                }
            } else {
                const fieldsToDisable = ['resume', 'certificates'];
                fieldsToDisable.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.disabled = true;
                        field.value = '';
                    }
                });
                
                const checkboxesToDisable = ['terms', 'consent'];
                checkboxesToDisable.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.disabled = true;
                        field.checked = false;
                    }
                });
                
                const submitButton = document.querySelector('.submit-btn');
                if (submitButton) submitButton.disabled = true;
            }
        }

        function enableNextField(currentElementId) {
            const currentIndex = fieldOrder.indexOf(currentElementId);
            if (currentIndex === -1) return;

            const nextFieldId = fieldOrder[currentIndex + 1];
            if (!nextFieldId) return;

            if (nextFieldId === 'expertise-selection') {
                const expertiseInput = document.getElementById('expertiseInput');
                const otherExpertiseInput = document.getElementById('otherExpertise');
                if (expertiseInput) expertiseInput.disabled = false;
                if (otherExpertiseInput) otherExpertiseInput.disabled = false;
                
                const techButtons = document.querySelectorAll('.tech-button');
                techButtons.forEach(button => button.style.pointerEvents = 'auto');
                return;
            }

            if (nextFieldId === 'mentored-yes') {
                document.getElementsByName('mentored').forEach(el => el.disabled = false);
                return;
            }
            
            if (nextFieldId === 'terms') {
                const termsCheckbox = document.getElementById('terms');
                if (termsCheckbox) termsCheckbox.disabled = false;
                return;
            }
            
            if (nextFieldId === 'consent') {
                const termsCheckbox = document.getElementById('terms');
                if (termsCheckbox && termsCheckbox.checked) {
                    const consentCheckbox = document.getElementById('consent');
                    if (consentCheckbox) consentCheckbox.disabled = false;
                }
                return;
            }

            const nextField = document.getElementById(nextFieldId);
            if (nextField) {
                nextField.disabled = false;
            }
        }

        function disableSubsequentFields(currentElementId) {
            const currentIndex = fieldOrder.indexOf(currentElementId);
            if (currentIndex === -1) return;

            for (let i = currentIndex + 1; i < fieldOrder.length; i++) {
                const fieldId = fieldOrder[i];
                
                if (fieldId === 'expertise-selection') {
                    const expertiseInput = document.getElementById('expertiseInput');
                    const otherExpertiseInput = document.getElementById('otherExpertise');
                    if (expertiseInput) {
                        expertiseInput.disabled = true;
                        expertiseInput.value = '';
                    }
                    if (otherExpertiseInput) {
                        otherExpertiseInput.disabled = true;
                        otherExpertiseInput.value = '';
                    }
                    
                    const techButtons = document.querySelectorAll('.tech-button');
                    techButtons.forEach(button => {
                        button.style.pointerEvents = 'none';
                        button.classList.remove('selected');
                    });
                    
                    selectedExpertise = [];
                    updateSelectedDisplay();
                    continue;
                }
                
                const dependentField = document.getElementById(fieldId);
                if (dependentField) {
                    if (dependentField.type === 'radio') {
                        document.getElementsByName(dependentField.name).forEach(el => {
                            el.disabled = true;
                            el.checked = false;
                        });
                    } else if (dependentField.type === 'checkbox') {
                        dependentField.disabled = true;
                        dependentField.checked = false;
                        if (dependentField.id === 'consent') {
                            const submitButton = form.querySelector('.submit-btn');
                            if (submitButton) submitButton.disabled = true;
                        }
                    } else {
                        dependentField.disabled = true;
                        dependentField.value = '';
                    }
                }
            }
        }

        // Add event listeners for field progression
        fieldOrder.forEach((id) => {
            if (id === 'expertise-selection') return;
            
            const currentField = document.getElementById(id);
            if (!currentField) return;

            if (currentField.type === 'radio') {
                document.getElementsByName(currentField.name).forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (this.checked) {
                            enableNextField(currentField.id);
                            
                            if (currentField.name === 'mentored') {
                                const experienceField = document.getElementById('experience');
                                
                                if (this.value === 'yes') {
                                    if (experienceField) experienceField.disabled = false;
                                } else if (this.value === 'no') {
                                    if (experienceField) {
                                        experienceField.disabled = true;
                                        experienceField.value = '';
                                    }
                                }
                                enableNextField('experience');
                            }
                        }
                    });
                });
            }
            else if (currentField.type === 'file') {
                currentField.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        enableNextField(currentField.id);
                    } else {
                        disableSubsequentFields(currentField.id);
                    }
                });
            }
            else if (currentField.type === 'checkbox') {
                currentField.addEventListener('change', function() {
                    if (this.checked) {
                        enableNextField(currentField.id);
                        
                        if (currentField.id === 'consent') {
                            const termsField = document.getElementById('terms');
                            if (termsField && termsField.checked) {
                                const submitButton = document.querySelector('.submit-btn');
                                if (submitButton) submitButton.disabled = false;
                            }
                        }
                    } else {
                        disableSubsequentFields(currentField.id);
                    }
                });
            }
            else {
                currentField.addEventListener('input', function() {
                    if (this.value.trim() !== '' && currentField.checkValidity()) {
                        enableNextField(currentField.id);
                        
                        if (currentField.id === 'contact') {
                            const fullContact = document.getElementById('full-contact');
                            if (fullContact.value.trim() !== '' && validatePhoneNumber(this.value.trim())) {
                                const usernameField = document.getElementById('username');
                                if (usernameField) usernameField.disabled = false;
                            }
                        }
                    } else {
                        disableSubsequentFields(currentField.id);
                    }
                });
                
                currentField.addEventListener('blur', function() {
                    if (this.value.trim() !== '' && currentField.checkValidity()) {
                        enableNextField(currentField.id);
                    } else {
                        disableSubsequentFields(currentField.id);
                    }
                });
            }
        });

        // Initialize - disable all fields except the first
        fieldOrder.forEach((id) => {
            if (id === 'expertise-selection') {
                const expertiseInput = document.getElementById('expertiseInput');
                const otherExpertiseInput = document.getElementById('otherExpertise');
                if (expertiseInput) expertiseInput.disabled = true;
                if (otherExpertiseInput) otherExpertiseInput.disabled = true;
                
                const techButtons = document.querySelectorAll('.tech-button');
                techButtons.forEach(button => button.style.pointerEvents = 'none');
                return;
            }
            
            const field = document.getElementById(id);
            if (!field) return;

            if (id !== 'fname') {
                if (field.type === 'radio') {
                    document.getElementsByName(field.name).forEach(el => el.disabled = true);
                } else if (field.type === 'checkbox') {
                    field.disabled = true;
                } else {
                    field.disabled = true;
                }
            }
        });
        
        const submitButton = form.querySelector('.submit-btn');
        if (submitButton) {
            submitButton.disabled = true;
        }

        // Form submission validation
        form.addEventListener('submit', function(event) {
            let formIsValid = true;

            form.querySelectorAll('[required]:not(:disabled)').forEach(input => {
                if (!input.value.trim()) {
                    formIsValid = false;
                    input.classList.add('invalid-input');
                } else {
                    input.classList.remove('invalid-input');
                }
            });

            if (!validateDOB()) formIsValid = false;
            if (!validatePassword()) formIsValid = false;
            if (!validateConfirmPassword()) formIsValid = false;
            if (!validatePhoneNumber(contactInput.value.trim())) formIsValid = false;
            if (!isUsernameValid) {
                usernameInput.classList.add('invalid-input');
                formIsValid = false;
            } else {
                usernameInput.classList.remove('invalid-input');
            }

            if (!isExpertiseSelected()) {
                alert('Please select at least one Area of Expertise or enter a custom one in the "Other" field.');
                formIsValid = false;
            }

            const termsChecked = document.getElementById('terms').checked;
            const consentChecked = document.getElementById('consent').checked;
            if (!termsChecked || !consentChecked) {
                alert("Please agree to the Terms & Conditions and Data Privacy Policy, and provide consent.");
                formIsValid = false;
            }

            if (!formIsValid) {
                event.preventDefault();
                alert('Please fill out all required fields correctly.');
            }
        });

        // Mentor Expertise Selection Script
        let selectedExpertise = [];
        const hiddenInput = document.getElementById('expertiseInput');
        const selectedTagsContainer = document.getElementById('selectedTags');
        const categories = document.querySelectorAll('.category-section');
        const techButtons = document.querySelectorAll('.tech-button');
        const otherExpertiseInput = document.getElementById('otherExpertise');

        // Restore selected expertise from form data if available
        const existingExpertise = hiddenInput.value;
        if (existingExpertise) {
            const expertiseList = existingExpertise.split(', ').map(item => item.trim()).filter(item => item.length > 0);
            selectedExpertise = expertiseList.filter(item => {
                const button = document.querySelector(`.tech-button[data-tech="${item}"]`);
                if (button) {
                    button.classList.add('selected');
                    return true;
                }
                return false;
            });
        }

        // Category Toggle Logic
        categories.forEach(category => {
            const header = category.querySelector('.category-header');
            header.addEventListener('click', () => {
                category.classList.toggle('expanded');
            });
        });

        // Tech Button Selection Logic
        techButtons.forEach(button => {
            button.addEventListener('click', function() {
                const expertiseInput = document.getElementById('expertiseInput');
                if (expertiseInput && expertiseInput.disabled) return;
                
                const tech = this.getAttribute('data-tech');
                const isSelected = this.classList.contains('selected');

                if (isSelected) {
                    selectedExpertise = selectedExpertise.filter(item => item !== tech);
                    this.classList.remove('selected');
                } else {
                    selectedExpertise.push(tech);
                    this.classList.add('selected');
                }

                updateSelectedDisplay();
                updateHiddenInput();
                checkExpertiseAndEnableNextFields();
            });
        });

        // Other Expertise Input Logic
        otherExpertiseInput.addEventListener('input', function() {
            updateSelectedDisplay();
            updateHiddenInput();
            checkExpertiseAndEnableNextFields();
        });

        function updateSelectedDisplay() {
            const otherText = otherExpertiseInput.value.trim();
            let finalTagsHTML = '';
            
            const expertiseTagsHTML = selectedExpertise.map(tag => 
                `<div class="selected-tag" data-tech="${tag}">
                    ${tag}
                    <span class="remove-tag" data-tech="${tag}">×</span> 
                </div>`
            ).join('');
            
            finalTagsHTML += expertiseTagsHTML;

            if (otherText) {
                const othersList = otherText.split(',').map(item => item.trim()).filter(item => item.length > 0);
                
                const otherTagsHTML = othersList.map(tag => 
                    `<div class="selected-tag" data-tech="${tag}">
                        ${tag}
                    </div>`
                ).join('');
                
                finalTagsHTML += otherTagsHTML;
            }

            if (!finalTagsHTML) {
                selectedTagsContainer.innerHTML = '<span class="no-selection">No expertise selected yet</span>';
                hiddenInput.value = '';
                return;
            }

            selectedTagsContainer.innerHTML = finalTagsHTML;
            attachRemoveTagListeners();
        }

        function attachRemoveTagListeners() {
            const removeTags = selectedTagsContainer.querySelectorAll('.remove-tag');
            removeTags.forEach(removeTag => {
                removeTag.addEventListener('click', function() {
                    const tech = this.getAttribute('data-tech');
                    
                    selectedExpertise = selectedExpertise.filter(interest => interest !== tech);
                    
                    const techButton = document.querySelector(`.tech-button[data-tech="${tech}"]`);
                    if (techButton) {
                        techButton.classList.remove('selected');
                    }
                    
                    updateSelectedDisplay();
                    updateHiddenInput();
                    checkExpertiseAndEnableNextFields();
                });
            });
        }

        function updateHiddenInput() {
            let allExpertise = [...selectedExpertise];
            const otherText = otherExpertiseInput.value.trim();
            
            if (otherText) {
                const othersList = otherText.split(',').map(item => item.trim()).filter(item => item.length > 0);
                allExpertise.push(...othersList);
            }

            hiddenInput.value = allExpertise.join(', ');
        }

        // Initialize expertise display
        updateSelectedDisplay();
        updateHiddenInput();
    });

    document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const fileNameSpanId = this.id + '-file-name';
            const fileNameSpan = document.getElementById(fileNameSpanId);

            if (this.files && this.files.length > 0) {
                if (this.multiple) {
                    // For multiple files, show the count
                    fileNameSpan.textContent = this.files.length + ' files selected';
                } else {
                    // For single file, show the name
                    fileNameSpan.textContent = this.files[0].name;
                }
            } else {
                // If the user cancels the selection
                fileNameSpan.textContent = 'No file chosen';
            }
        });
    });
});
    </script>
</body>
</html>