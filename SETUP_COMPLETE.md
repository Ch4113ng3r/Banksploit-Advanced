# ✅ BankSploit CTF - Setup Complete!

**Date:** July 19, 2026  
**Status:** ✅ All systems ready

---

## 📋 What Was Set Up

### 1. ✅ PHP Environment
- **Version:** PHP 8.2.32 (NTS, x86)
- **Location:** `C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86`
- **Configuration:** `php.ini` created with required extensions:
  - ✅ pdo_mysql
  - ✅ openssl
  - ✅ mbstring
  - ✅ curl

### 2. ✅ MySQL Database
- **Version:** MySQL 8.4.10
- **Status:** Running as Windows Service (MySQL84)
- **Database:** `banksploit`
- **User:** `banksploit`
- **Password:** `banksploit_pass`
- **Tables Loaded:** 18 tables

### 3. ✅ Database Schema
All SQL files successfully loaded:
- ✅ `01-schema.sql` - Database structure
- ✅ `02-seed-data.sql` - User accounts and data
- ✅ `03-ctf-flags.sql` - CTF challenges and flags
- ✅ `04-ctf-submissions.sql` - Scoreboard

### 4. ✅ Application Scripts
- **setup.ps1** - Database setup (completed)
- **start-app.ps1** - Start/stop application servers

---

## 🚀 Servers Currently Running

Both PHP development servers are running:

### CTF Application
- **URL:** http://localhost:9443
- **Network:** http://10.141.199.78:9443
- **Port:** 9443
- **PID:** 24168

### Admin Control Panel
- **URL:** http://localhost:8888
- **Network:** http://10.141.199.78:8888
- **Port:** 8888
- **Credentials:** instructor / instructor@123
- **PID:** 18680

### MySQL Database
- **Status:** Running (Windows Service MySQL84)
- **Port:** 3306
- **Credentials:** root / root

---

## 📝 Environment Variables

Configured for the application:

```
DB_HOST         = 127.0.0.1
DB_PORT         = 3306
DB_NAME         = banksploit
DB_USER         = banksploit
DB_PASS         = banksploit_pass
APP_SCHEME      = http
APP_HOST        = localhost
APP_PORT        = 9443
APP_ENV         = development
APP_DEBUG       = true
JWT_SECRET      = insecure_jwt_secret_for_training
SUPERADMIN_PORT = 8888
FLAG_SECRET     = [Generated and stored in ~/.banksploit/flag_secret]
```

---

## 🎯 Quick Start Commands

### Start the Application
```powershell
cd C:\Users\G01197938\Downloads\banksploit-ctf
.\start-app.ps1 start
```

### Check Server Status
```powershell
.\start-app.ps1 status
```

### Stop Servers
```powershell
.\start-app.ps1 stop
```

### Setup Database (if needed again)
```powershell
.\setup.ps1
```

---

## 🌐 Access the CTF

### User Application
- **URL:** http://localhost:9443
- **Action:** Start hacking the bank!

### Instructor Dashboard  
- **URL:** http://localhost:8888
- **Username:** instructor
- **Password:** instructor@123
- **Purpose:** Monitor participants, manage challenges, view scoreboard

---

## 📂 Project Structure

```
c:\Users\G01197938\Downloads\banksploit-ctf\
├── src/                    # Main CTF application (port 9443)
│   ├── api/               # API endpoints
│   ├── config/            # Configuration files
│   ├── core/              # Core framework
│   ├── modules/           # Business logic modules
│   ├── templates/         # HTML templates
│   ├── public/            # Static assets (CSS, JS, images)
│   └── index.php          # Entry point
├── src-superadmin/        # Instructor panel (port 8888)
├── sql/                   # Database scripts
│   ├── 01-schema.sql      # Table definitions
│   ├── 02-seed-data.sql   # User accounts
│   ├── 03-ctf-flags.sql   # Challenges
│   └── 04-ctf-submissions.sql  # Scoreboard
├── .local/                # Runtime files
│   ├── php.pid            # App server process ID
│   ├── superadmin.pid     # Admin server process ID
│   └── init.sql           # Database init script
├── .env                   # Environment config
├── setup.ps1              # Database setup script ✅ USED
├── start-app.ps1          # Server startup script ✅ ACTIVE
└── SETUP_COMPLETE.md      # This file
```

---

## 🐛 Troubleshooting

### Servers Won't Start
1. Verify MySQL is running: 
   ```powershell
   Get-Service MySQL84 | Select-Object Status
   ```
2. Check if ports are in use:
   ```powershell
   netstat -ano | findstr "9443\|8888"
   ```

### Database Connection Error
1. Verify MySQL credentials:
   ```powershell
   mysql -u banksploit -pbanksploit_pass -e "SELECT VERSION();"
   ```
2. Check database exists:
   ```powershell
   mysql -u root -proot -e "SHOW DATABASES;"
   ```

### PHP Extensions Not Loading
1. Verify php.ini:
   ```powershell
   php -i | findstr "extension_dir"
   ```
2. Check available extensions:
   ```powershell
   php -m
   ```

### Port Already in Use
1. Find process using port:
   ```powershell
   netstat -ano | findstr :9443
   ```
2. Kill the process:
   ```powershell
   taskkill /PID <PID> /F
   ```

---

## 📚 Documentation

Review these files for more information:

- **README.md** - Project overview
- **CTF_DESIGN_DECISIONS.md** - Architecture and design choices
- **CTF_WALKTHROUGH.html** - Complete challenge walkthrough
- **CREDENTIALS.md** - Test account credentials
- **VULN_MATRIX.html** - Vulnerability mapping
- **BankSploit_Pentest_Report.html** - Security assessment

---

## 🔒 Security Notes

This is a **CTF/training environment** with intentional vulnerabilities. 

**DO NOT** use any parts of this code or configurations in production:
- Credentials are hardcoded
- Security headers are minimal
- SQL injection, XSS, and other vulnerabilities are intentional
- Debug mode is enabled
- JWT secret is insecure

---

## ✨ What's Next?

1. ✅ **Setup Complete** - All systems running
2. 🎯 **Start Hacking** - Visit http://localhost:9443
3. 📊 **Monitor Progress** - Visit http://localhost:8888 (admin panel)
4. 🏆 **Solve Challenges** - Work through the CTF objectives
5. 🔍 **Review Vulnerabilities** - Check VULN_MATRIX.html

---

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Verify PHP/MySQL are installed correctly
3. Review the application logs in PHP dev server output
4. Check MySQL error log at: `C:\ProgramData\MySQL\MySQL Server 8.4\Data\`

---

**Setup completed successfully!** 🎉

The BankSploit CTF is ready to use. Start with:
```powershell
.\start-app.ps1 start
```

Then visit: **http://localhost:9443**

