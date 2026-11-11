# Implementation Summary: Session-Based SQLi Exploitation Verification

## Overview
Implemented a session marker system to verify that users actually exploited the statements.g2 SQL Injection vulnerability before accepting flag submissions.

## Flow
1. **User logs in** → Session created
2. **Accesses /statements** → `ensure_ctf_flags_seeded()` seeds only their flag in `ctf_flags` table
3. **Exploits SQLi** → Successfully extracts flag from database
4. **Server detects extraction** → Sets `$_SESSION['exploited_statements_g2'] = hash('sha256', userid)`
5. **User submits flag** → `tr_submit_flag()` validates session marker before accepting
6. **Result** → Flag accepted only if session marker exists (proof of exploitation)

## Changed Files

### 1. c:\Users\G01197938\Downloads\banksploit-ctf\src\modules\statements\_shared.php

**Changes:**
- Modified `ensure_ctf_flags_seeded()` function (lines 25-43)
  - Now seeds only the **current logged-in user's flag** instead of all users
  - Checks if user's flag already exists to avoid duplicate seeding
  - Flag format: `BANKSPLOIT{MD5(user{uid}:statements.g2)[0:12]}`

- Modified `stmt_transactions()` function (lines 169-176)
  - Added flag detection in vulnerable code path (after query execution)
  - When any row contains `BANKSPLOIT{[A-F0-9]{12}}` pattern:
    - Sets `$_SESSION['exploited_statements_g2'] = hash('sha256', userid)`
    - This happens only once per session (on first flag extraction)
  - Flag stored in 5th column (`remarks`) of UNION SELECT

### 2. c:\Users\G01197938\Downloads\banksploit-ctf\src\modules\accounts\api.php

**Changes:**
- Added `ensure_ctf_flags_seeded($pdo)` call after authentication (line 30)
- Ensures users accessing the API endpoint `/api/v1/accounts/{id}/statement` also get seeded
- Maintains consistency between HTML and API exploitation paths

### 3. c:\Users\G01197938\Downloads\banksploit-ctf\src\training\_shared.php

**Changes:**
- Modified `tr_submit_flag()` function (lines 210-257)
  - Added validation for `statements.g2` vulnerability
  - Before accepting flag:
    1. Validates flag is correct using `Flags::validate()`
    2. **If vuln_key is 'statements.g2':**
       - Checks if `$_SESSION['exploited_statements_g2']` exists
       - Compares session marker with `hash('sha256', current_user_id)`
       - Rejects with message "You must exploit the SQLi to submit this flag." if marker not set
    3. Only proceeds to mark as exploited + award points if validation passes
  - Other vulnerabilities unaffected

## Security Properties

### User Isolation
- Only seeded flag belongs to current user
- Flag extracted via SQLi is deterministic per user
- Session marker uniquely ties exploitation to user's session

### Exploitation Proof
- Session marker set ONLY when:
  - User is logged in (authenticated)
  - Vulnerable SQLi code path executes
  - Query returns data containing flag pattern
  - Flag is in remarks column (UNION SELECT output)

### Session Binding
- Marker expires when session ends (logout)
- Each login generates new session with new marker requirement
- Cannot reuse flag from previous session

## Exploitation Scenario

**Participant's workflow:**
```bash
# 1. Login
curl -c cookies.txt -d "customer_id=BS100234&password=password123" http://app/login

# 2. Complete OTP flow
[OTP received]

# 3. Access statements (seeding happens)
curl -b cookies.txt http://app/statements

# 4. Exploit SQLi
curl -b cookies.txt "http://app/statements?q=x'%20UNION%20SELECT%201,2,3,4,flag,6,7%20FROM%20ctf_flags%20WHERE%20user_id=1%23&account_id=1"
[Extracts: BANKSPLOIT{ABCDEF123456}]
[Server sets session marker]

# 5. Submit flag
curl -b cookies.txt -d "flag=BANKSPLOIT{ABCDEF123456}" http://app/api/v1/training/submit-flag
[Response: "Correct! +X points"]
```

## Testing Notes

- Multi-step login (OTP required) makes automated testing complex
- Session cookies must be preserved between requests
- Flag extraction happens in HTML table rows (remarks column)
- Regex pattern: `BANKSPLOIT{[A-F0-9]{12}}`

## Files NOT Modified

- Database schema (ctf_flags table already exists)
- Flag generation logic (uses existing Flags::generate() for other vulns)
- Authentication system (uses existing session/OTP)
- Training progress tracking (unchanged)
