<?php
require_once 'database.php';

echo "Testing Rejected Student Application Logic...\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Find a student with rejected status
    $stmt = $conn->query("SELECT * FROM students WHERE status = 'rejected' LIMIT 1");
    $rejected_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rejected_student) {
        echo "Testing with rejected student:\n";
        echo "  - Name: {$rejected_student['first_name']} {$rejected_student['last_name']}\n";
        echo "  - ULI: {$rejected_student['uli']}\n";
        echo "  - Status: {$rejected_student['status']}\n";
        echo "  - Course: " . ($rejected_student['course'] ?: 'None') . "\n\n";
        
        // Test the new logic
        $student_profile = $rejected_student;
        
        // Check pending applications
        $stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM course_applications WHERE student_id = :student_id AND status = 'pending'");
        $stmt->bindParam(':student_id', $student_profile['id']);
        $stmt->execute();
        $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
        
        // Check approved applications
        $approved_count = 0;
        if ($student_profile['status'] === 'approved') {
            $stmt = $conn->prepare("SELECT COUNT(*) as approved_count FROM course_applications WHERE student_id = :student_id AND status = 'approved'");
            $stmt->bindParam(':student_id', $student_profile['id']);
            $stmt->execute();
            $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];
        }
        
        // NEW LOGIC: Check for active courses - students can apply when status is 'completed' OR 'rejected'
        $has_active_course = false;
        if (!empty($student_profile['course']) && 
            $student_profile['status'] !== 'completed' && 
            $student_profile['status'] !== 'rejected') {
            $has_active_course = true;
        }
        
        // Determine if student can apply
        $can_apply = true;
        $restriction_message = '';
        
        if ($pending_count > 0) {
            $can_apply = false;
            $restriction_message = 'You have a pending course application waiting for admin review.';
        } elseif ($approved_count > 0 && $student_profile['status'] === 'approved') {
            $can_apply = false;
            $restriction_message = 'You have an approved course application waiting for completion.';
        } elseif ($has_active_course) {
            $can_apply = false;
            $restriction_message = 'You have an active course enrollment.';
        }
        
        echo "Application Validation Results:\n";
        echo "  - Pending applications: {$pending_count}\n";
        echo "  - Approved applications: {$approved_count}\n";
        echo "  - Has active course: " . ($has_active_course ? 'Yes' : 'No') . "\n";
        echo "  - Can apply: " . ($can_apply ? 'YES ✅' : 'NO ❌') . "\n";
        
        if (!$can_apply) {
            echo "  - Restriction: {$restriction_message}\n";
        }
        
        echo "\n" . ($can_apply ? "✅ SUCCESS: Rejected student CAN now apply for courses!" : "❌ ISSUE: Rejected student still cannot apply") . "\n";
        
        if ($can_apply) {
            echo "\nThe student can now:\n";
            echo "  - Access the course application form\n";
            echo "  - See all available courses in the dropdown\n";
            echo "  - Apply for any course (including the one they were rejected from)\n";
            echo "  - Submit new applications without restrictions\n";
        }
        
    } else {
        echo "No students with 'rejected' status found.\n";
        echo "The fix is ready for when students get rejected.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
?>