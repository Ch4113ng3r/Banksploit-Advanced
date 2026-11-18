# CTF Design Decisions & Progress

## Status Tracker

| # | Vuln | Pts | Status | Notes |
|---|------|-----|--------|-------|
| L11 | OTP Leaked in JavaScript | 100 | ✅ DONE | OTP injected dynamically into `otp-validation.js`. Flag in `X-CTF-Flag` header of JS response. |
| G1 | SQLi Login Bypass | 200 | ✅ DONE | Non-alphanumeric + successful login = confirmed SQLi. Flag in `X-CTF-Flag` header. |
| L10 | OTP Brute-Force | 200 | ✅ DONE | Console loop POSTs form data. Flag in `X-CTF-Flag` header on correct OTP. |
| SL1 | IDOR View Statements | 200 | ✅ DONE | Change `?account=` URL to other user's account number (discovered from beneficiaries). |
| L1 | IDOR Transfer | 300 | ✅ DONE | Greyed-out From field. DOM edit + hidden input change + wizard. Flag in `X-CTF-Flag` header. |
| L12 | JWT Algorithm None | 300 | ✅ DONE | Forge JWT (alg:none, role:admin). Console `fetch('/api/v1/auth/vault')`. Flag in JSON response. |
| G2 | SQLi UNION Extract | 300 | ✅ DONE | Edit date input type, UNION SELECT HEX(flag). Hex in HTML table → Console decode. |
| G9 | Command Injection | 300 | ✅ DONE | PDF filename: `x.pdf;ls;` discover → `x.pdf;cat flag_g9_1.txt;` extract. |
| L16 | Account Enumeration | — | ❌ REMOVED | Zero skill with known usernames. |
| L13 | Password Reset Token | — | ❌ REMOVED | Allows other user account compromise. |
| G4 | Reflected XSS | — | 🎓 TRAINING ONLY (2026-11-12) | Removed from the CTF (`ctf_eligible=false`). The admin/victim bot flag-delivery path was built and verified but then **retired** — the exploit was too unreliable to hinge a flag on. The L0–L3 reflection ladder stays in the labs for training/pentest: not scored, no flag delivery, no flag submission. See docs/VULN_LEVEL_PLAN.md §3. |

---

## Implemented Changes

### G1 — SQLi Login Bypass (DONE)
- **File**: `src/modules/auth/login.php` (line ~114)
- **Logic**: If `!ctype_alnum($customerId)` AND login succeeds → flag in `X-CTF-Flag` response header
- **Why foolproof**: Legit customer IDs are always alphanumeric. Non-alphanumeric input that still logs in = proven SQLi.
- **Participant flow**: Enter payload in browser login form → login succeeds → check Network tab → Response Headers → `X-CTF-Flag`

---

## Redesigns Needed

### L1 — IDOR (Current Problem & Proposed Fix)

**Current problem**: Statements page dropdown lists ALL accounts including other users'. This is:
- Unrealistic (no bank shows others' accounts)
- Requires no skill (just pick from dropdown)
- Teaches nothing about how real IDOR works

**Better approach — Beneficiary-based discovery + greyed-out "From" field tampering:**

The realistic scenario:
1. Each user account has **at least 4 beneficiaries** pre-seeded (need to check if beneficiary functionality exists)
2. These beneficiaries expose other account numbers naturally (you can see who you're paying)
3. On the Transfer page, the **"From account" field is greyed out** (disabled/readonly) showing the user's own account — it's not a dropdown they select from
4. The exploitation: participant notices the greyed-out field contains their account ID, uses DevTools to un-grey it (remove `disabled`/`readonly`) or edits the value, and submits a transfer FROM another user's account

**Participant flow**:
1. Log in, go to Transfer page
2. See "From account" is greyed out with their own account number
3. Notice beneficiary list shows other account numbers (discovery)
4. Open DevTools → Elements tab → find the From field → remove `disabled` attribute or change the value
5. Set it to another account number (discovered from beneficiaries)
6. Submit the transfer — it succeeds (server doesn't validate ownership)
7. Flag appears in `X-CTF-Flag` response header

**Why this is better**:
- Realistic — banks DO show beneficiaries, and the From field IS typically locked
- Requires DISCOVERY (find account numbers via beneficiaries)
- Requires TAMPERING (un-grey / modify a locked form field)
- Teaches real-world IDOR technique: "the UI prevents this, but the server doesn't enforce it"
- Progressive difficulty: discovery → identify the lock → bypass the lock → exploit

**Implementation changes needed**:
1. Check if beneficiary functionality exists; if yes, seed 4+ beneficiaries per user account
2. Transfer page: show "From account" as a **disabled/readonly** input (not a dropdown)
3. Remove the dropdown that lists all accounts
4. Keep the ACTUAL vulnerability: server still doesn't validate ownership on the backend
5. Add flag detection: if `from_account_id` doesn't belong to current user AND transfer succeeds → flag in `X-CTF-Flag` header

**Difficulty calibration**: 200 pts — discovery + DOM manipulation + understanding server-side vs client-side enforcement.

**Findings from codebase investigation:**
- ✅ `beneficiaries` table ALREADY EXISTS in `sql/01-schema.sql` (lines 62-76)
- ✅ Transfer "To" field already uses beneficiary dropdown (`xfer_user_beneficiaries()`)
- ✅ Flag already awarded in `src/modules/transfer/transfer_vuln.php` line 144 when `$from['user_id'] !== $userId`
- ⚠️ Statements page (`src/modules/statements/_shared.php` line 44) — `stmt_list_accounts()` returns ALL accounts at vuln levels → unrealistic, needs redesign
- ✅ Transfer page — "From account" is a `<select>` with only user's own accounts; in presence of beneficiaries (which show other account numbers), user can discover valid IDs and tamper the field → this is fine as-is

**Redesign decisions:**

**Transfer page IDOR: KEEP AS-IS** ✅
- With beneficiaries seeded, participants discover other account numbers naturally
- They tamper the "From account" field (edit DOM / intercept request) to use someone else's ID
- Server doesn't check ownership → transfer succeeds → flag awarded
- This flow is realistic and requires active exploitation

**Statements page IDOR: REDESIGN** 🔄
- Remove the dropdown that lists ALL accounts
- Instead, pass the **account_number** (e.g. `1000100234`) in the URL — NOT a numeric sequential ID
- Example: `/statements?account=1000100234` (user's own account number shown by default)
- The vulnerability: server resolves account_number to account record WITHOUT checking ownership
- Participant discovers other account numbers from beneficiaries list, then changes the URL parameter
- This is more realistic: real banks use account numbers in URLs, not internal DB IDs
- It also makes the IDOR slightly harder — you can't just increment `account_id=1` to `account_id=2`; you need an actual valid account number

**TODO (implementation)**:
- Seed 4+ beneficiaries per user in `sql/02-seed-data.sql` — beneficiary account numbers should reference other users' accounts (gives participants a way to discover valid account numbers)
- Statements page: remove the "show all accounts" dropdown; show only user's own account(s)
- Statements page: use `?account=<account_number>` in URL instead of `?account_id=<numeric_id>`
- Statements page: at vuln levels, resolve account_number without ownership check (IDOR remains exploitable)
- Statements page: when a non-owned account is accessed successfully, deliver flag in `X-CTF-Flag` header
- Transfer page: keep as-is; add `X-CTF-Flag` header delivery to the HTML form path (currently flag only in JSON API response at `transfer_vuln.php` line 144 via `$resp['ctf_flag']`)
- Ensure `start.ps1 setup` / `start.sh setup` / `reset-full` all include the beneficiary seed data

---

### G4 — Reflected XSS (Current Problem & Proposed Fix)

**Current problem**: No automated flag delivery. XSS executes but the flag can only come from:
- The instructor manually (breaks self-service CTF model)
- Extracting from DB via G2 SQLi (but seed_flags.php doesn't even seed a G4 flag)

**Better approach — Server-side XSS detection:**

When the search parameter contains a payload that WILL execute as XSS (because at vuln levels it's reflected without escaping), the server detects the injection attempt and returns the flag.

**Detection logic**: If the input contains patterns like `<script`, `onerror=`, `onload=`, `javascript:`, `<img`, `<svg` AND the vuln is active (meaning it WILL be reflected unescaped) → return flag in `X-CTF-Flag` header.

**Why this works**:
- Same principle as G1: we detect the attack payload, not the outcome
- If the vuln is active, these patterns WILL execute (the app reflects them unescaped)
- Participant must understand what XSS IS and craft a valid payload
- Typing normal search text won't trigger it (no `<script` in "rent payment")

**Flag delivery**: `X-CTF-Flag` response header on the Statements page when XSS payload detected.

**Implementation**: In `src/modules/statements/index.php`, after receiving the search `q` parameter:
```php
if ($engine->isVulnerable('statements', 'g4') && preg_match('/<\s*(script|img|svg|iframe|body|input|details|marquee)\b|on\w+\s*=/i', $q)) {
    header('X-CTF-Flag: ' . Flags::generate('statements.g4', $userId));
}
```

---

### L16 — Account Enumeration (DROPPED)

**Why dropped**: With real user accounts, participants already know valid usernames. Typing their own username + wrong password immediately reveals the flag. Zero skill needed.

**Points redistribution**: Consider adding 100 pts to another challenge or leaving total at 2400 instead of 2500.

---

## Final Challenge Lineup (IMPLEMENTED & VERIFIED)

| # | Vuln Key | Title | Pts | Tier | Technique | Flag Delivery |
|---|----------|-------|-----|------|-----------|---------------|
| L11 | auth.l11 | OTP Leaked in JavaScript | 100 | Easy | Inspect `/public/js/otp-validation.js` in DevTools Sources/Network tab | Flag embedded in `SESSION_DATA._flag` inside the JS file (alongside OTP in `_otp`) |
| G1 | auth.g1 | SQLi Login Bypass | 200 | Medium | Type `BS100234' -- ` in Customer ID field (must target real username) | `X-CTF-Flag` response header on login POST |
| L10 | auth.l10 | OTP Brute-Force | 200 | Medium | Console loop POSTing OTP 0000-9999 with `Accept: application/json` | `ctf_flag` field in JSON response body on correct OTP |
| SL1 | statements.l1 | IDOR — View Other Statements | 200 | Medium | Find account numbers in Beneficiaries, change `?account=` in Statements URL | `X-CTF-Flag` response header on Statements page |
| L1 | transfer.l1 | IDOR — Transfer from Other Account | 300 | Hard | DevTools: change hidden `from_account_id` input, complete wizard | `X-CTF-Flag` response header on transfer receipt |
| L12 | auth.l12 | JWT Forgery | 300 | Hard | Decode `access_token` cookie, forge JWT with `alg:none` + `role:admin` | Flag in JSON response from `/api/v1/auth/vault` (Console `fetch()`) |
| G2 | statements.g2 | SQLi UNION Data Extract | 300 | Hard | Edit date input type→text, `' UNION SELECT 1,HEX(flag),3,4,5,6,7 FROM ctf_flags WHERE user_id=1 AND vuln_key='statements.g2` | Hex-encoded flag in HTML table → decode in Console |
| G9 | statements.g9 | Command Injection PDF | 300 | Hard | PDF filename: `x.pdf;ls;` to discover → `x.pdf;cat flag_g9_1.txt;` to read | Flag in JSON output (browser navigates to API via form GET) |

**Active & delivering flags: 8 challenges, 2000 points**
**All challenges verified working as of 2026-07-11.**

**Key design changes (this session):**
- L11 flag now inside JS file body (SESSION_DATA._flag), NOT in X-CTF-Flag header
- G1 anti-tautology guard: `' OR '1'='1` blocked; must target a real username
- L10 returns JSON with flag when `Accept: application/json` header is sent (fixes redirect-swallows-header issue)
- CSRF completely disabled (class is a no-op) — no tokens in any forms

**Removed entirely:**
- L16 (Account Enumeration) — zero skill with known usernames
- L13 (Password Reset Token) — allows other user account compromise
- G4 (Reflected XSS) — removed from CTF; XSS vuln still in code for training but no flag/challenge

**Key design principle: NO DIRECT API CALLS** (except L12 vault via Console `fetch()`)
All flags are obtainable through the browser GUI + DevTools:
- Form submissions (login, transfer, statements filters)
- URL parameter manipulation (statements `?account=`)
- DOM editing (transfer From field, date input type)
- Console scripting (OTP brute-force loop, hex decode, JWT forge + fetch)
- Inspecting JS files loaded by the page (OTP in `otp-validation.js`)
- Response headers visible in Network tab (`X-CTF-Flag`)
- Browser navigates to API endpoint via form action (PDF export → JSON in browser)

---

---

## L12 — JWT (IMPLEMENTED: Read-Only Restriction)

**How JWT works in the app:**
- PHP session (`PHPSESSID`) is PRIMARY auth. JWT (`access_token` cookie) is SECONDARY — only used as fallback when no session exists.
- `getCurrentUser()` checks session FIRST, falls back to JWT only if no session.
- At vuln levels: `alg:none` → any forged JWT is accepted without signature check.
- The `/api/v1/auth/vault` endpoint is JWT-ONLY (rejects sessions) and requires `role:admin` in claims.

**Restriction (IMPLEMENTED):**
- JWT-authenticated users are **read-only** — they can view pages (dashboard, statements, cards, etc.) but CANNOT perform any write operation.
- All write endpoints call `auth()->requireSession()` which returns HTTP 403 `"Session authentication required. JWT tokens are read-only."` when the user is JWT-only.
- This prevents forged JWTs from executing transfers, updating profiles, making payments, etc.

**Protected write endpoints:**
- `POST /transfer` (HTML wizard) + all `/api/v1/transfer/*` POST routes
- `POST /profile` + `PUT/POST /api/v1/profile`
- `POST /beneficiaries` + `POST/DELETE /api/v1/beneficiaries`
- `POST /payments` + `POST /api/v1/payments/pay`
- `POST /loans` + `POST /api/v1/loans/apply`
- `PUT/POST /api/v1/cards/{id}/block|pin|limit`
- `POST /prefs`

**Allowed via JWT (read-only):**
- `GET /dashboard`, `/statements`, `/beneficiaries`, `/cards`, `/profile` (view)
- `GET /api/v1/accounts/*`, `/api/v1/cards`, `/api/v1/beneficiaries`
- `GET /api/v1/auth/vault` (L12 challenge target — requires `role:admin`)

**L12 challenge flow unchanged:** Forge JWT → set `role:admin` → `fetch('/api/v1/auth/vault')` → flag.

---

## OTP Delivery — SMS Inbox Page

**Route:** `/otp-inbox`
**Purpose:** Legitimate OTP delivery mechanism (simulates receiving an SMS).

Participants need a way to complete login WITHOUT exploiting vulnerabilities. The SMS Inbox page:
- Shows the current login OTP in a styled "SMS message" bubble
- Is linked from the OTP verification page ("📱 Open SMS Inbox" button)
- Only works when `pending_login` session exists (OTP phase)
- Does NOT reveal the CTF flag (the L11 flag is ONLY in `otp-validation.js`)

**Design rationale:** In a real bank, the OTP is delivered via SMS/email. This page simulates that delivery channel. The CTF challenge (L11) is about finding the OTP leaked in an *unexpected* place (a JavaScript file), not about the legitimate delivery being the vuln.

---

## Provisioning for New Users

**Current state:** When admin adds a new user (via registration or SQL), no accounts or beneficiaries are auto-created. The seed data only covers the 5 pre-defined users.

**What's needed for new users to participate in CTF:**
1. An account (savings/current) must exist for them
2. Beneficiaries should reference other users' account numbers (for IDOR discovery)
3. The user needs at least one seeded transaction to see on the Statements page

**Options:**
- **Script-based**: Add a `provision_user.php` script that creates account + beneficiaries for a given user_id
- **Auto-provisioning on registration**: After INSERT into users, also create a default savings account and 4 beneficiaries pointing to other known accounts
- **Instructor workflow**: Document in README that after adding a user, run `php src/scripts/provision_user.php <customer_id>`

**Decision**: TBD — not blocking for the current CTF. The 5 seeded users are sufficient. Will revisit when multi-participant deployment is needed.

---

## Credentials & Constants

- Test account: `BS100234` / `password123`
- Security Q answers (for L13): Sharma, DPS Bangalore, Mumbai, Tommy, Verma
- JWT secret: `insecure_jwt_secret_for_training`
- App URL: `http://<instructor-ip>:9443`
- Flag format: `BANKSPLOIT{12-CHAR-HEX}`
- Flag generation: `HMAC-SHA256("{userId}:{vulnKey}", FLAG_SECRET)` → first 12 hex chars → uppercase

---

## Resume Checklist

All 8 challenges implemented and verified as of 2026-07-11. Nothing to resume.

**JWT/L12 open item:** The vault endpoint (`/api/v1/auth/vault`) requires a Console `fetch()` call.
This is the ONLY challenge that touches an API endpoint, but it's done via browser DevTools Console
(same-origin, so cookies are sent automatically). No external tool needed. Acceptable.

**Potential future work:**
- Make `/vault` an HTML page so even L12 works purely via URL navigation (optional)
- ~~Re-add G4 (XSS) if a reliable server-side detection method is agreed upon~~ — Built an admin/victim bot (real browser execution) 2026-11-12, then **reverted 2026-11-12**: the exploit path was too unreliable to earn a flag. G4 is now **training/pentest-only** (`ctf_eligible=false`), levels L0–L3 kept, bot subsystem removed.
- Provisioning script for new users (accounts + beneficiaries + seed transactions)
