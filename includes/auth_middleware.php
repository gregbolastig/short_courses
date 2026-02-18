<?php
// Authentication middleware
function requireAuth($required_role = null) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Clear any partial session data
        session_unset();
        session_destroy();
        session_start();
        header('Location: ../auth/login.php');
        exit;
    }
    
    // Check role if specified
    if ($required_role && $_SESSION['role'] !== $required_role) {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth('admin');
}

function redirectIfLoggedIn() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}
?>