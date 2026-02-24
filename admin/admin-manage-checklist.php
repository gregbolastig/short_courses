<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

// Routing
$page = isset($_GET['page']) ? trim((string)$_GET['page']) : 'index';
$allowed_pages = ['index', 'add', 'edit'];
if (!in_array($page, $allowed_pages, true)) {
    $page = 'index';
}

// Use `p` for pagination to avoid clashing with routing param
$p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

// Shared vars
$page_title = 'Manage Checklist';
$breadcrumb_items = [];
$error_message = null;
$success_message = null;

// -----------------------
// INDEX: list checklist
// -----------------------
$total_checklist_count = 0;
$checklist_items = [];
$total_pages = 0;
$total_items = 0;
$search = '';

if ($page === 'index') {
    $page_title = 'Manage Checklist';
    $breadcrumb_items = [
        ['title' => 'Manage Checklist', 'icon' => 'fas fa-tasks']
    ];

    // Delete with password verification
    if (isset($_POST['action'], $_POST['id'], $_POST['admin_password']) && $_POST['action'] === 'delete' && is_numeric($_POST['id'])) {
        try {
            $item_id = (int)$_POST['id'];
            $admin_password = $_POST['admin_password'];
            
            // Verify admin password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin || !password_verify($admin_password, $admin['password'])) {
                $error_message = 'Invalid password. Deletion cancelled.';
            } else {
                // Password verified, proceed with deletion
                $stmt = $conn->prepare("SELECT document_name FROM checklist WHERE id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $conn->prepare("DELETE FROM checklist WHERE id = ?");
                $stmt->execute([$item_id]);

                if ($item) {
                    $logger->log(
                        'checklist_deleted',
                        "Admin deleted checklist item '{$item['document_name']}' (ID: {$item_id})",
                        'admin',
                        $_SESSION['user_id'],
                        'checklist',
                        $item_id
                    );
                }

                $success_message = 'Checklist item deleted successfully!';
            }
        } catch (PDOException $e) {
            $error_message = 'Cannot delete checklist item: ' . $e->getMessage();
        }
    }


    // Toast success from add/edit
    if (isset($_GET['success'])) {
        $success_type = (string)$_GET['success'];
        $item_name = isset($_GET['name']) ? (string)$_GET['name'] : 'Checklist Item';

        switch ($success_type) {
            case 'created':
                $toast_message = "Checklist item '{$item_name}' created successfully!";
                break;
            case 'updated':
                $toast_message = "Checklist item '{$item_name}' updated successfully!";
                break;
            default:
                $toast_message = "Operation completed successfully!";
        }
    }

    try {
        $per_page = 10;
        $offset = ($p - 1) * $per_page;

        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
        $search_condition = '';
        $params = [];

        if ($search !== '') {
            $search_condition = "WHERE document_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $count_sql = "SELECT COUNT(*) as total FROM checklist {$search_condition}";
        $stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_items = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $total_pages = (int)ceil($total_items / $per_page);

        $sql = "SELECT * FROM checklist {$search_condition} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $checklist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("SELECT COUNT(*) as total FROM checklist");
        $total_checklist_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        $total_checklist_count = 0;
        $checklist_items = [];
        $total_pages = 0;
        $total_items = 0;
    }
}

// -----------------------
// ADD: create item
// -----------------------
if ($page === 'add') {
    $page_title = 'Add Checklist Item';
    $breadcrumb_items = [
        ['title' => 'Manage Checklist', 'icon' => 'fas fa-tasks', 'url' => 'admin-manage-checklist.php?page=index'],
        ['title' => 'Add Item', 'icon' => 'fas fa-plus']
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $document_name = isset($_POST['document_name']) ? trim((string)$_POST['document_name']) : '';
            if ($document_name === '') {
                throw new RuntimeException('Document name is required.');
            }

            $stmt = $conn->prepare("INSERT INTO checklist (document_name) VALUES (?)");
            $stmt->execute([$document_name]);

            $logger->log(
                'checklist_created',
                "Admin created checklist item '{$document_name}'",
                'admin',
                $_SESSION['user_id'],
                'checklist',
                $conn->lastInsertId()
            );

            header("Location: " . basename(__FILE__) . "?page=index&success=created&name=" . urlencode($document_name));
            exit;
        } catch (Throwable $e) {
            $error_message = $e instanceof PDOException ? ('Database error: ' . $e->getMessage()) : $e->getMessage();
        }
    }
}

// -----------------------
// EDIT: update item
// -----------------------
$item_id = 0;
$checklist_item = null;

if ($page === 'edit') {
    $page_title = 'Edit Checklist Item';

    $item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($item_id <= 0) {
        header('Location: ' . basename(__FILE__) . '?page=index');
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM checklist WHERE id = ?");
        $stmt->execute([$item_id]);
        $checklist_item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$checklist_item || !isset($checklist_item['document_name'])) {
            header('Location: ' . basename(__FILE__) . '?page=index');
            exit;
        }

        $breadcrumb_items = [
            ['title' => 'Manage Checklist', 'icon' => 'fas fa-tasks', 'url' => 'admin-manage-checklist.php?page=index'],
            ['title' => 'Edit: ' . $checklist_item['document_name'], 'icon' => 'fas fa-edit']
        ];
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $document_name = isset($_POST['document_name']) ? trim((string)$_POST['document_name']) : '';
            if ($document_name === '') {
                throw new RuntimeException('Document name is required.');
            }

            $stmt = $conn->prepare("UPDATE checklist SET document_name = ? WHERE id = ?");
            $stmt->execute([$document_name, $item_id]);

            $logger->log(
                'checklist_updated',
                "Admin updated checklist item from '{$checklist_item['document_name']}' to '{$document_name}' (ID: {$item_id})",
                'admin',
                $_SESSION['user_id'],
                'checklist',
                $item_id
            );

            header("Location: " . basename(__FILE__) . "?page=index&success=updated&name=" . urlencode($document_name));
            exit;
        } catch (Throwable $e) {
            $error_message = $e instanceof PDOException ? ('Database error: ' . $e->getMessage()) : $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Student Registration System</title>
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
    <?php include 'components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
        <?php include 'components/sidebar.php'; ?>

        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include 'components/header.php'; ?>

            <main class="overflow-y-auto focus:outline-none">
                <?php if ($page === 'index'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <div class="mb-8 mt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div>
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Checklist Management</h1>
                                        <p class="text-lg text-gray-600 mt-2">Manage document requirements for student enrollment</p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="admin-manage-checklist.php?page=add" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-plus mr-2"></i>
                                            Add New Item
                                        </a>
                                    </div>
                                </div>
                            </div>

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

                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-blue-100 rounded-xl p-2">
                                                <i class="fas fa-list text-blue-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Document Requirements</h3>
                                        </div>
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                                            <form method="GET" action="admin-manage-checklist.php" class="relative flex-1 sm:flex-initial" id="searchForm">
                                                <input type="hidden" name="page" value="index">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-search text-gray-400"></i>
                                                </div>
                                                <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>"
                                                       placeholder="Search documents..."
                                                       class="block w-full sm:w-80 pl-12 <?php echo $search !== '' ? 'pr-20' : 'pr-4'; ?> py-3 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm transition-all duration-200"
                                                       oninput="handleSearch()">
                                                <?php if ($search !== ''): ?>
                                                    <a href="admin-manage-checklist.php?page=index" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-200" title="Clear search">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="submit" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-blue-600 transition-colors duration-200" title="Search">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($checklist_items)): ?>
                                    <div class="text-center py-16">
                                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                            <i class="fas fa-tasks text-blue-600 text-3xl"></i>
                                        </div>
                                        <h3 class="text-2xl font-bold text-gray-900 mb-3">No checklist items found</h3>
                                        <p class="text-lg text-gray-600 mb-8 px-4 max-w-md mx-auto">
                                            <?php echo $search !== '' ? 'No items match your search criteria. Try adjusting your search terms.' : 'Ready to get started? Add your first checklist item to begin managing document requirements.'; ?>
                                        </p>
                                        <?php if ($search === ''): ?>
                                            <a href="admin-manage-checklist.php?page=add" class="inline-flex items-center px-8 py-4 border border-transparent text-lg font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-plus mr-3"></i>
                                                Add Your First Item
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                                <tr>
                                                    <th class="px-8 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Document Name</th>
                                                    <th class="px-8 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Created</th>
                                                    <th class="px-8 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($checklist_items as $item): ?>
                                                    <tr class="hover:bg-blue-50 transition-all duration-200 border-b border-gray-100">
                                                        <td class="px-8 py-6">
                                                            <div class="flex items-center space-x-3">
                                                                <div class="bg-blue-100 rounded-lg p-2">
                                                                    <i class="fas fa-file-alt text-blue-600"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-lg font-semibold text-gray-900">
                                                                        <?php echo htmlspecialchars($item['document_name']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-8 py-6">
                                                            <div class="flex items-center space-x-2">
                                                                <i class="fas fa-calendar-alt text-gray-400"></i>
                                                                <span class="text-sm font-medium text-gray-900">
                                                                    <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                <?php echo date('g:i A', strtotime($item['created_at'])); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-8 py-6">
                                                            <div class="flex items-center justify-center space-x-3">
                                                                <a href="admin-manage-checklist.php?page=edit&id=<?php echo (int)$item['id']; ?>"
                                                                   class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105 shadow-sm">
                                                                    <i class="fas fa-edit mr-2"></i>Edit
                                                                </a>
                                                                <button onclick="confirmDelete('<?php echo htmlspecialchars($item['document_name']); ?>', <?php echo (int)$item['id']; ?>)"
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

                                    <div class="md:hidden">
                                        <?php foreach ($checklist_items as $item): ?>
                                            <div class="border-b border-gray-200 p-6 hover:bg-blue-50 transition-all duration-200">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex items-center space-x-3 flex-1">
                                                        <div class="bg-blue-100 rounded-lg p-2">
                                                            <i class="fas fa-file-alt text-blue-600"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <h4 class="text-lg font-semibold text-gray-900">
                                                                <?php echo htmlspecialchars($item['document_name']); ?>
                                                            </h4>
                                                            <div class="flex items-center space-x-2 mt-2">
                                                                <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                                                <span class="text-sm text-gray-600">
                                                                    Created: <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-3">
                                                    <a href="admin-manage-checklist.php?page=edit&id=<?php echo (int)$item['id']; ?>"
                                                       class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105">
                                                        <i class="fas fa-edit mr-2"></i>Edit Item
                                                    </a>
                                                    <button onclick="confirmDelete('<?php echo htmlspecialchars($item['document_name']); ?>', <?php echo (int)$item['id']; ?>)"
                                                       class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 transform hover:scale-105">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($total_pages > 1): ?>
                                    <?php $offset = ($p - 1) * 10; ?>
                                    <div class="px-6 py-5 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                            <div class="text-sm font-medium text-gray-700">
                                                Showing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span>
                                                to <span class="font-bold text-gray-900"><?php echo min($offset + 10, $total_items); ?></span>
                                                of <span class="font-bold text-gray-900"><?php echo $total_items; ?></span> items
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <?php if ($p > 1): ?>
                                                    <a href="admin-manage-checklist.php?page=index&p=<?php echo $p - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                        <i class="fas fa-chevron-left mr-2"></i>Previous
                                                    </a>
                                                <?php endif; ?>
                                                <div class="hidden sm:flex items-center space-x-1">
                                                    <?php for ($i = max(1, $p - 2); $i <= min($total_pages, $p + 2); $i++): ?>
                                                        <?php if ($i === $p): ?>
                                                            <span class="inline-flex items-center justify-center w-10 h-10 border-2 border-blue-500 rounded-lg text-sm font-bold text-white bg-blue-600 shadow-md"><?php echo $i; ?></span>
                                                        <?php else: ?>
                                                            <a href="admin-manage-checklist.php?page=index&p=<?php echo $i; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                               class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm"><?php echo $i; ?></a>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                <?php if ($p < $total_pages): ?>
                                                    <a href="admin-manage-checklist.php?page=index&p=<?php echo $p + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
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

                    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeDeleteModal()"></div>
                            <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
                                <div class="text-center mb-6">
                                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-red-100 to-red-200 mb-4 shadow-lg">
                                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-inner">
                                            <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                                        </div>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2" id="modal-title">
                                        Delete Checklist Item
                                    </h3>
                                    <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto"></div>
                                </div>
                                <div class="text-center mb-6">
                                    <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
                                        <div class="flex items-center justify-center space-x-3 mb-2">
                                            <div class="bg-blue-100 rounded-lg p-2">
                                                <i class="fas fa-file-alt text-blue-600"></i>
                                            </div>
                                            <span class="font-semibold text-gray-900 text-lg" id="itemNameToDelete"></span>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 leading-relaxed mb-4">
                                        This action will permanently remove the checklist item from your system.
                                    </p>
                                    <p class="text-sm text-red-600 font-medium">
                                        Enter your admin password to confirm this action.
                                    </p>
                                </div>
                                <form id="deleteForm" method="POST" action="admin-manage-checklist.php?page=index">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" id="deleteItemId" value="">
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                                        <input type="password" name="admin_password" id="adminPassword" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                               placeholder="Enter your password">
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-3">
                                        <button type="submit" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-trash mr-2"></i>
                                            Delete Item
                                        </button>
                                        <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-2"></i>
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script>
                        let itemToDelete = null;
                        let searchTimeout = null;

                        function handleSearch() {
                            if (searchTimeout) {
                                clearTimeout(searchTimeout);
                            }
                            searchTimeout = setTimeout(() => {
                                document.getElementById('searchForm').submit();
                            }, 500);
                        }

                        function confirmDelete(itemName, itemId) {
                            itemToDelete = itemId;
                            document.getElementById('itemNameToDelete').textContent = itemName;
                            document.getElementById('deleteItemId').value = itemId;
                            document.getElementById('adminPassword').value = '';
                            document.getElementById('deleteModal').classList.remove('hidden');
                            document.body.classList.add('overflow-hidden');
                            setTimeout(() => {
                                document.getElementById('adminPassword').focus();
                            }, 100);
                        }

                        function closeDeleteModal() {
                            itemToDelete = null;
                            document.getElementById('deleteModal').classList.add('hidden');
                            document.body.classList.remove('overflow-hidden');
                            document.getElementById('adminPassword').value = '';
                        }

                        document.addEventListener('keydown', function(event) {
                            if (event.key === 'Escape') {
                                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                                    closeDeleteModal();
                                }
                            }
                        });

                        <?php if (isset($toast_message)): ?>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof showToast === 'function') {
                                showToast('<?php echo addslashes($toast_message); ?>', 'success');
                            }
                            const url = new URL(window.location);
                            url.searchParams.delete('success');
                            url.searchParams.delete('name');
                            window.history.replaceState({}, document.title, url.pathname + url.search);
                        });
                        <?php endif; ?>
                    </script>
                <?php endif; ?>

                <?php if ($page === 'add'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <div class="mb-8 mt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div>
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Add New Checklist Item</h1>
                                        <p class="text-lg text-gray-600 mt-2">Create a new document requirement for student enrollment</p>
                                    </div>
                                </div>
                            </div>

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

                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-blue-100 rounded-xl p-2">
                                            <i class="fas fa-plus text-blue-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Document Information</h3>
                                    </div>
                                </div>

                                <div class="p-8">
                                    <form method="POST" action="admin-manage-checklist.php?page=add" class="space-y-8">
                                        <div>
                                            <label for="document_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Document Name <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-file-alt text-gray-400"></i>
                                                </div>
                                                <input type="text" name="document_name" id="document_name" required
                                                       value="<?php echo isset($_POST['document_name']) ? htmlspecialchars((string)$_POST['document_name']) : ''; ?>"
                                                       class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                       placeholder="e.g., Birth Certificate (PSA)">
                                            </div>
                                            <p class="mt-2 text-sm text-gray-500">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Enter the name of the document required for student enrollment
                                            </p>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                            <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-save mr-3"></i>
                                                Create Checklist Item
                                            </button>
                                            <a href="admin-manage-checklist.php?page=index" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                <i class="fas fa-times mr-3"></i>
                                                Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'edit'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <div class="mb-8 mt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div>
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Edit Checklist Item</h1>
                                        <p class="text-lg text-gray-600 mt-2">
                                            Editing: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($checklist_item['document_name'] ?? ''); ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>

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

                            <?php if ($checklist_item): ?>
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-blue-100 rounded-xl p-2">
                                                <i class="fas fa-edit text-blue-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Document Information</h3>
                                        </div>
                                    </div>
                                    <div class="p-8">
                                        <form method="POST" action="admin-manage-checklist.php?page=edit&id=<?php echo (int)$item_id; ?>" class="space-y-8">
                                            <div>
                                                <label for="document_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                                    Document Name <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                        <i class="fas fa-file-alt text-gray-400"></i>
                                                    </div>
                                                    <input type="text" name="document_name" id="document_name" required
                                                           value="<?php echo htmlspecialchars($checklist_item['document_name'] ?? ''); ?>"
                                                           class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                           placeholder="e.g., Birth Certificate (PSA)">
                                                </div>
                                                <p class="mt-2 text-sm text-gray-500">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Enter the name of the document required for student enrollment
                                                </p>
                                            </div>
                                            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                                <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                    <i class="fas fa-save mr-3"></i>
                                                    Update Checklist Item
                                                </button>
                                                <a href="admin-manage-checklist.php?page=index" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                    <i class="fas fa-times mr-3"></i>
                                                    Cancel
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>

