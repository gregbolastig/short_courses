<?php
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✓ Database connection successful!</p>";
        
        // Check if shortcourse_students table exists
        $stmt = $conn->prepare("SHOW TABLES LIKE 'shortcourse_students'");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo "<p style='color: green;'>✓ shortcourse_students table exists</p>";
        } else {
            echo "<p style='color: red;'>✗ shortcourse_students table does not exist</p>";
        }
        
        // Check if shortcourse_courses table exists
        $stmt = $conn->prepare("SHOW TABLES LIKE 'shortcourse_courses'");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo "<p style='color: green;'>✓ shortcourse_courses table exists</p>";
        } else {
            echo "<p style='color: red;'>✗ shortcourse_courses table does not exist</p>";
        }
        
        // Show available tables
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>Available Tables:</h3>";
        echo "<p>" . implode(', ', $tables) . "</p>";
        
        // Show shortcourse_students table structure if it exists
        if (in_array('shortcourse_students', $tables)) {
            $stmt = $conn->query("DESCRIBE shortcourse_students");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>shortcourse_students Table Structure:</h3>";
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database connection failed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>