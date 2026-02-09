# Fix: Student Course History Data Separation

## Problem
When viewing a student's course history in `student/profile/profile.php`, only the **last course** was shown with complete data. The training dates and adviser information were being overwritten with the latest course's data instead of displaying separate data for each course.

## Root Cause
The profile page was fetching training data from the `students` table, which only has **one set** of these fields:
- `training_start`
- `training_end`
- `adviser`
- `nc_level`

These fields can only store data for ONE course at a time. When a student completes multiple courses, each new course would overwrite these values.

## Solution
Updated the profile page to fetch training data from the `course_applications` table instead, where **each course application has its own separate record** with its own training data.

### Files Modified
**student/profile/profile.php** - Lines 137-176

### Before (Incorrect)
```php
if ($app['status'] === 'completed') {
    $app_status = 'completed';
    $completion_date = $app['reviewed_at'];
    // WRONG: Getting data from students table (only has one set of values)
    $training_start = $student_profile['training_start'] ?? null;
    $training_end = $student_profile['training_end'] ?? null;
    $adviser = $student_profile['adviser'] ?? 'Not Assigned';
} elseif ($app['status'] === 'approved') {
    $app_status = 'enrolled';
    // WRONG: Getting data from students table
    $training_start = $student_profile['training_start'] ?? null;
    $training_end = $student_profile['training_end'] ?? null;
    $adviser = $student_profile['adviser'] ?? 'Not Assigned';
}
```

### After (Correct)
```php
// Get training data from course_applications table (each course has its own data)
$training_start = $app['training_start'] ?? null;
$training_end = $app['training_end'] ?? null;
$adviser = $app['adviser'] ?? 'Not Assigned';

if ($app['status'] === 'completed') {
    $app_status = 'completed';
    $completion_date = $app['reviewed_at'];
    // Training data is already in $app from course_applications table
} elseif ($app['status'] === 'approved') {
    $app_status = 'enrolled';
    // Training data is in course_applications, not students table
}
```

## How It Works Now

1. **Course applications are fetched** from `course_applications` table
2. **For each course application**, the code extracts:
   - `training_start` from `ca.training_start`
   - `training_end` from `ca.training_end`
   - `adviser` from `ca.adviser`
   - `nc_level` from `ca.nc_level`

3. **Each course gets its own separate data** stored in the array
4. **Multiple courses display correctly** with their individual training information

## Database Schema
The `course_applications` table now stores complete course information:
```sql
CREATE TABLE course_applications (
    application_id INT PRIMARY KEY,
    student_id INT,
    course_id INT,
    nc_level VARCHAR(10),
    training_start DATE,        -- Each course has its own dates
    training_end DATE,          -- Each course has its own dates
    adviser VARCHAR(255),       -- Each course has its own adviser
    status ENUM('pending', 'approved', 'rejected', 'completed'),
    reviewed_at TIMESTAMP,
    reviewed_by INT,
    ...
);
```

## Example Scenario

**Student: John Doe**

**Course 1: Web Development (NC III)**
- Training: Jan 10, 2026 - Feb 20, 2026
- Adviser: Mark Anthony R. Guerrero
- Status: Completed

**Course 2: Database Management (NC II)**
- Training: Mar 1, 2026 - Apr 15, 2026
- Adviser: Maria Santos
- Status: Enrolled

**Course 3: Python Programming (NC I)**
- Training: Not yet assigned
- Adviser: Not yet assigned
- Status: Pending

Now each course displays with its **own independent data**, not just the latest course's information.

## Impact
✅ Student profile correctly displays **full course history**
✅ Each course has **separate training dates and adviser**
✅ **No data overwriting** when students take multiple courses
✅ **Complete audit trail** of all course enrollments

## Notes
- Legacy support (from students table) is kept for backward compatibility
- New courses should always be created via `course_applications` table
- The `students` table fields are now primarily for admin reference only
