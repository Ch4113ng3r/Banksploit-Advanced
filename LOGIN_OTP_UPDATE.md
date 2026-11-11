# Login OTP Page Updated - Now Similar to Transfer OTP

**Date:** 2026-07-26  
**Status:** ✅ COMPLETE

---

## What Changed

### Before (Old Way)
Login OTP page had:
- Link: "📱 Open SMS Inbox" → Took user to `/otp-inbox` page
- Had to navigate away from OTP form
- User had to remember/copy OTP then come back

### After (New Way - Like Transfer Page)
Login OTP page now has:
- Button: "📱 View OTP Code" → Shows OTP in popup
- User stays on login form
- Easy to copy OTP and paste into form

---

## Changes Made

### 1. New Endpoint: `/get-login-otp`

**File:** `src/index.php` (lines 212-241)

**What it does:**
- Checks for pending_login session (user in OTP stage)
- Fetches the active login OTP code from database
- Returns JSON: `{"ok": true, "otp": "1234"}`

**Code:**
```php
$router->get('/get-login-otp', function() {
    if (!Session::has('pending_login')) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No pending login']);
        return;
    }
    $pending = Session::get('pending_login');
    $stmt = $pdo->prepare(
        'SELECT otp_code FROM otp_tokens
          WHERE user_id = :uid AND purpose = :p AND is_used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':uid' => (int) $pending['id'], ':p' => 'login']);
    $otpRow = $stmt->fetch();
    header('Content-Type: application/json');
    if ($otpRow) {
        echo json_encode(['ok' => true, 'otp' => $otpRow['otp_code']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No pending OTP']);
    }
});
```

### 2. Updated Login OTP Form

**File:** `src/modules/auth/login.php` (lines 227-252)

**Changes:**
- Removed link to `/otp-inbox`
- Added button: "📱 View OTP Code"
- Added JavaScript function `showLoginOTP()`
- Function fetches `/get-login-otp` endpoint
- Shows OTP in alert popup

**Code:**
```html
<div class="mt-4 flex justify-center">
    <button type="button" id="login-otp-btn" onclick="showLoginOTP()" 
            class="text-xs font-semibold rounded-lg bg-emerald-500/15 ring-1 ring-emerald-500/30 hover:bg-emerald-500/25 px-3 py-1.5 text-emerald-300 transition">
        📱 View OTP Code
    </button>
</div>

<script>
function showLoginOTP() {
    fetch('/get-login-otp', { method: 'GET' })
        .then(r => r.json())
        .then(data => {
            if (data.otp) {
                const otpCode = data.otp;
                const msg = '📱 Your Login OTP Code:\n\n' + otpCode + '\n\nEnter this 4-digit code above and click "Verify & Sign In"';
                alert(msg);
            } else {
                alert('No pending OTP found. Please try again.');
            }
        })
        .catch(err => {
            alert('Could not retrieve OTP. Error: ' + err.message);
        });
}
</script>
```

---

## How It Works

1. User logs in with credentials
2. If credentials valid, OTP form appears
3. User sees: "Enter the 4-digit OTP sent to the phone..."
4. User clicks button: "📱 View OTP Code"
5. Alert popup shows: "📱 Your Login OTP Code: 1234"
6. User copies the code (Ctrl+C in alert)
7. User pastes into OTP form
8. User clicks "Verify & Sign In"
9. Dashboard loads

---

## Testing

### Test: View Login OTP Code

```
1. Go to: http://localhost:9443/login
2. Login: BS100234 / password123
3. Click "Sign In"
4. OTP form appears
5. Click button: "📱 View OTP Code"
6. EXPECTED: Alert shows "📱 Your Login OTP Code: 1234"
7. Copy the code
8. Paste into OTP form
9. Click "Verify & Sign In"
10. EXPECTED: Logged in to dashboard
```

### Verification Checklist
- [ ] Button "📱 View OTP Code" visible on OTP page
- [ ] Click button → Alert appears
- [ ] Alert shows OTP code
- [ ] Alert has instructions
- [ ] Can copy code from alert
- [ ] Can paste into form
- [ ] OTP verification works
- [ ] Dashboard loads after verification
- [ ] No JavaScript errors

---

## Files Modified

| File | Lines | Change |
|------|-------|--------|
| `src/index.php` | 212-241 | Added `/get-login-otp` endpoint |
| `src/modules/auth/login.php` | 227-252 | Added "View OTP Code" button + JavaScript |

---

## Consistency

Now both Login and Transfer OTP pages work the same way:

| Feature | Login OTP | Transfer OTP |
|---------|-----------|---|
| View Code Button | ✅ "📱 View OTP Code" | ✅ "📱 View OTP Code" |
| Location | On login form | On transfer form |
| Shows in | Alert popup | Alert popup |
| Endpoint | `/get-login-otp` | `/get-transfer-otp` |
| Function | `showLoginOTP()` | `showTransferOTP()` |
| User stays on form | ✅ Yes | ✅ Yes |

---

## Benefits

✅ **User-friendly** - OTP visible without navigation
✅ **Consistent** - Both login and transfer work same way
✅ **Secure** - Alert popup (not visible in HTML)
✅ **Easy to use** - Click button, copy, paste, done
✅ **Quick testing** - Easy for CTF participants to find OTP

---

## Status

✅ Endpoint created  
✅ Button added to login OTP page  
✅ JavaScript function implemented  
✅ Ready for testing
