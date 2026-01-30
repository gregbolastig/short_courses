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
           