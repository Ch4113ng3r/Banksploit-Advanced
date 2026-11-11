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
    html = response.read().decode('utf-8', errors='ignore')

    # Save login response
    with open('login_response.html', 'w', encoding='utf-8') as f:
        f.write(html)

    # Check if it contains redirect or success message
    if 'dashboard' in html.lower() or 'statement' in html.lower():
        print("[+] Login successful - got app page")
    elif 'login' in html.lower():
        print("[-] Still on login page - check credentials or errors")
    else:
        print("[?] Unexpected response")

    # Try API call
    api_url = f"{url_base}/api/v1/accounts"
    req = urllib.request.Request(api_url)
    response = opener.open(req, timeout=10)
    content = response.read().decode('utf-8', errors='ignore')

    if content.startswith('{'):
        print("[+] Got JSON from API!")
    elif content.startswith('<!DOCTYPE'):
        print("[-] Got HTML from API (not authenticated)")
    else:
        print(f"[?] Unexpected API response: {content[:50]}")

except Exception as e:
    print(f"Error: {e}")
