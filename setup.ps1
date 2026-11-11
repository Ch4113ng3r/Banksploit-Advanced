# Setup script for BankSploit CTF

param(
    [string]$Action = "all"
)

$ErrorActionPreference = "Continue"

# Configuration
$phpPath = "C:\Users\G01197938\Downloads\php-8.2.32-nts-Win32-vs16-x86"
$mysqlPath = "C:\Program Files\MySQL\MySQL Server 8.4\bin"
$env:PATH = "$phpPath;$mysqlPath;$env:PATH"
$env:PHPRC = $phpPath

$MysqlBin = Join-Path $mysqlPath "mysql.exe"
$ProjectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$SqlDir = Join-Path $ProjectDir "sql"

Write-Host "=== BankSploit CTF Setup ===" -ForegroundColor Cyan
Write-Host ""

# Step 1: Create database and user
Write-Host "[1/4] Creating database and user..." -ForegroundColor Cyan
$createDb = @(
    "CREATE DATABASE IF NOT EXISTS banksploit;",
    "CREATE USER IF NOT EXISTS 'banksploit'@'localhost' IDENTIFIED BY 'banksploit_pass';",
    "CREATE USER IF NOT EXISTS 'banksploit'@'127.0.0.1' IDENTIFIED BY 'banksploit_pass';",
    "GRANT ALL PRIVILEGES ON banksploit.* TO 'banksploit'@'localhost';",
    "GRANT ALL PRIVILEGES ON banksploit.* TO 'banksploit'@'127.0.0.1';",
    "FLUSH PRIVILEGES;"
) -join "`r`n"

$tempFile = [System.IO.Path]::GetTempFileName()
$createDb | Out-File -FilePath $tempFile -Encoding UTF8
& cmd /c "`"$MysqlBin`" -u root -proot -h 127.0.0.1 --protocol=TCP < `"$tempFile`"" 2>&1 | Where-Object { $_ -notmatch "Warning" }
Remove-Item $tempFile -Force
if ($LASTEXITCODE -eq 0) {
    Write-Host "[OK] Database and user created" -ForegroundColor Green
} else {
    Write-Host "[!] Database/user creation had issues (may already exist)" -ForegroundColor Yellow
}

# Step 2: Load SQL schema
Write-Host ""
Write-Host "[2/4] Loading SQL schema..." -ForegroundColor Cyan

$sqlFiles = Get-ChildItem -Path $SqlDir -Filter "*.sql" | Sort-Object Name

foreach ($file in $sqlFiles) {
    Write-Host "  Loading $($file.Name)..." -ForegroundColor Cyan
    & cmd /c "`"$MysqlBin`" -u banksploit -p`"banksploit_pass`" -h 127.0.0.1 --protocol=TCP banksploit < `"$($file.FullName)`"" 2>&1 | Where-Object { $_ -notmatch "Warning" }
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  [OK] $($file.Name)" -ForegroundColor Green
    } else {
        Write-Host "  [!] Issues loading $($file.Name)" -ForegroundColor Yellow
    }
}

# Step 2.5: Run migrations
Write-Host ""
Write-Host "[2.5/4] Running database migrations..." -ForegroundColor Cyan
$migrationFile = Join-Path $SqlDir "migration-add-username-to-ctf-flags.sql"
if (Test-Path $migrationFile) {
    Write-Host "  Running: 04-add-username-to-ctf-flags.sql..." -ForegroundColor Cyan
    & cmd /c "`"$MysqlBin`" -u banksploit -p`"banksploit_pass`" -h 127.0.0.1 --protocol=TCP banksploit < `"$migrationFile`"" 2>&1 | Where-Object { $_ -notmatch "Warning|Duplicate" }
    Write-Host "  [OK] Username column migration applied" -ForegroundColor Green
}

# Step 3: Verify setup
Write-Host ""
Write-Host "[3/4] Verifying database..." -ForegroundColor Cyan
$verifySql = "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'banksploit';"
$result = $verifySql | & $MysqlBin -u banksploit -p"banksploit_pass" -h 127.0.0.1 2>&1 | Where-Object { $_ -notmatch "Warning" }
Write-Host $result

# Step 4: Seed flags
Write-Host ""
Write-Host "[4/4] Seeding CTF flags..." -ForegroundColor Cyan
try {
    & php (Join-Path $ProjectDir "src\scripts\seed_flags.php") 2>&1 | Where-Object { $_ -notmatch "Warning" }
    Write-Host "  [OK] Flags seeded" -ForegroundColor Green
} catch {
    Write-Host "  [!] Flag seeding had issues (may already be seeded)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[Complete] Setup Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Configuration:" -ForegroundColor Cyan
Write-Host "  Database:  banksploit" -ForegroundColor White
Write-Host "  User:      banksploit" -ForegroundColor White
Write-Host "  Password:  banksploit_pass" -ForegroundColor White
Write-Host "  Host:      127.0.0.1" -ForegroundColor White
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  .\start-app.ps1 start # Start PHP and MySQL servers" -ForegroundColor White
Write-Host "  http://localhost:9443 # Access the CTF app" -ForegroundColor White
