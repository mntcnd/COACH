<?php
// Get file and title from URL parameters, provide defaults
$file = $_GET['file'] ?? '';
$title = $_GET['title'] ?? 'Resource Viewer';

// --- Security: Prevent directory traversal ---
$fileName = basename($file);
if (empty($fileName) || $fileName === '.' || $fileName === '..') {
    die("❌ Invalid file parameter.");
}

// --- Build file path ---
$uploadDir = '../uploads/';
$filepath = $uploadDir . $fileName;
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

// --- Check file existence ---
if (!file_exists($filepath)) {
    error_log("File not found: " . $filepath);
    die("❌ Resource file not found.");
}

// --- Construct full URL for Office viewer ---
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = '/'; // Adjust this if your project is in a subdirectory
$webFilePath = $basePath . $uploadDir . $fileName;
$fullUrl = $scheme . "://" . $host . $webFilePath;

// Detect if server is local/private
$isLocalOrPrivate = (
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
   <link rel="icon" href="../uploads/img/coachicon.svg" type="image/svg+xml">
  <title><?php echo htmlspecialchars($title); ?> | View Resource</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 20px;
        background: linear-gradient(135deg, #4b2354, #8a5a96);
      color: #333;
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
      border: 1px solid #ccc;
      background-color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .viewer-frame iframe,
    .viewer-frame video {
      width: 100%;
      height: 600px;
      border: none;
    }
    .viewer-frame img {
      max-width: 100%;
      height: auto;
      display: block;
      margin: auto;
    }
    .viewer-actions {
      text-align: center;
      margin-top: 20px;
    }
    .btn {
      padding: 12px 20px;
      font-size: 16px;
      margin: 5px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      background-color: #7c3aed;
      color: white;
      transition: background-color 0.3s ease;
      text-decoration: none;
    }
    .btn:hover {
      background-color: #5b21b6;
    }
    .btn.back-btn {
      background-color: #6b7280;
    }
    .btn.back-btn:hover {
      background-color: #4b5563;
    }
    .info-message {
      text-align: center;
      font-style: italic;
      color: #555;
      margin: 15px;
    }
    .error-message {
      text-align: center;
      font-weight: bold;
      color: #d9534f;
    }
  </style>
</head>
<body>
  <div class="viewer-container">
    <h1 class="viewer-title"><?php echo htmlspecialchars($title); ?></h1>

    <div class="viewer-frame">
      <?php if ($ext === "pdf"): ?>
        <iframe src="<?php echo htmlspecialchars($filepath); ?>" title="PDF Viewer"></iframe>
      <?php elseif (in_array($ext, ["mp4", "webm", "ogg", "mov"])): ?>
        <video controls preload="metadata">
          <source src="<?php echo htmlspecialchars($filepath); ?>" type="video/<?php echo $ext; ?>">
          Your browser does not support the video tag.
        </video>
      <?php elseif (in_array($ext, ["ppt", "pptx", "doc", "docx", "xls", "xlsx"])): ?>
        <?php if ($isLocalOrPrivate): ?>
          <p class="info-message">⚠️ Office preview is not available on local servers. Please download the file.</p>
        <?php else:
          $viewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . urlencode($fullUrl);
        ?>
          <iframe src="<?php echo htmlspecialchars($viewerUrl); ?>" title="Office Viewer"></iframe>
        <?php endif; ?>
      <?php elseif (in_array($ext, ["jpg", "jpeg", "png", "gif", "bmp", "webp"])): ?>
        <img src="<?php echo htmlspecialchars($filepath); ?>" alt="<?php echo htmlspecialchars($title); ?>">
      <?php else: ?>
        <p class="info-message">ℹ️ Preview not available for this file type.</p>
      <?php endif; ?>
    </div>

    <div class="viewer-actions">
      <a href="<?php echo htmlspecialchars($filepath); ?>" download class="btn">⬇ Download File</a>
      <?php if (in_array($ext, ["pdf", "mp4", "webm", "ogg", "mov", "ppt", "pptx", "doc", "docx", "xls", "xlsx"]) && !$isLocalOrPrivate): ?>
        <button onclick="toggleFullScreen()" class="btn">⛶ Full Screen</button>
      <?php endif; ?>
      <button onclick="window.location.href='../mentee/resource_library.php';" class="btn back-btn">← Back</button>
    </div>
  </div>

  <script>
    function toggleFullScreen() {
      const el = document.querySelector(".viewer-frame iframe, .viewer-frame video");
      if (!el) return;

      if (!document.fullscreenElement) {
        if (el.requestFullscreen) {
          el.requestFullscreen();
        } else if (el.webkitRequestFullscreen) {
          el.webkitRequestFullscreen();
        } else if (el.msRequestFullscreen) {
          el.msRequestFullscreen();
        } else if (el.mozRequestFullScreen) {
          el.mozRequestFullScreen();
        }
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
          document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
          document.msExitFullscreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        }
      }
    }
  </script>
</body>
</html>
