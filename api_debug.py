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
    response = opener.open(req, timeout=10)
    print("[+] Logged in")

    # Get account list
    api_url = f"{url_base}/api/v1/accounts"
    req = urllib.request.Request(api_url)
    response = opener.open(req, timeout=10)
    content = response.read().decode('utf-8')
    print(f"[+] Accounts response: {content[:500]}")

except Exception as e:
    print(f"Error: {type(e).__name__}: {e}")
