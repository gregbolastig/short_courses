<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Course Applications';

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

// Handle application approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['application_id'])) {
    $action = $_POST['action'];
    $application_id = $_POST['application_id'];
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($action === 'approve') {
            $adviser_id = $_POST['adviser_id'] ?? null;
            $training_start = $_POST['training_start'] ?? null;
            $training_end = $_POST['training_end'] ?? null;
            $notes = $_POST['notes'] ?? '';
            
            // Begin transaction for two-stage approval
            $conn->beginTransaction();
            
            // Get application details
            $stmt = $conn->prepare("SELECT * FROM course_applications WHERE application_id = ? AND status = 'pending'");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$application) {
                throw new Exception('Application not found or already processed');
            }
            
            // Get adviser name if adviser_id is provided
            $adviser_name = null;
            if ($adviser_id) {
                $stmt = $conn->prepare("SELECT adviser_name FROM advisers WHERE adviser_id = ?");
                $stmt->execute([$adviser_id]);
                $adviser_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $adviser_name = $adviser_result ? $adviser_result['adviser_name'] : null;
            }
            
            // Update application with approval details
            $stmt = $conn->prepare("UPDATE course_applications SET 
                status = 'approved',
                nc_level = ?,
                adviser = ?,
                training_start = ?,
                training_end = ?,
                reviewed_by = ?,
                reviewed_at = NOW(),
                notes = ?
                WHERE application_id = ?");
            $stmt->execute([
                $_POST['nc_level'] ?? $application['nc_level'] ?? null,
                $adviser_name,
                $training_start,
                $training_end,
                $_SESSION['user_id'],
                $notes,
                $application_id
            ]);
            
            // Update student record with course details (status: approved)
            $stmt = $conn->prepare("UPDATE students SET 
                status = 'approved',
                course = ?,
                nc_level = ?,
                adviser = ?,
                training_start = ?,
                training_end = ?,
                approved_by = ?,
                approved_at = NOW()
                WHERE id = ?");
            $stmt->execute([
                $application['course_name'],
                $_POST['nc_level'] ?? $application['nc_level'] ?? null,
                $adviser_name,
                $training_start,
                $training_end,
                $_SESSION['user_id'],
                $application['student_id']
            ]);
            
            $conn->commit();
            $success_message = 'Application approved and enrollment created successfully!';
            
        } elseif ($action === 'reject') {
            $notes = $_POST['notes'] ?? '';
            
            $stmt = $conn->prepare("UPDATE course_applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE application_id = ?");
            $stmt->execute([$_SESSION['user_id'], $notes, $application_id]);
            
            $success_message = 'Application rejected successfully.';
        }
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollback();
        }
        $error_message = 'Error: ' . $e->getMessage();
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
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($status_filter)) {
        $where_conditions[] = "ca.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($course_filter)) {
        $where_conditions[] = "ca.course_name = :course_name";
        $params[':course_name'] = $course_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search OR s.uli LIKE :search OR ca.course_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM course_applications ca 
                  INNER JOIN students s ON ca.student_id = s.id 
                  LEFT JOIN courses c ON ca.course_name = c.course_name 
                  $where_clause";
    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_applications / $per_page);
    
    // Get applications
    $sql = "SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, s.email, s.uli, s.contact_number,
                   ca.course_name, ca.adviser, ca.nc_level, ca.training_start, ca.training_end,
                   u.username as reviewed_by_name
            FROM course_applications ca
            INNER JOIN students s ON ca.student_id = s.id
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
    
    // Get courses for filter dropdown
    $stmt = $conn->query("SELECT DISTINCT course_name FROM course_applications WHERE course_name IS NOT NULL ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get advisers for approval modal
    $stmt = $conn->query("SELECT adviser_id, adviser_name FROM advisers WHERE is_active = TRUE ORDER BY adviser_name");
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
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Course Applications</h1>
                                    <p class="text-lg text-gray-600 mt-2">Review and manage student course applications</p>
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

                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-blue-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-file-alt text-blue-900 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Applications</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo $stats['total']; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Review</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-green-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-check text-green-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Approved</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-green-600"><?php echo $stats['approved']; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-red-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-times text-red-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Rejected</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-red-600"><?php echo $stats['rejected']; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3">
                                        <i class="fas fa-search text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Search & Filter Applications</h2>
                                        <p class="text-sm text-gray-600">Find applications by student or course</p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="GET" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                                    
                                    <div class="flex items-end">
                                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-search mr-2"></i>Apply Filters
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
                                        <div class="bg-blue-100 rounded-xl p-2">
                                            <i class="fas fa-list text-blue-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Course Applications</h3>
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
                                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <i class="fas fa-file-alt text-blue-600 text-3xl"></i>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No applications found</h3>
                                    <p class="text-lg text-gray-600 mb-8 px-4 max-w-md mx-auto">
                                        <?php echo !empty($search) || !empty($status_filter) || !empty($course_filter) ? 'No applications match your search criteria. Try adjusting your filters.' : 'No course applications have been submitted yet.'; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- Desktop Table View -->
                                <div class="hidden lg:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Course</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Applied</th>
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
                                                                <div class="text-sm text-gray-500">
                                                                    <?php echo htmlspecialchars($app['email']); ?>
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
                                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php
                                                        $status_class = '';
                                                        switch ($app['status']) {
                                                            case 'approved':
                                                                $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                                break;
                                                            case 'rejected':
                                                                $status_class = 'bg-red-100 text-red-800 border-red-200';
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
                                                        <?php if ($app['status'] === 'pending'): ?>
                                                            <div class="flex items-center justify-center space-x-2">
                                                                <button onclick="openApprovalModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>', '<?php echo htmlspecialchars($app['course_name']); ?>')"
                                                                        class="inline-flex items-center px-3 py-1.5 border border-green-300 text-xs font-semibold rounded-lg text-green-700 bg-green-50 hover:bg-green-100 hover:border-green-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                                <button onclick="openRejectModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>')"
                                                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                                    <i class="fas fa-times mr-1"></i>Reject
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-sm text-gray-500">
                                                                <?php echo $app['reviewed_by_name'] ? 'By ' . htmlspecialchars($app['reviewed_by_name']) : 'Processed'; ?>
                                                            </span>
                                                        <?php endif; ?>
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
                                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($app['email']); ?></p>
                                                        <p class="text-sm font-medium text-gray-700 mt-1">
                                                            <?php echo htmlspecialchars($app['course_name']); ?>
                                                            <?php if ($app['nc_level']): ?>
                                                                - <?php echo htmlspecialchars($app['nc_level']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            Applied: <?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <?php
                                                    $status_class = '';
                                                    switch ($app['status']) {
                                                        case 'approved':
                                                            $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-red-100 text-red-800 border-red-200';
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
                                            
                                            <?php if ($app['status'] === 'pending'): ?>
                                                <div class="flex items-center space-x-3">
                                                    <button onclick="openApprovalModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>', '<?php echo htmlspecialchars($app['course_name']); ?>')"
                                                            class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-green-300 text-sm font-semibold rounded-lg text-green-700 bg-green-50 hover:bg-green-100 hover:border-green-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                                                        <i class="fas fa-check mr-2"></i>Approve Application
                                                    </button>
                                                    <button onclick="openRejectModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>')"
                                                            class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                        <i class="fas fa-times mr-2"></i>Reject
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-sm text-gray-500 py-2">
                                                    <?php echo $app['reviewed_by_name'] ? 'Processed by ' . htmlspecialchars($app['reviewed_by_name']) : 'Application processed'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="px-6 py-5 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                        <div class="text-sm font-medium text-gray-700">
                                            Showing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span> to <span class="font-bold text-gray-900"><?php echo min($offset + $per_page, $total_applications); ?></span> of <span class="font-bold text-gray-900"><?php echo $total_applications; ?></span> applications
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
                                                        <span class="inline-flex items-center justify-center w-10 h-10 border-2 border-blue-500 rounded-lg text-sm font-bold text-white bg-blue-600 shadow-md"><?php echo $i; ?></span>
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
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeApprovalModal()"></div>
            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                <form method="POST" id="approvalForm">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="application_id" id="modalApplicationId">
                    
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-green-100 to-green-200 mb-4 shadow-lg">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-inner">
                                <i class="fas fa-check text-white text-lg"></i>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Approve Application</h3>
                        <p class="text-gray-600">Approve <span id="modalStudentName" class="font-semibold"></span>'s application for <span id="modalCourseName" class="font-semibold"></span></p>
                    </div>

                    <div class="space-y-4">
                         <div>
                             <label for="modalAdviser" class="block text-sm font-medium text-gray-700 mb-2">Assign Adviser</label>
                             <select id="modalAdviser" name="adviser_id" class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 text-sm">
                                 <option value="">Select Adviser (Optional)</option>
                                 <?php foreach ($advisers as $adviser): ?>
                                     <option value="<?php echo $adviser['adviser_id']; ?>">
                                         <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                         
                         <div>
                             <label for="modalNcLevel" class="block text-sm font-medium text-gray-700 mb-2">NC Level</label>
                             <select id="modalNcLevel" name="nc_level" class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 text-sm">
                                 <option value="">Select NC Level</option>
                                 <option value="NC I">NC I</option>
                                 <option value="NC II">NC II</option>
                                 <option value="NC III">NC III</option>
                                 <option value="NC IV">NC IV</option>
                             </select>
                         </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="modalTrainingStart" class="block text-sm font-medium text-gray-700 mb-2">Training Start</label>
                                <input type="date" id="modalTrainingStart" name="training_start" 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 text-sm">
                            </div>
                            <div>
                                <label for="modalTrainingEnd" class="block text-sm font-medium text-gray-700 mb-2">Training End</label>
                                <input type="date" id="modalTrainingEnd" name="training_end" 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 text-sm">
                            </div>
                        </div>

                        <div>
                            <label for="modalNotes" class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea id="modalNotes" name="notes" rows="3" 
                                      placeholder="Add any notes about this approval..."
                                      class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 text-sm"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeApprovalModal()" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                            <i class="fas fa-check mr-2"></i>Approve Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeRejectModal()"></div>
            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="application_id" id="rejectApplicationId">
                    
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-red-100 to-red-200 mb-4 shadow-lg">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-inner">
                                <i class="fas fa-times text-white text-lg"></i>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Application</h3>
                        <p class="text-gray-600">Reject <span id="rejectStudentName" class="font-semibold"></span>'s course application</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="rejectNotes" class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection</label>
                            <textarea id="rejectNotes" name="notes" rows="4" required
                                      placeholder="Please provide a reason for rejecting this application..."
                                      class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-red-500 focus:border-red-500 text-sm"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeRejectModal()" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Reject Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../components/admin-scripts.php'; ?>
    
    <script>
        function openApprovalModal(applicationId, studentName, courseName) {
            document.getElementById('modalApplicationId').value = applicationId;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalCourseName').textContent = courseName;
            document.getElementById('approvalModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.body.style.overflow = '';
            document.getElementById('approvalForm').reset();
        }

        function openRejectModal(applicationId, studentName) {
            document.getElementById('rejectApplicationId').value = applicationId;
            document.getElementById('rejectStudentName').textContent = studentName;
            document.getElementById('rejectModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.body.style.overflow = '';
            document.getElementById('rejectForm').reset();
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeApprovalModal();
                closeRejectModal();
            }
        });
    </script>
</body>
</html>