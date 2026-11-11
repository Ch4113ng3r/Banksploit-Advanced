# BankSploit CTF - Full Start Script for Windows
# Usage: .\start-app.ps1 [start|stop|status|setup|reset|restore|reset-full|help]

param([ValidateSet("start", "stop", "status", "setup", "reset", "restore", "reset-full", "help")][string]$Action = "help")

$ErrorActionPreference = "Continue"

# Configuration
$ProjectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$SrcDir = Join-Path $ProjectDir "src"
$SuperAdminSrc = Join-Path $ProjectDir "src-superadmin"
$SqlDir = Join-Path $ProjectDir "sql"
$LocalDir = Join-Path $ProjectDir ".local"
$PidFile = Join-Path $LocalDir "php.pid"
$LogFile = Join-Path $LocalDir "php-server.log"
$SuperAdminPid = Join-Path $LocalDir "superadmin-php.pid"

$AppPort = 9443
$SuperAdminPort = 8888
$MysqlPort = 3306

$PhpPath = "C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86"
$MysqlPath = "C:\Program Files\MySQL\MySQL Server 8.4\bin"
$env:PATH = "$PhpPath;$MysqlPath;$env:PATH"
$env:PHPRC = $PhpPath

$DbName = "banksploit"
$DbUser = "banksploit"
$DbPass = "banksploit_pass"
$DbHost = "127.0.0.1"

$env:DB_HOST = $DbHost
$env:DB_PORT = $MysqlPort
$env:DB_NAME = $DbName
$env:DB_USER = $DbUser
$env:DB_PASS = $DbPass
$env:APP_SCHEME = "http"
$env:APP_HOST = "0.0.0.0"
$env:APP_PORT = $AppPort
$env:APP_ENV = "development"
$env:APP_DEBUG = "true"
$env:JWT_SECRET = "insecure_jwt_secret_for_training"
$env:SUPERADMIN_PORT = $SuperAdminPort

# Flag secret: src/core/Flags.php reads this file. Never put it in the
# environment, because the phpinfo() lab prints the environment.
$SecretDir = Join-Path $env:USERPROFILE ".banksploit"
$SecretFile = Join-Path $SecretDir "flag_secret"
if (-not (Test-Path $SecretFile)) {
    New-Item -ItemType Directory -Path $SecretDir -Force | Out-Null
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RNGCryptoServiceProvider]::new()
    $rng.GetBytes($bytes)
    $newSecret = ([BitConverter]::ToString($bytes) -replace '-', '').ToLower()
    Set-Content -Path $SecretFile -Value $newSecret -Force
}
Remove-Item Env:FLAG_SECRET -ErrorAction SilentlyContinue

# Helpers
function Write-OK {
    param([string]$m)
    Write-Host "[OK] $m" -ForegroundColor Green
}

function Write-Warn {
    param([string]$m)
    Write-Host "[!] $m" -ForegroundColor Yellow
}

function Write-Err {
    param([string]$m)
    Write-Host "[X] $m" -ForegroundColor Red
}

function Write-Info {
    param([string]$m)
    Write-Host "[*] $m" -ForegroundColor Cyan
}

function Show-Help {
    Write-Host ""
    Write-Host "  BankSploit CTF - Local Environment Manager" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Usage: .\start-app.ps1 <command>" -ForegroundColor White
    Write-Host ""
    Write-Host "Commands:" -ForegroundColor Cyan
    Write-Host "  start      Start MySQL + PHP servers" -ForegroundColor White
    Write-Host "  stop       Stop all services" -ForegroundColor White
    Write-Host "  status     Check running services" -ForegroundColor White
    Write-Host "  setup      First-time: create DB, user, load schema" -ForegroundColor White
    Write-Host "  reset      Clear scoreboard (backup available)" -ForegroundColor White
    Write-Host "  restore    Restore submissions from backup" -ForegroundColor White
    Write-Host "  reset-full DROP and recreate entire database" -ForegroundColor White
    Write-Host "  help       Show this help message" -ForegroundColor White
    Write-Host ""
}

function Check-Deps {
    $missing = @()
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
        $missing += "PHP"
    }
    if (-not (Get-Command mysql -ErrorAction SilentlyContinue)) {
        $missing += "MySQL"
    }
    if ($missing.Count -gt 0) {
        Write-Err "Missing dependencies: $($missing -join ', ')"
        exit 1
    }
}

function Start-MySQL {
    Write-Info "Checking MySQL..."
    $mysql = Get-Process mysqld -ErrorAction SilentlyContinue
    if ($mysql) {
        Write-OK "MySQL already running (PID: $($mysql.Id))"
        return
    }
    try {
        $svc = Get-Service MySQL84 -ErrorAction SilentlyContinue
        if ($svc) {
            Start-Service MySQL84 -ErrorAction SilentlyContinue
            Start-Sleep -Seconds 2
            Write-OK "MySQL started"
        }
    }
    catch {
        Write-Warn "Could not start MySQL"
    }
}

function Stop-MySQL {
    try {
        Get-Service MySQL84 -ErrorAction SilentlyContinue | Stop-Service -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 1
        Write-OK "MySQL stopped"
    }
    catch {
        Write-Warn "Could not stop MySQL"
    }
}

function Setup-Database {
    Write-Info "Setting up database..."
    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"
    $setupSqlFile = Join-Path $env:TEMP "setup_db_$([System.Guid]::NewGuid()).sql"

    $sql = "CREATE DATABASE IF NOT EXISTS $DbName;" + [Environment]::NewLine
    $sql += "CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';" + [Environment]::NewLine
    $sql += "CREATE USER IF NOT EXISTS '$DbUser'@'127.0.0.1' IDENTIFIED BY '$DbPass';" + [Environment]::NewLine
    $sql += "GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'localhost';" + [Environment]::NewLine
    $sql += "GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'127.0.0.1';" + [Environment]::NewLine
    $sql += "FLUSH PRIVILEGES;" + [Environment]::NewLine

    try {
        $sql | Out-File -FilePath $setupSqlFile -Encoding UTF8
        & cmd /c "`"$MysqlBin`" -u root -proot -h 127.0.0.1 --protocol=TCP < `"$setupSqlFile`"" 2>&1 | Out-Null
        Remove-Item -Path $setupSqlFile -Force
        Write-OK "Database '$DbName' created"
        Write-OK "User '$DbUser' created"
    }
    catch {
        Write-Err "Failed to create database: $_"
        exit 1
    }
}

function Load-Schema {
    Write-Info "Loading database schema..."
    if (-not (Test-Path $SqlDir)) {
        Write-Err "SQL directory not found: $SqlDir"
        exit 1
    }

    $sqlFiles = Get-ChildItem -Path $SqlDir -Filter "*.sql" | Sort-Object Name
    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"

    foreach ($file in $sqlFiles) {
        Write-Info "  Loading $($file.Name)..."
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass -h 127.0.0.1 --protocol=TCP $DbName < `"$($file.FullName)`"" 2>&1 | Where-Object { $_ -notmatch "Warning" } | Out-Null
        }
        catch {
            Write-Err "Failed to load $($file.Name): $_"
            exit 1
        }
    }
    Write-OK "Schema loaded successfully"
}

function Run-Migrations {
    Write-Info "Running database migrations..."
    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"
    $migrationFiles = Get-ChildItem -Path $SqlDir -Filter "migration-*.sql" | Sort-Object Name

    if ($migrationFiles.Count -eq 0) {
        Write-Warn "No migration files found in $SqlDir"
        return
    }

    foreach ($file in $migrationFiles) {
        Write-Info "  Running migration: $($file.Name)..."
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass -h 127.0.0.1 --protocol=TCP $DbName < `"$($file.FullName)`"" 2>&1 | Where-Object { $_ -notmatch "Warning" } | Out-Null
            Write-OK "  Applied: $($file.Name)"
        }
        catch {
            Write-Warn "  Migration $($file.Name) had issues: $_"
        }
    }
    Write-OK "All migrations completed"
}

function Initialize-Flags {
    Write-Info "Seeding CTF flags..."
    try {
        & php (Join-Path $SrcDir "scripts\seed_flags.php")
        if ($LASTEXITCODE -ne 0) { Write-Warn "Flag seeding had warnings but completed" }
    }
    catch {
        Write-Warn "Flag seeding failed: $_"
    }
}

function Setup-FirstTime {
    Write-Host "==========================================="
    Write-Host "  BankSploit - First-Time Setup"
    Write-Host "==========================================="
    Write-Host ""

    Check-Deps
    New-Item -ItemType Directory -Path $LocalDir -Force | Out-Null

    Start-MySQL
    Setup-Database
    Load-Schema
    Run-Migrations
    Initialize-Flags

    Write-Host ""
    Write-OK "Setup complete! Run '.\start-app.ps1 start' to launch the app."
}

function Start-PHP {
    if ((Test-Path $PidFile) -and (Get-Content $PidFile | ForEach-Object { Get-Process -Id $_ -ErrorAction SilentlyContinue })) {
        Write-OK "PHP app server already running"
        return
    }

    New-Item -ItemType Directory -Path $LocalDir -Force | Out-Null
    Write-Info "Starting CTF App on 0.0.0.0:$AppPort..."

    $pinfo = New-Object System.Diagnostics.ProcessStartInfo
    $pinfo.FileName = "php"
    $pinfo.Arguments = "-d max_execution_time=300 -d memory_limit=256M -S 0.0.0.0:$AppPort -t `"$SrcDir`" router.php"
    $pinfo.RedirectStandardOutput = $true
    $pinfo.RedirectStandardError = $true
    $pinfo.UseShellExecute = $false
    $pinfo.CreateNoWindow = $true
    $pinfo.WorkingDirectory = $ProjectDir

    $pinfo.EnvironmentVariables["DB_HOST"] = $env:DB_HOST
    $pinfo.EnvironmentVariables["DB_PORT"] = $env:DB_PORT
    $pinfo.EnvironmentVariables["DB_NAME"] = $env:DB_NAME
    $pinfo.EnvironmentVariables["DB_USER"] = $env:DB_USER
    $pinfo.EnvironmentVariables["DB_PASS"] = $env:DB_PASS
    $pinfo.EnvironmentVariables["APP_SCHEME"] = $env:APP_SCHEME
    $pinfo.EnvironmentVariables["APP_HOST"] = $env:APP_HOST
    $pinfo.EnvironmentVariables["APP_PORT"] = $env:APP_PORT
    $pinfo.EnvironmentVariables["APP_ENV"] = $env:APP_ENV
    $pinfo.EnvironmentVariables["APP_DEBUG"] = $env:APP_DEBUG
    $pinfo.EnvironmentVariables["JWT_SECRET"] = $env:JWT_SECRET
    $pinfo.EnvironmentVariables["PHPRC"] = $env:PHPRC

    $proc = [System.Diagnostics.Process]::Start($pinfo)
    $proc.Id | Out-File -FilePath $PidFile -Force
    Start-Sleep -Seconds 1

    if (Get-Process -Id $proc.Id -ErrorAction SilentlyContinue) {
        Write-OK "CTF App started (PID $($proc.Id))"
    }
    else {
        Write-Err "CTF App failed to start"
        exit 1
    }
}

function Stop-PHP {
    if (Test-Path $PidFile) {
        $id = Get-Content $PidFile
        try {
            Stop-Process -Id $id -ErrorAction SilentlyContinue
            Remove-Item -Path $PidFile -Force -ErrorAction SilentlyContinue
            Write-OK "CTF App stopped"
        }
        catch {
            Write-Warn "Could not stop CTF App"
        }
    }
}

function Start-SuperAdmin {
    if ((Test-Path $SuperAdminPid) -and (Get-Content $SuperAdminPid | ForEach-Object { Get-Process -Id $_ -ErrorAction SilentlyContinue })) {
        Write-OK "Instructor Portal already running"
        return
    }

    Write-Info "Starting Instructor Portal on 0.0.0.0:$SuperAdminPort..."

    $pinfo = New-Object System.Diagnostics.ProcessStartInfo
    $pinfo.FileName = "php"
    $pinfo.Arguments = "-d max_execution_time=300 -d memory_limit=256M -S 0.0.0.0:$SuperAdminPort -t `"$SuperAdminSrc`" router.php"
    $pinfo.RedirectStandardOutput = $true
    $pinfo.RedirectStandardError = $true
    $pinfo.UseShellExecute = $false
    $pinfo.CreateNoWindow = $true
    $pinfo.WorkingDirectory = $SuperAdminSrc

    $pinfo.EnvironmentVariables["DB_HOST"] = $env:DB_HOST
    $pinfo.EnvironmentVariables["DB_PORT"] = $env:DB_PORT
    $pinfo.EnvironmentVariables["DB_NAME"] = $env:DB_NAME
    $pinfo.EnvironmentVariables["DB_USER"] = $env:DB_USER
    $pinfo.EnvironmentVariables["DB_PASS"] = $env:DB_PASS
    $pinfo.EnvironmentVariables["SUPERADMIN_PORT"] = $env:SUPERADMIN_PORT
    $pinfo.EnvironmentVariables["PHPRC"] = $env:PHPRC

    $proc = [System.Diagnostics.Process]::Start($pinfo)
    $proc.Id | Out-File -FilePath $SuperAdminPid -Force
    Start-Sleep -Seconds 1

    if (Get-Process -Id $proc.Id -ErrorAction SilentlyContinue) {
        Write-OK "Instructor Portal started (PID $($proc.Id))"
    }
    else {
        Write-Err "Instructor Portal failed to start"
        exit 1
    }
}

function Stop-SuperAdmin {
    if (Test-Path $SuperAdminPid) {
        $id = Get-Content $SuperAdminPid
        try {
            Stop-Process -Id $id -ErrorAction SilentlyContinue
            Remove-Item -Path $SuperAdminPid -Force -ErrorAction SilentlyContinue
            Write-OK "Instructor Portal stopped"
        }
        catch {
            Write-Warn "Could not stop Instructor Portal"
        }
    }
}

function Invoke-Start {
    Write-Host ""
    Write-Host "==========================================="
    Write-Host "  BankSploit - Starting Environment"
    Write-Host "==========================================="
    Write-Host ""

    Check-Deps
    Start-MySQL
    Start-PHP
    Start-SuperAdmin

    $routeableIp = "?"
    try {
        $routeableIp = (Get-NetIPAddress -AddressFamily IPv4 -PrefixOrigin Dhcp,Manual | Where-Object { $_.IPAddress -ne "127.0.0.1" } | Select-Object -First 1).IPAddress
    }
    catch { }

    Write-Host ""
    Write-OK "BankSploit is running!"
    Write-Host ""
    Write-Host "  CTF App:       http://localhost:$AppPort" -ForegroundColor Cyan
    Write-Host "  Network:       http://$routeableIp`:$AppPort" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  Instructor:    http://$routeableIp`:$SuperAdminPort" -ForegroundColor Cyan
    Write-Host "  Login:         instructor / instructor@123" -ForegroundColor Cyan
    Write-Host ""
}

function Invoke-Stop {
    Write-Host "Stopping BankSploit..." -ForegroundColor Yellow
    Stop-SuperAdmin
    Stop-PHP
    Stop-MySQL
    Write-OK "All stopped"
}

function Invoke-Status {
    Write-Host "=== Status ===" -ForegroundColor Cyan
    $mysql = Get-Process mysqld -ErrorAction SilentlyContinue
    if ($mysql) {
        Write-OK "MySQL: Running (PID $($mysql.Id))"
    }
    else {
        Write-Warn "MySQL: Stopped"
    }

    if ((Test-Path $PidFile) -and (Get-Content $PidFile | ForEach-Object { Get-Process -Id $_ -ErrorAction SilentlyContinue })) {
        $id = Get-Content $PidFile
        Write-OK "CTF App: Running (http://localhost:$AppPort, PID $id)"
    }
    else {
        Write-Warn "CTF App: Stopped"
    }

    if ((Test-Path $SuperAdminPid) -and (Get-Content $SuperAdminPid | ForEach-Object { Get-Process -Id $_ -ErrorAction SilentlyContinue })) {
        $id = Get-Content $SuperAdminPid
        Write-OK "Portal: Running (http://127.0.0.1:$SuperAdminPort, PID $id)"
    }
    else {
        Write-Warn "Portal: Stopped"
    }
    Write-Host ""
}

function Invoke-Reset {
    Write-Host "=== Reset CTF Scoreboard ===" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  1 - Reset scoreboard (backup submissions first)" -ForegroundColor White
    Write-Host "  2 - Reset scoreboard (no backup - permanent delete)" -ForegroundColor White
    Write-Host "  3 - Cancel" -ForegroundColor White
    Write-Host ""
    $choice = Read-Host "  Choose [1/2/3]"

    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"

    if ($choice -eq "1") {
        Write-Info "Backing up submissions..."
        $sqlFile = Join-Path $env:TEMP "backup_$([System.Guid]::NewGuid()).sql"
        $sql = "CREATE TABLE IF NOT EXISTS ctf_submissions_backup LIKE ctf_submissions;" + [Environment]::NewLine
        $sql += "INSERT INTO ctf_submissions_backup SELECT * FROM ctf_submissions;" + [Environment]::NewLine
        $sql += "DELETE FROM ctf_submissions;" + [Environment]::NewLine
        $sql | Out-File -FilePath $sqlFile -Encoding UTF8
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName < `"$sqlFile`"" 2>&1 | Out-Null
            Remove-Item $sqlFile -Force
            Write-OK "Backup saved. Scoreboard cleared."
        }
        catch {
            Write-Err "Backup failed: $_"
        }
    }
    elseif ($choice -eq "2") {
        Write-Info "Clearing all submissions..."
        $sqlFile = Join-Path $env:TEMP "clear_$([System.Guid]::NewGuid()).sql"
        "DELETE FROM ctf_submissions;" | Out-File -FilePath $sqlFile -Encoding UTF8
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName < `"$sqlFile`"" 2>&1 | Out-Null
            Remove-Item $sqlFile -Force
            Write-OK "Scoreboard cleared - permanent, no backup."
        }
        catch {
            Write-Err "Clear failed: $_"
        }
    }
    elseif ($choice -eq "3") {
        Write-Warn "Cancelled."
    }
    else {
        Write-Err "Invalid choice."
    }
}

function Invoke-Restore {
    Write-Host "=== Restore CTF Submissions from Backup ===" -ForegroundColor Cyan

    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"

    $sqlFile = Join-Path $env:TEMP "restore_check_$([System.Guid]::NewGuid()).sql"
    "SELECT COUNT(*) FROM ctf_submissions_backup;" | Out-File -FilePath $sqlFile -Encoding UTF8
    try {
        $count = & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName -N < `"$sqlFile`"" 2>$null | Select-Object -First 1
        Remove-Item $sqlFile -Force
    }
    catch {
        $count = "0"
    }

    if ([string]::IsNullOrWhiteSpace($count) -or $count -eq "0") {
        Write-Warn "No backup found."
        return
    }

    Write-OK "Found $count backed-up submissions."
    $confirm = Read-Host "  Restore them? [y/N]"

    if ($confirm -match "^[Yy]$") {
        $sqlFile = Join-Path $env:TEMP "restore_$([System.Guid]::NewGuid()).sql"
        $sql = "INSERT IGNORE INTO ctf_submissions SELECT * FROM ctf_submissions_backup;" + [Environment]::NewLine
        $sql | Out-File -FilePath $sqlFile -Encoding UTF8
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName < `"$sqlFile`"" 2>&1 | Out-Null
            Remove-Item $sqlFile -Force
            Write-OK "Submissions restored from backup."
        }
        catch {
            Write-Err "Restore failed: $_"
        }
    }
    else {
        Write-Warn "Cancelled."
    }
}

function Invoke-ResetFull {
    Write-Host "=== FULL Database Reset ===" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  1 - Full reset (DELETE everything)" -ForegroundColor White
    Write-Host "  2 - Full reset (KEEP submitted flags)" -ForegroundColor White
    Write-Host "  3 - Cancel" -ForegroundColor White
    Write-Host ""
    $choice = Read-Host "  Choose [1/2/3]"

    if ($choice -eq "3") {
        Write-Warn "Cancelled."
        return
    }

    if ($choice -ne "1" -and $choice -ne "2") {
        Write-Err "Invalid choice."
        return
    }

    $keepSubmissions = ($choice -eq "2")

    if ($keepSubmissions) {
        $confirm = Read-Host "  Type 'yes' to confirm (submissions will be kept)"
    }
    else {
        $confirm = Read-Host "  Type 'yes' to confirm full deletion"
    }

    if ($confirm -ne "yes") {
        Write-Warn "Cancelled."
        return
    }

    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"

    if ($keepSubmissions) {
        Write-Info "Backing up submissions..."
        $sqlFile = Join-Path $env:TEMP "backup_before_reset_$([System.Guid]::NewGuid()).sql"
        $sql = "CREATE TABLE IF NOT EXISTS ctf_submissions_backup LIKE ctf_submissions;" + [Environment]::NewLine
        $sql += "TRUNCATE ctf_submissions_backup;" + [Environment]::NewLine
        $sql += "INSERT INTO ctf_submissions_backup SELECT * FROM ctf_submissions;" + [Environment]::NewLine
        $sql | Out-File -FilePath $sqlFile -Encoding UTF8
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName < `"$sqlFile`"" 2>&1 | Out-Null
            Remove-Item $sqlFile -Force
        }
        catch {
            Write-Warn "Backup failed: $_"
        }
    }

    Write-Info "Dropping and recreating database..."
    $sqlFile = Join-Path $env:TEMP "reset_db_$([System.Guid]::NewGuid()).sql"
    $sql = "DROP DATABASE IF EXISTS $DbName;" + [Environment]::NewLine
    $sql += "CREATE DATABASE $DbName;" + [Environment]::NewLine
    $sql += "GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'localhost';" + [Environment]::NewLine
    $sql += "GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'127.0.0.1';" + [Environment]::NewLine
    $sql | Out-File -FilePath $sqlFile -Encoding UTF8

    try {
        & cmd /c "`"$MysqlBin`" -u root -proot < `"$sqlFile`"" 2>&1 | Out-Null
        Remove-Item $sqlFile -Force
        Write-OK "Database dropped and recreated"
    }
    catch {
        Write-Err "Failed to reset database: $_"
        exit 1
    }

    Load-Schema
    Run-Migrations

    if ($keepSubmissions) {
        Write-Info "Restoring submissions..."
        $sqlFile = Join-Path $env:TEMP "restore_after_reset_$([System.Guid]::NewGuid()).sql"
        $sql = "INSERT IGNORE INTO ctf_submissions SELECT * FROM ctf_submissions_backup;" + [Environment]::NewLine
        $sql | Out-File -FilePath $sqlFile -Encoding UTF8
        try {
            & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName < `"$sqlFile`"" 2>&1 | Out-Null
            Remove-Item $sqlFile -Force
            Write-OK "Submissions restored"
        }
        catch {
            Write-Warn "Restore failed: $_"
        }
    }

    Write-OK "Full reset complete"
}

# Main
switch ($Action) {
    "start" { Invoke-Start }
    "stop" { Invoke-Stop }
    "status" { Invoke-Status }
    "setup" { Setup-FirstTime }
    "reset" { Invoke-Reset }
    "restore" { Invoke-Restore }
    "reset-full" { Invoke-ResetFull }
    default { Show-Help }
}
