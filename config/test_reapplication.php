<?php
require_once 'database.php';

echo "Testing Course Reapplication Logic...\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Test scenario: Student with rejected course application
    echo "Scenario: Student wants to reapply for a previously rejected course\n";
    echo "=================================================================\n\n";
    
    // Check if we have any students with rejected applications
    $stmt = $conn->query("
        SELECT s.id, s.first_name, s.last_name, s.uli, s.status,
               c.course_name, ca.status as app_status, ca.applied_at, ca.reviewed_at
        FROM students s 
        JOIN course_applications ca ON s.id = ca.student_id 
        JOIN courses c ON ca.course_id = c.course_id 
        WHERE ca.status = 'rejected' 
        LIMIT 1
    ");
    
    $rejected_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rejected_student) {
        echo "Found student with rejected application:\n";
        echo "  - Name: {$rejected_student['first_name']} {$rejected_student['last_name']}\n";
        echo "  - ULI: {$rejected_student['uli']}\n";
        echo "  - Student Status: {$rejected_student['status']}\n";
        echo "  - Rejected Course: {$rejected_student['course_name']}\n";
        echo "  - Applied: {$rejected_student['applied_at']}\n";
        echo "  - Rejected: {$rejected_student['reviewed_at']}\n\n";
        
        // Check what would prevent this student from reapplying
        $student_id = $rejected_student['id'];
        
        // Check pending applications
        $stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM course_applications WHERE student_id = :student_id AND status = 'pending'");
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
        
        // Check approved applications
        $approved_count = 0;
        if ($rejected_student['status'] === 'approved') {
            $stmt = $conn->prepare("SELECT COUNT(*) as approved_count FROM course_applications WHERE student_id = :student_id AND status = 'approved'");
            $stmt->bindParam(':student_id', $student_id);
            $stmt->execute();
            $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];
        }
        
        // Check active course enrollment
        $has_active_course = false;
        if (!empty($rejected_student['course']) && $rejected_student['status'] !== 'completed') {
            $has_active_course = true;
        }
        
        echo "Reapplication Validation Results:\n";
        echo "  - Pending applications: {$pending_count} " . ($pending_count > 0 ? "❌ BLOCKS reapplication" : "✅ OK") . "\n";
        echo "  - Approved applications: {$approved_count} " . ($approved_count > 0 ? "❌ BLOCKS reapplication" : "✅ OK") . "\n";
        echo "  - Active course enrollment: " . ($has_active_course ? "❌ BLOCKS reapplication" : "✅ OK") . "\n";
        
        $can_reapply = ($pending_count == 0 && $approved_count == 0 && !$has_active_course);
        
        echo "\n" . ($can_reapply ? "✅ RESULT: Student CAN reapply for the rejected course!" : "❌ RESULT: Student CANNOT reapply due to restrictions above") . "\n\n";
        
        if ($can_reapply) {
            echo "The student can:\n";
            echo "  - See '{$rejected_student['course_name']}' in the course dropdown\n";
            echo "  - Submit a new application for the same course\n";
            echo "  - The previous rejection does not prevent reapplication\n";
        }
        
    } else {
        echo "No students with rejected applications found.\n";
        echo "Creating a test scenario...\n\n";
        
        // Check if we have any students
        $stmt = $conn->query("SELECT id, first_name, last_name, uli FROM students WHERE status = 'completed' LIMIT 1");
        $test_student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_student) {
            echo "Using test student: {$test_student['first_name']} {$test_student['last_name']} (ULI: {$test_student['uli']})\n";
            echo "This student has 'completed' status, so they should be able to apply for any course.\n";
        } else {
            echo "No suitable test students found.\n";
        }
    }
    
    // Show available courses
    echo "\nAvailable courses for application:\n";
    $stmt = $conn->query("SELECT course_name FROM courses WHERE is_active = 1 ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($courses as $course) {
        echo "  - {$course}\n";
    }
    
    echo "\nNote: All active courses appear in the dropdown regardless of previous rejections.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
?>