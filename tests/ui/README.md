# BankSploit — UI functional tests

Smoke tests for the **banking portal** (http://localhost:9443) that verify the
*features* work, not the vulnerabilities. Everything is driven through the HTML
pages and their `<form>`s, exactly as a browser would — GET a page, POST its
form, follow redirects, assert on the rendered HTML. **No `/api/v1/*` business
APIs are called** (a guard in the harness aborts if one is requested).

## Run

```bash
./start-app.sh start            # app must be running; CTF or any mode is fine
python3 tests/ui/functional_ui_test.py

# Or run the whole suite under all three modes (CTF, Training, Pentest) and
# restore CTF at the end — switches modes via the instructor portal:
tests/ui/run_all_modes.sh
```

Options via environment variables:

```bash
BASE=http://127.0.0.1:9443 CUSTOMER_ID=BS100234 PASSWORD=password123 \
    python3 tests/ui/functional_ui_test.py
```

Exit code `0` = all passed, `1` = at least one failed. No dependencies (Python 3
standard library only).

## About the OTP

Login and transfer use a 4-digit OTP. The harness reads it from `/get-login-otp`
and `/get-transfer-otp` — the same helper endpoints the pages' own JavaScript
calls to show the OTP to the user. This stands in for a human reading the code
off the screen; it is not one of the business APIs under test.

## Test cases

| # | Case | What it checks (via the UI) |
|---|------|------------------------------|
| 1 | Login rejects wrong password | Bad password keeps you on the login form and `/dashboard` stays blocked |
| 2 | Login reaches dashboard | Valid customer ID + password + OTP lands on the dashboard |
| 3 | Dashboard shows accounts | Dashboard renders with at least one account number |
| 4 | Statements + filter | Statements page loads; the date/search filter form returns results |
| 5 | Beneficiaries list + add | Beneficiaries page loads; adding one shows "Beneficiary added." |
| 6 | Transfer actually moves money | Full wizard; **source balance drops** and the debit appears in the account's statement |
| 7 | Transfer credits the payee (double-entry) | Logs in as the payee and confirms **their balance rose by the exact amount** (not via IDOR) |
| 8 | Cards page | Cards page loads |
| 9 | Loan is created and listed | Applying succeeds and the quoted EMI then **appears as a row in the loans list** |
| 10 | Bill payment debits + shows in history | Paying **drops the account balance** and the bill **appears in the payment history** |
| 11 | Profile | Profile page shows the user's details |
| 12 | Preferences + save | Saving shows "Preferences saved." |
| 13 | Logout | After logout, `/dashboard` redirects back to login |

Tests 6, 7, 9 and 10 assert the **real effect** (balances, ledger, history, list),
not just the confirmation message. The double-entry test uses a second real user
(`DEST_ID` / `DEST_PASSWORD`, default `BS100235` / `priya@123`).

## Running under each mode

`run_all_modes.sh` applies CTF, Training and Pentest in turn (via the instructor
portal's Modes page) and runs the suite under each, then restores CTF. All 13
checks pass in every mode — modes change only the CTF/teaching scaffolding, never
the banking functionality. Verified 2026-11-06.

## Notes

- Tests 5, 6, 8, 9 **create real records** and move ₹1 in the training DB. This is
  harmless and repeatable; to reset to clean seed data run `./start-app.sh reset-full`.
- These cover the *standard portal* features only. The instructor portal
  (port 8888) and the deliberate vulnerabilities are out of scope here.
- Default user `BS100234 / password123` (Rahul Sharma). Any valid user works.
