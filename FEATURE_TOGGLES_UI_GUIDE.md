# Feature Toggles UI — Visual Guide

## The Page

**URL:** `http://localhost:8888/features`

**Access:** Requires instructor login

## Layout

```
┌─────────────────────────────────────────────────────────────┐
│                                                               │
│  🎛️ Feature Toggles                                           │
│                                                               │
│  Control which features are accessible to participants in    │
│  the CTF app (port 9443).                                    │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Feature      | Description              | Status  | Updated │
│  ────────────────────────────────────────────────────────── │
│  training_lab │ Disable /training page   │ ✓ ON  │ 2h ago  │ [Toggle]
│  leaderboard  │ Disable /leaderboard     │ ✓ ON  │ 2h ago  │ [Toggle]
│  flag_subm... │ Prevent flag submissions │ ✓ ON  │ 5m ago  │ [Toggle]
│  code_inspec. │ Disable /inspector       │ ✓ ON  │ 2h ago  │ [Toggle]
│  swagger_docs │ Disable /swagger-ui      │ ✓ ON  │ 2h ago  │ [Toggle]
│  debug_mode   │ Enable debug endpoints   │ ✗ OFF │ 1d ago  │ [Toggle]
│  otp_display  │ Show OTP on login        │ ✓ ON  │ 2h ago  │ [Toggle]
│  request_...  │ Log requests to DB       │ ✓ ON  │ 2h ago  │ [Toggle]
│                                                               │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  📌 What Each Feature Does:                                  │
│                                                               │
│  • training_lab — Disable to hide /training page             │
│  • leaderboard — Disable to hide /leaderboard               │
│  • flag_submission — Disable flag submission API             │
│  • code_inspector — Disable to hide /inspector              │
│  • swagger_docs — Disable to hide /swagger-ui               │
│  • debug_mode — Enable debug endpoints                       │
│  • otp_display — Show OTP codes on login                    │
│  • request_logging — Log HTTP requests                      │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Individual Row Details

### Enabled Feature
```
┌──────────────────────────────────────────────────────────────┐
│ training_lab │ Disable to hide /training page │ ✓ Enabled │ [Toggle]
└──────────────────────────────────────────────────────────────┘
               │ Feature Key                                │ Status Badge (Green)
               │ (code name)                                │ Last Updated: Exact timestamp
```

### Disabled Feature
```
┌──────────────────────────────────────────────────────────────┐
│ leaderboard  │ Disable to hide /leaderboard │ ✗ Disabled │ [Toggle]
└──────────────────────────────────────────────────────────────┘
               │ Feature Key                               │ Status Badge (Red)
               │ (code name)                               │ Last Updated: Exact timestamp
```

## Status Badges

### Enabled (Green)
```
┌──────────────┐
│ ✓ Enabled    │  ← Click Toggle to turn OFF
└──────────────┘
```
- Background: Emerald/Green
- Icon: ✓ Checkmark
- Text: "Enabled"
- Action: Toggle will disable

### Disabled (Red)
```
┌──────────────┐
│ ✗ Disabled   │  ← Click Toggle to turn ON
└──────────────┘
```
- Background: Red
- Icon: ✗ Cross
- Text: "Disabled"
- Action: Toggle will enable

## Toggle Button States

### Normal
```
[Toggle]
```
- Blue background
- Clickable
- Shows "Toggle" text

### Updating
```
[Updating...]
```
- After clicking
- Disabled (grayed out)
- Shows "Updating..." text
- Page reloads automatically

### After Update
```
[Toggle]
```
- Returns to normal
- Page has reloaded
- Feature status changed
- Ready to toggle again

## Table Columns

### 1. Feature (Code Name)
```
feature_key in monospace font
Example: training_lab, leaderboard, flag_submission
```

### 2. Description
```
Human-readable explanation
Example: "Disable to hide /training page"
```

### 3. Status
```
Either: ✓ Enabled (green) or ✗ Disabled (red)
Shows current state at a glance
```

### 4. Last Updated
```
Exact timestamp when feature was last toggled
Example: "2026-08-09 14:35:22"
Shows when change was made
```

### 5. Action
```
[Toggle] button
Click to flip the feature on/off
Triggered instant database update
```

## Help Section

```
┌──────────────────────────────────────────────────────────────┐
│                                                                │
│ 📌 What Each Feature Does:                                   │
│                                                                │
│ training_lab — Disable to hide /training page and /lab       │
│ leaderboard — Disable to hide /leaderboard (rankings)        │
│ flag_submission — Disable to prevent earning points          │
│ code_inspector — Disable to hide /inspector (logs)           │
│ swagger_docs — Disable to hide /swagger-ui (API docs)        │
│ debug_mode — Enable to access /debug/* endpoints             │
│ otp_display — Show OTP codes on login page                   │
│ request_logging — Log all HTTP requests to database          │
│                                                                │
└──────────────────────────────────────────────────────────────┘
```

## Common Workflows

### Workflow 1: Disable Training Lab Before Event

```
1. Page loads → Show all features

2. Find row:
   training_lab │ Disable /training │ ✓ Enabled │ [Toggle]

3. Click [Toggle] button

4. Button changes:
   [Toggle] → [Updating...] (grayed out)

5. Page reloads (2-3 seconds)

6. Row now shows:
   training_lab │ Disable /training │ ✗ Disabled │ [Toggle]

7. When user visits /training on port 9443:
   ✗ See: "🔒 Training Lab is Disabled" (403)
```

### Workflow 2: Show Leaderboard After 3 Hours

```
1. Page shows:
   leaderboard │ Disable /leaderboard │ ✗ Disabled │ [Toggle]

2. Click [Toggle]

3. Wait for page reload

4. Row changes to:
   leaderboard │ Disable /leaderboard │ ✓ Enabled │ [Toggle]

5. When users visit /leaderboard:
   ✓ Leaderboard page loads normally
```

### Workflow 3: Enable Debug Mode for Troubleshooting

```
1. Find row:
   debug_mode │ Enable debug endpoints │ ✗ Disabled │ [Toggle]

2. Click [Toggle]

3. Wait for update

4. Row shows:
   debug_mode │ Enable debug endpoints │ ✓ Enabled │ [Toggle]

5. Can now access:
   • /debug/session — Check session data
   • /debug/api — Check API details

6. When done troubleshooting:
   Click [Toggle] again to disable
```

## Real-Time Response

When you click Toggle:

```
Browser → POST /features → Server → MySQL Update → Response → Page Reload

Timeline:
0ms    : Click [Toggle]
50ms   : Request sent
100ms  : Server processes
150ms  : Database updated
200ms  : Response received
250ms  : Page reloads
300ms  : New state visible
```

User sees:
1. Button text changes to "Updating..."
2. Page briefly shows loading state (if visible)
3. Page reloads
4. New feature state is displayed
5. Toggle ready to click again

## Mobile Responsiveness

### Desktop (> 1024px)
```
Full table layout:
Feature | Description | Status | Updated | Action
```

### Tablet (768px - 1024px)
```
Slightly compressed:
Feature | Desc (shorter) | Status | [Toggle]
Updated info in tooltip
```

### Mobile (< 768px)
```
Card layout:
┌─────────────────────┐
│ training_lab        │
│ Disable /training   │
│ ✓ Enabled           │
│ Updated: 2h ago     │
│ [Toggle]            │
└─────────────────────┘

Scrollable cards
```

## Color Scheme

| Element | Light Mode | Dark Mode |
|---------|-----------|----------|
| Background | White | Slate-900 |
| Table Header | Gray-100 | Slate-700 |
| Row Hover | Gray-50 | Slate-800 |
| Enabled Badge | Emerald-50 | Emerald-500/20 |
| Enabled Text | Emerald-700 | Emerald-300 |
| Disabled Badge | Red-50 | Red-500/20 |
| Disabled Text | Red-700 | Red-300 |
| Toggle Button | Blue-600 | Blue-500 |
| Button Hover | Blue-700 | Blue-400 |

## Error States

### CSRF Token Missing
```
Page loads but toggle returns error:
{
  "ok": false,
  "error": "CSRF token invalid."
}

Fix: Refresh page, try again
```

### Feature Not Found
```
Toggle returns:
{
  "ok": false,
  "error": "Unknown action or feature not found."
}

Fix: Feature may have been deleted, contact admin
```

### Database Error
```
Toggle returns:
{
  "ok": false,
  "error": "Could not update: [MySQL error]"
}

Fix: Check database connection, server logs
```

## Tips & Tricks

### Tip 1: Bulk Reset
Instead of toggling each individually:

```sql
-- Reset all to defaults
UPDATE feature_toggles SET is_enabled = 1;
UPDATE feature_toggles SET is_enabled = 0 WHERE feature_key = 'debug_mode';
```

Then refresh the Features page.

### Tip 2: Check Last Updated
Timestamp shows when feature was last toggled:
- 5m ago = toggled 5 minutes ago
- 2h ago = toggled 2 hours ago
- 1d ago = toggled 1 day ago

Helps track when changes were made.

### Tip 3: Keyboard Shortcut
No keyboard shortcut (requires clicking Toggle), but:
- Tab to navigate rows
- Space/Enter would require additional code
- Feature: Future enhancement

### Tip 4: Multiple Instructors
If multiple instructors using portal simultaneously:
- All see same feature states (database backed)
- No conflicts between toggles
- Timestamps show who toggled (could enhance later)
- Recommend discussing toggle strategy before event

## Animation & Feedback

### Button Click Animation
```
Initial:    [Toggle]  ← Normal button
Click:      [Updating...] ← Grayed, text changes
Processing: [Loading]  ← Fade effect
Complete:   [Toggle]  ← Back to normal
```

### Page Reload
```
During update:
- Button is disabled (can't spam-click)
- Visual feedback: "Updating..."
- Page briefly shows loading state
- Smooth reload without full page flash
```

## Accessibility

- ✓ Proper semantic HTML (table, buttons)
- ✓ Color not only indicator (has text: "Enabled" / "Disabled")
- ✓ Keyboard navigable (tabs through rows)
- ✓ Clear labels (Feature, Description, Status, etc.)
- ✓ Sufficient contrast (emerald/red on light/dark)

---

**Print this guide for quick reference during events!**

The Features page is simple and intuitive — one click per feature to toggle it on/off.
