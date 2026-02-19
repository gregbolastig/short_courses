<?php
session_start();
require_once 'config/database.php';

$errors = [];
$success_message = '';
$search_results = [];
$student_profile = null;
$search_performed = false;
$show_registrar_modal = false;

// Initialize search attempt counter
if (!isset($_SESSION['search_attempts'])) {
    $_SESSION['search_attempts'] = 0;
}

// Handle reset attempts request
if (isset($_POST['reset_attempts'])) {
    $_SESSION['search_attempts'] = 0;
    exit('OK');
}

// Handle different actions
$action = $_GET['action'] ?? 'home';

// Handle search functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_type = $_POST['search_type'] ?? '';
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($search_type === 'uli') {
            $uli = trim($_POST['uli']);
            if (!empty($uli)) {
                $stmt = $conn->prepare("SELECT * FROM students WHERE uli = :uli");
                $stmt->bindParam(':uli', $uli);
                $stmt->execute();
                $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
                $search_performed = true;
                
                if (!$student_profile) {
                    $_SESSION['search_attempts']++;
                    $errors[] = 'No student record found with ULI: ' . htmlspecialchars($uli);
                    
                    // Show modal only after 5 attempts
                    if ($_SESSION['search_attempts'] >= 5) {
                        $show_registrar_modal = true;
                    }
                } else {
                    // Reset attempts on successful search
                    $_SESSION['search_attempts'] = 0;
                }
            } else {
                $errors[] = 'Please enter a valid ULI';
            }
        } elseif ($search_type === 'name') {
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $birthday = trim($_POST['birthday']);
            $birth_province = trim($_POST['birth_province']);
            $birth_city = trim($_POST['birth_city']);
            
            // Validate all required fields
            $missing_fields = [];
            if (empty($first_name)) $missing_fields[] = 'First Name';
            if (empty($last_name)) $missing_fields[] = 'Last Name';
            if (empty($birthday)) $missing_fields[] = 'Date of Birth';
            if (empty($birth_province)) $missing_fields[] = 'Place of Birth Province';
            if (empty($birth_city)) $missing_fields[] = 'Place of Birth City';
            
            if (!empty($missing_fields)) {
                $errors[] = 'Please fill in all required fields: ' . implode(', ', $missing_fields);
            } else {
                // Validate date format
                $date_obj = DateTime::createFromFormat('Y-m-d', $birthday);
                if (!$date_obj || $date_obj->format('Y-m-d') !== $birthday) {
                    $errors[] = 'Please enter a valid date of birth';
                } else {
                    // Search with all criteria for exact match (case-insensitive)
                    $stmt = $conn->prepare("SELECT * FROM students WHERE 
                        LOWER(TRIM(first_name)) = LOWER(TRIM(:first_name)) AND 
                        LOWER(TRIM(last_name)) = LOWER(TRIM(:last_name)) AND 
                        birthday = :birthday AND 
                        LOWER(TRIM(birth_province)) = LOWER(TRIM(:birth_province)) AND 
                        LOWER(TRIM(birth_city)) = LOWER(TRIM(:birth_city))");
                    
                    $stmt->bindParam(':first_name', $first_name);
                    $stmt->bindParam(':last_name', $last_name);
                    $stmt->bindParam(':birthday', $birthday);
                    $stmt->bindParam(':birth_province', $birth_province);
                    $stmt->bindParam(':birth_city', $birth_city);
                    $stmt->execute();
                    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $search_performed = true;
                    
                    if (count($search_results) === 1) {
                        $student_profile = $search_results[0];
                        $search_results = [];
                        // Reset attempts on successful search
                        $_SESSION['search_attempts'] = 0;
                    } elseif (count($search_results) === 0) {
                        $_SESSION['search_attempts']++;
                        $errors[] = 'No student record found matching all the provided information. Please verify your details and try again.';
                        
                        // Show modal only after 5 attempts
                        if ($_SESSION['search_attempts'] >= 5) {
                            $show_registrar_modal = true;
                        }
                    } else {
                        // Reset attempts on successful search (multiple results)
                        $_SESSION['search_attempts'] = 0;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Handle student selection from multiple results
if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['student_id']);
        $stmt->execute();
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_profile) {
            $errors[] = 'Student record not found';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Set page variables for header component
// Using consistent title across all pages
$show_logo = true;

// Include header component
include 'student/components/header.php';
?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php 
        // Don't show alerts component on home page - errors will be shown as toast in modal
        ?>
        <?php if ($student_profile): ?>
            <!-- Redirect to profile page -->
            <script>
                window.location.href = 'student/profile/profile.php?student_id=<?php echo $student_profile['id']; ?>';
            </script>
            
        <?php elseif (!empty($search_results)): ?>
            <!-- Multiple Search Results -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-users text-red-800 mr-2"></i>Multiple Records Found
                </h2>
                <p class="text-gray-600 mb-6">We found <?php echo count($search_results); ?> students with that name. Please select your record:</p>
                
                <div class="space-y-4">
                    <?php foreach ($search_results as $student): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                        <span class="text-sm font-medium text-red-800">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>
                                        </h3>
                                        <div class="text-sm text-gray-600 space-y-1">
                                            <p><i class="fas fa-id-card mr-1"></i>ULI: <?php echo htmlspecialchars($student['uli']); ?></p>
                                            <p><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($student['email']); ?></p>
                                            <p><i class="fas fa-calendar mr-1"></i>Registered: <?php echo date('M j, Y', strtotime($student['created_at'])); ?></p>
                                            <div class="flex items-center mt-2">
                                                <?php
                                                $status_class = '';
                                                $status_icon = '';
                                                $status_text = '';
                                                switch ($student['status']) {
                                                    case 'completed':
                                                        $status_class = 'bg-green-100 text-green-800';
                                                        $status_icon = 'fas fa-graduation-cap';
                                                        $status_text = 'Completed';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-red-100 text-red-800';
                                                        $status_icon = 'fas fa-times-circle';
                                                        $status_text = 'Rejected';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        $status_icon = 'fas fa-clock';
                                                        $status_text = 'Pending';
                                                }
                                                ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                    <i class="<?php echo $status_icon; ?> mr-1"></i>
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <a href="student/profile/profile.php?student_id=<?php echo $student['id']; ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                    <i class="fas fa-eye mr-2"></i>View Profile
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Search
                    </a>
                </div>
            </div>
            
        <?php else: ?>  
          <!-- Main Search Interface -->
            
            
            
            <!-- Main Options -->

<!-- Student Type Selection Modal (Main Entry Point) -->
<div id="studentTypeModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto transform transition-all duration-300 ease-out">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-red-800 to-red-900 rounded-t-2xl px-8 py-6 text-white relative overflow-hidden">
            <div class="relative flex items-center">
                <div class="flex-shrink-0 w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user-graduate text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-1">Welcome to JZGMSAT </h3>
                    <p class="text-red-100 text-sm opacity-90">Please select your student type</p>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="px-8 py-6">
            <div class="grid grid-cols-1 gap-4">
                <button onclick="showNewStudentModal()"
                        class="w-full flex justify-center items-center py-3 px-4 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                    <i class="fas fa-user-plus mr-2"></i>New Registered Student
                </button>
                <button onclick="showOldStudentModal()"
                        class="w-full flex justify-center items-center py-3 px-4 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-800 transition duration-200">
                    <i class="fas fa-user-check mr-2"></i>Old Student
                </button>
            </div>
        </div>

        <!-- Footer Section -->
        <div class="bg-gray-50 rounded-b-2xl px-8 py-4 border-t border-gray-100">
            <div class="flex justify-center">

            </div>
        </div>
    </div>
</div>

<!-- New Student Modal -->
<div id="newStudentModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 hidden">
    <div class="relative bg-white rounded-lg shadow-2xl w-full max-w-md mx-auto transform transition-all duration-300 ease-out">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-red-800 to-red-900 rounded-t-lg px-5 py-3 text-white relative overflow-hidden">
            <div class="relative flex items-center">
                <div class="flex-shrink-0 w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-2">
                    <i class="fas fa-user-plus text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold">New Student Registration</h3>
                    <p class="text-red-100 text-xs opacity-90">Register as a new student</p>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="px-5 py-4">
            <div class="text-center mb-4">
                <div class="mx-auto h-12 w-12 bg-red-100 rounded-full flex items-center justify-center mb-2">
                    <i class="fas fa-user-plus text-xl text-red-800"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">New to JZGMSAT?</h3>
                <p class="text-gray-600 text-xs">Register as a new student to get started with your training journey</p>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <h4 class="font-semibold text-red-800 mb-2 text-xs flex items-center">
                    <i class="fas fa-check-circle mr-1.5"></i>What you'll receive:
                </h4>
                <ul class="text-xs text-red-700 space-y-1.5">
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-1.5 mt-0.5 flex-shrink-0 text-xs"></i>
                        <span>Your unique ULI (Unique Learner Identifier)</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-1.5 mt-0.5 flex-shrink-0 text-xs"></i>
                        <span>Access to course enrollment and training programs</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-1.5 mt-0.5 flex-shrink-0 text-xs"></i>
                        <span>Personal student profile and academic records</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-arrow-right mr-1.5 mt-0.5 flex-shrink-0 text-xs"></i>
                        <span>Training certificates upon successful completion</span>
                    </li>
                </ul>
            </div>

            <a href="student/register.php"
               class="w-full flex justify-center items-center py-2.5 px-4 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                <i class="fas fa-user-plus mr-2"></i>Register as New Student
            </a>
        </div>

        <!-- Footer Section -->
        <div class="bg-gray-50 rounded-b-lg px-5 py-2.5 border-t border-gray-100">
            <div class="flex justify-center">
                <button onclick="closeNewStudentModal()"
                        class="inline-flex items-center justify-center px-5 py-1.5 bg-gray-200 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                    <i class="fas fa-arrow-left mr-1.5"></i>Back
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Old Student Modal -->
<div id="oldStudentModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 hidden">
    <div class="relative bg-white rounded-lg shadow-2xl w-full max-w-5xl mx-auto transform transition-all duration-300 ease-out my-2">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-red-800 to-red-900 rounded-t-lg px-5 py-3 text-white relative overflow-hidden">
            <div class="relative flex items-center">
                <div class="flex-shrink-0 w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-2">
                    <i class="fas fa-search text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold">Search for Your Record</h3>
                    <p class="text-red-100 text-xs opacity-90">Find your existing student profile</p>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="px-5 py-4">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-center mb-4">
                    <div class="mx-auto h-12 w-12 bg-red-100 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-search text-lg text-red-800"></i>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 mb-1">Already Registered?</h3>
                    <p class="text-gray-600 text-xs">Search for your existing student record</p>
                </div>

                <!-- Important Notice -->
                <div class="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-2.5">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mt-0.5">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-sm"></i>
                        </div>
                        <div class="ml-2">
                            <h4 class="text-xs font-semibold text-yellow-800 mb-1.5">Important Notice</h4>
                            <div class="text-xs text-yellow-700 space-y-0.5">
                                <p>• Make sure you have your correct ULI or personal details</p>
                                <p>• If you don't know your ULI, visit the registrar's office</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Tabs -->
                <div class="mb-4">
                    <div class="flex border-b border-gray-200">
                        <button onclick="showSearchTab('uli')" id="uli-tab" class="flex-1 py-2 px-3 text-xs font-medium text-center border-b-2 border-red-800 text-red-800 -mb-px">
                            <i class="fas fa-id-card mr-1"></i>Search by ULI
                        </button>
                        <button onclick="showSearchTab('name')" id="name-tab" class="flex-1 py-2 px-3 text-xs font-medium text-center border-b-2 border-transparent text-gray-500 hover:text-gray-700 -mb-px">
                            <i class="fas fa-user mr-1"></i>Search by Details
                        </button>
                    </div>
                </div>

                <!-- ULI Search Form -->
                <div id="uli-search" class="search-form">
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="search_type" value="uli">
                        <div>
                            <label for="uli" class="block text-xs font-medium text-gray-700 mb-0.5">
                                <i class="fas fa-id-card text-red-800 mr-1"></i>Enter your ULI
                            </label>
                            <input type="text" id="uli" name="uli" required
                                   class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-800 focus:border-red-800 transition duration-200 font-mono tracking-wider"
                                   placeholder="ABC-12-123-12345-123"
                                   maxlength="25"
                                   title="Format: ABC-12-123-12345-123 (3 letters, then numbers separated by dashes)"
                                   value="<?php echo htmlspecialchars($_POST['uli'] ?? ''); ?>">
                            <p class="text-xs text-gray-500 mt-1 flex items-start">
                                <i class="fas fa-info-circle mr-1 mt-0.5 flex-shrink-0 text-xs"></i>
                                <span>Format: ABC-12-123-12345-123</span>
                            </p>
                        </div>
                        <button type="submit"
                                class="w-full flex justify-center items-center py-1.5 px-3 bg-red-800 text-white text-xs font-semibold rounded hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                            <i class="fas fa-search mr-1.5"></i>Search by ULI
                        </button>
                    </form>
                </div>

                <!-- Name Search Form -->
                <div id="name-search" class="search-form hidden">
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="search_type" value="name">

                        <!-- Personal Information -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label for="first_name" class="block text-xs font-medium text-gray-700 mb-0.5">
                                    <i class="fas fa-user text-red-800 mr-1"></i>First Name *
                                </label>
                                <input type="text" id="first_name" name="first_name" required
                                       class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                       placeholder="First name"
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="last_name" class="block text-xs font-medium text-gray-700 mb-0.5">
                                    <i class="fas fa-user text-red-800 mr-1"></i>Last Name *
                                </label>
                                <input type="text" id="last_name" name="last_name" required
                                       class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                       placeholder="Last name"
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Date of Birth -->
                        <div>
                            <label for="birthday" class="block text-xs font-medium text-gray-700 mb-0.5">
                                <i class="fas fa-calendar-alt text-red-800 mr-1"></i>Date of Birth *
                            </label>
                            <input type="date" id="birthday" name="birthday" required
                                   class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                   value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>">
                        </div>

                        <!-- Place of Birth -->
                        <div class="space-y-1">
                            <h4 class="text-xs font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-map-marker-alt text-red-800 mr-1"></i>Place of Birth *
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label for="birth_province" class="block text-xs font-medium text-gray-700 mb-0.5">
                                        Province *
                                    </label>
                                    <select id="birth_province" name="birth_province" required
                                            class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-800 focus:border-red-800 transition duration-200">
                                        <option value="">Select province</option>
                                        <!-- Provinces will be loaded via JavaScript -->
                                    </select>
                                </div>
                                <div>
                                    <label for="birth_city" class="block text-xs font-medium text-gray-700 mb-0.5">
                                        City/Municipality *
                                    </label>
                                    <select id="birth_city" name="birth_city" required
                                            class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-red-800 focus:border-red-800 transition duration-200">
                                        <option value="">Select city/municipality</option>
                                        <!-- Cities will be loaded via JavaScript -->
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="bg-red-50 border border-red-200 rounded p-1.5">
                            <p class="text-xs text-red-700 flex items-start">
                                <i class="fas fa-info-circle mr-1 mt-0.5 flex-shrink-0 text-xs"></i>
                                <span>All fields required for verification.</span>
                            </p>
                        </div>

                        <button type="submit"
                                class="w-full flex justify-center items-center py-1.5 px-3 bg-red-800 text-white text-xs font-semibold rounded hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                            <i class="fas fa-search mr-1.5"></i>Search by Details
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Footer Section -->
        <div class="bg-gray-50 rounded-b-lg px-5 py-2.5 border-t border-gray-100">
            <div class="flex justify-center">
                <button onclick="closeOldStudentModal()"
                        class="inline-flex items-center justify-center px-5 py-1.5 bg-gray-200 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                    <i class="fas fa-times mr-1.5"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>


            
        <?php endif; ?>
    </main>
    
    <!-- Professional Search Tips Modal -->
    <div id="registrarModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 <?php echo $show_registrar_modal ? '' : 'hidden'; ?>">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-auto transform transition-all duration-300 ease-out">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-red-800 to-red-900 rounded-t-2xl px-8 py-6 text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-black opacity-10"></div>
                <div class="relative flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-search text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-1">Search Assistance</h3>
                        <p class="text-red-100 text-sm opacity-90">Tips to help you find your student record</p>
                    </div>
                </div>
            </div>
            
            <!-- Content Section -->
            <div class="px-8 py-6">
                <div class="mb-6">
                    <p class="text-gray-700 text-sm leading-relaxed mb-6">
                        We're here to help you locate your student record. Please review these search tips to ensure accurate results:
                    </p>
                    
                    <!-- ULI Search Tips -->
                    <div class="mb-5">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-id-card text-red-600 text-sm"></i>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm">ULI Search Guidelines</h4>
                        </div>
                        <div class="ml-11 space-y-2">
                            <div class="flex items-start">
                                <div class="w-1.5 h-1.5 bg-red-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                <p class="text-xs text-gray-600">Check your enrollment documents or certificates for the exact ULI</p>
                            </div>
                            <div class="flex items-start">
                                <div class="w-1.5 h-1.5 bg-red-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                <p class="text-xs text-gray-600">Ensure format is correct: <span class="font-mono bg-gray-100 px-1 rounded">ABC-12-123-12345-123</span></p>
                            </div>
                            <div class="flex items-start">
                                <div class="w-1.5 h-1.5 bg-red-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                <p class="text-xs text-gray-600">Type exactly as shown on your official documents</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personal Details Tips -->
                    <div class="mb-5">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-user text-blue-600 text-sm"></i>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm">Personal Details Guidelines</h4>
                        </div>
                        <div class="ml-11 space-y-2">
                            <div class="flex items-start">
                                <div class="w-1.5 h-1.5 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                <p class="text-xs text-gray-600">Use exact spelling as when you registered</p>
                            </div>
                            <div class="flex items-start">
                                <div class="w-1.5 h-1.5 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                <p class="text-xs text-gray-600">Double-check your date of birth format</p>
                            </div>
                            <div class="flex items-start">
                                <div class="w-1.5 h-1.5 bg-blue-400 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                <p class="text-xs text-gray-600">Verify place of birth matches your official records</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Help -->
                    <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <div class="w-6 h-6 bg-amber-100 rounded-full flex items-center justify-center mr-3 mt-0.5">
                                <i class="fas fa-info-circle text-amber-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-amber-800 mb-1">Need Additional Assistance?</p>
                                <p class="text-xs text-amber-700">Visit the registrar's office with your ID and any school documents for personalized help.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer Section -->
            <div class="bg-gray-50 rounded-b-2xl px-8 py-4 border-t border-gray-100">
                <div class="flex justify-center">
                    <button onclick="closeRegistrarModal()" type="button" 
                            class="inline-flex items-center justify-center px-8 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white text-sm font-semibold rounded-xl hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        <i class="fas fa-check mr-2"></i>
                        Understood
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
    $include_search_js = true; // Enable search JavaScript
    include 'student/components/footer.php'; 
    ?>

    <script>
    // Show the student type modal on page load
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('studentTypeModal').classList.remove('hidden');
    });

    // Close student type modal
    function closeStudentTypeModal() {
        document.getElementById('studentTypeModal').classList.add('hidden');
    }

    // Show new student modal
    function showNewStudentModal() {
        document.getElementById('studentTypeModal').classList.add('hidden');
        document.getElementById('newStudentModal').classList.remove('hidden');
    }

    // Show old student modal
    function showOldStudentModal() {
        document.getElementById('studentTypeModal').classList.add('hidden');
        document.getElementById('oldStudentModal').classList.remove('hidden');
    }

    // Close new student modal
    function closeNewStudentModal() {
        document.getElementById('newStudentModal').classList.add('hidden');
        document.getElementById('studentTypeModal').classList.remove('hidden');
    }

    // Close old student modal
    function closeOldStudentModal() {
        document.getElementById('oldStudentModal').classList.add('hidden');
        document.getElementById('studentTypeModal').classList.remove('hidden');
    }

    // Search tab switching
    function showSearchTab(tab) {
        document.getElementById('uli-search').classList.add('hidden');
        document.getElementById('name-search').classList.add('hidden');
        document.getElementById(tab + '-search').classList.remove('hidden');

        document.getElementById('uli-tab').classList.remove('border-red-800', 'text-red-800');
        document.getElementById('uli-tab').classList.add('border-transparent', 'text-gray-500');
        document.getElementById('name-tab').classList.remove('border-red-800', 'text-red-800');
        document.getElementById('name-tab').classList.add('border-transparent', 'text-gray-500');

        if (tab === 'uli') {
            document.getElementById('uli-tab').classList.add('border-red-800', 'text-red-800');
            document.getElementById('uli-tab').classList.remove('border-transparent', 'text-gray-500');
        } else {
            document.getElementById('name-tab').classList.add('border-red-800', 'text-red-800');
            document.getElementById('name-tab').classList.remove('border-transparent', 'text-gray-500');
            // Load provinces when switching to name search tab
            if (typeof loadSearchProvinces === 'function') {
                loadSearchProvinces();
            }
        }
    }

    // Close registrar modal
    function closeRegistrarModal() {
        document.getElementById('registrarModal').classList.add('hidden');
    }

    // Show error toast notification
    function showErrorToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 z-[60] transition-all duration-300 opacity-0 translate-y-[-20px]';
        toast.innerHTML = `
            <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4 rounded-lg shadow-2xl border border-red-500 max-w-md">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold">Error</h3>
                        <p class="text-sm text-red-100 mt-1">${message}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" class="flex-shrink-0 text-white hover:text-red-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        }, 10);
        
        // Remove toast after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(-50%) translateY(-20px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Show errors on page load if any
    <?php if (!empty($errors) && $search_performed): ?>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($errors as $error): ?>
                showErrorToast(<?php echo json_encode($error); ?>);
            <?php endforeach; ?>
        });
    <?php endif; ?>
</script>
