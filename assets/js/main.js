// Main JavaScript file for the application

// AAPI Configuration
const AAPI_BASE_URL = 'https://psgc.gitlab.io/api';

// Utility functions
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = '<option value="">Loading...</option>';
        element.disabled = true;
    }
}

function hideLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.disabled = false;
    }
}

// Age calculation
function calculateAge(birthday) {
    const today = new Date();
    const birthDate = new Date(birthday);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
}

// Profile picture preview
function previewProfilePicture(input) {
    const file = input.files[0];
    const preview = document.getElementById('profile-preview');
    const placeholder = document.getElementById('upload-placeholder');
    
    if (file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, JPEG, or PNG)');
            input.value = '';
            return;
        }
        
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    } else {
        // Reset to placeholder if no file selected
        if (preview) {
            preview.style.display = 'none';
        }
        if (placeholder) {
            placeholder.style.display = 'flex';
        }
    }
}

// AAPI Functions
async function loadProvinces(selectId) {
    try {
        showLoading(selectId);
        const response = await fetch(`${AAPI_BASE_URL}/provinces/`);
        const provinces = await response.json();
        
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Select Province</option>';
        
        provinces.forEach(province => {
            const option = document.createElement('option');
            option.value = province.name;
            option.textContent = province.name;
            option.dataset.code = province.code;
            select.appendChild(option);
        });
        
        hideLoading(selectId);
    } catch (error) {
        console.error('Error loading provinces:', error);
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Error loading provinces</option>';
        hideLoading(selectId);
    }
}

async function loadCities(provinceCode, selectId) {
    try {
        showLoading(selectId);
        const response = await fetch(`${AAPI_BASE_URL}/provinces/${provinceCode}/cities-municipalities/`);
        const cities = await response.json();
        
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Select City/Municipality</option>';
        
        cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.name;
            option.textContent = city.name;
            option.dataset.code = city.code;
            select.appendChild(option);
        });
        
        hideLoading(selectId);
    } catch (error) {
        console.error('Error loading cities:', error);
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Error loading cities</option>';
        hideLoading(selectId);
    }
}

async function loadBarangays(cityCode, selectId) {
    try {
        showLoading(selectId);
        const response = await fetch(`${AAPI_BASE_URL}/cities-municipalities/${cityCode}/barangays/`);
        const barangays = await response.json();
        
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Select Barangay</option>';
        
        barangays.forEach(barangay => {
            const option = document.createElement('option');
            option.value = barangay.name;
            option.textContent = barangay.name;
            select.appendChild(option);
        });
        
        hideLoading(selectId);
    } catch (error) {
        console.error('Error loading barangays:', error);
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">Error loading barangays</option>';
        hideLoading(selectId);
    }
}

// Form validation
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function validatePhoneNumber(phone) {
    const phoneRegex = /^(\+63|0)[0-9]{10}$/;
    return phoneRegex.test(phone.replace(/\s+/g, ''));
}

function validateForm() {
    let isValid = true;
    const errors = [];
    
    // Get all required fields
    const requiredFields = document.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            errors.push(`${field.previousElementSibling.textContent} is required`);
        } else {
            field.classList.remove('error');
        }
    });
    
    // Email validation
    const emailField = document.getElementById('email');
    if (emailField && emailField.value && !validateEmail(emailField.value)) {
        isValid = false;
        emailField.classList.add('error');
        errors.push('Please enter a valid email address');
    }
    
    // Phone validation
    const phoneFields = ['contact_number', 'parent_contact'];
    phoneFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && field.value && !validatePhoneNumber(field.value)) {
            isValid = false;
            field.classList.add('error');
            errors.push('Please enter a valid phone number');
        }
    });
    
    // Display errors
    const errorContainer = document.getElementById('form-errors');
    if (errorContainer) {
        if (errors.length > 0) {
            errorContainer.innerHTML = '<div class="alert alert-error">' + errors.join('<br>') + '</div>';
        } else {
            errorContainer.innerHTML = '';
        }
    }
    
    return isValid;
}

// Captcha functions
function generateCaptcha() {
    const num1 = Math.floor(Math.random() * 10) + 1;
    const num2 = Math.floor(Math.random() * 10) + 1;
    const operators = ['+', '-', '*'];
    const operator = operators[Math.floor(Math.random() * operators.length)];
    
    let answer;
    switch (operator) {
        case '+':
            answer = num1 + num2;
            break;
        case '-':
            answer = num1 - num2;
            break;
        case '*':
            answer = num1 * num2;
            break;
    }
    
    const questionElement = document.getElementById('captcha-question');
    const answerElement = document.getElementById('captcha-answer');
    
    if (questionElement) {
        questionElement.textContent = `${num1} ${operator} ${num2} = ?`;
        questionElement.dataset.answer = answer;
    }
    
    if (answerElement) {
        answerElement.value = '';
    }
}

function validateCaptcha() {
    const questionElement = document.getElementById('captcha-question');
    const answerElement = document.getElementById('captcha-answer');
    
    if (questionElement && answerElement) {
        const correctAnswer = parseInt(questionElement.dataset.answer);
        const userAnswer = parseInt(answerElement.value);
        
        return correctAnswer === userAnswer;
    }
    
    return false;
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Load provinces on page load
    const provinceSelects = document.querySelectorAll('[data-load-provinces]');
    provinceSelects.forEach(select => {
        loadProvinces(select.id);
    });
    
    // Generate captcha
    generateCaptcha();
    
    // Age calculation on birthday change
    const birthdayField = document.getElementById('birthday');
    if (birthdayField) {
        birthdayField.addEventListener('change', function() {
            const age = calculateAge(this.value);
            const ageField = document.getElementById('age');
            if (ageField) {
                ageField.value = age;
            }
        });
    }
    
    // Profile picture preview with new design
    const profileInput = document.getElementById('profile_picture');
    if (profileInput) {
        profileInput.addEventListener('change', function() {
            previewProfilePicture(this);
        });
        
        // Make placeholder clickable
        const placeholder = document.getElementById('upload-placeholder');
        if (placeholder) {
            placeholder.addEventListener('click', function() {
                profileInput.click();
            });
        }
    }
    
    // Province change handlers
    const addressProvinceSelect = document.getElementById('province');
    if (addressProvinceSelect) {
        addressProvinceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const provinceCode = selectedOption.dataset.code;
            
            if (provinceCode) {
                loadCities(provinceCode, 'city');
            }
            
            // Clear barangay dropdown
            const barangaySelect = document.getElementById('barangay');
            if (barangaySelect) {
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            }
        });
    }
    
    // City change handler
    const citySelect = document.getElementById('city');
    if (citySelect) {
        citySelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const cityCode = selectedOption.dataset.code;
            
            if (cityCode) {
                loadBarangays(cityCode, 'barangay');
            }
        });
    }
    
    // Place of birth province change
    const pobProvinceSelect = document.getElementById('pob_province');
    if (pobProvinceSelect) {
        pobProvinceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const provinceCode = selectedOption.dataset.code;
            
            if (provinceCode) {
                loadCities(provinceCode, 'pob_city');
            }
        });
    }
    
    // School province change
    const schoolProvinceSelect = document.getElementById('school_province');
    if (schoolProvinceSelect) {
        schoolProvinceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const provinceCode = selectedOption.dataset.code;
            
            if (provinceCode) {
                loadCities(provinceCode, 'school_city');
            }
        });
    }
    
    // Form submission
    const registrationForm = document.getElementById('registration-form');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(e) {
            if (!validateForm() || !validateCaptcha()) {
                e.preventDefault();
                if (!validateCaptcha()) {
                    alert('Please solve the captcha correctly');
                    generateCaptcha();
                }
            }
        });
    }
});