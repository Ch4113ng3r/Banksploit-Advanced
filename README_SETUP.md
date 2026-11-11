# 🚀 BankSploit CTF - Complete Setup Guide

## ✅ Setup Status: COMPLETE ✅

All components have been successfully installed and configured.

---

## 📦 What Was Set Up

### 1. **PHP 8.2.32 (NTS, x86)**
   - ✅ Located at: `C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86`
   - ✅ php.ini configured with all required extensions
   - ✅ Extensions enabled:
     - pdo_mysql (database)
     - openssl (SSL/TLS)
     - mbstring (text processing)
     - curl (HTTP client)

### 2. **MySQL 8.4.10**
   - ✅ Running as Windows Service (MySQL84)
   - ✅ Database created: `banksploit`
   - ✅ User created: `banksploit` with password `banksploit_pass`
   - ✅ 18 tables loaded and ready

### 3. **Database Schema**
   - ✅ `01-schema.sql` - Table definitions
   - ✅ `02-seed-data.sql` - Test users and data
   - ✅ `03-ctf-flags.sql` - Challenges and flags
   - ✅ `04-ctf-submissions.sql` - Scoreboard tables

### 4. **Application Scripts**
   - ✅ `setup.ps1` - Database initialization (already run)
   - ✅ `start-app.ps1` - Start/stop servers
   - ✅ Environment variables configured

---

## 🎯 How to Start Using the CTF

### Step 1: Start the Servers
Open PowerShell and run:
```powershell
cd C:\Users\G01197938\Downloads\banksploit-ctf
.\start-app.ps1 start
```

### Step 2: Access the Application
- **CTF Application:** http://localhost:9443
- **Admin Dashboard:** http://localhost:8888
  - Username: `instructor`
  - Password: `instructor@123`

### Step 3: Start Hacking!
- Browse to http://localhost:9443
- Create an account or login with a test user
- Start solving CTF challenges

---

## 📚 Default Credentials

### Test Users (from seed data)
Check `CREDENTIALS.md` for available test accounts

### Admin Dashboard
- **URL:** http://localhost:8888
- **Username:** instructor
- **Password:** instructor@123

### MySQL
- **User:** banksploit
- **Password:** banksploit_pass
- **Database:** banksploit

### MySQL Root (for admin)
- **User:** root
- **Password:** root

---

## 🔧 Server Commands

### Start All Servers
```powershell
.\start-app.ps1 start
```

### Check Server Status
```powershell
.\start-app.ps1 status
```

### Stop All Servers
```powershell
.\start-app.ps1 stop
```

### Reset Database (if needed)
```powershell
.\setup.ps1
```

---

## 🌐 Network Access

After starting the servers, the CTF is accessible:

### Local Machine
- App: http://localhost:9443
- Admin: http://localhost:8888

### From Network
- App: http://<YOUR_IP>:9443
- Admin: http://<YOUR_IP>:8888

Example: If your computer's IP is 192.168.1.100:
- http://192.168.1.100:9443

---

## 📂 Project Files

```
C:\Users\G01197938\Downloads\banksploit-ctf\
├── src/                    # Main CTF app code
├── src-superadmin/         # Admin panel code
├── sql/                    # Database scripts
├── .local/                 # Runtime files
├── setup.ps1              # Database setup
├── start-app.ps1          # Start/stop servers
├── router.php             # URL router
├── SETUP_COMPLETE.md      # Detailed setup info
├── README_SETUP.md        # This file
├── CREDENTIALS.md         # Test account info
├── CTF_DESIGN_DECISIONS.md # Architecture
├── CTF_WALKTHROUGH.html   # Challenge guide
└── VULN_MATRIX.html       # Vulnerability list
```

---

## 🐛 Troubleshooting

### Servers Won't Start

**Check if ports are in use:**
```powershell
netstat -ano | findstr "9443\|8888"
```

**Check if MySQL is running:**
```powershell
Get-Service MySQL84 | Select-Object Status
```

**Restart MySQL if needed:**
```powershell
Stop-Service MySQL84
Start-Service MySQL84
```

### Database Connection Error

**Verify database exists:**
```powershell
mysql -u root -proot -e "SHOW DATABASES; USE banksploit; SHOW TABLES;"
```

**Verify user can connect:**
```powershell
mysql -u banksploit -pbanksploit_pass -e "SELECT VERSION();"
```

### PHP Extension Issues

**Check loaded extensions:**
```powershell
php -m
```

**Should include:**
- pdo_mysql
- openssl  
- mbstring

### Can't Connect to Admin Dashboard

**Check if server is running:**
```powershell
.\start-app.ps1 status
```

**Try accessing from browser:**
- http://localhost:8888

**Check firewall settings:**
- Windows Firewall may be blocking ports
- Add exceptions for ports 9443 and 8888

---

## 📖 Documentation

### Essential Reading
1. **SETUP_COMPLETE.md** - Full setup details
2. **README.md** - Project overview
3. **CREDENTIALS.md** - Test account list

### For CTF Players
- **CTF_WALKTHROUGH.html** - Complete challenge guide
- **VULN_MATRIX.html** - What vulnerabilities exist

### For Instructors
- **CTF_DESIGN_DECISIONS.md** - Why it was built this way
- **BankSploit_Pentest_Report.html** - Security assessment

---

## ✨ Features

### CTF Application
- ✅ User authentication (vulnerable by design)
- ✅ Banking functionality (with intentional flaws)
- ✅ Challenge system with scoring
- ✅ Scoreboard for tracking progress
- ✅ Multiple vulnerability types (SQL injection, XSS, etc.)

### Admin Dashboard
- ✅ Monitor participant progress
- ✅ View submitted flags
- ✅ Manage challenges
- ✅ Track scoreboard
- ✅ See live submissions

---

## 🔒 Security Note

⚠️ **This is a CTF training environment with intentional vulnerabilities.**

DO NOT deploy any part of this code to production. It includes:
- Hardcoded credentials
- Intentional SQL injection vulnerabilities
- Cross-site scripting (XSS) vulnerabilities
- Weak authentication mechanisms
- Debug/verbose error messages
- No rate limiting
- No security headers

This is designed for educational purposes only.

---

## 🚀 Next Steps

1. ✅ **Setup Complete** - All systems ready
2. **Start Servers:**
   ```powershell
   .\start-app.ps1 start
   ```
3. **Access CTF:** Visit http://localhost:9443
4. **Start Hacking:** Work through the challenges
5. **Check Scoreboard:** Admin panel at http://localhost:8888

---

## 💡 Tips

### For CTF Participants
- Use tools like Burp Suite to intercept requests
- Check browser developer tools (F12)
- Read error messages carefully
- Try common web vulnerabilities
- Document your findings

### For Instructors
- Monitor progress via the admin dashboard
- Review VULN_MATRIX.html for all vulnerabilities
- Use CTF_WALKTHROUGH.html to help participants
- Check scoreboard in real-time

---

## 📞 Need Help?

1. Check this README first
2. Review SETUP_COMPLETE.md for detailed information
3. Check the Troubleshooting section above
4. Review application logs in the PHP dev server console
5. Verify MySQL is running with proper credentials

---

**You're all set! The BankSploit CTF is ready to go.** 🎉

Start the servers and begin the challenges:
```powershell
.\start-app.ps1 start
```

Then visit: **http://localhost:9443**

