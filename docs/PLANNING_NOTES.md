# BankSploit planning notes

Status: **notes only, nothing started.** We plan this properly before any further development.

Last updated: 2026-11-05

---

## 1. Requirements

> **Modes implemented 2026-11-05.** CTF / Training / Pentest are built and verified (see section 2.4). The sections below are the original design; where they differ from what shipped, 2.4 is authoritative.

### 1.1 Three run modes, switched from the instructor panel

| Mode | Purpose | Vulnerability selection |
|---|---|---|
| **CTF** | Competitive event with flags and scoring | Instructor picks which vulnerabilities are open for the event, and at which level |
| **Training** | Teaching in a session | Instructor picks the complexity level for each vulnerability, and the app runs with those settings |
| **Pentest** | Looks like a plain production banking app, for a penetration test where the tester finds the flaws and writes a report | Instructor picks which vulnerabilities are open and at which level, same control as CTF, but nothing CTF-related is shown to the tester |

- The mode switch and all per-vulnerability settings are controlled from the instructor panel (port 8888).
- In CTF and Pentest modes, each vulnerability can be opened or closed and has its own level. The difference between them is what the tester sees, not which flaws are present.

### 1.1a Pentest mode — what the tester must NOT see

The point of this mode is that the app behaves like a real banking app with no sign that it's a training target. So it hides everything that reveals the exercise:

- No hints or "Lab Mode" hint bars, and no per-page difficulty selector.
- No flag submission, and no `BANKSPLOIT{...}` flags delivered (no `X-CTF-Flag` headers, no flag modal).
- No leaderboard.
- No Lab dashboard (`/lab`), code viewer (`/training/code`) or Request Inspector (`/inspector`).
- No CTF challenge list or scoring.
- Arguably no Swagger/API docs, unless the instructor wants to provide them as a tester would normally get API docs — open question.

What stays: the full banking app, the vulnerabilities themselves (at the levels the instructor set), and verbose errors if that vulnerability's level leaves them on, since a real misconfigured app would have them.

The tester works from the live app only. Their output is their own pentest report, not anything the app tracks.

### 1.1b How the three modes relate

- CTF and Pentest share the same vulnerability on/off and level controls. A natural design is: the instructor sets the open vulnerabilities once, then picks the mode, and the mode decides how much scaffolding (hints, flags, leaderboard, viewers) is shown on top.
- Training is the most open: hints, levels and the code viewer are all available for teaching.
- Several of these controls already exist as feature toggles (`feature_toggles` table: `training_lab`, `leaderboard`, `flag_submission`, `code_inspector`, `swagger_docs`, `otp_display`). Pentest mode is close to "turn all of those off" plus suppressing flag delivery. Worth checking during design whether a mode is best built as a named preset over these toggles, or as its own setting the toggles derive from.

### 1.1c Mode & feature control page (instructor panel)

A single page in the instructor panel that controls the whole app's presentation. It works two ways, and they drive the same underlying switches:

1. **Pick a mode.** Three buttons — CTF, Training, Pentest — each a preset that sets every feature toggle below at once. A fourth state, **Custom**, is shown (not clickable) whenever the toggles don't exactly match any mode's preset.
2. **Toggle individual features.** Each feature (leaderboard, flag submission, code viewer, …) has its own on/off switch. Flipping any switch away from the selected mode's preset moves the page into Custom; it does not change the other switches.

So picking a mode is a shortcut that fills in the switches, and the switches are the real state. Someone can click "Pentest" and be done, or hand-tune individual features from any starting point.

The page has three groups of switches, one per mode, so it reads as "these belong to CTF, these to Training, these the Pentest hides." A feature that matters to more than one mode appears in each group it belongs to and refers to the same switch.

#### Feature matrix

Defaults per mode. `on`/`off` are the preset values; `?` marks an open question (see section 5). This is a first proposal, not settled.

| Feature | Underlying toggle | CTF | Training | Pentest |
|---|---|---|---|---|
| **CTF group** | | | | |
| Leaderboard | `leaderboard` | on | off ? | off |
| Flag submission | `flag_submission` | on | on ? | off |
| Flag delivery (`X-CTF-Flag` header + modal) | new | on | on ? | off |
| Lab dashboard / challenge list (`/lab`) | `training_lab` | on | on | off |
| **Training group** | | | | |
| Hints and Lab Mode hint bars | new (hints) | off | on | off |
| Per-page difficulty selector | new | off | on | off |
| Code viewer (`/training/code`) | new (today: admin-only) | off | on | off |
| Request Inspector (`/inspector`) | `code_inspector` | on ? | on | off |
| OTP shown on login page | `otp_display` | off ? | on | off |
| **Shared / app-level group** | | | | |
| Swagger / API docs (`/swagger-ui`) | `swagger_docs` | on ? | on | off ? |
| Debug endpoints (`/api/v1/debug/*`) | `debug_mode` | off | off ? | off |
| Request logging | `request_logging` | on | on | on ? |

Notes:
- "new" means there is no toggle for it yet; it's implied by Lab Mode or hardcoded today and would need one.
- Vulnerability on/off and levels are a **separate** control (the existing `/vuln-config` screen), not part of this page. Which flaws are present is independent of how the app presents itself. CTF and Pentest both lean on that screen; this page only governs the scaffolding shown on top.
- Pentest is the strictest: every scaffolding feature off. That's the column to get right first, because anything left on breaks the "looks like a real bank" goal.

### 1.2 Lighter application

The app should be lighter to load and to work with. Specific targets are still to be defined (see section 5).

### 1.3 Per-vulnerability, per-level scoring — IMPLEMENTED 2026-11-05

Every vulnerability carries points, and a different level is worth different points. When the instructor sets up a CTF, they choose which vulnerabilities run and at which level, and the app adds up the points for those exact choices to show the total score available.

**Status: built and verified.**
- Each vulnerability has a `points` block in `vuln_config.json` keyed by level. **Complexity sets the level-0 starting score** (easy 100, medium 200, hard 300) and **each level adds +50** (so level 1 / 2 are worth more because more mitigation is bypassed). Level 3 is secure → 0 points. Direction decided 2026-11-05: reward skill (harder = more).
- `Flags::points($key, $level)` reads these; with no level it prices the vuln's current level.
- On capture, points are awarded for the level live at that moment (`tr_submit_flag`).
- **Headline "Score to achieve"** = sum of the selected (`ctf_active`) vulnerabilities, each priced at its set level. Shown on the CTF Vulnerabilities page ("Score available") and the Scoring page ("Score to achieve"); both follow the selection and levels live.

**What it replaced.** Points were fixed per vulnerability in `Flags::points()` (flat `match`), with level only deciding exploitability. The flat `match` remains only as a fallback for a vuln with no `points` block.

Open follow-up: scores within a complexity tier are currently identical (all easy = 100 at L0, etc.). The requirement "different complexity → different score" is met by the three tiers; if finer per-vuln differentiation is wanted, give each vuln its own base.

**Model.**
- Each vulnerability has a points value for each exploitable level, so a `(vulnerability, level)` pair maps to a score. A natural place is a `points` block per vulnerability in `vuln_config.json`.
- Level 3 is "secure": no flag, so it scores nothing and a vulnerability set to level 3 is out of the event.
- Levels 0–2 are the exploitable ones. These are the levels that carry points. Harder-to-exploit levels can be worth more (see the open question on direction).

**Instructor flow (CTF setup).**
1. On the selection screen, for each vulnerability the instructor picks: include it or not, and if included, which level (0–2).
2. As they pick, the panel shows a running **total score available** = sum of the points for each selected `(vulnerability, level)`.
3. That total is the event's maximum; the leaderboard is scored against it.

**Effects to handle during design.**
- A participant earns the points of the level that was live when they captured the flag. If the instructor changes a level mid-event, decide whether earlier captures keep their original points (`ctf_submissions` already stores `points` per capture, which helps).
- The flag itself doesn't depend on level (it's `HMAC(userId:vuln_key)`), so changing a level doesn't invalidate a captured flag — only its point value.
- `Flags::points()`, the `/scoring` page, and the per-capture points written in `src/training/_shared.php` all need to move to the per-level source.

---

## 2. What exists today

### Application

- **Stack:** plain PHP 8.3 and MySQL 8, no framework or Composer, run on PHP's built-in server.
- **Banking app:** `src/`, port 9443. Accounts, transfers, cards, loans, payments and statements.
- **Instructor panel:** `src-superadmin/`, port 8888. Users, bulk import, flags, scoring, leaderboard and feature toggles.
- **Startup on macOS or Linux:** `./start-app.sh setup`, then `./start-app.sh start`. `start.sh` and `start.ps1` are no longer in the project. `start-app.ps1` is Windows-only.

### Parts the new modes can build on

- **Levels:** each vulnerability runs from 0 (fully vulnerable) to 3 (secure). `src/core/TrainingEngine.php` reads them from `src/config/vuln_config.json`.
- **Panel pages:** `/vuln-config`, `/features`, `/hints`, `/flags` and `/scoring` already exist. They have not yet been reviewed against these requirements.
- **Feature toggles:** the `feature_toggles` table, plus checks in `src/index.php` for the training lab, leaderboard, API docs and flag submission.
- **Flags:** `src/core/Flags.php` generates a per-user `BANKSPLOIT{...}` flag with an HMAC.
- **Training pages:** `/lab`, `/training/code` and `/inspector`.

### Scored CTF challenges (1,900 points)

| Challenge | Key | Points |
|---|---|---|
| OTP leaked in a JS file | `auth.l11` | 100 |
| SQL injection login bypass | `auth.g1` | 200 |
| OTP brute force | `auth.l10` | 200 |
| IDOR: view another user's statements | `statements.l1` | 200 |
| IDOR: transfer from another user's account | `transfer.l1` | 300 |
| JWT `alg:none` forgery | `auth.l12` | 300 |
| UNION SQL injection | `statements.g2` | 300 |
| Command injection in PDF export (simulated by `src/core/Sandbox.php`) | `statements.g9` | 300 |

### 2.1 Full vulnerability inventory

Every vulnerability implemented in the code, found by scanning the `@vuln-start` markers and the `isVulnerable()` / `getVulnLevel()` branches in `src/`. **37 vulnerabilities across 12 modules.**

- **In config** = has an entry in `vuln_config.json` today, so it shows on the Vuln Config page and is controllable. Only 11 do.
- **Scored** = one of the 8 CTF challenges with points today.
- The other 26 are live in the code (default level 0, fully vulnerable) but invisible on the Vuln Config page and have no flag or points.

| Module | Key | Vulnerability | CWE | In config | Scored |
|---|---|---|---|:---:|:---:|
| auth | `auth.g1` | SQL injection login bypass + weak MD5 hashing | 89 / 327 | ✓ | ✓ |
| auth | `auth.l10` | OTP brute-force (no rate limit) | 307 | ✓ | ✓ |
| auth | `auth.l11` | OTP leaked in response / JS file | 200 | ✓ | ✓ |
| auth | `auth.l12` | JWT `alg:none` forgery | 347 | ✓ | ✓ |
| auth | `auth.l13` | Predictable password-reset token | 340 | ✓ | |
| auth | `auth.l14` | Session fixation (id not regenerated on login) | 384 | | |
| auth | `auth.l16` | Account enumeration | 203 | ✓ | |
| auth | `auth.l17` | PII (PAN/Aadhaar/balance) embedded in JWT | 359 | | |
| auth | `auth.l25` | Session puzzling (overloaded session key) | 841 | | |
| statements | `statements.g2` | SQL injection — data extraction (UNION) | 89 | ✓ | ✓ |
| statements | `statements.g4` | Reflected XSS | 79 | ✓ | |
| statements | `statements.g8` | Path traversal (CSV export `?file=`) | 22 | | |
| statements | `statements.g9` | Command injection (PDF export) | 78 | ✓ | ✓ |
| statements | `statements.l1` | IDOR — view other accounts' statements | 639 | ✓ | ✓ |
| transfer | `transfer.g5` | CSRF (no token validated) | 352 | | |
| transfer | `transfer.l1` | IDOR — transfer from another account | 639 | ✓ | ✓ |
| transfer | `transfer.l2` | IDOR — transfer receipt | 639 | | |
| transfer | `transfer.l6` | Negative-amount transfer | 840 | | |
| transfer | `transfer.l7` | Race condition / double-spend | 362 | | |
| transfer | `transfer.l9` | Wizard step-skipping / OTP skip | 841 | | |
| transfer | `transfer.l20` | Rounding / salami (floating-point interest) | 682 | | |
| transfer | `transfer.l21` | Daily-limit bypass (per-transaction check) | 840 | | |
| transfer | `transfer.l23` | Self-transfer cashback abuse | 840 | | |
| beneficiary | `beneficiary.g3` | Stored XSS (biller/beneficiary name) | 79 | | |
| beneficiary | `beneficiary.l3` | IDOR — any beneficiary id | 639 | | |
| beneficiary | `beneficiary.l8` | Cooling-period bypass (client timestamp) | 840 | | |
| loans | `loans.g6` | SSRF (document URL fetch) | 918 | | |
| loans | `loans.g7` | Unrestricted file upload / webshell | 434 | | |
| loans | `loans.g10` | XXE (XML import) | 611 | | |
| loans | `loans.l22` | Loan parameter tampering | 472 | | |
| cards | `cards.l24` | Plaintext card data (PAN + CVV), no ownership check | 311 | | |
| profile | `profile.l5` | Mass assignment + IDOR (role / is_locked bindable) | 915 / 639 | | |
| admin | `admin.l4` | Broken function-level authorization | 285 | | |
| accounts | `accounts.l19` | Hidden API fields (internal columns leaked) | 213 | | |
| core | `core.l18` | Info disclosure (verbose errors, debug config, phpinfo) | 209 / 200 | | |
| sessions | `sessions.g12` | Insecure deserialization (object injection) | 502 | | |
| templates | `templates.g11` | Local file inclusion | 98 | | |

**Loose ends found in the scan:**
- `cards.l24` also governs three further card flaws that share its one dial, with no separate key: block/unblock IDOR (CWE-639), broken PIN change with no old-PIN check (CWE-620), and spending-limit mass assignment (CWE-915). If these should be individually selectable, they each need their own key.
- `transfer.g5` (CSRF) has markers but no `isVulnerable()` dial of its own; it's tied to the CSRF class and the `transfer.l9` wizard state. Needs a dedicated level if it's to be toggled on its own.
- `g4` (reflected XSS), `l13` (reset token) and `l16` (enumeration) are marked "removed from the CTF" in the older docs but are still live in the code; `g4` and `l16` have no flag delivery.

### 2.2 Listing them all on the Vuln Config page — DONE 2026-11-05

All 37 are now in `vuln_config.json` with full metadata, added at level 0 (so runtime behaviour was unchanged), and the Vuln Config page lists all of them. The three card sub-flaws and `transfer.g5` were kept grouped as-is per Ankit's decision.

### 2.3 Built on the panel so far (2026-11-05)

- **CTF Vulnerabilities page** (`/ctf-vulns`, `src-superadmin/pages/ctf_vulns.php`): select which vulnerabilities are part of the CTF, grouped by module, with per-row checkboxes and bulk Select all / Clear all / Default 8. Selection is stored as `ctf_active` in `vuln_config.json` via `TrainingEngine::setCtfActive()`. Shows live active count and "Score available".
- **Per-level scoring** (section 1.3): implemented.
- **Enforcement**: a flag only scores if its vulnerability is `ctf_active`. This is a single authoritative gate in `tr_submit_flag()` — it works for every vuln regardless of how the flag is delivered (header, JS, DB, temp file). Verified: toggling a vuln active/inactive turns its scoring on/off live.
  - **Boundary:** enforcement is at *submission/scoring*, not *delivery*. An inactive vuln can still be exploited and may still surface a flag; it just can't be redeemed. Hiding flags/hints entirely is the mode switch's job (section 2.4).

### 2.4 Run modes — BUILT 2026-11-05

Three modes, driven from the panel, each a preset over the feature toggles.

- **Shared logic:** `src/core/Modes.php` (`Modes::presets/apply/detect/get/set/isEnabled/setEnabled`), loaded by both the CTF app and the instructor portal. App-side wrappers (`detect_mode()`, `get_app_mode()`, `flags_delivered()`) live in `helpers.php`.
- **Panel page:** `/modes` (`src-superadmin/pages/modes.php`), first in the sidebar. Three mode cards (apply a preset) plus a per-feature switch grid; the live mode is computed from the toggles (`detect`) and reads **Custom** when they match no preset. Stored label in `system_config.app_mode`.
- **Feature Toggles page removed (2026-11-05):** the old `/features` page (`features.php`) did a subset of this, so it was deleted along with its route and nav item. The Modes page now renders the full toggle list from the live `feature_toggles` table (any toggle added later shows automatically, grouped under "Other" if it has no preset metadata), with each feature's description — so it is a strict superset of the old page.
- **Two new toggles added:** `flag_delivery`, `hints` (seeded in `sql/03-feature-toggles.sql`, default enabled to preserve prior behaviour).

**Presets** (feature → on/off):

| Feature | CTF | Training | Pentest |
|---|:--:|:--:|:--:|
| leaderboard | on | off | off |
| flag_submission | on | off | off |
| flag_delivery | on | off | off |
| training_lab (lab/challenge list) | on | on | off |
| hints | on | on | off |
| code_viewer (participant source viewer) | off | on | off |
| otp_display | off | on | off |
| code_inspector | on | on | off |
| swagger_docs | on | on | on |
| request_logging | on | on | on |

**Enforcement wiring:**
- `/lab`, `/training`, `/inspector`, `/leaderboard`, `/swagger-ui`, `/api/v1/training/submit-flag` — already gated in `src/index.php` by their toggles (verified: 403 in Pentest).
- **Flag delivery** (`flag_delivery` / `flags_delivered()`): fully stripped when off —
  - the modal is a no-op (`FLAGS_ENABLED` in `layout.php`);
  - all five `X-CTF-Flag` header sites are gated;
  - the OTP-JS embed (`auth.l11`) is suppressed;
  - every JSON `ctf_flag`/`_debug_flag` field is gated (`auth/_shared.php`, `auth/api.php`, `auth/login.php`, `transfer/api.php`, `statements/_shared.php` account rows, `ctf_vault.php`);
  - every page-embedded `displayFlag(...)` script is gated (`statements/index.php`, `transfer/index.php`, `auth/login.php`).
  - **Verified:** a Pentest sweep of dashboard, statements IDOR, transfer, cards, loans, preferences, login and `otp-validation.js` shows **0** `BANKSPLOIT{` strings and **0** `X-CTF-Flag` headers; CTF shows them again.
- **Hints** (`hints`): `ctf_hint_banner()` returns empty when off.
- **Code viewer** (`code_viewer`): `/training/code` opens to any authenticated participant when on (Training), else instructors only (CTF/Pentest). Verified: CTF participant 403 / admin 200; Training participant 200.

**Verified:** Pentest → lab/leaderboard/swagger/submit 403, no flags anywhere, code viewer closed to participants; CTF → all on, flags delivered; Training → leaderboard off, hints on, code viewer open; flipping any switch → Custom. App left in CTF mode.

**`debug_mode` removed from Modes (2026-11-05):** the toggle was never wired to anything — the debug endpoints (`/api/v1/debug/*`) are governed by the `core.l18` vulnerability level (404 at level 3), set on Vuln Config. Verified the debug output leaks neither the flag secret nor any flag (only the JWT/DB secrets, which *are* the `core.l18` info-disclosure vuln). The dead toggle was dropped from the presets, the Modes page, the seed and the live DB, so it's controlled only where it belongs (its vuln level).

**Known gaps / deferred (not blocking):**
- `otp_display` has a toggle and preset value but is **not wired** — no code shows the login OTP based on it yet. Needs wiring if that teaching aid is wanted.
- Switching modes does not reset scores or progress.

## 3. Known problems

Problems 1 and 4 were fixed and retested against the running app on 2026-11-05. The others come from reading the code unless stated.

| # | Problem | Where | Status |
|---|---|---|---|
| 1 | The flag secret can be read through six open, unscored labs: `statements.g8`, `loans.g6`, `loans.g7`, `loans.g10`, `templates.g11`, `sessions.g12`. Anyone who reads it can compute every flag. Closing them was tried on 2026-11-05 and reverted at Ankit's request; the labs stay open. | `src/core/Flags.php` | **Partly fixed.** `/api/v1/debug/phpinfo` no longer shows it. Decide under CTF mode |
| 2 | Vulnerabilities missing from the config default to level 0, so they stay fully open. This includes the six labs in problem 1 and the debug endpoints (`core.l18`), which show the database password and JWT secret, a shortcut to the JWT challenge. | `src/core/TrainingEngine.php`, `src/modules/debug/api.php` | Open |
| 3 | The instructor panel has a default login, kept on purpose (Ankit's decision, 2026-11-05). `start-app.ps1` binds both servers to `0.0.0.0`; `start-app.sh` defaults to `127.0.0.1`. | `src-superadmin/core/SuperAdminAuth.php`, `start-app.ps1` | Accepted |
| 4 | `/config/vuln_config.json` was readable without login, and it contains attack scenarios. | `router.php`, `src/.htaccess` | **Fixed.** Config and source folders return 404 |
| 5 | Bulk-imported passwords follow `{customerid}B4rc@123`, so a participant can log in as another. | README, bulk import | Confirmed in README; check the code |
| 6 | Flag submission needs no proof of exploitation. | `CTF_FLAWS_TODO.md` | Known and open |
| 7 | `/training/code` showed attack scenarios and vulnerable source to any logged-in participant. Now limited to real admin logins, rejecting forged JWTs. A participant who logs in as `ADMIN001` (by password, or via the SQL injection challenge) can still open it. | `src/training/code_compare.php` | **Fixed for participants**; the `ADMIN001` route remains |

### Effect on the new modes

- **Problem 2 matters most.** "Select which vulnerabilities are open" only works if every vulnerability in the code is registered in the config and closed by default in CTF mode.
- **The six labs in problem 1 need a decision for CTF mode.** While they're open, any participant who exploits one can read the flag secret. Options to discuss: close them during events, or keep them open and accept it.
- **Problem 7 needs a mode rule.** The code viewer is now admin-only. It may need to open to participants in Training mode while staying closed in CTF mode.

### Docs that are out of date

- **Vulnerability count:** the README claims 37, the config lists 11, and 8 have their own point values. `g4`, `l13` and `l16` are marked removed from the CTF but remain in the config, so a valid flag for one would still earn the default 150 points.
- **Docker and HTTPS:** `.env` and `.env.example` describe both, but there are no Docker files and `start-app.sh` serves the app on port 9443.
- **Request Inspector:** the README says every request is logged, but logging is commented out in `src/index.php`.
- **Admin features:** the README's reset and source-download buttons are listed as blocked in `src/index.php`.
- **Windows setup:** the README still uses the old hardcoded flag secret.

---

## 4. Candidates for making it lighter

To be measured and confirmed during planning.

- **Root folder:** about 190 entries, mostly status reports, one-off exploit scripts and saved HTML responses. Archive or remove them.
- **Unrelated files in `src/`:** `Details.pdf` (about 2 MB), `Leadership_Lab_Enhanced.html`, `BankSploit_Security_Session.html`, `index_backup.html`, `index.html` and `index-printable.html`. Check whether anything uses them, then move them out of the web root.
- **Startup scripts:** `start-app.sh`, `start-app.ps1`, `start-banksploit.ps1` and `setup.ps1` overlap. Consolidate them. `start-app.ps1` also loads `sql/add-100-extended-users.sql`, which damages the `BS1004xx` data, and hides SQL errors.
- **Page load:** measure real load times first, since nothing has been measured yet.

---

## 5. Open questions

### Modes

1. Should Training mode award flags and points, or turn them off entirely?
2. In Training mode, are hints and the code viewer on for everyone, or does the instructor control them?
3. In CTF mode, what happens to a closed vulnerability: secure behaviour (level 3), or the feature hidden completely?
4. Should points change with the level chosen for a vulnerability? Scores are currently fixed per vulnerability.
5. What happens to scores and progress when the mode is switched during or after an event?
6. Should instructors be able to save configurations as named presets, such as "Beginner event"?
7. Should levels apply to everyone, or can they differ by team or batch?
8. In Pentest mode, should Swagger/API docs stay available (as a tester would normally get), or be hidden with the rest?
9. In Pentest mode, is a per-tester login still used, or one shared set of accounts? This affects whether IDOR-style flaws between accounts are reachable.
10. Is a mode best built as a named preset over the existing feature toggles, or as its own setting the toggles derive from?

### Scoring

11. ~~Which direction do points go with level?~~ **Decided 2026-11-05:** harder (higher) level = more points (+50/level); complexity sets the level-0 base. Reward skill.
12. Should every vulnerability be scored, or only a chosen set? (Pentest doesn't score; CTF-only.) **Partly answered:** every vuln now has points, and the CTF Vulnerabilities page selects which ones count via `ctf_active`.
13. When a level changes mid-event, do already-captured flags keep the points they were worth at capture time, or re-price? **Current behaviour:** captures store their points in `ctf_submissions` at capture time and are not re-priced. Confirm this is desired.
14. ~~Where do the per-level points live?~~ **Decided:** `vuln_config.json`, a `points` block per vuln.

### Scope

15. Which vulnerabilities should be selectable: the 8 scored ones, the 11 in the config, or everything in the code? The others would need flags and scoring added. (Pentest mode doesn't need flags, so it could expose more than CTF mode.)
16. What are the additional features?

### Lighter

17. What does "lighter" mean most: faster page loads, fewer files, easier setup, or lower server resource use?
18. How many participants need to be supported at once? PHP's built-in server may be a limit for larger events.

---

## 6. Suggested planning order

1. Answer the open questions in section 5.
2. List every vulnerability in the code and decide which ones are selectable.
3. Review the existing panel pages and feature toggles, and design the three-way mode switch and selection screen.
4. Agree the cleanup list for making the app lighter.
5. Break the work into phases and start development.
