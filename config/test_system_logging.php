<?php
require_once 'database.php';
require_once __DIR__ . '/../includes/system_activity_logger.php';

echo "Testing System Activity Logging...\n\n";

try {
    // Test database connection
    $database = new Database();
    $conn = $database->getConnection();
    echo "✓ Database connection successful\n";
    
    // Check if system_activities table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'system_activities'");
    if ($stmt->rowCount() > 0) {
        echo "✓ system_activities table exists\n";
    } else {
        echo "✗ system_activities table does NOT exist\n";
        echo "Creating system_activities table...\n";
        include 'create_system_activities_table.php';
    }
    
    // Check table structure
    $stmt = $conn->query("DESCRIBE system_activities");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Table structure:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    // Test logging
    echo "\nTesting logging functionality...\n";
    $logger = new SystemActivityLogger();
    
    $result = $logger->log(
        'test_activity',
        'This is a test log entry to verify logging is working',
        'system',
        null,
        'test',
        1
    );
    
    if ($result) {
        echo "✓ Test log entry created successfully\n";
    } else {
        echo "✗ Failed to create test log entry\n";
    }
    
    // Check if the entry was inserted
    $stmt = $conn->query("SELECT COUNT(*) as count FROM system_activities WHERE activity_type = 'test_activity'");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "✓ Found {$count} test entries in database\n";
    
    // Show recent entries
    echo "\nRecent system activities:\n";
    $stmt = $conn->query("SELECT * FROM system_activities ORDER BY created_at DESC LIMIT 5");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($activities as $activity) {
        echo "  - [{$activity['created_at']}] {$activity['activity_type']}: {$activity['activity_description']}\n";
    }
    
    // Clean up test entry
    $conn->query("DELETE FROM system_activities WHERE activity_type = 'test_activity'");
    echo "\n✓ Test entries cleaned up\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
?>