<?php
// Authentication middleware
function requireAuth($required_role = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }
    
    // All authenticated users are admins now
    if ($required_role && $_SESSION['role'] !== $required_role) {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth('admin');
}

function redirectIfLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}
?>