# BankSploit — Project State (start here)

Single entry point for querying project context from a phone (Claude Android app + GitHub).
Last updated: 2026-11-12. Keep this file current; it must never contain secrets.

## How to use
Ask Claude things like: "Read docs/PROJECT_STATE.md and tell me which vulns are remaining",
"Show me the vuln rework checklist", "What are my saved preferences for this project?"

## Where things are
| What | File |
|---|---|
| Per-vuln rework checklist + status log (the standard to apply/verify) | [VULN_REWORK_CHECKLIST.md](VULN_REWORK_CHECKLIST.md) |
| Planned L0–L3 ladders for the next batch of 11 vulns | [VULN_LEVEL_PLAN.md](VULN_LEVEL_PLAN.md) |
| Saved working preferences / decisions (Claude memory) | [memory/MEMORY.md](memory/MEMORY.md) |

## Vulnerability status (finalized list)
Source of truth is the status log in VULN_REWORK_CHECKLIST.md. (The table in VULN_LEVEL_PLAN.md
is out of date for auth.l25 and statements.g2.)

| Status | Vuln | Notes |
|---|---|---|
| ✅ Done | auth.g1 — SQLi login bypass | Reference implementation |
| ✅ Done | auth.l11 — OTP in response | |
| ✅ Done | auth.l12 — JWT forgery | |
| ✅ Done | statements.g2 — SQLi data extraction | Verified live vs MySQL, 12/12 |
| ◐ In progress | auth.l25 — Session puzzling | L0+L1 verified live. Open: L2 needs its own technique (currently mirrors L1); full flag-submit loop run; live re-check of L1/L3 |
| ✅ Done (training-only) | statements.g4 — Reflected XSS | Removed from the CTF (`ctf_eligible=false`): L0–L3 reflection levels kept for training/pentest, but not scored, no flag delivery, no flag submission. Admin-bot flag path retired (the exploit was too unreliable for a flag). |
| ☐ Remaining | statements.g9 — Command injection | |
| ☐ Remaining | transfer.l1 — IDOR (transfer from other account) | |
| ☐ Remaining | loans.g10 — XXE | |
| ☐ Remaining | sessions.g12 — Insecure deserialization | |
| ☐ Remaining | loans.g6 — SSRF | |
| ☐ Remaining | admin.l4 — Broken function-level authz | |
| ☐ Remaining | beneficiary.g3 — Stored XSS | |

5 done · 1 in progress · 7 not started.

## Checklist summary (per vulnerability change)
Full detail in VULN_REWORK_CHECKLIST.md.
- **A Levels:** L0 vulnerable → L1 weak fix → L2 partial fix (different technique) → L3 secure; legit use still works.
- **B Markers:** `@vuln-start/end`, `@fix-start/end`, `(module.key)` suffix, `[Lx]` level tags, no payload-spelling comments.
- **C Live verification:** flip L0→L3 and drive the real app over HTTP; L3 refuses the exploit.
- **D Scoring:** `points` per level in `vuln_config.json`; L3 = 0.
- **E Code viewer:** selected level only, marked blocks only, fixed code shown, brief note, readable.
- **F Hints:** level-aware `levels` map in `hints_config.json`; last step is a payload skeleton behind "Reveal payload"; only when `ctf_active`.
- **G Docs:** `VULN_MATRIX.html` rows for all four levels, marked ✓ verified.
- **H Access:** viewer reachable in the right context (e.g. pre-login for auth vulns).
- **I Flags:** flag delivered at every vulnerable level, gated on `flag_delivery`, correct per-user flag, accepted on submit, Flag Submission page in sync with `ctf_active` + live points, L3 gives no flag.

## Working preferences (from memory)
- Ask before changing lab behaviour or levels beyond the literal request.
- Code viewer must not spoil the exploit (level-tagged blocks, no payload comments).
- Hints must be level-specific and progressively help build the payload.
- Verification status is moving from `data/verify_progress.json` to a seeded `verification_status` DB table (migration status unchecked).
