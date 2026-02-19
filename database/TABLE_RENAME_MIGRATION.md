# Table Rename Migration Guide

## Overview
All database tables have been renamed with the `shortcourse_` prefix for better organization and to avoid conflicts with other systems.

## Table Name Changes

| Old Name | New Name |
|----------|----------|
| `users` | `shortcourse_users` |
| `students` | `shortcourse_students` |
| `courses` | `shortcourse_courses` |
| `advisers` | `shortcourse_advisers` |
| `course_applications` | `shortcourse_course_applications` |
| `system_activities` | `shortcourse_system_activities` |
| `checklist` | `shortcourse_checklist` |
| `bookkeeping_receipts` | `shortcourse_bookkeeping_receipts` |

## Migration Steps

### Step 1: Update Code (FIRST)
Run this script to update all PHP files with new table names:
```
http://localhost/your-project/database/update_code_for_new_tables.php
```

This will:
- Search through all PHP files
- Replace old table names with new ones
- Show you which files were updated

### Step 2: Rename Database Tables (SECOND)
After code is updated, run this script to rename the actual database tables:
```
http://localhost/your-project/database/rename_tables_to_shortcourse.php
```

This will:
- Rename all existing tables to use the shortcourse_ prefix
- Preserve all your data
- Update foreign key relationships

### Step 3: Verify
After migration:
1. Check that your application still works
2. Test key features:
   - Student registration
   - Course applications
   - Admin dashboard
   - Checklist management

## Important Notes

⚠️ **BACKUP YOUR DATABASE FIRST!**
Before running any migration scripts, create a backup of your database:
```sql
mysqldump -u root -p student_registration_db > backup_before_rename.sql
```

⚠️ **Run Scripts in Order**
1. First: `update_code_for_new_tables.php` (updates PHP files)
2. Second: `rename_tables_to_shortcourse.php` (renames database tables)

⚠️ **One-Time Migration**
These scripts should only be run ONCE. Running them multiple times won't cause errors, but it's unnecessary.

## Rollback (If Needed)

If something goes wrong, you can restore your backup:
```sql
mysql -u root -p student_registration_db < backup_before_rename.sql
```

Then revert the code changes by running:
```bash
git checkout .
```
(if using version control)

## Manual Verification

After migration, you can verify the tables were renamed:
```sql
SHOW TABLES LIKE 'shortcourse_%';
```

You should see all 8 tables with the shortcourse_ prefix.

## Support

If you encounter any issues during migration:
1. Check the error messages in the migration scripts
2. Verify your database connection in `config/database.php`
3. Ensure you have proper database permissions
4. Restore from backup if needed
