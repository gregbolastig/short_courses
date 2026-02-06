-- ============================================================================
-- FIX COURSES TABLE - ADD MISSING COLUMNS
-- ============================================================================
-- 
-- This script fixes the courses table by adding missing columns
-- Run this in phpMyAdmin if you get "Unknown column" errors
--
-- ============================================================================

USE student_registration_db;

-- Check current courses table structure
DESCRIBE courses;

-- Add missing columns to existing courses table
ALTER TABLE courses 
ADD COLUMN IF NOT EXISTS course_code VARCHAR(20) UNIQUE AFTER course_name,
ADD COLUMN IF NOT EXISTS description TEXT AFTER course_code,
ADD COLUMN IF NOT EXISTS duration_hours INT AFTER description,
ADD COLUMN IF NOT EXISTS nc_levels VARCHAR(100) DEFAULT 'NC I,NC II' AFTER duration_hours;

-- Add indexes for new columns
CREATE INDEX idx_course_code ON courses(course_code);

-- Now insert the sample courses data
INSERT IGNORE INTO courses (course_name, course_code, description, duration_hours, nc_levels) VALUES 
('Automotive Servicing', 'AUTO-001', 'Complete automotive maintenance and repair training program', 480, 'NC I,NC II'),
('Computer Systems Servicing', 'CSS-001', 'Computer hardware and software troubleshooting and maintenance', 320, 'NC II'),
('Electrical Installation and Maintenance', 'EIM-001', 'Electrical systems installation, maintenance, and safety procedures', 400, 'NC I,NC II'),
('Welding', 'WELD-001', 'Arc welding, gas welding, and metal fabrication techniques', 360, 'NC I,NC II'),
('Cookery', 'COOK-001', 'Professional cooking, food preparation, and kitchen management', 280, 'NC II'),
('Carpentry', 'CARP-001', 'Wood working, furniture making, and construction carpentry', 320, 'NC I,NC II'),
('Masonry', 'MASON-001', 'Concrete work, brick laying, and construction masonry', 300, 'NC I,NC II'),
('Plumbing', 'PLUMB-001', 'Pipe installation, repair, and plumbing system maintenance', 280, 'NC I,NC II');

-- Verify the results
SELECT * FROM courses;

-- ============================================================================
-- ALTERNATIVE: DROP AND RECREATE COURSES TABLE
-- ============================================================================
-- If the above doesn't work, uncomment and run these commands:

-- DROP TABLE IF EXISTS courses;
-- 
-- CREATE TABLE courses (
--     course_id INT AUTO_INCREMENT PRIMARY KEY,
--     course_name VARCHAR(200) NOT NULL UNIQUE,
--     course_code VARCHAR(20) UNIQUE,
--     description TEXT,
--     duration_hours INT,
--     nc_levels VARCHAR(100) DEFAULT 'NC I,NC II',
--     is_active BOOLEAN DEFAULT TRUE,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     INDEX idx_course_name (course_name),
--     INDEX idx_course_code (course_code),
--     INDEX idx_active (is_active)
-- );
-- 
-- INSERT INTO courses (course_name, course_code, description, duration_hours, nc_levels) VALUES 
-- ('Automotive Servicing', 'AUTO-001', 'Complete automotive maintenance and repair training program', 480, 'NC I,NC II'),
-- ('Computer Systems Servicing', 'CSS-001', 'Computer hardware and software troubleshooting and maintenance', 320, 'NC II'),
-- ('Electrical Installation and Maintenance', 'EIM-001', 'Electrical systems installation, maintenance, and safety procedures', 400, 'NC I,NC II'),
-- ('Welding', 'WELD-001', 'Arc welding, gas welding, and metal fabrication techniques', 360, 'NC I,NC II'),
-- ('Cookery', 'COOK-001', 'Professional cooking, food preparation, and kitchen management', 280, 'NC II'),
-- ('Carpentry', 'CARP-001', 'Wood working, furniture making, and construction carpentry', 320, 'NC I,NC II'),
-- ('Masonry', 'MASON-001', 'Concrete work, brick laying, and construction masonry', 300, 'NC I,NC II'),
-- ('Plumbing', 'PLUMB-001', 'Pipe installation, repair, and plumbing system maintenance', 280, 'NC I,NC II');