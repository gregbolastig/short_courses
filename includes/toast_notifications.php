<!-- Toast Container - Add this to your layout -->
<div id="toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 pointer-events-none"></div>

<script>
/**
 * Standardized Toast Notification System
 * Usage: showToast('Message here', 'success|error|info|warning')
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.warn('Toast container not found');
        return;
    }
    
    const toast = document.createElement('div');
    toast.className = 'transform transition-all duration-300 ease-in-out translate-x-full opacity-0 pointer-events-auto';
    
    // Define colors and icons for each type
    const config = {
        success: {
            bg: 'bg-gradient-to-r from-green-600 to-green-700',
            border: 'border-green-500',
            icon: 'fa-check-circle'
        },
        error: {
            bg: 'bg-gradient-to-r from-red-600 to-red-700',
            border: 'border-red-500',
            icon: 'fa-exclamation-circle'
        },
        warning: {
            bg: 'bg-gradient-to-r from-yellow-600 to-yellow-700',
            border: 'border-yellow-500',
            icon: 'fa-exclamation-triangle'
        },
        info: {
            bg: 'bg-gradient-to-r from-blue-600 to-blue-700',
            border: 'border-blue-500',
            icon: 'fa-info-circle'
        }
    };
    
    const style = config[type] || config.info;
    
    toast.innerHTML = `
        <div class="${style.bg} text-white px-6 py-4 rounded-lg shadow-2xl border ${style.border} flex items-center space-x-3 min-w-[320px] max-w-md">
            <i class="fas ${style.icon} text-xl flex-shrink-0"></i>
            <span class="flex-1 font-medium text-sm">${escapeHtml(message)}</span>
            <button onclick="removeToast(this)" class="text-white hover:text-gray-200 transition flex-shrink-0 ml-2 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Trigger slide-in animation from right
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    }, 10);
    
    // Auto remove after 5 seconds
    const autoRemoveTimeout = setTimeout(() => {
        removeToastElement(toast);
    }, 5000);
    
    // Store timeout ID for manual removal
    toast.dataset.timeoutId = autoRemoveTimeout;
}

function removeToast(button) {
    const toast = button.closest('.transform');
    if (toast) {
        // Clear auto-remove timeout
        if (toast.dataset.timeoutId) {
            clearTimeout(parseInt(toast.dataset.timeoutId));
        }
        removeToastElement(toast);
    }
}

function removeToastElement(toast) {
    toast.classList.add('translate-x-full', 'opacity-0');
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 300);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Backward compatibility aliases
function showSuccessToast(message) {
    showToast(message, 'success');
}

function showErrorToast(message) {
    showToast(message, 'error');
}

function showInfoToast(message) {
    showToast(message, 'info');
}

function showWarningToast(message) {
    showToast(message, 'warning');
}
</script>
