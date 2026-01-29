    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <div class="flex items-center justify-center mb-4">
                    <div class="h-8 w-8 bg-red-800 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-graduation-cap text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">JZGMSAT</h3>
                </div>
                <p class="text-gray-600 mb-4">Student Registration System</p>
                <div class="flex justify-center space-x-6 text-sm text-gray-500">
                    <span><i class="fas fa-phone mr-1"></i>Contact: (123) 456-7890</span>
                    <span><i class="fas fa-envelope mr-1"></i>Email: info@jzgmsat.edu</span>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        © <?php echo date('Y'); ?> JZGMSAT. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <?php if (isset($include_search_js) && $include_search_js): ?>
    <script>
        // Philippine Address API Configuration
        const PSGC_API_BASE = 'https://psgc.gitlab.io/api';
        
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
    </script>
    <?php endif; ?>
</body>
</html>