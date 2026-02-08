# Student Reapplication Pre-fill Feature

## Overview
This feature enables automatic pre-filling of the approval modal when an admin approves a student's reapplication for a new course. When a student has already completed a course and reapplies, the training data from their first course approval is automatically displayed in the completion approval modal.

## Workflow Explanation

### Stage 1: Course Application Approval (First Step)
1. Student submits a **new course application**
2. Admin reviews the application in `review_course_application.php`
3. Admin **approves** the application and fills in:
   - Course
   - NC Level
   - Training Start Date
   - Training End Date
   - Adviser
4. **All this data is saved to the `course_applications` table** (in addition to `students` table)
5. Student status becomes: `approved`
6. Application status becomes: `approved`

### Stage 2: Course Completion Approval (Second Step)
1. Admin goes to Dashboard and sees "Approved Applications" section
2. Admin clicks **"Approve"** button for the student
3. The approval modal opens with **pre-filled data**:
   - Course (from `course_applications.course_id`)
   - NC Level (from `course_applications.nc_level`)
   - Training Start Date (from `course_applications.training_start`)
   - Training End Date (from `course_applications.training_end`)
   - Adviser (from `course_applications.adviser`)
4. All fields are **disabled** (read-only) to prevent changes
5. Admin clicks **"Approve & Complete"**
6. Student status becomes: `completed`
7. Application status becomes: `completed`

## Database Changes

### New Columns Added to `course_applications` Table
```sql
ALTER TABLE course_applications ADD COLUMN training_start DATE NULL AFTER nc_level;
ALTER TABLE course_applications ADD COLUMN training_end DATE NULL AFTER training_start;
ALTER TABLE course_applications ADD COLUMN adviser VARCHAR(255) NULL AFTER training_end;
```

## Files Modified

### 1. **config/database.php**
- No changes needed (migration handled separately)

### 2. **admin/review_course_application.php**
**Changed**: Course application approval to save training data to `course_applications` table

**Before:**
```php
UPDATE course_applications SET 
    status = 'approved',
    course_id = :course_id,
    nc_level = :nc_level,
    reviewed_by = :admin_id,
    reviewed_at = NOW(),
    notes = :notes
    WHERE application_id = :id
```

**After:**
```php
UPDATE course_applications SET 
    status = 'approved',
    course_id = :course_id,
    nc_level = :nc_level,
    training_start = :training_start,      // NEW
    training_end = :training_end,          // NEW
    adviser = :adviser,                     // NEW
    reviewed_by = :admin_id,
    reviewed_at = NOW(),
    notes = :notes
    WHERE application_id = :id
```

### 3. **admin/dashboard.php**
**Changed**: Fetch training data from `course_applications` when displaying approved applications

**Before:**
```php
SELECT ca.application_id, ca.student_id, ca.course_id, ca.nc_level, ca.reviewed_at as approved_at,
       s.uli, s.first_name, s.last_name, s.email,
       c.course_name as course
FROM course_applications ca
```

**After:**
```php
SELECT ca.application_id, ca.student_id, ca.course_id, ca.nc_level, ca.reviewed_at as approved_at,
       ca.training_start, ca.training_end, ca.adviser,  // NEW
       s.uli, s.first_name, s.last_name, s.email,
       c.course_name as course
FROM course_applications ca
```

**Changed**: Pass training data to approval modal for reapplications

**Before:**
```php
onclick='openApprovalModal(<?php echo (int)$student['student_id']; ?>, 
                          <?php echo json_encode($student['first_name'] . ' ' . $student['last_name']); ?>, 
                          <?php echo (int)($student['course_id'] ?? 0); ?>, 
                          <?php echo json_encode($student['nc_level'] ?? ''); ?>, 
                          <?php echo json_encode(''); ?>,  // empty adviser
                          <?php echo json_encode(''); ?>,  // empty training_start
                          <?php echo json_encode(''); ?>)' // empty training_end
```

**After:**
```php
onclick='openApprovalModal(<?php echo (int)$student['student_id']; ?>, 
                          <?php echo json_encode($student['first_name'] . ' ' . $student['last_name']); ?>, 
                          <?php echo json_encode($student['course_id']); ?>, 
                          <?php echo json_encode($student['nc_level'] ?? ''); ?>, 
                          <?php echo json_encode($student['adviser'] ?? ''); ?>,           // FROM DB
                          <?php echo json_encode($student['training_start'] ?? ''); ?>,  // FROM DB
                          <?php echo json_encode($student['training_end'] ?? ''); ?>)'   // FROM DB
```

### 4. **admin/components/admin-scripts.php**
**No changes needed** - The `openApprovalModal()` function already supports pre-filling with all parameters!

The function already handles:
- Detecting if data is provided (for reapplications)
- Filling in all form fields
- Disabling fields when data is provided (read-only mode)
- Updating modal title and description based on whether it's an initial approval or completion approval

## New Files Created

### 1. **config/add_training_fields_to_course_applications.php**
Migration script to add the three new columns to the `course_applications` table.

Run this file once to apply the database changes:
```
URL: http://localhost/JZGMSAT/config/add_training_fields_to_course_applications.php
```

### 2. **config/REAPPLICATION_SETUP.php**
Setup documentation file that runs the migration.

## Setup Instructions

### Step 1: Apply Database Migration
Run the migration script to add the new columns:
```
1. Navigate to: http://localhost/JZGMSAT/config/add_training_fields_to_course_applications.php
2. You should see: "✓ Migration completed successfully!"
3. Or if columns already exist: "✓ No changes needed - all columns already exist."
```

### Step 2: No Code Changes Required
All code changes are already in place:
- ✅ `admin/review_course_application.php` - saves training data
- ✅ `admin/dashboard.php` - fetches and passes training data
- ✅ `admin/components/admin-scripts.php` - pre-fills modal

### Step 3: Test the Feature
1. Create or use an existing student who has completed a course
2. Have that student apply for a new course
3. Approve the application with training data
4. Go to Dashboard > "Approved Applications"
5. Click "Approve" button for the student
6. Verify that the modal shows pre-filled training data
7. All fields should be disabled (read-only)

## Benefits

✅ **Saves Time**: Admins don't need to re-enter the same training data for reapplications
✅ **Reduces Errors**: Pre-filled data prevents accidental changes to training information
✅ **Better UX**: Clear visual indication that data is read-only during completion approval
✅ **Data Integrity**: All approval data is stored in `course_applications` for audit trail
✅ **Backward Compatible**: Existing code continues to work, new feature is additive

## Troubleshooting

### Issue: Modal fields are empty when approving a reapplication
**Solution**: 
- Ensure the migration was run successfully
- Check that `course_applications` table has the three new columns
- Verify the student's approved application record has the training data saved

### Issue: Fields are editable instead of disabled
**Solution**: 
- This might be intentional if you want to allow changes
- To make them read-only, the `openApprovalModal()` function already disables fields when data is provided
- Check that all parameters are being passed correctly

### Issue: Course not showing in the pre-filled modal
**Solution**: 
- Course ID is being passed, not course name
- The modal dropdown should automatically select the correct course ID
- Check browser console for any JavaScript errors

## Future Enhancements

1. **Form Validation**: Add date validation to ensure training_end > training_start
2. **Visual Indicators**: Add badges to show "Previously Approved" for reapplications
3. **Edit Option**: Allow admins to edit training data before completion if needed
4. **Audit Trail**: Log all changes to training data in system activity logger
5. **Email Notifications**: Notify student when reapplication is approved/rejected

## Database Schema (Updated)

```sql
CREATE TABLE course_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    nc_level VARCHAR(10) NULL,
    training_start DATE NULL,          -- NEW: For pre-filling reapplications
    training_end DATE NULL,             -- NEW: For pre-filling reapplications
    adviser VARCHAR(255) NULL,          -- NEW: For pre-filling reapplications
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    notes TEXT NULL,
    enrollment_created BOOLEAN DEFAULT FALSE,
    enrollment_id INT NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    ...
);
```

## Contact & Support

For questions or issues related to this feature, please check:
1. Application logs in `includes/system_activity_logger.php`
2. Database error messages in browser console
3. PHP error logs in Apache/XAMPP configuration
