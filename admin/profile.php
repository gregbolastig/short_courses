<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

$success_message = '';
$error_message = '';

// Set breadcrumb items for header
$breadcrumb_items = [
    ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'admin-dashboard.php'],
    ['title' => 'Account Settings', 'icon' => 'fas fa-user-cog']
];

// Get admin info
$admin = null;
try {
    $stmt = $conn->prepare("SELECT * FROM shortcourse_users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        try {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            
            if (empty($username) || empty($email)) {
                throw new Exception('Username and email are required.');
            }
            
            // Check if username/email already exists for another user
            $stmt = $conn->prepare("SELECT id FROM shortcourse_users WHERE (username = :username OR email = :email) AND id != :id");
            $stmt->execute([':username' => $username, ':email' => $email, ':id' => $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception('Username or email already exists.');
            }
            
            $stmt = $conn->prepare("UPDATE shortcourse_users SET username = :username, email = :email WHERE id = :id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':id' => $_SESSION['user_id']
            ]);
            
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            
            $logger->log('profile_updated', "Admin updated profile information", 'admin', $_SESSION['user_id']);
            $success_message = 'Profile updated successfully!';
            
            // Refresh admin data
            $stmt = $conn->prepare("SELECT * FROM shortcourse_users WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'change_password') {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }
            
            // Verify current password
            if (!password_verify($current_password, $admin['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE shortcourse_users SET password = :password WHERE id = :id");
            $stmt->execute([':password' => $hashed_password, ':id' => $_SESSION['user_id']]);
            
            $logger->log('password_changed', "Admin changed password", 'admin', $_SESSION['user_id']);
            $success_message = 'Password changed successfully!';
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Get pending approvals count for sidebar
$pending_approvals = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM shortcourse_students WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_approvals = $result['count'];
} catch (PDOException $e) {
    $pending_approvals = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php include 'components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
    
    <?php include 'components/sidebar.php'; ?>
    
    <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
        <?php include 'components/header.php'; ?>
        
        <main class="p-4 md:p-6">
            <div class="max-w-4xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Account Settings</h1>
                    <p class="text-lg text-gray-600 mt-2">Manage your profile and security settings</p>
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
                
                <!-- Profile Information Card -->
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 rounded-xl p-3">
                            <i class="fas fa-user text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-2xl font-bold text-gray-900">Profile Information</h2>
                            <p class="text-sm text-gray-600">Update your account details</p>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-2 text-gray-400"></i>Username
                            </label>
                            <input type="text" id="username" name="username" required
                                   value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>Email Address
                            </label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Card -->
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-purple-100 rounded-xl p-3">
                            <i class="fas fa-lock text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-2xl font-bold text-gray-900">Change Password</h2>
                            <p class="text-sm text-gray-600">Update your password to keep your account secure</p>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-key mr-2 text-gray-400"></i>Current Password
                            </label>
                            <input type="password" id="current_password" name="current_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-2 text-gray-400"></i>New Password
                            </label>
                            <input type="password" id="new_password" name="new_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                            <p class="mt-1 text-xs text-gray-500">Password must be at least 6 characters long</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-2 text-gray-400"></i>Confirm New Password
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" 
                                    class="inline-flex items-center px-6 py-3 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                <i class="fas fa-shield-alt mr-2"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'components/admin-scripts.php'; ?>
    
    <?php if ($success_message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($success_message); ?>', 'success');
        });
    </script>
    <?php endif; ?>
</body>
</html>
