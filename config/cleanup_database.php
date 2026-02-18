<?php
// Simple script to clean up duplicate entries in the database
require_once 'database.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Database Cleanup</title></head><body>";
echo "<h1>Database Cleanup</h1>";

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
        echo "<p>✓ Courses table found</p>";
        
        // Get current courses count
        $stmt = $conn->query("SELECT COUNT(*) as count FROM courses");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>Current courses in database: $count</p>";
        
        // Check for duplicates
        $stmt = $conn->query("SELECT course_name, COUNT(*) as count FROM courses GROUP BY course_name HAVING COUNT(*) > 1");
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($duplicates) > 0) {
            echo "<p style='color: orange;'>Found " . count($duplicates) . " duplicate course names:</p>";
            echo "<ul>";
            foreach ($duplicates as $duplicate) {
                echo "<li>" . htmlspecialchars($duplicate['course_name']) . " (appears " . $duplicate['count'] . " times)</li>";
            }
            echo "</ul>";
            
            echo "<p>Cleaning up duplicates...</p>";
            
            // Remove duplicates, keeping only the first occurrence
            foreach ($duplicates as $duplicate) {
                $courseName = $duplicate['course_name'];
                
                // Get all IDs for this course name
                $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_name = ? ORDER BY course_id");
                $stmt->execute([$courseName]);
                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Keep the first ID, delete the rest
                $keepId = array_shift($ids);
                
                foreach ($ids as $deleteId) {
                    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
                    $stmt->execute([$deleteId]);
                    echo "<p>Deleted duplicate: $courseName (ID: $deleteId)</p>";
                }
            }
            
            echo "<p style='color: green;'>✓ Duplicates cleaned up successfully</p>";
        } else {
            echo "<p style='color: green;'>✓ No duplicates found</p>";
        }
        
        // Show final course list
        $stmt = $conn->query("SELECT * FROM courses ORDER BY course_name");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Final Course List (" . count($courses) . " courses):</h2>";
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>ID</th><th>Course Name</th><th>Status</th><th>Created</th></tr>";
        
        foreach ($courses as $course) {
            $status = $course['is_active'] ? 'Active' : 'Inactive';
            $statusColor = $course['is_active'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . $course['course_id'] . "</td>";
            echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
            echo "<td style='color: $statusColor;'>$status</td>";
            echo "<td>" . $course['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: red;'>✗ Courses table not found</p>";
        echo "<p>Please run the database setup first.</p>";
    }
    
    echo "<h2>Cleanup Complete!</h2>";
    echo "<p><a href='../admin/courses/index.php'>Go to Course Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>