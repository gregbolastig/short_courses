<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'View Student';

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id <= 0) {
    header('Location: index.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header('Location: index.php');
        exit();
    }
    
    // Get pending approvals count for sidebar
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - Student Registration System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php include '../components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
        <?php include '../components/sidebar.php'; ?>
        
        <!-- Main content wrapper -->
        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include '../components/header.php'; ?>
            
            <!-- Main content area -->
            <main class="overflow-y-auto focus:outline-none">
                <div class="py-4 md:py-6">
                    <div class="max-w-4xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        <!-- Back Button -->
                        <div class="mb-6">
                            <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Course Management
                            </a>
                        </div>
                        
                        <!-- Student Details Card -->
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <h2 class="text-xl font-semibold text-gray-900">Student Details</h2>
                            </div>
                            
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                                        <dl class="space-y-3">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                                                <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">ULI</dt>
                                                <dd class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded inline-block"><?php echo htmlspecialchars($student['uli']); ?></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                                <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email']); ?></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                                                <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($student['contact_number']); ?></dd>
                                            </div>
                                        </dl>
                                    </div>
                                    
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Course Information</h3>
                                        <dl class="space-y-3">
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                                <dd class="text-sm">
                                                    <?php
                                                    $status_classes = [
                                                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                        'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                        'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                                    ];
                                                    $status_class = $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($student['status']); ?>
                                                    </span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Course</dt>
                                                <dd class="text-sm text-gray-900"><?php echo $student['course'] ? htmlspecialchars($student['course']) : 'Not assigned'; ?></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">NC Level</dt>
                                                <dd class="text-sm text-gray-900"><?php echo $student['nc_level'] ? htmlspecialchars($student['nc_level']) : 'Not assigned'; ?></dd>
                                            </div>
                                            <div>
                                                <dt class="text-sm font-medium text-gray-500">Adviser</dt>
                                                <dd class="text-sm text-gray-900"><?php echo $student['adviser'] ? htmlspecialchars($student['adviser']) : 'Not assigned'; ?></dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                                
                                <?php if ($student['status'] === 'pending'): ?>
                                    <div class="mt-6 pt-6 border-t border-gray-200">
                                        <div class="flex space-x-3">
                                            <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                                <i class="fas fa-check mr-2"></i>
                                                Approve Student
                                            </button>
                                            <a href="index.php?action=reject&id=<?php echo $student['id']; ?>" 
                                               class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                               onclick="return confirm('Are you sure you want to reject this student?')">
                                                <i class="fas fa-times mr-2"></i>
                                                Reject Student
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Include the same approval modal from index.php -->
    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeApprovalModal()"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="approvalForm" method="POST" action="index.php">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="student_id" id="modalStudentId">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Approve Student Registration
                                </h3>
                                <p class="text-sm text-gray-500 mb-4">
                                    Approving: <span id="modalStudentName" class="font-semibold"></span>
                                </p>
                                
                                <div class="space-y-4">
                                    <!-- Course Dropdown -->
                                    <div>
                                        <label for="course" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                        <select name="course" id="course" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Course</option>
                                            <option value="Computer Programming">Computer Programming</option>
                                            <option value="Automotive Servicing">Automotive Servicing</option>
                                            <option value="Welding">Welding</option>
                                            <option value="Electrical Installation">Electrical Installation</option>
                                            <option value="Plumbing">Plumbing</option>
                                            <option value="Carpentry">Carpentry</option>
                                            <option value="Masonry">Masonry</option>
                                            <option value="Electronics">Electronics</option>
                                        </select>
                                    </div>
                                    
                                    <!-- NC Level Dropdown -->
                                    <div>
                                        <label for="nc_level" class="block text-sm font-medium text-gray-700 mb-1">NC Level</label>
                                        <select name="nc_level" id="nc_level" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select NC Level</option>
                                            <option value="NC I">NC I</option>
                                            <option value="NC II">NC II</option>
                                            <option value="NC III">NC III</option>
                                            <option value="NC IV">NC IV</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Training Duration -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="training_start" class="block text-sm font-medium text-gray-700 mb-1">Training Start</label>
                                            <input type="date" name="training_start" id="training_start" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label for="training_end" class="block text-sm font-medium text-gray-700 mb-1">Training End</label>
                                            <input type="date" name="training_end" id="training_end" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                    
                                    <!-- Adviser Dropdown -->
                                    <div>
                                        <label for="adviser" class="block text-sm font-medium text-gray-700 mb-1">Adviser</label>
                                        <select name="adviser" id="adviser" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Adviser</option>
                                            <option value="Juan dela Cruz">Juan dela Cruz</option>
                                            <option value="Jane Smith">Jane Smith</option>
                                            <option value="Mike Johnson">Mike Johnson</option>
                                            <option value="Sarah Wilson">Sarah Wilson</option>
                                            <option value="David Brown">David Brown</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                            <i class="fas fa-check mr-2"></i>Approve Student
                        </button>
                        <button type="button" onclick="closeApprovalModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>