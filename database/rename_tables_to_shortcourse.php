<?php
/**
 * Migration Script: Rename Tables with shortcourse_ Prefix
 * 
 * This script renames all existing tables to use the shortcourse_ prefix
 * Run this ONCE on your existing database before using the new code
 */

require_once __DIR__ . '/../config/database.php';

echo "<!DOCTYPE html><html><head><title>Table Rename Migration</title></head><body>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .warning { color: orange; }</style>";

echo "<h2>Renaming Tables to shortcourse_ Prefix</h2>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Disable foreign key checks temporarily
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p class='warning'>⚠ Disabled foreign key checks temporarily</p>";
    
    // Define table mappings (old_name => new_name)
    $table_mappings = [
        'users' => 'shortcourse_users',
        'students' => 'shortcourse_students',
        'courses' => 'shortcourse_courses',
        'advisers' => 'shortcourse_advisers',
        'course_applications' => 'shortcourse_course_applications',
        'system_activities' => 'shortcourse_system_activities',
        'checklist' => 'shortcourse_checklist',
        'bookkeeping_receipts' => 'shortcourse_bookkeeping_receipts'
    ];
    
    $renamed_count = 0;
    $skipped_count = 0;
    
    foreach ($table_mappings as $old_name => $new_name) {
        // Check if old table exists
        $stmt = $conn->query("SHOW TABLES LIKE '$old_name'");
        $old_exists = $stmt->rowCount() > 0;
        
        // Check if new table already exists
        $stmt = $conn->query("SHOW TABLES LIKE '$new_name'");
        $new_exists = $stmt->rowCount() > 0;
        
        if ($old_exists && !$new_exists) {
            // Rename the table
            $conn->exec("RENAME TABLE `$old_name` TO `$new_name`");
            echo "<p class='success'>✓ Renamed: $old_name → $new_name</p>";
            $renamed_count++;
        } elseif ($new_exists) {
            echo "<p class='warning'>⚠ Skipped: $new_name already exists</p>";
            $skipped_count++;
        } else {
            echo "<p class='warning'>⚠ Skipped: $old_name does not exist</p>";
            $skipped_count++;
        }
    }
    
    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p class='success'>✓ Re-enabled foreign key checks</p>";
    
    echo "<hr>";
    echo "<h3>Migration Summary</h3>";
    echo "<p><strong>Tables Renamed:</strong> $renamed_count</p>";
    echo "<p><strong>Tables Skipped:</strong> $skipped_count</p>";
    
    if ($renamed_count > 0) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3 style='color: #155724; margin-top: 0;'>✓ Migration Completed Successfully!</h3>";
        echo "<p style='color: #155724;'>All tables have been renamed with the shortcourse_ prefix.</p>";
        echo "<p style='color: #155724;'><strong>IMPORTANT:</strong> The application code has been updated to use the new table names.</p>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Migration failed: " . $e->getMessage() . "</p>";
    echo "<p class='error'>Please check your database connection and try again.</p>";
}

echo "</body></html>";
?>
