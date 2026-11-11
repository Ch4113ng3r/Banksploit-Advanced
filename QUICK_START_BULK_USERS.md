# BankSploit — 100 Bulk Users Quick Start (2 Minutes)

## One-Time Setup

```bash
# 1. Run setup (includes all 100 users automatically)
./start.sh setup

# 2. Verify everything is loaded correctly
php src/scripts/verify_bulk_users.php

# 3. Start the application
./start.sh start
```

**Expected output from verify script:**
```
✓ Found: 100 users (BS100300-BS100399)
✓ Total accounts: 200
✓ Total cards: 200
✓ ALL TESTS PASSED
```

---

## Login & Test

### Test User 1
```
URL:          http://localhost:9443
Customer ID:  BS100300
Password:     BS100300B4rc@123
OTP:          (shown on screen in Lab Mode)
```

### Test User 2
```
Customer ID:  BS100350
Password:     BS100350B4rc@123
```

### Test Any User
```
Customer ID:  BS100300 to BS100399 (pick any)
Password:     {customerid}B4rc@123
```

---

## What Each User Has

✓ Savings account (₹500K–₹1M)  
✓ Current account (₹200K–₹500K)  
✓ Debit card  
✓ Credit card  
✓ 4 beneficiaries  
✓ Transaction history (past 90 days)  
✓ Security questions (for forgot-password)  

---

## Share With Participants

**Copy this and send to participants:**

```
Your login credentials:
  Customer ID: BS100300
  Password: BS100300B4rc@123

Password format: {your_customer_id} + B4rc@123

Examples:
  If your Customer ID is BS100325 → Password is BS100325B4rc@123
  If your Customer ID is BS100350 → Password is BS100350B4rc@123
```

---

## Troubleshooting

**Setup fails?**
```bash
# Ensure MySQL is running, then retry
./start.sh setup
```

**Verification fails?**
```bash
# Make sure setup completed
php src/scripts/verify_bulk_users.php
# If it fails, re-run: ./start.sh setup
```

**Can't login?**
```
- Check password format: {customerid}B4rc@123 (with @ symbol)
- Ensure you're using customer ID between BS100300–BS100399
- Make sure OTP is entered (shown on screen)
```

**Need to reset?**
```bash
# Full reset (WARNING: deletes all data)
./start.sh reset-full
./start.sh setup
```

---

## Key Files

- **setup script:** `./start.sh` (just run `./start.sh setup`)
- **verification:** `php src/scripts/verify_bulk_users.php`
- **documentation:** `BULK_USERS_SETUP.md` (comprehensive guide)
- **credentials:** `BULK_USERS_CREDENTIALS.txt` (password reference)
- **SQL data:** `sql/03-bulk-users-100.sql` (100 users + related data)

---

## Password Template

All 100 bulk users follow this pattern:

```
{customerid}B4rc@123

Examples:
  BS100300B4rc@123 (Arjun Verma)
  BS100325B4rc@123 (Unknown user)
  BS100350B4rc@123 (Yara Khan)
  BS100375B4rc@123 (Unknown user)
  BS100399B4rc@123 (Kishan Sharma)
```

---

## That's It! 🎉

You now have 100 complete user accounts with all banking data ready to use.

```bash
./start.sh setup        # Load users (includes bulk users automatically)
php src/scripts/verify_bulk_users.php  # Verify
./start.sh start        # Start app
# Login with BS100300 / BS100300B4rc@123
```

For more details, see `BULK_USERS_SETUP.md`.

---
