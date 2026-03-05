<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as bookkeeping
if (!isset($_SESSION['bookkeeping_logged_in']) || $_SESSION['role'] !== 'bookkeeping') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get search parameters
    $lastName = $_GET['last_name'] ?? '';
    $firstName = $_GET['first_name'] ?? '';
    $uli = $_GET['uli'] ?? '';
    
    // Build query - fetch all students regardless of status to show all records
    $query = "SELECT 
                id,
                student_id,
                first_name,
                middle_name,
                last_name,
                uli,
                email,
                contact_number,
                status,
                course,
                nc_level,
                adviser
              FROM shortcourse_students 
              WHERE 1=1";
    
    $params = [];
    
    // Add filters if provided
    if (!empty($lastName)) {
        $query .= " AND last_name LIKE :last_name";
        $params[':last_name'] = '%' . $lastName . '%';
    }
    
    if (!empty($firstName)) {
        $query .= " AND first_name LIKE :first_name";
        $params[':first_name'] = '%' . $firstName . '%';
    }
    
    if (!empty($uli)) {
        $query .= " AND uli LIKE :uli";
        $params[':uli'] = '%' . $uli . '%';
    }
    
    $query .= " ORDER BY last_name, first_name";
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($students);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
