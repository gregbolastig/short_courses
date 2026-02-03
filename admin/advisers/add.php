<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Add Adviser';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Manage Advisers', 'icon' => 'fas fa-chalkboard-teacher', 'url' => 'index.php'],
    ['title' => 'Add Adviser', 'icon' => 'fas fa-plus']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        // Combine first name, middle initial, and last name
        $full_name = trim($_POST['first_name'] . ' ' . $_POST['middle_initial'] . ' ' . $_POST['last_name']);
        
        $stmt = $conn->prepare("INSERT INTO advisers (adviser_name) VALUES (?)");
        
        $stmt->execute([
            $full_name
        ]);
        
        $success_message = 'Adviser created successfully!';
        
        // Redirect to advisers list with success parameter
        header("Location: index.php?success=created&name=" . urlencode($full_name));
        
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
    <title>Add Adviser - Student Registration System</title>
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
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Add New Adviser</h1>
                                    <p class="text-lg text-gray-600 mt-2">Create a new adviser profile for your educational program</p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <a href="index.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Advisers
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
                                        <p class="text-xs text-green-600 mt-1">Redirecting to advisers list...</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Adviser Form -->
                        <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                <div class="flex items-center space-x-3">
                                    <div class="bg-blue-100 rounded-xl p-2">
                                        <i class="fas fa-plus text-blue-600"></i>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900">Adviser Information</h3>
                                </div>
                            </div>
                            
                            <div class="p-8">
                                <form method="POST" action="" class="space-y-8">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label for="first_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                                First Name <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-user text-gray-400"></i>
                                                </div>
                                                <input type="text" name="first_name" id="first_name" required 
                                                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                                                       class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                       placeholder="Juan">
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label for="middle_initial" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Middle Initial
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-user-tag text-gray-400"></i>
                                                </div>
                                                <input type="text" name="middle_initial" id="middle_initial" maxlength="2"
                                                       value="<?php echo isset($_POST['middle_initial']) ? htmlspecialchars($_POST['middle_initial']) : ''; ?>"
                                                       class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                       placeholder="D.">
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label for="last_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Last Name <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-user-tie text-gray-400"></i>
                                                </div>
                                                <input type="text" name="last_name" id="last_name" required 
                                                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                                                       class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                       placeholder="Cruz">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                        <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-save mr-3"></i>
                                            Create Adviser
                                        </button>
                                        
                                        <a href="index.php" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-3"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>