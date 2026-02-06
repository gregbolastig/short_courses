-- ============================================================================
-- MIGRATION: Allow Same Course with Different NC Levels (Simple Version)
-- ============================================================================
-- 
-- This is a simpler version that works with all MySQL versions.
-- Run this if the other migration script has issues.
--
-- ============================================================================

USE student_registration_db;

-- ============================================================================
-- STEP 1: Update course_applications table
-- ============================================================================

-- Drop the old unique constraint (ignore error if it doesn't exist)
-- Note: If you get an error here, the constraint might already be dropped
ALTER TABLE course_applications DROP INDEX unique_student_course;

-- If the above fails, try this alternative (for different MySQL versions):
-- ALTER TABLE course_applications DROP INDEX IF EXISTS unique_student_course;

-- Drop the new constraint if it already exists (in case of re-running)
ALTER TABLE course_applications DROP INDEX unique_student_course_nc;

-- Add new unique constraint that includes nc_level
ALTER TABLE course_applications 
ADD UNIQUE KEY unique_student_course_nc (student_id, course_id, nc_level);

-- ============================================================================
-- STEP 2: Update student_enrollments table
-- ============================================================================

-- Drop the old unique constraint
ALTER TABLE student_enrollments DROP INDEX unique_student_course_enrollment;

-- Add new unique constraint that includes nc_level
ALTER TABLE student_enrollments 
ADD UNIQUE KEY unique_student_course_enrollment (student_id, course_id, nc_level);

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- 
-- If you get errors about constraints not existing, that's okay - just continue.
-- The important part is that the new constraints are created.
-- 
-- Students can now:
-- ✅ Apply for the same course with different NC levels (e.g., NC I and NC II)
-- ✅ Be enrolled in the same course with different NC levels
-- 
-- ============================================================================
