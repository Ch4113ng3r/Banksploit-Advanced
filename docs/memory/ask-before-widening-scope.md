---
name: ask-before-widening-scope
description: "On BankSploit, ask before changing lab/vulnerability behaviour beyond what was literally requested"
metadata:
  node_type: memory
  type: feedback
  originSessionId: a8143090-4202-4914-b6d3-00bf263a4366
  modified: 2026-11-05T11:03:32.143Z
---

Do not change which labs are open, their levels, or other app behaviour beyond the literal request without asking first. If a requested fix can't fully hold without a wider change, make the requested part, report the gap, and ask.

**Why:** Ankit asked me to fix the public flag secret. I also closed six unscored labs (set them to level 3) so the fix would hold, and only mentioned it afterwards. They had me revert it and said not to make such changes without asking.

**How to apply:** The deliberate vulnerabilities are the product; Ankit decides what is open. Treat an "incomplete fix" as a finding to raise, not licence to expand scope.
