<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid application ID']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get application details with student information
    $stmt = $conn->prepare("SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, 
                                   s.email, s.uli, s.contact_number, s.age, s.sex, s.civil_status,
                                   s.province, s.city, s.barangay,
                                   c.course_name, c.course_id as course_id_from_courses
                           FROM course_applications ca
                           INNER JOIN students s ON ca.student_id = s.id
                           LEFT JOIN courses c ON ca.course_id = c.course_id
                           WHERE ca.application_id = :id");
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        http_response_code(404);
        echo json_encode(['error' => 'Application not found']);
        exit;
    }
    
    echo json_encode($application);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>