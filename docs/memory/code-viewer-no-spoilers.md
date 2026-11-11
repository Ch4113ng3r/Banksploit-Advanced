---
name: code-viewer-no-spoilers
description: "When reworking a vulnerability, keep the training code viewer from giving away the exploit"
metadata:
  node_type: memory
  type: feedback
  originSessionId: a8143090-4202-4914-b6d3-00bf263a4366
  modified: 2026-11-07T07:04:48.283Z
---

The participant-facing code viewer (`src/training/code_compare.php`, route `/training/code`) must show the vulnerable code for the **selected level only** plus the secure version — and must NOT hand over the exploit.

**Why:** participants should work out the attack themselves; the viewer leaking the payload (or every level's implementation) defeats the training.

**How to apply — for every vulnerability as we rework it:**
- Tag each `@vuln-start` block with its level, e.g. `[L0-1]`, `[L1]`, `[L2]`, so the viewer filters to the active level (`tr_marker_level_matches()` in `src/training/_shared.php`). Untagged blocks show at every level.
- Remove inline comments inside `@vuln`/`@fix` blocks that name the exact payload or bypass (e.g. don't write `// intended bypass: BS100234' OR '1'='1`). Keep neutral comments that describe what the code does.
- The viewer panel is already trimmed to a brief, non-spoiler note (no `description`/`attack_scenario`/marked-blocks list) — structural, applies to all vulns.

Done + verified for the SQLi ones: [[g1-sqli-login-levels]] (auth.g1) and statements.g2. Do the same for each remaining vuln when we work on it. Keep the matrix ([[vuln-matrix-verified-per-level]]) rows marked ✓ verified as we confirm each.
