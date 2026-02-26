<?php
/**
 * Student Header Component
 * Reusable header for student pages with full school name
 */

// Default values if not set
$page_title = $page_title ?? 'Jacobo Z. Gonzales Memorial School of Arts and Trades';
$page_subtitle = $page_subtitle ?? 'Jacobo Z. Gonzales Memorial School of Arts and Trades';
$show_logo = $show_logo ?? true;
$header_class = $header_class ?? 'bg-gradient-to-r from-primary-600 via-primary-500 to-primary-700';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_subtitle) ? htmlspecialchars($page_subtitle) : 'Jacobo Z. Gonzales Memorial School of Arts and Trades'; ?></title>
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
                        },
                        maroon: {
                            50: '#fdf2f2',
                            100: '#fce7e7',
                            200: '#f9d5d5',
                            300: '#f4b5b5',
                            400: '#ec8888',
                            500: '#dc2626',
                            600: '#800000',
                            700: '#6b0000',
                            800: '#5a0000',
                            900: '#4a0000'
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
    <!-- Header with compact design -->
    <header class="bg-gradient-to-r from-primary-600 via-primary-500 to-primary-700 shadow-2xl relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-center py-6 sm:py-8">
                <div class="text-center">
                    <div class="flex flex-col sm:flex-row items-center justify-center">
                        <?php if ($show_logo): ?>
                        <?php 
                        // Determine the correct path based on the current directory depth
                        if (file_exists('assets/images/Logo.png')) {
                            // Root level (index.php)
                            $logo_path = 'assets/images/Logo.png';
                        } elseif (file_exists('../assets/images/Logo.png')) {
                            // One level deep (student/register.php)
                            $logo_path = '../assets/images/Logo.png';
                        } else {
                            // Two levels deep (student/profile/profile.php)
                            $logo_path = '../../assets/images/Logo.png';
                        }
                        ?>
                        <img src="<?php echo $logo_path; ?>" alt="School Logo" class="h-14 w-14 sm:h-20 sm:w-20 object-cover rounded-fulls mb-3 sm:mb-0 sm:mr-5">
                        <?php endif; ?>
                        <div class="text-center sm:text-left">
                            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white tracking-tight"><?php echo htmlspecialchars($page_title); ?></h1>
                            <?php if (isset($page_description)): ?>
                            <p class="text-xs text-white text-opacity-70 mt-2"><?php echo htmlspecialchars($page_description); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>