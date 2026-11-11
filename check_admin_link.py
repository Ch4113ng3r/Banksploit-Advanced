#!/usr/bin/env python3
"""Check if the main admin link appears"""

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

def test_admin_link(customer_id, token):
    """Check if admin link appears"""
    url = "http://localhost:9443/dashboard"
    
    try:
        req = urllib.request.Request(url)
        req.add_header('Cookie', f'access_token={token}')
        
        with urllib.request.urlopen(req) as response:
            html = response.read().decode('utf-8')
            
            # Look for the admin nav item - it should have href="/admin"
            if 'href="http://localhost:9443/admin"' in html or 'href="/admin"' in html:
                print(f"[YES] Admin link found for {customer_id}")
                
                # Find the context
                idx = html.find('/admin')
                context = html[max(0, idx-200):idx+200]
                print(f"Context: ...{context}...")
            else:
                print(f"[NO] Admin link NOT found for {customer_id}")
                
                # Check what admin references exist
                if 'admin' in html.lower():
                    # Count occurrences
                    count = html.lower().count('admin')
                    print(f"  But 'admin' appears {count} times in HTML")
                    
                    # Find first occurrence
                    idx = html.lower().find('admin')
                    context = html[max(0, idx-100):idx+150]
                    print(f"  First occurrence: ...{context}...")
            
    except Exception as e:
        print(f"ERROR: {e}")

# Test with admin user
print("[*] Testing admin link visibility")
print("=" * 70)
print()

token = forge_jwt("ADMIN001", "admin")
test_admin_link("ADMIN001", token)

print()
print("[*] Searching HTML for 'Admin' nav item rendering...")

# Get the HTML and search for admin nav rendering
url = "http://localhost:9443/dashboard"
try:
    req = urllib.request.Request(url)
    token = forge_jwt("ADMIN001", "admin")
    req.add_header('Cookie', f'access_token={token}')
    
    with urllib.request.urlopen(req) as response:
        html = response.read().decode('utf-8')
        
        # Look for where nav items are rendered
        nav_start = html.find('<nav class="flex-1')
        nav_end = html.find('</nav>', nav_start)
        nav_section = html[nav_start:nav_end+6]
        
        print("[*] Navigation section (first 2000 chars):")
        print(nav_section[:2000])
        
        # Check what's at the end of nav
        print("\n[*] Last part of nav section:")
        print(nav_section[-1000:])
        
except Exception as e:
    print(f"ERROR: {e}")
