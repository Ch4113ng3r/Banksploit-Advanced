# Vulnerability Level Plan — Next Batch

Planning doc for the next set of vulnerabilities to rework into graduated ladders,
following the reference pattern already shipped for **auth.g1** and **auth.l11**
(see `docs/VULN_REWORK_CHECKLIST.md`). **Nothing here is implemented yet** — this is
the agreed plan; we pick one at a time, implement, verify live, document, and mark
verified before moving on.

Level convention (same as the engine): **L0** fully vulnerable → **L1** weak
mitigation (still bypassable) → **L2** partial/harder fix (still bypassable, a
*different* technique than L1) → **L3** secure (real fix, no flag). `isVulnerable()`
is true while level < 3. Scoring keeps the existing per-vuln `points` arrays
(increasing with level; +50 steps; L3 = 0).

Status legend: ☐ planned · ◐ in progress · ✅ implemented+verified.

| # | Vuln | Module.key | CWE | Current lvl | Points | Status |
|---|------|-----------|-----|-------------|--------|--------|
| 1 | Session puzzling | auth.l25 | 841 | 3 | 300/350/400 | ☐ |
| 2 | SQLi (data extraction) | statements.g2 | 89 | 3 | 300/350/400 | ☐ |
| 3 | Reflected XSS | statements.g4 | 79 | 3 | 100/150/200 | ☐ |
| 4 | Command injection | statements.g9 | 78 | 3 | 300/350/400 | ☐ |
| 5 | IDOR — transfer from other account | transfer.l1 | 639 | 3 | 300/350/400 | ☐ |
| 6 | XXE | loans.g10 | 611 | 3 | 300/350/400 | ☐ |
| 7 | JWT forgery | auth.l12 | 347 | 3 | 300/350/400 | ✅ |
| 8 | Insecure deserialization | sessions.g12 | 502 | 3 | 300/350/400 | ☐ |
| 9 | SSRF | loans.g6 | 918 | 3 | 300/350/400 | ☐ |
| 10 | Broken function-level authz | admin.l4 | 285 | 3 | 100/150/200 | ☐ |
| 11 | Stored XSS (beneficiary name) | beneficiary.g3 | 79 | 0 | 200/250/300 | ☐ |

> All sit at L3 (secure) today **except beneficiary.g3** (L0). So for most the work is
> building out the L0/L1/L2 rungs and verifying them; the L3 fix likely already exists.
> The L1/L2 designs below are proposals — reconcile with the actual source when
> implementing each (L0 must match real behaviour; L3 must be the genuine fix).

---

## 1. auth.l25 — Session puzzling (CWE-841)  ◐ L0+L1 IMPLEMENTED & VERIFIED
**Goal:** reach another user's account by reusing recovery-flow state that is not
bound to the principal it was earned for.

**Flow (all levels):** the forgot-password journey is a 4-stage machine, one GET-able
endpoint per stage so each opens in its own browser tab:
`/forgot-password` (Customer ID → `recovery_uid`) → `/forgot-password/otp`
(OTP → `recovery_stage=1`) → `/forgot-password/questions` (5 answers →
`recovery_stage=2`) → `/forgot-password/reset` (set new password for `recovery_uid`).
Progress is held in `recovery_uid` + `recovery_stage`, SEPARATE from the auth session
(`$_SESSION['user']`). OTP reuses the login OTP machinery (purpose `reset`); questions
+ answers are seeded for every user. Code: `src/modules/auth/forgot.php`.

Each level shows exactly **one** behaviour: L0 = overload only; L1/L2 = swap only;
L3 = secure. Progress is tracked by `recovery_stage` **and** `recovery_stage_uid` (which
user *earned* the stage). The fix is at the **reset gate**: L0/L3 require
`recovery_stage_uid === recovery_uid` (owner-binding), L1/L2 do not (`$swapVuln = level in
{1,2}`). `recovery_uid` is always set, so the reset journey works at every level; the flag
is delivered on the page that proves the takeover (L0 → dashboard; L1/L2 → reset page).

The code viewer shows the story as three snippets: the swap (step 1 overwrites
`recovery_uid`), the session state (`recovery_stage` + `recovery_stage_uid`), and the
reset gate (vuln trusts the stage; fix adds `&& $stageUid === $recoveryUid`).

- **L0** — Step 1 overloads the AUTH key (`$_SESSION['user']`): entering a victim's
  Customer ID authenticates you as them (browse `/dashboard`, flag drops there). The swap
  is **blocked** by the owner-binding gate. **Verified live** (swap → `/reset` bounces).
- **L1** — The reset gate trusts `recovery_stage` alone, so: complete the flow once for
  your OWN account (stage → 2, `recovery_stage_uid` = you), then open Step 1 in a new tab,
  submit a victim's Customer ID (`recovery_uid` swaps, stage/owner kept), and load
  `/forgot-password/reset` to set the VICTIM's password — no OTP, no questions. Flag on
  the reset page, keyed to your own account. **Verified live** (BS100238 → BS100234).
- **L2** — *(to design)* currently mirrors L1 (gated on `$swapVuln`). Intended distinct
  technique: the gate DOES check an owner, but a per-gate verified flag isn't cleared so
  the OTP/questions steps wave you through on stale state. Not yet differentiated.
- **L3** — The reset gate binds the stage to the user that earned it
  (`recovery_stage_uid === recovery_uid`), at both the GET (form) and POST (submit)
  checks, so a swapped-in target is refused. No swap possible.

Idle timeout: the whole recovery journey expires after **10 minutes** of inactivity
(`recovery_ts`), then bounces to the start.

Deferred: l25 **flag** delivery/acceptance (checklist area I); L2 differentiation;
code-viewer/hints/matrix updates.

## 2. statements.g2 — SQL injection, data extraction (CWE-89)
**Goal:** extract your per-user flag from `ctf_flags` via the Statements **search box**
only (the date range is always bound). Flag is per-user HMAC → no cross-user sharing;
no modal — the participant reads it out of the DB and submits it.
- **L0** — Raw term concatenated into `remarks LIKE '%…%'`; raw SQL error shown →
  in-band UNION (`%' UNION SELECT username,flag,3,4,5,6,7 FROM ctf_flags-- ` — flag
  in the Remarks cell; the Date cell truncates to 10 chars) **or**
  error-based (`extractvalue(...)`). 7 columns.
- **L1** — Leaky single-pass blacklist strips `union`/`select` + comment tokens, errors
  hidden → bypass by **nesting** keywords (`UNIunionON`, `SELselectECT`) and **balancing
  the quote** (`… WHERE '%'='`) since comments are gone.
- **L2** — `union` stripped **repeatedly** + errors hidden → in-band and error channels
  closed; `AND`/sub-selects/quotes still reach the query → **boolean-blind** only
  (`house%' AND SUBSTRING((SELECT flag…),1,1)='B'-- `, walk each position).
- **L3** — Search term bound as a parameter (`remarks LIKE :q`); dates always bound.

## 3. statements.g4 — Reflected XSS (CWE-79)
**Goal:** execute JS in the victim's browser via a reflected parameter → steal flag.
- **L0** — Raw reflection of a query param into HTML; `<script>` fires.
- **L1** — Non-recursive strip of `<script>` → bypass `<scr<script>ipt>` or
  `<img onerror=...>` event handlers.
- **L2** — `<>` HTML-encoded, but value reflected into an **attribute / JS string**
  context unescaped → break out with `"` or `';`.
- **L3** — Context-aware output encoding + CSP.

## 4. statements.g9 — Command injection (CWE-78)
**Goal:** run OS commands via a feature that shells out (export/PDF/filename).
- **L0** — Input concatenated into a shell command; `; id` works.
- **L1** — Blacklist `;`,`&&` → bypass with newline, `|`, `$( )`, backticks.
- **L2** — `escapeshellarg` on the main arg but a second unquoted param / wrapper flag
  remains → **argument injection** (e.g. inject an `-o`/`--output` option).
- **L3** — No shell: native library or strict allowlist + escaping everywhere.

## 5. transfer.l1 — IDOR, transfer from another account (CWE-639)
**Goal:** initiate a transfer that debits an account you don't own.
- **L0** — `from_account` taken from the request and used directly; no ownership check.
- **L1** — Ownership "checked" via a forgeable client field (hidden `owner_id`), **or**
  checked only on *initiate* and not on *confirm/execute* → bypass at the later step.
- **L2** — Server checks ownership on the main path but one code path (receipt/execute)
  references the account without binding it to the session user → narrow bypass.
- **L3** — Every account-referencing action verifies ownership server-side.

## 6. loans.g10 — XXE (CWE-611)
**Goal:** read local files / OOB via an XML document upload (loan docs).
- **L0** — Parser resolves external entities + DTD; `<!ENTITY xxe SYSTEM "file:///etc/passwd">` reflected.
- **L1** — Naive `DOCTYPE` string blacklist → bypass via case/encoding, or `SYSTEM`
  blocked but `PUBLIC` allowed.
- **L2** — External *general* entities blocked, but parameter entities / external DTD
  still load → **blind/OOB** exfiltration.
- **L3** — Disable entity loader + `LIBXML_NONET`, no DTD (or drop XML).

## 7. auth.l12 — JWT forgery (CWE-347)  ✅ IMPLEMENTED
**Goal:** forge a token for `role:admin` without the key. Weakness lives entirely in the
**verifier** (`Auth::verifyJWT`); the server always issues a proper HS256 signature.
- **L0** — **Signature not verified**: only checks a signature segment is *present*;
  tamper the payload, keep any signature string → accepted.
- **L1** — **alg:none**: unsigned tokens accepted when `alg` is none (HMAC checked otherwise).
- **L2** — **case-sensitive alg check**: only exact-lowercase `none` is rejected, so
  `None`/`NONE` slip through to the unsigned-accept path.
- **L3** — **Secure**: HS256 pinned, HMAC verified (constant-time), `exp` validated,
  everything else rejected.

## 8. sessions.g12 — Insecure deserialization (CWE-502)
**Goal:** object injection / tamper via `unserialize` of attacker-controlled data.
- **L0** — `unserialize($_COOKIE['prefs'])` (or pref blob) directly → object injection.
- **L1** — Naive class-name blacklist / `O:` strip → bypass with encoding/whitespace
  or an allowed gadget.
- **L2** — Integrity "protected" by a weak/disclosed HMAC or a magic-prefix check only
  → forge a valid-looking blob.
- **L3** — No native unserialize of untrusted input; JSON + schema, signed with a strong key.

## 9. loans.g6 — SSRF (CWE-918)
**Goal:** make the server fetch an attacker URL to reach internal services/metadata.
- **L0** — Server fetches any user URL; `http://169.254.169.254/…` / `http://localhost/admin`.
- **L1** — String blacklist (`localhost`,`127.0.0.1`) → bypass with `127.1`, decimal/hex
  IP, `[::1]`, `http://localhost.`, etc.
- **L2** — Resolves host & blocks private ranges on first resolution, but **follows
  redirects / TOCTOU** → redirect or rebind to internal.
- **L3** — Destination allowlist, block private/link-local post-resolution, no redirects.

## 10. admin.l4 — Broken function-level authorization (CWE-285)
**Goal:** perform an admin-only action as a normal user (forced browsing).
- **L0** — Admin endpoint performs no role check; any authed user can call it.
- **L1** — UI hides admin links but the endpoint is open (obscurity), **or** it trusts a
  client-controlled `is_admin` cookie/field (the existing bait) → flip it.
- **L2** — Role checked on most admin actions but one is unprotected (or checked on GET
  not POST) → find the gap.
- **L3** — Centralized server-side authorization on every admin action, deny by default.

## 11. beneficiary.g3 — Stored XSS, beneficiary name (CWE-79)  *(currently L0)*
**Goal:** store JS in a beneficiary name that fires when viewed → steal session/flag.
- **L0** — Name stored and rendered raw; `<script>`/`<img onerror>` fires on view.
- **L1** — Non-recursive `<script>` strip on input → nested/event-handler bypass.
- **L2** — Encoded on input but rendered unescaped in a *different* sink (admin view /
  attribute / export) → stored-to-attribute breakout.
- **L3** — Store raw, output-encode at every sink + CSP.

---

## Verification tracking → database (decided)

Verification status moves **from the file `data/verify_progress.json` into the database**,
so it travels with every deployment via the SQL dump. (Supersedes the earlier
file-based choice.)

Planned schema (added to `sql/01-schema.sql`, seeded via a new `sql/05-verification-status.sql`):

```sql
CREATE TABLE verification_status (
  module      VARCHAR(32)  NOT NULL,
  vuln        VARCHAR(16)  NOT NULL,
  level       TINYINT      NOT NULL,          -- 0..3
  verified    TINYINT(1)   NOT NULL DEFAULT 0,
  note        VARCHAR(255) NULL,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (module, vuln, level)
);
```

- `src-superadmin/pages/verify.php` is reworked to read/write this table instead of the
  JSON file (one-time import of the existing JSON on migration).
- The seed file records the **verified baseline for the build** — currently
  **auth.g1 (all levels)** and **auth.l11 (all levels)** verified; this batch starts
  unverified.
- Each time we finish a vuln, we tick it in the verify page **and** regenerate/append the
  seed so the dump reflects the build's verified set.
- The 11 vulns above are **highlighted** in the verify page as the active queue
  (a "planned / in this batch" marker), so progress is visible at a glance.

## Working order
Pick from the table one at a time. Suggested sequencing by shared machinery (lets us
reuse scaffolding): XSS pair (g4 reflected, g3 stored) → injection pair (g2 SQLi,
g9 cmdi) → auth/session (l25, l12) → object/io (g12, g10, g6) → authz/IDOR (l4, transfer.l1).
Order is not fixed — you choose the next one.
