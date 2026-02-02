<?php
// Common JavaScript functions for admin panel
?>

<script>
// Sidebar toggle functionality
let sidebarCollapsed = false;

function toggleSidebar() {
    // Only work on desktop (md and up)
    if (window.innerWidth < 768) return;
    
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');
    const sidebarTextElements = document.querySelectorAll('#sidebar-text');
    const toggleIcon = document.getElementById('sidebar-toggle-icon');
    
    sidebarCollapsed = !sidebarCollapsed;
    
    if (sidebarCollapsed) {
        // Collapse sidebar
        sidebar.classList.remove('w-64');
        sidebar.classList.add('w-16');
        
        // Adjust main content margin using Tailwind classes
        if (mainContent) {
            mainContent.classList.remove('md:ml-64');
            mainContent.classList.add('md:ml-16');
        }
        
        // Hide all text elements
        sidebarTexts.forEach(text => {
            text.style.opacity = '0';
            setTimeout(() => text.style.display = 'none', 200);
        });
        sidebarTextElements.forEach(text => {
            text.style.opacity = '0';
            setTimeout(() => text.style.display = 'none', 200);
        });
        
        // Animate toggle icon to collapsed state (subtle animation)
        if (toggleIcon) {
            const line = toggleIcon.querySelector('div:first-child');
            const panel = toggleIcon.querySelector('div:last-child');
            if (line && panel) {
                line.style.opacity = '0.4';
                panel.style.transform = 'scaleX(0.6)';
                panel.style.opacity = '0.4';
            }
        }
        
        // Adjust padding for navigation items
        const navItems = sidebar.querySelectorAll('nav a, nav > div > div');
        navItems.forEach(item => {
            item.classList.remove('px-3');
            item.classList.add('px-2', 'justify-center');
        });
        
        // Center the logo container
        const logoContainer = sidebar.querySelector('.flex.items-center.flex-shrink-0');
        if (logoContainer) {
            logoContainer.classList.add('justify-center');
        }
        
        // Position toggle button for collapsed state
        const toggleButton = sidebar.querySelector('button[onclick="toggleSidebar()"]');
        if (toggleButton) {
            toggleButton.classList.remove('right-2');
            toggleButton.classList.add('right-1');
        }
        
    } else {
        // Expand sidebar
        sidebar.classList.remove('w-16');
        sidebar.classList.add('w-64');
        
        // Reset main content margin using Tailwind classes
        if (mainContent) {
            mainContent.classList.remove('md:ml-16');
            mainContent.classList.add('md:ml-64');
        }
        
        // Show all text elements
        sidebarTexts.forEach(text => {
            text.style.display = '';
            setTimeout(() => text.style.opacity = '', 50);
        });
        sidebarTextElements.forEach(text => {
            text.style.display = '';
            setTimeout(() => text.style.opacity = '', 50);
        });
        
        // Reset toggle icon to expanded state (restore full opacity)
        if (toggleIcon) {
            const line = toggleIcon.querySelector('div:first-child');
            const panel = toggleIcon.querySelector('div:last-child');
            if (line && panel) {
                line.style.opacity = '';
                panel.style.transform = '';
                panel.style.opacity = '';
            }
        }
        
        // Restore padding for navigation items
        const navItems = sidebar.querySelectorAll('nav a, nav > div > div');
        navItems.forEach(item => {
            item.classList.remove('px-2', 'justify-center');
            item.classList.add('px-3');
        });
        
        // Restore logo alignment
        const logoContainer = sidebar.querySelector('.flex.items-center.flex-shrink-0');
        if (logoContainer) {
            logoContainer.classList.remove('justify-center');
        }
        
        // Restore toggle button position
        const toggleButton = sidebar.querySelector('button[onclick="toggleSidebar()"]');
        if (toggleButton) {
            toggleButton.classList.remove('right-1');
            toggleButton.classList.add('right-2');
        }
    }
    
    // Force layout recalculation
    setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
    }, 300);
}

// Reset sidebar on window resize to prevent mobile issues
window.addEventListener('resize', function() {
    const mainContent = document.getElementById('main-content');
    
    if (window.innerWidth < 768 && sidebarCollapsed) {
        // Reset to expanded state on mobile
        const sidebar = document.getElementById('sidebar');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');
        const sidebarTextElements = document.querySelectorAll('#sidebar-text');
        const toggleIcon = document.getElementById('sidebar-toggle-icon');
        
        sidebarCollapsed = false;
        
        if (sidebar) {
            sidebar.classList.remove('w-16');
            sidebar.classList.add('w-64');
        }
        
        // Reset main content margin on mobile using CSS class
        if (mainContent) {
            mainContent.classList.remove('sidebar-collapsed');
        }
        
        sidebarTexts.forEach(text => {
            text.style.display = '';
        });
        sidebarTextElements.forEach(text => {
            text.style.display = '';
        });
        
        if (toggleIcon) {
            const line = toggleIcon.querySelector('div:first-child');
            const panel = toggleIcon.querySelector('div:last-child');
            if (line && panel) {
                line.style.opacity = '';
                panel.style.transform = '';
                panel.style.opacity = '';
            }
        }
        
        const navItems = sidebar?.querySelectorAll('nav a, nav > div > div');
        navItems?.forEach(item => {
            item.classList.remove('px-2', 'justify-center');
            item.classList.add('px-3');
        });
        
        const logoContainer = sidebar?.querySelector('.flex.items-center.flex-shrink-0');
        if (logoContainer) {
            logoContainer.classList.remove('justify-center');
        }
        
        const toggleButton = sidebar?.querySelector('button[onclick="toggleSidebar()"]');
        if (toggleButton) {
            toggleButton.classList.remove('right-1');
            toggleButton.classList.add('right-2');
        }
    }
});

function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-sidebar-overlay');
    
    if (window.innerWidth < 768) {
        // On mobile, toggle the sidebar visibility using Tailwind classes
        sidebar.classList.toggle('-translate-x-full');
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    } else {
        // On desktop, use the overlay system
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }
}

function toggleProfileDropdown() {
    const dropdown = document.getElementById('profile-dropdown');
    const chevron = document.getElementById('profile-chevron');
    
    dropdown.classList.toggle('hidden');
    chevron.classList.toggle('rotate-180');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    // Close mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-sidebar-overlay');
    const toggleButton = document.querySelector('[onclick="toggleMobileSidebar()"]');
    
    if (window.innerWidth < 768) {
        // Mobile: close sidebar if clicking outside
        if (sidebar && sidebar.classList.contains('show') && 
            !sidebar.contains(event.target) && 
            toggleButton && !toggleButton.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    } else {
        // Desktop: use overlay system
        const sidebarContent = overlay?.querySelector('.relative');
        if (overlay && !overlay.classList.contains('hidden') && 
            sidebarContent && !sidebarContent.contains(event.target) && 
            toggleButton && !toggleButton.contains(event.target)) {
            overlay.classList.add('hidden');
        }
    }
    
    // Close profile dropdown
    const profileDropdown = document.getElementById('profile-dropdown');
    const profileButton = document.querySelector('[onclick="toggleProfileDropdown()"]');
    
    if (profileButton && profileDropdown && 
        !profileButton.contains(event.target) && 
        !profileDropdown.contains(event.target)) {
        profileDropdown.classList.add('hidden');
        const chevron = document.getElementById('profile-chevron');
        if (chevron) chevron.classList.remove('rotate-180');
    }
});

// Add smooth scroll behavior
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Add loading states to action buttons
document.querySelectorAll('a[href*="action="]').forEach(link => {
    link.addEventListener('click', function() {
        const icon = this.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-spinner fa-spin mr-1';
        }
        this.style.pointerEvents = 'none';
        this.style.opacity = '0.7';
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.animate-fade-in');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease-out';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Add real-time clock
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { 
        hour12: true, 
        hour: 'numeric', 
        minute: '2-digit' 
    });
    const clockElement = document.getElementById('live-clock');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Update clock every minute
setInterval(updateClock, 60000);
updateClock(); // Initial call

// Student search functionality
function initializeStudentSearch() {
    const searchInput = document.getElementById('studentSearch');
    const studentTable = document.querySelector('.data-table tbody');
    
    if (searchInput && studentTable) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = studentTable.querySelectorAll('tr');
            
            rows.forEach(row => {
                const studentName = row.querySelector('td:first-child')?.textContent.toLowerCase() || '';
                const studentId = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const email = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const status = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                
                const matchesSearch = studentName.includes(searchTerm) || 
                                    studentId.includes(searchTerm) || 
                                    email.includes(searchTerm) || 
                                    status.includes(searchTerm);
                
                if (matchesSearch || searchTerm === '') {
                    row.style.display = '';
                    row.classList.add('animate-fade-in');
                } else {
                    row.style.display = 'none';
                    row.classList.remove('animate-fade-in');
                }
            });
            
            // Show "no results" message if no rows are visible
            const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
            const noResultsRow = studentTable.querySelector('.no-results-row');
            
            if (visibleRows.length === 0 && searchTerm !== '') {
                if (!noResultsRow) {
                    const noResultsElement = document.createElement('tr');
                    noResultsElement.className = 'no-results-row';
                    noResultsElement.innerHTML = `
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-search text-gray-300 text-3xl mb-3"></i>
                                <p class="text-sm font-medium">No students found</p>
                                <p class="text-xs">Try adjusting your search terms</p>
                            </div>
                        </td>
                    `;
                    studentTable.appendChild(noResultsElement);
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
            }
        });
        
        // Add search shortcut (Ctrl/Cmd + K)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });
    }
}

// Initialize search functionality
initializeStudentSearch();

// Approval Modal Functions
function openApprovalModal(studentId, studentName, course = '', ncLevel = '', adviser = '', trainingStart = '', trainingEnd = '') {
    document.getElementById('modalStudentId').value = studentId;
    document.getElementById('modalStudentName').textContent = studentName;
    
    // Pre-fill form fields if provided (for approved students)
    if (course) {
        const courseSelect = document.getElementById('course');
        if (courseSelect) {
            courseSelect.value = course;
            courseSelect.disabled = true; // Disable for approved students
        }
    }
    if (ncLevel) {
        const ncLevelSelect = document.getElementById('nc_level');
        if (ncLevelSelect) {
            ncLevelSelect.value = ncLevel;
            ncLevelSelect.disabled = true;
        }
    }
    if (adviser) {
        const adviserSelect = document.getElementById('adviser');
        if (adviserSelect) {
            adviserSelect.value = adviser;
            adviserSelect.disabled = true;
        }
    }
    if (trainingStart) {
        const trainingStartInput = document.getElementById('training_start');
        if (trainingStartInput) {
            trainingStartInput.value = trainingStart;
            trainingStartInput.disabled = true;
        }
    }
    if (trainingEnd) {
        const trainingEndInput = document.getElementById('training_end');
        if (trainingEndInput) {
            trainingEndInput.value = trainingEnd;
            trainingEndInput.disabled = true;
        }
    }
    
    // Update modal title and description for approved students
    const modalTitle = document.getElementById('modal-title');
    const modalDesc = document.getElementById('modalDescription');
    if (course) {
        if (modalTitle) {
            modalTitle.textContent = 'Approve Course Completion';
        }
        if (modalDesc) {
            modalDesc.textContent = 'Approve this student\'s course completion. Once approved, the student can apply for new courses.';
        }
    } else {
        if (modalTitle) {
            modalTitle.textContent = 'Approve & Complete Course';
        }
        if (modalDesc) {
            modalDesc.textContent = 'Assign course details and mark as completed';
        }
    }
    
    document.getElementById('approvalModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeApprovalModal() {
    document.getElementById('approvalModal').classList.add('hidden');
    document.body.style.overflow = '';
    // Reset form and re-enable fields
    const form = document.getElementById('approvalForm');
    if (form) {
        form.reset();
        // Re-enable all disabled fields
        const disabledFields = form.querySelectorAll('[disabled]');
        disabledFields.forEach(field => {
            field.disabled = false;
        });
    }
    // Reset modal title and description
    const modalTitle = document.getElementById('modal-title');
    const modalDesc = document.getElementById('modalDescription');
    if (modalTitle) {
        modalTitle.textContent = 'Approve & Complete Course';
    }
    if (modalDesc) {
        modalDesc.textContent = 'Assign course details and mark as completed';
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeApprovalModal();
    }
});
</script>