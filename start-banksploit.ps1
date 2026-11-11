# BankSploit CTF - Start Script
# Usage: .\start-banksploit.ps1 [start|stop|status|setup|help]

param([ValidateSet("start", "stop", "status", "setup", "help")][string]$Action = "help")

$ErrorActionPreference = "Continue"

# Configuration
$ProjectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$SrcDir = Join-Path $ProjectDir "src"
$SuperAdminSrc = Join-Path $ProjectDir "src-superadmin"
$SqlDir = Join-Path $ProjectDir "sql"
$LocalDir = Join-Path $ProjectDir ".local"
$PidFile = Join-Path $LocalDir "php.pid"
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

# Environment variables
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
function Write-OK { param([string]$m) Write-Host "[OK] $m" -ForegroundColor Green }
function Write-Warn { param([string]$m) Write-Host "[!] $m" -ForegroundColor Yellow }
function Write-Err { param([string]$m) Write-Host "[X] $m" -ForegroundColor Red }
function Write-Info { param([string]$m) Write-Host "[*] $m" -ForegroundColor Cyan }

function Show-Help {
    Write-Host ""
    Write-Host "  BankSploit CTF - Local Environment Manager" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Usage: .\start-banksploit.ps1 <command>" -ForegroundColor White
    Write-Host ""
    Write-Host "Commands:" -ForegroundColor Cyan
    Write-Host "  start      Start all services" -ForegroundColor White
    Write-Host "  stop       Stop all services" -ForegroundColor White
    Write-Host "  status     Show service status" -ForegroundColor White
    Write-Host "  setup      First-time setup" -ForegroundColor White
    Write-Host "  help       Show this help" -ForegroundColor White
    Write-Host ""
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
    $pinfo.WorkingDirectory = $SrcDir

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
    Write-Host ("MySQL: " + $(if ($mysql) { "Running (PID $($mysql.Id))" } else { "Stopped" })) -ForegroundColor $(if ($mysql) { "Green" } else { "Yellow" })

    if (Test-Path $PidFile) {
        $id = Get-Content $PidFile
        $p = Get-Process -Id $id -ErrorAction SilentlyContinue
        Write-Host ("CTF App: " + $(if ($p) { "Running (PID $id)" } else { "Stopped" })) -ForegroundColor $(if ($p) { "Green" } else { "Yellow" })
    }
    else {
        Write-Host "CTF App: Stopped" -ForegroundColor Yellow
    }

    if (Test-Path $SuperAdminPid) {
        $id = Get-Content $SuperAdminPid
        $p = Get-Process -Id $id -ErrorAction SilentlyContinue
        Write-Host ("Portal: " + $(if ($p) { "Running (PID $id)" } else { "Stopped" })) -ForegroundColor $(if ($p) { "Green" } else { "Yellow" })
    }
    else {
        Write-Host "Portal: Stopped" -ForegroundColor Yellow
    }
    Write-Host ""
}

function Invoke-Setup {
    Write-Host ""
    Write-Host "============ BankSploit Setup ============" -ForegroundColor Cyan
    Write-Host ""
    Write-Info "Creating database..."
    $MysqlBin = "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe"
    $sql = "CREATE DATABASE IF NOT EXISTS $DbName; CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass'; GRANT ALL ON $DbName.* TO '$DbUser'@'localhost';"
    & cmd /c "echo $sql | `"$MysqlBin`" -u root -proot" 2>$null
    Write-OK "Database ready"

    Write-Info "Loading schema..."
    Get-ChildItem -Path $SqlDir -Filter "*.sql" -ErrorAction SilentlyContinue | ForEach-Object {
        Write-Info "  Loading $($_.Name)..."
        & cmd /c "`"$MysqlBin`" -u $DbUser -p$DbPass $DbName < `"$($_.FullName)`"" 2>$null
    }
    Write-OK "Schema loaded"
    Write-OK "Setup complete!"
}

# Main
switch ($Action) {
    "start" { Invoke-Start }
    "stop" { Invoke-Stop }
    "status" { Invoke-Status }
    "setup" { Invoke-Setup }
    default { Show-Help }
}
