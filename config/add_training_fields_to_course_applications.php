<?php
/**
 * Migration: Add training fields to course_applications table
 * Purpose: Store training dates and adviser during course application approval
 * This allows pre-filling of approval form when students reapply
 */

require_once __DIR__ . '/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if columns already exist
    $stmt = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                         WHERE TABLE_NAME='course_applications' AND TABLE_SCHEMA=DATABASE()");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['COLUMN_NAME'];
    }
    
    $changes_made = false;
    
    // Add training_start if not exists
    if (!in_array('training_start', $columns)) {
        $conn->exec("ALTER TABLE course_applications ADD COLUMN training_start DATE NULL AFTER nc_level");
        echo "✓ Added training_start column\n";
        $changes_made = true;
    } else {
        echo "• training_start column already exists\n";
    }
    
    // Add training_end if not exists
    if (!in_array('training_end', $columns)) {
        $conn->exec("ALTER TABLE course_applications ADD COLUMN training_end DATE NULL AFTER training_start");
        echo "✓ Added training_end column\n";
        $changes_made = true;
    } else {
        echo "• training_end column already exists\n";
    }
    
    // Add adviser if not exists
    if (!in_array('adviser', $columns)) {
        $conn->exec("ALTER TABLE course_applications ADD COLUMN adviser VARCHAR(255) NULL AFTER training_end");
        echo "✓ Added adviser column\n";
        $changes_made = true;
    } else {
        echo "• adviser column already exists\n";
    }
    
    if ($changes_made) {
        echo "\n✓ Migration completed successfully!\n";
        echo "course_applications table now stores training dates and adviser for reapplications.\n";
    } else {
        echo "\n✓ No changes needed - all columns already exist.\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
