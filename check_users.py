#!/usr/bin/env python3
"""Check what users exist in the database"""

import json
import urllib.request
import urllib.error

def get_users():
    """Get list of users - might need to login first"""
    base_url = "http://localhost:9443"
    
    # Try to get admin user info
    print("[*] Checking what users might exist...")
    print()
    
    # Common test user IDs
    test_users = ["BS100001", "BS000001", "BS000002", "admin001", "BS001", "1"]
    
    for uid in test_users:
        print(f"[?] Testing customer_id: {uid}")
    
    # Let's try to login and see what user IDs are used
    print("\n[*] Attempting login to discover valid customer IDs...")
    print()
    
    # Try a standard login
    url = f"{base_url}/api/v1/auth/login"
    print(f"[*] Testing login endpoint: {url}")
    
    data = json.dumps({
        "customer_id": "BS000001",
        "password": "password"
    }).encode()
    
    try:
        req = urllib.request.Request(url, data=data, method='POST')
        req.add_header('Content-Type', 'application/json')
        
        with urllib.request.urlopen(req) as response:
            result = json.loads(response.read().decode())
            print(f"Login response: {json.dumps(result, indent=2)}")
    except urllib.error.HTTPError as e:
        print(f"[LOGIN ERROR] HTTP {e.code}")
        try:
            error = json.loads(e.read().decode())
            print(f"Error: {json.dumps(error, indent=2)}")
        except:
            pass
    except Exception as e:
        print(f"[ERROR] {type(e).__name__}: {e}")

if __name__ == "__main__":
    get_users()
