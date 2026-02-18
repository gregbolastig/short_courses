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
$receipt_number = $_POST['receipt_number'] ?? null;

if (!$student_id || !$enrollment_id || !$receipt_number) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate receipt number
$receipt_number = trim($receipt_number);

if (!preg_match('/^[0-9]{1,9}$/', $receipt_number)) {
    echo json_encode(['success' => false, 'message' => 'Receipt number must be 1-9 digits']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Create bookkeeping_receipts table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS bookkeeping_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        enrollment_id INT NOT NULL,
        receipt_number VARCHAR(9) NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_enrollment_receipt (enrollment_id),
        INDEX idx_student_id (student_id),
        INDEX idx_receipt_number (receipt_number),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )";
    $conn->exec($create_table);
    
    // Check if receipt already exists for this enrollment
    $check_query = "SELECT id, receipt_number FROM bookkeeping_receipts WHERE enrollment_id = :enrollment_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing receipt
        $query = "UPDATE bookkeeping_receipts 
                  SET receipt_number = :receipt_number, 
                      created_by = :created_by,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE enrollment_id = :enrollment_id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':receipt_number', $receipt_number);
        $stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Receipt number updated successfully',
                'receipt_id' => $existing['id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update receipt number']);
        }
    } else {
        // Insert new receipt
        $query = "INSERT INTO bookkeeping_receipts 
                  (student_id, enrollment_id, receipt_number, created_by) 
                  VALUES (:student_id, :enrollment_id, :receipt_number, :created_by)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
        $stmt->bindParam(':receipt_number', $receipt_number);
        $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Receipt number saved successfully',
                'receipt_id' => $conn->lastInsertId()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save receipt number']);
        }
    }
    
} catch(PDOException $e) {
    // Check if it's a duplicate receipt number error
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'This receipt number already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
