<?php
/**
 * Diagnostic script to check course_applications data
 */

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "=== Checking course_applications table ===\n\n";
    
    // Get all course applications
    $stmt = $conn->query("
        SELECT ca.*, s.first_name, s.last_name, c.course_name
        FROM course_applications ca
        LEFT JOIN students s ON ca.student_id = s.id
        LEFT JOIN courses c ON ca.course_id = c.course_id
        ORDER BY ca.applied_at DESC
        LIMIT 10
    ");
    
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($applications) . " course applications (showing last 10):\n\n";
    
    foreach ($applications as $app) {
        echo "Application ID: {$app['application_id']}\n";
        echo "  Student: {$app['first_name']} {$app['last_name']} (ID: {$app['student_id']})\n";
        echo "  Course ID: " . ($app['course_id'] ?? 'NULL') . "\n";
        echo "  Course Name: " . ($app['course_name'] ?? 'NOT FOUND') . "\n";
        echo "  NC Level: {$app['nc_level']}\n";
        echo "  Status: {$app['status']}\n";
        echo "  Applied: {$app['applied_at']}\n";
        echo "  ---\n";
    }
    
    // Check for NULL course_ids
    $stmt = $conn->query("SELECT COUNT(*) as count FROM course_applications WHERE course_id IS NULL");
    $null_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "\nApplications with NULL course_id: $null_count\n";
    
    // Check for 0 course_ids
    $stmt = $conn->query("SELECT COUNT(*) as count FROM course_applications WHERE course_id = 0");
    $zero_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Applications with course_id = 0: $zero_count\n";
    
    // Check approved applications
    $stmt = $conn->query("SELECT COUNT(*) as count FROM course_applications WHERE status = 'approved'");
    $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Approved applications: $approved_count\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
