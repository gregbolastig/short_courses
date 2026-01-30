<?php
require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Update all 'approved' status to 'completed' since approval means course completion
    $sql = "UPDATE students SET status = 'completed' WHERE status = 'approved'";
    
    $result = $conn->exec($sql);
    
    if ($result !== false) {
        echo "Successfully updated $result student(s) from 'approved' to 'completed' status.\n";
    } else {
        echo "No students were updated.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>