<?php
if (!isset($_GET['file']) || !isset($_GET['type'])) {
    echo "Missing file parameter.";
    exit;
}

$file = basename($_GET['file']); // Sanitize file name
$type = $_GET['type'];

$baseUploadPath = "../uploads/"; // Use the base uploads folder
// If type is 'resume', use applications/resume/. 
// ELSE (If type is 'certificates', use applications/certificates/. 
// ELSE (If type is 'credentials', use credentials/))
$folder = ($type === "resume") ? $baseUploadPath . "applications/resume/" : 
          (($type === "certificate") ? $baseUploadPath . "applications/certificates/" : 
          $baseUploadPath . "applications/credentials/"); // <-- CHANGED HERE: Added 'applications/'
$filePath = $folder . $file;
// Debug: Add this temporarily to see what paths are being used
// echo "Looking for file at: " . $filePath . "<br>";
// echo "File exists: " . (file_exists($filePath) ? "Yes" : "No") . "<br>";

// If 'download' is set, force download the file
if (isset($_GET['download'])) {
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        flush();
        readfile($filePath);
        exit;
    } else {
        echo "<p class='error-message'>File not found at: " . htmlspecialchars($filePath) . "</p>";
        exit;
    }
}

// Check if file exists before trying to display it
$fileExists = file_exists($filePath);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
   <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
    <title>Viewing <?= ucfirst($type) ?></title>
    <style>
        body {
            background: linear-gradient(135deg, #4b2354, #8a5a96);
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }

        .viewer-container {
            max-width: 1300px;
            margin: 20px auto;
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .viewer-title {
            text-align: center;
            font-size: 32px;
            margin-bottom: 25px;
            color: #5e2ca5;
            font-weight: bold;
            text-shadow: 1px 1px 2px #d6d6e0;
        }

        .viewer-frame {
            width: 100%;
            min-height: 600px;
            border: 1px solid #d1c4e9;
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #f9f9f9;
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }

        .viewer-frame iframe {
            display: block;
            width: 100%;
            height: 600px;
            border: none;
            position: absolute;
            top: 0;
            left: 0;
        }

        .viewer-frame video {
            display: block;
            width: 100%;
            height: 100%;
            border: none;
            position: absolute;
            top: 0;
            left: 0;
        }

        .viewer-frame img {
            position: static;
            display: block;
            height: auto;
            max-height: 800px;
            width: auto;
            max-width: 100%;
            margin: auto;
        }

        .error-message {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #d32f2f;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
        }

        .viewer-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .viewer-actions .btn {
            background-color: #6a44a2;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.1s ease;
            font-size: 0.95em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .viewer-actions .btn:hover {
            background-color: #55317e;
            transform: translateY(-1px);
        }

        .viewer-actions .btn:active {
            transform: translateY(0px);
        }

        .viewer-actions .back-btn {
            background-color: rgb(120, 13, 128);
        }

        .viewer-actions .back-btn:hover {
            background-color: rgb(75, 5, 88);
        }

        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .btn:disabled:hover {
            background-color: #ccc;
            transform: none;
        }
    </style>
</head>
<body>

<div class="viewer-container">
    <div class="viewer-title">Viewing <?= ucfirst($type) ?></div>

    <div class="viewer-frame">
        <?php if ($fileExists): ?>
            <?php
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if ($fileExtension === 'pdf'):
            ?>
                <iframe src="<?= htmlspecialchars($filePath) ?>"></iframe>
            <?php elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                <img src="<?= htmlspecialchars($filePath) ?>" alt="<?= ucfirst($type) ?> image">
            <?php elseif (in_array($fileExtension, ['doc', 'docx'])): ?>
                <div style="text-align: center; padding: 20px;">
                    <p>Word document cannot be displayed inline. Please download to view.</p>
                    <a href="view_application.php?file=<?php echo urlencode($file); ?>&type=<?php echo $type; ?>&download=1" class="btn">Download Document</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 20px;">
                    <p>File type not supported for inline viewing. Please download to view.</p>
                    <a href="view_application.php?file=<?php echo urlencode($file); ?>&type=<?php echo $type; ?>&download=1" class="btn">Download File</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="error-message">
                File not found: <?= htmlspecialchars($file) ?><br>
                Looking in: <?= htmlspecialchars($filePath) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="viewer-actions">
        <a href="manage_mentors.php" class="btn back-btn">Back</a>
        <?php if ($fileExists): ?>
            <a href="view_application.php?file=<?php echo urlencode($file); ?>&type=<?php echo $type; ?>&download=1" class="btn">Download</a>
        <?php else: ?>
            <button class="btn" disabled>Download (File Not Found)</button>
        <?php endif; ?>
    </div>
</div>

</body>
</html>