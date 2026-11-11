# Setup Scripts Update Summary

## Changes Made

All setup scripts have been updated to:
1. Fix MySQL authentication issues (add root password and 127.0.0.1 user grant)
2. Include proper database migrations
3. Seed CTF flags automatically
4. Use environment variables for PHP (no hardcoded paths)

## Files Updated

### 1. **start-app.ps1** (Windows - Primary)
- Fixed `Setup-Database()` to:
  - Create user for both `localhost` and `127.0.0.1`
  - Use MySQL root password: `root`
- Fixed `Load-Schema()` to include host: `-h 127.0.0.1`
- Added `Initialize-Flags()` function (renamed from Seed-Flags for PS verb compliance)
- Fixed `Run-Migrations()` to include host
- Updated `Setup-FirstTime()` to call all functions in order
- All functions now properly handle errors

### 2. **setup.ps1** (Windows - Lightweight)
- Added 127.0.0.1 user grant to database creation
- Added `-h 127.0.0.1` to all mysql commands
- Added migration step 2.5 to run username migration
- Added step 4 to seed CTF flags
- Updated configuration display with host information

### 3. **start.ps1** (Windows - Alternative)
- Fixed `Setup-DB()` function with proper root credentials and dual-host setup
- Renamed `Seed-Flags` to `Initialize-Flags` (PowerShell verb compliance)
- Added proper error handling with warnings instead of errors
- Updated `Do-Reset()` and `Do-Restore()` to include host and root password
- Updated `Do-ResetFull()` to create users for both localhost and 127.0.0.1

### 4. **start.sh** (Linux/macOS)
- Updated `setup_db()` to create user for both localhost and 127.0.0.1
- Added `seed_flags()` function that uses PHP with proper environment variables
- Updated `do_reset_full()` to grant privileges for both host variants
- All commands properly use environment variables (no hardcoded paths)

## Database Credentials

**All scripts now use:**
- Host: 127.0.0.1 (in addition to localhost)
- Database: banksploit
- User: banksploit
- Password: banksploit_pass
- MySQL Root Password: root

## Key Fixes

1. **MySQL Authentication**: All scripts now connect with proper credentials
   - Root user password: `root`
   - Both `localhost` and `127.0.0.1` hosts configured
   
2. **Username Field**: Migration script (04-add-username-to-ctf-flags.sql) runs automatically
   
3. **Flag Seeding**: CTF flags now properly seeded with username field populated
   
4. **PHP Path**: All scripts use `php` command (expects it in PATH), no hardcoded paths

## Usage

**Windows:**
```powershell
# Use any of these:
.\start-app.ps1 setup
.\setup.ps1
.\start.ps1 setup
```

**Linux/macOS:**
```bash
./start.sh setup
```

All scripts will now:
1. Create database with proper user grants
2. Load schema files
3. Apply migrations (username field)
4. Seed CTF flags with usernames
5. Report success/status

## Environment Variables

The following environment variables must be in PATH or set:
- `php` - PHP CLI binary (8.3+)
- `mysql` - MySQL client binary (8.0+)

Scripts set these for PHP subprocesses:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_SCHEME`, `APP_HOST`, `APP_PORT`, `APP_ENV`, `APP_DEBUG`
- `JWT_SECRET`, `FLAG_SECRET`, `SUPERADMIN_PORT`
