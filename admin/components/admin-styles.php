<?php
// Common CSS styles for admin panel - Minimal custom CSS, using Tailwind classes
?>

<style>
/* Custom animations not available in Tailwind */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
    from { transform: translateX(-20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes attentionPulse {
    0%, 100% { 
        opacity: 1; 
        transform: scale(1);
    }
    50% { 
        opacity: 0.8; 
        transform: scale(1.05);
    }
}

.animate-attention-pulse {
    animation: attentionPulse 1.5s ease-in-out infinite;
}

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Profile dropdown width - matches sidebar width */
.profile-dropdown-width {
    width: 14rem; /* 224px - matches sidebar width minus padding */
    left: 0.75rem; /* 12px - matches sidebar padding */
    right: 0.75rem; /* 12px - matches sidebar padding */
}

/* Sidebar toggle button - specific styling */
button[onclick="toggleSidebar()"] {
    @apply bg-white bg-opacity-10 rounded w-5 h-5 flex items-center justify-center transition-all duration-200 hover:bg-opacity-20 hover:scale-110;
}

#sidebar-toggle-icon {
    @apply transition-transform duration-300 text-xs;
}
</style>
