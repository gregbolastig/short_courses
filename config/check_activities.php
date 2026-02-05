<?php
require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Checking system activities...\n\n";
    
    // Check total count
    $stmt = $conn->query("SELECT COUNT(*) as total FROM system_activities");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total activities in database: {$total}\n";
    
    // Check by user type
    $stmt = $conn->query("SELECT user_type, COUNT(*) as count FROM system_activities GROUP BY user_type");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nActivities by user type:\n";
    foreach ($counts as $count) {
        echo "  - {$count['user_type']}: {$count['count']}\n";
    }
    
    // Check recent activities
    echo "\nRecent 10 activities:\n";
    $stmt = $conn->query("SELECT * FROM system_activities ORDER BY created_at DESC LIMIT 10");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($activities as $activity) {
        echo "  - [{$activity['created_at']}] {$activity['user_type']}: {$activity['activity_type']} - {$activity['activity_description']}\n";
    }
    
    // Check what the system activities page query would return
    echo "\nWhat system activities page would show (excluding system activities):\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM system_activities WHERE user_type IN ('student', 'admin')");
    $filtered_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Filtered count (admin/student only): {$filtered_count}\n";
    
    if ($filtered_count > 0) {
        $stmt = $conn->query("SELECT * FROM system_activities WHERE user_type IN ('student', 'admin') ORDER BY created_at DESC LIMIT 5");
        $filtered_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($filtered_activities as $activity) {
            echo "  - [{$activity['created_at']}] {$activity['user_type']}: {$activity['activity_type']} - {$activity['activity_description']}\n";
        }
    } else {
        echo "No admin or student activities found!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>