<?php
// Dark Mode Configuration
// Include this in the <head> section of admin pages to enable dark mode support

// Initialize theme preference if not set
if (!isset($_SESSION['theme_preference'])) {
    $_SESSION['theme_preference'] = 'light';
}

$current_theme = $_SESSION['theme_preference'] ?? 'light';
?>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    primary: {
                        50: '#eff6ff',
                        100: '#dbeafe',
                        200: '#bfdbfe',
                        300: '#93c5fd',
                        400: '#60a5fa',
                        500: '#1e3a8a',
                        600: '#1e40af',
                        700: '#1d4ed8',
                        800: '#1e3a8a',
                        900: '#1e293b'
                    }
                }
            }
        }
    }
</script>
<style>
    /* Dark mode styles */
    .dark body {
        background-color: #1a202c;
        color: #e2e8f0;
    }
    .dark .bg-gray-50 {
        background-color: #1a202c;
    }
    .dark .bg-white {
        background-color: #2d3748;
    }
    .dark .text-gray-900 {
        color: #f7fafc;
    }
    .dark .text-gray-800 {
        color: #e2e8f0;
    }
    .dark .text-gray-700 {
        color: #cbd5e0;
    }
    .dark .text-gray-600 {
        color: #a0aec0;
    }
    .dark .text-gray-500 {
        color: #9ca3af;
    }
    .dark .text-gray-400 {
        color: #9ca3af;
    }
    .dark .border-gray-100 {
        border-color: #4a5568;
    }
    .dark .border-gray-200 {
        border-color: #4a5568;
    }
    .dark .border-gray-300 {
        border-color: #4a5568;
    }
    .dark .bg-gray-100 {
        background-color: #374151;
    }
    .dark .bg-gray-200 {
        background-color: #4b5563;
    }
    .dark .bg-gray-800 {
        background-color: #1f2937;
    }
    .dark .hover\:bg-gray-50:hover {
        background-color: #374151;
    }
    .dark .hover\:bg-gray-100:hover {
        background-color: #4b5563;
    }
    .dark input, .dark select, .dark textarea {
        background-color: #374151;
        border-color: #4a5568;
        color: #f7fafc;
    }
    .dark input:focus, .dark select:focus, .dark textarea:focus {
        background-color: #2d3748;
        border-color: #60a5fa;
    }
    .dark input::placeholder, .dark select::placeholder, .dark textarea::placeholder {
        color: #9ca3af;
    }
    .dark .shadow-xl {
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
    }
    .dark .shadow-lg {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
    }
    .dark table {
        color: #e2e8f0;
    }
    .dark th {
        background-color: #374151;
        color: #f3f4f6;
    }
    .dark td {
        border-color: #4a5568;
        color: #e2e8f0;
    }
    .dark tr:hover {
        background-color: #374151;
    }
    /* Headings in dark mode */
    .dark h1, .dark h2, .dark h3, .dark h4, .dark h5, .dark h6 {
        color: #f7fafc;
    }
    /* Links in dark mode */
    .dark a {
        color: #93c5fd;
    }
    .dark a:hover {
        color: #bfdbfe;
    }
    /* Labels in dark mode */
    .dark label {
        color: #e5e7eb;
    }
    /* Specific text color overrides for better readability */
    .dark .text-blue-600 {
        color: #60a5fa;
    }
    .dark .text-blue-700 {
        color: #93c5fd;
    }
    .dark .text-green-600 {
        color: #34d399;
    }
    .dark .text-green-700 {
        color: #6ee7b7;
    }
    .dark .text-red-600 {
        color: #f87171;
    }
    .dark .text-red-700 {
        color: #fca5a5;
    }
    .dark .text-yellow-600 {
        color: #fbbf24;
    }
    .dark .text-purple-600 {
        color: #a78bfa;
    }
    .dark .text-orange-600 {
        color: #fb923c;
    }
    /* Background colors for colored sections */
    .dark .bg-blue-50 {
        background-color: #1e3a5f;
    }
    .dark .bg-blue-100 {
        background-color: #1e40af;
    }
    .dark .bg-green-50 {
        background-color: #064e3b;
    }
    .dark .bg-green-100 {
        background-color: #065f46;
    }
    .dark .bg-red-50 {
        background-color: #7f1d1d;
    }
    .dark .bg-red-100 {
        background-color: #991b1b;
    }
    .dark .bg-yellow-50 {
        background-color: #78350f;
    }
    .dark .bg-yellow-100 {
        background-color: #92400e;
    }
    .dark .bg-purple-50 {
        background-color: #581c87;
    }
    .dark .bg-purple-100 {
        background-color: #6b21a8;
    }
    .dark .bg-orange-50 {
        background-color: #7c2d12;
    }
    .dark .bg-orange-100 {
        background-color: #9a3412;
    }
    /* Ensure buttons remain readable */
    .dark button {
        color: inherit;
    }
    .dark .bg-blue-600 {
        background-color: #2563eb;
    }
    .dark .bg-green-600 {
        background-color: #059669;
    }
    .dark .bg-red-600 {
        background-color: #dc2626;
    }
    /* Dividers and borders */
    .dark .divide-gray-200 > * + * {
        border-color: #4a5568;
    }
    .dark .border-t {
        border-color: #4a5568;
    }
    .dark .border-b {
        border-color: #4a5568;
    }
    /* Specific fixes for low contrast text */
    .dark .text-sm {
        color: #e5e7eb;
    }
    .dark .text-xs {
        color: #d1d5db;
    }
    .dark p {
        color: #e5e7eb;
    }
    .dark span {
        color: #e5e7eb;
    }
    /* Card headers and titles */
    .dark .font-bold {
        color: #f9fafb;
    }
    .dark .font-semibold {
        color: #f3f4f6;
    }
    .dark .font-medium {
        color: #e5e7eb;
    }
    /* Ensure all text in cards is readable */
    .dark .bg-white * {
        color: #e5e7eb;
    }
    .dark .bg-white h1,
    .dark .bg-white h2,
    .dark .bg-white h3,
    .dark .bg-white h4,
    .dark .bg-white h5,
    .dark .bg-white h6 {
        color: #f9fafb;
    }
    /* Override for specific gray text that's too light */
    .dark .text-gray-400 {
        color: #d1d5db;
    }
    .dark .text-gray-500 {
        color: #d1d5db;
    }
</style>
<script>
    // Apply theme immediately on page load (before DOM ready)
    (function() {
        const theme = '<?php echo $current_theme; ?>';
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
