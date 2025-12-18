<?php
// Start the session
session_start();

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie (clears it from the client's browser)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Redirect to the login page (login.php is in the same directory)
header("Location: login.php");
exit();
?>