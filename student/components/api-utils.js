/**
 * API Utilities for Jacobo Z. Gonzales Memorial School of Arts and Trades Student Portal
 * Shared functions for loading geographic and country data
 */

// API Configuration
const PSGC_API_BASE = 'https://psgc.gitlab.io/api';
const COUNTRY_API_BASE = 'https://restcountries.com/v3.1';

// Cache for API responses
const apiCache = {
    provinces: null,
    countries: null,
    cities: {},
    barangays: {}
};

// Utility functions
function showLoading(elementId) {
    const loadingDiv = document.getElementById(elementId + '-loading');
    if (loadingDiv) {
        loadingDiv.classList.remove('hidden');
    }
}

function hideLoading(elementId) {
    const loadingDiv = document.getElementById(elementId + '-loading');
    if (loadingDiv) {
        loadingDiv.classList.add('hidden');
    }
}

function populateSelect(selectId, options, placeholder = 'Select option', valueField = 'name', textField = 'name') {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    select.innerHTML = `<option value="">${placeholder}</option>`;
    
    options.forEach(option => {
        const optionElement = document.createElement('option');
        optionElement.value = option[valueField];
        optionElement.textContent = option[textField];
        
        // Store additional data as dataset attributes
        if (option.code) optionElement.dataset.code = option.code;
        if (option.flag) optionElement.dataset.flag = option.flag;
        
        select.appendChild(optionElement);
    });
}

// Load provinces with caching
async function loadProvinces(selectId, selectedValue = '') {
    try {
        console.log(`Loading provinces for ${selectId}`);
        showLoading(selectId);
        
        // Use cached data if available
        if (!apiCache.provinces) {
            const response = await fetch(`${PSGC_API_BASE}/provinces/`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            // Sort provinces alphabetically by name
            apiCache.provinces = data.sort((a, b) => a.name.localeCompare(b.name));
        }
        
        const provinces = apiCache.provinces;
        console.log(`Loaded ${provinces.length} provinces for ${selectId}`);
        
        populateSelect(selectId, provinces, 'Select province');
        
        // Restore selected value if provided
        if (selectedValue) {
            const select = document.getElementById(selectId);
            if (select) {
                select.value = selectedValue;
            }
        }
        
        hideLoading(selectId);
        return provinces;
    } catch (error) {
        console.error('Error loading provinces:', error);
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Error loading provinces</option>';
        }
        hideLoading(selectId);
        throw error;
    }
}

// Load cities/municipalities with caching
async function loadCities(provinceCode, selectId, selectedValue = '') {
    try {
        console.log(`Loading cities for province ${provinceCode} into ${selectId}`);
        showLoading(selectId);
        
        const cacheKey = provinceCode;
        
        // Use cached data if available
        if (!apiCache.cities[cacheKey]) {
            const response = await fetch(`${PSGC_API_BASE}/provinces/${provinceCode}/cities-municipalities/`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            // Sort cities alphabetically by name
            apiCache.cities[cacheKey] = data.sort((a, b) => a.name.localeCompare(b.name));
        }
        
        const cities = apiCache.cities[cacheKey];
        console.log(`Loaded ${cities.length} cities for ${selectId}`);
        
        populateSelect(selectId, cities, 'Select city/municipality');
        
        // Restore selected value if provided
        if (selectedValue) {
            const select = document.getElementById(selectId);
            if (select) {
                select.value = selectedValue;
            }
        }
        
        hideLoading(selectId);
        return cities;
    } catch (error) {
        console.error('Error loading cities:', error);
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Error loading cities</option>';
        }
        hideLoading(selectId);
        throw error;
    }
}

// Load barangays with caching
async function loadBarangays(cityCode, selectId, selectedValue = '') {
    try {
        console.log(`Loading barangays for city ${cityCode} into ${selectId}`);
        showLoading(selectId);
        
        const cacheKey = cityCode;
        
        // Use cached data if available
        if (!apiCache.barangays[cacheKey]) {
            const response = await fetch(`${PSGC_API_BASE}/cities-municipalities/${cityCode}/barangays/`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            // Sort barangays alphabetically by name
            apiCache.barangays[cacheKey] = data.sort((a, b) => a.name.localeCompare(b.name));
        }
        
        const barangays = apiCache.barangays[cacheKey];
        console.log(`Loaded ${barangays.length} barangays for ${selectId}`);
        
        populateSelect(selectId, barangays, 'Select barangay');
        
        // Restore selected value if provided
        if (selectedValue) {
            const select = document.getElementById(selectId);
            if (select) {
                select.value = selectedValue;
            }
        }
        
        hideLoading(selectId);
        return barangays;
    } catch (error) {
        console.error('Error loading barangays:', error);
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Error loading barangays</option>';
        }
        hideLoading(selectId);
        throw error;
    }
}

// Load country codes with caching
async function loadCountryCodes(selectId, selectedValue = '') {
    try {
        console.log(`Loading country codes for ${selectId}`);
        showLoading(selectId);
        
        // Use cached data if available
        if (!apiCache.countries) {
            const response = await fetch(`${COUNTRY_API_BASE}/all?fields=name,idd,flag`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const countries = await response.json();
            
            // Process and sort countries
            apiCache.countries = countries
                .filter(country => country.idd && country.idd.root)
                .map(country => ({
                    name: country.name.common,
                    code: country.idd.root + (country.idd.suffixes ? country.idd.suffixes[0] : ''),
                    flag: country.flag || ''
                }))
                .sort((a, b) => a.name.localeCompare(b.name));
        }
        
        const countries = apiCache.countries;
        console.log(`Loaded ${countries.length} country codes for ${selectId}`);
        
        // Populate select with country codes
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Code</option>';
            
            countries.forEach(country => {
                const option = document.createElement('option');
                option.value = country.code;
                option.textContent = `${country.flag} ${country.code}`;
                option.title = country.name;
                
                if (country.code === selectedValue) {
                    option.selected = true;
                }
                
                select.appendChild(option);
            });
        }
        
        hideLoading(selectId);
        return countries;
    } catch (error) {
        console.error('Error loading country codes:', error);
        const select = document.getElementById(selectId);
        if (select) {
            select.innerHTML = '<option value="">Error loading codes</option>';
        }
        hideLoading(selectId);
        throw error;
    }
}

// Setup cascading dropdowns for address fields
function setupAddressCascade(provinceId, cityId, barangayId = null) {
    const provinceSelect = document.getElementById(provinceId);
    const citySelect = document.getElementById(cityId);
    const barangaySelect = barangayId ? document.getElementById(barangayId) : null;
    
    if (provinceSelect) {
        provinceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const provinceCode = selectedOption.dataset.code;
            
            // Clear dependent dropdowns
            if (citySelect) {
                citySelect.innerHTML = '<option value="">Select city/municipality</option>';
            }
            if (barangaySelect) {
                barangaySelect.innerHTML = '<option value="">Select barangay</option>';
            }
            
            if (provinceCode) {
                loadCities(provinceCode, cityId);
            }
        });
    }
    
    if (citySelect && barangaySelect) {
        citySelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const cityCode = selectedOption.dataset.code;
            
            // Clear barangay dropdown
            barangaySelect.innerHTML = '<option value="">Select barangay</option>';
            
            if (cityCode) {
                loadBarangays(cityCode, barangayId);
            }
        });
    }
}

// Initialize API utilities when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('API utilities loaded and ready');
});

// Export functions for use in other scripts
window.APIUtils = {
    loadProvinces,
    loadCities,
    loadBarangays,
    loadCountryCodes,
    setupAddressCascade,
    showLoading,
    hideLoading,
    populateSelect
};