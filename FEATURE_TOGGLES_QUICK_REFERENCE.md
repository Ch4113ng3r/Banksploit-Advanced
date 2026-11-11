# Feature Toggles — Quick Reference

## Access

**URL:** `http://localhost:8888/features`

**Who:** Instructor (port 8888) only

## One-Minute Overview

The Feature Toggles page lets you enable/disable CTF features without code changes:

| Click This | To Do This |
|-----------|-----------|
| Toggle next to `training_lab` | Hide/show Training Lab challenges |
| Toggle next to `leaderboard` | Hide/show participant rankings |
| Toggle next to `flag_submission` | Allow/prevent points from being earned |
| Toggle next to `swagger_docs` | Hide/show API documentation |
| Toggle next to `code_inspector` | Hide/show request/response logs |

**Result:** Instant. No restart needed.

## Common Event Scenarios

### Before Event Starts
```
training_lab OFF → Participants cannot access yet
leaderboard OFF → No early peeking at scores
```

### During Event (Allow Exploration)
```
All features ON
swagger_docs ON → Participants see API docs
otp_display ON → OTP shown on login (convenience)
```

### After 1 Hour (Challenge!)
```
swagger_docs OFF → Force discovery through exploration
→ Participants must read code and inspect requests
```

### After 2 Hours (Release Hints)
```
flag_submission ON (ensure it's still enabled)
💡 Announce hints available in Training Lab
```

### End of Event (Freeze Scores)
```
flag_submission OFF → No more points can be earned
leaderboard ON → Show final rankings
```

## What Each Feature Does

```
🎯 training_lab
   OFF = Hide /training and /lab pages
   Effect: "🔒 Training Lab is Disabled"

🏆 leaderboard
   OFF = Hide /leaderboard
   Effect: "🔒 Leaderboard is Disabled"

🚩 flag_submission
   OFF = Reject all flag submissions
   Effect: HTTP 403 + "Flag submission is disabled"

📖 swagger_docs
   OFF = Hide /swagger-ui
   Effect: HTTP 403 + "API documentation is disabled"

📋 code_inspector
   OFF = Hide /inspector (request logs)
   Effect: "🔒 Feature Disabled"

🔧 debug_mode
   ON = Enable /debug/session and /debug/api for troubleshooting
   Effect: Debug endpoints become accessible

📱 otp_display
   OFF = OTP not shown on login page
   Effect: Participants must find OTP via API/DB

📊 request_logging
   OFF = Stop logging requests to database
   Effect: /inspector becomes empty, less DB I/O
```

## The UI

```
🎛️ Feature Toggles

Feature          Description                               Status         Action
─────────────────────────────────────────────────────────────────────────────
training_lab     Disable to hide /training page            ✓ Enabled      [Toggle]
leaderboard      Disable to hide /leaderboard             ✓ Enabled      [Toggle]
flag_submission  Disable to prevent flag submissions       ✓ Enabled      [Toggle]
code_inspector   Disable to hide /inspector               ✓ Enabled      [Toggle]
swagger_docs     Disable to hide /swagger-ui              ✓ Enabled      [Toggle]
debug_mode       Enable debug endpoints                    ✗ Disabled     [Toggle]
otp_display      Show OTP on login page                   ✓ Enabled      [Toggle]
request_logging  Log all requests to database             ✓ Enabled      [Toggle]
```

Each row has a **Toggle** button:
1. Click it
2. Button shows "Updating..."
3. Page reloads
4. Status changes instantly
5. Participants see effect immediately

## Direct Database Queries

```sql
-- Check current state
SELECT feature_key, is_enabled FROM feature_toggles;

-- Enable a feature
UPDATE feature_toggles SET is_enabled = 1 WHERE feature_key = 'training_lab';

-- Disable a feature
UPDATE feature_toggles SET is_enabled = 0 WHERE feature_key = 'leaderboard';

-- Reset all to defaults
UPDATE feature_toggles SET is_enabled = 1 WHERE feature_key IN 
('training_lab', 'leaderboard', 'flag_submission', 'code_inspector', 'swagger_docs', 'otp_display', 'request_logging');
UPDATE feature_toggles SET is_enabled = 0 WHERE feature_key = 'debug_mode';
```

## Troubleshooting

| Problem | Check |
|---------|-------|
| Toggle button not working | Is CSRF token present? |
| Feature still enabled after toggle | Clear browser cache (Ctrl+F5) |
| "Feature not found" error | Was `sql/03-feature-toggles.sql` loaded? Run setup again |
| Participants still access feature | They may have cached pages; ask them to hard-refresh |

## Quick Event Timeline

```
T-30 min    → training_lab OFF (setup phase)
T-0         → training_lab ON (event starts!)
T+30 min    → leaderboard OFF (focus on learning, not scoring)
T+1 hour    → swagger_docs OFF (force exploration)
T+2 hours   → Announce hints in /training
T+3 hours   → leaderboard ON (show final ranks)
T+4 hours   → flag_submission OFF (freeze scores)
T+5 hours   → Event ends!
```

## That's It!

Go to `http://localhost:8888/features` and toggle away. 🎛️

---

**Print this and keep it handy during the event!**
