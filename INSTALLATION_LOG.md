# BankSploit CTF - Installation Log

**Date:** July 19, 2026  
**Status:** ✅ COMPLETE  
**Duration:** Full automated setup

---

## What Was Done

### 1. PHP Environment Setup ✅

**Installation:**
- Located PHP at: `C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86`
- Created `php.ini` from development template
- Set correct `extension_dir` to: `ext/`

**Extensions Enabled:**
- ✅ `pdo_mysql` - MySQL database driver
- ✅ `openssl` - SSL/TLS encryption
- ✅ `mbstring` - Multi-byte string handling
- ✅ `curl` - HTTP client library

**Verification:**
```
php -v                    → PHP 8.2.32 ✅
php -m | grep pdo_mysql   → pdo_mysql ✅
php -m | grep openssl      → openssl ✅
php -m | grep mbstring     → mbstring ✅
```

---

### 2. MySQL Database Setup ✅

**Database Creation:**
```sql
CREATE DATABASE IF NOT EXISTS banksploit;
CREATE USER IF NOT EXISTS 'banksploit'@'localhost' IDENTIFIED BY 'banksploit_pass';
GRANT ALL PRIVILEGES ON banksploit.* TO 'banksploit'@'localhost';
FLUSH PRIVILEGES;
```

**Status:**
- ✅ MySQL 8.4.10 running as Windows Service (MySQL84)
- ✅ Database `banksploit` created
- ✅ User `banksploit` created with full privileges
- ✅ Password: `banksploit_pass`

---

### 3. Database Schema Loading ✅

**SQL Files Loaded (in order):**

1. **01-schema.sql** ✅
   - Tables: users, accounts, transactions, login_attempts, audit_logs, etc.
   - Foreign keys and indexes configured
   - Status: 6 tables created

2. **02-seed-data.sql** ✅
   - Test users: alice, bob, charlie, diana
   - Sample bank accounts and balances
   - Initial transaction history
   - Status: Loaded successfully

3. **03-ctf-flags.sql** ✅
   - CTF challenges and objectives
   - Flag definitions
   - Challenge descriptions
   - Status: Loaded successfully

4. **04-ctf-submissions.sql** ✅
   - Scoreboard tables
   - Submission tracking
   - Leaderboard structure
   - Status: Loaded successfully

**Total Tables Created:** 18 tables ✅

**Verification:**
```sql
SELECT COUNT(*) FROM information_schema.tables 
WHERE table_schema = 'banksploit';
Result: 18 ✅
```

---

### 4. Application Scripts Created ✅

**setup.ps1**
- Purpose: Database initialization and schema loading
- Status: ✅ Successfully executed
- Uses: MySQL CLI with credentials
- Can be re-run for database reset

**start-app.ps1**
- Purpose: Start and manage PHP servers
- Features:
  - Starts PHP app server on port 9443
  - Starts PHP admin server on port 8888
  - Manages MySQL service
  - Generates FLAG_SECRET for security
  - Proper process management with PID files
  - Status, start, stop, and help commands
- Status: ✅ Created and tested

---

### 5. Environment Configuration ✅

**Environment Variables Set:**
```
DB_HOST           = 127.0.0.1
DB_PORT           = 3306
DB_NAME           = banksploit
DB_USER           = banksploit
DB_PASS           = banksploit_pass
APP_SCHEME        = http
APP_HOST          = localhost
APP_PORT          = 9443
APP_ENV           = development
APP_DEBUG         = true
JWT_SECRET        = insecure_jwt_secret_for_training
SUPERADMIN_PORT   = 8888
FLAG_SECRET       = [Generated and stored]
```

**Generated Files:**
- `.env` ✅ - Copied from `.env.example`
- `.local/.env` ✅ - Contains database init script
- `~/.banksploit/flag_secret` ✅ - Generated security secret

---

### 6. Documentation Created ✅

**Setup Documentation:**
- ✅ `SETUP_GUIDE.md` - Original setup guide (updated)
- ✅ `SETUP_COMPLETE.md` - Comprehensive setup details
- ✅ `README_SETUP.md` - User-friendly guide
- ✅ `QUICK_START.txt` - Quick reference card
- ✅ `INSTALLATION_LOG.md` - This file

**Existing Documentation Preserved:**
- ✅ `README.md` - Project overview
- ✅ `CREDENTIALS.md` - Test accounts
- ✅ `CTF_DESIGN_DECISIONS.md` - Architecture
- ✅ `CTF_WALKTHROUGH.html` - Challenge guide
- ✅ `VULN_MATRIX.html` - Vulnerability matrix
- ✅ `BankSploit_Pentest_Report.html` - Security assessment

---

### 7. Servers Started and Verified ✅

**PHP App Server:**
- ✅ Started on port 9443
- ✅ Process ID: 24168
- ✅ Base path: `src/`
- ✅ URL: http://localhost:9443

**PHP Admin Server:**
- ✅ Started on port 8888
- ✅ Process ID: 18680
- ✅ Base path: `src-superadmin/`
- ✅ URL: http://localhost:8888

**MySQL Server:**
- ✅ Running as service MySQL84
- ✅ Port: 3306
- ✅ Database verified: 18 tables
- ✅ User verified: banksploit@localhost

---

## System Configuration Summary

### Hardware/OS
- **OS:** Windows 11 Enterprise 10.0.26100
- **Platform:** Win32
- **Shell:** bash (via Git Bash)
- **PowerShell:** Available for scripts

### Software Stack
| Component | Version | Status | Location |
|-----------|---------|--------|----------|
| PHP | 8.2.32 NTS x86 | ✅ Ready | C:\Users\...\php-8.2.32-nts-Win32-vs16-x86 |
| MySQL | 8.4.10 | ✅ Running | Program Files\MySQL\MySQL Server 8.4 |
| Database | banksploit | ✅ Ready | localhost:3306 |
| pdo_mysql | Latest | ✅ Enabled | ext/php_pdo_mysql.dll |
| openssl | Latest | ✅ Enabled | ext/php_openssl.dll |
| mbstring | Latest | ✅ Enabled | ext/php_mbstring.dll |

### Network Configuration
- **App Server:** localhost:9443 (or 10.141.199.78:9443)
- **Admin Panel:** localhost:8888 (or 10.141.199.78:8888)
- **MySQL:** localhost:3306 (internal only)

---

## Troubleshooting Performed

### Issue 1: PHP Extensions Not Found ❌→✅
- **Problem:** PHP was looking in C:\php\ext instead of actual location
- **Solution:** Set correct `extension_dir` in php.ini
- **Result:** All extensions now load correctly

### Issue 2: User Creation SQL Syntax Error ❌→✅
- **Problem:** PowerShell here-string syntax had issues
- **Solution:** Created SQL file and used pipe method
- **Result:** Database and user created successfully

### Issue 3: PHP Extension Missing ❌→✅
- **Problem:** RandomNumberGenerator method not available
- **Solution:** Used RNGCryptoServiceProvider instead
- **Result:** FLAG_SECRET generated correctly

---

## Final Checklist

- ✅ PHP 8.2.32 installed and configured
- ✅ MySQL 8.4.10 database created
- ✅ Database schema loaded (18 tables)
- ✅ Test data seeded
- ✅ CTF challenges loaded
- ✅ Scoreboard initialized
- ✅ Environment variables configured
- ✅ PHP app server ready (port 9443)
- ✅ PHP admin server ready (port 8888)
- ✅ MySQL service running
- ✅ All documentation created
- ✅ Startup scripts working
- ✅ Network access verified
- ✅ Credentials configured

---

## How to Start

### Quick Start
```powershell
cd C:\Users\G01197938\Downloads\banksploit-ctf
.\start-app.ps1 start
```

### Access
- App: http://localhost:9443
- Admin: http://localhost:8888

### Credentials
- Admin Username: instructor
- Admin Password: instructor@123
- DB User: banksploit
- DB Pass: banksploit_pass

---

## System Ready ✅

All components are installed, configured, and ready for use. The BankSploit CTF application is fully set up and can be started immediately with the provided startup scripts.

**Next Step:** Run `.\start-app.ps1 start` and visit http://localhost:9443

---

**Installation Date:** July 19, 2026  
**Installation By:** Claude Code  
**Status:** ✅ COMPLETE AND VERIFIED
