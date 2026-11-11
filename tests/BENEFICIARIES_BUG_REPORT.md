# BankSploit: Beneficiaries Module - Bug Report

## Issue: Duplicate Beneficiary Account Entries

**Severity:** Medium  
**Status:** Confirmed  
**Date:** 2026-08-01

---

## Summary

The Beneficiaries module allows a user to add the **same account number multiple times** as separate beneficiary entries. There is no duplicate validation check, leading to:

- Multiple identical entries in the beneficiary list
- Redundant rows in the transfer dropdown
- Data integrity issues
- Potential confusion during fund transfers

---

## Root Cause

### Database Schema Issue

The `beneficiaries` table lacks a **unique constraint** on the combination of `(user_id, account_number)`:

```sql
CREATE TABLE beneficiaries (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id              INT UNSIGNED NOT NULL,
    name                 VARCHAR(128) NOT NULL,
    account_number       VARCHAR(20)  NOT NULL,
    ifsc_code            VARCHAR(11)  NOT NULL,
    bank_name            VARCHAR(128) NOT NULL,
    is_verified          BOOLEAN      NOT NULL DEFAULT TRUE,
    cooling_period_until DATETIME     DEFAULT NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_beneficiaries_user (user_id),
    CONSTRAINT fk_beneficiaries_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Missing:** `UNIQUE KEY uniq_user_account (user_id, account_number)`

### Code-Level Issue

In [src/modules/transfer/beneficiary.php:65-78](../src/modules/transfer/beneficiary.php#L65-L78), the `ben_add()` function **does NOT validate** for duplicate accounts:

```php
function ben_add(PDO $pdo, TrainingEngine $engine, int $uid, array $in): void
{
    $pdo->prepare(
        'INSERT INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
         VALUES (:u, :n, :a, :i, :b, 1, :c)'
    )->execute([
        ':u' => $uid,
        ':n' => (string) ($in['name'] ?? ''),
        ':a' => (string) ($in['account_number'] ?? ''),
        ':i' => (string) ($in['ifsc_code'] ?? ''),
        ':b' => (string) ($in['bank_name'] ?? ''),
        ':c' => ben_cooling_until($engine, $in),
    ]);
}
```

**Missing:** Pre-insert check for existing `(user_id, account_number)` pair

---

## Reproduction Steps

### Manual Test

1. **Login** to BankSploit as any user
2. Navigate to **Beneficiaries** page
3. **Add Beneficiary #1:**
   - Name: `John Doe`
   - Account: `1234567890`
   - IFSC: `SBIN0001234`
   - Bank: `State Bank`
   - Click **Add**

4. **Add Beneficiary #2 (same account):**
   - Name: `John Doe (Copy)`
   - Account: `1234567890` ← **Same account number**
   - IFSC: `SBIN0001234`
   - Bank: `State Bank`
   - Click **Add**

5. **Result:** Both entries appear in the beneficiary list

### Automated Test

Run test case: `BeneficiariesTest::testDuplicateBeneficiaryCanBeAdded()`

```bash
./vendor/bin/phpunit tests/BeneficiariesTest.php::BeneficiariesTest::testDuplicateBeneficiaryCanBeAdded
```

---

## Impact Analysis

### User Experience

- **Beneficiary List:** Shows duplicate entries
- **Transfer Dropdown:** Multiple entries for same account (confusing)
- **Account Management:** Difficult to track unique payees

### System Impact

```
Current Behavior:
User adds account 1234567890 five times
├── Beneficiary 1: Account 1234567890
├── Beneficiary 2: Account 1234567890
├── Beneficiary 3: Account 1234567890
├── Beneficiary 4: Account 1234567890
└── Beneficiary 5: Account 1234567890
Result: 5 rows in transfer dropdown for same account

Expected Behavior:
User adds account 1234567890 multiple times
└── Beneficiary 1: Account 1234567890 (UNIQUE)
Result: 1 row in transfer dropdown for same account
```

### Related Features Affected

- **Transfer Module:** Transfer dropdown shows duplicates
- **Beneficiary List UI:** Table bloat with duplicate rows
- **Beneficiary Count:** Misleading total count of unique payees

---

## Test Coverage

### Test Cases Created

✓ `testDuplicateBeneficiaryCanBeAdded()` — Verifies duplicate prevention missing  
✓ `testMultipleDuplicatesInTransferDropdown()` — Shows UI impact  
✓ `testAddBeneficiaryWithValidData()` — Baseline add functionality  
✓ `testAddBeneficiaryWithEmptyFields()` — Edge case validation  
✓ `testAddBeneficiaryWithXSSPayload()` — XSS payload storage  
✓ `testDeleteBeneficiary()` — Delete functionality  
✓ `testListBeneficiariesByUser()` — Ownership scoping  
✓ `testStoredXSSInBeneficiaryName()` — beneficiary.g3 XSS vuln  
✓ `testIDORReadOtherUserBeneficiary()` — beneficiary.l3 IDOR vuln  

**File:** [tests/BeneficiariesTest.php](./BeneficiariesTest.php)

---

## Recommended Fix

### Option 1: Database Constraint (Recommended)

Add unique constraint to prevent duplicates at the database level:

```sql
ALTER TABLE beneficiaries 
ADD UNIQUE KEY uniq_user_account (user_id, account_number);
```

**Pros:**
- ✓ Database-level enforcement (strongest)
- ✓ Prevents duplicate regardless of app code path
- ✓ Performance: Unique index speeds up lookups
- ✓ No application code changes needed

**Cons:**
- ✗ Requires database migration
- ✗ Insertion will fail if duplicate attempted (need error handling)

### Option 2: Application-Level Validation

Add check in `ben_add()` before insert:

```php
function ben_add(PDO $pdo, TrainingEngine $engine, int $uid, array $in): void
{
    $accountNumber = (string) ($in['account_number'] ?? '');
    
    // Check for existing beneficiary with same account
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM beneficiaries 
        WHERE user_id = :u AND account_number = :a
    ');
    $stmt->execute([':u' => $uid, ':a' => $accountNumber]);
    $exists = (int) $stmt->fetchColumn();
    
    if ($exists > 0) {
        throw new Exception('Beneficiary with this account already exists.');
    }
    
    // Continue with insert...
}
```

**Pros:**
- ✓ Application-level control
- ✓ Friendly error messages to user
- ✓ No database migration needed

**Cons:**
- ✗ Weaker than DB constraint (race conditions possible)
- ✗ Requires app code update
- ✗ Duplicate prevention only if code is used

### Option 3: Hybrid (Best Practice)

Implement **both** database constraint AND application validation:

```sql
ALTER TABLE beneficiaries 
ADD UNIQUE KEY uniq_user_account (user_id, account_number);
```

```php
function ben_add(PDO $pdo, TrainingEngine $engine, int $uid, array $in): ?string
{
    try {
        $pdo->prepare(
            'INSERT INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
             VALUES (:u, :n, :a, :i, :b, 1, :c)'
        )->execute([...]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // Duplicate entry error
            return 'Beneficiary with this account already exists.';
        }
        throw $e;
    }
}
```

**Pros:**
- ✓ Defense in depth (DB + app)
- ✓ Catches any duplicate attempts
- ✓ User-friendly error handling
- ✓ Race condition safe

---

## Migration Script

For existing databases with duplicate entries:

```sql
-- Remove duplicates, keeping the oldest entry per user/account
DELETE FROM beneficiaries 
WHERE id NOT IN (
    SELECT MIN(id) 
    FROM beneficiaries b1 
    GROUP BY user_id, account_number
);

-- Add unique constraint
ALTER TABLE beneficiaries 
ADD UNIQUE KEY uniq_user_account (user_id, account_number);
```

---

## Functional Test Cases

All test cases are implemented in [tests/BeneficiariesTest.php](./BeneficiariesTest.php):

### Test Suites

**Suite 1: Duplicate Prevention** (Primary Issue)
- `testDuplicateBeneficiaryCanBeAdded()` → FAILS (confirms bug)
- `testMultipleDuplicatesInTransferDropdown()` → FAILS (shows impact)

**Suite 2: Beneficiary Management**
- `testAddBeneficiaryWithValidData()` → PASS
- `testAddBeneficiaryWithEmptyFields()` → PASS (but shows design issue)
- `testAddBeneficiaryWithXSSPayload()` → PASS (XSS payload accepted)
- `testDeleteBeneficiary()` → PASS
- `testListBeneficiariesByUser()` → PASS

**Suite 3: Cooling Period Bypass (beneficiary.l8)**
- `testCoolingPeriodBypassVulnerable()` → PASS (shows vuln)
- `testCoolingPeriodSecure()` → PASS (shows fix at secure level)

**Suite 4: IDOR Vulnerability (beneficiary.l3)**
- `testIDORReadOtherUserBeneficiary()` → PASS (shows vuln)
- `testIDORListAllBeneficiaries()` → PASS (shows information leak)

**Suite 5: Stored XSS (beneficiary.g3)**
- `testStoredXSSInBeneficiaryName()` → PASS (shows vuln)
- `testStoredXSSWithEventHandlers()` → PASS (event handlers stored)

**Suite 6: API Endpoints**
- `testAPIAddBeneficiary()` → PASS
- `testAPIListBeneficiaries()` → PASS
- `testAPIGetBeneficiary()` → PASS
- `testAPIDeleteBeneficiary()` → PASS

---

## Running the Tests

### Install PHPUnit

```bash
composer require --dev phpunit/phpunit
```

### Run All Tests

```bash
./vendor/bin/phpunit tests/BeneficiariesTest.php
```

### Run Specific Test

```bash
./vendor/bin/phpunit tests/BeneficiariesTest.php::BeneficiariesTest::testDuplicateBeneficiaryCanBeAdded
```

### Run Test Suite

```bash
./vendor/bin/phpunit tests/BeneficiariesTest.php --filter "Duplicate"
```

---

## Files Involved

| File | Issue |
|------|-------|
| `sql/01-schema.sql` | Missing UNIQUE constraint on `(user_id, account_number)` |
| `src/modules/transfer/beneficiary.php` | No pre-insert duplicate check in `ben_add()` |
| `src/modules/transfer/_shared.php` | `xfer_user_beneficiaries()` returns all including duplicates |
| `src/modules/transfer/index.php` | Transfer dropdown shows duplicate entries |

---

## Related Vulnerabilities

This module also contains the following documented vulnerabilities:

| Vuln ID | Title | Type | Severity |
|---------|-------|------|----------|
| `beneficiary.l8` | Cooling Period Bypass | CWE-840 | Medium |
| `beneficiary.l3` | IDOR | CWE-639 | High |
| `beneficiary.g3` | Stored XSS | CWE-79 | Medium |

See [BeneficiariesTest.php](./BeneficiariesTest.php) for test cases covering these vulns.

---

## Recommendation Priority

1. **CRITICAL:** Add database constraint for duplicate prevention
2. **HIGH:** Add application-level validation
3. **MEDIUM:** Update UI to indicate unique beneficiaries
4. **LOW:** Run migration to clean existing duplicates

---

## References

- **CWE-111:** Direct Use of Insufficiently Evaluated Data  
- **CWE-840:** Business Logic Flaw  
- Database Schema: [sql/01-schema.sql](../sql/01-schema.sql)  
- Module Code: [src/modules/transfer/beneficiary.php](../src/modules/transfer/beneficiary.php)  
- Test Suite: [tests/BeneficiariesTest.php](./BeneficiariesTest.php)

---

**Status:** ✅ Confirmed | **Assignee:** Backend Team | **Priority:** Medium
