#!/usr/bin/env python3
import urllib.request
import http.cookiejar
import re

url_base = "http://10.141.199.78:9443"
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

try:
    req = urllib.request.Request(f"{url_base}/login")
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    # Save for inspection
    with open('login_page.html', 'w', encoding='utf-8') as f:
        f.write(html[:5000])

    # Look for CSRF
    if 'csrf_token' in html:
        print("[+] CSRF token field found")
        match = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
        if match:
            print(f"[+] CSRF: {match.group(1)}")
        else:
            print("[-] Could not extract CSRF value")
            # Try alternative pattern
            match = re.search(r'csrf_token["\']?\s*[:=]\s*["\']([^"\']+)', html)
            if match:
                print(f"[+] Alternative CSRF: {match.group(1)}")
    else:
        print("[-] No CSRF field in form")

    # Check for form tag
    form_match = re.search(r'<form[^>]+>', html)
    if form_match:
        print(f"[+] Form found: {form_match.group(0)[:100]}")

    print(f"[+] Response length: {len(html)}")
    print("[+] Saved to login_page.html")

except Exception as e:
    print(f"Error: {e}")
