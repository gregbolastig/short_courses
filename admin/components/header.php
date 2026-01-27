<?php
// Header component for admin panel
// This component requires $page_title variable to be set
// Optional: $breadcrumb_items array for breadcrumb navigation
?>

<!-- Top header -->
<header class="sticky top-0 z-30 bg-white shadow-lg border-b border-gray-200">
    <div class="flex flex-col">
        <!-- Top bar -->
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
        
        <!-- Breadcrumb section -->
        <?php if (isset($breadcrumb_items) && !empty($breadcrumb_items)): ?>
        <div class="px-3 md:px-6 py-2 bg-gray-50 border-t border-gray-100">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <?php foreach ($breadcrumb_items as $index => $item): ?>
                        <li class="inline-flex items-center">
                            <?php if ($index > 0): ?>
                                <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <?php endif; ?>
                            
                            <?php if (isset($item['url']) && $item['url']): ?>
                                <a href="<?php echo $item['url']; ?>" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">
                                    <?php if (isset($item['icon'])): ?>
                                        <i class="<?php echo $item['icon']; ?> mr-2"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-sm font-medium text-blue-600">
                                    <?php if (isset($item['icon'])): ?>
                                        <i class="<?php echo $item['icon']; ?> mr-2"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</header>