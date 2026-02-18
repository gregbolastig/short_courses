<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/system_activity_logger.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Manage Advisers';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Manage Advisers', 'icon' => 'fas fa-chalkboard-teacher']
];

// Initialize system activity logger
$logger = new SystemActivityLogger();

// Initialize variables to prevent undefined warnings
$total_advisers_count = 0;
$advisers = [];
$total_pages = 0;
$total_advisers = 0;
$search = '';

// Handle delete operation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get adviser info before deleting for logging
        $stmt = $conn->prepare("SELECT adviser_name FROM advisers WHERE adviser_id = ?");
        $stmt->execute([$_GET['delete']]);
        $adviser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $conn->prepare("DELETE FROM advisers WHERE adviser_id = ?");
        $stmt->execute([$_GET['delete']]);
        
        // Log adviser deletion
        if ($adviser) {
            $logger->log(
                'adviser_deleted',
                "Admin deleted adviser '{$adviser['adviser_name']}' (ID: {$_GET['delete']})",
                'admin',
                $_SESSION['user_id'],
                'adviser',
                $_GET['delete']
            );
        }
        
        $success_message = 'Adviser deleted successfully!';
    } catch (PDOException $e) {
        $error_message = 'Cannot delete adviser: ' . $e->getMessage();
    }
}

// Handle success messages from other pages
if (isset($_GET['success'])) {
    $success_type = $_GET['success'];
    $adviser_name = isset($_GET['name']) ? $_GET['name'] : 'Adviser';
    
    switch ($success_type) {
        case 'created':
            $success_message = 'Adviser created successfully!';
            $show_success_modal = true;
            $modal_title = 'Adviser Created Successfully!';
            $modal_message = "The adviser '{$adviser_name}' has been successfully added to your system.";
            $modal_icon = 'fas fa-check-circle';
            break;
        case 'updated':
            $success_message = 'Adviser updated successfully!';
            $show_success_modal = true;
            $modal_title = 'Adviser Updated Successfully!';
            $modal_message = "The adviser '{$adviser_name}' has been successfully updated.";
            $modal_icon = 'fas fa-edit';
            break;
    }
}

// Get advisers with pagination
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_condition = '';
    $params = [];
    
    if (!empty($search)) {
        $search_condition = "WHERE adviser_name LIKE :search";
        $search_param = "%$search%";
        $params[':search'] = $search_param;
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM advisers $search_condition";
    $stmt = $conn->prepare($count_sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $total_advisers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_advisers / $per_page);
    
    // Get advisers
    $sql = "SELECT * FROM advisers $search_condition ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    // Bind search parameters if they exist
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $conn->query("SELECT COUNT(*) as total FROM advisers");
    $total_advisers_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Set default values in case of error
    $total_advisers_count = 0;
    $advisers = [];
    $total_pages = 0;
    $total_advisers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Advisers - Student Registration System</title>
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
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Adviser Management</h1>
                                    <p class="text-lg text-gray-600 mt-2">Manage your educational advisers and mentors</p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <a href="add.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add New Adviser
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        
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

                        <!-- Advisers Table -->
                        <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-blue-100 rounded-xl p-2">
                                            <i class="fas fa-list text-blue-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Adviser Directory</h3>
                                    </div>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                                        <!-- Search Bar -->
                                        <form method="GET" action="" class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search advisers..." 
                                                   class="block w-full sm:w-80 pl-12 pr-4 py-3 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm transition-all duration-200">
                                            <?php if (!empty($search)): ?>
                                                <a href="index.php" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                                    <i class="fas fa-times text-gray-400 hover:text-gray-600 transition-colors duration-200"></i>
                                                </a>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($advisers)): ?>
                                <div class="text-center py-16">
                                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <i class="fas fa-chalkboard-teacher text-blue-600 text-3xl"></i>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No advisers found</h3>
                                    <p class="text-lg text-gray-600 mb-8 px-4 max-w-md mx-auto">
                                        <?php echo !empty($search) ? 'No advisers match your search criteria. Try adjusting your search terms.' : 'Ready to get started? Add your first adviser to begin building your educational team.'; ?>
                                    </p>
                                    <?php if (empty($search)): ?>
                                        <a href="add.php" class="inline-flex items-center px-8 py-4 border border-transparent text-lg font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-plus mr-3"></i>
                                            Add Your First Adviser
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Desktop Table View -->
                                <div class="hidden md:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="px-8 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Adviser Name</th>
                                                <th class="px-8 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Created</th>
                                                <th class="px-8 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($advisers as $adviser): ?>
                                                <tr class="hover:bg-blue-50 transition-all duration-200 border-b border-gray-100">
                                                    <td class="px-8 py-6">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="bg-blue-100 rounded-lg p-2">
                                                                <i class="fas fa-user-tie text-blue-600"></i>
                                                            </div>
                                                            <div>
                                                                <div class="text-lg font-semibold text-gray-900">
                                                                    <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-8 py-6">
                                                        <div class="flex items-center space-x-2">
                                                            <i class="fas fa-calendar-alt text-gray-400"></i>
                                                            <span class="text-sm font-medium text-gray-900">
                                                                <?php echo date('M j, Y', strtotime($adviser['created_at'])); ?>
                                                            </span>
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            <?php echo date('g:i A', strtotime($adviser['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-8 py-6">
                                                        <div class="flex items-center justify-center space-x-3">
                                                            <a href="edit.php?id=<?php echo $adviser['adviser_id']; ?>" 
                                                               class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105 shadow-sm">
                                                                <i class="fas fa-edit mr-2"></i>Edit
                                                            </a>
                                                            
                                                            <button onclick="confirmDelete('<?php echo htmlspecialchars($adviser['adviser_name']); ?>', <?php echo $adviser['adviser_id']; ?>)"
                                                               class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 transform hover:scale-105 shadow-sm">
                                                                <i class="fas fa-trash mr-2"></i>Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card View -->
                                <div class="md:hidden">
                                    <?php foreach ($advisers as $adviser): ?>
                                        <div class="border-b border-gray-200 p-6 hover:bg-blue-50 transition-all duration-200">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex items-center space-x-3 flex-1">
                                                    <div class="bg-blue-100 rounded-lg p-2">
                                                        <i class="fas fa-user-tie text-blue-600"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <h4 class="text-lg font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($adviser['adviser_name']); ?>
                                                        </h4>
                                                        <div class="flex items-center space-x-2 mt-2">
                                                            <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                                            <span class="text-sm text-gray-600">
                                                                Created: <?php echo date('M j, Y', strtotime($adviser['created_at'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-3">
                                                <a href="edit.php?id=<?php echo $adviser['adviser_id']; ?>" 
                                                   class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105">
                                                    <i class="fas fa-edit mr-2"></i>Edit Adviser
                                                </a>
                                                <button onclick="confirmDelete('<?php echo htmlspecialchars($adviser['adviser_name']); ?>', <?php echo $adviser['adviser_id']; ?>)"
                                                   class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 transform hover:scale-105">
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
                                            Showing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span> to <span class="font-bold text-gray-900"><?php echo min($offset + $per_page, $total_advisers); ?></span> of <span class="font-bold text-gray-900"><?php echo $total_advisers; ?></span> advisers
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Button -->
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
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
                                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                                           class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Next Button -->
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
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
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay with blur effect -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeDeleteModal()"></div>

            <!-- Modal panel with enhanced design -->
            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
                <!-- Header Section -->
                <div class="text-center mb-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-red-100 to-red-200 mb-4 shadow-lg">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-inner">
                            <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2" id="modal-title">
                        Delete Adviser
                    </h3>
                    <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto"></div>
                </div>

                <!-- Content Section -->
                <div class="text-center mb-8">
                    <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
                        <div class="flex items-center justify-center space-x-3 mb-2">
                            <div class="bg-blue-100 rounded-lg p-2">
                                <i class="fas fa-user-tie text-blue-600"></i>
                            </div>
                            <span class="font-semibold text-gray-900 text-lg" id="adviserNameToDelete"></span>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        This action will permanently remove the adviser from your system. All associated data will be lost and cannot be recovered.
                    </p>
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center justify-center space-x-2 text-red-700">
                            <i class="fas fa-info-circle text-sm"></i>
                            <span class="text-sm font-medium">This action cannot be undone</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row-reverse gap-3">
                    <button type="button" id="confirmDeleteBtn" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                        <i class="fas fa-trash mr-2"></i>
                        Delete Adviser
                    </button>
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <?php if (isset($show_success_modal) && $show_success_modal): ?>
    <div id="successModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay with blur effect -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeSuccessModal()"></div>

            <!-- Modal panel with enhanced design -->
            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
                <!-- Header Section -->
                <div class="text-center mb-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-green-100 to-green-200 mb-4 shadow-lg">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-inner">
                            <i class="<?php echo $modal_icon ?? 'fas fa-check-circle'; ?> text-white text-lg"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2" id="modal-title">
                        <?php echo htmlspecialchars($modal_title ?? 'Success!'); ?>
                    </h3>
                    <div class="w-12 h-1 bg-gradient-to-r from-green-500 to-green-600 rounded-full mx-auto"></div>
                </div>

                <!-- Content Section -->
                <div class="text-center mb-8">
                    <div class="bg-green-50 rounded-xl p-4 mb-4 border border-green-200">
                        <div class="flex items-center justify-center space-x-3 mb-2">
                            <div class="bg-blue-100 rounded-lg p-2">
                                <i class="fas fa-chalkboard-teacher text-blue-600"></i>
                            </div>
                            <span class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($_GET['name'] ?? 'Adviser'); ?></span>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed">
                        <?php echo htmlspecialchars($modal_message ?? 'Operation completed successfully!'); ?>
                    </p>
                    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center justify-center space-x-2 text-green-700">
                            <i class="fas fa-info-circle text-sm"></i>
                            <span class="text-sm font-medium">You can now manage this adviser from the list below</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="closeSuccessModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transform transition-all duration-200 hover:scale-105">
                        <i class="fas fa-check mr-2"></i>
                        Great!
                    </button>
                    <a href="add.php" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Add Another
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        let adviserToDelete = null;
        
        function confirmDelete(adviserName, adviserId) {
            adviserToDelete = adviserId;
            document.getElementById('adviserNameToDelete').textContent = adviserName;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        
        function closeDeleteModal() {
            adviserToDelete = null;
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                // Clean URL by removing success parameters
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('name');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }
        
        // Auto-show success modal if it exists
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = document.getElementById('successModal');
            if (successModal) {
                document.body.classList.add('overflow-hidden');
            }
        });
        
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (adviserToDelete) {
                window.location.href = `?delete=${adviserToDelete}`;
            }
        });
        
        // Close modals on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    closeDeleteModal();
                }
                if (document.getElementById('successModal') && !document.getElementById('successModal').classList.contains('hidden')) {
                    closeSuccessModal();
                }
            }
        });
    </script>
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>