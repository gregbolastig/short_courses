<?php
// Header component for admin panel
// This component requires $page_title variable to be set
?>

<!-- Top header -->
<header class="sticky top-0 z-30 bg-white shadow-lg border-b border-gray-200">
    <div class="flex h-16 md:h-20">
        <div class="flex-1 px-3 md:px-6 flex justify-between items-center">
            <div class="flex items-center">
                <!-- Desktop sidebar toggle -->
                <button onclick="toggleSidebar()" class="hidden md:flex mr-4 text-blue-600 hover:text-blue-700 focus:outline-none hover:bg-blue-50 p-2 rounded-md transition-all duration-200 items-center justify-center" title="Toggle Sidebar">
                    <div class="flex items-center" id="sidebar-toggle-icon">
                        <div class="w-1 h-4 bg-current rounded-sm transition-all duration-200"></div>
                        <div class="w-3 h-4 border-2 border-current ml-0.5 rounded-sm transition-all duration-200"></div>
                    </div>
                </button>
                
                <!-- Mobile sidebar toggle -->
                <button onclick="toggleMobileSidebar()" class="md:hidden mr-4 text-blue-600 hover:text-blue-700 focus:outline-none hover:bg-blue-50 p-2 rounded-md transition-colors duration-200">
                    <i class="fas fa-bars text-base"></i>
                </button>
            </div>
            
            <!-- Empty space for future content -->
            <div class="flex-1"></div>
        </div>
    </div>
</header>