<?php
// We do not need session_start() or database connection in this file,
// as all necessary data is passed via URL parameters for simplicity.

// Basic Security and Data Validation
if (!isset($_GET['tier']) || !isset($_GET['mentor_name']) || !isset($_GET['date'])) {
    http_response_code(400); // Bad Request
    die("Missing required certificate parameters.");
}

// 1. Sanitize and Define Variables
$tier_key = htmlspecialchars($_GET['tier']); // 'certified', 'advanced', 'elite'
$mentor_name = htmlspecialchars(urldecode($_GET['mentor_name'])); // The full mentor name
$awarded_date = htmlspecialchars($_GET['date']); // The date it was awarded (e.g., YYYY-MM-DD)

// 2. Map the Tier Key to Display Title and Image Path
$tier_titles = [
    'certified' => 'Certified Mentor',
    'advanced' => 'Advanced Mentor',
    'elite' => 'Elite Mentor'
];

// --- CRITICAL FIXES FOR IMAGE LOADING START ---

// 1. Construct the base URL (http or https + domain name)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

// 2. Determine the folder path relative to the website's root
$image_folder_path_from_root = "/uploads/img/";

// 3. Set the Absolute URL and ensure the correct .png file extension is used
$certificate_image_path = $base_url . $image_folder_path_from_root . "certificate_{$tier_key}.png"; 

// --- CRITICAL FIXES FOR IMAGE LOADING END ---

$tier_title = $tier_titles[$tier_key] ?? 'Achievement Certificate'; // Default in case of bad input

// 3. Simple Date Formatting for Display
// Format the date to something more readable like "October 12, 2025"
$display_date = date("F j, Y", strtotime($awarded_date));

// 4. Set Headers for Download Suggestion
$filename = str_replace(' ', '_', $tier_title) . '_' . str_replace(' ', '_', $mentor_name) . '.html';

header("Content-Type: text/html");
header('Content-Disposition: attachment; filename="' . $filename . '"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <title>COACH | <?php echo $tier_title; ?> Certificate</title>
    <style>
        /* CSS for the certificate layout */
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4; /* Light background for non-print view */
        }

        .certificate-container {
            position: relative;
            /* Adjust these dimensions to the aspect ratio of your PNG files (e.g., 1000px wide, 700px high for landscape) */
            width: 1000px; 
            height: 700px; 
            
            /* DYNAMIC BACKGROUND IMAGE: Now using the full, absolute URL */
            background-image: url('<?php echo $certificate_image_path; ?>');
            background-size: cover; /* Use 'cover' to ensure it fills the container */
            background-repeat: no-repeat;
            background-position: center;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            box-sizing: border-box;
            overflow: hidden;
        }

        /* Overlay Text Styling */
        .overlay-text {
            position: absolute;
            width: 100%;
            text-align: center;
            /* Use a common dark color for better contrast on the certificate */
            color: #000000ff; 
            line-height: 1.2;
        }

        .mentor-name {
            /* === YOU MUST ADJUST THESE VALUES (top/font-size) FOR EACH CERTIFICATE DESIGN === */
            top: 60%; /* Example: 40% down from the top */
            font-size: 48px; 
            font-weight: bold;
            font-family: Verdana, Geneva, Tahoma, sans-serif; /* Clean, highly legible sans-serif font */
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
        }

        .awarded-date {
            /* === YOU MUST ADJUST THESE VALUES (bottom/font-size) FOR EACH CERTIFICATE DESIGN === */
            bottom: 15%; /* Example: 15% up from the bottom */
            font-size: 20px;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            color: #555;
            margin-left: -30px;
        }

        /* Print-Specific Styles */
        @media print {
            body {
                background-color: #ffffff !important;
                /* Force landscape for most browsers */
                transform: rotate(-90deg) translate(-100vh, 0);
                transform-origin: top left;
                width: 100vh; 
                height: 100vw; 
                overflow: hidden; 
            }
            .certificate-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
                /* CRITICAL: Force the browser to print the background image */
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            /* Ensure text overlays print correctly without scroll issues */
            .overlay-text {
                 position: fixed; /* Use fixed for print layout stability */
                 color: #000; /* Force black text for contrast in print */
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        
        <div class="overlay-text mentor-name">
            <?php echo $mentor_name; ?>
        </div>
        

        
        <div class="overlay-text awarded-date">
            <strong><?php echo $display_date; ?></strong>
        </div>
    </div>
</body>
</html>