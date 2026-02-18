<?php
/**
 * Database Migration: Remove nc_level column from courses table
 * 
 * This script removes the nc_level column from the courses table
 * since NC levels will be handled in the approval modal only.
 */

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Starting migration: Removing nc_level column from courses table...\n";
    
    // Check if nc_level column exists
    $stmt = $conn->query("SHOW COLUMNS FROM courses LIKE 'nc_level'");
    $columnExists = $stmt->rowCount() > 0;
    
    if ($columnExists) {
        // Remove nc_level column
        $conn->exec("ALTER TABLE courses DROP COLUMN nc_level");
        echo "✓ Removed nc_level column from courses table\n";
        echo "✓ Migration completed successfully!\n";
        
    } else {
        echo "ℹ nc_level column does not exist in courses table\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>