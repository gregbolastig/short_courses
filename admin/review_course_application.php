<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$success_message = '';
$error_message = '';
$application = null;
$student = null;
$student_courses = [];
$available_courses = [];
$advisers = [];

// Get application ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$application_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Validate required fields for approval
        $required_fields = ['course_name', 'nc_level', 'adviser', 'training_start', 'training_end'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            $error_message = 'Please fill in all required fields: ' . implode(', ', $missing_fields);
        } else {
            try {
                $database = new Database();
                $conn = $database->getConnection();
                
                // Start transaction to ensure both updates succeed
                $conn->beginTransaction();
                
                // Get student_id and course name first
                $stmt = $conn->prepare("SELECT student_id FROM course_applications WHERE application_id = :app_id");
                $stmt->bindParam(':app_id', $application_id);
                $stmt->execute();
                $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $student_id = $app_data['student_id'];
                
                // Get course name for students table
                $stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = :course_id");
                $stmt->bindParam(':course_id', $_POST['course_name']);
                $stmt->execute();
                $course_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $course_name = $course_data['course_name'] ?? $_POST['course_name'];
                
                // Update course application with approval and details
                $stmt = $conn->prepare("UPDATE course_applications SET 
                    status = 'approved',
                    course_id = :course_id,
                    nc_level = :nc_level,
                    reviewed_by = :admin_id,
                    reviewed_at = NOW(),
                    notes = :notes
                    WHERE application_id = :id");
                
                $course_id = $_POST['course_name']; // Form field is named course_name but contains course_id
                $nc_level = $_POST['nc_level'];
                $notes = $_POST['notes'];
                $admin_id = $_SESSION['user_id'];
                
                $stmt->bindParam(':course_id', $course_id);
                $stmt->bindParam(':nc_level', $nc_level);
                $stmt->bindParam(':admin_id', $admin_id);
                $stmt->bindParam(':notes', $notes);
                $stmt->bindParam(':id', $application_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update course application');
                }
                
                // Update students table with course details (training dates, adviser, course name)
                $stmt = $conn->prepare("UPDATE students SET 
                    course = :course_name,
                    nc_level = :nc_level,
                    adviser = :adviser,
                    training_start = :training_start,
                    training_end = :training_end,
                    status = 'approved',
                    approved_by = :admin_id,
                    approved_at = NOW()
                    WHERE id = :student_id");
                
                $adviser = $_POST['adviser'];
                $training_start = $_POST['training_start'];
                $training_end = $_POST['training_end'];
                
                $stmt->bindParam(':course_name', $course_name);
                $stmt->bindParam(':nc_level', $nc_level);
                $stmt->bindParam(':adviser', $adviser);
                $stmt->bindParam(':training_start', $training_start);
                $stmt->bindParam(':training_end', $training_end);
                $stmt->bindParam(':admin_id', $admin_id);
                $stmt->bindParam(':student_id', $student_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update student record');
                }
                
                // Commit the transaction
                $conn->commit();
                
                // Log the activity
                if (file_exists(__DIR__ . '/../includes/system_activity_logger.php')) {
                    require_once __DIR__ . '/../includes/system_activity_logger.php';
                    $logger = new SystemActivityLogger($conn);
                    $logger->log(
                        'course_application_approved',
                        'Approved course application ID: ' . $application_id,
                        'admin',
                        $_SESSION['user_id'],
                        'course_application',
                        $application_id
                    );
                }
                
                $success_message = 'Course application approved successfully!';
                // Redirect after 2 seconds to allow user to read the message
                header("refresh:2;url=dashboard.php");
                
            } catch (Exception $e) {
                // Rollback the transaction on error
                if ($conn->inTransaction()) {
                    $conn->rollback();
                }
                $error_message = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reject') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $stmt = $conn->prepare("UPDATE course_applications SET 
                status = 'rejected',
                reviewed_by = :admin_id,
                reviewed_at = NOW(),
                notes = :notes
                WHERE application_id = :id");
            
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':notes', $_POST['notes']);
            $stmt->bindParam(':id', $application_id);
            
            if ($stmt->execute()) {
                $success_message = 'Course application rejected.';
                // Redirect after 2 seconds
                header("refresh:2;url=dashboard.php");
            } else {
                $error_message = 'Failed to reject course application.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get application details
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get application with student details
    $stmt = $conn->prepare("SELECT ca.*, s.*, 
                           COALESCE(c.course_name, ca.course_id) as course_name,
                           c.course_name as application_course_name,
                           c2.course_name as student_course_name
                           FROM course_applications ca 
                           JOIN students s ON ca.student_id = s.id 
                           LEFT JOIN courses c ON ca.course_id = c.course_id
                           LEFT JOIN courses c2 ON s.course = c2.course_id
                           WHERE ca.application_id = :id AND ca.status = 'pending'");
    $stmt->bindParam(':id', $application_id);
    $stmt->execute();
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        header('Location: dashboard.php');
        exit;
    }
    
    $student = $application; // Student data is included in the application
    
    // Initialize student_courses array
    $student_courses = [];
    
    // PRIORITY 1: Get course data from students table (has complete info: training dates, adviser, certificate)
    if (!empty($student['student_course_name']) && $student['status'] === 'completed') {
        $student_courses[] = [
            'course_name' => $student['student_course_name'],
            'nc_level' => $student['nc_level'] ?? 'Not specified',
            'training_start' => $student['training_start'] ?? null,
            'training_end' => $student['training_end'] ?? null,
            'adviser' => $student['adviser'] ?? 'Not assigned',
            'status' => 'completed',
            'approved_at' => $student['approved_at'] ?? null,
            'applied_at' => $student['created_at'] ?? null
        ];
    } elseif (!empty($student['student_course_name']) && $student['status'] === 'approved') {
        $student_courses[] = [
            'course_name' => $student['student_course_name'],
            'nc_level' => $student['nc_level'] ?? 'Not specified',
            'training_start' => $student['training_start'] ?? null,
            'training_end' => $student['training_end'] ?? null,
            'adviser' => $student['adviser'] ?? 'Not assigned',
            'status' => 'approved',
            'approved_at' => $student['approved_at'] ?? null
        ];
    }
    
    // PRIORITY 2: Get additional courses from course_applications table (if any)
    // Only include if not already in student_courses from students table
    $stmt = $conn->prepare("SELECT ca.application_id,
                           ca.student_id,
                           ca.course_id,
                           ca.nc_level,
                           ca.status,
                           ca.reviewed_at as approved_at,
                           ca.applied_at,
                           COALESCE(c.course_name, ca.course_id) as course_name
                           FROM course_applications ca 
                           LEFT JOIN courses c ON ca.course_id = c.course_id
                           WHERE ca.student_id = :student_id 
                           AND ca.status = 'approved'
                           ORDER BY ca.reviewed_at DESC, ca.applied_at DESC");
    $stmt->bindParam(':student_id', $student['id']);
    $stmt->execute();
    $completed_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add courses from course_applications only if not already in student_courses
    $existing_courses = array_column($student_courses, 'course_name');
    foreach ($completed_applications as $comp_app) {
        $course_name = $comp_app['course_name'] ?? 'Course ID: ' . ($comp_app['course_id'] ?? 'Unknown');
        
        // Only add if this course isn't already in the list
        if (!in_array($course_name, $existing_courses)) {
            $student_courses[] = [
                'course_name' => $course_name,
                'nc_level' => $comp_app['nc_level'] ?? 'Not specified',
                'training_start' => $comp_app['training_start'] ?? null,
                'training_end' => $comp_app['training_end'] ?? null,
                'adviser' => $comp_app['adviser'] ?? 'Not assigned',
                'status' => 'completed',
                'approved_at' => $comp_app['approved_at'] ?? $comp_app['applied_at'] ?? null
            ];
        }
    }
    
    // Get other course applications (pending, approved, rejected) excluding current application
    $stmt = $conn->prepare("SELECT ca.*, 
                           COALESCE(c.course_name, ca.course_id) as course_display_name,
                           c.course_name as course_name
                           FROM course_applications ca 
                           LEFT JOIN courses c ON ca.course_id = c.course_id
                           WHERE ca.student_id = :student_id 
                           AND ca.application_id != :current_id
                           AND ca.status != 'completed'
                           ORDER BY ca.applied_at DESC");
    $stmt->bindParam(':student_id', $student['id']);
    $stmt->bindParam(':current_id', $application_id);
    $stmt->execute();
    $other_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available courses
    $stmt = $conn->query("SELECT * FROM courses WHERE is_active = TRUE ORDER BY course_name");
    $available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available advisers
    $stmt = $conn->query("SELECT * FROM advisers WHERE is_active = TRUE ORDER BY adviser_name");
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Course Application - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include 'components/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <?php include 'components/header.php'; ?>
        
        <!-- Page Content -->
        <main class="p-4 md:p-6 lg:p-8">
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-900">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </a>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-sm font-medium text-gray-500">Review Course Application</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="mb-6 md:mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Review Course Application</h1>
                <p class="text-gray-600">Review student profile and course application details</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($error_message): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($application): ?>
                <!-- Student Profile Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 py-6">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <?php 
                                // Handle profile picture path resolution for admin view
                                $profile_picture_url = '';
                                $file_exists = false;
                                
                                if (!empty($student['profile_picture'])) {
                                    $stored_path = $student['profile_picture'];
                                    
                                    // Handle both old format (../uploads/profiles/file.jpg) and new format (uploads/profiles/file.jpg)
                                    if (strpos($stored_path, '../') === 0) {
                                        // Old format: use as is
                                        $profile_picture_url = $stored_path;
                                    } else {
                                        // New format: add ../
                                        $profile_picture_url = '../' . $stored_path;
                                    }
                                    
                                    $file_exists = file_exists($profile_picture_url);
                                }
                                ?>
                                
                                <?php if (!empty($student['profile_picture']) && $file_exists): ?>
                                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" 
                                         alt="Profile Picture" 
                                         class="w-16 h-16 rounded-full object-cover border-4 border-white shadow-lg">
                                <?php else: ?>
                                    <div class="w-16 h-16 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                        <span class="text-xl font-bold text-white">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-white">
                                <h2 class="text-xl font-bold mb-1">
                                    <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>
                                    <?php if ($student['extension_name']): ?>
                                        <?php echo htmlspecialchars($student['extension_name']); ?>
                                    <?php endif; ?>
                                </h2>
                                <div class="text-blue-100 text-sm space-y-1">
                                    <p><i class="fas fa-id-card mr-2"></i>ULI: <?php echo htmlspecialchars($student['uli']); ?></p>
                                    <p><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($student['email']); ?></p>
                                    <p><i class="fas fa-calendar mr-2"></i>Registered: <?php echo date('M j, Y', strtotime($student['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Details -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Personal Information</h4>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p><span class="font-medium">Age:</span> <?php echo $student['age']; ?> years old</p>
                                    <p><span class="font-medium">Sex:</span> <?php echo htmlspecialchars($student['sex']); ?></p>
                                    <p><span class="font-medium">Civil Status:</span> <?php echo htmlspecialchars($student['civil_status']); ?></p>
                                    <p><span class="font-medium">Contact:</span> <?php echo htmlspecialchars($student['contact_number']); ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Address</h4>
                                <div class="text-sm text-gray-600">
                                    <p><?php echo htmlspecialchars($student['barangay'] . ', ' . $student['city'] . ', ' . $student['province']); ?></p>
                                    <?php if ($student['street_address']): ?>
                                        <p><?php echo htmlspecialchars($student['street_address']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Education</h4>
                                <div class="text-sm text-gray-600">
                                    <p><span class="font-medium">Last School:</span></p>
                                    <p><?php echo htmlspecialchars($student['last_school']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course History -->
                <?php if (!empty($student_courses) || !empty($other_applications)): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-history text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Course History</h3>
                            <p class="text-gray-600 text-sm">Previous and other course enrollments</p>
                        </div>
                    </div>

                    <?php 
                    // Separate current enrolled (approved) and completed courses
                    $current_enrolled = [];
                    $completed_courses = [];
                    
                    foreach ($student_courses as $course) {
                        if ($course['status'] === 'completed') {
                            $completed_courses[] = $course;
                        } elseif ($course['status'] === 'approved') {
                            $current_enrolled[] = $course;
                        }
                    }
                    ?>
                    
                    <?php if (!empty($current_enrolled)): ?>
                        <div class="mb-6">
                            <h4 class="font-medium text-gray-900 mb-3">
                                <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>Current Enrolled Course
                            </h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NC Level</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adviser</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Training Period</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($current_enrolled as $course): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($course['nc_level']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo $course['adviser'] ? htmlspecialchars($course['adviser']) : '-'; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php if ($course['training_start'] && $course['training_end']): ?>
                                                            <?php echo date('M j, Y', strtotime($course['training_start'])); ?> - 
                                                            <?php echo date('M j, Y', strtotime($course['training_end'])); ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <i class="fas fa-clock mr-1"></i>Approved
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($completed_courses)): ?>
                        <div class="mb-6">
                            <h4 class="font-medium text-gray-900 mb-3">
                                <i class="fas fa-check-circle mr-2 text-green-600"></i>Completed Courses (<?php echo count($completed_courses); ?>)
                            </h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NC Level</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adviser</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Training Period</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($completed_courses as $course): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($course['nc_level']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo $course['adviser'] ? htmlspecialchars($course['adviser']) : '-'; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php if ($course['training_start'] && $course['training_end']): ?>
                                                            <?php echo date('M j, Y', strtotime($course['training_start'])); ?> - 
                                                            <?php echo date('M j, Y', strtotime($course['training_end'])); ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo $course['approved_at'] ? date('M j, Y', strtotime($course['approved_at'])) : '-'; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>Completed
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($other_applications)): ?>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Other Course Applications</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applied Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($other_applications as $app): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php 
                                                        $display_name = !empty($app['course_display_name']) 
                                                            ? $app['course_display_name'] 
                                                            : (!empty($app['course_name']) 
                                                                ? $app['course_name'] 
                                                                : 'Course ID: ' . htmlspecialchars($app['course_id'] ?? 'Unknown'));
                                                        echo htmlspecialchars($display_name); 
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo date('M j, Y', strtotime($app['applied_at'])); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php
                                                    $app_status_class = '';
                                                    switch ($app['status']) {
                                                        case 'approved':
                                                            $app_status_class = 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'rejected':
                                                            $app_status_class = 'bg-red-100 text-red-800';
                                                            break;
                                                        default:
                                                            $app_status_class = 'bg-yellow-100 text-yellow-800';
                                                    }
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $app_status_class; ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Application Review & Decision -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-clipboard-check text-blue-900"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Application Review & Decision</h3>
                            <p class="text-gray-600 text-sm">Review course application and make approval decision</p>
                        </div>
                    </div>

                    <!-- Current Application Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-blue-800 text-lg">
                                    <?php 
                                    $display_course_name = !empty($application['course_name']) 
                                        ? $application['course_name'] 
                                        : (!empty($application['application_course_name']) 
                                            ? $application['application_course_name'] 
                                            : 'Course ID: ' . htmlspecialchars($application['course_id'] ?? 'Unknown'));
                                    echo htmlspecialchars($display_course_name); 
                                    ?>
                                </h4>
                                <p class="text-blue-600 text-sm">Applied on <?php echo date('M j, Y g:i A', strtotime($application['applied_at'])); ?></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-clock mr-1"></i>Pending Review
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Form -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Approve Section -->
                        <div class="border border-green-200 rounded-lg p-6 bg-green-50">
                            <h4 class="text-lg font-semibold text-green-800 mb-4">
                                <i class="fas fa-check-circle mr-2"></i>Approve Application
                            </h4>
                            
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="approve">
                                
                                <div>
                                    <label for="course_name" class="block text-sm font-medium text-gray-700 mb-2">Course *</label>
                                    <select id="course_name" name="course_name" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900">
                                        <option value="">Select Course</option>
                                        <?php foreach ($available_courses as $course): ?>
                                            <option value="<?php echo htmlspecialchars($course['course_id']); ?>" 
                                                    <?php echo ($course['course_id'] == $application['course_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['course_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="nc_level" class="block text-sm font-medium text-gray-700 mb-2">NC Level *</label>
                                    <select id="nc_level" name="nc_level" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900">
                                        <option value="">Select NC Level</option>
                                        <option value="NC I">NC I</option>
                                        <option value="NC II">NC II</option>
                                        <option value="NC III">NC III</option>
                                        <option value="NC IV">NC IV</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="adviser" class="block text-sm font-medium text-gray-700 mb-2">Assigned Adviser *</label>
                                    <select id="adviser" name="adviser" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900">
                                        <option value="">Select an adviser</option>
                                        <?php foreach ($advisers as $adviser): ?>
                                            <option value="<?php echo htmlspecialchars($adviser['adviser_name']); ?>">
                                                <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="training_start" class="block text-sm font-medium text-gray-700 mb-2">Training Start *</label>
                                        <input type="date" id="training_start" name="training_start" required 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900">
                                    </div>

                                    <div>
                                        <label for="training_end" class="block text-sm font-medium text-gray-700 mb-2">Training End *</label>
                                        <input type="date" id="training_end" name="training_end" required 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900">
                                    </div>
                                </div>

                                <div>
                                    <label for="approve_notes" class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                                    <textarea id="approve_notes" name="notes" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900"
                                              placeholder="Add any notes about the approval..."></textarea>
                                </div>

                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center px-4 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors duration-200">
                                    <i class="fas fa-check mr-2"></i>Approve Application
                                </button>
                            </form>
                        </div>

                        <!-- Reject Section -->
                        <div class="border border-red-200 rounded-lg p-6 bg-red-50">
                            <h4 class="text-lg font-semibold text-red-800 mb-4">
                                <i class="fas fa-times-circle mr-2"></i>Reject Application
                            </h4>
                            
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="reject">
                                
                                <div>
                                    <label for="reject_notes" class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
                                    <textarea id="reject_notes" name="notes" rows="4" required
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-900 focus:border-blue-900"
                                              placeholder="Please provide a reason for rejecting this application..."></textarea>
                                </div>

                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center px-4 py-3 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors duration-200"
                                        onclick="return confirm('Are you sure you want to reject this application?')">
                                    <i class="fas fa-times mr-2"></i>Reject Application
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Set minimum date for training start to today
        document.getElementById('training_start').min = new Date().toISOString().split('T')[0];
        
        // Update training end minimum date when start date changes
        document.getElementById('training_start').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('training_end');
            endDateInput.min = startDate;
            
            // Clear end date if it's before the new start date
            if (endDateInput.value && endDateInput.value < startDate) {
                endDateInput.value = '';
            }
        });
    </script>
</body>
</html>