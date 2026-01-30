<?php
require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Add 'completed' to the status ENUM
    $sql = "ALTER TABLE students MODIFY COLUMN status ENUM('pending', 'approved', 'completed', 'rejected') NOT NULL DEFAULT 'pending'";
    
    if ($conn->exec($sql)) {
        echo "Successfully added 'completed' status to students table.\n";
    } else {
        echo "Failed to update status column.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>