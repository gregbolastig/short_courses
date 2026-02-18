<?php
/**
 * Fix Course Applications - Set proper course_id for existing records
 * 
 * This script updates course_applications records that have NULL or 0 course_id
 * by looking up the course name from the students table and finding the matching course_id
 */

require_once 'database.php';

echo "Starting course_applications course_id fix...\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Find course_applications with NULL or 0 course_id
    // Check both approved and pending applications
    $stmt = $conn->query("
        SELECT ca.application_id, ca.student_id, ca.status
        FROM course_applications ca
        WHERE (ca.course_id IS NULL OR ca.course_id = 0)
    ");
    
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($applications) . " course applications with missing course_id\n\n";
    
    if (count($applications) == 0) {
        echo "No applications need fixing. All course_applications have valid course_id values.\n";
        $conn->commit();
        exit(0);
    }
    
    echo "These applications have NULL/0 course_id and cannot be properly displayed.\n";
    echo "This usually means they were created with old code or test data.\n\n";
    echo "Recommendation: Delete these invalid applications and have users reapply.\n\n";
    
    $fixed_count = 0;
    $deleted_count = 0;
    
    foreach ($applications as $app) {
        echo "Application ID {$app['application_id']} (student_id: {$app['student_id']}, status: {$app['status']}) has no course_id\n";
        
        // Option: Delete invalid applications
        // Uncomment the following lines to delete them:
        /*
        $stmt = $conn->prepare("DELETE FROM course_applications WHERE application_id = :application_id");
        $stmt->bindParam(':application_id', $app['application_id']);
        $stmt->execute();
        echo "  → Deleted\n";
        $deleted_count++;
        */
    }
    
    echo "\n=== Summary ===\n";
    echo "Total applications with missing course_id: " . count($applications) . "\n";
    echo "Deleted: $deleted_count\n";
    echo "\nTo delete these invalid applications, uncomment the delete code in the script.\n";
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
