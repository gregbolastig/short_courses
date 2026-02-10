# First Course Migration to course_applications Table

## Problem
Previously, when a student's first course was approved during initial registration, the course data was stored in the `students` table fields (`course`, `nc_level`, `training_start`, `training_end`, `adviser`). When the student completed additional courses, these fields would be overwritten with the latest course data, causing the first course record to dynamically change in the Course History display.

## Solution
The first course registration now creates a record in the `course_applications` table, just like all subsequent courses. This ensures:
- All courses are stored consistently in one place
- Each course maintains its own independent record
- Course history displays correctly without dynamic updates
- No data is lost when new courses are completed

## Changes Made

### 1. Dashboard Approval Process (`admin/dashboard.php`)
**Before:**
```php
// Updated students table with course data
UPDATE students SET 
    status = 'completed',
    course = :course,
    nc_level = :nc_level,
    training_start = :training_start,
    training_end = :training_end,
    adviser = :adviser
WHERE id = :id
```

**After:**
```php
// 1. Create course_application record
INSERT INTO course_applications 
    (student_id, course_id, nc_level, training_start, training_end, adviser, status) 
VALUES (:student_id, :course_id, :nc_level, :training_start, :training_end, :adviser, 'completed')

// 2. Update student status only (no course data)
UPDATE students SET 
    status = 'completed',
    approved_by = :admin_id,
    approved_at = NOW()
WHERE id = :id
```

### 2. Profile Display (`student/profile/profile.php`)
- Removed legacy support code that pulled course data from `students` table
- All courses now display from `course_applications` table only
- Consistent display logic for all courses

## Migration for Existing Data

### Run the Migration Script
For students who already have their first course stored in the `students` table:

```bash
php database/migrate_first_course_to_applications.php
```

This script will:
1. Find all students with course data in `students` table
2. Create corresponding `course_applications` records
3. Preserve all original data (training dates, adviser, etc.)
4. Keep `students` table data for backward compatibility

### What the Script Does
- ✓ Creates `course_applications` records for first courses
- ✓ Uses registration date as the application date
- ✓ Sets status to 'completed' with proper timestamps
- ✓ Maintains data integrity with transactions
- ✓ Provides detailed progress output

### Safety
- Uses database transactions (rollback on error)
- Does NOT delete data from `students` table (backward compatible)
- Can be run multiple times safely (checks for existing records)
- Provides detailed logging of all operations

## Testing

### Before Migration
1. Check a student profile with multiple courses
2. Note if the first course shows incorrect/latest data

### After Migration
1. Run the migration script
2. Refresh the student profile
3. Verify all courses show their correct, independent data
4. Verify first course no longer changes when new courses are added

### New Registrations
1. Approve a new student registration
2. Check their profile - first course should appear in Course History
3. Apply for a second course
4. Approve the second course
5. Verify both courses show independently with correct data

## Database Schema

### course_applications Table
The table should have these fields for first course data:
- `student_id` - Links to students table
- `course_id` - Links to courses table
- `nc_level` - NC Level for this course
- `training_start` - Training start date
- `training_end` - Training end date
- `adviser` - Assigned adviser name
- `status` - 'completed' for approved first courses
- `reviewed_by` - Admin who approved
- `reviewed_at` - Approval timestamp
- `applied_at` - Application date (uses registration date for first course)

## Rollback (if needed)

If you need to rollback this change:

1. Restore the old `admin/dashboard.php` code that updates `students` table
2. Restore the legacy support code in `student/profile/profile.php`
3. Optionally delete migrated `course_applications` records:
```sql
DELETE FROM course_applications 
WHERE student_id IN (
    SELECT id FROM students 
    WHERE course IS NOT NULL
) 
AND applied_at = (
    SELECT created_at FROM students WHERE id = course_applications.student_id
);
```

## Benefits

1. **Data Consistency**: All courses stored in one table
2. **No Data Loss**: First course data never gets overwritten
3. **Accurate History**: Course history displays correctly
4. **Scalability**: Easy to add more courses without conflicts
5. **Maintainability**: Single source of truth for course data

## Notes

- The `students` table still has course-related fields for backward compatibility
- These fields may be used by other parts of the system
- Future development should use `course_applications` table exclusively
- Consider deprecating `students` table course fields in future versions
