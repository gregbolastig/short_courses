<?php
/**
 * Navigation Component
 * Simple navigation links for student pages
 */

$nav_links = $nav_links ?? [
    ['url' => '../index.php', 'text' => 'Back to Portal', 'icon' => 'fas fa-arrow-left'],
    ['url' => 'register.php', 'text' => 'Register', 'icon' => 'fas fa-user-plus']
];
?>

<?php if (!empty($nav_links)): ?>
<nav class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <div class="flex space-x-4">
                <?php foreach ($nav_links as $link): ?>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors duration-200">
                    <i class="<?php echo htmlspecialchars($link['icon']); ?> mr-2"></i>
                    <?php echo htmlspecialchars($link['text']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>