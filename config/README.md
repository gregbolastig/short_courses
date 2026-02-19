# Config Folder

This folder contains essential configuration files for the application.

## Files

### database.php
Database connection configuration. Contains the Database class used throughout the application.

**Usage:**
```php
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();
```

**Configuration:**
- Database host, name, username, and password are defined here
- Uses PDO for database connections
- Includes error handling and connection management

### table_names.php
Centralized table name definitions with `shortcourse_` prefix.

**Usage:**
```php
require_once 'config/table_names.php';
$query = "SELECT * FROM " . TABLE_STUDENTS;
```

**Available Constants:**
- `TABLE_USERS` - shortcourse_users
- `TABLE_STUDENTS` - shortcourse_students
- `TABLE_COURSES` - shortcourse_courses
- `TABLE_ADVISERS` - shortcourse_advisers
- `TABLE_COURSE_APPLICATIONS` - shortcourse_course_applications
- `TABLE_SYSTEM_ACTIVITIES` - shortcourse_system_activities
- `TABLE_CHECKLIST` - shortcourse_checklist
- `TABLE_BOOKKEEPING_RECEIPTS` - shortcourse_bookkeeping_receipts

## Configuration Guidelines

1. **Never commit sensitive data** - Use environment variables for passwords
2. **Keep this folder minimal** - Only essential config files should be here
3. **Document changes** - Update this README when adding new config files
4. **Use constants** - Prefer table_names.php constants over hardcoded table names

## Database Setup

For initial database setup, use the schema file in the root directory:
```
schema.sql
```

For database migrations, see the `database/` folder in the root directory.
