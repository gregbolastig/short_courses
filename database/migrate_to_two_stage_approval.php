<?php
// ============================================================================
// MIGRATION SCRIPT - UPGRADE TO TWO-STAGE APPROVAL SYSTEM
// ============================================================================
// 
// This script migrates your existing database to support two-stage approval:
// Stage 1: Application Approval → Creates Enrollment
// Stage 2: Course Completion Approval → Issues Certificate
//
// ============================================================================

require_once '../config/database.php';

class TwoStageApprovalMigration {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function migrate() {
        echo "<h2>Two-Stage Approval System Migration</h2>\n";
        
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Step 1: Create student_enrollments table
            $this->createStudentEnrollmentsTable();
            
            // Step 2: Update course_applications table
            $this->updateCourseApplicationsTable();
            
            // Step 3: Migrate existing approved applications to enrollments
            $this->migrateApprovedApplicationsToEnrollments();
            
            // Step 4: Remove course-related fields from students table
            $this->cleanupStudentsTable();
            
            // Step 5: Create indexes and constraints
            $this->createIndexesAndConstraints();
            
            // Commit transaction
            $this->conn->commit();
            
            echo "<p style='color: green;'>✓ Two-stage approval migration completed successfully!</p>\n";
            $this->showMigrationSummary();
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            echo "<p style='color: red;'>✗ Migration failed: " . $e->getMessage() . "</p>\n";
        }
    }
    
    private function createStudentEnrollmentsTable() {
        echo "<h3>Creating student_enrollments table...</h3>\n";
        
        // Check if table already exists
        $stmt = $this->conn->query("SHOW TABLES LIKE 'student_enrollments'");
        if ($stmt->fetch()) {
            echo "<p>⚠ student_enrollments table already exists, skipping creation</p>\n";
            return;
        }
        
        $sql = "CREATE TABLE student_enrollments (
            enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            nc_level VARCHAR(10) NULL,
            adviser_id INT NULL,
            training_start DATE NULL,
            training_end DATE NULL,
            enrollment_status ENUM('enrolled', 'ongoing', 'completed', 'dropped') DEFAULT 'enrolled',
            completion_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            
            -- Enrollment details (copied from approved application)
            application_id INT NOT NULL,
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            enrolled_by INT NULL,
            
            -- Completion approval details
            completed_at TIMESTAMP NULL,
            completion_approved_by INT NULL,
            completion_approved_at TIMESTAMP NULL,
            completion_notes TEXT NULL,
            
            -- Certificate details
            certificate_number VARCHAR(50) NULL,
            certificate_issued_at TIMESTAMP NULL,
            
            -- Foreign Key Constraints
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
            FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL,
            FOREIGN KEY (application_id) REFERENCES course_applications(application_id) ON DELETE CASCADE,
            FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (completion_approved_by) REFERENCES users(id) ON DELETE SET NULL,
            
            -- Indexes for performance
            INDEX idx_student_id (student_id),
            INDEX idx_course_id (course_id),
            INDEX idx_enrollment_status (enrollment_status),
            INDEX idx_completion_status (completion_status),
            INDEX idx_enrolled_at (enrolled_at),
            INDEX idx_application_id (application_id),
            
            -- Unique constraint to prevent duplicate enrollments
            UNIQUE KEY unique_student_course_enrollment (student_id, course_id)
        )";
        
        $this->conn->exec($sql);
        echo "<p>✓ student_enrollments table created</p>\n";
    }
    
    private function updateCourseApplicationsTable() {
        echo "<h3>Updating course_applications table...</h3>\n";
        
        // Add enrollment tracking columns
        $columns_to_add = [
            'enrollment_created' => "ALTER TABLE course_applications ADD COLUMN enrollment_created BOOLEAN DEFAULT FALSE",
            'enrollment_id' => "ALTER TABLE course_applications ADD COLUMN enrollment_id INT NULL"
        ];
        
        foreach ($columns_to_add as $column => $sql) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications LIKE '$column'");
            if (!$stmt->fetch()) {
                $this->conn->exec($sql);
                echo "<p>✓ Added $column column to course_applications</p>\n";
            }
        }
        
        // Remove fields that are now in student_enrollments
        $columns_to_remove = ['adviser_id', 'training_start', 'training_end'];
        foreach ($columns_to_remove as $column) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications LIKE '$column'");
            if ($stmt->fetch()) {
                // Don't remove yet - we'll use them for migration
                echo "<p>⚠ Keeping $column for migration (will remove later)</p>\n";
            }
        }
        
        // Update status enum to remove 'completed' (now handled in enrollments)
        $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications WHERE Field = 'status'");
        $column = $stmt->fetch();
        if ($column && strpos($column['Type'], 'completed') !== false) {
            $this->conn->exec("ALTER TABLE course_applications MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
            echo "<p>✓ Updated status enum to remove 'completed'</p>\n";
        }
    }
    
    private function migrateApprovedApplicationsToEnrollments() {
        echo "<h3>Migrating approved applications to enrollments...</h3>\n";
        
        // Get all approved applications that don't have enrollments yet
        $sql = "SELECT ca.*, c.course_id 
                FROM course_applications ca
                INNER JOIN courses c ON ca.course_id = c.course_id
                WHERE ca.status = 'approved' 
                  AND (ca.enrollment_created IS NULL OR ca.enrollment_created = FALSE)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $approved_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migrated_count = 0;
        
        foreach ($approved_applications as $app) {
            try {
                // Create enrollment record
                $enrollment_sql = "INSERT INTO student_enrollments 
                                  (student_id, course_id, nc_level, adviser_id, training_start, training_end, 
                                   application_id, enrolled_by, enrolled_at, enrollment_status, completion_status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'enrolled', 'pending')";
                
                $enrollment_stmt = $this->conn->prepare($enrollment_sql);
                $enrollment_stmt->execute([
                    $app['student_id'],
                    $app['course_id'],
                    $app['nc_level'],
                    $app['adviser_id'] ?? null,
                    $app['training_start'],
                    $app['training_end'],
                    $app['application_id'],
                    $app['reviewed_by'],
                    $app['reviewed_at'] ?? date('Y-m-d H:i:s')
                ]);
                
                $enrollment_id = $this->conn->lastInsertId();
                
                // Update application with enrollment reference
                $update_sql = "UPDATE course_applications 
                              SET enrollment_created = TRUE, enrollment_id = ? 
                              WHERE application_id = ?";
                $update_stmt = $this->conn->prepare($update_sql);
                $update_stmt->execute([$enrollment_id, $app['application_id']]);
                
                $migrated_count++;
                
            } catch (Exception $e) {
                echo "<p style='color: orange;'>⚠ Failed to migrate application {$app['application_id']}: " . $e->getMessage() . "</p>\n";
            }
        }
        
        echo "<p>✓ Migrated $migrated_count approved applications to enrollments</p>\n";
    }
    
    private function cleanupStudentsTable() {
        echo "<h3>Cleaning up students table...</h3>\n";
        
        // Remove course-related fields from students table (they're now in enrollments)
        $fields_to_remove = ['course', 'nc_level', 'training_start', 'training_end', 'adviser'];
        
        foreach ($fields_to_remove as $field) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM students LIKE '$field'");
            if ($stmt->fetch()) {
                try {
                    $this->conn->exec("ALTER TABLE students DROP COLUMN $field");
                    echo "<p>✓ Removed $field column from students table</p>\n";
                } catch (Exception $e) {
                    echo "<p style='color: orange;'>⚠ Could not remove $field column: " . $e->getMessage() . "</p>\n";
                }
            }
        }
        
        // Also remove the old fields from course_applications now that we've migrated
        $ca_fields_to_remove = ['adviser_id', 'training_start', 'training_end'];
        foreach ($ca_fields_to_remove as $field) {
            $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications LIKE '$field'");
            if ($stmt->fetch()) {
                try {
                    $this->conn->exec("ALTER TABLE course_applications DROP COLUMN $field");
                    echo "<p>✓ Removed $field column from course_applications table</p>\n";
                } catch (Exception $e) {
                    echo "<p style='color: orange;'>⚠ Could not remove $field column from course_applications: " . $e->getMessage() . "</p>\n";
                }
            }
        }
    }
    
    private function createIndexesAndConstraints() {
        echo "<h3>Creating indexes and constraints...</h3>\n";
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_ca_enrollment_created ON course_applications(enrollment_created)",
            "CREATE INDEX IF NOT EXISTS idx_se_completion_dates ON student_enrollments(completed_at, completion_approved_at)",
            "CREATE INDEX IF NOT EXISTS idx_se_certificate ON student_enrollments(certificate_number)"
        ];
        
        foreach ($indexes as $index_sql) {
            try {
                $this->conn->exec($index_sql);
            } catch (Exception $e) {
                // Ignore if index already exists
            }
        }
        
        echo "<p>✓ Indexes created</p>\n";
    }
    
    private function showMigrationSummary() {
        echo "<h3>Migration Summary</h3>\n";
        
        // Get counts
        $stats = [];
        
        $stmt = $this->conn->query("SELECT COUNT(*) FROM course_applications WHERE status = 'pending'");
        $stats['pending_applications'] = $stmt->fetchColumn();
        
        $stmt = $this->conn->query("SELECT COUNT(*) FROM course_applications WHERE status = 'approved'");
        $stats['approved_applications'] = $stmt->fetchColumn();
        
        $stmt = $this->conn->query("SELECT COUNT(*) FROM student_enrollments");
        $stats['total_enrollments'] = $stmt->fetchColumn();
        
        $stmt = $this->conn->query("SELECT COUNT(*) FROM student_enrollments WHERE enrollment_status = 'enrolled'");
        $stats['active_enrollments'] = $stmt->fetchColumn();
        
        $stmt = $this->conn->query("SELECT COUNT(*) FROM student_enrollments WHERE completion_status = 'pending'");
        $stats['pending_completions'] = $stmt->fetchColumn();
        
        echo "<table border='1' cellpadding='5' cellspacing='0' style='background-color: white; margin: 10px 0;'>";
        echo "<tr style='background-color: #800000; color: white;'><th>Metric</th><th>Count</th></tr>";
        echo "<tr><td>Pending Applications</td><td>{$stats['pending_applications']}</td></tr>";
        echo "<tr><td>Approved Applications</td><td>{$stats['approved_applications']}</td></tr>";
        echo "<tr><td>Total Enrollments</td><td>{$stats['total_enrollments']}</td></tr>";
        echo "<tr><td>Active Enrollments</td><td>{$stats['active_enrollments']}</td></tr>";
        echo "<tr><td>Pending Completions</td><td>{$stats['pending_completions']}</td></tr>";
        echo "</table>";
        
        echo "<h3>Next Steps:</h3>";
        echo "<ul>";
        echo "<li><strong>Stage 1:</strong> Review and approve pending applications at <a href='../admin/pending_applications.php'>Pending Applications</a></li>";
        echo "<li><strong>Stage 2:</strong> Manage enrollments and approve completions at <a href='../admin/enrollments.php'>Student Enrollments</a></li>";
        echo "<li><strong>Certificates:</strong> Issue certificates for completed courses at <a href='../admin/completion_approvals.php'>Completion Approvals</a></li>";
        echo "<li><strong>Dashboard:</strong> View overall statistics at <a href='../admin/dashboard.php'>Admin Dashboard</a></li>";
        echo "</ul>";
        
        echo "<h3>New Workflow:</h3>";
        echo "<ol>";
        echo "<li><strong>Student applies</strong> for a course → Creates record in <code>course_applications</code></li>";
        echo "<li><strong>Admin approves application</strong> → Creates record in <code>student_enrollments</code></li>";
        echo "<li><strong>Student completes course</strong> → Updates <code>enrollment_status</code> to 'completed'</li>";
        echo "<li><strong>Admin approves completion</strong> → Updates <code>completion_status</code> and issues certificate</li>";
        echo "</ol>";
    }
}

// Run migration if accessed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    echo "<!DOCTYPE html><html><head><title>Two-Stage Approval Migration</title></head><body>";
    echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } table { border-collapse: collapse; } code { background: #f0f0f0; padding: 2px 4px; }</style>";
    
    $migration = new TwoStageApprovalMigration();
    $migration->migrate();
    
    echo "</body></html>";
}

?>