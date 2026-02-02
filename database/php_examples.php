<?php
// ============================================================================
// PHP EXAMPLES FOR STUDENT COURSE APPLICATION SYSTEM
// ============================================================================

require_once '../config/database.php';

class CourseApplicationManager {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    // 1. INSERT NEW COURSE APPLICATION
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
    
    // 2. GET STUDENT'S COURSE APPLICATIONS
    public function getStudentApplications($student_id) {
        try {
            $sql = "SELECT 
                        ca.application_id,
                        c.course_name,
                        ca.nc_level,
                        ca.status,
                        ca.applied_at,
                        ca.training_start,
                        ca.training_end,
                        a.adviser_name,
                        ca.notes
                    FROM course_applications ca
                    INNER JOIN courses c ON ca.course_id = c.course_id
                    LEFT JOIN advisers a ON ca.adviser_id = a.adviser_id
                    WHERE ca.student_id = ?
                    ORDER BY ca.applied_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$student_id]);
            
            return [
                'success' => true,
                'applications' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 3. GET ALL PENDING APPLICATIONS (FOR ADMIN)
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
    
    // 4. APPROVE/REJECT COURSE APPLICATION
    public function updateApplicationStatus($application_id, $status, $admin_id, $adviser_id = null, $training_start = null, $training_end = null, $notes = null) {
        try {
            $sql = "UPDATE course_applications 
                    SET status = ?, 
                        reviewed_by = ?, 
                        reviewed_at = NOW(),
                        adviser_id = ?,
                        training_start = ?,
                        training_end = ?,
                        notes = ?
                    WHERE application_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$status, $admin_id, $adviser_id, $training_start, $training_end, $notes, $application_id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Application status updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Application not found'];
            }
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 5. GET STUDENTS BY COURSE
    public function getStudentsByCourse($course_id, $status = null) {
        try {
            $sql = "SELECT 
                        s.id,
                        s.student_id,
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        s.email,
                        s.contact_number,
                        ca.nc_level,
                        ca.status,
                        ca.applied_at,
                        ca.training_start,
                        ca.training_end,
                        a.adviser_name
                    FROM students s
                    INNER JOIN course_applications ca ON s.id = ca.student_id
                    LEFT JOIN advisers a ON ca.adviser_id = a.adviser_id
                    WHERE ca.course_id = ?";
            
            $params = [$course_id];
            
            if ($status) {
                $sql .= " AND ca.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY ca.applied_at";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 6. GET AVAILABLE COURSES
    public function getAvailableCourses() {
        try {
            $sql = "SELECT course_id, course_name, course_code, description, duration_hours, nc_levels
                    FROM courses 
                    WHERE is_active = TRUE 
                    ORDER BY course_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON nc_levels
            foreach ($courses as &$course) {
                $course['nc_levels'] = json_decode($course['nc_levels'], true) ?: [];
            }
            
            return [
                'success' => true,
                'courses' => $courses
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // 7. GET COURSE STATISTICS
    public function getCourseStatistics() {
        try {
            $sql = "SELECT 
                        c.course_name,
                        COUNT(ca.application_id) as total_applications,
                        SUM(CASE WHEN ca.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN ca.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN ca.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                        SUM(CASE WHEN ca.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                    FROM courses c
                    LEFT JOIN course_applications ca ON c.course_id = ca.course_id
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
    
    // 8. SEARCH STUDENTS WITH APPLICATIONS
    public function searchStudents($search_term) {
        try {
            $search_param = "%{$search_term}%";
            
            $sql = "SELECT 
                        s.id,
                        s.student_id,
                        CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) as full_name,
                        s.email,
                        s.contact_number,
                        s.status,
                        COUNT(ca.application_id) as total_applications
                    FROM students s
                    LEFT JOIN course_applications ca ON s.id = ca.student_id
                    WHERE (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)
                    GROUP BY s.id
                    ORDER BY s.last_name, s.first_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
            
            return [
                'success' => true,
                'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// ============================================================================
// USAGE EXAMPLES
// ============================================================================

// Initialize the manager
$courseManager = new CourseApplicationManager();

// Example 1: Create a new course application
/*
$result = $courseManager->createCourseApplication(1, 1, 'NC II');
if ($result['success']) {
    echo "Application created with ID: " . $result['application_id'];
} else {
    echo "Error: " . $result['message'];
}
*/

// Example 2: Get student's applications
/*
$student_id = 1;
$result = $courseManager->getStudentApplications($student_id);
if ($result['success']) {
    foreach ($result['applications'] as $app) {
        echo "Course: " . $app['course_name'] . " - Status: " . $app['status'] . "\n";
    }
}
*/

// Example 3: Get pending applications for admin review
/*
$result = $courseManager->getPendingApplications();
if ($result['success']) {
    foreach ($result['applications'] as $app) {
        echo "Student: " . $app['student_name'] . " - Course: " . $app['course_name'] . "\n";
    }
}
*/

// Example 4: Approve an application
/*
$result = $courseManager->updateApplicationStatus(
    1,              // application_id
    'approved',     // status
    1,              // admin_id
    1,              // adviser_id
    '2024-03-01',   // training_start
    '2024-08-31',   // training_end
    'Approved for enrollment' // notes
);
*/

// Example 5: Get course statistics
/*
$result = $courseManager->getCourseStatistics();
if ($result['success']) {
    foreach ($result['statistics'] as $stat) {
        echo $stat['course_name'] . ": " . $stat['total_applications'] . " applications\n";
    }
}
*/

?>