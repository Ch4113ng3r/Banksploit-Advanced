# Password Template Guide for User Replacement

## Overview
When users are replaced in the system, they automatically receive a **default password** based on their new Customer ID. This guide explains the template and provides examples.

---

## 🔐 Default Password Template

### Formula
```
{NewCustomerID}B4rc@123
```

### Pattern Breakdown
- **{NewCustomerID}** - The new Customer ID entered during replacement
- **B4rc@123** - Fixed suffix added to all passwords

---

## 📝 Examples

### Format A (Bulk Auto-Match) - Staff Login IDs
| New Customer ID | Default Password | User Type |
|---|---|---|
| G01197938 | G01197938B4rc@123 | Barclays Staff |
| B01182746 | B01182746B4rc@123 | Barclays Staff |
| H98765432 | H98765432B4rc@123 | Barclays Staff |
| E00000001 | E00000001B4rc@123 | Barclays Staff |

### Format B (Bulk Manual) - Custom IDs
| New Customer ID | Default Password | User Type |
|---|---|---|
| STAFF001 | STAFF001B4rc@123 | Staff Code |
| TRAINER002 | TRAINER002B4rc@123 | Trainer ID |
| ADMIN_CTF | ADMIN_CTFB4rc@123 | Special Role |
| TEST_USER_01 | TEST_USER_01B4rc@123 | Test Account |

### Single Mode A (Auto-Match)
| Staff ID | Generated Password |
|---|---|
| G01197938 | G01197938B4rc@123 |
| G00000001 | G00000001B4rc@123 |

### Single Mode B (Manual Selection)
| New Staff ID | Generated Password |
|---|---|
| PARTICIPANT_A | PARTICIPANT_AB4rc@123 |
| CTF_PLAYER_01 | CTF_PLAYER_01B4rc@123 |

---

## 🎯 Usage Scenarios

### Scenario 1: Event Registration
**Event:** CTF Competition Registration  
**Process:** Walk-in staff register one at a time

```
Register: Alice Johnson (Barclays ID: G01197938)
System replaces BS100300 with G01197938
Password: G01197938B4rc@123

Register: Bob Smith (Barclays ID: B01182746)
System replaces BS100301 with B01182746
Password: B01182746B4rc@123
```

### Scenario 2: Bulk Staff Import
**Event:** Import 50 staff members via CSV  
**File:** staff_bulk_import.csv

```
Format A (2 columns):
G01197938,Alice Johnson
B01182746,Bob Smith
H98765432,Charlie Brown

Generated Passwords:
G01197938B4rc@123
B01182746B4rc@123
H98765432B4rc@123
```

### Scenario 3: Manual Override
**Scenario:** Instructor needs to replace specific user for specific participant

```
Select: BS100462 (existing banking user)
Replace with: PARTICIPANT_A
Name: Test Participant A
Password: PARTICIPANT_AB4rc@123
```

---

## 🔒 Security Features

### Password Composition
- ✅ **Mixed Case**: Uppercase letters (G, B, H, E, Staff IDs)
- ✅ **Numbers**: Customer ID digits + numbers (4, 123)
- ✅ **Special Characters**: @ symbol
- ✅ **Length**: Minimum 15+ characters (exceeds minimum requirements)
- ✅ **Not Dictionary**: Pattern not found in standard dictionaries

### Example Analysis: G01197938B4rc@123
```
Part 1: G01197938      (Customer ID - letters + numbers)
Part 2: B4rc           (Memorable phrase - mixed case)
Part 3: @123           (Special char + numbers)
Total:  18 characters
Strength: Strong
```

---

## 📋 Best Practices

### ✅ DO
- ✅ Share the password template with participants BEFORE replacement
- ✅ Inform participants they will receive: `{YourID}B4rc@123`
- ✅ Advise them to change password at first login
- ✅ Keep a backup copy of Customer ID assignments
- ✅ Log all replacements with timestamps

### ❌ DON'T
- ❌ Don't modify the password template
- ❌ Don't share passwords via unsecured channels
- ❌ Don't reuse Customer IDs across events
- ❌ Don't forget to reset passwords after CTF ends

---

## 🛠️ System Implementation Details

### Where Passwords Are Set
When a user is replaced:

1. **Bulk Replace (Format A/B)**
   - User ID from CSV is extracted
   - Password generated: `{UserID}B4rc@123`
   - Both bcrypt hash and MD5 stored in database

2. **Single Replace (Mode A/B)**
   - Staff ID or Customer ID from form is extracted
   - Password generated: `{UserID}B4rc@123`
   - Hashes stored immediately

### Password Storage
```sql
users table:
- password_hash: bcrypt hash ($2y$10$...)
- password_md5: MD5 hash (for legacy compatibility)
```

### Authentication
Users can login with:
- **Username**: Customer ID (e.g., G01197938)
- **Password**: `{CustomerID}B4rc@123`

---

## 📞 Support Information

### Common Questions

**Q: Can I use a custom password instead?**  
A: No. The system auto-generates passwords using the template. Participants change it at first login.

**Q: What if someone forgets their password?**  
A: Admins can reset to the template password by replacing the user again.

**Q: Why is the password the same format for everyone?**  
A: Standardized format ensures consistency and makes it easy for participants to remember their initial password.

**Q: Can participants change their password?**  
A: Yes, they can change it at first login or in their profile settings.

---

## 📊 Password Template Statistics

### Commonly Used Patterns
```
Barclays Prefix:     G, B, H, E
Length:              15-25 characters
Special Chars:       1 (@)
Numbers:             Varies by ID
Upper/Lowercase:     Yes / Yes
Example Strength:    Strong (18 chars)
```

### Entropy Estimation
```
Base: {Variable_Length_ID} + "B4rc@123"
Min: G00000000B4rc@123 (19 chars)
Max: ABC123DEF456GHI789B4rc@123 (30 chars)
Typical: 18-22 characters
```

---

## 🎓 Training Materials

### For Participants
> Your initial login password will follow this pattern:  
> **Your Staff ID + B4rc@123**
>
> Example:
> - Staff ID: G01197938
> - Password: G01197938B4rc@123
>
> Please change this password after your first login.

### For Administrators
> During replacement operations:
> 1. Extract Customer ID from CSV or form
> 2. Password auto-generates: {CustomerID}B4rc@123
> 3. Both bcrypt and MD5 hashes are stored
> 4. User receives confirmation with their new ID
> 5. No need to manually set passwords

### For IT Support
> Password Reset Procedure:
> 1. Go to User Replacement → Mode B (Manual)
> 2. Select the user from dropdown
> 3. Enter new Customer ID (or same ID)
> 4. Click "Replace User"
> 5. Password will be reset to: {CustomerID}B4rc@123

---

## 🔄 Migration Example

### Before Replacement
```
Database:
  customer_id: BS100462
  name: Albina Singh
  password_hash: $2y$10$albina01...
```

### After Replacement (Format A)
```
Input CSV:
  G01197938, Test User

Database Updated:
  customer_id: G01197938
  name: Test User
  password_hash: $2y$10$... (hash of G01197938B4rc@123)
  password_md5: ... (MD5 of G01197938B4rc@123)
```

### Login Credentials
```
Username: G01197938
Password: G01197938B4rc@123
```

---

## 📝 Documentation Link

**Related Files:**
- `VERIFICATION-REPORT.txt` - System state verification
- `FULL-COMPLETION-SUMMARY.txt` - Complete system documentation
- `bulk_replace_users.php` - Implementation code

---

**Last Updated:** 2026-08-09  
**Status:** Production Ready ✓
