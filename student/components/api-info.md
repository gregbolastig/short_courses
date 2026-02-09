# API Integration Documentation

This document outlines the APIs used in the Jacobo Z. Gonzales Memorial School of Arts and Trades Student Portal for dynamic data loading.

## APIs Currently Implemented

### 1. Philippine Statistics Authority Geographic Code (PSGC) API
**Base URL**: `https://psgc.gitlab.io/api`

**Purpose**: Provides official Philippine geographic data for provinces, cities/municipalities, and barangays.

**Endpoints Used**:
- `GET /provinces/` - Retrieves all provinces
- `GET /provinces/{provinceCode}/cities-municipalities/` - Retrieves cities/municipalities for a specific province
- `GET /cities-municipalities/{cityCode}/barangays/` - Retrieves barangays for a specific city/municipality

**Used In**:
- Student registration form (address fields)
- Student search form (place of birth fields)
- All location-based dropdowns

**Data Structure**:
```json
{
  "code": "012345678901",
  "name": "Province/City Name",
  "oldName": "Previous Name (if any)",
  "islandGroupCode": "1",
  "psgc10DigitCode": "1234567890"
}
```

### 2. REST Countries API
**Base URL**: `https://restcountries.com/v3.1`

**Purpose**: Provides country information including international dialing codes for phone number validation.

**Endpoints Used**:
- `GET /all?fields=name,idd,flag` - Retrieves all countries with name, international dialing code, and flag

**Used In**:
- Student registration form (country code dropdowns for phone numbers)
- Parent/guardian contact information

**Data Structure**:
```json
{
  "name": {
    "common": "Philippines",
    "official": "Republic of the Philippines"
  },
  "idd": {
    "root": "+6",
    "suffixes": ["3"]
  },
  "flag": "🇵🇭"
}
```

## Implementation Details

### Loading States
- Loading spinners are shown while API data is being fetched
- Error handling displays user-friendly messages if APIs are unavailable
- Fallback options available for offline scenarios

### Data Persistence
- Selected values are maintained during form validation errors
- Province/city relationships are preserved when forms are resubmitted
- Search form remembers user selections

### Performance Optimizations
- API calls are made only when needed (lazy loading)
- Province data is cached after first load
- City data is loaded dynamically based on province selection
- Barangay data is loaded only when city is selected

### Error Handling
- Network errors are caught and logged
- User-friendly error messages are displayed
- Graceful degradation when APIs are unavailable
- Retry mechanisms for failed requests

## Security Considerations

### Data Validation
- All API responses are validated before use
- User input is sanitized before API calls
- HTTPS endpoints are used for all API communications

### Rate Limiting
- API calls are debounced to prevent excessive requests
- Caching reduces redundant API calls
- Progressive loading prevents overwhelming the APIs

## Future Enhancements

### Potential Additions
1. **Offline Support**: Cache API data locally for offline use
2. **Search Optimization**: Add search/filter functionality to dropdowns
3. **Data Updates**: Implement periodic updates for geographic data
4. **Alternative APIs**: Add fallback APIs for redundancy

### Performance Improvements
1. **CDN Integration**: Use CDN for faster API responses
2. **Preloading**: Preload common provinces/cities
3. **Compression**: Implement response compression
4. **Pagination**: Add pagination for large datasets

## Troubleshooting

### Common Issues
1. **Slow Loading**: Check internet connection and API status
2. **Empty Dropdowns**: Verify API endpoints are accessible
3. **Missing Data**: Check for API response format changes
4. **Form Errors**: Ensure all required fields are selected

### Debug Information
- Console logs show API call status and responses
- Network tab in browser dev tools shows API request details
- Error messages provide specific failure information