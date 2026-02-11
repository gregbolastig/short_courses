<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Create checklist table
    $sql = "CREATE TABLE IF NOT EXISTS checklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        description TEXT,
        is_required TINYINT(1) DEFAULT 1,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    echo "✓ Checklist table created successfully!\n";
    
    // Insert sample data
    $sampleData = [
        ['Birth Certificate (PSA)', 'Original or certified true copy of PSA Birth Certificate', 1, 1, 1],
        ['Form 137/138', 'High School Report Card', 1, 1, 2],
        ['Good Moral Certificate', 'Certificate of Good Moral Character from previous school', 1, 1, 3],
        ['2x2 ID Pictures', 'Recent 2x2 ID pictures (4 copies, white background)', 1, 1, 4],
        ['Medical Certificate', 'Medical certificate from licensed physician', 1, 1, 5],
        ['Barangay Clearance', 'Barangay clearance from place of residence', 0, 1, 6],
    ];
    
    $stmt = $conn->prepare("INSERT INTO checklist (item_name, description, is_required, is_active, display_order) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($sampleData as $data) {
        $stmt->execute($data);
    }
    
    echo "✓ Sample checklist items inserted successfully!\n";
    echo "\nChecklist management system is ready to use!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
