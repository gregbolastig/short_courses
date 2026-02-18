<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JZGMSAT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex flex-col lg:flex-row min-h-screen">
        <!-- Left Section - Image (60% width on large screens) -->
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
        
        <!-- Right Section - Role Selection (40% width on large screens) -->
        <div class="lg:w-2/5 flex items-center justify-center p-6 lg:p-12 order-2 lg:order-2 bg-white shadow-2xl">
            <div class="w-full max-w-md">
                <!-- Role Selection Container -->
                <div class="p-6 lg:p-8">
                    <div class="mb-8 text-center">
                        <h2 class="text-3xl font-bold text-gray-800 mb-2">Welcome</h2>
                        <p class="text-gray-600">Select your role to continue</p>
                    </div>
                    
                    <!-- Role Selection Buttons -->
                    <div class="space-y-4">
                        <!-- Admin Button -->
                        <a href="admin_login.php" class="block w-full bg-blue-900 hover:bg-blue-800 text-white font-semibold py-4 px-6 rounded-lg transition duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <div class="flex items-center justify-center">
                                <i class="fas fa-user-shield text-2xl mr-3"></i>
                                <div class="text-left">
                                    <div class="text-lg font-bold">Admin</div>
                                    <div class="text-sm opacity-90">System Administration</div>
                                </div>
                            </div>
                        </a>
                        
                        <!-- Bookkeeping Button -->
                        <a href="bookkeeping_login.php" class="block w-full bg-red-900 hover:bg-red-800 text-white font-semibold py-4 px-6 rounded-lg transition duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <div class="flex items-center justify-center">
                                <i class="fas fa-calculator text-2xl mr-3"></i>
                                <div class="text-left">
                                    <div class="text-lg font-bold">Bookkeeping</div>
                                    <div class="text-sm opacity-90">Financial Management</div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
