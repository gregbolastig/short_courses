<?php
// ============================================================================
// PHP EXAMPLES FOR TWO-STAGE APPROVAL SYSTEM
// ============================================================================
// 
// Stage 1: Application Approval → Creates Enrollment
// Stage 2: Course Completion Approval → Issues Certificate
//
// ============================================================================

require_once '../config/database.php';

class TwoStageApprovalManager {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // ========================================================================
    // STAGE 1: APPLICATION MANAGEMENT
    // ========================================================================
    
    // 1. CREATE COURSE APPLICATION
    public function createCourseApplication($student_id, $course_id, $nc_level = null) {
        try {
            // Check if student already applied for this course
            $check_sql = "SELECT application_id FROM course_applications 
                         WHERE student_id = ? AND course_id = ?";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([$student_id, $course_id]);
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Student already applied for this course'];
            }
            
            // Insert new application
            $sql = "INSERT INTO course_applications (student_id, course_id, nc_level, status) 
                    VALUES (?, ?, ?, 'pending')";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$student_id, $course_id, $nc_level]);
            
            return [
                'success' => true, 
                'message' => 'Course application submitted successfully',
                'application_id' => $this->conn->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 2. APPROVE APPLICATION AND CREATE ENROLLMENT
    public function approveApplicationAndCreateEnrollment($application_id, $admin_id, $adviser_id = null, $training_start = null, $training_end = null, $notes = null) {
        try {
            $this->conn->beginTransaction();
            
            // Get application details
            $app_sql = "SELECT * FROM course_applications WHERE application_id = ? AND status = 'pending'";
            $app_stmt = $this->conn->prepare($app_sql);
            $app_stmt->execute([$application_id]);
            $application = $app_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                throw new Exception('Application not found or already processed');
            }
            
            // Update application status
            $update_app_sql = "UPDATE course_applications 
                              SET status = 'approved', 
                                  reviewed_by = ?, 
                                  reviewed_at = NOW(),
                                  notes = ?
                              WHERE application_id = ?";
            $update_stmt = $this->conn->prepare($update_app_sql);
            $update_stmt->execute([$admin_id, $notes, $application_id]);
            
            // Create enrollment record
            $enrollment_sql = "INSERT INTO student_enrollments 
                              (student_id, course_id, nc_level, adviser_id, training_start, training_end, 
                               application_id, enrolled_by, enrollment_status, completion_status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'enrolled', 'pending')";
            $enrollment_stmt = $this->conn->prepare($enrollment_sql);
            $enrollment_stmt->execute([
                $application['student_id'],
                $application['course_id'],
                $application['nc_level'],
                $adviser_id,
                $training_start,
                $training_end,
                $application_id,
                $admin_id
            ]);
            
            $enrollment_id = $this->conn->lastInsertId();
            
            // Update application with enrollment reference
            $link_sql = "UPDATE course_applications 
                        SET enrollment_created = TRUE, enrollment_id = ? 
                        WHERE application_id = ?";
            $link_stmt = $this->conn->prepare($link_sql);
            $link_stmt->execute([$enrollment_id, $application_id]);
            
            $this->conn->commit();
            
            return [
                'success' => true, 
                'message' => 'Application approved and enrollment created successfully',
                'enrollment_id' => $enrollment_id
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    // 3. GET PENDING APPLICATIONS
    public function getPendingApplications() {
        try {
            $sql = "SELECT 
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
                    ORDER BY ca.applied_at ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return [
                'success' => true,
                'applications' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // ========================================================================
    // STAGE 2: ENROLLMENT AND COMPLETION MANAGEMENT
    // ========================================================================
    
    // 4. GET ALL ENROLLMENTS (APPROVED APPLICATIONS)
    public function getAllEnrollments($status = null) {
        try {
            $sql = "SELECT 
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
                        se.completed_at,
                        a.adviser_name,
                        se.certificate_number
                    FROM student_enrollments se
                    INNER JOIN students s ON se.student_id = s.id
                    INNER JOIN courses c ON se.course_id = c.course_id
                    LEFT JOIN advisers a ON se.adviser_id = a.adviser_id";
            
            $params = [];
            if ($status) {
                $sql .= " WHERE se.enrollment_status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY se.enrolled_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'enrollments' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 5. MARK COURSE AS COMPLETED (READY FOR APPROVAL)
    public function markCourseCompleted($enrollment_id) {
        try {
            $sql = "UPDATE student_enrollments 
                    SET enrollment_status = 'completed', 
                        completed_at = NOW()
                    WHERE enrollment_id = ? AND enrollment_status = 'enrolled'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$enrollment_id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Course marked as completed, pending approval'];
            } else {
                return ['success' => false, 'message' => 'Enrollment not found or already completed'];
            }
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 6. GET PENDING COMPLETION APPROVALS
    public function getPendingCompletionApprovals() {
        try {
            $sql = "SELECT 
                        se.enrollment_id,
                        s.student_id,
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        s.email,
                        c.course_name,
                        se.nc_level,
                        se.training_start,
                        se.training_end,
                        se.completed_at,
                        a.adviser_name
                    FROM student_enrollments se
                    INNER JOIN students s ON se.student_id = s.id
                    INNER JOIN courses c ON se.course_id = c.course_id
                    LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
                    WHERE se.enrollment_status = 'completed' 
                      AND se.completion_status = 'pending'
                    ORDER BY se.completed_at ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return [
                'success' => true,
                'completions' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 7. APPROVE COURSE COMPLETION AND ISSUE CERTIFICATE
    public function approveCompletionAndIssueCertificate($enrollment_id, $admin_id, $certificate_number = null, $notes = null) {
        try {
            // Generate certificate number if not provided
            if (!$certificate_number) {
                $certificate_number = 'CERT-' . date('Y') . '-' . str_pad($enrollment_id, 6, '0', STR_PAD_LEFT);
            }
            
            $sql = "UPDATE student_enrollments 
                    SET completion_status = 'approved',
                        completion_approved_by = ?,
                        completion_approved_at = NOW(),
                        certificate_number = ?,
                        certificate_issued_at = NOW(),
                        completion_notes = ?
                    WHERE enrollment_id = ? 
                      AND enrollment_status = 'completed' 
                      AND completion_status = 'pending'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$admin_id, $certificate_number, $notes, $enrollment_id]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true, 
                    'message' => 'Course completion approved and certificate issued',
                    'certificate_number' => $certificate_number
                ];
            } else {
                return ['success' => false, 'message' => 'Enrollment not found or not ready for approval'];
            }
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 8. GET STUDENT'S ENROLLMENT HISTORY
    public function getStudentEnrollmentHistory($student_id) {
        try {
            $sql = "SELECT 
                        se.enrollment_id,
                        c.course_name,
                        se.nc_level,
                        se.enrollment_status,
                        se.completion_status,
                        se.training_start,
                        se.training_end,
                        se.enrolled_at,
                        se.completed_at,
                        se.certificate_number,
                        se.certificate_issued_at,
                        a.adviser_name,
                        ca.applied_at
                    FROM student_enrollments se
                    INNER JOIN courses c ON se.course_id = c.course_id
                    LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
                    LEFT JOIN course_applications ca ON se.application_id = ca.application_id
                    WHERE se.student_id = ?
                    ORDER BY se.enrolled_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$student_id]);
            
            return [
                'success' => true,
                'enrollments' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 9. GET COURSE STATISTICS WITH TWO-STAGE DATA
    public function getCourseStatisticsWithStages() {
        try {
            $sql = "SELECT 
                        c.course_name,
                        COUNT(DISTINCT ca.application_id) as total_applications,
                        SUM(CASE WHEN ca.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
                        SUM(CASE WHEN ca.status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
                        COUNT(DISTINCT se.enrollment_id) as total_enrollments,
                        SUM(CASE WHEN se.enrollment_status = 'enrolled' THEN 1 ELSE 0 END) as active_enrollments,
                        SUM(CASE WHEN se.enrollment_status = 'completed' AND se.completion_status = 'pending' THEN 1 ELSE 0 END) as pending_completions,
                        SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) as certified_students
                    FROM courses c
                    LEFT JOIN course_applications ca ON c.course_id = ca.course_id
                    LEFT JOIN student_enrollments se ON c.course_id = se.course_id
                    WHERE c.is_active = TRUE
                    GROUP BY c.course_id, c.course_name
                    ORDER BY total_applications DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            return [
                'success' => true,
                'statistics' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// ============================================================================
// USAGE EXAMPLES FOR TWO-STAGE APPROVAL
// ============================================================================

// Initialize the manager
$approvalManager = new TwoStageApprovalManager();

// STAGE 1 EXAMPLES:

// Example 1: Student applies for a course
/*
$result = $approvalManager->createCourseApplication(1, 1, 'NC II');
if ($result['success']) {
    echo "Application submitted with ID: " . $result['application_id'];
}
*/

// Example 2: Admin approves application and creates enrollment
/*
$result = $approvalManager->approveApplicationAndCreateEnrollment(
    1,              // application_id
    1,              // admin_id
    1,              // adviser_id
    '2024-03-01',   // training_start
    '2024-08-31',   // training_end
    'Approved for enrollment'
);
if ($result['success']) {
    echo "Enrollment created with ID: " . $result['enrollment_id'];
}
*/

// STAGE 2 EXAMPLES:

// Example 3: Mark course as completed (ready for approval)
/*
$result = $approvalManager->markCourseCompleted(1);
if ($result['success']) {
    echo "Course marked as completed, pending approval";
}
*/

// Example 4: Approve completion and issue certificate
/*
$result = $approvalManager->approveCompletionAndIssueCertificate(
    1,              // enrollment_id
    1,              // admin_id
    'CERT-2024-000001', // certificate_number (optional)
    'Successfully completed all requirements'
);
if ($result['success']) {
    echo "Certificate issued: " . $result['certificate_number'];
}
*/

// Example 5: Get pending completion approvals
/*
$result = $approvalManager->getPendingCompletionApprovals();
if ($result['success']) {
    foreach ($result['completions'] as $completion) {
        echo "Student: " . $completion['student_name'] . " - Course: " . $completion['course_name'] . "\n";
    }
}
*/

?>