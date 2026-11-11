# Password Login Issue - Troubleshooting Guide

## Issue
User tried to login with:
- **Customer ID:** G01197938
- **Password:** G01197938B4rc@123
- **Result:** ❌ Login failed

---

## Root Cause Analysis

### Finding 1: User Exists in Database
- ✓ Customer ID: G01197938
- ✓ Name: Ankit Rai
- ✓ Email: aditya.agarwal.BS000400@banksploit.local
- ✓ Account Status: Not locked, not suspended

### Finding 2: User is Legacy/Pre-existing
- Created: 2026-05-10 (pre-existing in database)
- NOT created through replacement system
- `replacement_status` = NULL (not tracked)
- Email format suggests imported from old data

### Finding 3: Password Template Doesn't Apply
The password template formula `{CustomerID}B4rc@123` **only applies** to:
- Users created through Format A (bulk auto-match)
- Users created through Format B (bulk manual)
- Users created through Mode A (single auto-match)
- Users created through Mode B (single manual)

**G01197938 is a legacy user, so the template password does NOT apply.**

---

## Solution: Re-register the User

### Step 1: Access Replace User Interface
Navigate to:
```
http://127.0.0.1:8888/bulk-replace-users?tab=single&mode=manual
```

### Step 2: Select Mode B (Manual Replace)
This allows us to "re-register" the existing user with the new password system.

### Step 3: Fill in the Form
```
Select User to Replace:     G01197938 (Ankit Rai)
New Customer ID (Staff ID): G01197938  (keep same)
New Full Name:              Ankit Rai
```

### Step 4: Click "Replace User"
The system will:
- Keep the same customer ID: G01197938
- Keep the same name: Ankit Rai
- Generate NEW password: **G01197938B4rc@123**
- Update the database with new password hash

### Step 5: Try Login Again
Now use:
- **Username:** G01197938
- **Password:** G01197938B4rc@123
- **Result:** ✅ Should work!

---

## Why This Happens

### Legacy Users (Pre-existing in DB)
These users were either:
- Imported from old system
- Created before replacement system was set up
- Manually added to database
- Created for testing/demo purposes

Their passwords were set at creation time, NOT using the template formula.

### Replacement System Users (BS1003xx, BS1004xx)
These users:
- Were created through the replacement system
- Have passwords following: `{CustomerID}B4rc@123`
- Have `replacement_status` tracked in database
- Can be identified by their replacement status

---

## How to Identify User Type

### Check Replacement Status
```sql
SELECT 
    customer_id,
    name,
    replacement_status,
    created_at
FROM users
WHERE customer_id = 'G01197938';
```

**Result:**
- `replacement_status = NULL` → Legacy user (template doesn't apply)
- `replacement_status = 'available'` → Replacement system user (template applies)
- `replacement_status = 'replaced'` → Already replaced (template applies)

---

## Testing with Known Users

### Try These Users (From Replacement System)
These have the template password `{CustomerID}B4rc@123`:

```
BS100300  →  BS100300B4rc@123
BS100301  →  BS100301B4rc@123
BS100400  →  BS100400B4rc@123
BS100401  →  BS100401B4rc@123
```

All should work ✓

---

## Permanent Fix for G01197938

### Option A: One-Time Fix (Recommended)
1. Go to Replace User Mode B
2. Select G01197938
3. Re-enter with same ID and name
4. Password resets to `G01197938B4rc@123`
5. Done!

### Option B: Bulk Fix (If Multiple Legacy Users)
Create CSV for Mode B:
```csv
Old Customer ID,New Customer ID,New Full Name
G01197938,G01197938,Ankit Rai
```

Upload and all legacy users get template passwords.

### Option C: Direct Database Update (Admin Only)
```php
// Generate bcrypt hash for password G01197938B4rc@123
$password = 'G01197938B4rc@123';
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$passwordMd5 = md5($password);

// Update database
UPDATE users 
SET password_hash = '$passwordHash',
    password_md5 = '$passwordMd5',
    replacement_status = 'replaced'
WHERE customer_id = 'G01197938';
```

---

## Documentation Links

### Password Template
- **Guide:** `PASSWORD-TEMPLATE-GUIDE.md`
- **Quick Ref:** `PASSWORD-QUICK-REFERENCE.txt`
- **Implementation:** `PASSWORD-TEMPLATE-IMPLEMENTATION.md`

### User Replacement
- **Bulk Tab:** Format A and B CSV uploads
- **Mode A:** Auto-match next available slot
- **Mode B:** Manual select any user (recommended for legacy users)

---

## Prevention

### For New Users
Always create users through the replacement system:
- Use Mode A (auto-match) for walk-in staff
- Use Mode B (manual) for specific assignments
- This ensures passwords follow the template formula

### For Existing Legacy Users
Re-register them through Mode B to:
- Set template password
- Mark with `replacement_status`
- Enable consistent password management

---

## Summary

| Aspect | Legacy User (G01197938) | Replacement System User (BS100300) |
|--------|--------------------------|-------------------------------------|
| Password Template | ❌ Does NOT apply | ✅ Applies |
| Initial Password | Set at creation time | `{CustomerID}B4rc@123` |
| Replacement Status | NULL | available / replaced |
| Can Login? | Yes (different password) | Yes (template password) |
| How to Fix | Re-register in Mode B | Password already correct |

---

## Action Items

- [ ] Go to Replace User → Mode B
- [ ] Select: G01197938
- [ ] Enter: G01197938 (same ID)
- [ ] Enter: Ankit Rai (same name)
- [ ] Click: Replace User
- [ ] Try login with: G01197938B4rc@123
- [ ] Confirm success ✓

---

**Status:** Issue Identified and Solution Provided  
**Last Updated:** 2026-08-09
