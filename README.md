# BankSploit

An **intentionally vulnerable** banking web application for hands-on security
training. It implements a realistic Indian retail-banking app (accounts,
transfers, beneficiaries, cards, loans, bill payments, admin) on **PHP 8.3 /
MySQL 8**, where every vulnerability has a configurable difficulty level
(0 = fully vulnerable → 3 = secure) so the same feature can be exploited, then
patched, then re-tested.

> ⚠️ **For local training only.** This app is deliberately insecure. Never
> deploy it on a public network, expose it to the internet, or load it with
> real data.

---

## Setup

### 1. Prerequisites

| Dependency | Install |
|------------|---------|
| **PHP 8.3 or newer** (tested on 8.5) | `brew install php` (macOS) / `sudo apt install php8.3` (Linux) / [windows.php.net](https://windows.php.net/download/) (Windows) |
| **MySQL 8.0** | [MySQL installer](https://dev.mysql.com/downloads/) or `brew install mysql@8.0` (macOS) / `sudo apt install mysql-server` (Linux) / [dev.mysql.com](https://dev.mysql.com/downloads/) (Windows) |

Required PHP extensions: `pdo_mysql`, `xml`, `mbstring`, `json` (all included
in standard PHP packages).

MySQL must already be running. The script doesn't start or stop it, because it's
a shared system service.

### 2. First-time setup

```bash
./start-app.sh setup
```

This asks for your MySQL root password, which isn't saved. It then creates the
database and user, loads the schema and seed data, and seeds CTF flags. It's only
needed once, and it won't change a database that's already set up.

### 3. Start the app

```bash
./start-app.sh start
```

| URL | What |
|-----|------|
| **http://localhost:9443** | The CTF app |
| **http://localhost:8888** | CTF Control Panel (instructor portal) |

### Commands

| Command | What it does |
|---------|--------------|
| `./start-app.sh start` | Start the banking app and instructor panel |
| `./start-app.sh stop` | Stop both servers (MySQL keeps running) |
| `./start-app.sh restart` | Stop, then start |
| `./start-app.sh status` | Show what's running |
| `./start-app.sh reset` | Clear the scoreboard, with an optional backup |
| `./start-app.sh restore` | Restore submissions from that backup |
| `./start-app.sh reset-full` | Drop and rebuild the whole database (asks for the root password) |

### Running an event on your network

Both servers listen only on your own machine by default. To let participants
connect, open the banking app to your network:

```bash
BIND_HOST=0.0.0.0 ./start-app.sh start
```

The instructor panel stays local unless you also set `SUPERADMIN_BIND=0.0.0.0`.
Only do this on a trusted, private network, because the app is deliberately
vulnerable.

You can also override `APP_PORT`, `SUPERADMIN_PORT`, `DB_HOST`, `DB_PORT` and
`PHP_WORKERS` (default 8) the same way.

### Windows users

Use `start-app.ps1`, which has the same commands (`restart` isn't included):

```powershell
.\start-app.ps1 setup
.\start-app.ps1 start
```

Before the first run, edit these values for your machine, because they are
hardcoded in the script:

- `$PhpPath` and `$MysqlPath` near the top, plus `$MysqlBin`, which appears in
  several functions.
- The MySQL root password, currently `-proot`.

`start-app.ps1` also differs from `start-app.sh` in three ways:

- It binds both servers to `0.0.0.0`, so they're open to your network.
- It stops MySQL when you run `stop`.
- It loads every file in `sql/`, including `add-100-extended-users.sql`, which
  replaces the full `BS1004xx` user data with a smaller set.

---

## Default credentials

### Banking App (port 9443)

| Customer ID | Password      | Role  |
|-------------|---------------|-------|
| `BS100234`  | `password123` | user (Rahul Sharma) |
| `BS100235`  | `priya@123`   | user (Priya Menon)  |
| `BS100236`  | `vikram@123`  | user (Vikram Singh) |
| `BS100237`  | `aditya@123`  | user (Aditya Rao)   |
| `BS100238`  | `1234`        | user (Ankit)        |
| `ADMIN001`  | `admin@123`   | admin (Admin User)  |

Login is two-factor: enter the Customer ID + password, then a 4-digit OTP. In
**Lab Mode** the dev OTP is shown on screen; otherwise read it from the login
API response (that's the `auth.l11` "OTP in response" vulnerability) or from the
`otp_tokens` table.

### CTF Control Panel (port 8888)

| Username     | Password         | Role       |
|--------------|------------------|------------|
| `instructor` | `instructor@123` | CTF Admin  |

This is the **instructor-only** management portal — separate from the banking
app. Used to manage participants, view flags, configure vulnerabilities, track
progress, and monitor the leaderboard.

---

## Key URLs

| Path             | What                                            |
|------------------|-------------------------------------------------|
| `/login`         | Sign in                                         |
| `/dashboard`     | Accounts + recent transactions                  |
| `/transfer`      | Fund-transfer wizard                            |
| `/beneficiaries` | Manage payees                                   |
| `/cards`         | Cards                                           |
| `/loans`         | Loan application + EMI calculator               |
| `/payments`      | Bill payments                                   |
| `/statements`    | Statements (filter / export)                    |
| `/preferences`   | User preferences (deserialization lab)          |
| `/pages?page=`   | Help pages (local file inclusion lab)           |
| `/lab`           | **Lab Mode** dashboard — vuln list, levels, CTF |
| `/inspector`     | **Request Inspector** — live request/response log |
| `/training/code` | Side-by-side vulnerable vs. secure code viewer  |
| `/admin`         | Admin panel (users, config, audit, reset DB)    |
| `/swagger-ui`    | Interactive API docs (OpenAPI 3.0)              |

---

## How the training works

- **Levels.** Every vulnerability has a level 0–3. Set them globally or
  per-page from the Lab controls in the navbar, per-vulnerability in the Lab
  dashboard, or from the **Admin → Vuln Config** panel (with presets:
  Beginner / Intermediate / Advanced / Secure).
- **Lab Mode toggle.** Turns the yellow hint bars on/off. The per-page
  *difficulty* selector stays visible even with Lab off.
- **Hints.** Progressive (1 → 2 → 3) per vulnerability via the Lab dashboard.
- **CTF flags.** Each vuln has a flag `BANKSPLOIT{...}` unique to the user;
  submit it in the Lab dashboard's Challenge mode.
- **Code viewer.** Click any vulnerability to see its vulnerable and secure
  source side by side, with the exact lines highlighted.
- **Request Inspector.** Every request is logged; inspect headers, bodies, and
  user-controlled fields, with hints on what to tamper.
- **Reset Database.** Admin panel button restores the clean seed state after
  you've drained accounts / dropped webshells / etc.
- **Download Vulnerable Source.** Admin panel button zips all `*_vuln.php` /
  `*_secure.php` files for offline review.

---

## Instructor Portal — Bulk User Import

The **Bulk User Import** feature (available in the CTF Control Panel at `http://localhost:8888 → Bulk Import`) lets instructors create multiple user accounts at once from CSV or Excel files.

### File Format

Upload a CSV (`.csv`) or Excel (`.xlsx`) file with the following columns:

| Column      | Required | Description                                |
|-------------|----------|---------------------------------------------|
| Full Name   | Yes      | User's full name (e.g., "John Doe")       |
| Customer ID | Yes      | Unique customer ID (e.g., "BS100001")     |

### Example CSV

```
Full Name,Customer ID
John Doe,BS100001
Jane Smith,BS100002
Alice Johnson,BS100003
```

A sample CSV file is available at [`samples/bulk_import_sample.csv`](samples/bulk_import_sample.csv) that you can use as a template.

### Auto-Generated Fields

When you import users, the following are created automatically:

- **Password**: `{customerid}B4rc@123`
  - Example: For customer ID `BS100001`, password is `BS100001B4rc@123`
  - This allows easy onboarding — participants can use their customer ID to derive their password
  
- **Email**: Auto-generated from name + customer ID
  - Example: `john.doe.BS100001@banksploit.local`
  
- **Phone**: Placeholder (`9999999999`)

- **Role**: Always `user` (participants, not admins)

### Usage

1. Open the CTF Control Panel: `http://localhost:8888`
2. Log in with instructor credentials (default: `instructor` / `instructor@123`)
3. Click **Bulk Import** in the navigation menu
4. Prepare your CSV/Excel file with Full Name and Customer ID columns
5. Click "Select file" and upload
6. Review the import results (success/failure count per row)
7. Users are created immediately upon successful import

### Notes

- Customer IDs must be **unique** — duplicates are rejected
- Email conflicts are resolved automatically with a timestamp suffix
- To manage created users, visit the **Users** page in the CTF Control Panel to view, lock, suspend, or reset passwords

---

## Vulnerability coverage

37 vulnerabilities across two families:

- **G1–G12 — Generic web** (in a banking context): SQL injection (login &
  extraction), stored & reflected XSS, CSRF, SSRF, unrestricted file upload,
  path traversal, command injection, XXE, local file inclusion, insecure
  deserialization.
- **L1–L25 — Banking logic**: IDOR (accounts / receipts / beneficiaries),
  broken function-level authorization, mass assignment, negative-amount
  transfer, race-condition double-spend, cooling-period bypass, wizard
  step-skipping, OTP brute-force & OTP-in-response, JWT `alg:none`, predictable
  reset tokens, session fixation, account enumeration, PII in JWT, verbose
  errors, hidden API fields, rounding/salami, daily-limit bypass, loan
  parameter tampering, self-transfer cashback abuse, plaintext card data,
  session puzzling (email-only account takeover via the reset flow).

Full metadata (OWASP category, CWE, hints, flag) lives in
[`src/config/vuln_config.json`](src/config/vuln_config.json).

### Flag secret

Flags are generated from a random secret stored in `~/.banksploit/flag_secret`,
outside the project. Don't put it in an environment variable, because the
`phpinfo()` lab prints the environment. The startup scripts create the file. If
you delete it, every flag changes, so run `./start-app.sh reset-full` afterwards.

The path traversal, SSRF, XXE, file inclusion, file upload and deserialization
labs are open and can reach this file. A participant who exploits one can read
the secret and compute every flag.

---

## Architecture

```
src/
  index.php            front controller + router + bootstrap
  config/              app.php, database.php, vuln_config.json
  core/                TrainingEngine, Auth, Session, CSRF, RequestLogger, Router, helpers
  modules/             auth, dashboard, statements, accounts, transfer, cards,
                       loans, payments, admin, pages, prefs, debug, misc
  training/            lab, code viewer, inspector, hints/progress/flags API
  templates/layout.php Tailwind app shell
  api/swagger.json     OpenAPI 3.0 spec
  public/              css/style.css
sql/
  01-schema.sql        schema (runs during setup)
  02-seed-data.sql     seed data
src-superadmin/        CTF Control Panel (instructor-only, port 8888)
start-app.sh           Setup, start, stop and reset script (macOS/Linux)
start-app.ps1          Same script for Windows
```

- **TrainingEngine** (singleton) reads `vuln_config.json` and every module
  branches on `getVulnLevel()` / `isVulnerable()` between vulnerable and secure
  code paths. Levels persist back to the JSON file.
- Each vuln is split into `*_vuln.php` (levels 0–2) and `*_secure.php`
  (level 3) with `// @vuln-start` / `// @fix-start` markers the code viewer parses.

---

## Troubleshooting

| Symptom | Cause & fix |
|---------|-------------|
| **Port is already in use** (9443/8888) | Stop whatever holds the port, or choose another: `APP_PORT=9444 ./start-app.sh start`. |
| **MySQL isn't reachable** | Start it. For the macOS installer version: `sudo /usr/local/mysql/support-files/mysql.server start`. |
| **`mysql` client not found** | The script checks your PATH, `/usr/local/mysql/bin` and Homebrew. If yours is elsewhere, add it to your PATH. |
| **PHP server fails** | Ensure PHP 8.3+ is on PATH with PDO MySQL: `php -m \| grep pdo_mysql`. Details are in `.local/php-server.log`. |
| **Login works but pages look broken** | Hard-refresh (Ctrl-F5). Assets are self-hosted; stale cache can mismatch. |
| **Need a clean slate after testing** | Run `./start-app.sh reset-full`. |

---

## Notes & conventions

- **Line endings:** all files use **LF**.
- **No frameworks**, no Composer, no Redis — vanilla PHP by design.
- `./src` edits apply immediately (PHP built-in server re-reads on each
  request). Only `start-app.sh` changes need a restart.
- `wkhtmltopdf` is not installed; the PDF-export command-injection lab still
  demonstrates the flaw (the injected command runs regardless).
