<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

$page_title = 'Account Settings';
$breadcrumb_items = [
    ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'admin-dashboard.php'],
    ['title' => 'Account Settings', 'icon' => 'fas fa-user-cog']
];

$error_message = null;
$success_message = null;

// Get current theme preference
$current_theme = $_SESSION['theme_preference'] ?? 'light';

// Get current admin data
try {
    $stmt = $conn->prepare("SELECT * FROM shortcourse_users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        header('Location: ../auth/logout.php');
        exit;
    }
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        try {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($username) || empty($email)) {
                throw new Exception('Username and email are required.');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            
            // Check if username/email already exists for other users
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
            
            $logger->log(
                'profile_updated',
                "Admin updated profile information",
                'admin',
                $_SESSION['user_id']
            );
            
            $success_message = 'Profile updated successfully!';
            
            // Refresh admin data
            $stmt = $conn->prepare("SELECT * FROM shortcourse_users WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'change_password') {
        try {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required.');
            }
            
            if (!password_verify($current_password, $admin['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Strong password validation
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            if (!preg_match('/[a-z]/', $new_password)) {
                throw new Exception('Password must contain at least one lowercase letter.');
            }
            
            if (!preg_match('/[A-Z]/', $new_password)) {
                throw new Exception('Password must contain at least one uppercase letter.');
            }
            
            if (!preg_match('/[0-9]/', $new_password)) {
                throw new Exception('Password must contain at least one number.');
            }
            
            if (!preg_match('/[^a-zA-Z0-9]/', $new_password)) {
                throw new Exception('Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?).');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE shortcourse_users SET password = :password WHERE id = :id");
            $stmt->execute([
                ':password' => $hashed_password,
                ':id' => $_SESSION['user_id']
            ]);
            
            $logger->log(
                'password_changed',
                "Admin changed password",
                'admin',
                $_SESSION['user_id']
            );
            
            $success_message = 'Password changed successfully!';
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $current_theme === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php include 'components/dark-mode-config.php'; ?>
    <?php include 'components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
    
    <?php include 'components/sidebar.php'; ?>
    
    <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
        <?php include 'components/header.php'; ?>
        
        <main class="p-4 md:p-6">
            <div class="max-w-6xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900">Account Settings</h1>
                    <p class="text-gray-600 mt-2">Manage your profile information and security settings</p>
                </div>
                
                <?php if ($error_message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo addslashes($error_message); ?>', 'error');
                    });
                </script>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo addslashes($success_message); ?>', 'success');
                    });
                </script>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - Profile Summary -->
                    <div class="lg:col-span-1">
                        <!-- Profile Card -->
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
                            <div class="text-center">
                                <div class="relative inline-block mb-4">
                                    <div class="w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-3xl font-bold shadow-lg">
                                        <?php echo strtoupper(substr($admin['username'], 0, 2)); ?>
                                    </div>
                                    <div class="absolute bottom-0 right-0 w-6 h-6 bg-green-500 border-4 border-white rounded-full"></div>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($admin['username']); ?></h3>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($admin['email']); ?></p>
                                <div class="mt-4 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-shield-alt mr-1"></i>
                                    Administrator
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Info -->
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                Account Information
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">User ID</span>
                                    <span class="text-sm font-semibold text-gray-900">#<?php echo $admin['id']; ?></span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Role</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo ucfirst($admin['role']); ?></span>
                                </div>
                                <div class="flex items-center justify-between py-2">
                                    <span class="text-sm text-gray-600">Member Since</span>
                                    <span class="text-sm font-semibold text-gray-900"><?php echo date('M Y', strtotime($admin['created_at'] ?? 'now')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Forms -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Profile Information -->
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 rounded-xl p-3 mr-4">
                                        <i class="fas fa-user-edit text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-2xl font-bold text-gray-900">Profile Information</h2>
                                        <p class="text-sm text-gray-600">Update your account details and email address</p>
                                    </div>
                                </div>
                                <button type="button" id="editToggle" onclick="toggleEditMode()" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-all duration-200">
                                    <i class="fas fa-edit mr-2"></i>
                                    <span id="editButtonText">Edit</span>
                                </button>
                            </div>
                            
                            <form method="POST" class="space-y-6" id="profileForm">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div>
                                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-user text-gray-400 mr-2"></i>Username
                                    </label>
                                    <input type="text" id="username" name="username" required readonly
                                           value="<?php echo htmlspecialchars($admin['username']); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 cursor-not-allowed"
                                           placeholder="Enter your username">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-envelope text-gray-400 mr-2"></i>Email Address
                                    </label>
                                    <input type="email" id="email" name="email" required readonly
                                           value="<?php echo htmlspecialchars($admin['email']); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-gray-50 cursor-not-allowed"
                                           placeholder="Enter your email">
                                </div>
                                
                                <div class="flex justify-between items-center pt-4">
                                    <button type="button" id="cancelEdit" onclick="cancelEdit()" class="hidden inline-flex items-center px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition-all duration-200">
                                        <i class="fas fa-times mr-2"></i>Cancel
                                    </button>
                                    <button type="submit" id="saveButton" disabled class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105 shadow-lg opacity-50 cursor-not-allowed ml-auto">
                                        <i class="fas fa-save mr-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                            <div class="flex items-center mb-6">
                                <div class="bg-purple-100 rounded-xl p-3 mr-4">
                                    <i class="fas fa-lock text-purple-600 text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-900">Change Password</h2>
                                    <p class="text-sm text-gray-600">Update your password to keep your account secure</p>
                                </div>
                            </div>
                            
                            <form method="POST" class="space-y-6" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div>
                                    <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-key text-gray-400 mr-2"></i>Current Password
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="current_password" name="current_password" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                                               placeholder="Enter current password">
                                        <button type="button" onclick="togglePassword('current_password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye" id="current_password_icon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-lock text-gray-400 mr-2"></i>New Password
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="new_password" name="new_password" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                                               placeholder="Enter new password"
                                               oninput="checkPasswordStrength(this.value); validatePasswordRequirements(this.value)">
                                        <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye" id="new_password_icon"></i>
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs text-gray-600">Password Strength:</span>
                                            <span class="text-xs font-semibold" id="strength-text">-</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div id="strength-bar" class="h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <p class="text-xs font-semibold text-gray-700 mb-2">Password must contain:</p>
                                        <ul class="space-y-1 text-xs">
                                            <li id="req-length" class="flex items-center text-gray-500">
                                                <i class="fas fa-circle text-gray-300 mr-2 text-xs"></i>
                                                At least 8 characters
                                            </li>
                                            <li id="req-lowercase" class="flex items-center text-gray-500">
                                                <i class="fas fa-circle text-gray-300 mr-2 text-xs"></i>
                                                One lowercase letter (a-z)
                                            </li>
                                            <li id="req-uppercase" class="flex items-center text-gray-500">
                                                <i class="fas fa-circle text-gray-300 mr-2 text-xs"></i>
                                                One uppercase letter (A-Z)
                                            </li>
                                            <li id="req-number" class="flex items-center text-gray-500">
                                                <i class="fas fa-circle text-gray-300 mr-2 text-xs"></i>
                                                One number (0-9)
                                            </li>
                                            <li id="req-special" class="flex items-center text-gray-500">
                                                <i class="fas fa-circle text-gray-300 mr-2 text-xs"></i>
                                                One special character (!@#$%^&*)
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-check-circle text-gray-400 mr-2"></i>Confirm New Password
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="confirm_password" name="confirm_password" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                                               placeholder="Confirm new password">
                                        <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                            <i class="fas fa-eye" id="confirm_password_icon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end pt-4">
                                    <button type="submit" class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white font-semibold rounded-lg hover:from-purple-700 hover:to-purple-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200 transform hover:scale-105 shadow-lg">
                                        <i class="fas fa-key mr-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Edit mode toggle
        let isEditMode = false;
        const originalValues = {
            username: document.getElementById('username').value,
            email: document.getElementById('email').value
        };
        
        function toggleEditMode() {
            isEditMode = !isEditMode;
            const usernameField = document.getElementById('username');
            const emailField = document.getElementById('email');
            const saveButton = document.getElementById('saveButton');
            const cancelButton = document.getElementById('cancelEdit');
            const editButton = document.getElementById('editToggle');
            const editButtonText = document.getElementById('editButtonText');
            
            if (isEditMode) {
                // Enable editing
                usernameField.removeAttribute('readonly');
                emailField.removeAttribute('readonly');
                usernameField.classList.remove('bg-gray-50', 'cursor-not-allowed');
                emailField.classList.remove('bg-gray-50', 'cursor-not-allowed');
                usernameField.classList.add('bg-white');
                emailField.classList.add('bg-white');
                saveButton.removeAttribute('disabled');
                saveButton.classList.remove('opacity-50', 'cursor-not-allowed');
                cancelButton.classList.remove('hidden');
                editButton.classList.add('bg-blue-600', 'text-white');
                editButton.classList.remove('bg-gray-100', 'text-gray-700');
                editButtonText.textContent = 'Editing...';
                usernameField.focus();
            } else {
                // Disable editing
                usernameField.setAttribute('readonly', 'readonly');
                emailField.setAttribute('readonly', 'readonly');
                usernameField.classList.add('bg-gray-50', 'cursor-not-allowed');
                emailField.classList.add('bg-gray-50', 'cursor-not-allowed');
                usernameField.classList.remove('bg-white');
                emailField.classList.remove('bg-white');
                saveButton.setAttribute('disabled', 'disabled');
                saveButton.classList.add('opacity-50', 'cursor-not-allowed');
                cancelButton.classList.add('hidden');
                editButton.classList.remove('bg-blue-600', 'text-white');
                editButton.classList.add('bg-gray-100', 'text-gray-700');
                editButtonText.textContent = 'Edit';
            }
        }
        
        function cancelEdit() {
            // Restore original values
            document.getElementById('username').value = originalValues.username;
            document.getElementById('email').value = originalValues.email;
            toggleEditMode();
        }
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Weak';
                    color = 'bg-red-500';
                    strengthBar.style.width = '20%';
                    break;
                case 2:
                    text = 'Fair';
                    color = 'bg-orange-500';
                    strengthBar.style.width = '40%';
                    break;
                case 3:
                    text = 'Good';
                    color = 'bg-yellow-500';
                    strengthBar.style.width = '60%';
                    break;
                case 4:
                    text = 'Strong';
                    color = 'bg-blue-500';
                    strengthBar.style.width = '80%';
                    break;
                case 5:
                    text = 'Very Strong';
                    color = 'bg-green-500';
                    strengthBar.style.width = '100%';
                    break;
            }
            
            strengthBar.className = 'h-2 rounded-full transition-all duration-300 ' + color;
            strengthText.textContent = text;
            strengthText.className = 'text-xs font-semibold ' + color.replace('bg-', 'text-');
        }
        
        // Validate password requirements
        function validatePasswordRequirements(password) {
            const requirements = {
                length: password.length >= 8,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /\d/.test(password),
                special: /[^a-zA-Z\d]/.test(password)
            };
            
            // Update UI for each requirement
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById('req-' + req);
                const icon = element.querySelector('i');
                
                if (requirements[req]) {
                    element.classList.remove('text-gray-500');
                    element.classList.add('text-green-600');
                    icon.classList.remove('fa-circle', 'text-gray-300');
                    icon.classList.add('fa-check-circle', 'text-green-500');
                } else {
                    element.classList.remove('text-green-600');
                    element.classList.add('text-gray-500');
                    icon.classList.remove('fa-check-circle', 'text-green-500');
                    icon.classList.add('fa-circle', 'text-gray-300');
                }
            });
        }
    </script>
    
    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>
