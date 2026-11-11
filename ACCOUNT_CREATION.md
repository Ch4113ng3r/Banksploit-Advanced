# Account Creation - Instructor-Only Feature

**Date:** July 20, 2026  
**Status:** ✅ IMPLEMENTED

## Overview

Account creation functionality has been **removed from the login page** and **moved exclusively to the instructor/admin panel**. Only instructors can now create new participant accounts.

## Changes Made

### 1. **Login Page** (`src/modules/auth/login.php`)
- ❌ Removed "Create account" link from login page
- ✅ Kept "Forgot password?" link only
- Result: Clean, login-only interface for participants

### 2. **Instructor Panel** (`src-superadmin/pages/users.php`)
- ✅ Added "Create New User" form at top of User Management page
- ✅ Form includes fields:
  - Full Name
  - Customer ID (unique identifier, e.g., BS100240)
  - Email
  - Phone
  - Password (instructor sets initial password)
- ✅ Both passwords stored:
  - bcrypt hash (secure, for normal login)
  - MD5 hash (for intentional CTF vulnerability demonstration)
- ✅ New users created as "user" role (not admin)
- ✅ Real-time validation:
  - Prevents duplicate Customer IDs
  - Prevents duplicate emails
  - Requires all fields

## User Access Control

| Feature | Login Page | Participant Login | Instructor Panel |
|---------|-----------|-------------------|------------------|
| View login form | ✅ | N/A | ❌ |
| Forgot password | ✅ | ✅ | N/A |
| Create account | ❌ | ❌ | ✅ |
| Manage users | ❌ | ❌ | ✅ |
| Reset passwords | ❌ | ❌ | ✅ |
| Suspend users | ❌ | ❌ | ✅ |
| Unlock users | ❌ | ❌ | ✅ |

## How Instructors Create Accounts

1. Login to Control Panel: `http://<server>:8888`
2. Username: `instructor`
3. Password: `instructor@123`
4. Navigate to "User Management" → "Users" tab
5. Fill in "Create New User" form at top
6. Click "Create User"
7. New account is immediately active

## Files Modified

```
src/modules/auth/login.php
├── Removed: "Create account" link
└── Result: Login page now only has "Forgot password?" link

src-superadmin/pages/users.php
├── Added: Create New User form (top of page)
├── Added: Backend handler for "create" action
├── Enhanced: JavaScript to handle form submission
└── Result: Instructors can create accounts after login
```

## Database Impact

When an instructor creates a new user:
- `users` table: new row inserted with hashed passwords
- No other tables affected
- Account is immediately usable for login

## Password Requirements

Instructors can set any password. The system stores:
- **bcrypt**: For actual secure login (modern authentication)
- **MD5**: For training/demonstration purposes (intentionally weak, shows hashing flaws)

Example initial password: `Welcome123`

## Security Notes

- ✅ No self-registration allowed
- ✅ Only authenticated instructors can create accounts
- ✅ CSRF protection on create form
- ✅ Input validation on all fields
- ✅ Unique constraints prevent duplicate accounts
- ✅ Default role is "user" (not admin)

## Rollback (if needed)

If you need to re-enable public self-registration:

1. Uncomment the registration link in `src/modules/auth/login.php`
2. Ensure `src/modules/auth/register.php` is accessible

## Testing

```bash
# Login to instructor panel
http://<server>:8888
instructor / instructor@123

# Create test user
- Name: Test User
- Customer ID: BS100999
- Email: test@example.com
- Phone: +91-9999999999
- Password: TestPass123

# Verify user can login to CTF
http://<server>:9443
BS100999 / TestPass123
```
