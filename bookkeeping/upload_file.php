<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as bookkeeping
if (!isset($_SESSION['bookkeeping_logged_in']) || $_SESSION['role'] !== 'bookkeeping') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Validate required fields
$student_id = $_POST['student_id'] ?? null;
$enrollment_id = $_POST['enrollment_id'] ?? null;

if (!$student_id || !$enrollment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['file'];
$allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, DOC, DOCX, JPG, PNG allowed']);
    exit();
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit();
}

try {
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/bookkeeping/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'student_' . $student_id . '_enrollment_' . $enrollment_id . '_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Save file information to database
        $database = new Database();
        $conn = $database->getConnection();
        
        // Create bookkeeping_files table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS bookkeeping_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            enrollment_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES shortcourse_students(id) ON DELETE CASCADE,
            FOREIGN KEY (enrollment_id) REFERENCES student_enrollments(enrollment_id) ON DELETE CASCADE,
            INDEX idx_student_id (student_id),
            INDEX idx_enrollment_id (enrollment_id)
        )";
        $conn->exec($create_table);
        
        // Insert file record
        $query = "INSERT INTO bookkeeping_files 
                  (student_id, enrollment_id, file_name, file_path, file_type, file_size, uploaded_by) 
                  VALUES (:student_id, :enrollment_id, :file_name, :file_path, :file_type, :file_size, :uploaded_by)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
        $stmt->bindParam(':file_name', $file['name']);
        $stmt->bindParam(':file_path', $new_filename);
        $stmt->bindParam(':file_type', $file['type']);
        $stmt->bindParam(':file_size', $file['size'], PDO::PARAM_INT);
        $stmt->bindParam(':uploaded_by', $_SESSION['user_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'File uploaded successfully',
                'file_id' => $conn->lastInsertId()
            ]);
        } else {
            // Delete uploaded file if database insert fails
            unlink($upload_path);
            echo json_encode(['success' => false, 'message' => 'Failed to save file information']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
    
} catch(PDOException $e) {
    // Delete uploaded file if there's a database error
    if (isset($upload_path) && file_exists($upload_path)) {
        unlink($upload_path);
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
