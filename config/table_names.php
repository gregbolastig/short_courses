<?php
/**
 * Table Names Configuration
 * 
 * Centralized table name definitions with shortcourse_ prefix
 * Use these constants throughout the application instead of hardcoded table names
 */

// Define table name constants
define('TABLE_USERS', 'shortcourse_users');
define('TABLE_STUDENTS', 'shortcourse_students');
define('TABLE_COURSES', 'shortcourse_courses');
define('TABLE_ADVISERS', 'shortcourse_advisers');
define('TABLE_COURSE_APPLICATIONS', 'shortcourse_course_applications');
define('TABLE_SYSTEM_ACTIVITIES', 'shortcourse_system_activities');
define('TABLE_CHECKLIST', 'shortcourse_checklist');
define('TABLE_BOOKKEEPING_RECEIPTS', 'shortcourse_bookkeeping_receipts');

// Legacy table names (for backward compatibility during migration)
define('TABLE_USERS_OLD', 'users');
define('TABLE_STUDENTS_OLD', 'students');
define('TABLE_COURSES_OLD', 'courses');
define('TABLE_ADVISERS_OLD', 'advisers');
define('TABLE_COURSE_APPLICATIONS_OLD', 'course_applications');
define('TABLE_SYSTEM_ACTIVITIES_OLD', 'system_activities');
define('TABLE_CHECKLIST_OLD', 'checklist');
define('TABLE_BOOKKEEPING_RECEIPTS_OLD', 'bookkeeping_receipts');

/**
 * Helper function to get table name
 * This allows for easy switching between old and new table names
 */
function getTableName($table_constant) {
    return constant($table_constant);
}
?>
