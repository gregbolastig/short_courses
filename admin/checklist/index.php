<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

requireAdmin();

$page_title = 'Manage Checklist';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("DELETE FROM checklist WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Checklist item deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to delete checklist item.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    }
    
    header('Location: index.php');
    exit();
}

// Get all checklist items
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->query("SELECT * FROM checklist ORDER BY display_order ASC, created_at DESC");
    $checklist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $checklist_items = [];
}

// Check for session messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? $error_message ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - JZGMSAT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../components/sidebar.php'; ?>
    
    <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
        <?php include '../components/header.php'; ?>
        
        <main class="p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Breadcrumb -->
                <nav class="mb-4 text-sm">
                    <ol class="flex items-center space-x-2 text-gray-600">
                        <li><a href="../dashboard.php" class="hover:text-blue-600"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-gray-900 font-medium">Manage Checklist</li>
                    </ol>
                </nav>

                <!-- Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-tasks text-blue-900 mr-3"></i>
                        Document Requirements Checklist
                    </h1>
                    <p class="text-gray-600 mt-2">Manage required documents for student enrollment</p>
                </div>

                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-400 mt-1"></i>
                            <p class="ml-3 text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-red-400 mt-1"></i>
                            <p class="ml-3 text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Add New Button -->
                <div class="mb-6">
                    <a href="add.php" class="inline-flex items-center px-4 py-2 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-lg transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>
                        Add New Checklist Item
                    </a>
                </div>

                <!-- Checklist Table -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($checklist_items)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center">
                                            <i class="fas fa-clipboard-list text-gray-300 text-5xl mb-4"></i>
                                            <p class="text-gray-500 text-lg font-medium">No checklist items found</p>
                                            <p class="text-gray-400 text-sm mt-2">Get started by adding your first checklist item</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($checklist_items as $item): ?>
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['display_order'] ?? 0); ?></span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['document_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($item['is_required']): ?>
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        <i class="fas fa-exclamation-circle mr-1"></i> Required
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        Optional
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['document_name'])); ?>')" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                window.location.href = `index.php?action=delete&id=${id}`;
            }
        }
    </script>

    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
