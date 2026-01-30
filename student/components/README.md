# Student Portal Components

This directory contains reusable components for the student portal pages.

## Components

### header.php
Standard header for student portal pages.

**Variables:**
- `$page_title` - Page title (default: "JZGMSAT Student Portal")
- `$page_subtitle` - Subtitle (default: "Student Registration System")
- `$page_description` - Optional description text
- `$show_logo` - Show logo icon (default: true)

### register-header.php
Special header for registration form with different styling.

**Variables:**
- `$page_title` - Page title (default: "Student Registration")
- `$show_logo` - Show logo image (default: true)

### footer.php
Standard footer with contact information and optional JavaScript.

**Variables:**
- `$include_search_js` - Include search tab JavaScript (default: false)

### alerts.php
Alert messages component for displaying errors, success, and info messages.

**Variables:**
- `$errors` - Array of error messages
- `$success_message` - Success message string
- `$info_message` - Info message string

### navigation.php
Simple navigation component with customizable links.

**Variables:**
- `$nav_links` - Array of navigation links with format:
  ```php
  [
      ['url' => 'link.php', 'text' => 'Link Text', 'icon' => 'fas fa-icon']
  ]
  ```

## API Integration

### api-utils.js
Shared JavaScript utilities for API integration.

**APIs Used:**
- **PSGC API**: Philippine provinces, cities, and barangays
- **REST Countries API**: Country codes for phone numbers

**Functions:**
- `loadProvinces(selectId, selectedValue)` - Load provinces into dropdown
- `loadCities(provinceCode, selectId, selectedValue)` - Load cities for province
- `loadBarangays(cityCode, selectId, selectedValue)` - Load barangays for city
- `loadCountryCodes(selectId, selectedValue)` - Load country codes
- `setupAddressCascade(provinceId, cityId, barangayId)` - Setup cascading dropdowns

**Features:**
- Response caching for better performance
- Loading states and error handling
- Form value persistence
- Cascading dropdown relationships

### api-info.md
Comprehensive documentation of all APIs used in the system.

## Usage Example

```php
<?php
// Set page variables
$page_title = 'My Page';
$page_subtitle = 'Page Description';
$errors = ['Error message'];
$success_message = 'Success!';

// Include components
include 'components/header.php';
include 'components/alerts.php';
?>

<!-- Your page content here -->

<?php include 'components/footer.php'; ?>
```

## API Usage Example

```html
<!-- Include API utilities -->
<script src="components/api-utils.js"></script>

<script>
// Load provinces into dropdown
APIUtils.loadProvinces('province-select');

// Setup cascading address dropdowns
APIUtils.setupAddressCascade('province', 'city', 'barangay');

// Load country codes
APIUtils.loadCountryCodes('country-code-select');
</script>
```