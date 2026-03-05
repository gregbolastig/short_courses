<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as bookkeeping
if (!isset($_SESSION['bookkeeping_logged_in']) || $_SESSION['role'] !== 'bookkeeping') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID required']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $courses = [];
    
    // First, check if student_enrollments table exists and has data
    try {
        $query = "SELECT 
                    se.enrollment_id,
                    se.student_id,
                    se.nc_level,
                    se.training_start,
                    se.training_end,
                    se.enrollment_status,
                    se.completion_status,
                    c.course_name,
                    c.course_code,
                    a.adviser_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    br.receipt_number
                  FROM student_enrollments se
                  INNER JOIN shortcourse_courses c ON se.course_id = c.course_id
                  LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
                  INNER JOIN shortcourse_students s ON se.student_id = s.id
                  LEFT JOIN shortcourse_bookkeeping_receipts br ON se.enrollment_id = br.enrollment_id
                  WHERE se.student_id = :student_id
                  ORDER BY se.enrolled_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // student_enrollments table might not exist, continue to check legacy data
    }
    
    // If no enrollments found, check course_applications table
    if (empty($courses)) {
        try {
            $query = "SELECT 
                        ca.application_id as enrollment_id,
                        ca.student_id,
                        ca.nc_level,
                        ca.training_start,
                        ca.training_end,
                        ca.status as enrollment_status,
                        ca.status as completion_status,
                        c.course_name,
                        c.course_code,
                        ca.adviser as adviser_name,
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        br.receipt_number
                      FROM shortcourse_course_applications ca
                      INNER JOIN shortcourse_courses c ON ca.course_id = c.course_id
                      INNER JOIN shortcourse_students s ON ca.student_id = s.id
                      LEFT JOIN shortcourse_bookkeeping_receipts br ON ca.application_id = br.enrollment_id
                      WHERE ca.student_id = :student_id
                      AND ca.status IN ('approved', 'completed')
                      ORDER BY ca.applied_at DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // course_applications table might not exist, continue to check legacy data
        }
    }
    
    // If still no courses found, check legacy course field in students table
    if (empty($courses)) {
        $query = "SELECT 
                    s.id as enrollment_id,
                    s.id as student_id,
                    s.nc_level,
                    s.training_start,
                    s.training_end,
                    s.status as enrollment_status,
                    s.status as completion_status,
                    s.course as course_name,
                    '' as course_code,
                    s.adviser as adviser_name,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    br.receipt_number
                  FROM shortcourse_students s
                  LEFT JOIN shortcourse_bookkeeping_receipts br ON s.id = br.enrollment_id
                  WHERE s.id = :student_id
                  AND s.course IS NOT NULL
                  AND s.course != ''";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $legacyCourse = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($legacyCourse) {
            $courses = [$legacyCourse];
        }
    }
    
    echo json_encode($courses);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
