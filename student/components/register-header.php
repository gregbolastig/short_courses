<?php
/**
 * Registration Page Header Component
 * Special header for registration form with different styling
 */

// Default values if not set
$page_title = $page_title ?? 'Student Registration';
$show_logo = $show_logo ?? true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Professional Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fdf2f2',
                            100: '#fce7e7',
                            200: '#f9d5d5',
                            300: '#f4b5b5',
                            400: '#ec8888',
                            500: '#800000',
                            600: '#660000',
                            700: '#5c0000',
                            800: '#4a0000',
                            900: '#3d0000'
                        },
                        secondary: {
                            500: '#000080',
                            600: '#000066',
                            700: '#000055'
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
        .form-step {
            transition: all 0.3s ease-in-out;
        }
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #800000;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-gray-100 min-h-screen">
    <!-- Header with improved design -->
    <header class="bg-gradient-to-r from-primary-600 via-primary-500 to-primary-700 shadow-2xl relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center py-4 sm:py-6">
                <div class="text-center">
                    <div class="flex flex-col sm:flex-row items-center justify-center mb-2">
                        <?php if ($show_logo): ?>
                        <img src="../assets/images/Logo.png" alt="School Logo" class="h-16 w-16 sm:h-20 sm:w-20 object-cover rounded-full border-3 border-white/40 shadow-lg mb-3 sm:mb-0 sm:mr-6">
                        <?php endif; ?>
                        <div>
                            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white tracking-tight"><?php echo htmlspecialchars($page_title); ?></h1>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>