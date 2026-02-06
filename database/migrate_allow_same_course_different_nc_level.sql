-- ============================================================================
-- MIGRATION: Allow Same Course with Different NC Levels
-- ============================================================================
-- 
-- This migration updates the unique constraints to allow students to apply for
-- or enroll in the same course with different NC levels.
--
-- BEFORE: Students could only apply/enroll once per course (regardless of NC level)
-- AFTER: Students can apply/enroll in the same course multiple times with different NC levels
--
-- IMPORTANT: Run this migration on your existing database to update the constraints
--
-- ============================================================================

USE student_registration_db;

-- ============================================================================
-- STEP 1: Update course_applications table
-- ============================================================================

-- Check and drop old constraint if it exists
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
    AND TABLE_NAME = 'course_applications' 
    AND CONSTRAINT_NAME = 'unique_student_course'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE course_applications DROP INDEX unique_student_course',
    'SELECT "Constraint unique_student_course does not exist, skipping drop" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and drop new constraint if it already exists (in case migration was partially run)
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
    AND TABLE_NAME = 'course_applications' 
    AND CONSTRAINT_NAME = 'unique_student_course_nc'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE course_applications DROP INDEX unique_student_course_nc',
    'SELECT "Constraint unique_student_course_nc does not exist, will create new" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add new unique constraint that includes nc_level
-- Note: NULL values are allowed and treated as distinct in MySQL unique constraints
ALTER TABLE course_applications 
ADD UNIQUE KEY unique_student_course_nc (student_id, course_id, nc_level);

-- ============================================================================
-- STEP 2: Update student_enrollments table
-- ============================================================================

-- Check and drop old constraint if it exists
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
    AND TABLE_NAME = 'student_enrollments' 
    AND CONSTRAINT_NAME = 'unique_student_course_enrollment'
);

SET @sql = IF(@constraint_exists > 0,
    'ALTER TABLE student_enrollments DROP INDEX unique_student_course_enrollment',
    'SELECT "Constraint unique_student_course_enrollment does not exist, skipping drop" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add new unique constraint that includes nc_level
ALTER TABLE student_enrollments 
ADD UNIQUE KEY unique_student_course_enrollment (student_id, course_id, nc_level);

-- ============================================================================
-- VERIFICATION: Check that constraints were created successfully
-- ============================================================================

SELECT 
    'course_applications' AS table_name,
    CONSTRAINT_NAME,
    'Constraint created successfully' AS status
FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
AND TABLE_NAME = 'course_applications' 
AND CONSTRAINT_NAME = 'unique_student_course_nc'

UNION ALL

SELECT 
    'student_enrollments' AS table_name,
    CONSTRAINT_NAME,
    'Constraint created successfully' AS status
FROM information_schema.TABLE_CONSTRAINTS 
WHERE CONSTRAINT_SCHEMA = 'student_registration_db' 
AND TABLE_NAME = 'student_enrollments' 
AND CONSTRAINT_NAME = 'unique_student_course_enrollment';

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- 
-- Students can now:
-- ✅ Apply for the same course with different NC levels (e.g., NC I and NC II)
-- ✅ Be enrolled in the same course with different NC levels
-- ✅ Apply for the same course with NULL nc_level multiple times (each NULL is distinct)
-- 
-- Still prevented:
-- ❌ Applying for the same course with the same NC level (duplicate)
-- ❌ Enrolling in the same course with the same NC level (duplicate)
-- 
-- ============================================================================
