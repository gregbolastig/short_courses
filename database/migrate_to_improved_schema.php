<?php
// ============================================================================
// MIGRATION SCRIPT - UPGRADE TO IMPROVED SCHEMA
// ============================================================================
// 
// This script safely migrates your existing database to the improved schema
// with proper foreign key relationships
//
// ============================================================================

require_once '../config/database.php';

class SchemaMigration {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function migrate() {
        echo "<h2>Database Schema Migration</h2>\n";
        
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Step 1: Update courses table
            $this->updateCoursesTable();
            
            // Step 2: Update course_applications table
            $this->updateCourseApplicationsTable();
            
            // Step 3: Add sample data if needed
            $this->addSampleData();
            
            // Step 4: Create indexes
            $this->createIndexes();
            
            // Commit transaction
            $this->conn->commit();
            
            echo "<p style='color: green;'>✓ Migration completed successfully!</p>\n";
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            echo "<p style='color: red;'>✗ Migration failed: " . $e->getMessage() . "</p>\n";
        }
    }
    
    private function updateCoursesTable() {
        echo "<h3>Updating courses table...</h3>\n";
        
        // Check if course_code column exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM courses LIKE 'course_code'");
        if (!$stmt->fetch()) {
            $this->conn->exec("ALTER TABLE courses ADD COLUMN course_code VARCHAR(20) UNIQUE AFTER course_name");
            echo "<p>✓ Added course_code column</p>\n";
        }
        
        // Check if description column exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM courses LIKE 'description'");
        if (!$stmt->fetch()) {
            $this->conn->exec("ALTER TABLE courses ADD COLUMN description TEXT AFTER course_code");
            echo "<p>✓ Added description column</p>\n";
        }
        
        // Check if duration_hours column exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM courses LIKE 'duration_hours'");
        if (!$stmt->fetch()) {
            $this->conn->exec("ALTER TABLE courses ADD COLUMN duration_hours INT AFTER description");
            echo "<p>✓ Added duration_hours column</p>\n";
        }
        
        // Check if nc_levels column exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM courses LIKE 'nc_levels'");
        if (!$stmt->fetch()) {
            $this->conn->exec("ALTER TABLE courses ADD COLUMN nc_levels JSON AFTER duration_hours");
            echo "<p>✓ Added nc_levels column</p>\n";
        }
    }
    
    private function updateCourseApplicationsTable() {
        echo "<h3>Updating course_applications table...</h3>\n";
        
        // Check if course_id column exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications LIKE 'course_id'");
        if (!$stmt->fetch()) {
            // Add course_id column
            $this->conn->exec("ALTER TABLE course_applications ADD COLUMN course_id INT AFTER student_id");
            echo "<p>✓ Added course_id column</p>\n";
            
            // Migrate existing course_name data to course_id
            $this->migrateCourseNames();
        }
        
        // Check if adviser_id column exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications LIKE 'adviser_id'");
        if (!$stmt->fetch()) {
            $this->conn->exec("ALTER TABLE course_applications ADD COLUMN adviser_id INT AFTER nc_level");
            echo "<p>✓ Added adviser_id column</p>\n";
            
            // Migrate existing adviser names to adviser_id
            $this->migrateAdviserNames();
        }
        
        // Add completed status if not exists
        $stmt = $this->conn->query("SHOW COLUMNS FROM course_applications WHERE Field = 'status'");
        $column = $stmt->fetch();
        if ($column && strpos($column['Type'], 'completed') === false) {
            $this->conn->exec("ALTER TABLE course_applications MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending'");
            echo "<p>✓ Updated status enum to include 'completed'</p>\n";
        }
        
        // Add foreign key constraints (drop existing first if they exist)
        $this->addForeignKeyConstraints();
        
        // Add unique constraint for student-course combination
        $this->addUniqueConstraint();
    }
    
    private function migrateCourseNames() {
        echo "<p>Migrating course names to course IDs...</p>\n";
        
        // Get all unique course names from applications
        $stmt = $this->conn->query("SELECT DISTINCT course_name FROM course_applications WHERE course_name IS NOT NULL");
        $course_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($course_names as $course_name) {
            // Check if course exists in courses table
            $stmt = $this->conn->prepare("SELECT course_id FROM courses WHERE course_name = ?");
            $stmt->execute([$course_name]);
            $course = $stmt->fetch();
            
            if (!$course) {
                // Insert course if it doesn't exist
                $stmt = $this->conn->prepare("INSERT INTO courses (course_name, is_active) VALUES (?, TRUE)");
                $stmt->execute([$course_name]);
                $course_id = $this->conn->lastInsertId();
            } else {
                $course_id = $course['course_id'];
            }
            
            // Update applications with course_id
            $stmt = $this->conn->prepare("UPDATE course_applications SET course_id = ? WHERE course_name = ?");
            $stmt->execute([$course_id, $course_name]);
        }
        
        echo "<p>✓ Course names migrated to course IDs</p>\n";
    }
    
    private function migrateAdviserNames() {
        echo "<p>Migrating adviser names to adviser IDs...</p>\n";
        
        // Get all unique adviser names from applications
        $stmt = $this->conn->query("SELECT DISTINCT adviser FROM course_applications WHERE adviser IS NOT NULL AND adviser != ''");
        $adviser_names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($adviser_names as $adviser_name) {
            // Check if adviser exists in advisers table
            $stmt = $this->conn->prepare("SELECT adviser_id FROM advisers WHERE adviser_name = ?");
            $stmt->execute([$adviser_name]);
            $adviser = $stmt->fetch();
            
            if (!$adviser) {
                // Insert adviser if it doesn't exist
                $stmt = $this->conn->prepare("INSERT INTO advisers (adviser_name, is_active) VALUES (?, TRUE)");
                $stmt->execute([$adviser_name]);
                $adviser_id = $this->conn->lastInsertId();
            } else {
                $adviser_id = $adviser['adviser_id'];
            }
            
            // Update applications with adviser_id
            $stmt = $this->conn->prepare("UPDATE course_applications SET adviser_id = ? WHERE adviser = ?");
            $stmt->execute([$adviser_id, $adviser_name]);
        }
        
        echo "<p>✓ Adviser names migrated to adviser IDs</p>\n";
    }
    
    private function addForeignKeyConstraints() {
        echo "<p>Adding foreign key constraints...</p>\n";
        
        // Drop existing foreign keys if they exist
        try {
            $this->conn->exec("ALTER TABLE course_applications DROP FOREIGN KEY course_applications_ibfk_1");
        } catch (Exception $e) {
            // Ignore if constraint doesn't exist
        }
        
        try {
            $this->conn->exec("ALTER TABLE course_applications DROP FOREIGN KEY course_applications_ibfk_2");
        } catch (Exception $e) {
            // Ignore if constraint doesn't exist
        }
        
        // Add new foreign key constraints
        try {
            $this->conn->exec("ALTER TABLE course_applications 
                              ADD CONSTRAINT fk_ca_student 
                              FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE");
            echo "<p>✓ Added student foreign key constraint</p>\n";
        } catch (Exception $e) {
            echo "<p>⚠ Student foreign key constraint already exists or failed: " . $e->getMessage() . "</p>\n";
        }
        
        try {
            $this->conn->exec("ALTER TABLE course_applications 
                              ADD CONSTRAINT fk_ca_course 
                              FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE");
            echo "<p>✓ Added course foreign key constraint</p>\n";
        } catch (Exception $e) {
            echo "<p>⚠ Course foreign key constraint already exists or failed: " . $e->getMessage() . "</p>\n";
        }
        
        try {
            $this->conn->exec("ALTER TABLE course_applications 
                              ADD CONSTRAINT fk_ca_adviser 
                              FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL");
            echo "<p>✓ Added adviser foreign key constraint</p>\n";
        } catch (Exception $e) {
            echo "<p>⚠ Adviser foreign key constraint already exists or failed: " . $e->getMessage() . "</p>\n";
        }
        
        try {
            $this->conn->exec("ALTER TABLE course_applications 
                              ADD CONSTRAINT fk_ca_reviewer 
                              FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL");
            echo "<p>✓ Added reviewer foreign key constraint</p>\n";
        } catch (Exception $e) {
            echo "<p>⚠ Reviewer foreign key constraint already exists or failed: " . $e->getMessage() . "</p>\n";
        }
    }
    
    private function addUniqueConstraint() {
        echo "<p>Adding unique constraint for student-course combination...</p>\n";
        
        try {
            // First, remove any duplicate applications (keep the latest one)
            $this->conn->exec("
                DELETE ca1 FROM course_applications ca1
                INNER JOIN course_applications ca2 
                WHERE ca1.student_id = ca2.student_id 
                  AND ca1.course_id = ca2.course_id 
                  AND ca1.application_id < ca2.application_id
            ");
            
            // Add unique constraint
            $this->conn->exec("ALTER TABLE course_applications 
                              ADD CONSTRAINT unique_student_course 
                              UNIQUE (student_id, course_id)");
            echo "<p>✓ Added unique constraint for student-course combination</p>\n";
        } catch (Exception $e) {
            echo "<p>⚠ Unique constraint already exists or failed: " . $e->getMessage() . "</p>\n";
        }
    }
    
    private function addSampleData() {
        echo "<h3>Adding sample data...</h3>\n";
        
        // Add sample courses if courses table is empty
        $stmt = $this->conn->query("SELECT COUNT(*) FROM courses");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $courses = [
                ['Automotive Servicing', 'AUTO-001', 'Complete automotive maintenance and repair training', 480, '["NC I", "NC II"]'],
                ['Computer Systems Servicing', 'CSS-001', 'Computer hardware and software troubleshooting', 320, '["NC II"]'],
                ['Electrical Installation and Maintenance', 'EIM-001', 'Electrical systems installation and maintenance', 400, '["NC I", "NC II"]'],
                ['Welding', 'WELD-001', 'Arc and gas welding techniques', 360, '["NC I", "NC II"]'],
                ['Cookery', 'COOK-001', 'Professional cooking and food preparation', 280, '["NC II"]']
            ];
            
            $stmt = $this->conn->prepare("INSERT INTO courses (course_name, course_code, description, duration_hours, nc_levels) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($courses as $course) {
                $stmt->execute($course);
            }
            
            echo "<p>✓ Added sample courses</p>\n";
        }
        
        // Add sample advisers if advisers table is empty
        $stmt = $this->conn->query("SELECT COUNT(*) FROM advisers");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $advisers = [
                ['John Doe', 'john.doe@tesda.gov.ph', '09123456789', 'Automotive', 'Automotive Technology'],
                ['Jane Smith', 'jane.smith@tesda.gov.ph', '09123456790', 'ICT', 'Computer Systems'],
                ['Mike Johnson', 'mike.johnson@tesda.gov.ph', '09123456791', 'Electrical', 'Electrical Systems'],
                ['Sarah Wilson', 'sarah.wilson@tesda.gov.ph', '09123456792', 'Welding', 'Metal Fabrication'],
                ['Lisa Brown', 'lisa.brown@tesda.gov.ph', '09123456793', 'Food Service', 'Culinary Arts']
            ];
            
            $stmt = $this->conn->prepare("INSERT INTO advisers (adviser_name) VALUES (?)");
            
            foreach ($advisers as $adviser) {
                $stmt->execute([$adviser[0]]); // Only use the name
                $stmt->execute($adviser);
            }
            
            echo "<p>✓ Added sample advisers</p>\n";
        }
    }
    
    private function createIndexes() {
        echo "<h3>Creating indexes...</h3>\n";
        
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_courses_code ON courses(course_code)",
            "CREATE INDEX IF NOT EXISTS idx_courses_active ON courses(is_active)",
            "CREATE INDEX IF NOT EXISTS idx_ca_course_id ON course_applications(course_id)",
            "CREATE INDEX IF NOT EXISTS idx_ca_adviser_id ON course_applications(adviser_id)",
            "CREATE INDEX IF NOT EXISTS idx_ca_dates ON course_applications(training_start, training_end)",
            "CREATE INDEX IF NOT EXISTS idx_advisers_active ON advisers(is_active)",
            "CREATE INDEX IF NOT EXISTS idx_advisers_name ON advisers(adviser_name)"
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
}

// Run migration if accessed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    echo "<!DOCTYPE html><html><head><title>Database Migration</title></head><body>";
    echo "<style>body { font-family: Arial, sans-serif; margin: 20px; }</style>";
    
    $migration = new SchemaMigration();
    $migration->migrate();
    
    echo "<hr>";
    echo "<p><a href='../admin/dashboard.php'>Go to Admin Dashboard</a></p>";
    echo "</body></html>";
}

?>