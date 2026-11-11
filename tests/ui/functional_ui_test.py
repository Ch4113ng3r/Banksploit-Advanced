#!/usr/bin/env python3
"""
BankSploit — functional UI smoke tests for the banking portal (port 9443).

Verifies that the *features* work (login, dashboard, statements, beneficiaries,
transfer, cards, loans, payments, profile, preferences, logout) — NOT the
vulnerabilities. Everything is driven through the HTML pages and their forms,
exactly as a browser would: GET a page, POST its <form>, follow redirects, and
assert on the rendered HTML.

It never calls the business JSON APIs (/api/v1/*). The only non-page calls are
/get-login-otp and /get-transfer-otp — the same helper endpoints the pages' own
JavaScript calls to show the OTP to the user — used here in place of a human
reading the code off the screen. A guard aborts if any /api/v1 path is requested.

Usage:
    python3 tests/ui/functional_ui_test.py
    BASE=http://127.0.0.1:9443 CUSTOMER_ID=BS100234 PASSWORD=password123 \
        python3 tests/ui/functional_ui_test.py

Exit code 0 = all passed, 1 = one or more failed.

Note: transfer / payment / loan / beneficiary tests create real records and move
a tiny amount of money in the training DB. Re-run freely; to reset the data use
./start-app.sh reset-full.
"""

import http.cookiejar
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.request

BASE = os.environ.get("BASE", "http://127.0.0.1:9443").rstrip("/")
CUSTOMER_ID = os.environ.get("CUSTOMER_ID", "BS100234")
PASSWORD = os.environ.get("PASSWORD", "password123")
# A second real user, used to confirm the transfer CREDITS the payee (double
# entry) by reading that user's own balance — not via the IDOR vulnerability.
DEST_ID = os.environ.get("DEST_ID", "BS100235")
DEST_PASSWORD = os.environ.get("DEST_PASSWORD", "priya@123")

# Endpoints the tests are allowed to touch: HTML page routes + the two OTP
# display helpers the pages' own JS uses. Business APIs are off-limits.
OTP_HELPERS = {"/get-login-otp", "/get-transfer-otp"}

results = []  # (name, ok, detail)


class Session:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar),
            urllib.request.HTTPRedirectHandler(),
        )

    def _check_path(self, path):
        if path.startswith("/api/"):
            raise AssertionError(
                f"TEST BUG: tried to call a direct API ({path}); UI tests must use pages only."
            )

    def get(self, path):
        self._check_path(path)
        req = urllib.request.Request(BASE + path, method="GET")
        try:
            resp = self.opener.open(req, timeout=15)
            return resp.getcode(), resp.read().decode("utf-8", "replace"), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace"), BASE + path

    def post(self, path, data):
        self._check_path(path)
        body = urllib.parse.urlencode(data).encode()
        req = urllib.request.Request(BASE + path, data=body, method="POST")
        req.add_header("Content-Type", "application/x-www-form-urlencoded")
        try:
            resp = self.opener.open(req, timeout=15)
            return resp.getcode(), resp.read().decode("utf-8", "replace"), resp.geturl()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace"), BASE + path

    def otp(self, which):
        # which: "login" or "transfer" -> uses the page's own helper endpoint.
        path = "/get-login-otp" if which == "login" else "/get-transfer-otp"
        assert path in OTP_HELPERS
        req = urllib.request.Request(BASE + path, method="GET")
        resp = self.opener.open(req, timeout=15)
        return json.loads(resp.read().decode()).get("otp", "")


def record(name, ok, detail=""):
    results.append((name, ok, detail))
    mark = "PASS" if ok else "FAIL"
    line = f"  [{mark}] {name}"
    if detail:
        line += f" — {detail}"
    print(line)


def title_of(html):
    m = re.search(r"<title>(.*?)</title>", html, re.S | re.I)
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else ""


def balance(sess, acct_number):
    """Read an account's current balance off the dashboard (₹ shown as &#8377;)."""
    _, body, _ = sess.get("/dashboard")
    t = re.sub(r"<[^>]+>", " ", body).replace("&#8377;", "₹")
    m = re.search(re.escape(acct_number) + r"[^\d]{0,40}₹([\d,]+\.\d{2})", t)
    return float(m.group(1).replace(",", "")) if m else None


def first_option(html, select_name):
    """First <option value="..."> inside the named <select>."""
    block = re.search(
        r'name="%s".*?</select>' % re.escape(select_name), html, re.S
    )
    if not block:
        return None
    vals = re.findall(r'<option[^>]*value="([^"]+)"', block.group(0))
    return vals[0] if vals else None


def login(sess, customer_id=CUSTOMER_ID, password=PASSWORD):
    sess.post("/login", {"customer_id": customer_id, "password": password})
    code = sess.otp("login")
    return sess.post("/login", {"otp": code})


# --------------------------------------------------------------------------- #
#  Test cases
# --------------------------------------------------------------------------- #

def tc_login_invalid():
    s = Session()
    _, body, _ = s.post("/login", {"customer_id": CUSTOMER_ID, "password": "wrong-password-xyz"})
    still_on_login = 'name="customer_id"' in body or "credential" in body.lower() or "incorrect" in body.lower()
    code, dash, url = s.get("/dashboard")
    not_authed = "/login" in url or 'name="customer_id"' in dash
    record("login rejects wrong password", still_on_login and not_authed,
           f"stayed on login={still_on_login}, dashboard blocked={not_authed}")


def tc_login_valid(s):
    _, body, url = login(s)
    ok = "Dashboard" in title_of(body) or url.endswith("/dashboard")
    record("login with valid OTP reaches dashboard", ok, f"title={title_of(body)!r}")
    return ok


def tc_dashboard(s):
    code, body, _ = s.get("/dashboard")
    ok = code == 200 and "Dashboard" in title_of(body)
    has_account = re.search(r"\b\d{14}\b", body) is not None
    record("dashboard shows accounts", ok and has_account,
           f"status={code}, account#visible={has_account}")


def tc_statements(s):
    code, body, _ = s.get("/statements")
    ok = code == 200 and "Statements" in title_of(body)
    # filter form (GET) — submit a date range + search term
    fcode, _, _ = s.get("/statements?" + urllib.parse.urlencode(
        {"from_date": "2020-01-01", "to_date": "2030-01-01", "q": "a"}))
    record("statements page + filter work", ok and fcode == 200,
           f"list={code}, filtered={fcode}")


def tc_beneficiaries(s):
    code, body, _ = s.get("/beneficiaries")
    ok = code == 200 and "Beneficiaries" in title_of(body)
    uniq = "UITest %d" % int(time.time())
    s.post("/beneficiaries", {
        "action": "add",
        "name": uniq,
        "account_number": "50900%09d" % (int(time.time()) % 1000000000),
        "ifsc_code": "HDFC0001234",
        "bank_name": "HDFC Bank",
    })
    # Effect: re-open the list from scratch and confirm it persisted.
    _, reload_body, _ = s.get("/beneficiaries")
    persisted = uniq in reload_body
    record("beneficiary added and persists on reload", ok and persisted,
           f"list={code}, '{uniq}' present after reload={persisted}")


def tc_transfer(s):
    _, page, _ = s.get("/transfer")
    frm = first_option(page, "from_account_number")
    to = first_option(page, "to_account_number")
    if not frm or not to:
        record("transfer actually moves money", False, f"could not read accounts (from={frm}, to={to})")
        return
    remark = "uix%d" % int(time.time())
    amount = 100.0
    before = balance(s, frm)
    # Wizard: initiate -> confirm (issues OTP) -> verify
    s.post("/transfer", {
        "action": "initiate", "from_account_number": frm,
        "to_account_number": to, "amount": str(int(amount)), "mode": "IMPS", "remarks": remark,
    })
    s.post("/transfer", {"action": "confirm"})
    _, receipt, _ = s.post("/transfer", {"action": "verify", "otp": s.otp("transfer")})
    receipt_ok = "Transfer successful" in receipt or "Reference" in receipt
    after = balance(s, frm)
    debited = (before is not None and after is not None and after < before)
    # Effect in the ledger: the debit shows in the source account's statement.
    _, stmt, _ = s.get("/statements?" + urllib.parse.urlencode({"account": frm}))
    in_ledger = remark in stmt
    record("transfer actually moves money", receipt_ok and debited and in_ledger,
           f"balance {before}->{after} (debited={debited}), remark in statement={in_ledger}")


def tc_transfer_double_entry(s):
    """Confirm the payee is actually credited (double-entry), verified by logging
    in as the payee and reading their own balance — not via IDOR."""
    # Payee session: read their own primary account number + balance.
    d = Session()
    _, dbody, durl = login(d, DEST_ID, DEST_PASSWORD)
    if "Dashboard" not in title_of(dbody) and not durl.endswith("/dashboard"):
        record("transfer credits the payee (double-entry)", False, "could not log in as payee")
        return
    m = re.search(r"\b(\d{14})\b", re.sub(r"<[^>]+>", " ", dbody))
    dest_acct = m.group(1) if m else None
    before = balance(d, dest_acct) if dest_acct else None
    if before is None:
        record("transfer credits the payee (double-entry)", False, f"could not read payee balance (acct={dest_acct})")
        return

    # Sender: add a beneficiary pointing at the payee's account, then transfer.
    amount = 200.0
    s.post("/beneficiaries", {
        "action": "add", "name": "DoubleEntry %d" % int(time.time()),
        "account_number": dest_acct, "ifsc_code": "HDFC0001234", "bank_name": "HDFC Bank",
    })
    _, page, _ = s.get("/transfer")
    frm = first_option(page, "from_account_number")
    s.post("/transfer", {
        "action": "initiate", "from_account_number": frm,
        "to_account_number": dest_acct, "amount": str(int(amount)), "mode": "IMPS", "remarks": "ui-de",
    })
    s.post("/transfer", {"action": "confirm"})
    _, receipt, _ = s.post("/transfer", {"action": "verify", "otp": s.otp("transfer")})
    receipt_ok = "Transfer successful" in receipt or "Reference" in receipt

    after = balance(d, dest_acct)  # fresh read of the payee's own dashboard
    credited = (after is not None and abs((after - before) - amount) < 0.5)
    record("transfer credits the payee (double-entry)", receipt_ok and credited,
           f"payee {dest_acct} balance {before}->{after} (expected +{amount})")


def tc_cards(s):
    code, body, _ = s.get("/cards")
    record("cards page loads", code == 200 and "Cards" in title_of(body), f"status={code}")


def tc_loans(s):
    code, body, _ = s.get("/loans")
    ok = code == 200 and "Loans" in title_of(body)
    _, applied, _ = s.post("/loans", {
        "loan_type": "personal", "amount": "100000",
        "tenure_months": "12", "interest_rate": "10.5",
    })
    applied_ok = "Loan application submitted" in applied
    # Effect: the EMI quoted on apply now appears as a row in the loans list.
    emi = re.search(r"EMI:\s*&#8377;\s*([\d,]+\.\d{2})", applied) or re.search(r"EMI:\s*₹?\s*([\d,]+\.\d{2})", applied)
    in_list = False
    if emi:
        _, loans_body, _ = s.get("/loans")
        in_list = emi.group(1) in loans_body
    record("loan application is created and listed", ok and applied_ok and in_list,
           f"list={code}, apply={applied_ok}, EMI {emi.group(1) if emi else '?'} in list={in_list}")


def tc_payments(s):
    code, page, _ = s.get("/payments")
    ok = code == 200 and "Payments" in title_of(page)
    acc_id = first_option(page, "from_account_id")
    # Resolve the account NUMBER for that id so we can read its balance.
    label = re.search(r'value="%s"[^>]*>([^<]*)' % re.escape(acc_id or ""), page)
    acc_num = re.search(r"\d{14}", label.group(1)) if label else None
    acc_num = acc_num.group(0) if acc_num else None
    biller = "UIPay%d" % int(time.time())
    before = balance(s, acc_num) if acc_num else None
    paid_ok = False
    if acc_id:
        _, paid, _ = s.post("/payments", {
            "biller_type": "electricity", "biller_name": biller,
            "amount": "50", "from_account_id": acc_id, "remarks": "ui-test",
        })
        paid_ok = "Payment successful" in paid or "successful" in paid.lower()
    after = balance(s, acc_num) if acc_num else None
    debited = (before is not None and after is not None and after < before)
    # Effect: the bill shows up in the payment history on the page.
    _, hist, _ = s.get("/payments")
    in_history = biller in hist
    record("bill payment debits account and shows in history",
           ok and paid_ok and in_history and (debited or acc_num is None),
           f"from={acc_num}, balance {before}->{after} (debited={debited}), in history={in_history}")


def tc_profile(s):
    code, body, _ = s.get("/profile")
    ok = code == 200 and "Profile" in title_of(body)
    has_detail = "@" in body  # email shown
    record("profile page shows details", ok and has_detail, f"status={code}")


def tc_preferences(s):
    code, body, _ = s.get("/preferences")
    ok = code == 200 and "Preferences" in title_of(body)
    _, saved, _ = s.post("/preferences", {
        "theme": "light", "landing": "dashboard", "per_page": "10",
    })
    saved_ok = "Preferences saved." in saved
    record("preferences page + save", ok and saved_ok, f"list={code}, saved={saved_ok}")


def tc_logout(s):
    s.get("/logout")
    code, body, url = s.get("/dashboard")
    ok = "/login" in url or 'name="customer_id"' in body
    record("logout ends the session", ok, f"dashboard after logout -> login={ok}")


def main():
    print(f"BankSploit UI functional tests → {BASE} as {CUSTOMER_ID}\n")

    # Independent-session test first.
    tc_login_invalid()

    # Shared authenticated session for the rest.
    s = Session()
    if not tc_login_valid(s):
        print("\nLogin failed — aborting the authenticated tests.")
        _summary()
        return 1

    tc_dashboard(s)
    tc_statements(s)
    tc_beneficiaries(s)
    tc_transfer(s)
    tc_transfer_double_entry(s)
    tc_cards(s)
    tc_loans(s)
    tc_payments(s)
    tc_profile(s)
    tc_preferences(s)
    tc_logout(s)  # keep last — it ends the session

    return _summary()


def _summary():
    passed = sum(1 for _, ok, _ in results if ok)
    total = len(results)
    print(f"\n{passed}/{total} checks passed.")
    return 0 if passed == total else 1


if __name__ == "__main__":
    sys.exit(main())
