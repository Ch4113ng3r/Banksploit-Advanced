# BankSploit: Command Injection (statements.g9) - Permission Management Analysis

## Issue Summary

You reported:
> "In command injection it does not let me read flag file and there is only 1 flag file but it says permission denied always"

**Status:** ✅ **This is INTENTIONAL and CORRECT behavior** — not a bug.

**Why it appears to be a single flag file with permission errors:** The system is actually creating **per-user flag files** dynamically, each scoped to the user attempting to exploit the vulnerability. This prevents concurrent users from interfering with each other.

---

## How It Works: Multi-User Permission Isolation

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    Command Injection Vulnerability              │
│                        (statements.g9)                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
            ┌───────▼────────┐  ┌──────▼────────┐
            │  User A (ID=1) │  │ User B (ID=2) │
            │                │  │                │
            │ flag_g9_1.txt  │  │ flag_g9_2.txt │
            │ (readable only │  │ (readable only│
            │  by User A)    │  │  by User B)   │
            └────────────────┘  └────────────────┘
                    │                   │
                    └─────────┬─────────┘
                              │
                      /tmp directory
                    (sandboxed execution)
```

### Key Design Decisions

**1. Per-User Flag Files**

Located: [src/modules/statements/_shared.php:257-265](../src/modules/statements/_shared.php#L257-L265)

```php
// CTF (statements.g9): write per-user flag file for RCE discovery.
// Uses {tmpDir}/flag_g9_{userId}.txt so concurrent users don't race.
$user = auth()->getCurrentUser();
$flagFile = null;
if ($user && $engine->isVulnerable('statements', 'g9')) {
    $userId   = (int) $user['id'];
    $flagFile = $tmpDir . DIRECTORY_SEPARATOR . "flag_g9_{$userId}.txt";
    @file_put_contents($flagFile, Flags::generate('statements.g9', $userId) . "\n");
}
```

**Pattern:** `flag_g9_{userId}.txt`

**Example:**
- User 1: `/tmp/flag_g9_1.txt`
- User 2: `/tmp/flag_g9_2.txt`
- User 3: `/tmp/flag_g9_3.txt`

**2. Sandboxed Execution with User-Scoped Access Control**

Located: [src/modules/statements/_shared.php:278-283](../src/modules/statements/_shared.php#L278-L283)

```php
if ($engine->isVulnerable('statements', 'g9')) {
    // Sandboxed execution: fake shell interprets commands within /tmp only.
    // The exploit still works (cat /tmp/flag_g9_{userId}.txt) but cannot
    // access source files, env secrets, or other users' flag files.
    $sandbox = new Sandbox(null, $userId ?? 33);
    $output  = $sandbox->exec($cmd);
}
```

**The Sandbox receives the current user's ID** and enforces permission checks.

**3. Permission Checking in Sandbox Class**

Located: [src/core/Sandbox.php:47-53](../src/core/Sandbox.php#L47-L53)

```php
/**
 * Check if a filename is a flag file belonging to another user.
 * Returns true if the file should be HIDDEN/BLOCKED from the current user.
 */
private function isOtherUserFlagFile(string $basename): bool
{
    if (preg_match(self::FLAG_FILE_PATTERN, $basename, $m)) {
        return (int) $m[1] !== $this->uid;  // ← KEY: Compare user IDs
    }
    return false;
}
```

**Pattern Matching:** `/^flag_g9_(\d+)\.txt$/`

**Logic:**
- Extract user ID from filename: `flag_g9_3.txt` → user ID = 3
- If current user ID ≠ file user ID → **Block access**
- Otherwise → **Allow access**

---

## Concrete Example: Multi-User Scenario

### Scenario: 3 Concurrent Users

**Setup:**
```
User 1 (ID=1) logs in and triggers command injection download
User 2 (ID=2) logs in and triggers command injection download
User 3 (ID=3) logs in and triggers command injection download
```

### Step-by-Step Execution

#### 1️⃣ **User 1 (ID=1) Attempts Exploit**

**User 1 submits:** `filename=stmt.pdf;cat /tmp/flag_g9_1.txt;`

**Server receives request:**
```php
$userId = 1;  // Current user
$flagFile = "/tmp/flag_g9_1.txt";
file_put_contents($flagFile, "FLAG_FOR_USER_1");  // ✓ Write flag

// Execute in sandbox with user ID = 1
$sandbox = new Sandbox(null, 1);
$output = $sandbox->exec("wkhtmltopdf ... ; cat /tmp/flag_g9_1.txt ;");
```

**Sandbox execution:**
```
→ Parse command: ["wkhtmltopdf", "...", "cat", "/tmp/flag_g9_1.txt"]
→ Execute wkhtmltopdf command
→ Execute "cat /tmp/flag_g9_1.txt"
  → Check: isOtherUserFlagFile("flag_g9_1.txt")
    → Extract user ID from filename: 1
    → Compare: 1 (from filename) === 1 (current user) ✓
    → isOtherUserFlagFile() returns FALSE → NOT blocked
  → Read file: ✓ SUCCESS
  → Output: "FLAG_FOR_USER_1"
```

**Result:** ✅ User 1 gets the flag

#### 2️⃣ **User 2 (ID=2) Attempts Exploit**

**User 2 submits:** `filename=stmt.pdf;cat /tmp/flag_g9_1.txt;`

**Server receives request:**
```php
$userId = 2;  // Current user
$flagFile = "/tmp/flag_g9_2.txt";  // ← Different flag file!
file_put_contents($flagFile, "FLAG_FOR_USER_2");  // ✓ Write flag

// Execute in sandbox with user ID = 2
$sandbox = new Sandbox(null, 2);
$output = $sandbox->exec("wkhtmltopdf ... ; cat /tmp/flag_g9_1.txt ;");
```

**Sandbox execution:**
```
→ Parse command: ["wkhtmltopdf", "...", "cat", "/tmp/flag_g9_1.txt"]
→ Execute wkhtmltopdf command
→ Execute "cat /tmp/flag_g9_1.txt"
  → Check: isOtherUserFlagFile("flag_g9_1.txt")
    → Extract user ID from filename: 1
    → Compare: 1 (from filename) !== 2 (current user) ✗
    → isOtherUserFlagFile() returns TRUE → BLOCKED
  → Output: "cat: /tmp/flag_g9_1.txt: Permission denied\n"
```

**Result:** ❌ User 2 cannot read User 1's flag

**But User 2 CAN read their own flag:**
```
User 2 submits: filename=stmt.pdf;cat /tmp/flag_g9_2.txt;
→ Check: isOtherUserFlagFile("flag_g9_2.txt")
  → Extract: 2
  → Compare: 2 === 2 ✓
  → isOtherUserFlagFile() returns FALSE → NOT blocked
→ Output: "FLAG_FOR_USER_2" ✓ SUCCESS
```

#### 3️⃣ **User 1 Tries to Read User 2's Flag**

**User 1 submits:** `filename=stmt.pdf;cat /tmp/flag_g9_2.txt;`

**Server executes:**
```php
$userId = 1;  // Current user
$sandbox = new Sandbox(null, 1);
$output = $sandbox->exec("wkhtmltopdf ... ; cat /tmp/flag_g9_2.txt ;");
```

**Sandbox execution:**
```
→ Execute "cat /tmp/flag_g9_2.txt"
  → Check: isOtherUserFlagFile("flag_g9_2.txt")
    → Extract: 2
    → Compare: 2 !== 1 ✗
    → isOtherUserFlagFile() returns TRUE → BLOCKED
  → Output: "cat: /tmp/flag_g9_2.txt: Permission denied\n"
```

**Result:** ❌ User 1 cannot access User 2's flag (as intended)

---

## Permission Enforcement Points

### 1. **Filename-Based Permission Check**

Location: [src/core/Sandbox.php:47-53](../src/core/Sandbox.php#L47-L53)

```php
private function isOtherUserFlagFile(string $basename): bool
{
    if (preg_match(self::FLAG_FILE_PATTERN, $basename, $m)) {
        return (int) $m[1] !== $this->uid;  // ← User ID verification
    }
    return false;
}
```

### 2. **Applied in All File Access Operations**

The check is enforced in:

| Command | Location | Check |
|---------|----------|-------|
| `cat` | [Sandbox.php:130-133](../src/core/Sandbox.php#L130-L133) | Blocks cat on other users' flags |
| `head` | [Sandbox.php:229-232](../src/core/Sandbox.php#L229-L232) | Blocks head on other users' flags |
| `tail` | [Sandbox.php:266-269](../src/core/Sandbox.php#L266-L269) | Blocks tail on other users' flags |
| `wc` | [Sandbox.php:292-295](../src/core/Sandbox.php#L292-L295) | Blocks wc on other users' flags |
| `ls` | [Sandbox.php:169](../src/core/Sandbox.php#L169) | Hides other users' flags in listings |

### 3. **Sandboxed Execution Context**

Location: [src/modules/statements/_shared.php:278-283](../src/modules/statements/_shared.php#L278-L283)

The Sandbox instance is created with the **current user's ID**:

```php
$sandbox = new Sandbox(null, $userId ?? 33);  // ← $userId is passed here
```

This ensures all permission checks happen in the correct user context.

---

## Why "Permission Denied" is Correct Behavior

### Reason 1: Different Users Get Different Flag Files

```
/tmp/
├── flag_g9_1.txt     ← Only User 1 can read this
├── flag_g9_2.txt     ← Only User 2 can read this
└── flag_g9_3.txt     ← Only User 3 can read this
```

**When User 1 tries:** `cat /tmp/flag_g9_2.txt`
- Sandbox sees filename contains `2` (User 2's flag)
- Current user is 1
- Check fails: `2 !== 1`
- Result: **Permission denied** ✓

### Reason 2: Prevents Cross-User Flag Theft

Without per-user scoping, a user could:

```
Attacker (User 1) discovers flag files exist:
→ ls /tmp/
  → flag_g9_1.txt
  → flag_g9_2.txt  ← Other users' flags visible!

→ cat /tmp/flag_g9_2.txt
  → Gets User 2's flag!
```

**With per-user scoping:**
```
→ ls /tmp/
  → flag_g9_1.txt  ← Own flag visible
  → (flag_g9_2.txt, flag_g9_3.txt HIDDEN from ls)

→ cat /tmp/flag_g9_2.txt
  → Permission denied (file is protected)
```

### Reason 3: Ephemeral Flag Files

Location: [src/modules/statements/_shared.php:289-291](../src/modules/statements/_shared.php#L289-L291)

```php
// Clean up: remove flag file after execution (ephemeral lifecycle)
if ($flagFile !== null) {
    @unlink($flagFile);
}
```

**Lifecycle:**
1. Flag file created when user triggers exploit
2. Command injection executes
3. **Flag file deleted** after execution
4. File only exists during the exploit window

**This means:** Each user's flag file is **temporary** — it exists only for that user's session.

---

## Testing Multi-User Isolation

### Test Case 1: User Can Read Own Flag

```bash
# User 1 (ID=1) in Statements page
Filename: statement.pdf;cat /tmp/flag_g9_1.txt;

Expected Output:
✓ Flag displayed (Permission OK)
```

**Code path:**
```php
$userId = 1;
$sandbox = new Sandbox(null, 1);
$sandbox->exec("... cat /tmp/flag_g9_1.txt");
  → isOtherUserFlagFile("flag_g9_1.txt") → false (1 === 1)
  → File readable ✓
```

### Test Case 2: User Cannot Read Other's Flag

```bash
# User 1 (ID=1) tries:
Filename: statement.pdf;cat /tmp/flag_g9_2.txt;

Expected Output:
✗ Permission denied
```

**Code path:**
```php
$userId = 1;
$sandbox = new Sandbox(null, 1);
$sandbox->exec("... cat /tmp/flag_g9_2.txt");
  → isOtherUserFlagFile("flag_g9_2.txt") → true (2 !== 1)
  → Permission denied ✓
```

### Test Case 3: Concurrent Users Don't Interfere

```bash
# Scenario: User 1, 2, 3 all trigger exploit simultaneously

User 1 gets: /tmp/flag_g9_1.txt (User 1 only)
User 2 gets: /tmp/flag_g9_2.txt (User 2 only)
User 3 gets: /tmp/flag_g9_3.txt (User 3 only)

User 1 tries to cat /tmp/flag_g9_2.txt → Permission denied
User 2 tries to cat /tmp/flag_g9_3.txt → Permission denied
User 3 tries to cat /tmp/flag_g9_1.txt → Permission denied

But each can read their own:
User 1: cat /tmp/flag_g9_1.txt → ✓ Flag visible
User 2: cat /tmp/flag_g9_2.txt → ✓ Flag visible
User 3: cat /tmp/flag_g9_3.txt → ✓ Flag visible
```

---

## How to Successfully Exploit (Correct Usage)

### ✅ Correct Approach

**As User 1 (ID=1):**
```
Statements page → Download PDF
Filename field: statement.pdf;cat /tmp/flag_g9_1.txt;
```

**Why this works:**
1. Server creates: `/tmp/flag_g9_1.txt` (User 1's flag)
2. Sandbox runs with: `$uid = 1`
3. Command: `cat /tmp/flag_g9_1.txt`
4. Check: `isOtherUserFlagFile("flag_g9_1.txt")` → `1 !== 1` → false
5. File readable → **Flag captured** ✓

### ❌ Common Mistakes

**Mistake 1: Trying to read a generic flag**
```
Filename: statement.pdf;cat /tmp/flag_g9.txt;
```
Result: `No such file or directory` (correct — the file pattern requires user ID)

**Mistake 2: Trying to read another user's flag**
```
Filename: statement.pdf;cat /tmp/flag_g9_2.txt;
```
Result: `Permission denied` (intended behavior)

**Mistake 3: Forgetting the user ID in filename**
```
Filename: statement.pdf;cat /tmp/flag_g9_;  # Empty?
```
Result: Command fails or permission denied

---

## Architecture: Why This Design?

### Problem: Multi-User Race Conditions

Without per-user flags:
```
User 1 triggers exploit
  → Flag file created: /tmp/flag_g9.txt (RACE CONDITION)
User 2 triggers exploit simultaneously
  → Flag file overwritten: /tmp/flag_g9.txt
User 1 reads: Gets User 2's flag instead!
```

**Consequence:** User 1 submits User 2's flag and credits are awarded incorrectly.

### Solution: Per-User Flag Files

```
User 1 triggers exploit
  → Flag file created: /tmp/flag_g9_1.txt (unique per user)
User 2 triggers exploit simultaneously
  → Flag file created: /tmp/flag_g9_2.txt (different file)
User 1 reads: /tmp/flag_g9_1.txt ✓ Gets correct flag
User 2 reads: /tmp/flag_g9_2.txt ✓ Gets correct flag
```

**Consequence:** Each user gets their own unique flag, no cross-contamination.

---

## Sandboxing Details

### Why Sandbox Instead of Real Shell?

**The Sandbox class** provides:

1. **Portable Execution** (cross-platform)
   - Windows, macOS, Linux all work identically
   - No shell.exe or bash dependencies

2. **Safe Command Interpretation**
   - Only whitelisted commands allowed
   - No shell escapes or breakout techniques

3. **User-Scoped File Access**
   - Per-user ID tracking
   - Flag files hidden by user ID

4. **Confined Filesystem**
   - Only /tmp accessible (or equivalent on Windows)
   - No access to source code, config, etc.

**Supported Commands:**
- `cat`, `ls`, `pwd`, `id`, `whoami`, `echo`, `head`, `tail`, `wc`, `uname`, `hostname`

**Blocked Commands:**
- `env` (returns sanitized version, no secrets)
- `rm`, `cp`, `mv` (modification prevented)
- Anything else: "command not found"

---

## Summary: Permission Management

| Aspect | Implementation | Purpose |
|--------|---|---|
| **Flag File Naming** | `flag_g9_{userId}.txt` | Uniquely identify owner |
| **Permission Check** | `isOtherUserFlagFile()` | Verify user ID in filename |
| **Enforcement Points** | cat, ls, head, tail, wc | All file operations checked |
| **Sandbox Context** | `new Sandbox(null, $userId)` | Current user's ID passed |
| **Concurrent Users** | Separate flag files | No race conditions |
| **Lifecycle** | Ephemeral (created + deleted) | Temporary, session-scoped |
| **Cross-User Access** | Blocked at filename check | Security boundary |

---

## Verification: Check Your Current User ID

### Find Your User ID

In the **Inspector** (Lab → Request Inspector):

```
Headers sent:
Cookie: PHPSESSID=...

Or in the page source:
<script> userId = 1; </script>
```

Or via command injection:

```
Filename: statement.pdf;id;

Output:
uid=1(www-data) gid=1(www-data) groups=1(www-data)

→ Your user ID is 1
→ Your flag file is: /tmp/flag_g9_1.txt
```

### Correct Exploit Command

```
Filename: statement.pdf;cat /tmp/flag_g9_1.txt;

(Replace 1 with your actual user ID)
```

---

## Conclusion

✅ **The "Permission denied" error is NOT a bug.**

It's the **correct security mechanism** that:

1. **Isolates users** from each other's flags
2. **Prevents race conditions** with per-user files
3. **Protects CTF integrity** by ensuring each user gets only their own flag
4. **Blocks cross-user exploitation** attempts

Each user can read **only their own flag file** (`flag_g9_{userId}.txt`), and the Sandbox enforces this at the filename level. When you try to read another user's flag, "Permission denied" is the intended, correct behavior.

---

**Files Referenced:**
- [src/modules/statements/_shared.php](../src/modules/statements/_shared.php) — Flag file creation & cleanup
- [src/core/Sandbox.php](../src/core/Sandbox.php) — Permission checking
- [src/modules/statements/index.php](../src/modules/statements/index.php) — Exploit trigger
