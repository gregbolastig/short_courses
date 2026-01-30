<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$success_message = '';
$error_message = '';

// Handle course completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Update student status to completed
        $stmt = $conn->prepare("UPDATE students SET status = 'completed' WHERE id = :id AND status = 'approved'");
        $stmt->bindParam(':id', $student_id);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $success_message = 'Student course marked as completed successfully!';
        } else {
            $error_message = 'Failed to mark course as completed. Student may not be currently enrolled.';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Redirect back to the referring page
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'students/index.php';
if ($success_message) {
    header("Location: $redirect_url?success=" . urlencode($success_message));
} else {
    header("Location: $redirect_url?error=" . urlencode($error_message));
}
exit;
?>