-- ============================================================================
-- SQL QUERIES FOR TWO-STAGE APPROVAL SYSTEM
-- ============================================================================
-- 
-- Stage 1: Application Management
-- Stage 2: Enrollment and Completion Management
--
-- ============================================================================

-- ============================================================================
-- STAGE 1: APPLICATION QUERIES
-- ============================================================================

-- 1. GET ALL PENDING APPLICATIONS (FOR ADMIN REVIEW)
SELECT 
    ca.application_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.email,
    s.contact_number,
    c.course_name,
    ca.nc_level,
    ca.applied_at,
    ca.notes
FROM course_applications ca
INNER JOIN students s ON ca.student_id = s.id
INNER JOIN courses c ON ca.course_id = c.course_id
WHERE ca.status = 'pending'
ORDER BY ca.applied_at ASC;

-- 2. GET STUDENT'S APPLICATION HISTORY
SELECT 
    ca.application_id,
    c.course_name,
    ca.nc_level,
    ca.status,
    ca.applied_at,
    ca.reviewed_at,
    ca.enrollment_created,
    u.username as reviewed_by
FROM course_applications ca
INNER JOIN courses c ON ca.course_id = c.course_id
LEFT JOIN users u ON ca.reviewed_by = u.id
WHERE ca.student_id = ? -- Replace with actual student ID
ORDER BY ca.applied_at DESC;

-- ============================================================================
-- STAGE 2: ENROLLMENT QUERIES
-- ============================================================================

-- 3. GET ALL ACTIVE ENROLLMENTS (APPROVED APPLICATIONS)
SELECT 
    se.enrollment_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.email,
    c.course_name,
    se.nc_level,
    se.enrollment_status,
    se.completion_status,
    se.training_start,
    se.training_end,
    se.enrolled_at,
    a.adviser_name,
    se.certificate_number
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
WHERE se.enrollment_status IN ('enrolled', 'completed')
ORDER BY se.enrolled_at DESC;

-- 4. GET PENDING COMPLETION APPROVALS
SELECT 
    se.enrollment_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.email,
    c.course_name,
    se.nc_level,
    se.training_start,
    se.training_end,
    se.completed_at,
    a.adviser_name,
    DATEDIFF(se.completed_at, se.training_start) as training_duration_days
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
WHERE se.enrollment_status = 'completed' 
  AND se.completion_status = 'pending'
ORDER BY se.completed_at ASC;

-- 5. GET CERTIFIED STUDENTS (COMPLETED AND APPROVED)
SELECT 
    se.enrollment_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.email,
    c.course_name,
    se.nc_level,
    se.certificate_number,
    se.certificate_issued_at,
    se.completion_approved_at,
    a.adviser_name,
    u.username as approved_by
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
LEFT JOIN users u ON se.completion_approved_by = u.id
WHERE se.completion_status = 'approved'
ORDER BY se.certificate_issued_at DESC;

-- ============================================================================
-- COMPREHENSIVE STUDENT VIEW
-- ============================================================================

-- 6. GET COMPLETE STUDENT JOURNEY (APPLICATIONS + ENROLLMENTS)
SELECT 
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.email,
    c.course_name,
    ca.applied_at,
    ca.status as application_status,
    se.enrollment_id,
    se.enrollment_status,
    se.completion_status,
    se.enrolled_at,
    se.completed_at,
    se.certificate_number,
    a.adviser_name
FROM students s
LEFT JOIN course_applications ca ON s.id = ca.student_id
LEFT JOIN courses c ON ca.course_id = c.course_id
LEFT JOIN student_enrollments se ON ca.enrollment_id = se.enrollment_id
LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
WHERE s.id = ? -- Replace with actual student ID
ORDER BY ca.applied_at DESC;

-- ============================================================================
-- STATISTICS AND REPORTING
-- ============================================================================

-- 7. GET COURSE STATISTICS WITH TWO-STAGE DATA
SELECT 
    c.course_name,
    COUNT(DISTINCT ca.application_id) as total_applications,
    SUM(CASE WHEN ca.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
    SUM(CASE WHEN ca.status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
    SUM(CASE WHEN ca.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
    COUNT(DISTINCT se.enrollment_id) as total_enrollments,
    SUM(CASE WHEN se.enrollment_status = 'enrolled' THEN 1 ELSE 0 END) as active_enrollments,
    SUM(CASE WHEN se.enrollment_status = 'completed' AND se.completion_status = 'pending' THEN 1 ELSE 0 END) as pending_completions,
    SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) as certified_students,
    ROUND(
        (SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) * 100.0) / 
        NULLIF(COUNT(DISTINCT se.enrollment_id), 0), 2
    ) as completion_rate_percent
FROM courses c
LEFT JOIN course_applications ca ON c.course_id = ca.course_id
LEFT JOIN student_enrollments se ON c.course_id = se.course_id
WHERE c.is_active = TRUE
GROUP BY c.course_id, c.course_name
ORDER BY total_applications DESC;

-- 8. GET MONTHLY ENROLLMENT AND COMPLETION STATISTICS
SELECT 
    DATE_FORMAT(se.enrolled_at, '%Y-%m') as month,
    COUNT(se.enrollment_id) as new_enrollments,
    SUM(CASE WHEN se.enrollment_status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
    SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) as certificates_issued,
    COUNT(DISTINCT se.student_id) as unique_students
FROM student_enrollments se
WHERE se.enrolled_at >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(se.enrolled_at, '%Y-%m')
ORDER BY month DESC;

-- 9. GET ADVISER WORKLOAD WITH COMPLETION RATES
SELECT 
    a.adviser_name,
    a.department,
    COUNT(se.enrollment_id) as total_students,
    SUM(CASE WHEN se.enrollment_status = 'enrolled' THEN 1 ELSE 0 END) as active_students,
    SUM(CASE WHEN se.enrollment_status = 'completed' THEN 1 ELSE 0 END) as completed_students,
    SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) as certified_students,
    ROUND(
        (SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) * 100.0) / 
        NULLIF(COUNT(se.enrollment_id), 0), 2
    ) as success_rate_percent,
    GROUP_CONCAT(DISTINCT c.course_name) as courses_handled
FROM advisers a
LEFT JOIN student_enrollments se ON a.adviser_id = se.adviser_id
LEFT JOIN courses c ON se.course_id = c.course_id
WHERE a.is_active = TRUE
GROUP BY a.adviser_id, a.adviser_name
ORDER BY total_students DESC;

-- ============================================================================
-- ADMIN DASHBOARD QUERIES
-- ============================================================================

-- 10. GET DASHBOARD SUMMARY COUNTS
SELECT 
    (SELECT COUNT(*) FROM course_applications WHERE status = 'pending') as pending_applications,
    (SELECT COUNT(*) FROM student_enrollments WHERE enrollment_status = 'enrolled') as active_enrollments,
    (SELECT COUNT(*) FROM student_enrollments WHERE enrollment_status = 'completed' AND completion_status = 'pending') as pending_completions,
    (SELECT COUNT(*) FROM student_enrollments WHERE completion_status = 'approved') as total_certificates,
    (SELECT COUNT(*) FROM students WHERE status = 'approved') as total_students,
    (SELECT COUNT(*) FROM courses WHERE is_active = TRUE) as active_courses;

-- 11. GET RECENT ACTIVITY (LAST 30 DAYS)
SELECT 
    'Application' as activity_type,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    c.course_name,
    ca.applied_at as activity_date,
    ca.status as current_status
FROM course_applications ca
INNER JOIN students s ON ca.student_id = s.id
INNER JOIN courses c ON ca.course_id = c.course_id
WHERE ca.applied_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)

UNION ALL

SELECT 
    'Enrollment' as activity_type,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    c.course_name,
    se.enrolled_at as activity_date,
    se.enrollment_status as current_status
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
WHERE se.enrolled_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)

UNION ALL

SELECT 
    'Completion' as activity_type,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    c.course_name,
    se.completed_at as activity_date,
    CONCAT('Completed - ', se.completion_status) as current_status
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
WHERE se.completed_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)

ORDER BY activity_date DESC
LIMIT 50;

-- ============================================================================
-- SEARCH AND FILTER QUERIES
-- ============================================================================

-- 12. SEARCH STUDENTS WITH THEIR CURRENT STATUS
SELECT 
    s.id,
    s.student_id,
    CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as full_name,
    s.email,
    s.contact_number,
    s.status as student_status,
    COUNT(DISTINCT ca.application_id) as total_applications,
    COUNT(DISTINCT se.enrollment_id) as total_enrollments,
    COUNT(DISTINCT CASE WHEN se.completion_status = 'approved' THEN se.enrollment_id END) as certificates_earned
FROM students s
LEFT JOIN course_applications ca ON s.id = ca.student_id
LEFT JOIN student_enrollments se ON s.id = se.student_id
WHERE (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)
GROUP BY s.id
ORDER BY s.last_name, s.first_name;

-- 13. GET STUDENTS BY COURSE AND ENROLLMENT STATUS
SELECT 
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.email,
    s.contact_number,
    se.nc_level,
    se.enrollment_status,
    se.completion_status,
    se.enrolled_at,
    se.training_start,
    se.training_end,
    a.adviser_name,
    se.certificate_number
FROM students s
INNER JOIN student_enrollments se ON s.id = se.student_id
INNER JOIN courses c ON se.course_id = c.course_id
LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
WHERE c.course_name = ? -- Replace with course name
  AND se.enrollment_status = ? -- Replace with status
ORDER BY se.enrolled_at;