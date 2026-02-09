<?php
session_start();
require_once '../config/database.php';
require_once '../includes/system_activity_logger.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE username = :username OR email = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Log successful login
                $logger = new SystemActivityLogger($conn);
                $logger->logAdminLogin($user['id'], $user['username']);
                
                header('Location: ../admin/dashboard.php');
                exit;
            } else {
                // Log failed login attempt
                $logger = new SystemActivityLogger($conn);
                $logger->log(
                    'login_failed',
                    "Failed login attempt for username: {$username}",
                    'system',
                    null
                );
                
                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Jacobo Z. Gonzales Memorial School of Arts and Trades</title>
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
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .login-container {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
    </style>
</head>
<body class="login-container">
    <div class="relative min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <!-- Background Elements -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-white opacity-5 rounded-full"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-white opacity-5 rounded-full"></div>
        </div>
        
        <div class="relative max-w-md w-full space-y-8 animate-fade-in">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm border border-white border-opacity-30">
                    <i class="fas fa-shield-alt text-3xl text-white"></i>
                </div>
                <h1 class="mt-6 text-4xl font-bold text-white">Admin Portal</h1>
                <p class="mt-2 text-lg text-white text-opacity-90">Jacobo Z. Gonzales Memorial School of Arts and Trades Student Registration System</p>
                <p class="mt-1 text-sm text-white text-opacity-70">Secure administrative access only</p>
            </div>
            
            <!-- Login Form -->
            <div class="bg-white bg-opacity-95 backdrop-blur-sm rounded-2xl shadow-2xl p-8 border border-white border-opacity-20 animate-slide-up">
                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-slide-up">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-primary-500 mr-2"></i>Username or Email
                        </label>
                        <input type="text" id="username" name="username" required 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                               placeholder="Enter your username or email"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-lock text-primary-500 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300 pr-12"
                                   placeholder="Enter your password">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i id="passwordToggle" class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-gradient-to-r from-primary-500 to-primary-700 hover:from-primary-600 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200 transform hover:-translate-y-0.5">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign In to Admin Portal
                        </button>
                    </div>
                </form>
                
                <!-- Back to Student Portal -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-4">
                            <i class="fas fa-info-circle mr-1"></i>
                            Not an administrator?
                        </p>
                        <a href="../index.php" 
                           class="inline-flex items-center px-4 py-2 border border-primary-500 rounded-lg text-sm font-medium text-primary-600 bg-primary-50 hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Student Portal
                        </a>
                    </div>
                </div>
                
                <!-- Demo Credentials -->
                <div class="mt-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200">
                    <div class="text-center">
                        <p class="text-xs font-semibold text-gray-700 mb-2">
                            <i class="fas fa-key mr-1"></i>Demo Credentials
                        </p>
                        <div class="space-y-1">
                            <p class="text-xs text-gray-600">
                                <span class="font-medium">Username:</span> 
                                <code class="bg-white px-2 py-1 rounded text-primary-600 font-mono">admin</code>
                            </p>
                            <p class="text-xs text-gray-600">
                                <span class="font-medium">Password:</span> 
                                <code class="bg-white px-2 py-1 rounded text-primary-600 font-mono">admin123</code>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-center">
                <p class="text-sm text-white text-opacity-70">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Secure Admin Access • Jacobo Z. Gonzales Memorial School of Arts and Trades Student Registration System
                </p>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Add loading state to submit button
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>