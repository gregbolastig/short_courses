<?php
session_start();
require_once 'config/database.php';

$errors = [];
$success_message = '';
$search_results = [];
$student_profile = null;
$search_performed = false;

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
                    $errors[] = 'No student record found with ULI: ' . htmlspecialchars($uli);
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
                    // Search with all criteria for exact match
                    $stmt = $conn->prepare("SELECT * FROM students WHERE 
                        first_name = :first_name AND 
                        last_name = :last_name AND 
                        birthday = :birthday AND 
                        birth_province = :birth_province AND 
                        birth_city = :birth_city");
                    
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
                    } elseif (count($search_results) === 0) {
                        $errors[] = 'No student record found matching all the provided information. Please verify your details and try again.';
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
$page_title = 'JZGMSAT Student Portal';
$page_subtitle = 'Student Registration System';
$page_description = 'Search your records or register as a new student';

// Include header component
include 'student/components/header.php';
?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php 
        // Convert old error variable to errors array for component compatibility
        if (!empty($error)) {
            $errors = [$error];
        }
        
        // Include alerts component
        include 'student/components/alerts.php'; 
        ?>
        <?php if ($student_profile): ?>
            <!-- Student Profile Display -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-8">
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                        <div class="flex-shrink-0">
                            <?php if (!empty($student_profile['profile_picture']) && file_exists($student_profile['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($student_profile['profile_picture']); ?>" 
                                     alt="Profile Picture" 
                                     class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white shadow-lg">
                            <?php else: ?>
                                <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                    <span class="text-2xl md:text-3xl font-bold text-white">
                                        <?php echo strtoupper(substr($student_profile['first_name'], 0, 1) . substr($student_profile['last_name'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center md:text-left text-white flex-1">
                            <h2 class="text-2xl md:text-3xl font-bold mb-2">
                                <?php echo htmlspecialchars(trim($student_profile['first_name'] . ' ' . ($student_profile['middle_name'] ? $student_profile['middle_name'] . ' ' : '') . $student_profile['last_name'])); ?>
                                <?php if ($student_profile['extension_name']): ?>
                                    <?php echo htmlspecialchars($student_profile['extension_name']); ?>
                                <?php endif; ?>
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-green-100 mb-4">
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-id-card mr-2"></i>
                                    <span>ULI: <?php echo htmlspecialchars($student_profile['uli']); ?></span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-envelope mr-2"></i>
                                    <span><?php echo htmlspecialchars($student_profile['email']); ?></span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-phone mr-2"></i>
                                    <span><?php echo htmlspecialchars($student_profile['contact_number']); ?></span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-calendar mr-2"></i>
                                    <span>Registered: <?php echo date('M j, Y', strtotime($student_profile['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <!-- Status Badge -->
                            <div>
                                <?php
                                $status_class = '';
                                $status_icon = '';
                                $status_text = '';
                                switch ($student_profile['status']) {
                                    case 'approved':
                                        $status_class = 'bg-green-100 text-green-800 border-green-200';
                                        $status_icon = 'fas fa-check-circle';
                                        $status_text = 'Approved - You can now attend classes';
                                        break;
                                    case 'rejected':
                                        $status_class = 'bg-red-100 text-red-800 border-red-200';
                                        $status_icon = 'fas fa-times-circle';
                                        $status_text = 'Application Rejected - Please contact admin';
                                        break;
                                    default:
                                        $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                        $status_icon = 'fas fa-clock';
                                        $status_text = 'Pending Approval - Please wait for admin review';
                                }
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border <?php echo $status_class; ?>">
                                    <i class="<?php echo $status_icon; ?> mr-2"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Course Information -->
                <?php if ($student_profile['status'] === 'approved' && ($student_profile['course'] || $student_profile['nc_level'])): ?>
                    <div class="px-6 py-6 bg-gray-50 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-book text-red-800 mr-2"></i>Your Approved Course
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php if ($student_profile['course']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Course</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['course']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student_profile['nc_level']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['nc_level']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student_profile['training_start']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training Start</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y', strtotime($student_profile['training_start'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student_profile['training_end']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training End</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y', strtotime($student_profile['training_end'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($student_profile['adviser']): ?>
                            <div class="mt-4 bg-white p-4 rounded-lg border border-gray-200">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Adviser</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['adviser']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="px-6 py-4 bg-white border-t border-gray-200">
                    <div class="flex justify-center">
                        <a href="index.php" class="inline-flex items-center px-6 py-3 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Search Again
                        </a>
                    </div>
                </div>
            </div>
            
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
                                        </div>
                                    </div>
                                </div>
                                <a href="?student_id=<?php echo $student['id']; ?>" 
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
            
            <!-- Welcome Section -->
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Welcome to JZGMSAT Student Portal</h2>
                <p class="text-lg text-gray-600 mb-6">Choose an option below to get started</p>
            </div>
            
            <!-- Main Options -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- Search for Existing Record -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-search text-2xl text-red-800"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Already Registered?</h3>
                        <p class="text-gray-600">Search for your existing student record</p>
                    </div>
                    
                    <!-- Search Tabs -->
                    <div class="mb-6">
                        <div class="flex border-b border-gray-200">
                            <button onclick="showSearchTab('uli')" id="uli-tab" class="flex-1 py-2 px-4 text-sm font-medium text-center border-b-2 border-red-800 text-red-800">
                                <i class="fas fa-id-card mr-2"></i>Search by ULI
                            </button>
                            <button onclick="showSearchTab('name')" id="name-tab" class="flex-1 py-2 px-4 text-sm font-medium text-center border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                                <i class="fas fa-user mr-2"></i>Search by Details
                            </button>
                        </div>
                    </div>
                    
                    <!-- ULI Search Form -->
                    <div id="uli-search" class="search-form">
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="search_type" value="uli">
                            <div>
                                <label for="uli" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-id-card text-red-800 mr-2"></i>Enter your ULI (Unique Learner Identifier)
                                </label>
                                <input type="text" id="uli" name="uli" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                       placeholder="e.g., ULI123456789"
                                       value="<?php echo htmlspecialchars($_POST['uli'] ?? ''); ?>">
                            </div>
                            <button type="submit" 
                                    class="w-full flex justify-center items-center py-3 px-4 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                                <i class="fas fa-search mr-2"></i>Search by ULI
                            </button>
                        </form>
                    </div>
                    
                    <!-- Name Search Form -->
                    <div id="name-search" class="search-form hidden">
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="search_type" value="name">
                            
                            <!-- Personal Information -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-user text-red-800 mr-2"></i>First Name *
                                    </label>
                                    <input type="text" id="first_name" name="first_name" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                           placeholder="Enter first name"
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-user text-red-800 mr-2"></i>Last Name *
                                    </label>
                                    <input type="text" id="last_name" name="last_name" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                           placeholder="Enter last name"
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Date of Birth -->
                            <div>
                                <label for="birthday" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt text-red-800 mr-2"></i>Date of Birth *
                                </label>
                                <input type="date" id="birthday" name="birthday" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200"
                                       value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>">
                            </div>
                            
                            <!-- Place of Birth -->
                            <div class="space-y-3">
                                <h4 class="text-sm font-semibold text-gray-800 flex items-center">
                                    <i class="fas fa-map-marker-alt text-red-800 mr-2"></i>Place of Birth *
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="birth_province" class="block text-sm font-medium text-gray-700 mb-2">
                                            Province *
                                        </label>
                                        <select id="birth_province" name="birth_province" required 
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200">
                                            <option value="">Select province</option>
                                            <!-- Provinces will be loaded via JavaScript -->
                                        </select>
                                    </div>
                                    <div>
                                        <label for="birth_city" class="block text-sm font-medium text-gray-700 mb-2">
                                            City/Municipality *
                                        </label>
                                        <select id="birth_city" name="birth_city" required 
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200">
                                            <option value="">Select city/municipality</option>
                                            <!-- Cities will be loaded via JavaScript -->
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-xs text-red-700 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    All fields are required for security verification. Please enter the exact information as registered.
                                </p>
                            </div>
                            
                            <button type="submit" 
                                    class="w-full flex justify-center items-center py-3 px-4 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                                <i class="fas fa-search mr-2"></i>Search by Personal Details
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- New Student Registration -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-user-plus text-2xl text-red-800"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">New to JZGMSAT?</h3>
                        <p class="text-gray-600">Register as a new student to get started</p>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h4 class="font-semibold text-red-800 mb-2">
                                <i class="fas fa-check-circle mr-2"></i>What you'll get:
                            </h4>
                            <ul class="text-sm text-red-700 space-y-1">
                                <li><i class="fas fa-arrow-right mr-2"></i>Your unique ULI (Unique Learner Identifier)</li>
                                <li><i class="fas fa-arrow-right mr-2"></i>Access to course enrollment</li>
                                <li><i class="fas fa-arrow-right mr-2"></i>Student profile and records</li>
                                <li><i class="fas fa-arrow-right mr-2"></i>Training certificates upon completion</li>
                            </ul>
                        </div>
                        
                        <a href="student/register.php" 
                           class="w-full flex justify-center items-center py-3 px-4 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-800 transition duration-200">
                            <i class="fas fa-user-plus mr-2"></i>Register as New Student
                        </a>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </main>
    
    <?php 
    $include_search_js = true; // Enable search JavaScript
    include 'student/components/footer.php'; 
    ?>