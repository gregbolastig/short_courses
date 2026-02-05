<?php
session_start();

// Log logout activity before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    require_once '../config/database.php';
    require_once '../includes/system_activity_logger.php';
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $logger = new SystemActivityLogger($conn);
        $logger->logAdminLogout($_SESSION['user_id'], $_SESSION['username']);
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log("Logout logging error: " . $e->getMessage());
    }
}

session_destroy();
header('Location: login.php');
exit;
?>