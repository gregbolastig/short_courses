<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Admin Login - JZGMSAT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php
    session_start();
    
    // Initialize variables
    $error = '';
    $success = '';
    
    // Generate CSRF token if not exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Check if user is already logged in (check for user_id which is set by auth)
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ../admin/dashboard.php');
        exit();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid security token. Please try again.';
        } else {
            // Get and sanitize input
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $remember = isset($_POST['remember']) ? true : false;
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } 
            // Check credentials
            elseif ($email === 'admin@admin.com' && $password === 'admin123') {
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Set session variables (compatible with auth middleware)
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['user_id'] = 1; // Admin user ID
                $_SESSION['role'] = 'admin';
                $_SESSION['username'] = 'Admin';
                $_SESSION['admin_email'] = $email;
                $_SESSION['email'] = $email;
                $_SESSION['login_time'] = time();
                $_SESSION['login_success'] = true; // Flag for toast notification
                
                // Handle remember me
                if ($remember) {
                    // Set cookie for 30 days
                    setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                } else {
                    // Clear remember me cookie
                    if (isset($_COOKIE['remember_email'])) {
                        setcookie('remember_email', '', time() - 3600, '/', '', false, true);
                    }
                }
                
                // Redirect to dashboard
                header('Location: ../admin/dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
    ?>
    
    <div class="flex flex-col lg:flex-row min-h-screen">
        <!-- Left Section - Image (60% width on large screens for better balance) -->
        <div class="lg:w-3/5 lg:h-screen h-72 relative order-1 lg:order-1">
            <!-- School Image -->
            <img 
                src="../assets/images/school.jpg" 
                alt="JZGMSAT Campus"
                class="w-full h-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <!-- Fallback gradient if image fails to load -->
            <div class="absolute inset-0 bg-gradient-to-br from-blue-900 to-blue-700" style="display: none;"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-blue-900/70 via-blue-800/50 to-transparent flex items-center justify-center lg:justify-start lg:pl-16">
                <div class="text-white text-center lg:text-left p-6 max-w-xl">
                    <h1 class="text-3xl lg:text-4xl font-bold tracking-wide mb-3">Jacobo Z. Gonzales Memorial School of Arts and Trades</h1>
                    <h2 class="text-2xl lg:text-3xl font-semibold opacity-95">Student Management System</h2>
                </div>
            </div>
        </div>
        
        <!-- Right Section - Login Form (40% width on large screens for better balance) -->
        <div class="lg:w-2/5 flex items-center justify-center p-6 lg:p-12 order-2 lg:order-2 bg-white shadow-2xl">
            <div class="w-full max-w-md">
                <!-- Login Form Container -->
                <div class="p-6 lg:p-8">
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Admin Login</h2>
                        <p class="text-gray-600">Enter your credentials to access the dashboard</p>
                    </div>
                    
                    <!-- Error/Success Messages -->
                    <?php if (isset($error) && $error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r animate-pulse">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success) && $success): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Login Form with POST method -->
                    <form method="POST" action="" class="space-y-6" id="loginForm">
                        <!-- CSRF Token Field (Server-side implementation required) -->
                        <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
                        
                        <!-- Email Field with anti-copy-paste protection -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email Address
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required
                                    class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                                    placeholder="admin@jzgmsat.edu"
                                    oncopy="return false" 
                                    onpaste="return false"
                                    oncut="return false"
                                    oncontextmenu="return false"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : ''); ?>"
                                    autocomplete="email"
                                >
                            </div>
                            <!-- Server-side validation required here -->
                        </div>
                        
                        <!-- Password Field with anti-copy-paste protection -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    Password
                                </label>
                                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 transition">
                                    Forgot password?
                                </a>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required
                                    class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                                    placeholder="••••••••"
                                    oncopy="return false" 
                                    onpaste="return false"
                                    oncut="return false"
                                    oncontextmenu="return false"
                                    autocomplete="current-password"
                                >
                                <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="passwordToggle" class="fas fa-eye text-gray-400 hover:text-gray-600 transition"></i>
                                </button>
                            </div>
                            <!-- Server-side password validation required here -->
                        </div>
                        
                        <!-- Remember Me Checkbox -->
                        <div class="flex items-center">
                            <input 
                                id="remember" 
                                name="remember" 
                                type="checkbox"
                                value="1"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                <?php echo (isset($_COOKIE['remember_email']) && $_COOKIE['remember_email']) ? 'checked' : ''; ?>
                            >
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        
                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                        >
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign In
                        </button>
                    </form>
                    

                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
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
        
        // Disable right-click on form inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                return false;
            });
            
            // Prevent drag and drop
            input.addEventListener('dragstart', (e) => e.preventDefault());
            input.addEventListener('drop', (e) => e.preventDefault());
        });
        
        // Form submission handler
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Authenticating...';
            submitBtn.disabled = true;
            submitBtn.classList.remove('hover:bg-blue-800', 'hover:shadow-lg', 'transform', 'hover:-translate-y-0.5');
            
            // Client-side validation (additional to HTML5 validation)
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            // Basic email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                // Server-side validation is primary, this is secondary
                console.warn('Client-side email validation failed');
            }
            
            // Password length validation
            if (password.length < 8) {
                console.warn('Password should be at least 8 characters');
            }
            
            // Note: Server-side validation is CRITICAL for security
            // All client-side validation should be duplicated server-side
            
            // If validation passes, form submits to server
            // Server-side must implement:
            // 1. CSRF token verification
            // 2. Input sanitization (htmlspecialchars, trim, etc.)
            // 3. SQL injection prevention (prepared statements)
            // 4. XSS prevention (output encoding)
            // 5. Password hashing (password_hash())
            // 6. Rate limiting
            // 7. Secure session management
        });
        
        // Auto-focus email field on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
        
        // Responsive adjustments
        window.addEventListener('resize', function() {
            // Additional responsive behaviors can be added here
        });
    </script>
    
    <!-- Security Comments for Server-side Implementation -->
    <!-- 
    SERVER-SIDE SECURITY REQUIREMENTS:
    
    1. CSRF PROTECTION:
       - Generate unique token per session
       - Validate token on form submission
       - Reject requests without valid token
    
    2. INPUT VALIDATION & SANITIZATION:
       - Trim all inputs: trim($_POST['email'])
       - Validate email format: filter_var($email, FILTER_VALIDATE_EMAIL)
       - Sanitize: htmlspecialchars($input, ENT_QUOTES, 'UTF-8')
       - Use prepared statements for database queries
    
    3. SQL INJECTION PREVENTION:
       - Use PDO or MySQLi with prepared statements
       - NEVER concatenate user input into SQL queries
       - Example: $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                   $stmt->execute([$email]);
    
    4. XSS PREVENTION:
       - Always escape output: htmlspecialchars($data, ENT_QUOTES, 'UTF-8')
       - Use Content Security Policy headers
    
    5. PASSWORD SECURITY:
       - Verify with password_verify($password, $hashedPassword)
       - Store hashed passwords only: password_hash($password, PASSWORD_DEFAULT)
    
    6. SESSION SECURITY:
       - session_regenerate_id(true) after login
       - Set secure session cookies: session_set_cookie_params(['secure' => true, 'httponly' => true])
    
    7. ADDITIONAL SECURITY:
       - Implement rate limiting (max 5 attempts per 15 minutes)
       - Log all login attempts (success/failure)
       - Use HTTPS only
       - Set secure headers (X-Frame-Options, X-Content-Type-Options, etc.)
    -->
</body>
</html>