# Bulk User Import Feature — Implementation Summary

## Overview

Added a bulk user import feature to the **CTF Control Panel** (Instructor Portal on port 8888) that allows instructors to create multiple user accounts at once from CSV or Excel files.

## Files Changed

### 1. **src-superadmin/pages/bulk_import.php** (NEW)
   - Main bulk import page for the instructor portal (GET + POST)
   - Accepts CSV (`.csv`) and Excel (`.xlsx`) uploads
   - Processes files and creates users in bulk
   - Displays import results with detailed success/failure reporting
   - Auto-generates passwords: `{customerid}B4rc@123`
   - Auto-generates emails and placeholder phone numbers
   - Functions:
     - `process_bulk_import()` — orchestrates the import flow
     - `parse_csv()` — parses CSV files
     - `parse_excel()` — parses Excel (.xlsx) files via ZipArchive + XML parsing
   - Responsive UI with light/dark mode support

### 2. **src-superadmin/templates/layout.php** (MODIFIED)
   - **Line 15**: Added "Bulk Import" navigation item:
     ```php
     ['path' => '/bulk-import',   'label' => 'Bulk Import',   'icon' => '📥', 'key' => 'bulk-import'],
     ```
   - Adds "Bulk Import" menu item in the CTF Control Panel sidebar

### 3. **src-superadmin/index.php** (MODIFIED)
   - **Line 139**: Added routing for bulk import:
     ```php
     '/bulk-import'        => 'bulk_import.php',
     ```

### 4. **README.md** (MODIFIED)
   - **Lines 150-195**: Added comprehensive "Instructor Portal — Bulk User Import" section with:
     - Updated to reference port 8888 (CTF Control Panel)
     - File format requirements
     - CSV example with sample file reference
     - Auto-generated field specifications
     - Usage instructions
     - Important notes on duplicates and conflicts
     - Password template: `{customerid}B4rc@123`

### 5. **samples/bulk_import_sample.csv** (EXISTING)
   - Sample CSV file for instructors to use as a template
   - Contains 10 example users with full names and customer IDs
   - File format: `Full Name,Customer ID`

## Feature Specifications

### Required Columns
| Column      | Type   | Description                    |
|-------------|--------|--------------------------------|
| Full Name   | String | User's full name (required)    |
| Customer ID | String | Unique identifier (required)   |

### Auto-Generated Data
| Field      | Format                      | Example                          |
|------------|-----------------------------|---------------------------------|
| Password   | `{customerid}B4rc@123`      | `BS100001B4rc@123`               |
| Email      | `{name}.{custid}@...local`  | `john.doe.BS100001@banksploit.local` |
| Phone      | Placeholder                 | `9999999999`                     |
| Role       | Fixed                       | `user` (always)                  |

### Supported File Formats
- CSV (`.csv`) — parsed with PHP's native `fgetcsv()`
- Excel (`.xlsx`) — parsed via ZipArchive + XML (no external dependencies)

### Input Validation
- Full Name: Required, non-empty
- Customer ID: Required, non-empty, must be unique
- Email conflicts resolved with timestamp suffix
- Duplicate customer IDs rejected

### Security & Logging
- CSRF token validation on form submission
- All user creations logged in audit trail
- Imports are admin-guarded (requires login, role enforced at level 3)
- Passwords are bcrypt hashed (PASSWORD_BCRYPT)

### Error Handling
- File upload failures reported
- Empty files rejected
- Parsing errors caught and reported
- Per-row error messages displayed to instructor
- Results table shows success/failure for each row

## Usage Workflow

1. Log in as instructor/admin
2. Navigate to **Admin → Bulk Import** (or click from admin dashboard)
3. Prepare CSV/Excel file with columns: Full Name, Customer ID
4. Upload file
5. Review import results (success count, failure count, per-row details)
6. Users are created immediately upon import success
7. Check Audit Log for import records

## Password Template Reference

**Password Format:** `{customerid}B4rc@123`

This template allows instructors to easily share passwords with participants:
- Easy to remember (customer ID + fixed suffix)
- Participants can derive their own password
- Example: Customer ID `BS100001` → Password `BS100001B4rc@123`

Instructors should document this template when distributing login credentials.

## Notes

- All imported users are created with role `user` (regular participants)
- Instructors cannot mass-import admin accounts through this interface
- Phone numbers are set to placeholder `9999999999`
- Import is transaction-safe per row (failures don't affect other rows)
- No batch delete — users can be managed individually via admin dashboard
