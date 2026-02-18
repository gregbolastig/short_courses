<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Database Structure Check</h2>";
    
    // Check students table
    echo "<h3>Students Table Columns:</h3>";
    $stmt = $conn->query("DESCRIBE students");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    // Check if student_enrollments table exists
    echo "<h3>Checking student_enrollments table:</h3>";
    try {
        $stmt = $conn->query("DESCRIBE student_enrollments");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
        
        // Count enrollments
        $stmt = $conn->query("SELECT COUNT(*) as count FROM student_enrollments");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total enrollments: " . $count['count'] . "</p>";
    } catch(PDOException $e) {
        echo "<p style='color: red;'>student_enrollments table does not exist or error: " . $e->getMessage() . "</p>";
    }
    
    // Check if course_applications table exists
    echo "<h3>Checking course_applications table:</h3>";
    try {
        $stmt = $conn->query("DESCRIBE course_applications");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
        
        // Count applications
        $stmt = $conn->query("SELECT COUNT(*) as count FROM course_applications");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total applications: " . $count['count'] . "</p>";
    } catch(PDOException $e) {
        echo "<p style='color: red;'>course_applications table does not exist or error: " . $e->getMessage() . "</p>";
    }
    
    // Check courses table
    echo "<h3>Courses Table:</h3>";
    try {
        $stmt = $conn->query("DESCRIBE courses");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
        
        // Count courses
        $stmt = $conn->query("SELECT COUNT(*) as count FROM courses");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total courses: " . $count['count'] . "</p>";
    } catch(PDOException $e) {
        echo "<p style='color: red;'>courses table error: " . $e->getMessage() . "</p>";
    }
    
    // Check advisers table
    echo "<h3>Advisers Table:</h3>";
    try {
        $stmt = $conn->query("DESCRIBE advisers");
        echo "<pre>";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
        echo "</pre>";
    } catch(PDOException $e) {
        echo "<p style='color: red;'>advisers table error: " . $e->getMessage() . "</p>";
    }
    
    // Sample student data
    echo "<h3>Sample Students (first 5):</h3>";
    $stmt = $conn->query("SELECT id, student_id, first_name, last_name, uli, course, nc_level, adviser, status FROM students LIMIT 5");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
