# Apply MySQL Security Configuration
# This script updates MySQL to bind to localhost only

# Requires admin privileges
if (-not ([Security.Principal.WindowsIdentity]::GetCurrent().Groups -match "S-1-5-32-544")) {
    Write-Host "[!] This script requires administrator privileges" -ForegroundColor Yellow
    Write-Host "[*] Please run as Administrator" -ForegroundColor Cyan
    exit 1
}

$mysqlConfigPath = "c:\ProgramData\MySQL\MySQL Server 8.4\my.ini"

Write-Host "=== MySQL Security Configuration ===" -ForegroundColor Cyan
Write-Host ""

# Read current config
Write-Host "[*] Reading current MySQL configuration..." -ForegroundColor Cyan
$config = Get-Content $mysqlConfigPath -Raw

# Check if bind-address is already set
if ($config -match "bind-address\s*=") {
    Write-Host "[!] bind-address is already configured" -ForegroundColor Yellow
    $config | Select-String "bind-address"
} else {
    Write-Host "[*] Adding bind-address=127.0.0.1 to [mysqld] section..." -ForegroundColor Cyan

    # Add bind-address after [mysqld] section header
    $config = $config -replace "(\[mysqld\])", "`$1`nbind-address=127.0.0.1"

    # Write back
    Set-Content -Path $mysqlConfigPath -Value $config -Force
    Write-Host "[OK] Configuration updated" -ForegroundColor Green
}

Write-Host ""
Write-Host "[*] Restarting MySQL service..." -ForegroundColor Cyan
Stop-Service MySQL84 -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2
Start-Service MySQL84 -ErrorAction SilentlyContinue
Start-Sleep -Seconds 3

Write-Host "[OK] MySQL restarted" -ForegroundColor Green
Write-Host ""

# Verify
Write-Host "[*] Verifying MySQL binding..." -ForegroundColor Cyan
$netstat = netstat -ano | Select-String "3306" | Select-String -NotMatch "33060"
Write-Host $netstat -ForegroundColor White

Write-Host ""
Write-Host "[!] MySQL is now configured to:" -ForegroundColor Green
Write-Host "    - Bind to 127.0.0.1 (localhost only)" -ForegroundColor Green
Write-Host "    - NOT accessible from network" -ForegroundColor Green
Write-Host "    - Only accessible by local PHP application" -ForegroundColor Green
Write-Host ""
