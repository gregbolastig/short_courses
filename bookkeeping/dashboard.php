<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as bookkeeping
if (!isset($_SESSION['bookkeeping_logged_in']) || $_SESSION['role'] !== 'bookkeeping') {
    header('Location: ../auth/bookkeeping_login.php');
    exit();
}

$username = $_SESSION['username'] ?? 'Bookkeeping User';

// Database connection
$database = new Database();
$conn = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records - JZGMSAT Bookkeeping</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .collapsible-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .collapsible-content.active {
            max-height: 1000px;
            transition: max-height 0.5s ease-in;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            animation: slideIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-red-900 shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-user-graduate text-white text-2xl mr-3"></i>
                    <span class="text-white text-xl font-bold">Student Records</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white">
                        <i class="fas fa-user-circle mr-2"></i><?php echo htmlspecialchars($username); ?>
                    </span>
                    <a href="../auth/logout.php" class="bg-red-800 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Search Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Search Students</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" id="searchLastName" placeholder="Enter last name" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" id="searchFirstName" placeholder="Enter first name" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ULI</label>
                    <input type="text" id="searchULI" placeholder="Enter ULI" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <button onclick="searchStudents()" class="bg-red-700 hover:bg-red-800 text-white px-6 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <button onclick="clearSearch()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-times mr-2"></i>Clear
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-900">Student List</h2>
                <span id="resultCount" class="text-sm text-gray-600">Loading...</span>
            </div>
            <div id="studentList" class="space-y-3">
                <!-- Students will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Receipt Number Modal -->
    <div id="receiptModal" class="modal">
        <div class="modal-content bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="bg-red-900 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
                <h3 id="receiptModalTitle" class="text-lg font-bold">
                    <i class="fas fa-receipt mr-2"></i>Insert Receipt Number
                </h3>
                <button onclick="closeReceiptModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="receiptForm" class="p-6">
                <input type="hidden" id="receiptStudentId" name="student_id">
                <input type="hidden" id="receiptEnrollmentId" name="enrollment_id">
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        <strong>Student:</strong> <span id="modalStudentName"></span>
                    </p>
                    <p class="text-sm text-gray-600">
                        <strong>Course:</strong> <span id="modalCourseName"></span>
                    </p>
                </div>

                <div class="mb-6">
                    <label for="receiptNumber" class="block text-sm font-medium text-gray-700 mb-2">
                        Receipt Number <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="receiptNumber" 
                        name="receipt_number" 
                        maxlength="9"
                        pattern="[0-9]{1,9}"
                        placeholder="Enter receipt number (max 9 digits)"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-lg"
                    >
                    <p class="text-xs text-gray-500 mt-1">Maximum 9 digits (numbers only)</p>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-lg transition duration-200 font-medium">
                        <i class="fas fa-save mr-2"></i>Save Receipt
                    </button>
                    <button type="button" onclick="closeReceiptModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success/Error Toast -->
    <div id="toast" class="fixed bottom-4 right-4 hidden">
        <div class="bg-white rounded-lg shadow-lg p-4 max-w-sm">
            <div class="flex items-center">
                <i id="toastIcon" class="text-2xl mr-3"></i>
                <p id="toastMessage" class="text-gray-700"></p>
            </div>
        </div>
    </div>

    <script>
        let allStudents = [];

        // Load students on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadStudents();
            setupReceiptInput();
        });

        // Load all students with optional filters
        function loadStudents(filters = {}) {
            // Build query string
            const params = new URLSearchParams();
            if (filters.lastName) params.append('last_name', filters.lastName);
            if (filters.firstName) params.append('first_name', filters.firstName);
            if (filters.uli) params.append('uli', filters.uli);
            
            const queryString = params.toString();
            const url = queryString ? `get_students.php?${queryString}` : 'get_students.php';
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showToast(data.error, 'error');
                        displayStudents([]);
                    } else {
                        allStudents = data;
                        displayStudents(allStudents);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error loading students', 'error');
                    displayStudents([]);
                });
        }

        // Display students
        function displayStudents(students) {
            const container = document.getElementById('studentList');
            const countEl = document.getElementById('resultCount');
            
            if (students.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <i class="fas fa-users text-gray-300 text-5xl mb-4"></i>
                        <p class="text-gray-500">No students found</p>
                    </div>
                `;
                countEl.textContent = '0 students';
                return;
            }

            countEl.textContent = `${students.length} student${students.length !== 1 ? 's' : ''}`;
            
            container.innerHTML = students.map(student => `
                <div class="border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                    <div class="p-4 cursor-pointer hover:bg-gray-50" onclick="toggleStudent(${student.id})">
                        <div class="flex justify-between items-center">
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900">
                                    ${student.last_name}, ${student.first_name} ${student.middle_name || ''}
                                </h3>
                                <p class="text-sm text-gray-600">ULI: ${student.uli}</p>
                                <p class="text-sm text-gray-500">Student ID: ${student.student_id}</p>
                            </div>
                            <i id="icon-${student.id}" class="fas fa-chevron-down text-gray-400 transition-transform"></i>
                        </div>
                    </div>
                    <div id="courses-${student.id}" class="collapsible-content border-t border-gray-200">
                        <div class="p-4 bg-gray-50">
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="font-medium text-gray-700">Enrolled Courses</h4>
                            </div>
                            <div id="course-list-${student.id}" class="space-y-2">
                                <p class="text-sm text-gray-500">Loading courses...</p>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Toggle student courses
        function toggleStudent(studentId) {
            const content = document.getElementById(`courses-${studentId}`);
            const icon = document.getElementById(`icon-${studentId}`);
            const courseList = document.getElementById(`course-list-${studentId}`);
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.style.transform = 'rotate(0deg)';
            } else {
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
                
                // Load courses if not already loaded
                if (courseList.innerHTML.includes('Loading')) {
                    loadCourses(studentId);
                }
            }
        }

        // Load courses for a student
        function loadCourses(studentId) {
            fetch(`get_student_courses.php?student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    const courseList = document.getElementById(`course-list-${studentId}`);
                    
                    if (data.length === 0) {
                        courseList.innerHTML = '<p class="text-sm text-gray-500">No courses enrolled</p>';
                        return;
                    }

                    courseList.innerHTML = data.map(course => `
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900">${course.course_name}</p>
                                    ${course.nc_level ? `<p class="text-sm text-gray-600">NC Level: ${course.nc_level}</p>` : ''}
                                    ${course.adviser_name ? `<p class="text-sm text-gray-600">Adviser: ${course.adviser_name}</p>` : ''}
                                    <p class="text-xs text-gray-500 mt-1">
                                        Status: <span class="font-medium">${course.enrollment_status}</span>
                                    </p>
                                    ${course.receipt_number ? `
                                        <p class="text-sm text-red-800 font-medium mt-2">
                                            <i class="fas fa-receipt mr-1"></i>Receipt #: ${course.receipt_number}
                                        </p>
                                    ` : `
                                        <p class="text-sm text-gray-400 mt-2">
                                            <i class="fas fa-receipt mr-1"></i>No receipt number
                                        </p>
                                    `}
                                </div>
                                <button onclick="openReceiptModal(${studentId}, ${course.enrollment_id}, '${course.student_name}', '${course.course_name}', '${course.receipt_number || ''}')" 
                                        class="bg-red-700 hover:bg-red-800 text-white px-3 py-1 rounded text-sm transition duration-200">
                                    <i class="fas fa-receipt mr-1"></i>${course.receipt_number ? 'Edit' : 'Add'}
                                </button>
                            </div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById(`course-list-${studentId}`).innerHTML = 
                        '<p class="text-sm text-red-500">Error loading courses</p>';
                });
        }

        // Search students - now fetches from server with filters
        function searchStudents() {
            const lastName = document.getElementById('searchLastName').value.trim();
            const firstName = document.getElementById('searchFirstName').value.trim();
            const uli = document.getElementById('searchULI').value.trim();

            // Load students with filters
            loadStudents({
                lastName: lastName,
                firstName: firstName,
                uli: uli
            });
        }

        // Clear search - reload all students
        function clearSearch() {
            document.getElementById('searchLastName').value = '';
            document.getElementById('searchFirstName').value = '';
            document.getElementById('searchULI').value = '';
            loadStudents(); // Reload all students without filters
        }

        // Open receipt modal
        function openReceiptModal(studentId, enrollmentId, studentName, courseName, existingReceipt = '') {
            document.getElementById('receiptStudentId').value = studentId;
            document.getElementById('receiptEnrollmentId').value = enrollmentId;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalCourseName').textContent = courseName;
            
            // Pre-fill receipt number if editing
            const receiptInput = document.getElementById('receiptNumber');
            receiptInput.value = existingReceipt;
            
            // Update modal title based on whether we're adding or editing
            const modalTitle = document.getElementById('receiptModalTitle');
            if (existingReceipt) {
                modalTitle.innerHTML = '<i class="fas fa-edit mr-2"></i>Edit Receipt Number';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-receipt mr-2"></i>Insert Receipt Number';
            }
            
            document.getElementById('receiptModal').classList.add('active');
            
            // Focus on receipt number input
            setTimeout(() => {
                receiptInput.focus();
            }, 100);
        }

        // Close receipt modal
        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.remove('active');
            document.getElementById('receiptForm').reset();
        }

        // Setup receipt input validation
        function setupReceiptInput() {
            const receiptInput = document.getElementById('receiptNumber');
            
            // Only allow numbers
            receiptInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Limit to 9 digits
                if (this.value.length > 9) {
                    this.value = this.value.slice(0, 9);
                }
            });
            
            // Prevent non-numeric keys
            receiptInput.addEventListener('keypress', function(e) {
                if (e.key && !/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab' && e.key !== 'Enter') {
                    e.preventDefault();
                }
            });
        }

        // Handle receipt form submission
        document.getElementById('receiptForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const receiptNumber = document.getElementById('receiptNumber').value.trim();
            
            if (!receiptNumber) {
                showToast('Please enter a receipt number', 'error');
                return;
            }
            
            if (receiptNumber.length > 9) {
                showToast('Receipt number must be maximum 9 digits', 'error');
                return;
            }
            
            if (!/^[0-9]+$/.test(receiptNumber)) {
                showToast('Receipt number must contain only numbers', 'error');
                return;
            }

            const formData = new FormData(this);

            fetch('save_receipt.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Receipt number saved successfully', 'success');
                    closeReceiptModal();
                    
                    // Reload courses for this student to show updated data
                    const studentId = document.getElementById('receiptStudentId').value;
                    loadCourses(studentId);
                } else {
                    showToast(data.message || 'Failed to save receipt number', 'error');
                }
            })
            .catch(error => {
                showToast('Error saving receipt number', 'error');
                console.error('Error:', error);
            });
        });

        // Show toast notification
        function showToast(message, type) {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toastIcon');
            const messageEl = document.getElementById('toastMessage');

            if (type === 'success') {
                icon.className = 'fas fa-check-circle text-green-500 text-2xl mr-3';
            } else {
                icon.className = 'fas fa-exclamation-circle text-red-500 text-2xl mr-3';
            }

            messageEl.textContent = message;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Add enter key support for search
        document.querySelectorAll('#searchLastName, #searchFirstName, #searchULI').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchStudents();
                }
            });
        });
    </script>
</body>
</html>
