# Database Folder

This folder contains database documentation for the Student Registration System.

## Database Setup

For a fresh installation, import the schema file from the root directory:
```
schema.sql
```

This will:
- Create all 8 database tables
- Insert sample faculty data
- Set up all indexes and foreign keys

## Database Structure

The system uses 8 tables (7 with `shortcourse_` prefix + faculty):

1. **shortcourse_users** - Admin user authentication
2. **shortcourse_students** - Student information and registration
3. **shortcourse_courses** - Course catalog with NC levels
4. **faculty** - Faculty members (instructors/advisers)
5. **shortcourse_course_applications** - Student course applications
6. **shortcourse_system_activities** - System activity logging
7. **shortcourse_checklist** - Document requirements checklist
8. **shortcourse_bookkeeping_receipts** - Receipt tracking

## Import Instructions

### Using phpMyAdmin:
1. Open phpMyAdmin
2. Create database: `grading_system`
3. Select the database
4. Click "Import" tab
5. Choose `schema.sql` file
6. Click "Go"

### Using MySQL Command Line:
```bash
mysql -u root -p < schema.sql
```

## Sample Data

The schema includes sample faculty data:
- Mark Miller Abrera Polinar (Employee #2022-0585)
- Mark Anthony Rapuson Guerrero (Employee #1998-8528)

## Notes

- The `faculty` table does NOT use the `shortcourse_` prefix
- All other tables use the `shortcourse_` prefix for organization
- Foreign keys are set up for data integrity
- Indexes are optimized for performance
