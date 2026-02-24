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
            showToast('<?php echo addslashes($success_message); ?>', 'success');
        });
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