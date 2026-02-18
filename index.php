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
        // Convert old error variable to errors array for component compatibility
        if (!empty($error)) {
            $errors = [$error];
        }
        
        // Include alerts component
        include 'student/components/alerts.php'; 
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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                
                <!-- New Student Registration -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-user-plus text-2xl text-red-800"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">New to Jacobo Z. Gonzales Memorial School of Arts and Trades?</h3>
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
                
                <!-- Search for Existing Record -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto h-16 w-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-search text-2xl text-red-800"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Already Registered?</h3>
                        <p class="text-gray-600">Search for your existing student record</p>
                    </div>
                    
                    <!-- Important Notice -->
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-semibold text-yellow-800 mb-2">Important Notice</h4>
                                <div class="text-xs text-yellow-700 space-y-1">
                                    <p>• Make sure you have your correct ULI or personal details before searching</p>
                                    <p>• If you don't know your ULI or are unsure about your details, please visit the registrar's office</p>
                                    <p>• Contact the registrar if you need assistance finding your records</p>
                                </div>
                            </div>
                        </div>
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
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-red-800 transition duration-200 font-mono tracking-wider"
                                       placeholder="ABC-12-123-12345-123"
                                       maxlength="25"
                                       title="Format: ABC-12-123-12345-123 (3 letters, then numbers separated by dashes)"
                                       value="<?php echo htmlspecialchars($_POST['uli'] ?? ''); ?>">
                                <p class="text-xs text-gray-500 mt-2 flex items-center">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Format: 3 letters, then numbers (ABC-12-123-12345-123). You can type in lowercase - it will automatically convert to uppercase and format with dashes.
                                </p>
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