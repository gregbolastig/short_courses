<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$success_message = '';
$error_message = '';

// Handle approval/rejection actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $student_id = $_GET['id'];
    
    if (in_array($action, ['approve', 'reject'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE students SET status = :status, approved_by = :admin_id, approved_at = NOW() WHERE id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':id', $student_id);
            
            if ($stmt->execute()) {
                $success_message = 'Student registration ' . $status . ' successfully.';
                
                // Log the approval action
                if ($status === 'approved') {
                    $success_message .= ' The student\'s course completion has been recorded.';
                } elseif ($status === 'rejected') {
                    $success_message .= ' The student has been notified of the rejection.';
                }
            } else {
                $error_message = 'Failed to update student status.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get pending students
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->query("SELECT * FROM students WHERE status = 'pending' ORDER BY created_at ASC");
    $pending_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approvals - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            500: '#334155',
                            600: '#475569',
                            700: '#64748b'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-secondary-500 to-secondary-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white">Pending Approvals</h1>
                    <p class="text-blue-100 mt-1">Review and approve student registrations</p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-blue-100 text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="../auth/logout.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-200">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8">
                <a href="dashboard.php" class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                    Dashboard
                </a>
                <a href="students/index.php" class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                    Manage Students
                </a>
                <a href="pending_approvals.php" class="border-b-2 border-secondary-500 text-secondary-600 py-4 px-1 text-sm font-medium">
                    Pending Approvals
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Alerts -->
        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Pending Students -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    Pending Student Registrations 
                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <?php echo count($pending_students); ?> pending
                    </span>
                </h3>
            </div>
            
            <?php if (empty($pending_students)): ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No pending approvals</h3>
                    <p class="mt-1 text-sm text-gray-500">All student registrations have been reviewed.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($pending_students as $student): ?>
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <?php 
                                            // Handle profile picture path resolution for admin view
                                            $profile_picture_url = '';
                                            $file_exists = false;
                                            
                                            if (!empty($student['profile_picture'])) {
                                                $stored_path = $student['profile_picture'];
                                                
                                                // Handle both old format (../uploads/profiles/file.jpg) and new format (uploads/profiles/file.jpg)
                                                if (strpos($stored_path, '../') === 0) {
                                                    // Old format: remove one level of ../
                                                    $profile_picture_url = substr($stored_path, 3);
                                                } else {
                                                    // New format: use as is
                                                    $profile_picture_url = $stored_path;
                                                }
                                                
                                                $file_exists = file_exists($profile_picture_url);
                                            }
                                            ?>
                                            
                                            <?php if (!empty($student['profile_picture']) && $file_exists): ?>
                                                <img class="h-12 w-12 rounded-full object-cover" 
                                                     src="<?php echo htmlspecialchars($profile_picture_url); ?>" 
                                                     alt="Profile">
                                            <?php else: ?>
                                                <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </h4>
                                            <div class="mt-1 text-sm text-gray-500 space-y-1">
                                                <p><span class="font-medium">Student ID:</span> <?php echo htmlspecialchars($student['student_id']); ?></p>
                                                <p><span class="font-medium">ULI:</span> <?php echo htmlspecialchars($student['uli']); ?></p>
                                                <p><span class="font-medium">Email:</span> <?php echo htmlspecialchars($student['email']); ?></p>
                                                <p><span class="font-medium">Contact:</span> <?php echo htmlspecialchars($student['contact_number']); ?></p>
                                                <p><span class="font-medium">Address:</span> <?php echo htmlspecialchars($student['barangay'] . ', ' . $student['city'] . ', ' . $student['province']); ?></p>
                                                <p><span class="font-medium">Last School:</span> <?php echo htmlspecialchars($student['last_school']); ?></p>
                                                <p><span class="font-medium">Registered:</span> <?php echo date('M j, Y g:i A', strtotime($student['created_at'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 ml-6">
                                    <div class="flex flex-col space-y-2">
                                        <a href="students/view.php?id=<?php echo $student['id']; ?>" 
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary-500">
                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View Details
                                        </a>
                                        <div class="flex space-x-2">
                                            <a href="approve_student.php?id=<?php echo $student['id']; ?>" 
                                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Review & Approve
                                            </a>
                                            <a href="?action=reject&id=<?php echo $student['id']; ?>" 
                                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                               onclick="return confirm('Are you sure you want to reject this student registration?')">
                                                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                Reject
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>