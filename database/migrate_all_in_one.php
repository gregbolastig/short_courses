<?php
/**
 * All-in-One Migration Script
 * 
 * This script performs both code updates and table renaming in one go
 * IMPORTANT: Backup your database before running this!
 */

echo "<!DOCTYPE html><html><head><title>All-in-One Migration</title></head><body>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: #856404; background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #004085; background: #cce5ff; border: 1px solid #b8daff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
    h2 { color: #555; margin-top: 30px; }
    .step { background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
</style>";

echo "<div class='container'>";
echo "<h1>🚀 All-in-One Migration Script</h1>";
echo "<p><strong>This script will:</strong></p>";
echo "<ol>";
echo "<li>Update all PHP files with new table names</li>";
echo "<li>Rename database tables with shortcourse_ prefix</li>";
echo "</ol>";

echo "<div class='warning'>";
echo "<strong>⚠️ WARNING:</strong> Make sure you have backed up your database before proceeding!";
echo "</div>";

// Check if user confirmed
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "<div class='info'>";
    echo "<h3>Ready to proceed?</h3>";
    echo "<p>Click the button below to start the migration:</p>";
    echo "<a href='?confirm=yes' style='display: inline-block; background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Start Migration</a>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

// ============================================================================
// STEP 1: UPDATE CODE
// ============================================================================

echo "<div class='step'>";
echo "<h2>Step 1: Updating PHP Files</h2>";

$replacements = [
    "FROM users" => "FROM shortcourse_users",
    "INTO users" => "INTO shortcourse_users",
    "UPDATE users" => "UPDATE shortcourse_users",
    "JOIN users" => "JOIN shortcourse_users",
    "TABLE users" => "TABLE shortcourse_users",
    "FROM students" => "FROM shortcourse_students",
    "INTO students" => "INTO shortcourse_students",
    "UPDATE students" => "UPDATE shortcourse_students",
    "JOIN students" => "JOIN shortcourse_students",
    "TABLE students" => "TABLE shortcourse_students",
    "FROM courses" => "FROM shortcourse_courses",
    "INTO courses" => "INTO shortcourse_courses",
    "UPDATE courses" => "UPDATE shortcourse_courses",
    "JOIN courses" => "JOIN shortcourse_courses",
    "TABLE courses" => "TABLE shortcourse_courses",
    "FROM advisers" => "FROM shortcourse_advisers",
    "INTO advisers" => "INTO shortcourse_advisers",
    "UPDATE advisers" => "UPDATE shortcourse_advisers",
    "JOIN advisers" => "JOIN shortcourse_advisers",
    "TABLE advisers" => "TABLE shortcourse_advisers",
    "FROM course_applications" => "FROM shortcourse_course_applications",
    "INTO course_applications" => "INTO shortcourse_course_applications",
    "UPDATE course_applications" => "UPDATE shortcourse_course_applications",
    "JOIN course_applications" => "JOIN shortcourse_course_applications",
    "TABLE course_applications" => "TABLE shortcourse_course_applications",
    "FROM system_activities" => "FROM shortcourse_system_activities",
    "INTO system_activities" => "INTO shortcourse_system_activities",
    "UPDATE system_activities" => "UPDATE shortcourse_system_activities",
    "JOIN system_activities" => "JOIN shortcourse_system_activities",
    "TABLE system_activities" => "TABLE shortcourse_system_activities",
    "FROM checklist" => "FROM shortcourse_checklist",
    "INTO checklist" => "INTO shortcourse_checklist",
    "UPDATE checklist" => "UPDATE shortcourse_checklist",
    "JOIN checklist" => "JOIN shortcourse_checklist",
    "TABLE checklist" => "TABLE shortcourse_checklist",
    "FROM bookkeeping_receipts" => "FROM shortcourse_bookkeeping_receipts",
    "INTO bookkeeping_receipts" => "INTO shortcourse_bookkeeping_receipts",
    "UPDATE bookkeeping_receipts" => "UPDATE shortcourse_bookkeeping_receipts",
    "JOIN bookkeeping_receipts" => "JOIN shortcourse_bookkeeping_receipts",
    "TABLE bookkeeping_receipts" => "TABLE shortcourse_bookkeeping_receipts",
];

function getAllPhpFiles($dir) {
    $files = [];
    $items = @scandir($dir);
    if (!$items) return $files;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (in_array($item, ['.git', '.kiro', 'node_modules', 'vendor'])) continue;
            $files = array_merge($files, getAllPhpFiles($path));
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $files[] = $path;
        }
    }
    return $files;
}

$root_dir = dirname(__DIR__);
$php_files = getAllPhpFiles($root_dir);
$updated_files = 0;
$total_replacements = 0;

foreach ($php_files as $file) {
    $content = file_get_contents($file);
    $file_replacements = 0;
    
    foreach ($replacements as $old => $new) {
        $count = 0;
        $content = str_replace($old, $new, $content, $count);
        $file_replacements += $count;
    }
    
    if ($file_replacements > 0) {
        file_put_contents($file, $content);
        $updated_files++;
        $total_replacements += $file_replacements;
    }
}

echo "<div class='success'>✓ Updated $updated_files PHP files ($total_replacements replacements)</div>";
echo "</div>";

// ============================================================================
// STEP 2: RENAME TABLES
// ============================================================================

echo "<div class='step'>";
echo "<h2>Step 2: Renaming Database Tables</h2>";

try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
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
    
    foreach ($table_mappings as $old_name => $new_name) {
        $stmt = $conn->query("SHOW TABLES LIKE '$old_name'");
        $old_exists = $stmt->rowCount() > 0;
        
        $stmt = $conn->query("SHOW TABLES LIKE '$new_name'");
        $new_exists = $stmt->rowCount() > 0;
        
        if ($old_exists && !$new_exists) {
            $conn->exec("RENAME TABLE `$old_name` TO `$new_name`");
            echo "<div class='success'>✓ Renamed: $old_name → $new_name</div>";
            $renamed_count++;
        } elseif ($new_exists) {
            echo "<div class='info'>ℹ Already exists: $new_name</div>";
        }
    }
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "</div>";
    
    // ============================================================================
    // SUMMARY
    // ============================================================================
    
    echo "<div class='success' style='margin-top: 30px; padding: 20px;'>";
    echo "<h2 style='margin-top: 0;'>✓ Migration Completed Successfully!</h2>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>PHP Files Updated: $updated_files</li>";
    echo "<li>Code Replacements: $total_replacements</li>";
    echo "<li>Tables Renamed: $renamed_count</li>";
    echo "</ul>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Test your application thoroughly</li>";
    echo "<li>Check student registration</li>";
    echo "<li>Verify admin dashboard</li>";
    echo "<li>Test course applications</li>";
    echo "</ol>";
    echo "<p><a href='../admin/dashboard.php' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Go to Admin Dashboard</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>✗ Database error: " . $e->getMessage() . "</div>";
    echo "</div>";
}

echo "</div></body></html>";
?>
