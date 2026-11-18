---
name: work-in-banksploit-advanced
description: Use /Users/ankit/Documents/GitHub/Banksploit-Advanced as the working codebase; run it from there when needed
metadata:
  type: feedback
---

From 2026-11-12 the user wants all work done in `/Users/ankit/Documents/GitHub/Banksploit-Advanced` (the GitHub-backed repo), not the older `~/Documents/banksploit-ctf` copy. Run the app from this directory when verification needs it.

**Why:** the user wants the code reachable via GitHub (private repo) and a single source of truth; the two directories were identical when merged.

**How to apply:** edit and test only in Banksploit-Advanced. Note the app already running on :9443 may still serve the old banksploit-ctf copy. Don't kill it without asking. Related: [[vuln-rework-checklist]].
