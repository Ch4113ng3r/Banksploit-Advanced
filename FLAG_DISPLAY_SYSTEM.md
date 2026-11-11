# CTF Flag Display System

**Date:** July 26, 2026  
**Status:** ✅ IMPLEMENTED

## Overview

A real-time flag detection and display system has been added to automatically capture and display CTF flags when participants exploit vulnerabilities successfully.

---

## How It Works

### Flag Detection Methods

The system intercepts and detects flags from **two sources**:

1. **Response Headers** — Flags returned in `X-CTF-Flag` HTTP header
2. **JSON Response Body** — Flags in `ctf_flag` field in JSON responses

### When Flag Is Detected

- ✨ **Modal popup** appears with flag and "FLAG CAPTURED!" banner
- 🔊 **Audio cue** plays (subtle success tone)
- 📋 **Copy button** for easy flag copying
- 📊 **Console log** shows flag in developer console
- 🚩 **Flag emoji** animation with bounce effect

### Prevention of Duplicate Popups

- Each unique flag is captured only once per session
- Set-based tracking prevents spam or repeated displays

---

## Affected Vulnerabilities

### **1. auth.g1 — SQL Injection (Login)**

| Detail | Value |
|--------|-------|
| **Location** | `/login` (POST) |
| **Flag Delivery** | `X-CTF-Flag` header |
| **Trigger** | Non-alphanumeric input in Customer ID field (e.g., `' OR '1'='1`) |
| **Exploit** | SQLi login bypass with SQL injection payload |
| **Code Path** | `src/modules/auth/login.php:146` |

**How to trigger:**
```
Customer ID: ' OR '1'='1
Password: anything
→ Flag displays when SQLi succeeds
```

---

### **2. auth.l10 — OTP Brute Force (No Rate Limit)**

| Detail | Value |
|--------|-------|
| **Location** | `/login` (OTP verification) |
| **Flag Delivery** | `X-CTF-Flag` header (HTML form) + `ctf_flag` field (JSON API) |
| **Trigger** | Valid OTP submitted after brute-forcing all 10,000 combinations |
| **Exploit** | OTP bypass via rapid-fire requests without rate limiting |
| **Code Path** | `src/modules/auth/login.php:121` + `src/modules/auth/api.php:144` |

**How to trigger:**
```
1. Submit any invalid credentials
2. Get stuck at OTP screen
3. Brute-force OTP 0000-9999 without being rate-limited
→ Flag displays when correct OTP accepted
```

---

### **3. auth.g1 (API) — SQL Injection (API Path)**

| Detail | Value |
|--------|-------|
| **Location** | `/api/v1/auth/login` (POST, JSON) |
| **Flag Delivery** | `ctf_flag` field in JSON response |
| **Trigger** | Non-alphanumeric Customer ID with SQLi payload |
| **Exploit** | SQLi login bypass via REST API |
| **Code Path** | `src/modules/auth/api.php:76` |

**How to trigger:**
```json
POST /api/v1/auth/login
{
  "customer_id": "' OR '1'='1",
  "password": "anything"
}
→ Flag displays in JSON response
```

---

### **4. statements.l1 — IDOR (Account Statements)**

| Detail | Value |
|--------|-------|
| **Location** | `/statements?account_id=X` (GET) |
| **Flag Delivery** | `X-CTF-Flag` header |
| **Trigger** | Accessing another user's account statements via account ID manipulation |
| **Exploit** | Direct IDOR: change `account_id` parameter to view any account's statements |
| **Code Path** | `src/modules/statements/index.php:48` |

**How to trigger:**
```
1. Login as BS100234 (Rahul)
2. Go to Statements page
3. Note the account_id (e.g., 1)
4. Change URL to: /statements?account_id=4 (Priya's account)
5. View another user's statements
→ Flag displays in response header
```

---

### **5. transfer.l1 — IDOR (Fund Transfer)**

| Detail | Value |
|--------|-------|
| **Location** | `/transfer` (POST transfer execution) |
| **Flag Delivery** | `X-CTF-Flag` header (HTML form) + `ctf_flag` field (JSON API) |
| **Trigger** | Transferring funds from another user's account via account ID manipulation |
| **Exploit** | IDOR in transfer: manipulate `from_account_id` to transfer from other accounts |
| **Code Path** | `src/modules/transfer/index.php:101` + `src/modules/transfer/api.php:187` |

**How to trigger:**
```
1. Login as BS100234 (Rahul)
2. Go to Transfer page
3. Try to submit form with from_account_id=4 (Priya's account)
4. Transfer succeeds using another user's account
→ Flag displays in response header or JSON
```

---

## Flag Display Locations

### **Which Vulnerabilities Show Flags Automatically:**

✅ **Showing flags in headers/responses:**
- `auth.g1` — Login SQLi
- `auth.l10` — OTP brute force
- `statements.l1` — Statement IDOR
- `transfer.l1` — Transfer IDOR

### **NOT included (no flag headers):**
- Other auth levels (l11, l12, l13, etc.) — flags in different delivery methods (JS files, cookies, session data)
- Loans, Cards, Beneficiaries — no header-based flags
- Admin vulnerabilities — different flag delivery

---

## Implementation Details

### **JavaScript Flag Detection**

**Location:** `src/templates/layout.php` (bottom, before `</body>`)

**Mechanism:**
1. Intercepts all `fetch()` calls globally
2. Checks response headers for `X-CTF-Flag`
3. Parses JSON responses for `ctf_flag` field
4. Displays modal only once per unique flag

**Code:**
```javascript
window.fetch = function(...args) {
    return originalFetch.apply(this, args).then(response => {
        const flag = response.headers.get('X-CTF-Flag');
        if (flag && !capturedFlags.has(flag)) {
            capturedFlags.add(flag);
            displayFlag(flag);
        }
        return response;
    });
};
```

### **Modal Styling**

- **Background:** Gradient emerald (emerald-900 to emerald-950)
- **Border:** Ring-2 with emerald-500/50
- **Text:** Emerald-300 and emerald-100 (high contrast)
- **Animation:** Bounce effect with scale and rotation
- **Buttons:** Close and Copy buttons

### **Accessibility**

- ✅ Flag text is selectable (select-all class)
- ✅ Copy-to-clipboard functionality
- ✅ Visible in browser DevTools console
- ✅ Sound cue (Web Audio API, graceful fallback)
- ✅ Works in light/dark theme

---

## Testing Flags

### **Test Flag 1: auth.g1 (Login SQLi)**

```
URL: http://localhost:9443/login
Customer ID: ' OR '1'='1
Password: test
→ Flag modal appears on login
```

### **Test Flag 2: auth.l10 (OTP Brute Force)**

```
URL: http://localhost:9443/login
1. Submit credentials to reach OTP page
2. Rapidly submit OTP 0000-9999 via fetch/curl
3. When correct OTP accepted
→ Flag modal appears
```

### **Test Flag 3: statements.l1 (IDOR)**

```
URL: http://localhost:9443/statements?account_id=4
Login first, then access another account's statements
→ Flag modal appears
```

### **Test Flag 4: transfer.l1 (IDOR)**

```
URL: http://localhost:9443/transfer
Login, then in DevTools modify from_account_id to another user's ID
→ Flag modal appears after successful transfer
```

---

## Files Modified

```
src/templates/layout.php
├── Added flag modal HTML (div#flag-modal)
├── Added flag capture JavaScript
├── Added animation styles (@keyframes flag-bounce)
├── Added copyFlag() function
├── Implemented fetch() interceptor
└── Added console logging for flags
```

---

## Browser Compatibility

- ✅ Chrome/Edge (full support)
- ✅ Firefox (full support)
- ✅ Safari (full support)
- ✅ Mobile browsers (responsive modal)

---

## How Participants Use It

1. **Exploit a vulnerability** (SQLi, IDOR, brute-force, etc.)
2. **Modal automatically appears** with the captured flag
3. **Click "Copy"** to copy flag to clipboard
4. **Paste into flag submission** form or `/lab` page
5. **Score points!**

---

## Troubleshooting

| Issue | Cause | Solution |
|-------|-------|----------|
| Modal doesn't appear | Vulnerability not at vulnerable level | Check training engine settings for vulnerability level (must be vulnerable) |
| Multiple modals | Browser cache | Clear localStorage and browser cache |
| Copy button doesn't work | Clipboard API unavailable | Use manual selection + Ctrl+C |
| No audio | User disabled audio | System gracefully ignores audio errors |
| Flag not showing | Ad blocker/script blocker | Disable extensions, check console for errors |

---

## Future Enhancements

- [ ] Flag notification badge in navbar
- [ ] Flag history/log on dashboard
- [ ] Export captured flags as JSON
- [ ] Leaderboard showing flags captured per participant
- [ ] Confetti animation on flag capture
- [ ] Flag submission directly from modal

---

**Status:** Ready for CTF deployment 🚩

