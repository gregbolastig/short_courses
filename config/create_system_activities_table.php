<?php
require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Create system_activities table
    $sql = "CREATE TABLE IF NOT EXISTS system_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        user_type ENUM('admin', 'student', 'system') NOT NULL DEFAULT 'system',
        activity_type VARCHAR(100) NOT NULL,
        activity_description TEXT NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id INT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_user_type (user_type),
        INDEX idx_activity_type (activity_type),
        INDEX idx_created_at (created_at),
        INDEX idx_entity (entity_type, entity_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    $conn->exec($sql);
    echo "System activities table created successfully!\n";
    
    // Insert some sample data
    $sample_activities = [
        [
            'user_type' => 'system',
            'activity_type' => 'system_startup',
            'activity_description' => 'System activities table created and initialized',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ],
        [
            'user_type' => 'admin',
            'activity_type' => 'table_creation',
            'activity_description' => 'Created system_activities table for activity logging',
            'entity_type' => 'table',
            'entity_id' => 1,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]
    ];
    
    $stmt = $conn->prepare("INSERT INTO system_activities (user_type, activity_type, activity_description, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($sample_activities as $activity) {
        $stmt->execute([
            $activity['user_type'],
            $activity['activity_type'],
            $activity['activity_description'],
            $activity['entity_type'] ?? null,
            $activity['entity_id'] ?? null,
            $activity['ip_address']
        ]);
    }
    
    echo "Sample activities inserted successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>