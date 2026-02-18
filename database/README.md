# Database Documentation

## Primary Database File

**`../schema.sql`** (located in root directory) - This is the **single source of truth** for the database structure.

- Contains the complete, optimized database schema
- Includes all tables, relationships, indexes, and constraints
- Use this file to create or reset the database
- All database changes should be made here

## Migration Scripts

The PHP files in this directory are migration utilities for updating existing databases:

- `migrate_to_improved_schema.php` - Migrates from old schema to normalized structure
- `migrate_to_two_stage_approval.php` - Implements two-stage approval workflow
- `remove_unused_adviser_fields.php` - Cleans up legacy adviser fields
- `php_examples.php` - Example queries for common operations
- `two_stage_approval_examples.php` - Examples of the two-stage workflow

## Usage

### Fresh Installation
```bash
mysql -u root -p < schema.sql
```

Or import via phpMyAdmin.

### Updating Existing Database
Run the appropriate migration script from the config directory or use phpMyAdmin to import schema.sql (will drop and recreate tables).

## Important Notes

- **Do not create additional SQL files** - All schema changes go in `schema.sql`
- Migration scripts are for transitioning existing data only
- Always backup your database before running migrations
- The schema.sql file is the authoritative source for database structure
