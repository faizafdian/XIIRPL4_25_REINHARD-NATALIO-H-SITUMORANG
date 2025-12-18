<?php
require_once 'config/config.php';

if (isLoggedIn() && isset($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        logActivity($pdo, $_SESSION['user_id'], null, 'logout', 'User logged out');
    } catch (Exception $e) {
        // Continue with logout even if logging fails
        error_log("Logout activity log error: " . $e->getMessage());
    }
}

session_destroy();
header('Location: ' . BASE_URL . 'index.php');
exit;
?>

