# Feature Toggles - Instructor Control Panel

## Overview

The **Feature Toggles** system allows instructors to enable/disable CTF features on the main app (port 9443) directly from the Control Panel (port 8888) without modifying code or restarting services.

## Access

**URL:** `http://localhost:8888/features`

**Requirements:**
- Logged in as instructor (port 8888)
- Direct database control via the Control Panel

## Available Features

| Feature | Default | Description |
|---------|---------|-------------|
| **training_lab** | ✅ Enabled | Disables `/training`, `/lab`, and challenge mode. Participants cannot access the CTF dashboard |
| **leaderboard** | ✅ Enabled | Disables `/leaderboard`. Participants cannot view rankings |
| **flag_submission** | ✅ Enabled | Disables flag submission API endpoints. Prevents earning points |
| **code_inspector** | ✅ Enabled | Disables `/inspector`. Participants cannot see request/response logs |
| **swagger_docs** | ✅ Enabled | Disables `/swagger-ui`. Participants cannot access API documentation |
| **debug_mode** | ❌ Disabled | Enables debug endpoints (`/debug/session`, `/debug/api`) for troubleshooting |
| **otp_display** | ✅ Enabled | Shows OTP codes on login page (Lab Mode convenience feature) |
| **request_logging** | ✅ Enabled | Logs all HTTP requests to the database |

## Use Cases

### 1. **During Event Setup**
```
Disable: training_lab
Purpose: Keep participants off the CTF challenges until event officially starts
Action:
  - Go to /features
  - Click "Toggle" next to training_lab
  - Status changes to "✗ Disabled"
  - Participants see: "🔒 Training Lab is Disabled"
```

### 2. **Hide Challenge Scores During Competition**
```
Disable: leaderboard
Purpose: Prevent participants from checking their rank during active play
Action:
  - Toggle leaderboard OFF
  - Re-enable after event ends
```

### 3. **Prevent Early Flag Submissions**
```
Disable: flag_submission
Purpose: Stop premature flag captures before hints are released
Action:
  - Start with flag_submission ON
  - After first hour: Toggle OFF
  - Wait for participants to explore more
  - Toggle back ON when releasing hints
```

### 4. **Disable API Documentation**
```
Disable: swagger_docs
Purpose: Force participants to discover APIs through exploration
Action:
  - Toggle swagger_docs OFF
  - Participants cannot access `/swagger-ui`
  - Encourages code reading and request inspection
```

### 5. **Troubleshooting Issues**
```
Enable: debug_mode
Purpose: Access debug endpoints for diagnosing problems
Action:
  - Toggle debug_mode ON
  - Call `/debug/session` to inspect session state
  - Call `/debug/api` to see API details
  - Disable when done
```

## How It Works

### Architecture

```
Port 8888 (Instructor Portal)
    ↓
Feature Toggles UI (/features)
    ↓
Toggle Button Click
    ↓
JSON POST Request (feature + csrf_token)
    ↓
Update feature_toggles table in MySQL
    ↓
Port 9443 (CTF App)
    ↓
Middleware checks is_feature_enabled()
    ↓
Allow or Block request
```

### Database Schema

```sql
CREATE TABLE feature_toggles (
    feature_key VARCHAR(50) PRIMARY KEY,      -- e.g., 'training_lab'
    is_enabled BOOLEAN DEFAULT TRUE,
    description VARCHAR(255),
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Code Implementation

**Instructor Portal** (`src-superadmin/pages/features.php`):
- Displays all features with current status
- Toggle button POSTs to `/features`
- Updates database via `UPDATE feature_toggles SET is_enabled = :state`

**Main App** (`src/index.php`):
- Middleware checks `is_feature_enabled('training_lab')` before routing
- If disabled: Returns 403 with message "🔒 Feature is Disabled"
- If enabled: Routes normally

**Helper Functions** (`src/core/helpers.php`):
```php
is_feature_enabled(string $featureKey): bool
// Checks feature_toggles table
// Returns TRUE if enabled or feature doesn't exist

get_all_features(): array
// Returns all features for admin UI display
```

## Effects When Disabled

### training_lab = OFF
- User tries to visit `/training` or `/lab`
- Sees: "🔒 Training Lab is Disabled"
- HTTP 403 status
- All training APIs blocked

### leaderboard = OFF
- User tries to visit `/leaderboard`
- Sees: "🔒 Leaderboard is Disabled"
- HTTP 403 status

### flag_submission = OFF
- User tries to submit a flag via `/api/v1/training/submit-flag`
- Receives: `{"ok": false, "error": "Flag submission is currently disabled"}`
- HTTP 403 status
- No points earned

### code_inspector = OFF
- User tries to visit `/inspector`
- Sees: "🔒 Feature Disabled"
- HTTP 403 status

### swagger_docs = OFF
- User tries to visit `/swagger-ui`
- Receives: `{"ok": false, "error": "API documentation is currently disabled"}`
- HTTP 403 status

### debug_mode = ON
- Debug endpoints become accessible
- Instructors can call `/debug/session` and `/debug/api`
- NEVER enable in production with public access

### otp_display = OFF
- OTP codes no longer shown on login page
- Forces participants to use database or API to get OTP

### request_logging = OFF
- HTTP requests no longer logged to database
- `/inspector` page becomes empty
- Reduces database I/O

## Admin Interface

### Toggle Button
```html
<button onclick="toggleFeature('training_lab')">Toggle</button>
```

**Behavior:**
1. Click sends POST with `action=toggle` and `feature=training_lab`
2. Button shows "Updating..."
3. Feature state flips in database
4. Page reloads to show new status

### Status Indicators
- ✅ **Enabled** — Green badge, feature is accessible
- ❌ **Disabled** — Red badge, feature is blocked

### Last Updated
Shows exact timestamp of last toggle change

## Real-World Event Timeline

### T-30 min: Setup
```
✅ training_lab = ON (ready for participants)
✅ leaderboard = ON (but show preliminary stats)
✅ flag_submission = ON
✅ swagger_docs = ON (help them explore)
✅ debug_mode = OFF (security)
✅ otp_display = ON (convenience for first-timers)
```

### T-0: Event Starts
```
All features ON as above
Participants begin exploring app
```

### T+30 min: Hide Early Results
```
❌ leaderboard = OFF
Purpose: Prevent competitive rushing
Participants must focus on learning, not score-chasing
```

### T+1 hour: Close Easy Access
```
❌ swagger_docs = OFF
Purpose: Force discovery through exploration
Participants now must inspect requests to find endpoints
```

### T+2 hours: Release Hints
```
✅ flag_submission = ON (ensure ON)
💡 Announce hints are available in Training Lab
Participants revisit challenges with guidance
```

### T+3 hours: Show Progress
```
✅ leaderboard = ON
Purpose: Motivate with visible ranking
Participants see their rank and earned points
```

### T+4 hours: End Event
```
❌ flag_submission = OFF
Purpose: Freeze final scores
No more points can be earned
```

## API Reference

### Toggle a Feature

**POST** `/features`

**Request:**
```json
{
  "action": "toggle",
  "feature": "training_lab",
  "csrf_token": "hexadecimal_token_here"
}
```

**Response (Success):**
```json
{
  "ok": true,
  "feature": "training_lab",
  "is_enabled": false
}
```

**Response (Error):**
```json
{
  "ok": false,
  "error": "CSRF token invalid."
}
```

## Security Considerations

### CSRF Protection
- All toggle requests require valid CSRF token
- Token must be from session (instructor-only)
- Prevents unauthorized toggles

### Access Control
- Only accessible on port 8888 (Control Panel)
- Requires instructor login
- No public API for toggles

### No Remote Access
- Feature toggles stored in database
- Requires database access
- Cannot be toggled remotely without compromising Control Panel

### Audit Trail
- Each toggle records `updated_at` timestamp
- Instructors can see when toggles were changed
- No explicit log of WHO changed what (could enhance later)

## Troubleshooting

### Feature Not Responding
**Problem:** Clicked toggle but nothing happened

**Solution:**
1. Check browser console (F12) for errors
2. Verify CSRF token is present
3. Check network tab for POST request
4. Verify instructor is logged in

### Feature Stays Enabled
**Problem:** Toggled OFF but feature still works

**Solution:**
1. Check that the exact feature_key is correct
2. Verify middleware is checking the right feature
3. Clear browser cache (hard refresh: Ctrl+F5)
4. Check database directly: `SELECT * FROM feature_toggles WHERE feature_key = '...'`

### Database Table Missing
**Problem:** Error accessing feature_toggles table

**Solution:**
1. Run: `php ./start.sh setup`
2. This loads `sql/03-feature-toggles.sql`
3. Or manually: `mysql -u banksploit -p banksploit < sql/03-feature-toggles.sql`

## Enhancement Ideas

### Future Features
- [ ] **Toggle schedule** — Automatically enable/disable at specific times
- [ ] **Partial toggles** — Disable for specific users only
- [ ] **Audit log** — Track who toggled what and when
- [ ] **Bulk actions** — Set all to same state with one click
- [ ] **Per-user toggles** — Different features for different participants
- [ ] **Conditional toggles** — Enable based on participant progress (e.g., after 3 flags)

### Implementation Example (Future)
```php
// Scheduled toggle (enable at specific time)
if (time() > EVENT_START_TIME && time() < EVENT_END_TIME) {
    enable_feature('training_lab');
} else {
    disable_feature('training_lab');
}

// Per-user toggle
is_feature_enabled_for_user('training_lab', $userId)
```

## Support

### Questions?
- Check the Use Cases section above
- Review the Real-World Event Timeline
- Inspect the database: `SELECT * FROM feature_toggles`

### Bugs?
- Report in instructor chat with:
  - Which feature was toggled
  - Expected behavior
  - Actual behavior
  - Screenshot of error (if any)

---

**Version:** 1.0  
**Last Updated:** 2026-08-09  
**Status:** Production Ready ✅
