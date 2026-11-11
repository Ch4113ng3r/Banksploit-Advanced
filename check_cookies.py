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
    req.add_header('User-Agent', 'Python-Test')
    response = opener.open(req, timeout=10)
    print("[+] Login response code:", response.status)
    print("[+] Cookies after login:")
    for cookie in cookie_jar:
        print(f"    {cookie.name}={cookie.value[:20]}...")

    # Try API
    api_url = f"{url_base}/api/v1/accounts"
    req = urllib.request.Request(api_url)
    req.add_header('User-Agent', 'Python-Test')
    response = opener.open(req, timeout=10)
    content = response.read().decode('utf-8', errors='ignore')
    print(f"\n[+] API response status: {response.status}")
    print(f"[+] API response starts with: {content[:100]}")

except urllib.error.HTTPError as e:
    print(f"HTTP Error: {e.code}")
    print(f"Response: {e.read().decode('utf-8', errors='ignore')[:200]}")
except Exception as e:
    print(f"Error: {type(e).__name__}: {e}")
