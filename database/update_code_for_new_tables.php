<?php
/**
 * Code Update Script: Replace Old Table Names with New Ones
 * 
 * This script searches through all PHP files and replaces old table names
 * with new shortcourse_ prefixed table names
 */

echo "<!DOCTYPE html><html><head><title>Code Update Script</title></head><body>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

echo "<h2>Updating PHP Files with New Table Names</h2>";

// Define table name replacements
$replacements = [
    // FROM users
    "FROM users" => "FROM shortcourse_users",
    "INTO users" => "INTO shortcourse_users",
    "UPDATE users" => "UPDATE shortcourse_users",
    "JOIN users" => "JOIN shortcourse_users",
    "TABLE users" => "TABLE shortcourse_users",
    
    // FROM students
    "FROM students" => "FROM shortcourse_students",
    "INTO students" => "INTO shortcourse_students",
    "UPDATE students" => "UPDATE shortcourse_students",
    "JOIN students" => "JOIN shortcourse_students",
    "TABLE students" => "TABLE shortcourse_students",
    
    // FROM courses
    "FROM courses" => "FROM shortcourse_courses",
    "INTO courses" => "INTO shortcourse_courses",
    "UPDATE courses" => "UPDATE shortcourse_courses",
    "JOIN courses" => "JOIN shortcourse_courses",
    "TABLE courses" => "TABLE shortcourse_courses",
    
    // FROM advisers
    "FROM advisers" => "FROM shortcourse_advisers",
    "INTO advisers" => "INTO shortcourse_advisers",
    "UPDATE advisers" => "UPDATE shortcourse_advisers",
    "JOIN advisers" => "JOIN shortcourse_advisers",
    "TABLE advisers" => "TABLE shortcourse_advisers",
    
    // FROM course_applications
    "FROM course_applications" => "FROM shortcourse_course_applications",
    "INTO course_applications" => "INTO shortcourse_course_applications",
    "UPDATE course_applications" => "UPDATE shortcourse_course_applications",
    "JOIN course_applications" => "JOIN shortcourse_course_applications",
    "TABLE course_applications" => "TABLE shortcourse_course_applications",
    
    // FROM system_activities
    "FROM system_activities" => "FROM shortcourse_system_activities",
    "INTO system_activities" => "INTO shortcourse_system_activities",
    "UPDATE system_activities" => "UPDATE shortcourse_system_activities",
    "JOIN system_activities" => "JOIN shortcourse_system_activities",
    "TABLE system_activities" => "TABLE shortcourse_system_activities",
    
    // FROM checklist
    "FROM checklist" => "FROM shortcourse_checklist",
    "INTO checklist" => "INTO shortcourse_checklist",
    "UPDATE checklist" => "UPDATE shortcourse_checklist",
    "JOIN checklist" => "JOIN shortcourse_checklist",
    "TABLE checklist" => "TABLE shortcourse_checklist",
    
    // FROM bookkeeping_receipts
    "FROM bookkeeping_receipts" => "FROM shortcourse_bookkeeping_receipts",
    "INTO bookkeeping_receipts" => "INTO shortcourse_bookkeeping_receipts",
    "UPDATE bookkeeping_receipts" => "UPDATE shortcourse_bookkeeping_receipts",
    "JOIN bookkeeping_receipts" => "JOIN shortcourse_bookkeeping_receipts",
    "TABLE bookkeeping_receipts" => "TABLE shortcourse_bookkeeping_receipts",
];

// Get all PHP files recursively
function getAllPhpFiles($dir) {
    $files = [];
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            // Skip certain directories
            if (in_array($item, ['.git', '.kiro', 'node_modules', 'vendor'])) {
                continue;
            }
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

echo "<p class='info'>Found " . count($php_files) . " PHP files to process...</p>";
echo "<hr>";

foreach ($php_files as $file) {
    $content = file_get_contents($file);
    $original_content = $content;
    $file_replacements = 0;
    
    foreach ($replacements as $old => $new) {
        $count = 0;
        $content = str_replace($old, $new, $content, $count);
        $file_replacements += $count;
    }
    
    if ($file_replacements > 0) {
        file_put_contents($file, $content);
        $relative_path = str_replace($root_dir . '/', '', $file);
        echo "<p class='success'>✓ Updated: $relative_path ($file_replacements replacements)</p>";
        $updated_files++;
        $total_replacements += $file_replacements;
    }
}

echo "<hr>";
echo "<h3>Update Summary</h3>";
echo "<p><strong>Files Updated:</strong> $updated_files</p>";
echo "<p><strong>Total Replacements:</strong> $total_replacements</p>";

if ($updated_files > 0) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✓ Code Update Completed!</h3>";
    echo "<p style='color: #155724;'>All PHP files have been updated to use the new table names.</p>";
    echo "<p style='color: #155724;'><strong>Next Step:</strong> Run the table rename migration script at <a href='rename_tables_to_shortcourse.php'>database/rename_tables_to_shortcourse.php</a></p>";
    echo "</div>";
} else {
    echo "<p class='info'>No files needed updating. Either they're already updated or no table references were found.</p>";
}

echo "</body></html>";
?>
