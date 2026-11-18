---
name: g4-reflected-xss-admin-bot
description: statements.g4 reflected XSS is training/pentest-only (removed from CTF); the admin/victim bot flag path was built then reverted
metadata:
  node_type: memory
  type: project
  originSessionId: ab074060-e869-4832-94d5-5ac7b89f011b
  modified: 2026-11-12T17:17:50.428Z
---

statements.g4 (reflected XSS) is **training / pentest only** — removed from the CTF on 2026-11-12. In `src/config/vuln_config.json` it carries `"ctf_eligible": false`.

**Why:** an admin/victim **bot** flag-delivery path (Chrome headless + PHP, no Node) was built and verified end-to-end that same day, then **reverted** — the exploit was judged too unreliable to hinge a flag on. The whole bot subsystem was deleted: `src/modules/xss/*`, `bin/xss_bot.php`, the `/xss/report|status|bot|collect` routes, the `xss_bot_runs` table + `sql/05-xss-bot-runs.sql`, and the "Report to Support agent" panel in the statements page. (bin/ was then empty and removed.)

**How to apply:** `ctf_eligible=false` means a vuln is shown in the labs with its full level ladder but is never scored, flagged, or selectable for a CTF. Enforced centrally in `TrainingEngine`: `isCtfActive()` returns false for it and `setCtfActive()` refuses to enable it (so "Select all" on the CTF Vulnerabilities page can't add it; it renders as a disabled "Training only" row). Flag submission (`tr_submit_flag`) already gates on `isCtfActive`, so a g4 flag can never score.

**Level ladder (kept)** in `g4_text()`/`g4_attr()` (src/core/helpers.php), reflected into the player's own browser: L0 raw text · L1 single-pass `<script>` strip (bypass: event handler or nested `<scr<script>ipt>`) · L2 text encoded but `value="…"` attribute raw (break out `">…`) · L3 both encoded. No CSP enforced (app renders inline scripts); L3 = context-aware encoding. Hints in `hints_config.json` retargeted to the ladder (no bot/flag).

Related: [[vuln-rework-checklist]], [[work-in-banksploit-advanced]], [[verification-storage-in-db]].
