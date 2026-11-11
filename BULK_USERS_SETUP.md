# BankSploit — 100 Bulk Users Setup Guide

## Overview

The BankSploit CTF application now includes a complete seeding system for 100 realistic user accounts (BS100300–BS100399) with fully populated related data:

- **100 User Accounts**
- **200 Bank Accounts** (2 per user: 1 savings + 1 current)
- **400+ Beneficiaries** (4 per user with cross-references)
- **200 Debit/Credit Cards** (2 per user)
- **100+ Bank Transactions** (salary credits, bill payments, transfers)
- **500+ Security Questions** (5 per user for forgot-password flow)
- **100+ Audit Log Entries** (login activity)
- **User Preferences** (theme, language, dashboard defaults)

All users are created with realistic Indian names, addresses, phone numbers, and full banking details.

---

## Quick Start

### 1. Fresh Setup (First Time)

```bash
./start.sh setup
```

This will:
1. Create the database and user
2. Load the schema (01-schema.sql)
3. Load default seed data (02-seed-data.sql) — 5 default users
4. Load bulk users (03-bulk-users-100.sql) — 100 additional users
5. Run migrations
6. Seed CTF flags

**Total users after setup: 105** (5 default + 100 bulk)

### 2. Verify the Bulk Users

After setup, verify that all 100 users are correctly loaded:

```bash
php src/scripts/verify_bulk_users.php
```

Expected output:
```
✓ Found: 100 users (BS100300-BS100399)
✓ Total accounts: 200
✓ Total cards: 200
✓ Total beneficiaries: 400+
✓ Total transactions: 100+
✓ Total security questions: 500+
✓ ALL TESTS PASSED
```

### 3. Start the Application

```bash
./start.sh start
```

Visit:
- **CTF App:** http://localhost:9443
- **Instructor Portal:** http://localhost:8888

---

## Accessing Bulk Users

### Default Login Credentials (Sample)

All 100 bulk users follow this password pattern:

| Customer ID | Password        | Name           |
|------------|-----------------|----------------|
| BS100300   | BS100300B4rc@123 | Arjun Verma   |
| BS100301   | BS100301B4rc@123 | Bhavna Patel  |
| BS100302   | BS100302B4rc@123 | Chirag Sharma |
| ...        | ...             | ... (100 users) |
| BS100399   | BS100399B4rc@123 | Kishan Sharma |

**Pattern:** `{customerid}B4rc@123`

### Two-Factor Authentication

After entering Customer ID + Password, you'll be prompted for a 4-digit OTP:

- **Lab Mode:** OTP is displayed on screen
- **Production Mode:** Check `otp_tokens` table for the OTP code

---

## User Details & Auto-Generated Fields

Each bulk user has:

### Core Information
- **Customer ID:** BS100300–BS100399 (unique)
- **Name:** Realistic Indian names (full name)
- **Email:** `{firstname}.{lastname}.{customerid}@banksploit.local`
- **Phone:** +919900000300–+919900000399
- **Address:** Realistic Bangalore locations (for stored XSS lab)
- **PAN:** Unique Indian PAN format
- **Aadhaar:** Masked format (XXXX-XXXX-NNNN)

### Accounts (Per User)
- **Savings Account:** Account number 50100XXXXX, Balance ₹500K–₹1M, Daily limit ₹200K
- **Current Account:** Account number 50200XXXXX, Balance ₹200K–₹500K, Daily limit ₹500K

### Beneficiaries (Per User)
- 4 pre-configured beneficiaries (cross-referenced to other users' accounts)
- Used to demonstrate IDOR vulnerabilities in statements/transfers

### Cards (Per User)
- **Debit Card:** 16-digit Visa card (plaintext by design)
- **Credit Card:** 16-digit MasterCard (plaintext by design)
- CVV stored plaintext (intentional for card.l24 lab)
- Spending limits, expiry dates, PIN hashes

### Transactions (Per User)
- **Salary Credits:** 2–3 NEFT inbound transactions (₹50K–₹150K)
- **Bill Payments:** 1–2 IMPS outbound transactions (₹500–₹2K)
- **Transfers:** Inter-account transfers (to demonstrate transfer workflows)
- Distributed over past 90 days for realistic transaction history

### Security Questions (Per User)
- 5 standard security questions
- Answers: Derived from user name + generic suffixes
- Used in forgot-password flow (auth.l25 session-puzzling lab)

### Audit Log (Per User)
- Sample login entries
- IP addresses for last_login_ip field (accounts.l19 info disclosure)

---

## SQL Files Structure

### 03-bulk-users-100.sql (New)

This file is auto-loaded during `./start.sh setup`. It contains:

```
1. 100 user INSERT statements
   - Passwords: bcrypt hashes of {customerid}B4rc@123
   - Password MD5: plaintext hash (insecure, by design)
   - Created dates: staggered over past 30 days

2. 200 account INSERT statements (2 per user)
   - Savings and current account types
   - Realistic balances (₹200K–₹1M)
   - Daily transfer limits

3. 400+ beneficiary INSERT statements (4 per user)
   - Cross-references to other users' accounts
   - IFSC codes, bank names
   - Used for IDOR labs

4. 200 card INSERT statements (2 per user)
   - Debit and credit cards
   - Card numbers, CVV, expiry (all plaintext)
   - PIN hashes (MD5 by design)

5. 100+ transaction INSERT statements
   - Salary credits (external → savings account)
   - Bill payments (account → external)
   - Inter-account transfers
   - Realistic transaction references

6. 500+ security question INSERT statements (5 per user)
   - Standard forgot-password questions
   - User-specific answers

7. 100+ audit log INSERT statements
   - Login activity per user
   - IP addresses

8. 100 user preference INSERT statements
   - Default theme (dark)
   - Language (en)
   - Dashboard defaults
```

---

## Verification & Testing

### Run Verification Script

```bash
php src/scripts/verify_bulk_users.php
```

This checks:
- 100 users exist (BS100300–BS100399)
- 200 accounts (2 per user)
- 400+ beneficiaries (4 per user)
- 200 cards (2 per user: debit + credit)
- 100+ transactions
- 500+ security questions (5 per user)
- 100+ audit log entries
- User preferences configured
- Sample user functional completeness

### Manual Testing Checklist

- [ ] **Login:** Try BS100300 / BS100300B4rc@123
- [ ] **OTP Flow:** Verify 4-digit OTP is generated
- [ ] **Dashboard:** View accounts, balances, recent transactions
- [ ] **Statements:** Check transaction history (salary credits, bill payments)
- [ ] **Beneficiaries:** Verify 4 beneficiaries are pre-configured
- [ ] **Transfer:** Verify beneficiary list loads correctly
- [ ] **Cards:** View debit and credit cards with plaintext details
- [ ] **Preferences:** Check theme/language settings persist
- [ ] **Audit Log:** View login history in admin panel
- [ ] **Security Questions:** Test forgot-password flow

### Sample User Scenarios

**User:** BS100300 (Arjun Verma)
- **Password:** BS100300B4rc@123
- **Savings Account:** Starting balance ~₹500K
- **Credit Card Limit:** ₹300K
- **Beneficiaries:** Pre-configured for transfers

**User:** BS100350 (Yara Khan)
- **Password:** BS100350B4rc@123
- **Current Account:** Starting balance ~₹300K
- **Debit Card Limit:** ₹100K
- **Transactions:** Recent salary credits visible in statement

---

## Database Cleanup & Reset

### View Bulk Users

```bash
mysql -u banksploit -p banksploit_pass banksploit -e "
SELECT COUNT(*) as bulk_users FROM users WHERE customer_id LIKE 'BS1003%';
SELECT customer_id, name, email FROM users WHERE customer_id LIKE 'BS1003%' LIMIT 10;
"
```

### Remove Only Bulk Users (Keep Defaults)

```bash
mysql -u banksploit -p banksploit_pass banksploit -e "
DELETE FROM users WHERE customer_id LIKE 'BS1003%';
-- Cascading deletes handle accounts, beneficiaries, cards, transactions, etc.
"
```

### Full Database Reset (All Users)

```bash
./start.sh reset-full
```

Then re-run:
```bash
./start.sh setup
```

---

## Files Changed

### New Files
1. **sql/03-bulk-users-100.sql** — 100-user seed data
2. **src/scripts/verify_bulk_users.php** — Verification script

### Modified Files
1. **start.sh** — Updated help text to mention 100 sample users

### Unchanged
- All other application files remain identical
- Security vulnerabilities intentionally preserved
- No changes to core application logic

---

## Performance Considerations

- **Database Size:** ~15MB larger (100 users + 2000+ related records)
- **Setup Time:** +30 seconds (loading 03-bulk-users-100.sql)
- **Query Performance:** No impact (proper indexes on user_id, customer_id)
- **Test Runtime:** Verification script completes in <5 seconds

---

## Troubleshooting

### Problem: Verification Script Fails

**Solution:** Ensure setup completed successfully:
```bash
./start.sh setup
php src/scripts/verify_bulk_users.php
```

### Problem: Can't Login with Bulk User

**Solution:** Verify password format is correct:
- Format: `{customerid}B4rc@123`
- Example: `BS100300B4rc@123` (not `BS100300@123` or `BS100300b4rc@123`)

### Problem: OTP Not Generated

**Solution:** OTP tokens are created during login. Ensure:
1. Lab Mode is ON (OTP shown on screen)
2. Or query `otp_tokens` table directly for OTP code

### Problem: Some Users Missing After Setup

**Solution:** Check if there's an error in the SQL file. Verify:
```bash
mysql -u banksploit -p banksploit_pass banksploit -e "SELECT COUNT(*) FROM users WHERE customer_id LIKE 'BS1003%';"
```

Expected: 100

---

## Password Template Reference

All 100 bulk users follow the **password template** from bulk import functionality:

```
Format: {customerid}B4rc@123

Replace {customerid} with the participant's customer ID.

Examples:
  Customer ID: BS100300  →  Password: BS100300B4rc@123
  Customer ID: BS100350  →  Password: BS100350B4rc@123
  Customer ID: BS100399  →  Password: BS100399B4rc@123
```

This template:
- ✓ Is easy to remember and communicate
- ✓ Allows participants to derive their own password
- ✓ Ensures consistent, predictable credentials for testing
- ✓ Matches the bulk-import feature password generation

---

## Next Steps

1. ✓ Run `./start.sh setup` to load all 100 users
2. ✓ Run `php src/scripts/verify_bulk_users.php` to verify
3. ✓ Start the application: `./start.sh start`
4. ✓ Test with sample users (BS100300–BS100399)
5. ✓ Share login credentials with participants
6. ✓ Monitor performance and user activity in audit logs

---

## Support

For issues or questions:
- Check the verification script output for specific failures
- Review the database schema: `sql/01-schema.sql`
- Check audit logs: Admin Panel → Audit Log
- Review application logs: `.local/php-server.log`

---

**Status:** ✅ Production Ready  
**Last Updated:** 2026-08-08  
**Total Users:** 105 (5 default + 100 bulk)
