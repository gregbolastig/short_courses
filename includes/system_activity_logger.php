<?php
/**
 * System Activity Logger
 * 
 * This class handles logging of all system activities for audit trail purposes.
 */

class SystemActivityLogger {
    private $conn;
    
    public function __construct($database_connection = null) {
        if ($database_connection) {
            $this->conn = $database_connection;
        } else {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $this->conn = $database->getConnection();
        }
    }
    
    /**
     * Log a system activity
     * 
     * @param string $activity_type Type of activity (login, logout, student_registration, etc.)
     * @param string $activity_description Detailed description of the activity
     * @param string $user_type Type of user (admin, student, system)
     * @param int|null $user_id ID of the user performing the action
     * @param string|null $entity_type Type of entity affected (student, course, etc.)
     * @param int|null $entity_id ID of the entity affected
     * @param string|null $ip_address IP address of the user
     * @param string|null $user_agent User agent string
     * @return bool Success status
     */
    public function log($activity_type, $activity_description, $user_type = 'system', $user_id = null, $entity_type = null, $entity_id = null, $ip_address = null, $user_agent = null) {
        try {
            // Get IP address if not provided
            if ($ip_address === null) {
                $ip_address = $this->getClientIP();
            }
            
            // Get user agent if not provided
            if ($user_agent === null && isset($_SERVER['HTTP_USER_AGENT'])) {
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
            }
            
            $sql = "INSERT INTO shortcourse_system_activities (
                        user_id, user_type, activity_type, activity_description, 
                        entity_type, entity_id, ip_address, user_agent
                    ) VALUES (
                        :user_id, :user_type, :activity_type, :activity_description,
                        :entity_type, :entity_id, :ip_address, :user_agent
                    )";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_type', $user_type);
            $stmt->bindParam(':activity_type', $activity_type);
            $stmt->bindParam(':activity_description', $activity_description);
            $stmt->bindParam(':entity_type', $entity_type);
            $stmt->bindParam(':entity_id', $entity_id, PDO::PARAM_INT);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':user_agent', $user_agent);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error but don't break the main functionality
            error_log("System Activity Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log admin login
     */
    public function logAdminLogin($user_id, $username) {
        return $this->log(
            'login',
            "Admin user '{$username}' logged into the system",
            'admin',
            $user_id
        );
    }
    
    /**
     * Log admin logout
     */
    public function logAdminLogout($user_id, $username) {
        return $this->log(
            'logout',
            "Admin user '{$username}' logged out of the system",
            'admin',
            $user_id
        );
    }
    
    /**
     * Log student registration
     */
    public function logStudentRegistration($student_id, $student_name, $email) {
        return $this->log(
            'student_registration',
            "New student '{$student_name}' ({$email}) registered in the system",
            'student',
            null,
            'student',
            $student_id
        );
    }
    
    /**
     * Log student approval
     */
    public function logStudentApproval($admin_user_id, $admin_username, $student_id, $student_name) {
        return $this->log(
            'student_approval',
            "Admin '{$admin_username}' approved student '{$student_name}'",
            'admin',
            $admin_user_id,
            'student',
            $student_id
        );
    }
    
    /**
     * Log student rejection
     */
    public function logStudentRejection($admin_user_id, $admin_username, $student_id, $student_name) {
        return $this->log(
            'student_rejection',
            "Admin '{$admin_username}' rejected student '{$student_name}'",
            'admin',
            $admin_user_id,
            'student',
            $student_id
        );
    }
    
    /**
     * Log course creation
     */
    public function logCourseCreation($admin_user_id, $admin_username, $course_id, $course_name) {
        return $this->log(
            'course_created',
            "Admin '{$admin_username}' created new course '{$course_name}'",
            'admin',
            $admin_user_id,
            'course',
            $course_id
        );
    }
    
    /**
     * Log course update
     */
    public function logCourseUpdate($admin_user_id, $admin_username, $course_id, $course_name) {
        return $this->log(
            'course_updated',
            "Admin '{$admin_username}' updated course '{$course_name}'",
            'admin',
            $admin_user_id,
            'course',
            $course_id
        );
    }
    
    /**
     * Log course deletion
     */
    public function logCourseDeletion($admin_user_id, $admin_username, $course_id, $course_name) {
        return $this->log(
            'course_deleted',
            "Admin '{$admin_username}' deleted course '{$course_name}'",
            'admin',
            $admin_user_id,
            'course',
            $course_id
        );
    }
    
    /**
     * Log course application
     */
    public function logCourseApplication($student_id, $student_name, $course_name, $application_id) {
        return $this->log(
            'course_application',
            "Student '{$student_name}' applied for course '{$course_name}'",
            'student',
            null,
            'course_application',
            $application_id
        );
    }
    
    /**
     * Log course application approval
     */
    public function logCourseApplicationApproval($admin_user_id, $admin_username, $application_id, $student_name, $course_name) {
        return $this->log(
            'course_application_approved',
            "Admin '{$admin_username}' approved course application for student '{$student_name}' in course '{$course_name}'",
            'admin',
            $admin_user_id,
            'course_application',
            $application_id
        );
    }
    
    /**
     * Log course application rejection
     */
    public function logCourseApplicationRejection($admin_user_id, $admin_username, $application_id, $student_name, $course_name) {
        return $this->log(
            'course_application_rejected',
            "Admin '{$admin_username}' rejected course application for student '{$student_name}' in course '{$course_name}'",
            'admin',
            $admin_user_id,
            'course_application',
            $application_id
        );
    }
    
    /**
     * Log student data update
     */
    public function logStudentUpdate($admin_user_id, $admin_username, $student_id, $student_name) {
        return $this->log(
            'student_updated',
            "Admin '{$admin_username}' updated student information for '{$student_name}'",
            'admin',
            $admin_user_id,
            'student',
            $student_id
        );
    }
    
    /**
     * Log student deletion
     */
    public function logStudentDeletion($admin_user_id, $admin_username, $student_id, $student_name) {
        return $this->log(
            'student_deleted',
            "Admin '{$admin_username}' deleted student '{$student_name}'",
            'admin',
            $admin_user_id,
            'student',
            $student_id
        );
    }
    
    /**
     * Log adviser creation
     */
    public function logAdviserCreation($admin_user_id, $admin_username, $adviser_id, $adviser_name) {
        return $this->log(
            'adviser_created',
            "Admin '{$admin_username}' created new adviser '{$adviser_name}'",
            'admin',
            $admin_user_id,
            'adviser',
            $adviser_id
        );
    }
    
    /**
     * Log adviser update
     */
    public function logAdviserUpdate($admin_user_id, $admin_username, $adviser_id, $adviser_name) {
        return $this->log(
            'adviser_updated',
            "Admin '{$admin_username}' updated adviser '{$adviser_name}'",
            'admin',
            $admin_user_id,
            'adviser',
            $adviser_id
        );
    }
    
    /**
     * Log adviser deletion
     */
    public function logAdviserDeletion($admin_user_id, $admin_username, $adviser_id, $adviser_name) {
        return $this->log(
            'adviser_deleted',
            "Admin '{$admin_username}' deleted adviser '{$adviser_name}'",
            'admin',
            $admin_user_id,
            'adviser',
            $adviser_id
        );
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

/**
 * Global function to easily log system activities
 */
function logSystemActivity($activity_type, $activity_description, $user_type = 'system', $user_id = null, $entity_type = null, $entity_id = null) {
    try {
        $logger = new SystemActivityLogger();
        return $logger->log($activity_type, $activity_description, $user_type, $user_id, $entity_type, $entity_id);
    } catch (Exception $e) {
        error_log("System Activity Logging Error: " . $e->getMessage());
        return false;
    }
}