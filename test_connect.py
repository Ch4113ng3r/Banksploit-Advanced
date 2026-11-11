#!/usr/bin/env python3
import urllib.request
import urllib.error
import ssl

url = "http://10.141.199.78:9443/login"

try:
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE

    response = urllib.request.urlopen(url, context=context, timeout=10)
    content = response.read().decode('utf-8', errors='ignore')

    print(f"Status: {response.status}")
    print(f"Content length: {len(content)}")
    print(f"\nFirst 500 chars:\n{content[:500]}")
except Exception as e:
    print(f"Error: {type(e).__name__}: {e}")
