# BankSploit CTF — Windows Server Setup Guide

## Requirements

| Software | Version | Purpose |
|----------|---------|---------|
| PHP | 8.3+ (8.4 recommended) | Application server (built-in web server) |
| MySQL | 8.0+ (8.4 works) | Database |
| PowerShell | 5.1+ (pre-installed on Windows) | Start/stop script |

> **No web server (Apache/Nginx/IIS) needed** — PHP's built-in server handles everything.

---

## Downloads

### 1. PHP 8.4 (Non Thread Safe, x64, ZIP)

**URL:** https://windows.php.net/download/

Download: **VS17 x64 Non Thread Safe** → **Zip** (~30 MB)

- ✅ Non Thread Safe (NTS) — required for PHP built-in server
- ✅ x64 — 64-bit Windows
- ✅ ZIP — no installer needed, just extract
- ❌ Do NOT pick "Thread Safe" (that's for Apache mod_php)
- ❌ Do NOT pick "Source code" or "Tests package"

### 2. MySQL 8.0+ (Community Server)

**URL:** https://dev.mysql.com/downloads/installer/

Download: **MySQL Installer for Windows** (mysql-installer-community-8.x.x.msi)

Or for a portable install (no admin rights):  
**URL:** https://dev.mysql.com/downloads/mysql/  
Download: **Windows (x86, 64-bit), ZIP Archive**

### 3. Visual C++ Redistributable (required by PHP)

**URL:** https://aka.ms/vs/17/release/vc_redist.x64.exe

PHP 8.4 requires the **Visual Studio 2022 (VS17)** C++ runtime. If you get a "VCRUNTIME140.dll not found" error, install this.

---

## Installation Steps

### Step 1: Install PHP

1. Extract the PHP ZIP to `C:\php`
2. Add `C:\php` to the system `PATH`:
   - Win+R → `sysdm.cpl` → Advanced → Environment Variables
   - Edit `Path` under System variables → Add `C:\php`
3. Copy `php.ini-development` to `php.ini`:
   ```powershell
   copy C:\php\php.ini-development C:\php\php.ini
   ```
4. Edit `C:\php\php.ini` — uncomment these extensions (remove the leading `;`):
   ```ini
   extension=pdo_mysql
   extension=openssl
   extension=mbstring
   extension=curl
   ```
5. **IMPORTANT:** Do NOT add `exec` or `shell_exec` to `disable_functions` — they are needed for the G9 command injection challenge.
6. Verify:
   ```powershell
   php -v
   php -m | findstr pdo_mysql
   ```

### Step 2: Install MySQL

**Option A — MSI Installer (recommended):**
1. Run the MySQL Installer
2. Choose "Server only" (or "Custom" → MySQL Server)
3. Use defaults (port 3306, root password — remember it)
4. It installs as a Windows Service (auto-starts on boot)

**Option B — ZIP (portable, no admin):**
1. Extract to `C:\mysql`
2. Initialize:
   ```powershell
   cd C:\mysql\bin
   .\mysqld --initialize-insecure --basedir=C:\mysql --datadir=C:\mysql\data
   ```
3. Start manually:
   ```powershell
   .\mysqld --console
   ```

### Step 3: First-Time Setup

```powershell
cd C:\path\to\banksploit-ctf
.\start.ps1 setup
```

This creates the database, user, schema, and seeds CTF flags.

### Step 4: Start the App

```powershell
.\start.ps1 start
```

Output will show:
```
  CTF App (local):    http://localhost:9443
  CTF App (network):  http://<your-ip>:9443

  Control Panel:      http://<your-ip>:8888  (instructor / instructor@123)
```

---

## Firewall (if participants can't connect)

Run PowerShell **as Administrator**:

```powershell
netsh advfirewall firewall add rule name="BankSploit CTF" dir=in action=allow protocol=TCP localport=9443
netsh advfirewall firewall add rule name="BankSploit Admin" dir=in action=allow protocol=TCP localport=8888
```

---

## Required PHP Extensions (verify with `php -m`)

| Extension | Purpose |
|-----------|---------|
| `pdo_mysql` | Database connectivity |
| `openssl` | JWT signing, HTTPS |
| `mbstring` | String handling |
| `curl` | Optional (future use) |

---

## Ports

| Port | Service |
|------|---------|
| 9443 | CTF App (participants) |
| 8888 | Superadmin/Instructor Panel |
| 3306 | MySQL (local only, not exposed) |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `php` not recognized | Add `C:\php` to PATH, restart PowerShell |
| `VCRUNTIME140.dll not found` | Install VC++ Redistributable (link above) |
| `mysql` not recognized | Add `C:\Program Files\MySQL\MySQL Server 8.x\bin` to PATH |
| Port 9443 in use | Change `$AppPort` in `start.ps1` |
| Participants can't connect | Run the firewall commands above (as Admin) |
| MySQL won't start | Check if it's already running as a service: `Get-Service MySQL*` |

---

## Commands Reference

```powershell
.\start.ps1 setup       # First-time: create DB, load schema, seed flags
.\start.ps1 start       # Start MySQL + PHP servers
.\start.ps1 stop        # Stop PHP servers (MySQL left as service)
.\start.ps1 status      # Check what's running
.\start.ps1 reset       # Clear scoreboard (with backup option)
.\start.ps1 restore     # Restore scoreboard from backup
.\start.ps1 reset-full  # Nuclear: drop & recreate entire DB
.\start.ps1 help        # Show help
```
