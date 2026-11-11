# Per-Vulnerability Rework Checklist

Everything we corrected while reworking **auth.g1 (SQL injection — login bypass)**, turned into a standard to apply and **verify** for every other vulnerability as we rework it.

Reference implementation: `auth.g1` — code in `src/modules/auth/login_vuln.php` / `login_secure.php`, hints in `src/config/hints_config.json`, matrix rows in `VULN_MATRIX.html`.

Copy the checklist below per vulnerability and tick each item (the Verification page at `/verify` in the instructor portal tracks the level×behaviour grid separately).

---

## A. Graduated implementation (the code)
- [ ] **Four distinct levels**: L0 fully vulnerable → L1 weak mitigation (still bypassable) → L2 harder/partial (still bypassable, different technique) → L3 secure (no bypass).
- [ ] Each vulnerable level is **actually exploitable**; L3 is **actually fixed** (proper control: parameterization / validation / auth check / encoding, as appropriate).
- [ ] **Legitimate functionality still works** at every level (the feature isn't broken by the vuln logic).
- [ ] Levels gate cleanly off the configured level (no dependency on an unrelated vuln's level unless intended).

## B. Markers (drive the code viewer)
- [ ] Every vulnerable region wrapped in `// @vuln-start … // @vuln-end`; secure region in `// @fix-start … // @fix-end`.
- [ ] Each marker label ends with the `(module.key)` suffix, e.g. `(auth.g1)`.
- [ ] Each vulnerable block carries a **level tag** — `[L0-1]`, `[L1]`, `[L2]` — so the viewer shows only the selected level's code.
- [ ] **No inline comments that spell out the payload/bypass** inside `@vuln`/`@fix` blocks (keep neutral comments describing what the code does).

## C. Live verification (not just reading code)
- [ ] Flip the level 0→1→2→3 and **drive the real app over HTTP**: confirm each level's intended bypass works, and that wrong/previous-level payloads fail as expected.
- [ ] L3: confirm the exploit is refused.
- [ ] Legitimate path (e.g. correct credentials / valid input) still succeeds at each level.
- [ ] Flag is still awarded on a successful exploit (where flags apply).

## D. Scoring
- [ ] `points` set per level in `vuln_config.json` (increasing with level; default 50-pt steps), L3 = 0 (secure, no flag).

## E. Code viewer (participant-facing, `/training/code`)
- [ ] Shows **only the selected level's** vulnerable code (level-tagged blocks).
- [ ] Shows **only marked blocks**, not the whole file (gaps elided with `⋯`).
- [ ] **Fixed/secure code is always shown** alongside the vulnerable code.
- [ ] Panel above the code is a **brief note only** — no attack-scenario walkthrough, no verbose description, no marked-blocks list.
- [ ] Code is **readable** (light text on the dark panes, both themes).

## F. Hints (participant-facing)
- [ ] **Level-aware** `levels` map in `hints_config.json` (keys `"0"`,`"1"`,`"2"`), with `subtitle` + ordered `steps`: concept → technique → **payload skeleton**.
- [ ] Final step is a **skeleton with a placeholder** (e.g. `<yourID>`), behind the **"Reveal payload"** toggle — not a copy-paste answer.
- [ ] Hints only appear when the vuln is **selected for the CTF** (`ctf_active`) and still vulnerable (L3 auto-hides); off in Training by default.

## G. Documentation
- [ ] `VULN_MATRIX.html` rows updated for **all four levels** with the verified "how to bypass," each marked **✓ verified**.
- [ ] Goal/phrasing matches intent (e.g. g1 = "log in as **your own** account", not admin).

## H. Access & context (where relevant)
- [ ] Viewer reachable in the right context — e.g. pre-login (auth) vulns are studiable from the login page via the focused, scoped viewer.

## I. Flag delivery & acceptance (where flags apply)
The whole point of an exploit in CTF is the flag — verify the loop end to end:
- [ ] **A successful exploit delivers the flag** to the player wherever the vuln is
  exploited — via the **flag modal** (`displayFlag`/`showFlagModal`), the `X-CTF-Flag`
  response header, or an in-page/embedded flag — at **every vulnerable level (L0–L2)**.
- [ ] Flag delivery is **gated on `flag_delivery`** (CTF/flags on) — nothing leaks when
  flags are off. (Don't rely on obscurity: gate the embedded flag string too, not just
  the modal.)
- [ ] The delivered flag is the **correct per-user flag** for the identity that will
  submit it — i.e. `Flags::generate(vulnKey, submitterId)`. Watch impersonation vulns
  (e.g. JWT/IDOR) where the exploited identity ≠ the player's own account, so the flag
  shown is keyed to the *wrong* id and won't validate.
- [ ] **The server accepts the flag on submission** (`/api/v1/training/submit-flag`):
  it validates against `getCurrentUser()['id']` **and requires the vuln to be
  `ctf_active`** — so confirm submission succeeds with the vuln selected into the CTF.
- [ ] **Flag Submission UI is in sync with the selected CTF.** Only vulnerabilities
  currently selected (`ctf_active`) appear / are submittable on the Flag Submission page —
  a deselected or secure (L3) vuln must **not** show up or score. Each shown vuln's
  **points match the live level** (`Flags::points(vulnKey, currentLevel)`), so a
  submission awards exactly the points for the configuration at capture time. Verify the
  list and the per-row points update when the selection or a level changes.
- [ ] L3 (secure) delivers **no** flag.

---

## Status log
| Vuln | A | B | C | D | E | F | G | I | Notes |
|------|---|---|---|---|---|---|---|---|-------|
| auth.g1 — SQLi login bypass | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Reference. L0 error-based · L1 blacklist+OR · L2 blind+brackets · L3 secure. |
| auth.l11 — OTP in response | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | L0 plaintext (`_otp`+`_flag` in JS body) · L1 predictable `parseInt(sha256(_seed·_uid)[:8],16)%10000` (sealed `_flag_enc`) · L2 brute-force `sha256(otp)` — but delivered in **response headers** `X-Otp-Check`/`X-Flag-Enc`, body carries only breadcrumbs · L3 secure. **CTF-only** `_algo`/`_flag_algo` formula breadcrumbs. Console exploits in matrix; level-aware hints. Verified live over HTTP. |
| auth.l12 — JWT forgery | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | L0 sig-present-not-verified · L1 alg:none · L2 case-sensitive alg check (None/NONE bypass) · L3 HS256 verified + exp. Verifier-only vuln; server always signs. Verified end-to-end via getCurrentUser(). **I verified live:** forge `sub`=own-id + role:admin → `/admin/vault` 200 + flag modal, submission accepted (+300). Caveat: forging `sub`=ADMIN001 keys the flag to the admin's id (won't validate under your own account unless the forged cookie is still set at submit). |
| auth.l25 — Session puzzling (forgot-password) | ✅ | ✅ | ◐ | ✅ | ✅ | ✅ | ✅ | ◐ | Flow reworked to 4 endpoints (Customer ID→OTP→questions→reset), `recovery_uid`+`recovery_stage` (+`recovery_ts` 10-min idle timeout). **One behaviour per level:** L0 auth-key overload **only** (swap blocked — **verified live**) · L1 stage-carryover swap (**verified live**) · L2 mirrors L1 (distinct technique TBD) · L3 secure. Fix is at the **reset gate** (owner-binding `recovery_stage_uid === recovery_uid`, at GET+POST); `$swapVuln = level∈{1,2}` controls the binding. Code viewer shows 3 snippets (swap · session state · gate vuln-vs-fix). Normal reset journey works at every level (**verified end-to-end at L0**: reset + re-login). Code viewer→`forgot.php`, level-aware hints, matrix rows done. **I:** flag on dashboard (L0, keyed to taken-over victim) / on reset page (L1/L2, keyed to the stage-earner); Flag-Submission page now in sync (shows only `ctf_active`+vulnerable, per-level points — **verified**). Remaining: full submit-loop run (needs `flag_delivery`+`ctf_active` on l25) and L1/L3 live re-check at those levels. |
| statements.g2 — SQLi data extraction | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Reworked to **search box only** (`remarks LIKE`); dates always bound. `g2_term()` ladder: L0 raw (+raw error → in-band UNION **or** error-based) · L1 leaky single-pass blacklist, errors hidden → nest keywords + balance quote · L2 `union` stripped repeatedly + errors hidden → **boolean-blind** only · L3 `remarks LIKE :q`. Flag = per-user HMAC in `ctf_flags` (anti-share), reachable only via SQLi → **row-scan proof gate removed**; `Flags::validate` alone accepts (works for boolean where nothing lands in a row). **No modal** — participant reads flag from DB and submits. Viewer: `g2_term()` (core/helpers.php) added as vuln-pane extra beside the sink, level-tagged markers. Hints + matrix + vuln_config text per-level with payloads. **Verified LIVE against MySQL (12/12)** — flipped g2 L0→L3 in-process, ran the matrix/hint payloads as user BS100234: L0 UNION + extractvalue error-based leak the flag; L1 naive payload blocked (generic error) and nesting+quote-balance bypass dumps it; L2 in-band UNION dead + full boolean-blind char-by-char reconstruction matched the flag exactly; L3 inert. Submission: own flag validates, other user's rejected, ctf_active gate open, points 300/350/400/0. Config restored to original (level 0, ctf_active false). |
