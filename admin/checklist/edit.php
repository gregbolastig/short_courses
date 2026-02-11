<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

requireAdmin();

$page_title = 'Edit Checklist Item';

// Get checklist item
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM checklist WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $_SESSION['error_message'] = 'Checklist item not found.';
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $document_name = trim($_POST['document_name']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $display_order = intval($_POST['display_order']);
        
        $stmt = $conn->prepare("UPDATE checklist SET document_name = :document_name, is_required = :is_required, display_order = :display_order WHERE id = :id");
        $stmt->bindParam(':document_name', $document_name);
        $stmt->bindParam(':is_required', $is_required);
        $stmt->bindParam(':display_order', $display_order);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Checklist item updated successfully.';
            header('Location: index.php');
            exit();
        } else {
            $error_message = 'Failed to update checklist item.';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}
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
            <div class="max-w-3xl mx-auto">
                <!-- Breadcrumb -->
                <nav class="mb-4 text-sm">
                    <ol class="flex items-center space-x-2 text-gray-600">
                        <li><a href="../dashboard.php" class="hover:text-blue-600"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li><a href="index.php" class="hover:text-blue-600">Manage Checklist</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-gray-900 font-medium">Edit Document</li>
                    </ol>
                </nav>

                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center mb-4">
                        <a href="index.php" class="text-blue-600 hover:text-blue-800 mr-4">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <i class="fas fa-edit text-blue-900 mr-3"></i>
                            Edit Document Requirement
                        </h1>
                    </div>
                    <p class="text-gray-600">Update document requirement information</p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-red-400 mt-1"></i>
                            <p class="ml-3 text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                    <form method="POST" action="" class="p-6 space-y-6">
                        <!-- Document Name -->
                        <div>
                            <label for="document_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Document Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="document_name" name="document_name" required
                                   value="<?php echo htmlspecialchars($item['document_name']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Display Order -->
                        <div>
                            <label for="display_order" class="block text-sm font-medium text-gray-700 mb-2">
                                Display Order
                            </label>
                            <input type="number" id="display_order" name="display_order" min="0"
                                   value="<?php echo htmlspecialchars($item['display_order'] ?? 0); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Lower numbers appear first</p>
                        </div>

                        <!-- Required Checkbox -->
                        <div class="flex items-center">
                            <input type="checkbox" id="is_required" name="is_required" value="1"
                                   <?php echo $item['is_required'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_required" class="ml-2 block text-sm text-gray-700">
                                Required Document
                            </label>
                        </div>

                        <!-- Buttons -->
                        <div class="flex items-center justify-end space-x-4 pt-4 border-t">
                            <a href="index.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-900 hover:bg-blue-800 text-white font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-save mr-2"></i>
                                Update Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
