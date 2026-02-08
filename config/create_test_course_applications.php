<?php
require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Creating Test Course Applications</h2>";
    
    // First, let's check if we have students and courses
    $stmt = $conn->query("SELECT COUNT(*) as student_count FROM students");
    $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as course_count FROM courses");
    $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['course_count'];
    
    echo "<p>Students in database: $student_count</p>";
    echo "<p>Courses in database: $course_count</p>";
    
    if ($student_count == 0) {
        echo "<p style='color: red;'>No students found! Please register some students first.</p>";
        exit;
    }
    
    if ($course_count == 0) {
        echo "<p style='color: red;'>No courses found! Please add some courses first.</p>";
        exit;
    }
    
    // Get some students and courses
    $stmt = $conn->query("SELECT id, first_name, last_name FROM students LIMIT 5");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT course_id, course_name FROM courses LIMIT 3");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Students:</h3>";
    foreach ($students as $student) {
        echo "<p>ID: {$student['id']} - {$student['first_name']} {$student['last_name']}</p>";
    }
    
    echo "<h3>Available Courses:</h3>";
    foreach ($courses as $course) {
        echo "<p>ID: {$course['course_id']} - {$course['course_name']}</p>";
    }
    
    // Create test course applications
    echo "<h3>Creating Test Applications:</h3>";
    
    $test_applications = [
        [
            'student_id' => $students[0]['id'],
            'course_id' => $courses[0]['course_id'],
            'nc_level' => 'NC II',
            'status' => 'pending'
        ],
        [
            'student_id' => $students[1]['id'] ?? $students[0]['id'],
            'course_id' => $courses[1]['course_id'] ?? $courses[0]['course_id'],
            'nc_level' => 'NC III',
            'status' => 'approved'
        ],
        [
            'student_id' => $students[2]['id'] ?? $students[0]['id'],
            'course_id' => $courses[2]['course_id'] ?? $courses[0]['course_id'],
            'nc_level' => 'NC I',
            'status' => 'approved'
        ]
    ];
    
    foreach ($test_applications as $app) {
        // Check if application already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_applications 
                               WHERE student_id = :student_id AND course_id = :course_id AND nc_level = :nc_level");
        $stmt->execute($app);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($exists > 0) {
            echo "<p style='color: orange;'>Application already exists for Student {$app['student_id']}, Course {$app['course_id']}, {$app['nc_level']}</p>";
            continue;
        }
        
        // Insert the application
        $stmt = $conn->prepare("INSERT INTO course_applications (student_id, course_id, nc_level, status, applied_at) 
                               VALUES (:student_id, :course_id, :nc_level, :status, NOW())");
        
        if ($stmt->execute($app)) {
            echo "<p style='color: green;'>✓ Created {$app['status']} application for Student {$app['student_id']}, Course {$app['course_id']}, {$app['nc_level']}</p>";
        } else {
            echo "<p style='color: red;'>✗ Failed to create application for Student {$app['student_id']}</p>";
        }
    }
    
    // Show final counts
    echo "<h3>Final Course Application Counts:</h3>";
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM course_applications GROUP BY status");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($counts as $count) {
        echo "<p>{$count['status']}: {$count['count']}</p>";
    }
    
    echo "<p><a href='../admin/dashboard.php'>Go to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>