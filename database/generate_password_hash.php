<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - JZGMSAT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    <i class="fas fa-key text-blue-600 mr-3"></i>
                    Password Hash Generator
                </h1>
                <p class="text-gray-600">Generate secure password hashes for admin users</p>
            </div>

            <?php
            $hash = '';
            $password = '';
            $error = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                $password = $_POST['password'];
                
                if (empty($password)) {
                    $error = 'Please enter a password';
                } elseif (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters long';
                } elseif (!preg_match('/[a-z]/', $password)) {
                    $error = 'Password must contain at least one lowercase letter';
                } elseif (!preg_match('/[A-Z]/', $password)) {
                    $error = 'Password must contain at least one uppercase letter';
                } elseif (!preg_match('/[0-9]/', $password)) {
                    $error = 'Password must contain at least one number';
                } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) {
                    $error = 'Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                }
            }
            ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle text-red-400 mt-1"></i>
                        <p class="ml-3 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hash): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r">
                    <div class="flex">
                        <i class="fas fa-check-circle text-green-400 mt-1"></i>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-green-800 mb-2">Password hash generated successfully!</p>
                            <div class="bg-white p-3 rounded border border-green-200 mt-2">
                                <p class="text-xs text-gray-600 mb-1">Password:</p>
                                <p class="text-sm font-mono text-gray-900 mb-3"><?php echo htmlspecialchars($password); ?></p>
                                
                                <p class="text-xs text-gray-600 mb-1">Hash:</p>
                                <p class="text-sm font-mono text-gray-900 break-all"><?php echo htmlspecialchars($hash); ?></p>
                                
                                <button onclick="copyHash('<?php echo htmlspecialchars($hash); ?>')" 
                                        class="mt-3 inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    <i class="fas fa-copy mr-2"></i>Copy Hash
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r">
                    <div class="flex">
                        <i class="fas fa-info-circle text-blue-400 mt-1"></i>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-blue-800 mb-2">SQL Insert Statement:</p>
                            <div class="bg-white p-3 rounded border border-blue-200 mt-2">
                                <pre class="text-xs font-mono text-gray-900 whitespace-pre-wrap">INSERT INTO shortcourse_users (username, email, password, role) 
VALUES (
    'your_username',
    'your_email@example.com',
    '<?php echo htmlspecialchars($hash); ?>',
    'admin'
);</pre>
                                <button onclick="copySQL()" 
                                        class="mt-3 inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    <i class="fas fa-copy mr-2"></i>Copy SQL
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Enter Password to Hash
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="password" 
                               name="password" 
                               required
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter strong password"
                               value="<?php echo htmlspecialchars($password); ?>">
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-xs font-semibold text-gray-700 mb-2">Password must contain:</p>
                        <div class="space-y-1 text-xs text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-gray-400 mr-2"></i>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-gray-400 mr-2"></i>
                                <span>One lowercase letter (a-z)</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-gray-400 mr-2"></i>
                                <span>One uppercase letter (A-Z)</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-gray-400 mr-2"></i>
                                <span>One number (0-9)</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-gray-400 mr-2"></i>
                                <span>One special character (!@#$%^&*...)</span>
                            </div>
                        </div>
                    </div>
                    
                    <p class="mt-2 text-sm text-gray-500">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Password will be hashed using PHP's password_hash() with PASSWORD_DEFAULT
                    </p>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 shadow-md hover:shadow-lg">
                    <i class="fas fa-key mr-2"></i>Generate Hash
                </button>
            </form>

            <div class="mt-8 border-t border-gray-200 pt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-book text-blue-600 mr-2"></i>
                    How to Add Admin Users
                </h2>
                
                <div class="space-y-4 text-sm text-gray-700">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold mb-2">Method 1: Using phpMyAdmin</h3>
                        <ol class="list-decimal list-inside space-y-1 ml-2">
                            <li>Generate password hash using this tool</li>
                            <li>Open phpMyAdmin and select 'grading_system' database</li>
                            <li>Click on 'shortcourse_users' table</li>
                            <li>Click 'Insert' tab</li>
                            <li>Fill in: username, email, password (paste hash), role = 'admin'</li>
                            <li>Click 'Go' to insert</li>
                        </ol>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold mb-2">Method 2: Using SQL Query</h3>
                        <ol class="list-decimal list-inside space-y-1 ml-2">
                            <li>Generate password hash using this tool</li>
                            <li>Copy the SQL INSERT statement above</li>
                            <li>Replace 'your_username' and 'your_email@example.com' with actual values</li>
                            <li>Run the SQL query in phpMyAdmin SQL tab</li>
                        </ol>
                    </div>

                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <h3 class="font-semibold text-yellow-800 mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Security Notes
                        </h3>
                        <ul class="list-disc list-inside space-y-1 ml-2 text-yellow-700">
                            <li>Never store plain text passwords in the database</li>
                            <li>Always use strong passwords (8+ characters, mixed case, numbers, symbols)</li>
                            <li>Delete this file after setting up admin accounts in production</li>
                            <li>Each admin should have a unique email address</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyHash(hash) {
            navigator.clipboard.writeText(hash).then(() => {
                alert('Hash copied to clipboard!');
            });
        }

        function copySQL() {
            const sql = document.querySelector('pre').textContent;
            navigator.clipboard.writeText(sql).then(() => {
                alert('SQL statement copied to clipboard!');
            });
        }
    </script>
</body>
</html>

