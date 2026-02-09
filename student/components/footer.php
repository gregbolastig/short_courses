    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <div class="flex items-center justify-center mb-4">
                    <div class="h-8 w-8 bg-red-800 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-graduation-cap text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Jacobo Z. Gonzales Memorial School of Arts and Trades</h3>
                </div>
               
                <div class="flex justify-center space-x-6 text-sm text-gray-500">
                    
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        © <?php echo date('Y'); ?> Jacobo Z. Gonzales Memorial School of Arts and Trades. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <?php if (isset($include_search_js) && $include_search_js): ?>
    <script>
        // Philippine Address API Configuration
        const PSGC_API_BASE = 'https://psgc.gitlab.io/api';
        
        // ULI formatting function - maintains ABC-12-123-12345-123 format with automatic uppercase
        function formatULI(input) {
            let value = input.value.toUpperCase(); // Convert to uppercase automatically
            
            // Remove all non-alphanumeric characters to get clean input
            let cleanValue = value.replace(/[^A-Z0-9]/g, '');
            
            // Limit to 16 characters total (3 letters + 13 numbers)
            if (cleanValue.length > 16) {
                cleanValue = cleanValue.substring(0, 16);
            }
            
            // Build formatted string
            let formatted = '';
            
            // First 3 characters (letters only)
            if (cleanValue.length > 0) {
                let letters = cleanValue.substring(0, 3);
                // Ensure first 3 are letters, if not, don't format yet
                if (letters.length > 0) {
                    formatted += letters;
                }
            }
            
            // Add dash and next 2 digits
            if (cleanValue.length > 3) {
                formatted += '-' + cleanValue.substring(3, 5);
            }
            
            // Add dash and next 3 digits  
            if (cleanValue.length > 5) {
                formatted += '-' + cleanValue.substring(5, 8);
            }
            
            // Add dash and next 5 digits
            if (cleanValue.length > 8) {
                formatted += '-' + cleanValue.substring(8, 13);
            }
            
            // Add dash and last 3 digits
            if (cleanValue.length > 13) {
                formatted += '-' + cleanValue.substring(13, 16);
            }
            
            // Update input value
            input.value = formatted;
            
            // Visual feedback
            if (cleanValue.length === 16) {
                // Check if format is correct (3 letters + 13 numbers)
                const letters = cleanValue.substring(0, 3);
                const numbers = cleanValue.substring(3);
                const hasValidLetters = /^[A-Z]{3}$/.test(letters);
                const hasValidNumbers = /^\d{13}$/.test(numbers);
                
                if (hasValidLetters && hasValidNumbers) {
                    input.classList.add('border-green-500');
                    input.classList.remove('border-red-500', 'ring-red-500', 'border-gray-300');
                } else {
                    input.classList.add('border-red-500', 'ring-red-500');
                    input.classList.remove('border-green-500', 'border-gray-300');
                }
            } else {
                // Partial input - neutral styling
                input.classList.remove('border-red-500', 'ring-red-500', 'border-green-500');
                input.classList.add('border-gray-300');
            }
        }
        
        // Load provinces for search form
        async function loadSearchProvinces() {
            try {
                const response = await fetch(`${PSGC_API_BASE}/provinces/`);
                const provinces = await response.json();
                
                const select = document.getElementById('birth_province');
                if (select) {
                    select.innerHTML = '<option value="">Select province</option>';
                    provinces.forEach(province => {
                        const option = document.createElement('option');
                        option.value = province.name;
                        option.textContent = province.name;
                        option.dataset.code = province.code;
                        
                        // Restore selected value if exists
                        if (province.name === '<?php echo htmlspecialchars($_POST['birth_province'] ?? ''); ?>') {
                            option.selected = true;
                        }
                        
                        select.appendChild(option);
                    });
                    
                    // If there's a selected province, load its cities
                    const selectedProvince = select.options[select.selectedIndex];
                    if (selectedProvince && selectedProvince.dataset.code) {
                        loadSearchCities(selectedProvince.dataset.code);
                    }
                }
            } catch (error) {
                console.error('Error loading provinces:', error);
            }
        }
        
        // Load cities for search form
        async function loadSearchCities(provinceCode) {
            try {
                const response = await fetch(`${PSGC_API_BASE}/provinces/${provinceCode}/cities-municipalities/`);
                const cities = await response.json();
                
                const select = document.getElementById('birth_city');
                if (select) {
                    select.innerHTML = '<option value="">Select city/municipality</option>';
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city.name;
                        option.textContent = city.name;
                        
                        // Restore selected value if exists
                        if (city.name === '<?php echo htmlspecialchars($_POST['birth_city'] ?? ''); ?>') {
                            option.selected = true;
                        }
                        
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading cities:', error);
            }
        }
        
        function showSearchTab(tabName) {
            // Hide all search forms
            document.querySelectorAll('.search-form').forEach(form => {
                form.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('[id$="-tab"]').forEach(tab => {
                tab.classList.remove('border-red-800', 'text-red-800');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected form
            document.getElementById(tabName + '-search').classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById(tabName + '-tab');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-red-800', 'text-red-800');
            
            // Load provinces when name search tab is shown
            if (tabName === 'name') {
                loadSearchProvinces();
            }
        }
        
        // Initialize the correct tab based on POST data
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_POST['search_type']) && $_POST['search_type'] === 'name'): ?>
                showSearchTab('name');
            <?php else: ?>
                showSearchTab('uli');
            <?php endif; ?>
            
            // Show modal if needed
            <?php if ($show_registrar_modal): ?>
                document.getElementById('registrarModal').classList.remove('hidden');
            <?php endif; ?>
            
            // Set up ULI formatting for search form
            const uliInput = document.getElementById('uli');
            if (uliInput) {
                // Single input handler that does both formatting and uppercase conversion
                uliInput.addEventListener('input', function() {
                    // Store cursor position
                    const cursorPos = this.selectionStart;
                    const oldLength = this.value.length;
                    
                    // Format the ULI (this already converts to uppercase)
                    formatULI(this);
                    
                    // Adjust cursor position if needed
                    const newLength = this.value.length;
                    const lengthDiff = newLength - oldLength;
                    this.setSelectionRange(cursorPos + lengthDiff, cursorPos + lengthDiff);
                });
                
                // Format on paste
                uliInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        formatULI(this);
                    }, 10);
                });
                
                // Allow all alphanumeric characters (both upper and lowercase)
                uliInput.addEventListener('keypress', function(e) {
                    // Allow control keys (backspace, delete, tab, escape, enter)
                    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                        // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                        (e.ctrlKey === true)) {
                        return;
                    }
                    
                    const char = String.fromCharCode(e.which);
                    
                    // Allow both uppercase and lowercase letters, and numbers
                    if (!/[A-Za-z0-9]/.test(char)) {
                        e.preventDefault();
                    }
                });
            }
            
            // Set up province change handler for search form
            const birthProvinceSelect = document.getElementById('birth_province');
            if (birthProvinceSelect) {
                birthProvinceSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const provinceCode = selectedOption.dataset.code;
                    
                    // Clear city dropdown
                    const citySelect = document.getElementById('birth_city');
                    if (citySelect) {
                        citySelect.innerHTML = '<option value="">Select city/municipality</option>';
                    }
                    
                    if (provinceCode) {
                        loadSearchCities(provinceCode);
                    }
                });
            }
            
            // Set max date for birthday field
            const birthdayField = document.getElementById('birthday');
            if (birthdayField) {
                const today = new Date().toISOString().split('T')[0];
                birthdayField.setAttribute('max', today);
            }
        });
        
        // Modal functions
        function closeRegistrarModal() {
            document.getElementById('registrarModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            const registrarModal = document.getElementById('registrarModal');
            
            if (e.target === registrarModal) {
                closeRegistrarModal();
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRegistrarModal();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>