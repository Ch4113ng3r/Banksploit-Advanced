# BankSploit CTF - Local Setup Guide (Windows)

## Status
- ✅ `.env` file created from template
- ✅ MySQL installed with credentials: root/root
- ✅ PHP 8.2.32 available at: `C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86`

## Step 1: Add PHP to System PATH

1. Open **Environment Variables**:
   - Press `Win+R`
   - Type `sysdm.cpl` and press Enter
   - Click "Environment Variables" button

2. Under **System variables**, find and edit **Path**:
   - Click "New" and add: `C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86`
   - Click OK and close the windows

3. **Restart PowerShell** to load the updated PATH

4. Verify PHP is in PATH:
   ```powershell
   php -v
   ```
   Expected output: PHP 8.2.32 ...

## Step 2: Verify PHP Extensions

After PHP is in PATH, verify required extensions:

```powershell
php -m | findstr pdo_mysql
php -m | findstr openssl
php -m | findstr mbstring
```

All three should return results. If not, edit `C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86\php.ini` and uncomment:
```ini
extension=pdo_mysql
extension=openssl
extension=mbstring
```

## Step 3: Verify MySQL

Check MySQL service status:

```powershell
Get-Service MySQL* | Select-Object Name, Status
```

If MySQL is not running, start it:
```powershell
net start MySQL80
```
(Replace 8.0 with your MySQL version if different)

Test connection:
```powershell
mysql -u root -p
# Enter password: root
# You should see: mysql>
# Type: exit
```

## Step 4: Run Setup Script

Navigate to the project directory and run the setup:

```powershell
cd C:\Users\G01197938\Downloads\banksploit-ctf
.\start.ps1 setup
```

This will:
- Create the `banksploit` database
- Create the `banksploit` user with password `banksploit_pass`
- Load the schema from `sql/01-schema.sql`
- Seed data from `sql/02-seed-data.sql`
- Load CTF flags from `sql/03-ctf-flags.sql`
- Initialize scoreboard from `sql/04-ctf-submissions.sql`

**Expected output:**
```
[OK] Connected to MySQL
[OK] Database created
[OK] Schema loaded
[OK] Data seeded
[OK] CTF flags loaded
[OK] Setup complete!
```

## Step 5: Start the Application

```powershell
.\start.ps1 start
```

**Expected output:**
```
[OK] MySQL is running (PID: xxxx)
[OK] PHP App Server started (PID: xxxx) - http://localhost:9443
[OK] PHP SuperAdmin Server started (PID: xxxx) - http://localhost:8888

Access the app:
  CTF App: http://localhost:9443 (or https://localhost:9443)
  Admin Panel: http://localhost:8888
```

## Step 6: Access the Application

### CTF Application
- **URL:** http://localhost:9443 (or https://localhost:9443)
- **Default user:** Any of the seeded accounts (check `CREDENTIALS.md`)
- **Browser:** Open and accept the self-signed certificate warning

### Instructor Control Panel
- **URL:** http://localhost:8888
- **Username:** instructor
- **Password:** instructor@123

## Useful Commands

```powershell
# Check status of running servers
.\start.ps1 status

# Stop the application
.\start.ps1 stop

# Reset scoreboard (keeps app data)
.\start.ps1 reset

# Full database reset
.\start.ps1 reset-full

# View help
.\start.ps1 help
```

## Troubleshooting

### PHP not found
```powershell
# Verify PHP is in PATH
$env:Path
# Should contain: C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86

# If not, restart PowerShell after adding to PATH
```

### MySQL not found
```powershell
# Check if MySQL service exists
Get-Service MySQL* 

# Start the service
net start MySQL80
```

### Port 9443 already in use
Edit `start.ps1` line 29:
```powershell
$AppPort = 9443  # Change this to another port like 8443
```

### Database connection error
1. Verify MySQL is running: `Get-Service MySQL80 | Select-Object Status`
2. Test MySQL: `mysql -u root -p` (password: root)
3. Check credentials in `.env` match `start.ps1` line 54-56

## Project Structure

```
banksploit-ctf/
├── src/                    # Main CTF application
│   ├── api/               # API endpoints
│   ├── modules/           # Business logic
│   ├── config/            # Configuration
│   ├── templates/         # HTML templates
│   └── index.php          # Entry point
├── src-superadmin/        # Instructor control panel
├── sql/                   # Database scripts
│   ├── 01-schema.sql      # Tables & structure
│   ├── 02-seed-data.sql   # User data
│   ├── 03-ctf-flags.sql   # CTF challenges & flags
│   └── 04-ctf-submissions.sql # Scoreboard
├── router.php             # CLI router for app
├── start.ps1              # Main startup script
└── .env                   # Environment config
```

## Next Steps

1. Complete Step 1-5 above in order
2. Verify the app loads at http://localhost:9443
3. Review `CTF_DESIGN_DECISIONS.md` for architecture details
4. Check `CTF_WALKTHROUGH.html` for challenge walkthrough
5. Review `CREDENTIALS.md` for test user accounts

---

**Last updated:** 2026-07-19  
**PHP version:** 8.2.32 (NTS, x86)  
**MySQL:** 8.0+ (root/root)  
**Ports:** 9443 (App), 8888 (Admin), 3306 (MySQL)
