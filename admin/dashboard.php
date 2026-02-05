<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Dashboard';

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle approval/rejection actions
if (isset($_POST['action']) && isset($_POST['student_id'])) {
    $action = $_POST['action'];
    $student_id = $_POST['student_id'];
    
    if ($action === 'approve') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Check current student status
            $stmt = $conn->prepare("SELECT status, course FROM students WHERE id = :id");
            $stmt->bindParam(':id', $student_id);
            $stmt->execute();
            $current_student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_student) {
                $error_message = 'Student not found.';
            } elseif ($current_student['status'] === 'approved' && !empty($current_student['course'])) {
                // Course application completion approval (status: approved -> completed)
                // Student already has approved course application from course_applications table
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
                    $stmt = $conn->prepare("UPDATE course_applications SET 
                        status = 'completed',
                        reviewed_by = :admin_id,
                        reviewed_at = NOW()
                        WHERE student_id = :id AND status = 'approved'");
                    
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    
                    $success_message = 'Course completion approved successfully! Status updated from "approved" to "completed". Student can now apply for new courses.';
                    // Redirect to refresh and show updated status
                    header("Location: dashboard.php?approved=" . $student_id);
                    exit;
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $error_message = 'Failed to approve course completion: ' . $e->getMessage();
                }
            } elseif ($current_student['status'] === 'pending' && empty($current_student['course'])) {
                // Student registration approval (status: pending -> completed)
                // Initial student registration - goes directly to completed
                $course = $_POST['course'] ?? '';
                $nc_level = $_POST['nc_level'] ?? '';
                $training_start = $_POST['training_start'] ?? '';
                $training_end = $_POST['training_end'] ?? '';
                $adviser = $_POST['adviser'] ?? '';
                
                // Validate required fields
                if (empty($course) || empty($nc_level) || empty($adviser) || empty($training_start) || empty($training_end)) {
                    $error_message = 'Please fill in all required fields.';
                } else {
                    // Update student with approval and course details (status: pending -> completed)
                    $stmt = $conn->prepare("UPDATE students SET 
                        status = 'completed',
                        approved_by = :admin_id,
                        approved_at = NOW(),
                        course = :course,
                        nc_level = :nc_level,
                        training_start = :training_start,
                        training_end = :training_end,
                        adviser = :adviser
                        WHERE id = :id");
                    
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':course', $course);
                    $stmt->bindParam(':nc_level', $nc_level);
                    $stmt->bindParam(':training_start', $training_start);
                    $stmt->bindParam(':training_end', $training_end);
                    $stmt->bindParam(':adviser', $adviser);
                    $stmt->bindParam(':id', $student_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Student registration approved successfully! Status updated from "pending" to "completed". Course completion has been recorded.';
                        // Redirect to refresh and show updated status
                        header("Location: dashboard.php?approved=" . $student_id);
                        exit;
                    } else {
                        $error_message = 'Failed to approve student registration.';
                    }
                }
            } else {
                $error_message = 'Student is not in a valid status for approval.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle simple rejection actions (from GET request)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $student_id = $_GET['id'];
    
    if ($action === 'reject') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Check if student has status 'approved' (course application approved, need to reject completion)
            $stmt = $conn->prepare("SELECT status FROM students WHERE id = :id");
            $stmt->bindParam(':id', $student_id);
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                if ($student['status'] === 'approved') {
                    // Reject course completion - update both students table and course_applications
                    $conn->beginTransaction();
                    
                    // Update students table
                    $stmt = $conn->prepare("UPDATE students SET status = 'rejected', approved_by = :admin_id, approved_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    $stmt->execute();
                    
                    // Update course_applications table
                    $stmt = $conn->prepare("UPDATE course_applications SET status = 'rejected', reviewed_by = :admin_id, reviewed_at = NOW() WHERE student_id = :id AND status = 'approved'");
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $success_message = 'Course completion rejected successfully.';
                } else {
                    // Regular student registration rejection
                    $stmt = $conn->prepare("UPDATE students SET status = 'rejected', approved_by = :admin_id, approved_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
                    $stmt->bindParam(':id', $student_id);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Student registration rejected successfully.';
                    } else {
                        $error_message = 'Failed to reject student registration.';
                    }
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

// Get statistics
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Total students
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending approvals
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Completed students
    $stmt = $conn->query("SELECT COUNT(*) as completed FROM students WHERE status = 'completed'");
    $completed_students = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
    
    // Recent registrations (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as recent FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recent_registrations = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
    
    // Pagination for recent students
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10; // Show 10 students per page
    $offset = ($page - 1) * $per_page;
    
    
    // Get course applications - includes:
    // 1. Student registrations (status='pending' - initial registrations)
    // 2. Approved course applications (status='approved' with course - from course_applications)
    // Note: Completed students are now only visible in students/index.php
    $stmt = $conn->prepare("SELECT id, uli, first_name, last_name, email, status, course, nc_level, adviser, training_start, training_end, approved_at, created_at 
                           FROM students 
                           WHERE status = 'pending' 
                              OR (status = 'approved' AND course IS NOT NULL)
                           ORDER BY 
                               CASE 
                                   WHEN status = 'pending' THEN created_at 
                                   WHEN status = 'approved' THEN approved_at 
                               END DESC
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update total count for pagination
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'pending' OR (status = 'approved' AND course IS NOT NULL)");
    $total_students_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_students_count / $per_page);
    
    // Get active courses for approval modal
    $stmt = $conn->query("SELECT course_id, course_name FROM courses WHERE is_active = 1 ORDER BY course_name");
    $active_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get advisers for approval modal
    $stmt = $conn->query("SELECT adviser_id, adviser_name FROM advisers ORDER BY adviser_name");
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending course applications
    $stmt = $conn->query("SELECT COUNT(*) as pending_applications FROM course_applications WHERE status = 'pending'");
    $pending_applications = $stmt->fetch(PDO::FETCH_ASSOC)['pending_applications'];
    
    // Pagination for course applications
    $app_page = isset($_GET['app_page']) ? max(1, intval($_GET['app_page'])) : 1;
    $app_per_page = 10; // Show 10 applications per page
    $app_offset = ($app_page - 1) * $app_per_page;
    
    // Get total count for pagination
    $stmt = $conn->query("SELECT COUNT(*) as total FROM course_applications WHERE status = 'pending'");
    $total_applications_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_app_pages = ceil($total_applications_count / $app_per_page);
    
    // Get recent course applications with pagination
    $stmt = $conn->prepare("SELECT ca.*, s.first_name, s.last_name, s.email 
                           FROM course_applications ca 
                           JOIN students s ON ca.student_id = s.id 
                           WHERE ca.status = 'pending' 
                           ORDER BY ca.applied_at DESC 
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $app_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $app_offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get approved course applications (students with status='approved' - awaiting completion approval)
    $stmt = $conn->query("SELECT COUNT(*) as approved_applications FROM students WHERE status = 'approved' AND course IS NOT NULL");
    $approved_applications_count = $stmt->fetch(PDO::FETCH_ASSOC)['approved_applications'];
    
    // Pagination for approved applications
    $approved_page = isset($_GET['approved_page']) ? max(1, intval($_GET['approved_page'])) : 1;
    $approved_per_page = 10;
    $approved_offset = ($approved_page - 1) * $approved_per_page;
    
    // Get total count for pagination
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'approved' AND course IS NOT NULL");
    $total_approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_approved_pages = ceil($total_approved_count / $approved_per_page);
    
    // Get approved applications (students with status='approved')
    $stmt = $conn->prepare("SELECT id, uli, first_name, last_name, email, course, nc_level, adviser, training_start, training_end, status, approved_at 
                           FROM students 
                           WHERE status = 'approved' AND course IS NOT NULL 
                           ORDER BY approved_at DESC 
                           LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $approved_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $approved_offset, PDO::PARAM_INT);
    $stmt->execute();
    $approved_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Set default values in case of error
    $recent_students = [];
    $total_students = 0;
    $pending_approvals = 0;
    $completed_students = 0;
    $recent_registrations = 0;
    $total_pages = 0;
    $active_courses = [];
    $advisers = [];
    $pending_applications = 0;
    $recent_applications = [];
    $total_app_pages = 0;
    $approved_applications_count = 0;
    $approved_applications = [];
    $total_approved_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Registration System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#1e3a8a',
                            600: '#1e40af',
                            700: '#1d4ed8',
                            800: '#1e3a8a',
                            900: '#1e293b'
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            500: '#334155',
                            600: '#475569',
                            700: '#64748b'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out'
                    }
                }
            }
        }
    </script>
    <?php include 'components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
        <?php include 'components/sidebar.php'; ?>
        
        <!-- Main content wrapper -->
        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include 'components/header.php'; ?>
            
            <!-- Main content area -->
            <main class="overflow-y-auto focus:outline-none">
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        <!-- Alerts -->
                        <?php if (isset($error_message)): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-fade-in">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success_message)): ?>
                            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg animate-fade-in">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                            <!-- Total Students Card -->
                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-blue-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-users text-blue-900 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-gray-900 animate-pulse"><?php echo $total_students; ?></dd>
                                                <dd class="text-xs text-green-600 flex items-center mt-1">
                                                    <i class="fas fa-arrow-up mr-1"></i>
                                                    +12% from last month
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-blue-50 px-4 md:px-6 py-3 border-t border-blue-100">
                                    <a href="students/index.php" class="text-sm text-blue-700 hover:text-blue-800 font-medium flex items-center transition-colors duration-200">
                                        View all students
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Pending Approvals Card -->
                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-yellow-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-clock text-yellow-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Approvals</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-yellow-600 animate-pulse"><?php echo $pending_approvals; ?></dd>
                                                <?php if ($pending_approvals > 0): ?>
                                                    <dd class="text-xs text-red-600 flex items-center mt-1 animate-attention-pulse">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Requires attention
                                                    </dd>
                                                <?php else: ?>
                                                    <dd class="text-xs text-green-600 flex items-center mt-1">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        All caught up!
                                                    </dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-yellow-50 px-4 md:px-6 py-3 border-t border-yellow-100">
                                    <a href="pending_approvals.php" class="text-sm text-yellow-700 hover:text-yellow-800 font-medium flex items-center transition-colors duration-200">
                                        Review pending approvals
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Course Applications Card -->
                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-orange-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-file-alt text-orange-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Course Applications</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-orange-600 animate-pulse"><?php echo $pending_applications; ?></dd>
                                                <?php if ($pending_applications > 0): ?>
                                                    <dd class="text-xs text-red-600 flex items-center mt-1 animate-attention-pulse">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Needs review
                                                    </dd>
                                                <?php else: ?>
                                                    <dd class="text-xs text-green-600 flex items-center mt-1">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        All reviewed!
                                                    </dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-orange-50 px-4 md:px-6 py-3 border-t border-orange-100">
                                    <a href="course_application/index.php" class="text-sm text-orange-700 hover:text-orange-800 font-medium flex items-center transition-colors duration-200">
                                        View course applications
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Approved Applications Card -->
                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-indigo-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-check-circle text-indigo-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Approved Applications</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-indigo-600 animate-pulse"><?php echo $approved_applications_count; ?></dd>
                                                <?php if ($approved_applications_count > 0): ?>
                                                    <dd class="text-xs text-orange-600 flex items-center mt-1 animate-attention-pulse">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Awaiting completion
                                                    </dd>
                                                <?php else: ?>
                                                    <dd class="text-xs text-green-600 flex items-center mt-1">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        All processed!
                                                    </dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-indigo-50 px-4 md:px-6 py-3 border-t border-indigo-100">
                                    <a href="#approved-applications" class="text-sm text-indigo-700 hover:text-indigo-800 font-medium flex items-center transition-colors duration-200">
                                        View approved applications
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            

                        
                        </div>

                        <!-- Pending Course Applications Section - Simplified -->
                        <div id="course-applications" class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 mb-6 md:mb-8">
                            <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-4">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                        <i class="fas fa-file-alt text-gray-500 mr-2"></i>
                                        Pending Course Applications
                                    </h3>
                                    <span class="text-sm text-gray-600">
                                        <?php echo $pending_applications; ?> pending applications
                                    </span>
                                </div>
                            </div>
                            
                            <div class="text-center py-8 md:py-12">
                                <div class="bg-gray-100 rounded-full w-12 h-12 md:w-16 md:h-16 flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-file-alt text-gray-400 text-xl md:text-2xl"></i>
                                </div>
                                <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">Course Applications</h3>
                                <p class="text-sm md:text-base text-gray-500 mb-4 px-4">
                                    <?php if ($pending_applications == 0): ?>
                                        No course applications are currently pending review.
                                    <?php else: ?>
                                        <?php echo $pending_applications; ?> course applications are pending review.
                                    <?php endif; ?>
                                </p>
                                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                                    <a href="course_application/index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                        <i class="fas fa-list mr-2"></i>View All Applications
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Removed: Approved Course Applications section - now shown in Course Applications above -->
                        <?php if (false && $approved_applications_count > 0): ?>
                        <div id="approved-applications" class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100 mb-6 md:mb-8">
                            <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-4">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                        Approved Course Applications (Awaiting Completion)
                                    </h3>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                        <span class="text-sm text-gray-600">
                                            <?php echo $approved_applications_count; ?> approved applications
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($approved_applications)): ?>
                                <div class="text-center py-8 md:py-12">
                                    <div class="bg-gray-100 rounded-full w-12 h-12 md:w-16 md:h-16 flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-check-circle text-gray-400 text-xl md:text-2xl"></i>
                                    </div>
                                    <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">No approved applications</h3>
                                    <p class="text-sm md:text-base text-gray-500 mb-4 px-4">All approved applications have been processed.</p>
                                </div>
                            <?php else: ?>
                                <!-- Mobile Card View -->
                                <div class="block md:hidden">
                                    <div class="divide-y divide-gray-200">
                                        <?php foreach ($approved_applications as $student): ?>
                                            <div class="p-4 hover:bg-gray-50 transition-colors duration-200">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center space-x-3 mb-2">
                                                            <div class="bg-green-600 rounded-full p-2">
                                                                <i class="fas fa-user-graduate text-white text-xs"></i>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                                </p>
                                                                <p class="text-xs text-gray-500 truncate">
                                                                    <?php echo htmlspecialchars($student['email']); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="space-y-1">
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Course:</span>
                                                                <span class="text-xs text-gray-900 font-medium">
                                                                    <?php echo htmlspecialchars($student['course']); ?>
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">ULI:</span>
                                                                <span class="text-xs text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                                    <?php echo htmlspecialchars($student['uli']); ?>
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Approved:</span>
                                                                <span class="text-xs text-gray-500">
                                                                    <?php echo $student['approved_at'] ? date('M j, Y g:i A', strtotime($student['approved_at'])) : 'N/A'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="flex items-center space-x-2 mt-3 pt-3 border-t border-gray-100">
                                                            <a href="students/view.php?id=<?php echo $student['id']; ?>" 
                                                               class="inline-flex items-center px-3 py-1.5 bg-blue-900 text-white text-xs font-medium rounded-lg hover:bg-blue-800 transition-colors duration-200">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                            <a href="approve_student.php?id=<?php echo $student['id']; ?>" 
                                                               class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-colors duration-200">
                                                                <i class="fas fa-check mr-1"></i>Approve
                                                            </a>
                                                            <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                                               class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition-colors duration-200"
                                                               onclick="return confirm('Are you sure you want to reject this course completion?')">
                                                                <i class="fas fa-times mr-1"></i>Reject
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Desktop Table View -->
                                <div class="hidden md:block overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ULI</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($approved_applications as $student): ?>
                                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="bg-green-100 rounded-full p-2 mr-3">
                                                            <i class="fas fa-user-graduate text-green-600 text-sm"></i>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($student['email']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                        <?php echo htmlspecialchars($student['uli']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($student['course']); ?>
                                                    </div>
                                                    <?php if ($student['nc_level']): ?>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($student['nc_level']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $student['approved_at'] ? date('M j, Y g:i A', strtotime($student['approved_at'])) : 'N/A'; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex items-center space-x-3">
                                                        <a href="students/view.php?id=<?php echo $student['id']; ?>" 
                                                           class="inline-flex items-center px-4 py-2 bg-blue-900 text-white text-sm font-medium rounded-lg hover:bg-blue-800 transition-colors duration-200">
                                                            <i class="fas fa-eye mr-2"></i>View
                                                        </a>
                                                        <a href="approve_student.php?id=<?php echo $student['id']; ?>" 
                                                           class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors duration-200">
                                                            <i class="fas fa-check mr-2"></i>Approve
                                                        </a>
                                                        <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                                           class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors duration-200"
                                                           onclick="return confirm('Are you sure you want to reject this course completion?')">
                                                            <i class="fas fa-times mr-2"></i>Reject
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php endif; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_approved_pages > 1): ?>
                                <div class="px-4 md:px-6 py-4 border-t border-gray-200 bg-gray-50">
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                        <div class="text-sm text-gray-700">
                                            Showing <?php echo $approved_offset + 1; ?> to <?php echo min($approved_offset + $approved_per_page, $total_approved_count); ?> of <?php echo $total_approved_count; ?> applications
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Button -->
                                            <?php if ($approved_page > 1): ?>
                                                <a href="?approved_page=<?php echo $approved_page - 1; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <div class="hidden sm:flex items-center space-x-1">
                                                <?php
                                                $start_page = max(1, $approved_page - 2);
                                                $end_page = min($total_approved_pages, $approved_page + 2);
                                                
                                                if ($start_page > 1): ?>
                                                    <a href="?approved_page=1" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">1</a>
                                                    <?php if ($start_page > 2): ?>
                                                        <span class="text-gray-500">...</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                    <?php if ($i == $approved_page): ?>
                                                        <span class="inline-flex items-center justify-center w-8 h-8 border border-green-600 rounded text-sm font-medium text-white bg-green-600 shadow-md"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a href="?approved_page=<?php echo $i; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                                
                                                <?php if ($end_page < $total_approved_pages): ?>
                                                    <?php if ($end_page < $total_approved_pages - 1): ?>
                                                        <span class="text-gray-500">...</span>
                                                    <?php endif; ?>
                                                    <a href="?approved_page=<?php echo $total_approved_pages; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"><?php echo $total_approved_pages; ?></a>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="sm:hidden text-sm text-gray-700">
                                                Page <?php echo $approved_page; ?> of <?php echo $total_approved_pages; ?>
                                            </div>
                                            
                                            <!-- Next Button -->
                                            <?php if ($approved_page < $total_approved_pages): ?>
                                                <a href="?approved_page=<?php echo $approved_page + 1; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Students -->
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-4">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                        <i class="fas fa-file-alt text-gray-500 mr-2"></i>
                                        Course Applications
                                    </h3>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                        <!-- Search Bar -->
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                            <input type="text" id="studentSearch" placeholder="Search students..." class="block w-full sm:w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-900 focus:border-blue-900 text-sm">
                                        </div>
                                        <a href="students/index.php" class="text-sm text-blue-900 hover:text-blue-800 font-medium flex items-center justify-center sm:justify-start">
                                            View all
                                            <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php if (empty($recent_students)): ?>
                                <div class="text-center py-8 md:py-12">
                                    <div class="bg-gray-100 rounded-full w-12 h-12 md:w-16 md:h-16 flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-file-alt text-gray-400 text-xl md:text-2xl"></i>
                                    </div>
                                    <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">No course applications</h3>
                                    <p class="text-sm md:text-base text-gray-500 mb-4 px-4">No pending student registrations or approved course applications at this time.</p>
                                    <p class="text-sm md:text-base text-gray-500 mb-4 px-4">Get started by having students register through the student portal.</p>
                                    <a href="../student/register.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-900 hover:bg-blue-800">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        Go to Student Portal
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Mobile Card View -->
                                <div class="block md:hidden">
                                    <div class="divide-y divide-gray-200">
                                        <?php foreach ($recent_students as $student): ?>
                                            <div id="student-card-<?php echo $student['id']; ?>" class="p-4 hover:bg-gray-50 transition-colors duration-200 <?php echo (isset($_GET['approved']) && $_GET['approved'] == $student['id']) ? 'bg-green-50 animate-pulse' : ''; ?>">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center space-x-3 mb-2">
                                                            <div class="bg-blue-900 rounded-full p-2">
                                                                <i class="fas fa-user text-white text-xs"></i>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                                </p>
                                                                <p class="text-xs text-gray-500 truncate">
                                                                    <?php echo htmlspecialchars($student['email']); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="space-y-1">
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">ULI:</span>
                                                                <span class="text-xs text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                                    <?php echo htmlspecialchars($student['uli']); ?>
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Status:</span>
                                                                <?php
                                                                $status_classes = [
                                                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                                    'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                                    'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                                                    'completed' => 'bg-blue-100 text-blue-800 border-blue-200'
                                                                ];
                                                                $status_class = $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                                ?>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                                    <?php echo ucfirst($student['status']); ?>
                                                                </span>
                                                            </div>
                                                            <?php if (!empty($student['course'])): ?>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Course:</span>
                                                                <span class="text-xs text-gray-900 font-medium">
                                                                    <?php echo htmlspecialchars($student['course']); ?>
                                                                </span>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div class="flex items-center justify-between">
                                                                <span class="text-xs text-gray-500">Approved:</span>
                                                                <span class="text-xs text-gray-500">
                                                                    <?php echo $student['approved_at'] ? date('M j, Y g:i A', strtotime($student['approved_at'])) : date('M j, Y', strtotime($student['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="flex items-center space-x-3 mt-3 pt-3 border-t border-gray-100">
                                                            <a href="students/view.php?id=<?php echo $student['id']; ?>" 
                                                               class="text-blue-900 hover:text-blue-800 flex items-center text-xs">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                            
                                                            <?php if ($student['status'] === 'pending'): ?>
                                                                <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                                   class="text-green-600 hover:text-green-900 flex items-center text-xs">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                                <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                                                   class="text-red-600 hover:text-red-900 flex items-center text-xs"
                                                                   onclick="return confirm('Are you sure you want to reject this student registration?')">
                                                                    <i class="fas fa-times mr-1"></i>Reject
                                                                </a>
                                                            <?php elseif ($student['status'] === 'approved'): ?>
                                                                <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', '<?php echo htmlspecialchars($student['course'] ?? ''); ?>', '<?php echo htmlspecialchars($student['nc_level'] ?? ''); ?>', '<?php echo htmlspecialchars($student['adviser'] ?? ''); ?>', '<?php echo $student['training_start'] ?? ''; ?>', '<?php echo $student['training_end'] ?? ''; ?>')" 
                                                                   class="text-green-600 hover:text-green-900 flex items-center text-xs">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                                <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                                                   class="text-red-600 hover:text-red-900 flex items-center text-xs"
                                                                   onclick="return confirm('Are you sure you want to reject this course completion?')">
                                                                    <i class="fas fa-times mr-1"></i>Reject
                                                                </a>
                                                            <?php elseif ($student['status'] === 'completed'): ?>
                                                                <!-- Completed - only View action, no Approve/Reject -->
                                                                <span class="text-xs text-gray-500 italic">Completed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Desktop Table View -->
                                <div class="hidden md:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ULI</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($recent_students as $student): ?>
                                                <tr id="student-row-<?php echo $student['id']; ?>" class="hover:bg-gray-50 transition-colors duration-200 <?php echo (isset($_GET['approved']) && $_GET['approved'] == $student['id']) ? 'bg-green-50 animate-pulse' : ''; ?>">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                            <?php echo htmlspecialchars($student['uli']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($student['email']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($student['course'] ?? 'N/A'); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $status_classes = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                            'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                            'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                                            'completed' => 'bg-blue-100 text-blue-800 border-blue-200'
                                                        ];
                                                        $status_class = $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($student['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $student['approved_at'] ? date('M j, Y g:i A', strtotime($student['approved_at'])) : date('M j, Y g:i A', strtotime($student['created_at'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex items-center space-x-3">
                                                            <a href="students/view.php?id=<?php echo $student['id']; ?>" 
                                                               class="text-blue-900 hover:text-blue-800 flex items-center">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                            
                                                            <?php if ($student['status'] === 'pending'): ?>
                                                                <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                                   class="text-green-600 hover:text-green-900 flex items-center">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                                <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                                                   class="text-red-600 hover:text-red-900 flex items-center"
                                                                   onclick="return confirm('Are you sure you want to reject this student registration?')">
                                                                    <i class="fas fa-times mr-1"></i>Reject
                                                                </a>
                                                            <?php elseif ($student['status'] === 'approved'): ?>
                                                                <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', '<?php echo htmlspecialchars($student['course'] ?? ''); ?>', '<?php echo htmlspecialchars($student['nc_level'] ?? ''); ?>', '<?php echo htmlspecialchars($student['adviser'] ?? ''); ?>', '<?php echo $student['training_start'] ?? ''; ?>', '<?php echo $student['training_end'] ?? ''; ?>')" 
                                                                   class="text-green-600 hover:text-green-900 flex items-center">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                                <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                                                   class="text-red-600 hover:text-red-900 flex items-center"
                                                                   onclick="return confirm('Are you sure you want to reject this course completion?')">
                                                                    <i class="fas fa-times mr-1"></i>Reject
                                                                </a>
                                                            <?php elseif ($student['status'] === 'completed'): ?>
                                                                <!-- Completed - only View action, no Approve/Reject -->
                                                                <span class="text-xs text-gray-500 italic">Completed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="px-4 md:px-6 py-4 border-t border-gray-200 bg-gray-50">
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                        <div class="text-sm text-gray-700">
                                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_students_count); ?> of <?php echo $total_students_count; ?> applications
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Button -->
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <div class="hidden sm:flex items-center space-x-1">
                                                <?php
                                                $start_page = max(1, $page - 2);
                                                $end_page = min($total_pages, $page + 2);
                                                
                                                if ($start_page > 1): ?>
                                                    <a href="?page=1" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">1</a>
                                                    <?php if ($start_page > 2): ?>
                                                        <span class="text-gray-500">...</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                    <?php if ($i == $page): ?>
                                                        <span class="inline-flex items-center justify-center w-8 h-8 border border-blue-900 rounded text-sm font-medium text-white bg-blue-900 shadow-md"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a href="?page=<?php echo $i; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                                
                                                <?php if ($end_page < $total_pages): ?>
                                                    <?php if ($end_page < $total_pages - 1): ?>
                                                        <span class="text-gray-500">...</span>
                                                    <?php endif; ?>
                                                    <a href="?page=<?php echo $total_pages; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"><?php echo $total_pages; ?></a>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Mobile Page Info -->
                                            <div class="sm:hidden text-sm text-gray-700">
                                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                            </div>
                                            
                                            <!-- Next Button -->
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?>" class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded-lg text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed">
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mt-6 md:mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                            <div class="bg-white shadow-lg rounded-xl p-4 md:p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="bg-blue-100 rounded-lg p-2 md:p-3 mr-3 md:mr-4">
                                        <i class="fas fa-users text-blue-900 text-lg md:text-xl"></i>
                                    </div>
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">Student Management</h3>
                                </div>
                                <p class="text-sm md:text-base text-gray-600 mb-4">Manage all student records, approvals, and registrations.</p>
                                <div class="space-y-2">
                                    <a href="students/index.php" class="block text-blue-900 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>View All Students
                                    </a>
                                    <a href="pending_approvals.php" class="block text-blue-900 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Pending Approvals (<?php echo $pending_approvals; ?>)
                                    </a>
                                </div>
                            </div>
                            
                            <div class="bg-white shadow-lg rounded-xl p-4 md:p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="bg-green-100 rounded-lg p-2 md:p-3 mr-3 md:mr-4">
                                        <i class="fas fa-chalkboard-teacher text-green-600 text-lg md:text-xl"></i>
                                    </div>
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">Adviser Management</h3>
                                </div>
                                <p class="text-sm md:text-base text-gray-600 mb-4">Manage academic advisers and their assignments.</p>
                                <div class="space-y-2">
                                    <a href="advisers/index.php" class="block text-blue-900 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>View Adviser List
                                    </a>
                                    <a href="advisers/add.php" class="block text-blue-900 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Add New Adviser
                                    </a>
                                </div>
                            </div>
                            
                            <div class="bg-white shadow-lg rounded-xl p-4 md:p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="bg-purple-100 rounded-lg p-2 md:p-3 mr-3 md:mr-4">
                                        <i class="fas fa-book text-purple-600 text-lg md:text-xl"></i>
                                    </div>
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">Course Management</h3>
                                </div>
                                <p class="text-sm md:text-base text-gray-600 mb-4">Organize and manage courses and course applications.</p>
                                <div class="space-y-2">
                                    <a href="courses/index.php" class="block text-blue-900 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Manage Courses
                                    </a>
                                    <a href="courses/add.php" class="block text-blue-900 hover:text-blue-800 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Add New Course
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay with blur effect -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeApprovalModal()"></div>
            
            <!-- Modal panel with enhanced design -->
            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-gray-100">
                <form id="approvalForm" method="POST" action="">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="student_id" id="modalStudentId">
                    
                    <!-- Header Section -->
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-green-100 to-green-200 mb-4 shadow-lg">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-inner">
                                <i class="fas fa-user-check text-white text-lg"></i>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2" id="modal-title">
                            Approve & Complete Course
                        </h3>
                        <div class="w-12 h-1 bg-gradient-to-r from-green-500 to-green-600 rounded-full mx-auto"></div>
                    </div>

                    <!-- Student Info Section -->
                    <div class="text-center mb-6">
                        <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
                            <div class="flex items-center justify-center space-x-3 mb-2">
                                <div class="bg-blue-100 rounded-lg p-2">
                                    <i class="fas fa-user-graduate text-blue-900"></i>
                                </div>
                                <span class="font-semibold text-gray-900 text-lg" id="modalStudentName"></span>
                            </div>
                            <p class="text-sm text-gray-600" id="modalDescription">Assign course details and mark as completed</p>
                        </div>
                    </div>

                    <!-- Form Fields -->
                    <div class="space-y-6 mb-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Course Dropdown -->
                            <div>
                                <label for="course" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-book text-gray-400 mr-2"></i>Course
                                </label>
                                <select name="course" id="course" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                                    <option value="">Select Course</option>
                                    <?php foreach ($active_courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_name']); ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- NC Level Dropdown -->
                            <div>
                                <label for="nc_level" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-certificate text-gray-400 mr-2"></i>NC Level
                                </label>
                                <select name="nc_level" id="nc_level" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                                    <option value="">Select NC Level</option>
                                    <option value="NC I">NC I</option>
                                    <option value="NC II">NC II</option>
                                    <option value="NC III">NC III</option>
                                    <option value="NC IV">NC IV</option>
                                    <option value="NC V">NC V</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Training Duration -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="training_start" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt text-gray-400 mr-2"></i>Training Start Date
                                </label>
                                <input type="date" name="training_start" id="training_start" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            </div>
                            <div>
                                <label for="training_end" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-calendar-check text-gray-400 mr-2"></i>Training End Date
                                </label>
                                <input type="date" name="training_end" id="training_end" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            </div>
                        </div>
                        
                        <!-- Adviser Dropdown -->
                        <div>
                            <label for="adviser" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-chalkboard-teacher text-gray-400 mr-2"></i>Assigned Adviser
                            </label>
                            <select name="adviser" id="adviser" required class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                                <option value="">Select Adviser</option>
                                <?php foreach ($advisers as $adviser): ?>
                                    <option value="<?php echo htmlspecialchars($adviser['adviser_name']); ?>">
                                        <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row-reverse gap-3">
                        <button type="submit" id="approveSubmitBtn" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transform transition-all duration-200 hover:scale-105">
                            <i class="fas fa-check mr-2"></i>
                            <span id="approveBtnText">Approve & Complete</span>
                        </button>
                        <button type="button" onclick="closeApprovalModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Handle approval form submission with dynamic status update
    document.getElementById('approvalForm')?.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('approveSubmitBtn');
        const btnText = document.getElementById('approveBtnText');
        
        if (submitBtn && btnText) {
            submitBtn.disabled = true;
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        }
        
        // Form will submit normally, page will refresh and show updated status
    });
    
    // Update status display dynamically after page load (if coming from approval)
    window.addEventListener('load', function() {
        // Check if coming from approval redirect
        const urlParams = new URLSearchParams(window.location.search);
        const approvedId = urlParams.get('approved');
        
        if (approvedId) {
            // Find the student row and highlight it
            const studentRow = document.getElementById('student-row-' + approvedId);
            const studentCard = document.getElementById('student-card-' + approvedId);
            
            if (studentRow) {
                // Scroll to the updated row
                studentRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Remove highlight after 3 seconds
                setTimeout(function() {
                    studentRow.classList.remove('bg-green-50', 'animate-pulse');
                }, 3000);
            }
            
            if (studentCard) {
                // Scroll to the updated card
                studentCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Remove highlight after 3 seconds
                setTimeout(function() {
                    studentCard.classList.remove('bg-green-50', 'animate-pulse');
                }, 3000);
            }
            
            // Scroll to top to show success message
            setTimeout(function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
            
            // Clean URL after highlighting
            setTimeout(function() {
                const newUrl = window.location.pathname + window.location.search.replace(/[?&]approved=[^&]*/, '').replace(/^&/, '?');
                window.history.replaceState({}, '', newUrl);
            }, 3000);
        }
        
        // Check if there's a success message
        const successMsg = document.querySelector('.bg-green-50');
        if (successMsg) {
            // Auto-hide success message after 5 seconds
            setTimeout(function() {
                successMsg.style.transition = 'opacity 0.5s ease-out';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }, 5000);
        }
    });
    </script>
    
    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>