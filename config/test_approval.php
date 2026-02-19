<?php
/**
 * Test Approval Process
 * This script tests if the approval process works with the fixed database structure
 */

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Testing Approval Process</h2>";
    
    // Test if we can update a student with course details (simulate approval)
    $test_data = [
        'course' => 'Test Course',
        'nc_level' => 'NC II',
        'training_start' => '2024-02-01',
        'training_end' => '2024-06-01',
        'adviser' => 'Test Adviser'
    ];
    
    // First, let's see if there are any students to test with
    $stmt = $conn->query("SELECT id, first_name, last_name, status FROM students LIMIT 1");
    $test_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_student) {
        echo "<p>Found test student: {$test_student['first_name']} {$test_student['last_name']} (ID: {$test_student['id']})</p>";
        echo "<p>Current status: {$test_student['status']}</p>";
        
        // Test the UPDATE query that was causing the error
        $test_sql = "UPDATE students SET 
            status = 'approved', 
            course = :course,
            nc_level = :nc_level,
            training_start = :training_start,
            training_end = :training_end,
            adviser = :adviser
            WHERE id = :id";
        
        $stmt = $conn->prepare($test_sql);
        $stmt->bindParam(':course', $test_data['course']);
        $stmt->bindParam(':nc_level', $test_data['nc_level']);
        $stmt->bindParam(':training_start', $test_data['training_start']);
        $stmt->bindParam(':training_end', $test_data['training_end']);
        $stmt->bindParam(':adviser', $test_data['adviser']);
        $stmt->bindParam(':id', $test_student['id']);
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Successfully updated student record</p>";
            
            // Verify the update
            $verify_stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
            $verify_stmt->bindParam(':id', $test_student['id']);
            $verify_stmt->execute();
            $updated_student = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<h3>Updated Student Data:</h3>";
            echo "<pre>" . print_r($updated_student, true) . "</pre>";
        } else {
            echo "<p style='color: red;'>✗ Failed to update student record</p>";
            echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
        }
    } else {
        echo "<p>No students found in database to test with.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>