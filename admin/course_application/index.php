<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Course Applications - Browse & View';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Course Applications', 'icon' => 'fas fa-file-alt']
];

// Initialize variables
$applications = [];
$total_applications = 0;
$total_pages = 0;
$error_message = '';
$success_message = '';

// Handle success messages from other pages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'updated':
            $success_message = 'Course application updated successfully!';
            break;
    }
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get application info before deleting
        $stmt = $conn->prepare("SELECT ca.*, s.first_name, s.last_name FROM course_applications ca 
                               JOIN students s ON ca.student_id = s.id 
                               WHERE ca.application_id = ?");
        $stmt->execute([$_GET['delete']]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($app) {
            $stmt = $conn->prepare("DELETE FROM course_applications WHERE application_id = ?");
            $stmt->execute([$_GET['delete']]);
            
            $success_message = "Application for {$app['first_name']} {$app['last_name']} deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = 'Cannot delete application: ' . $e->getMessage();
    }
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $application_id = $_POST['application_id'] ?? '';
    
    if ($action === 'approve' && !empty($application_id)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
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
                $notes = $_POST['notes'] ?? '';
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
                if (file_exists(__DIR__ . '/../../includes/system_activity_logger.php')) {
                    require_once __DIR__ . '/../../includes/system_activity_logger.php';
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
                
            }
        } catch (Exception $e) {
            // Rollback the transaction on error
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif ($action === 'reject' && !empty($application_id)) {
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
            $stmt->bindParam(':notes', $_POST['notes'] ?? '');
            $stmt->bindParam(':id', $application_id);
            
            if ($stmt->execute()) {
                $success_message = 'Course application rejected.';
            } else {
                $error_message = 'Failed to reject course application.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get applications with pagination and filtering
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Filtering
    $status_filter = $_GET['status'] ?? '';
    $course_filter = $_GET['course'] ?? '';
    $search = $_GET['search'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    // Only show completed applications in this browser
    // Pending and approved applications should be handled elsewhere
    $where_conditions[] = "ca.status = 'completed'";
    
    if (!empty($status_filter)) {
        $where_conditions[] = "ca.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($course_filter)) {
        $where_conditions[] = "c.course_name = :course_name";
        $params[':course_name'] = $course_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search OR s.uli LIKE :search OR c.course_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($start_date)) {
        $where_conditions[] = "DATE(ca.applied_at) >= :start_date";
        $params[':start_date'] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where_conditions[] = "DATE(ca.applied_at) <= :end_date";
        $params[':end_date'] = $end_date;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM course_applications ca 
                  INNER JOIN students s ON ca.student_id = s.id 
                  LEFT JOIN courses c ON ca.course_id = c.course_id 
                  $where_clause";
    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_applications / $per_page);
    
    // Get applications with additional data for modal
    $sql = "SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, s.uli, s.contact_number,
                   s.email, s.age, s.sex, s.civil_status, s.province, s.city, s.barangay,
                   c.course_name, ca.nc_level,
                   u.username as reviewed_by_name
            FROM course_applications ca
            INNER JOIN students s ON ca.student_id = s.id
            LEFT JOIN courses c ON ca.course_id = c.course_id
            LEFT JOIN users u ON ca.reviewed_by = u.id
            $where_clause
            ORDER BY ca.applied_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get courses from courses table for filter dropdown and modal
    $stmt = $conn->query("SELECT course_id, course_name FROM courses WHERE is_active = 1 ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available advisers for modal
    $stmt = $conn->query("SELECT adviser_name FROM advisers WHERE is_active = 1 ORDER BY adviser_name");
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM course_applications");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $applications = [];
    $total_applications = 0;
    $total_pages = 0;
    $courses = [];
    $advisers = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Student Registration System</title>
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
    <?php include '../components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
        <?php include '../components/sidebar.php'; ?>
        
        <!-- Main content wrapper -->
        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include '../components/header.php'; ?>
            
            <!-- Main content area -->
            <main class="overflow-y-auto focus:outline-none">
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        
                        <!-- Page Header -->
                        <div class="mb-8 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Course Applications Browser</h1>
                                    <p class="text-lg text-gray-600 mt-2">Browse and view detailed student course applications</p>
                                </div>
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
                                        <div class="flex items-center text-blue-700">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <span class="text-sm font-medium">View Mode - Browse Applications</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Alerts -->
                        <?php if ($error_message): ?>
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
                        
                        <?php if ($success_message): ?>
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

                        <!-- Search and Filter Section -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                            
                            <form method="GET" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                            <input type="text" id="search" name="search" 
                                                   placeholder="Student name, email, ID..." 
                                                   value="<?php echo htmlspecialchars($search); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                        <select id="status" name="status" 
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo ($status_filter === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="course" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                                         <select id="course" name="course" 
                                                 class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                             <option value="">All Courses</option>
                                             <?php foreach ($courses as $course): ?>
                                                 <option value="<?php echo htmlspecialchars($course['course_name']); ?>" 
                                                         <?php echo ($course_filter == $course['course_name']) ? 'selected' : ''; ?>>
                                                     <?php echo htmlspecialchars($course['course_name']); ?>
                                                 </option>
                                             <?php endforeach; ?>
                                         </select>
                                    </div>
                                    
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-calendar text-gray-400"></i>
                                            </div>
                                            <input type="date" id="start_date" name="start_date" 
                                                   value="<?php echo htmlspecialchars($start_date); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-calendar text-gray-400"></i>
                                            </div>
                                            <input type="date" id="end_date" name="end_date" 
                                                   value="<?php echo htmlspecialchars($end_date); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-end">
                                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                            <i class="fas fa-filter mr-2"></i>Apply Filters
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex justify-start pt-4 border-t border-gray-200">
                                    <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                                        <i class="fas fa-times mr-2"></i>Clear Filters
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Applications Table -->
                        <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-indigo-100 rounded-xl p-2">
                                            <i class="fas fa-eye text-indigo-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Application Browser</h3>
                                    </div>
                                    <?php if ($total_pages > 1): ?>
                                        <div class="flex items-center">
                                            <div class="bg-white rounded-lg px-3 py-2 border border-gray-200 shadow-sm">
                                                <span class="text-sm font-medium text-gray-600">
                                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (empty($applications)): ?>
                                <div class="text-center py-16">
                                    <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <i class="fas fa-search text-indigo-600 text-3xl"></i>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No applications found</h3>
                                    <p class="text-lg text-gray-600 mb-8 px-4 max-w-md mx-auto">
                                        <?php echo !empty($search) || !empty($status_filter) || !empty($course_filter) ? 'No applications match your search criteria. Try adjusting your filters to view different applications.' : 'No course applications have been submitted yet. Check back later for new applications to view.'; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- Desktop Table View -->
                                <div class="hidden lg:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Student Information</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Course Details</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Application Info</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($applications as $app): ?>
                                                <tr class="hover:bg-blue-50 transition-all duration-200">
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-10 w-10">
                                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                                    <span class="text-sm font-medium text-blue-600">
                                                                        <?php echo strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900">
                                                                    <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></div>
                                                        <?php if ($app['nc_level']): ?>
                                                            <div class="text-sm text-gray-500">NC Level: <?php echo htmlspecialchars($app['nc_level']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900">Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php
                                                        $status_class = '';
                                                        switch ($app['status']) {
                                                            case 'completed':
                                                                $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                                break;
                                                            case 'approved':
                                                                $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                                break;
                                                            case 'rejected':
                                                                $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                                break;
                                                            case 'pending':
                                                                $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                                break;
                                                            default:
                                                                $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                        }
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($app['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-center">
                                                        <div class="flex items-center justify-center space-x-2">
                                                            <a href="view.php?id=<?php echo $app['application_id']; ?>" 
                                                               class="inline-flex items-center px-3 py-1.5 border border-indigo-300 text-xs font-semibold rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                               title="View Details">
                                                                <i class="fas fa-eye mr-1"></i>
                                                            </a>
                                                            
                                                            <a href="edit.php?id=<?php echo $app['application_id']; ?>" 
                                                               class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-semibold rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                                                               title="Edit Application">
                                                                <i class="fas fa-edit mr-1"></i>
                                                            </a>
                                                        
                                                            <button onclick="confirmDelete(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>')" 
                                                                    class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-md text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                                                    title="Delete Application">
                                                                <i class="fas fa-trash mr-1"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card View -->
                                <div class="lg:hidden">
                                    <?php foreach ($applications as $app): ?>
                                        <div class="border-b border-gray-200 p-6 hover:bg-blue-50 transition-all duration-200">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex items-center space-x-3 flex-1">
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-blue-600">
                                                            <?php echo strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex-1">
                                                        <h4 class="text-lg font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                                        </h4>
                                                        <p class="text-sm font-medium text-gray-700 mt-1">
                                                            <?php echo htmlspecialchars($app['course_name']); ?>
                                                            <?php if ($app['nc_level']): ?>
                                                                - <?php echo htmlspecialchars($app['nc_level']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                        <?php if ($app['adviser']): ?>
                                                            <p class="text-sm text-blue-600 mt-1">
                                                                Adviser: <?php echo htmlspecialchars($app['adviser']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            Applied: <?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?>
                                                        </p>
                                                        <?php if ($app['training_start'] && $app['training_end']): ?>
                                                            <p class="text-xs text-green-600 mt-1">
                                                                Training: <?php echo date('M j', strtotime($app['training_start'])); ?> - <?php echo date('M j, Y', strtotime($app['training_end'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <?php
                                                    $status_class = '';
                                                    switch ($app['status']) {
                                                        case 'completed':
                                                            $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                            break;
                                                        case 'approved':
                                                            $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                    }
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center justify-center space-x-2 mt-4 flex-wrap gap-2">
                                                <a href="view.php?id=<?php echo $app['application_id']; ?>" 
                                                   class="inline-flex items-center justify-center px-4 py-2 border border-indigo-300 text-sm font-semibold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                                    <i class="fas fa-eye mr-2"></i>View
                                                </a>
                                                
                                                <a href="edit.php?id=<?php echo $app['application_id']; ?>" 
                                                   class="inline-flex items-center justify-center px-4 py-2 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                    <i class="fas fa-edit mr-2"></i>Edit
                                                </a>
                                            
                                                <button onclick="confirmDelete(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>')" 
                                                        class="inline-flex items-center justify-center px-4 py-2 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                    <i class="fas fa-trash mr-2"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="px-6 py-5 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                        <div class="text-sm font-medium text-gray-700">
                                            Viewing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span> to <span class="font-bold text-gray-900"><?php echo min($offset + $per_page, $total_applications); ?></span> of <span class="font-bold text-gray-900"><?php echo $total_applications; ?></span> applications
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Button -->
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <div class="hidden sm:flex items-center space-x-1">
                                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <?php if ($i == $page): ?>
                                                        <span class="inline-flex items-center justify-center w-10 h-10 border-2 border-indigo-500 rounded-lg text-sm font-bold text-white bg-indigo-600 shadow-md"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                                                           class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Next Button -->
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form method="POST" id="approvalForm">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="application_id" id="modal_application_id">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Approve Course Application
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500" id="modal_student_info">
                                        <!-- Student info will be populated here -->
                                    </p>
                                </div>
                                
                                <!-- Form Fields -->
                                <div class="mt-4 space-y-4">
                                    <!-- Course Selection -->
                                    <div>
                                        <label for="modal_course_name" class="block text-sm font-medium text-gray-700 mb-1">
                                            <i class="fas fa-book text-blue-600 mr-2"></i>Course *
                                        </label>
                                        <select id="modal_course_name" name="course_name" required 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="">Select a course</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo htmlspecialchars($course['course_id']); ?>">
                                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- NC Level -->
                                    <div>
                                        <label for="modal_nc_level" class="block text-sm font-medium text-gray-700 mb-1">
                                            <i class="fas fa-certificate text-blue-600 mr-2"></i>NC Level *
                                        </label>
                                        <select id="modal_nc_level" name="nc_level" required 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="">Select NC Level</option>
                                            <option value="NC I">NC I</option>
                                            <option value="NC II">NC II</option>
                                            <option value="NC III">NC III</option>
                                            <option value="NC IV">NC IV</option>
                                        </select>
                                    </div>

                                    <!-- Adviser Selection -->
                                    <div>
                                        <label for="modal_adviser" class="block text-sm font-medium text-gray-700 mb-1">
                                            <i class="fas fa-user-tie text-blue-600 mr-2"></i>Assigned Adviser *
                                        </label>
                                        <select id="modal_adviser" name="adviser" required 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="">Select an adviser</option>
                                            <?php foreach ($advisers as $adviser): ?>
                                                <option value="<?php echo htmlspecialchars($adviser['adviser_name']); ?>">
                                                    <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Training Period -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="modal_training_start" class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>Training Start Date *
                                            </label>
                                            <input type="date" id="modal_training_start" name="training_start" required 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                        </div>

                                        <div>
                                            <label for="modal_training_end" class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-calendar-check text-blue-600 mr-2"></i>Training End Date *
                                            </label>
                                            <input type="date" id="modal_training_end" name="training_end" required 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                        </div>
                                    </div>

                                    <!-- Notes -->
                                    <div>
                                        <label for="modal_notes" class="block text-sm font-medium text-gray-700 mb-1">
                                            <i class="fas fa-sticky-note text-blue-600 mr-2"></i>Notes (Optional)
                                        </label>
                                        <textarea id="modal_notes" name="notes" rows="3" 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                                  placeholder="Add any additional notes..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-check mr-2"></i>Approve Application
                        </button>
                        <button type="button" onclick="closeApprovalModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="rejection-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" id="rejectionForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="application_id" id="reject_application_id">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-times text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="rejection-modal-title">
                                    Reject Course Application
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500" id="reject_student_info">
                                        <!-- Student info will be populated here -->
                                    </p>
                                </div>
                                
                                <!-- Notes -->
                                <div class="mt-4">
                                    <label for="reject_notes" class="block text-sm font-medium text-gray-700 mb-1">
                                        Reason for Rejection *
                                    </label>
                                    <textarea id="reject_notes" name="notes" rows="4" required
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                              placeholder="Please provide a reason for rejecting this application..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-times mr-2"></i>Reject Application
                        </button>
                        <button type="button" onclick="closeRejectionModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeDeleteModal()"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
                <!-- Header Section -->
                <div class="text-center mb-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-red-100 to-red-200 mb-4 shadow-lg">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-inner">
                            <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">
                        Delete Application
                    </h3>
                    <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto"></div>
                </div>

                <!-- Content Section -->
                <div class="text-center mb-8">
                    <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
                        <div class="flex items-center justify-center space-x-3 mb-2">
                            <div class="bg-blue-100 rounded-lg p-2">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <span class="font-semibold text-gray-900 text-lg" id="deleteStudentName"></span>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        This action will permanently remove the course application from your system. All associated data will be lost and cannot be recovered.
                    </p>
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center justify-center space-x-2 text-red-700">
                            <i class="fas fa-info-circle text-sm"></i>
                            <span class="text-sm font-medium">This action cannot be undone</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="executeDelete()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                        <i class="fas fa-trash mr-2"></i>
                        Delete Application
                    </button>
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openApprovalModal(application) {
            // Populate modal with application data
            document.getElementById('modal_application_id').value = application.application_id;
            document.getElementById('modal_student_info').textContent = 
                `Approving application for ${application.first_name} ${application.last_name} (ULI: ${application.uli})`;
            
            // Pre-fill form fields if data exists
            if (application.course_id) {
                document.getElementById('modal_course_name').value = application.course_id;
            }
            if (application.nc_level) {
                document.getElementById('modal_nc_level').value = application.nc_level;
            }
            
            // Show modal
            document.getElementById('approvalModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            // Reset form
            document.getElementById('approvalForm').reset();
        }

        function openRejectionModal(applicationId, studentName) {
            document.getElementById('reject_application_id').value = applicationId;
            document.getElementById('reject_student_info').textContent = 
                `Are you sure you want to reject the application for ${studentName}?`;
            
            // Show modal
            document.getElementById('rejectionModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            // Reset form
            document.getElementById('rejectionForm').reset();
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const approvalModal = document.getElementById('approvalModal');
            const rejectionModal = document.getElementById('rejectionModal');
            
            if (event.target === approvalModal) {
                closeApprovalModal();
            }
            if (event.target === rejectionModal) {
                closeRejectionModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeApprovalModal();
                closeRejectionModal();
            }
        });

        // Form validation
        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            const requiredFields = ['course_name', 'nc_level', 'adviser', 'training_start', 'training_end'];
            let isValid = true;
            
            requiredFields.forEach(function(fieldName) {
                const field = document.getElementById('modal_' + fieldName);
                if (!field.value.trim()) {
                    field.classList.add('border-red-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Date validation
        document.getElementById('modal_training_start').addEventListener('change', function() {
            const startDate = this.value;
            const endDateField = document.getElementById('modal_training_end');
            
            if (startDate) {
                endDateField.min = startDate;
                if (endDateField.value && endDateField.value < startDate) {
                    endDateField.value = '';
                }
            }
        });
        
        // Delete confirmation function
        let applicationToDelete = null;
        let studentToDelete = '';
        
        function confirmDelete(applicationId, studentName) {
            applicationToDelete = applicationId;
            studentToDelete = studentName;
            document.getElementById('deleteStudentName').textContent = studentName;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        
        function closeDeleteModal() {
            applicationToDelete = null;
            studentToDelete = '';
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        function executeDelete() {
            if (applicationToDelete) {
                window.location.href = `?delete=${applicationToDelete}`;
            }
        }
    </script>

    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>