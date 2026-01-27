<?php
// Script to clear all courses from the database
require_once 'database.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Clear All Courses</title></head><body>";
echo "<h1>Clear All Courses</h1>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Could not connect to database");
    }
    
    echo "<p>✓ Connected to database successfully</p>";
    
    // Check if courses table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'courses'");
    $coursesTableExists = $stmt->fetch();
    
    if ($coursesTableExists) {
        // Get current courses count
        $stmt = $conn->query("SELECT COUNT(*) as count FROM courses");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Current courses in database: $count</p>";
        
        if ($count > 0) {
            // Show current courses before deletion
            $stmt = $conn->query("SELECT * FROM courses ORDER BY course_name");
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Courses to be deleted:</h3>";
            echo "<ul>";
            foreach ($courses as $course) {
                echo "<li>" . htmlspecialchars($course['course_name']) . " (ID: " . $course['course_id'] . ")</li>";
            }
            echo "</ul>";
            
            // Clear all courses
            $stmt = $conn->prepare("DELETE FROM courses");
            $stmt->execute();
            
            echo "<p style='color: green;'>✓ All courses cleared successfully</p>";
            
            // Reset auto increment
            $conn->exec("ALTER TABLE courses AUTO_INCREMENT = 1");
            echo "<p>✓ Course ID counter reset to 1</p>";
            
        } else {
            echo "<p style='color: blue;'>ℹ No courses found in database</p>";
        }
        
        // Verify deletion
        $stmt = $conn->query("SELECT COUNT(*) as count FROM courses");
        $finalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><strong>Final course count: $finalCount</strong></p>";
        
    } else {
        echo "<p style='color: red;'>✗ Courses table not found</p>";
        echo "<p>Please run the database setup first.</p>";
    }
    
    echo "<h2>Cleanup Complete!</h2>";
    echo "<p>The courses table is now empty and ready for user input.</p>";
    echo "<p><a href='../admin/courses/add.php'>Add Your First Course</a></p>";
    echo "<p><a href='../admin/courses/index.php'>Go to Course Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>