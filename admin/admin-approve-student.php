<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$success_message = '';
$error_message = '';
$student = null;
$courses = [];
$advisers = [];

// Get student ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin-dashboard.php');
    exit;
}

$student_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Get current student status
            $stmt = $conn->prepare("SELECT status FROM students WHERE id = :id");
            $stmt->bindParam(':id', $student_id);
            $stmt->execute();
            $current_student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_student) {
                $error_message = 'Student not found.';
            } elseif ($current_student['status'] === 'pending') {
                // Initial registration approval - need course details
                $required_fields = ['course', 'nc_level', 'adviser', 'training_start', 'training_end'];
                $missing_fields = [];
                
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
                    }
                }
                
                if (!empty($missing_fields)) {
                    $error_message = 'Please fill in all required fields: ' . implode(', ', $missing_fields);
                } else {
                    // Update student with approval and course details (status: pending -> approved)
                    $stmt = $conn->prepare("UPDATE students SET 
                        status = 'approved',
                        course = :course,
                        nc_level = :nc_level,
                        adviser = :adviser,
                        training_start = :training_start,
                        training_end = :training_end,
                        approved_by = :admin_id,
                        approved_at = NOW()
                        WHERE id = :id");
                    
                    $stmt->bindParam(':course', $_POST['course']);
                    $stmt->bindParam(':nc_level', $_POST['nc_level']);
                    $stmt->bindParam(':adviser', $_POST['adviser']);
                    $stmt->bindParam(':training_start', $_POST['training_start']);
                    $stmt->bindParam(':training_end', $_POST['training_end']);
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Course application approved successfully! Student data has been moved to the students table with status "approved".';
                        header("refresh:2;url=admin-dashboard.php");
                    } else {
                        $error_message = 'Failed to approve course application.';
                    }
                }
            } elseif ($current_student['status'] === 'approved') {
                // Course completion approval - change status to completed
                $conn->beginTransaction();
                
                try {
                    // Update students table
                    $stmt = $conn->prepare("UPDATE students SET 
                        status = 'completed',
                        approved_by = :admin_id,
                        approved_at = NOW()
                        WHERE id = :id AND status = 'approved'");
                    
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    $stmt->execute();
                    
                    // Also update course_applications table to mark as completed
                    // Copy adviser and training dates from students table for historical record
                    $stmt = $conn->prepare("UPDATE course_applications SET 
                        status = 'completed',
                        adviser = :adviser,
                        training_start = :training_start,
                        training_end = :training_end,
                        reviewed_by = :admin_id,
                        reviewed_at = NOW()
                        WHERE student_id = :id AND status = 'approved'");
                    
                    $stmt->bindParam(':adviser', $current_student['adviser']);
                    $stmt->bindParam(':training_start', $current_student['training_start']);
                    $stmt->bindParam(':training_end', $current_student['training_end']);
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    
                    $success_message = 'Course completion approved successfully! Student can now apply for new courses.';
                    header("refresh:2;url=admin-dashboard.php");
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error_message = 'Failed to approve course completion: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Student is not in a valid status for approval.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif ($action === 'reject') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Get current student status
            $stmt = $conn->prepare("SELECT status FROM students WHERE id = :id");
            $stmt->bindParam(':id', $student_id);
            $stmt->execute();
            $current_student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current_student && $current_student['status'] === 'approved') {
                // Reject course completion - update both tables
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("UPDATE students SET 
                    status = 'rejected',
                    approved_by = :admin_id,
                    approved_at = NOW()
                    WHERE id = :id");
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':id', $student_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE course_applications SET 
                    status = 'rejected',
                    reviewed_by = :admin_id,
                    reviewed_at = NOW()
                    WHERE student_id = :id AND status = 'approved'");
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':id', $student_id);
                $stmt->execute();
                
                $conn->commit();
                $success_message = 'Course completion rejected successfully.';
                header("refresh:2;url=admin-dashboard.php");
            } else {
                // Regular student registration rejection
                $stmt = $conn->prepare("UPDATE students SET 
                    status = 'rejected',
                    approved_by = :admin_id,
                    approved_at = NOW()
                    WHERE id = :id");
                
                $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $stmt->bindParam(':id', $student_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Student registration rejected.';
                    header("refresh:2;url=pending_approvals.php");
                } else {
                    $error_message = 'Failed to reject student registration.';
                }
            }
        } catch (PDOException $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollback();
            }
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get student details
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Allow both pending and approved status
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id AND status IN ('pending', 'approved')");
    $stmt->bindParam(':id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header('Location: admin-dashboard.php');
        exit;
    }
    
    // If student status is 'approved', fetch course details from course_applications
    if ($student['status'] === 'approved') {
        $stmt = $conn->prepare("SELECT ca.*, c.course_name, c.course_code, c.description as course_description
                               FROM course_applications ca
                               LEFT JOIN courses c ON ca.course_id = c.course_id
                               WHERE ca.student_id = :id AND ca.status = 'approved'
                               ORDER BY ca.reviewed_at DESC
                               LIMIT 1");
        $stmt->bindParam(':id', $student_id);
        $stmt->execute();
        $course_application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Merge course application data into student array for display
        if ($course_application) {
            $student['course'] = $course_application['course_name'];
            $student['nc_level'] = $course_application['nc_level'];
            $student['course_code'] = $course_application['course_code'] ?? '';
            $student['course_description'] = $course_application['course_description'] ?? '';
            // Note: adviser, training_start, training_end are not in course_applications table
            // They would need to be added if required for display
        }
    }
    
    // Get available courses
    $stmt = $conn->query("SELECT * FROM courses WHERE is_active = TRUE ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available advisers from faculty table
    $stmt = $conn->query("SELECT faculty_id, name as adviser_name, status FROM faculty WHERE status = 'Active' ORDER BY name");
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
    <title>Approve Student Registration - Admin</title>
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
                        <a href="admin-pending-approvals.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                            <i class="fas fa-clock mr-2"></i>Pending Approvals
                        </a>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-sm font-medium text-gray-500">Approve Student</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="mb-6 md:mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
                    <?php if ($student['status'] === 'approved'): ?>
                        Approve Course Completion
                    <?php else: ?>
                        Approve Student Registration
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600">
                    <?php if ($student['status'] === 'approved'): ?>
                        Review student course details and approve course completion
                    <?php else: ?>
                        Review student details and assign course information
                    <?php endif; ?>
                </p>
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

            <?php if ($student): ?>
                <!-- Student Information Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6">
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
                                    <p class="mt-1"><?php echo htmlspecialchars($student['school_city'] . ', ' . $student['school_province']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Information Card (for approved students) -->
                <?php if ($student['status'] === 'approved' && !empty($student['course'])): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">
                            <i class="fas fa-graduation-cap mr-2"></i>Course Information
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Course</label>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($student['course']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($student['nc_level'] ?: 'Not specified'); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Adviser</label>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($student['adviser'] ?: 'Not assigned'); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Training Period</label>
                                <p class="text-lg font-semibold text-gray-900">
                                    <?php if ($student['training_start'] && $student['training_end']): ?>
                                        <?php echo date('M j, Y', strtotime($student['training_start'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($student['training_end'])); ?>
                                    <?php else: ?>
                                        Not specified
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Approval Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                <?php if ($student['status'] === 'approved'): ?>
                                    Course Completion Approval
                                <?php else: ?>
                                    Course Assignment & Approval
                                <?php endif; ?>
                            </h3>
                            <p class="text-gray-600 text-sm">
                                <?php if ($student['status'] === 'approved'): ?>
                                    Approve this student's course completion. Once approved, the student can apply for new courses.
                                <?php else: ?>
                                    Assign course details to approve this student's registration
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="approve">
                        
                        <?php if ($student['status'] === 'approved'): ?>
                            <!-- Course Completion Approval (no form fields needed) -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Note:</strong> Approving this course completion will change the student's status to "completed". 
                                    The student will then be able to apply for new courses.
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Initial Registration Approval (need course details) -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Course Selection -->
                                <div>
                                    <label for="course" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-book text-blue-600 mr-2"></i>Course *
                                    </label>
                                    <select id="course" name="course" required 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select a course</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo htmlspecialchars($course['course_name']); ?>">
                                                <?php echo htmlspecialchars($course['course_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- NC Level -->
                                <div>
                                    <label for="nc_level" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-certificate text-blue-600 mr-2"></i>NC Level *
                                    </label>
                                    <select id="nc_level" name="nc_level" required 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select NC Level</option>
                                        <option value="NC I">NC I</option>
                                        <option value="NC II">NC II</option>
                                        <option value="NC III">NC III</option>
                                        <option value="NC IV">NC IV</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Adviser Selection -->
                            <div>
                                <label for="adviser" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user-tie text-blue-600 mr-2"></i>Assigned Adviser *
                                </label>
                                <select id="adviser" name="adviser" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select an adviser</option>
                                    <?php foreach ($advisers as $adviser): ?>
                                        <option value="<?php echo htmlspecialchars($adviser['adviser_name']); ?>">
                                            <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Training Period -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="training_start" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Training Start Date *
                                    </label>
                                    <input type="date" id="training_start" name="training_start" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="training_end" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-calendar-check text-blue-600 mr-2"></i>Training End Date *
                                    </label>
                                    <input type="date" id="training_end" name="training_end" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                            <button type="submit" 
                                    class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                                <i class="fas fa-check mr-2"></i>
                                <?php if ($student['status'] === 'approved'): ?>
                                    Approve Completion
                                <?php else: ?>
                                    Approve & Assign Course
                                <?php endif; ?>
                            </button>
                            
                            <button type="button" onclick="showRejectModal()" 
                                    class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Reject
                            </button>
                            
                            <a href="<?php echo $student['status'] === 'approved' ? 'dashboard.php' : 'pending_approvals.php'; ?>" 
                               class="flex-1 inline-flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Reject Confirmation Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Reject</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500 mb-4">
                        Are you sure you want to reject this <?php echo $student['status'] === 'approved' ? 'course completion' : 'student registration'; ?>? This action cannot be undone.
                    </p>
                </div>
                <div class="flex items-center justify-center space-x-4 pt-4">
                    <button onclick="hideRejectModal()" type="button" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-times mr-2"></i>Reject
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showRejectModal() {
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        
        function hideRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }
        
        // Set minimum date for training start to today (only for pending students)
        <?php if ($student['status'] === 'pending'): ?>
        const trainingStartInput = document.getElementById('training_start');
        if (trainingStartInput) {
            trainingStartInput.min = new Date().toISOString().split('T')[0];
            
            // Update training end minimum date when start date changes
            trainingStartInput.addEventListener('change', function() {
                const startDate = this.value;
                const endDateInput = document.getElementById('training_end');
                if (endDateInput) {
                    endDateInput.min = startDate;
                    
                    // Clear end date if it's before the new start date
                    if (endDateInput.value && endDateInput.value < startDate) {
                        endDateInput.value = '';
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>