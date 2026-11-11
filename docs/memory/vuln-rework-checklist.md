---
name: vuln-rework-checklist
description: "The standard checklist to apply and verify when reworking any vulnerability's implementation"
metadata:
  node_type: memory
  type: project
  originSessionId: a8143090-4202-4914-b6d3-00bf263a4366
  modified: 2026-11-07T07:33:26.004Z
---

When reworking any vulnerability (levels, viewer, hints, scoring, docs), follow and **verify** the full checklist in `docs/VULN_REWORK_CHECKLIST.md`. It was distilled from the `auth.g1` SQLi rework and is the per-vuln standard the user asked to reuse.

Covers: graduated L0–L3 implementation, `@vuln`/`@fix` markers with `(module.key)` + `[Lx]` level tags, **live HTTP verification** of each level's bypass (not just reading code), per-level scoring, the participant code viewer (level-filtered, marked-blocks-only, fixed code shown, brief note, readable), level-aware payload-building hints gated by `ctf_active`, and `VULN_MATRIX.html` rows marked ✓ verified.

Keep the status table at the bottom of that file up to date as each vuln is done. Related: [[code-viewer-no-spoilers]], [[level-aware-hints]], [[g1-sqli-login-levels]], [[vuln-matrix-verified-per-level]].
