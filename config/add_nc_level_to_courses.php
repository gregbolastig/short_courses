<?php
/**
 * Database Migration: Add nc_level column to courses table
 * 
 * This script adds the nc_level column to the existing courses table
 * and sets default values for existing courses.
 */

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Starting migration: Adding nc_level column to courses table...\n";
    
    // Check if nc_level column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM courses LIKE 'nc_level'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add nc_level column
        $conn->exec("ALTER TABLE courses ADD COLUMN nc_level VARCHAR(50) NOT NULL DEFAULT 'NC II' AFTER course_name");
        echo "✓ Added nc_level column to courses table\n";
        
        // Update existing courses with appropriate NC levels
        $courseUpdates = [
            'Computer Programming' => 'NC IV',
            'Automotive Servicing' => 'NC II',
            'Welding' => 'NC II',
            'Electrical Installation' => 'NC II',
            'Plumbing' => 'NC II',
            'Carpentry' => 'NC II',
            'Masonry' => 'NC II',
            'Electronics' => 'NC III'
        ];
        
        foreach ($courseUpdates as $courseName => $ncLevel) {
            $stmt = $conn->prepare("UPDATE courses SET nc_level = :nc_level WHERE course_name = :course_name");
            $stmt->bindParam(':nc_level', $ncLevel);
            $stmt->bindParam(':course_name', $courseName);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "✓ Updated '$courseName' to $ncLevel\n";
            }
        }
        
        echo "✓ Migration completed successfully!\n";
        
    } else {
        echo "ℹ nc_level column already exists in courses table\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>