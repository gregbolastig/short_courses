<?php
/**
 * Alert Messages Component
 * Displays error and success messages
 */

// Display error messages
if (!empty($errors)): ?>
    <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-slide-up">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                <div class="mt-2 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Display success message as toast notification
if (!empty($success_message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showSuccessToast('<?php echo addslashes($success_message); ?>');
        });
        
        function showSuccessToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 translate-y-[-20px]';
            toast.innerHTML = `
                <div class="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-4 rounded-lg shadow-2xl border border-green-500 max-w-md">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold">Success!</h3>
                            <p class="text-sm text-green-100 mt-1">${message}</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('opacity-100', 'translate-y-0');
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(-50%) translateY(0)';
            }, 10);
            
            // Remove toast after 4 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(-50%) translateY(-20px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    </script>
<?php endif; ?>

<?php
// Display info message
if (!empty($info_message)): ?>
    <div class="mb-6 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg animate-slide-up">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Information</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <?php echo htmlspecialchars($info_message); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>