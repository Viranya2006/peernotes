<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Log activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (PDOException $e) {
        error_log("Logout activity log error: " . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to home page
header('Location: index.php?logged_out=1');
exit();
?>
