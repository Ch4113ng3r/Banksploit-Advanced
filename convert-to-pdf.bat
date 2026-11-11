@echo off
setlocal enabledelayedexpansion

echo ================================================
echo BankSploit HTML to PDF Converter
echo ================================================
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if errorlevel 1 (
    echo [X] Node.js is not installed or not in PATH
    echo [*] Download from: https://nodejs.org/
    pause
    exit /b 1
)

echo [OK] Node.js found:
node --version
echo.

REM Check if puppeteer is installed
npm list puppeteer >nul 2>&1
if errorlevel 1 (
    echo [*] Installing Puppeteer (this may take a minute)...
    call npm install puppeteer
    if errorlevel 1 (
        echo [X] Failed to install Puppeteer
        pause
        exit /b 1
    )
)

echo [*] Starting conversion...
echo.

node convert-html-to-pdf.js

if errorlevel 1 (
    echo.
    echo [X] Conversion failed
    pause
    exit /b 1
)

echo.
echo [OK] Done! PDF is ready in the project root.
pause
