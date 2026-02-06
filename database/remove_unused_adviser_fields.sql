-- ============================================================================
-- REMOVE UNUSED ADVISER FIELDS - DATABASE MIGRATION
-- ============================================================================
-- 
-- This script removes unused fields from the advisers table:
-- - email (not used in admin interface)
-- - phone (not used in admin interface) 
-- - department (not used in admin interface)
-- - specialization (not used in admin interface)
--
-- Only adviser_name is actually used in the system
-- ============================================================================

USE student_registration_db;

-- Backup current advisers table structure (optional)
-- CREATE TABLE advisers_backup AS SELECT * FROM advisers;

-- Remove unused columns from advisers table
ALTER TABLE advisers 
DROP COLUMN IF EXISTS email,
DROP COLUMN IF EXISTS phone,
DROP COLUMN IF EXISTS department,
DROP COLUMN IF EXISTS specialization;

-- Verify the table structure
DESCRIBE advisers;

-- Show remaining data
SELECT * FROM advisers LIMIT 5;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Count total advisers
SELECT COUNT(*) as total_advisers FROM advisers;

-- Show simplified table structure
SHOW CREATE TABLE advisers;

-- ============================================================================
-- ROLLBACK SCRIPT (if needed)
-- ============================================================================
-- To rollback this migration, run:
-- 
-- ALTER TABLE advisers 
-- ADD COLUMN email VARCHAR(150) UNIQUE AFTER adviser_name,
-- ADD COLUMN phone VARCHAR(20) AFTER email,
-- ADD COLUMN department VARCHAR(100) AFTER phone,
-- ADD COLUMN specialization VARCHAR(200) AFTER department;
-- ============================================================================