<?php
// Database configuration and setup
class Database {
    private $host = 'localhost';
    private $db_name = 'student_registration_db';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Create database and tables if not exists
function createDatabaseAndTable() {
    try {
        // Connect without database first
        $pdo = new PDO("mysql:host=localhost", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS student_registration_db");
        $pdo->exec("USE student_registration_db");
        
        // Create users table for authentication (admin only)
        $sql_users = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin') NOT NULL DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql_users);
        
        // Create students table with approval status
        $sql_students = "CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL UNIQUE,
            first_name VARCHAR(100) NOT NULL,
            middle_name VARCHAR(100),
            last_name VARCHAR(100) NOT NULL,
            extension_name VARCHAR(20),
            birthday DATE NOT NULL,
            age INT NOT NULL,
            sex ENUM('Male','Female','Other') NOT NULL,
            civil_status VARCHAR(50) NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            province VARCHAR(100) NOT NULL,
            city VARCHAR(100) NOT NULL,
            barangay VARCHAR(100) NOT NULL,
            street_address VARCHAR(200),
            place_of_birth VARCHAR(200) NOT NULL,
            guardian_last_name VARCHAR(100) NOT NULL,
            guardian_first_name VARCHAR(100) NOT NULL,
            guardian_middle_name VARCHAR(100),
            guardian_extension VARCHAR(20),
            parent_contact VARCHAR(20) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            profile_picture VARCHAR(255),
            uli VARCHAR(50) NOT NULL UNIQUE,
            last_school VARCHAR(200) NOT NULL,
            school_province VARCHAR(100) NOT NULL,
            school_city VARCHAR(100) NOT NULL,
            verification_code VARCHAR(4) NOT NULL,
            is_verified BOOLEAN DEFAULT FALSE,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (approved_by) REFERENCES users(id)
        )";
        
        $pdo->exec($sql_students);
        
        // Create courses table
        $sql_courses = "CREATE TABLE IF NOT EXISTS courses (
            course_id INT AUTO_INCREMENT PRIMARY KEY,
            course_name VARCHAR(200) NOT NULL UNIQUE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql_courses);
        
        // Create advisers table
        $sql_advisers = "CREATE TABLE IF NOT EXISTS advisers (
            adviser_id INT AUTO_INCREMENT PRIMARY KEY,
            adviser_name VARCHAR(200) NOT NULL,
            email VARCHAR(150) UNIQUE,
            phone VARCHAR(20),
            department VARCHAR(100),
            specialization VARCHAR(200),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql_advisers);
        
<<<<<<< HEAD
        // Create course_applications table
        $sql_course_applications = "CREATE TABLE IF NOT EXISTS course_applications (
            application_id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            student_uli VARCHAR(50) NOT NULL,
            course_name VARCHAR(200) NOT NULL,
            nc_level VARCHAR(10) NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            adviser VARCHAR(200) NULL,
            training_start DATE NULL,
            training_end DATE NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            reviewed_by INT NULL,
            notes TEXT NULL,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_student_id (student_id),
            INDEX idx_student_uli (student_uli),
            INDEX idx_status (status),
            INDEX idx_applied_at (applied_at)
        )";
        
        $pdo->exec($sql_course_applications);
        
=======
>>>>>>> 719ba2fe487140cf7e0419847c8b1e1d20a1f9f9
        // Don't insert sample courses - let users add them manually
        // Don't insert sample advisers - let users add them manually
        
        // Insert default admin user if not exists
        $admin_check = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        if ($admin_check->fetchColumn() == 0) {
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@system.com', $admin_password, 'admin']);
        }
        
        return true;
    } catch(PDOException $e) {
        echo "Error creating database/table: " . $e->getMessage();
        return false;
    }
}

// Fix database - Add missing columns if needed
function fixDatabase() {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            // Check if student_id column exists
            $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'student_id'");
            $columnExists = $stmt->fetch();
            
            if (!$columnExists) {
                // Add the missing student_id column
                $alterSql = "ALTER TABLE students ADD COLUMN student_id VARCHAR(20) NOT NULL UNIQUE AFTER id";
                $conn->exec($alterSql);
            }
            
            // Check if status column exists
            $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'status'");
            $statusExists = $stmt->fetch();
            
            if (!$statusExists) {
                $conn->exec("ALTER TABLE students ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
                $conn->exec("ALTER TABLE students ADD COLUMN approved_by INT NULL");
                $conn->exec("ALTER TABLE students ADD COLUMN approved_at TIMESTAMP NULL");
            }
            
            // Check and add new guardian name columns
            $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'guardian_last_name'");
            $guardianExists = $stmt->fetch();
            
            if (!$guardianExists) {
                $conn->exec("ALTER TABLE students ADD COLUMN guardian_last_name VARCHAR(100) NOT NULL DEFAULT ''");
                $conn->exec("ALTER TABLE students ADD COLUMN guardian_first_name VARCHAR(100) NOT NULL DEFAULT ''");
                $conn->exec("ALTER TABLE students ADD COLUMN guardian_middle_name VARCHAR(100)");
                $conn->exec("ALTER TABLE students ADD COLUMN guardian_extension VARCHAR(20)");
                
                // Migrate existing parent_name data if exists
                $conn->exec("UPDATE students SET guardian_last_name = parent_name WHERE guardian_last_name = ''");
                $conn->exec("ALTER TABLE students DROP COLUMN parent_name");
            }
            
            // Check and add birth province and city columns
            $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'birth_province'");
            $birthProvinceExists = $stmt->fetch();
            
            if (!$birthProvinceExists) {
                $conn->exec("ALTER TABLE students ADD COLUMN birth_province VARCHAR(100) NOT NULL DEFAULT '' AFTER place_of_birth");
                $conn->exec("ALTER TABLE students ADD COLUMN birth_city VARCHAR(100) NOT NULL DEFAULT '' AFTER birth_province");
            }
            
            // Check and add extension name column for student
            $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'extension_name'");
            $extensionExists = $stmt->fetch();
            
            if (!$extensionExists) {
                $conn->exec("ALTER TABLE students ADD COLUMN extension_name VARCHAR(20) AFTER last_name");
            }
            
            // Check and add verification code columns
            $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'verification_code'");
            $verificationExists = $stmt->fetch();
            
            if (!$verificationExists) {
                $conn->exec("ALTER TABLE students ADD COLUMN verification_code VARCHAR(4) NOT NULL DEFAULT '0000'");
                $conn->exec("ALTER TABLE students ADD COLUMN is_verified BOOLEAN DEFAULT FALSE");
            }
            
            // Check if courses table exists
            $stmt = $conn->query("SHOW TABLES LIKE 'courses'");
            $coursesTableExists = $stmt->fetch();
            
            if (!$coursesTableExists) {
                // Create courses table
                $sql_courses = "CREATE TABLE courses (
                    course_id INT AUTO_INCREMENT PRIMARY KEY,
                    course_name VARCHAR(200) NOT NULL UNIQUE,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $conn->exec($sql_courses);
                
                // Don't insert sample courses - let users add them manually
            }
            
            // Check if advisers table exists
            $stmt = $conn->query("SHOW TABLES LIKE 'advisers'");
            $advisersTableExists = $stmt->fetch();
            
            if (!$advisersTableExists) {
                // Create advisers table
                $sql_advisers = "CREATE TABLE advisers (
                    adviser_id INT AUTO_INCREMENT PRIMARY KEY,
                    adviser_name VARCHAR(200) NOT NULL,
                    email VARCHAR(150) UNIQUE,
                    phone VARCHAR(20),
                    department VARCHAR(100),
                    specialization VARCHAR(200),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $conn->exec($sql_advisers);
            }
        }
        
        return true;
    } catch(PDOException $e) {
        echo "Error fixing database: " . $e->getMessage();
        return false;
    }
}

// Setup database with visual feedback (for setup page)
function setupDatabaseWithFeedback() {
    echo "<h2>Database Setup</h2>";
    
    try {
        // Initialize database
        if (createDatabaseAndTable()) {
            $database = new Database();
            $conn = $database->getConnection();
            
            if ($conn) {
                echo "<p style='color: green;'>✓ Database connection successful!</p>";
                echo "<p style='color: green;'>✓ Database 'student_registration_db' created/verified</p>";
                echo "<p style='color: green;'>✓ Tables created/verified</p>";
                
                // Fix any missing columns
                fixDatabase();
                echo "<p style='color: green;'>✓ Database structure verified and fixed</p>";
                
                // Show table structure
                $stmt = $conn->query("DESCRIBE students");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h3>Students Table Structure:</h3>";
                echo "<table border='1' cellpadding='5' cellspacing='0' style='background-color: white; width: 100%; margin: 10px 0;'>";
                echo "<tr style='background-color: #800000; color: white;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
                    echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
                    echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
                    echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
                    echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
                    echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                echo "<h3>Next Steps:</h3>";
                echo "<ul>";
                echo "<li><a href='index.html' style='color: #800000; text-decoration: none; font-weight: bold;'>Go to Home Page</a></li>";
                echo "<li><a href='student/register.php' style='color: #800000; text-decoration: none; font-weight: bold;'>Test Student Registration</a></li>";
                echo "<li><a href='admin/dashboard.php' style='color: #800000; text-decoration: none; font-weight: bold;'>Access Admin Dashboard</a></li>";
                echo "</ul>";
                
            } else {
                echo "<p style='color: red;'>✗ Database connection failed!</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Database setup failed!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    }
}

// Initialize database automatically when this file is included
createDatabaseAndTable();
fixDatabase();
?>