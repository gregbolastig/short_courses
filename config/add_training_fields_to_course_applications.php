<?php
/**
 * Migration: Add training fields to course_applications table
 * This allows storing adviser and training dates for historical record keeping
 */

require_once __DIR__ . '/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Adding training fields to course_applications table...\n";
    
    // Check if columns already exist
    $stmt = $conn->query("SHOW COLUMNS FROM course_applications LIKE 'adviser'");
    $adviser_exists = $stmt->rowCount() > 0;
    
    if (!$adviser_exists) {
        // Add adviser column
        $conn->exec("ALTER TABLE course_applications 
                     ADD COLUMN adviser VARCHAR(255) NULL AFTER nc_level");
        echo "✓ Added 'adviser' column\n";
    } else {
        echo "- 'adviser' column already exists\n";
    }
    
    // Check if training_start exists
    $stmt = $conn->query("SHOW COLUMNS FROM course_applications LIKE 'training_start'");
    $training_start_exists = $stmt->rowCount() > 0;
    
    if (!$training_start_exists) {
        // Add training_start column
        $conn->exec("ALTER TABLE course_applications 
                     ADD COLUMN training_start DATE NULL AFTER adviser");
        echo "✓ Added 'training_start' column\n";
    } else {
        echo "- 'training_start' column already exists\n";
    }
    
    // Check if training_end exists
    $stmt = $conn->query("SHOW COLUMNS FROM course_applications LIKE 'training_end'");
    $training_end_exists = $stmt->rowCount() > 0;
    
    if (!$training_end_exists) {
        // Add training_end column
        $conn->exec("ALTER TABLE course_applications 
                     ADD COLUMN training_end DATE NULL AFTER training_start");
        echo "✓ Added 'training_end' column\n";
    } else {
        echo "- 'training_end' column already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "The course_applications table now stores adviser and training dates for historical records.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
