---
name: verification-storage-in-db
description: "Vuln verification status moved from file to a DB table, seeded in the SQL dump"
metadata:
  node_type: memory
  type: project
  originSessionId: a8143090-4202-4914-b6d3-00bf263a4366
  modified: 2026-11-07T14:14:47.510Z
---

Verification tracking (which vuln×level is verified) is being moved **from
`data/verify_progress.json` into a `verification_status` DB table**, seeded via the SQL
dump (`sql/`), so the verified baseline travels with every deployment. Decided
2026-11-07; this **supersedes** the earlier "do not use database — use a file" choice for
the verify page (portability won).

`src-superadmin/pages/verify.php` will read/write the table instead of the JSON file.
Baseline verified in current build: auth.g1 (all levels), auth.l11 (all levels).

The next rework batch (11 vulns) and their planned L0–L3 ladders are in
`docs/VULN_LEVEL_PLAN.md`; plan-first, then implement one at a time. See
[[vuln-rework-checklist]].
