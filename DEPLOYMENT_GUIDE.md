# BankSploit CTF — Complete New System Deployment Guide

**Date:** July 20, 2026  
**Purpose:** Step-by-step instructions to deploy BankSploit on a new Windows system

---

## 📋 Pre-Deployment Checklist

- [ ] Windows 11/Server 2019+ (PowerShell 5.1+)
- [ ] Administrator access for firewall rules
- [ ] ~500 MB disk space (PHP + MySQL)
- [ ] Network access to download PHP & MySQL
- [ ] 2+ CPU cores, 2GB+ RAM (minimum)

---

## 🎯 Deployment Overview

| Phase | Task | Time |
|-------|------|------|
| **1. Prerequisites** | Download PHP, MySQL, Visual C++ runtime | 15 min |
| **2. PHP Installation** | Extract, configure, verify | 10 min |
| **3. MySQL Installation** | Install service, verify connectivity | 15 min |
| **4. MySQL Root Password** | Set root password in script (CRITICAL) | 5 min |
| **5. Project Setup** | Clone/copy BankSploit, run setup script | 10 min |
| **6. Firewall** | Allow ports 9443 & 8888 | 5 min |
| **7. Verify** | Test login & functionality | 5 min |
| **Total** | | ~65 min |

---

## 🔧 Phase 1: Download Prerequisites

### Download 1: PHP 8.4 (Non-Thread-Safe)
- **URL:** https://windows.php.net/download/
- **Pick:** `VS17 x64 Non Thread Safe` → `Zip` (~30 MB)
- **Save to:** `Downloads\php-8.4-nts-x64.zip`
- **WHY:** NTS required for PHP built-in server; x64 for 64-bit Windows

### Download 2: MySQL 8.4 Community Server
- **URL:** https://dev.mysql.com/downloads/installer/
- **Pick:** `MySQL Installer for Windows` (mysql-installer-community-8.4.x.msi)
- **Save to:** `Downloads\mysql-installer-8.4.x.msi`
- **Alternative (portable):** MySQL ZIP from https://dev.mysql.com/downloads/mysql/

### Download 3: Visual C++ Redistributable (if needed)
- **URL:** https://aka.ms/vs/17/release/vc_redist.x64.exe
- **When:** Only if you get `VCRUNTIME140.dll not found` errors
- **Install:** Run the .exe

---

## 💾 Phase 2: Install PHP

### Step 2.1 — Extract PHP
```powershell
# Create PHP directory
mkdir C:\php

# Extract downloaded zip to C:\php
# (Use Windows Explorer or: Expand-Archive C:\Users\YourUser\Downloads\php-8.4-nts-x64.zip -DestinationPath C:\php)
```

### Step 2.2 — Add PHP to System PATH
1. Press `Win+R` → type `sysdm.cpl` → press Enter
2. Click **Advanced** tab → **Environment Variables**
3. Under **System variables**, find `Path` → click **Edit**
4. Click **New** → paste: `C:\php`
5. Click **OK** three times
6. **Restart PowerShell/CMD** (close and reopen)

### Step 2.3 — Create php.ini
```powershell
cd C:\php
copy php.ini-development php.ini
```

### Step 2.4 — Edit php.ini (Enable Required Extensions)
Open `C:\php\php.ini` in Notepad and find these lines (uncomment by removing `;`):

```ini
; Find these lines and remove the leading semicolon:
extension=pdo_mysql
extension=openssl
extension=mbstring
extension=curl

; IMPORTANT: Do NOT add exec or shell_exec to disable_functions
; They are needed for the G9 command injection challenge
```

### Step 2.5 — Verify PHP Installation
```powershell
php -v
php -m | findstr pdo_mysql
```

**Expected output:**
- PHP version 8.4.x (or similar)
- `pdo_mysql` in the module list

---

## 🗄️ Phase 3: Install MySQL

### Option A: MSI Installer (Recommended)

1. Run `mysql-installer-8.4.x.msi`
2. Choose **Server only** (or **Custom** → MySQL Server only)
3. Click **Next** through setup wizard
4. **IMPORTANT:** When asked for root password:
   - Set a strong password (e.g., `MySQL_Root_P@ssw0rd_2024`)
   - **Remember this password** — you'll need it in Phase 4
   - Write it down: `_______________________`
5. Click **Finish**
6. MySQL installs as Windows Service (auto-starts on boot)

### Option B: ZIP (Portable, No Admin Rights)

```powershell
# Extract to C:\mysql
mkdir C:\mysql
# Extract the zip to C:\mysql

# Initialize database
cd C:\mysql\bin
.\mysqld --initialize-insecure --basedir=C:\mysql --datadir=C:\mysql\data

# Start manually (console window stays open)
.\mysqld --console
```

### Step 3.2 — Verify MySQL
```powershell
mysql -u root -p
# When prompted for password, enter the password you set
# Type: SHOW DATABASES;
# Type: EXIT;
```

---

## 🔐 Phase 4: Configure MySQL Root Password in Script (CRITICAL)

This is the step that fixes "ERROR: Access denied for user 'root'@'localhost'"

### Step 4.1 — Locate start-app.ps1

```powershell
cd C:\path\to\banksploit-ctf
# Or wherever you copied the project
```

### Step 4.2 — Edit start-app.ps1

Open `start-app.ps1` in a text editor (Notepad++ or VS Code recommended).

**Find line ~176:**
```powershell
$mysqlCmd | mysql -u root -h $DbHost
```

**Change to:**
```powershell
$mysqlCmd | mysql -u root -p"YOUR_ROOT_PASSWORD" -h $DbHost
```

**Example:**
```powershell
$mysqlCmd | mysql -u root -p"MySQL_Root_P@ssw0rd_2024" -h $DbHost
```

---

### Step 4.3 — Update Lines 518-519 (Database Reset Functions)

**Find lines ~518-519:**
```powershell
"DROP DATABASE IF EXISTS ``$DbName``; CREATE DATABASE ``$DbName``;" | mysql -u root
"GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';" | mysql -u root
```

**Change both to:**
```powershell
"DROP DATABASE IF EXISTS ``$DbName``; CREATE DATABASE ``$DbName``;" | mysql -u root -p"YOUR_ROOT_PASSWORD"
"GRANT ALL PRIVILEGES ON ``$DbName``.* TO '$DbUser'@'localhost';" | mysql -u root -p"YOUR_ROOT_PASSWORD"
```

---

### Step 4.4 — Update PHP Path (if not default)

Check line ~32:
```powershell
$PhpPath = "C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86"
```

If you installed PHP to a different path, update this line. **Example:**
```powershell
$PhpPath = "C:\php"  # If you extracted to C:\php
```

---

### Step 4.5 — Update MySQL Path (if not default)

Check line ~33:
```powershell
$MysqlPath = "C:\Program Files\MySQL\MySQL Server 8.4\bin"
```

If you used a different MySQL path, update this. **Common paths:**
- MSI default: `C:\Program Files\MySQL\MySQL Server 8.4\bin`
- ZIP portable: `C:\mysql\bin`

---

### Step 4.6 — Save start-app.ps1

Save the file with your changes. Use Ctrl+S in most editors.

---

## 🚀 Phase 5: First-Time Project Setup

### Step 5.1 — Verify Dependencies

```powershell
cd C:\path\to\banksploit-ctf

# Verify PHP is found
php -v

# Verify MySQL is found
mysql --version
```

### Step 5.2 — Run Setup Script

```powershell
.\start-app.ps1 setup
```

**What this does:**
1. Creates database `banksploit`
2. Creates database user `banksploit` / `banksploit_pass`
3. Loads schema from `sql/` directory
4. Generates FLAG_SECRET (stored in `~\.banksploit\flag_secret`)
5. Seeds CTF flags and participant accounts

**Expected output:**
```
[OK] Database 'banksploit' created
[OK] User 'banksploit' created
[OK] Schema loaded successfully
```

**If errors occur:**
- Most common: "Access denied for user 'root'" → Verify root password in Step 4.2
- "MySQL not running" → Start MySQL service manually first
- "PHP not found" → Add PHP to PATH and restart PowerShell

---

## 🔥 Phase 6: Configure Windows Firewall

**Required if participants will access from other machines.**

Open PowerShell **as Administrator**:

```powershell
# Allow CTF App (port 9443)
netsh advfirewall firewall add rule name="BankSploit CTF" dir=in action=allow protocol=TCP localport=9443

# Allow Superadmin Panel (port 8888)
netsh advfirewall firewall add rule name="BankSploit Admin" dir=in action=allow protocol=TCP localport=8888
```

---

## ✅ Phase 7: Verify Installation

### Step 7.1 — Start Services

```powershell
.\start-app.ps1 start
```

**Expected output:**
```
[OK] MySQL already running
[OK] PHP app server started on 0.0.0.0:9443
[OK] Superadmin started on 0.0.0.0:8888
```

### Step 7.2 — Test Local Access

Open a browser and navigate to:

**CTF App (Participants):**
```
http://localhost:9443
```

**Superadmin Panel (Instructor):**
```
http://localhost:8888
```

Login with: `instructor` / `instructor@123`

### Step 7.3 — Test Network Access (from another machine)

Get your machine's IP:
```powershell
ipconfig
# Look for IPv4 Address (e.g., 192.168.1.100)
```

From another machine, visit:
```
http://192.168.1.100:9443    # CTF App
http://192.168.1.100:8888    # Superadmin Panel
```

---

## 📝 Default Credentials After Setup

### Instructor/Superadmin
- **URL:** `http://localhost:8888`
- **Username:** `instructor`
- **Password:** `instructor@123`

### Sample Participant Accounts
| Name | Customer ID | Password |
|------|-------------|----------|
| Rahul Sharma | BS100234 | password123 |
| Priya Menon | BS100235 | priya@123 |
| Vikram Singh | BS100236 | vikram@123 |

All passwords are hashed with bcrypt (and intentionally also MD5 for CTF challenges).

---

## 🎮 Common Commands

```powershell
# Start services
.\start-app.ps1 start

# Stop services (leaves MySQL running as service)
.\start-app.ps1 stop

# Check status
.\start-app.ps1 status

# Clear scoreboard (keep database schema)
.\start-app.ps1 reset

# Full reset (DROP and recreate database)
.\start-app.ps1 reset-full

# Help
.\start-app.ps1 help
```

---

## 🚨 Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| **"mysql: Access denied for user 'root'@'localhost'"** | Root password not set in script | Re-do Phase 4, verify password is correct |
| **"php not recognized"** | PHP not in PATH | Add `C:\php` to PATH and restart PowerShell |
| **"VCRUNTIME140.dll not found"** | Missing Visual C++ runtime | Install VC++ Redistributable (Phase 1) |
| **"Port 9443 already in use"** | Another app on that port | Edit `$AppPort` in start-app.ps1 |
| **"Participants can't connect"** | Firewall blocking | Run firewall rules (Phase 6) |
| **"MySQL service won't start"** | Service failed | Manually start from Services.msc or use ZIP portable option |
| **"OTP not sending to participants"** | Database not loaded | Re-run `.\start-app.ps1 setup` |

---

## 📊 Architecture After Deployment

```
Participant Machine                    Server Machine
    ↓                                       ↓
Browser (http://server:9443) ────────→ PHP Server (0.0.0.0:9443)
                                       ↓
                                    MySQL (127.0.0.1:3306)
                                    
Instructor Machine
    ↓
Browser (http://server:8888) ────────→ Superadmin (0.0.0.0:8888)
                                       ↓
                                    PHP Router
                                       ↓
                                    MySQL (127.0.0.1:3306)
```

---

## 📞 Support

For issues:
1. Check the **Troubleshooting** section above
2. Verify all paths in `start-app.ps1` match your installation
3. Check MySQL service status: `Get-Service MySQL*`
4. Check ports: `netstat -ano | findstr 9443`
5. Review firewall rules: `netsh advfirewall firewall show rule name="BankSploit"`

---

## ✨ Next Steps After Deployment

1. **Create participant accounts** via Superadmin → Users panel
2. **Configure vulnerability levels** via Superadmin → Training
3. **Enable/disable hints** via Superadmin → Hints
4. **Share CTF URLs** with participants (with instructions)
5. **Monitor leaderboard** for flag submissions and rankings

---

**Deployment Status:** Ready for production CTF deployment 🎉

