#!/usr/bin/env python3
import urllib.request
import http.cookiejar
import re

url_base = "http://10.141.199.78:9443"
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

req = urllib.request.Request(f"{url_base}/login")
response = opener.open(req, timeout=10)
html = response.read().decode('utf-8', errors='ignore')

# Find form
form_start = html.find('<form')
form_end = html.find('</form>', form_start) + 7

if form_start >= 0:
    form = html[form_start:form_end]
    print(form)
    print("\n\n--- FIELDS ---")
    # Find all input fields
    for match in re.finditer(r'<input[^>]+>', form):
        print(match.group(0))
else:
    print("No form found")
