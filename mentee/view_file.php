<?php
// view_file.php — secure file preview page
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Restrict access to mentees (optional)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'Mentee') {
    header("Location: ../login.php");
    exit();
}

$filePath = isset($_GET['file']) ? urldecode($_GET['file']) : '';
if (!$filePath || !file_exists($filePath)) {
    $error = "The requested file could not be found.";
}

// Detect file type for preview
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$viewableTypes = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'mp4', 'webm', 'txt', 'ppt', 'pptx', 'doc', 'docx'];
$isViewable = in_array($extension, $viewableTypes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>View File</title>
<link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
:root {
    --primary-purple: #6a2c70;
    --primary-hover: #9724b0ff;
    --secondary-purple: #91489bff;
    --secondary-hover: #60225dff;
    --text-color: #424242;
    --light-bg: #fdfdff;
    --container-bg: #fff;
    --border-color: #E1BEE7;
}
body {
    margin: 0;
    padding: 0;
    background: var(--light-bg);
    font-family: "Poppins", sans-serif;
    color: var(--text-color);
    min-height: 100vh;
}
.viewer-wrapper {
    max-width: 900px;
    margin: 40px auto;
    padding: 30px;
    background: var(--container-bg);
    border-radius: 14px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.08);
}
.viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 15px;
    margin-bottom: 20px;
}
.viewer-header h1 {
    margin: 0;
    color: var(--primary-purple);
    font-size: 1.8rem;
    font-weight: 700;
}
.viewer-header a.btn-secondary {
    background: var(--secondary-purple);
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.15s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.viewer-header a.btn-secondary:hover {
    background: var(--secondary-hover);
    transform: translateY(-1px);
}
.file-viewer {
    text-align: center;
    padding: 20px;
}
.file-viewer iframe, 
.file-viewer video, 
.file-viewer img {
    width: 100%;
    border: 1px solid var(--border-color);
    border-radius: 10px;
}
.file-viewer p {
    color: var(--text-color);
    font-size: 1.1rem;
}
.download-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
    background: var(--primary-purple);
    color: white;
    padding: 12px 22px;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.2s;
    font-weight: 600;
}
.download-link:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
}
@media (max-width: 760px) {
    .viewer-wrapper {
        margin: 10px;
        padding: 20px;
    }
    .viewer-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .viewer-header a.btn-secondary {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>
<div class="viewer-wrapper">
    <div class="viewer-header">
        <h1><i class='bx bx-file'></i> File Viewer</h1>
        <a href="javascript:window.close()" class="btn-secondary"><i class='bx bx-arrow-back'></i> Close</a>
    </div>

    <div class="file-viewer">
        <?php if (isset($error)): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php elseif ($isViewable): ?>
            <?php if (in_array($extension, ['pdf'])): ?>
                <iframe src="<?= htmlspecialchars($filePath) ?>" height="600"></iframe>
            <?php elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'gif'])): ?>
                <img src="<?= htmlspecialchars($filePath) ?>" alt="Attached File Preview">
            <?php elseif (in_array($extension, ['mp4', 'webm'])): ?>
                <video controls>
                    <source src="<?= htmlspecialchars($filePath) ?>" type="video/<?= htmlspecialchars($extension) ?>">
                    Your browser does not support video playback.
                </video>
            <?php elseif (in_array($extension, ['txt'])): ?>
                <iframe src="<?= htmlspecialchars($filePath) ?>" height="600"></iframe>
            <?php elseif (in_array($extension, ['ppt', 'pptx', 'doc', 'docx'])): ?>
                <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($filePath, '/')) ?>" height="600"></iframe>
            <?php else: ?>
                <p>Preview not supported. You can download the file below.</p>
            <?php endif; ?>
            <div>
                <a class="download-link" href="<?= htmlspecialchars($filePath) ?>" download>
                    <i class='bx bx-download'></i> Download File
                </a>
            </div>
        <?php else: ?>
            <p>Preview not supported. You can download the file below.</p>
            <a class="download-link" href="<?= htmlspecialchars($filePath) ?>" download>
                <i class='bx bx-download'></i> Download File
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
