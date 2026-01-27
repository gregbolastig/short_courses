<?php
/**
 * Fix Students Table Structure
 * This script ensures the students table has all required columns
 */

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Fixing Students Table Structure</h2>";
    
    // Check if students table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'students'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Students table does not exist. Please run schema.sql first.</p>";
        exit;
    }
    
    // Get current table structure
    $stmt = $conn->query("DESCRIBE students");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>Current columns in students table:</p>";
    echo "<ul>";
    foreach ($existing_columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    // Define required columns with their definitions
    $required_columns = [
        'course' => 'VARCHAR(200)',
        'nc_level' => 'VARCHAR(50)',
        'training_start' => 'DATE',
        'training_end' => 'DATE',
        'adviser' => 'VARCHAR(200)'
    ];
    
    $columns_added = 0;
    
    // Add missing columns
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $sql = "ALTER TABLE students ADD COLUMN $column $definition";
                $conn->exec($sql);
                echo "<p style='color: green;'>✓ Added column: $column</p>";
                $columns_added++;
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Failed to add column $column: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ Column $column already exists</p>";
        }
    }
    
    if ($columns_added > 0) {
        echo "<p style='color: green;'><strong>✓ Successfully added $columns_added columns to students table</strong></p>";
    } else {
        echo "<p style='color: blue;'><strong>ℹ All required columns already exist</strong></p>";
    }
    
    // Show final table structure
    echo "<h3>Final Table Structure:</h3>";
    $stmt = $conn->query("DESCRIBE students");
    $final_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($final_columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'><strong>✓ Students table structure is now correct!</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
?>