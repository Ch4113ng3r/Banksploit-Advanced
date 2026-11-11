---
name: level-aware-hints
description: Participant hints must be level-specific and progressively guide building the correct payload
metadata:
  node_type: memory
  type: feedback
  originSessionId: a8143090-4202-4914-b6d3-00bf263a4366
  modified: 2026-11-07T09:33:44.533Z
---

For every vulnerability, the participant hint (login-page / side banner via `ctf_hint_banner()` in `src/core/helpers.php`, data in `src/config/hints_config.json`) must be **based on the vuln's currently selected level** and must **help the player build the correct payload** progressively.

**Why:** a single static hint doesn't match what actually runs when the instructor changes the level, and good hints should teach the player to construct the payload themselves, not just hand it over or leave them stuck.

**Hints are optional and gated by selection (decided 2026-11-07):** `ctf_hint_banner()` shows a hint only when the vuln is **selected for the event** (`ctf_active` true) AND still vulnerable (level < 3) AND the `hints` feature is on. The `hints` feature is **off by default in ALL three presets** (CTF, Training, Pentest) — it's optional; an instructor enables it per event. Even when enabled, only CTF-selected vulns show hints. Instructors can optionally set all unselected vulns to Fixed/L3 via the "Set unselected → Fixed" button on the CTF Vulnerabilities page.

**How to apply — per vuln, as we rework it:**
- In `hints_config.json`, give the entry a `levels` map keyed by level (`"0"`,`"1"`,`"2"`) with a `subtitle` and ordered `steps` (concept → technique → payload skeleton). L3 is secure → banner auto-hides.
- Write the final step as a payload *skeleton* with a placeholder (e.g. `<yourID>`), not a copy-paste answer, so the player still fills in their own account.
- The banner tucks the last step behind a "Reveal payload" toggle (progressive). Entries without a `levels` map fall back to the flat `description`.

Done + verified for the SQLi login bypass [[g1-sqli-login-levels]] (auth.g1), L0/L1/L2. Pairs with [[code-viewer-no-spoilers]] — the viewer hides the exploit, the hints progressively reveal it.
