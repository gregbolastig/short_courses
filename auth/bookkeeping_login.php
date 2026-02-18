<?php
session_start();

// Initialize variables
$error = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'bookkeeping') {
    header('Location: ../bookkeeping/dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif ($email === 'bookkeeping@jzgmsat.com' && $password === 'bookkeeping123') {
            session_regenerate_id(true);
            
            $_SESSION['bookkeeping_logged_in'] = true;
            $_SESSION['user_id'] = 2;
            $_SESSION['role'] = 'bookkeeping';
            $_SESSION['username'] = 'Bookkeeping';
            $_SESSION['bookkeeping_email'] = $email;
            $_SESSION['email'] = $email;
            $_SESSION['login_time'] = time();
            $_SESSION['login_success'] = true;
            
            if ($remember) {
                setcookie('remember_bookkeeping_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            } else {
                if (isset($_COOKIE['remember_bookkeeping_email'])) {
                    setcookie('remember_bookkeeping_email', '', time() - 3600, '/', '', false, true);
                }
            }
            
            header('Location: ../bookkeeping/dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookkeeping Login - JZGMSAT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex flex-col lg:flex-row min-h-screen">
        <!-- Left Section - Image -->
        <div class="lg:w-3/5 lg:h-screen h-72 relative order-1 lg:order-1">
            <img src="../assets/images/school.jpg" alt="JZGMSAT Campus" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="absolute inset-0 bg-gradient-to-br from-red-900 to-red-700" style="display: none;"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-red-900/70 via-red-800/50 to-transparent flex items-center justify-center lg:justify-start lg:pl-16">
                <div class="text-white text-center lg:text-left p-6 max-w-xl">
                    <h1 class="text-3xl lg:text-4xl font-bold tracking-wide mb-3">Jacobo Z. Gonzales Memorial School of Arts and Trades</h1>
                    <h2 class="text-2xl lg:text-3xl font-semibold opacity-95">Financial Management System</h2>
                </div>
            </div>
        </div>
        
        <!-- Right Section - Bookkeeping Login Form -->
        <div class="lg:w-2/5 flex items-center justify-center p-6 lg:p-12 order-2 lg:order-2 bg-white shadow-2xl">
            <div class="w-full max-w-md">
                <div class="p-6 lg:p-8">
                    <div class="mb-6">
                        <a href="login.php" class="text-red-700 hover:text-red-900 text-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to role selection
                        </a>
                    </div>
                    
                    <div class="mb-8">
                        <div class="flex items-center justify-center mb-4">
                            <div class="bg-red-100 p-4 rounded-full">
                                <i class="fas fa-calculator text-red-800 text-3xl"></i>
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">Bookkeeping Login</h2>
                        <p class="text-gray-600 text-center">Enter your credentials to access financial management</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r animate-pulse">
                            <div class="flex">
                                <i class="fas fa-exclamation-circle text-red-400 mt-1"></i>
                                <p class="ml-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" id="email" name="email" required
                                       class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       placeholder="bookkeeping@jzgmsat.com"
                                       value="<?php echo isset($_COOKIE['remember_bookkeeping_email']) ? htmlspecialchars($_COOKIE['remember_bookkeeping_email']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="password" name="password" required
                                       class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="toggleIcon" class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox" value="1"
                                   <?php echo isset($_COOKIE['remember_bookkeeping_email']) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-red-700 focus:ring-red-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                        </div>
                        
                        <button type="submit" class="w-full bg-red-900 hover:bg-red-800 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 shadow-md hover:shadow-lg">
                            <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                        </button>
                    </form>
                    
                    <div class="mt-6 p-4 bg-red-50 rounded-lg border border-red-200">
                        <p class="text-xs text-red-900 text-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Demo credentials: bookkeeping@jzgmsat.com / bookkeeping123
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const field = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
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
    </script>
</body>
</html>
