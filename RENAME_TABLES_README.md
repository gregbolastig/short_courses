# Table Rename Migration - Quick Start

## What Changed?
All database tables now have the `shortcourse_` prefix:
- `users` → `shortcourse_users`
- `students` → `shortcourse_students`
- `courses` → `shortcourse_courses`
- And 5 more tables...

## How to Migrate (Easy Way)

### Option 1: Automatic Migration (Recommended)
Just run this one script:
```
http://localhost/your-project/database/migrate_all_in_one.php
```

This will:
1. ✅ Update all PHP code automatically
2. ✅ Rename all database tables
3. ✅ Show you a summary of changes

### Option 2: Manual Step-by-Step
If you prefer to do it manually:

1. **Update Code First:**
   ```
   http://localhost/your-project/database/update_code_for_new_tables.php
   ```

2. **Then Rename Tables:**
   ```
   http://localhost/your-project/database/rename_tables_to_shortcourse.php
   ```

## ⚠️ IMPORTANT: Backup First!

Before running any migration:
```sql
mysqldump -u root -p student_registration_db > backup.sql
```

## Files Created

1. **schema.sql** - Updated with new table names
2. **database/migrate_all_in_one.php** - One-click migration
3. **database/update_code_for_new_tables.php** - Updates PHP files
4. **database/rename_tables_to_shortcourse.php** - Renames database tables
5. **database/TABLE_RENAME_MIGRATION.md** - Detailed guide
6. **config/table_names.php** - Table name constants (for future use)

## After Migration

Test these features:
- ✅ Student registration
- ✅ Admin login
- ✅ Course applications
- ✅ Checklist management
- ✅ Bookkeeping

## Need Help?

Check the detailed guide: `database/TABLE_RENAME_MIGRATION.md`
