<?php
/**
 * Database Migration: Add 'completed' status to students table
 * This script adds the 'completed' status option to allow students
 * who have finished their courses to apply for new ones.
 */

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Starting database migration: Adding 'completed' status...\n";
    
    // Modify the students table to include 'completed' status
    $sql = "ALTER TABLE students MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending'";
    $conn->exec($sql);
    
    echo "✓ Successfully added 'completed' status to students table\n";
    
    // Also update course_applications table to include 'completed' status
    $sql = "ALTER TABLE course_applications MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'";
    $conn->exec($sql);
    
    echo "✓ Successfully added 'completed' status to course_applications table\n";
    
    echo "\nMigration completed successfully!\n";
    echo "Students with 'completed' status can now apply for new courses.\n";
    
} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>