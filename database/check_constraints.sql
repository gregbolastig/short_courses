-- ============================================================================
-- VERIFICATION SCRIPT: Check Current Constraint State
-- ============================================================================
-- 
-- Run this script to check what constraints currently exist on your tables.
-- This will help diagnose if the migration was applied correctly.
--
-- ============================================================================

USE student_registration_db;

-- Check constraints on course_applications table
SELECT 
    'course_applications' AS table_name,
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    'Current constraint' AS status
FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
AND TABLE_NAME = 'course_applications' 
AND CONSTRAINT_TYPE = 'UNIQUE'
ORDER BY CONSTRAINT_NAME;

-- Check constraints on student_enrollments table
SELECT 
    'student_enrollments' AS table_name,
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    'Current constraint' AS status
FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
AND TABLE_NAME = 'student_enrollments' 
AND CONSTRAINT_TYPE = 'UNIQUE'
ORDER BY CONSTRAINT_NAME;

-- Check the actual columns in the unique constraint
SELECT 
    tc.TABLE_NAME,
    tc.CONSTRAINT_NAME,
    GROUP_CONCAT(kcu.COLUMN_NAME ORDER BY kcu.ORDINAL_POSITION) AS constraint_columns
FROM information_schema.TABLE_CONSTRAINTS tc
JOIN information_schema.KEY_COLUMN_USAGE kcu 
    ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA 
    AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = 'student_registration_db' 
AND tc.TABLE_NAME IN ('course_applications', 'student_enrollments')
AND tc.CONSTRAINT_TYPE = 'UNIQUE'
GROUP BY tc.TABLE_NAME, tc.CONSTRAINT_NAME
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME;

-- ============================================================================
-- EXPECTED RESULTS:
-- ============================================================================
-- 
-- course_applications should have:
-- - unique_student_course_nc with columns: student_id, course_id, nc_level
-- 
-- student_enrollments should have:
-- - unique_student_course_enrollment with columns: student_id, course_id, nc_level
-- 
-- If you see 'unique_student_course' (without _nc), the migration hasn't been applied.
-- ============================================================================
