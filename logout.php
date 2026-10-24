<?php
include 'includes/auth.php'; // To ensure session_start() runs

// 1. Clear all session variables
$_SESSION = array();

// 2. Kill the session cookie itself
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session on the server
session_destroy();

// 4. Redirect to login
header("Location: login.php");
exit;