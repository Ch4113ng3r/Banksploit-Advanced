# CTF Leaderboard Feature

**Date:** July 19, 2026  
**Status:** ✅ IMPLEMENTED

## Overview

A real-time leaderboard showing all logged-in CTF participants ranked by points earned from flag submissions.

## Features

### 1. **Leaderboard Page** (`/leaderboard`)
- 📊 Displays all users ranked by total CTF points
- Shows rank, name, customer ID, flags captured, and total points
- Current user's row is highlighted
- Real-time ranking with medals for top 3 (🥇 🥈 🥉)
- Current user's personal stats card showing rank, points, and flags

### 2. **Leaderboard API** (`/api/v1/leaderboard`)
- JSON endpoint for programmatic access
- Returns full leaderboard with ranks
- Includes `is_current_user` flag for client-side highlighting
- Useful for building custom dashboards or integrations

### 3. **Authentication Required**
- Only logged-in users can view the leaderboard
- All users can see all other users' points and rankings
- Creates a transparent, competitive scoring system

## Files Created

```
src/modules/leaderboard/
├── index.php       (HTML leaderboard page)
└── api.php         (JSON API endpoint)
```

## Files Modified

```
src/templates/layout.php
├── Added leaderboard to sidebar navigation
├── Icon: bar chart SVG
└── Appears in main menu alongside Dashboard, Transfer, etc.

src/index.php
├── Added GET /leaderboard route → modules/leaderboard/index.php
└── Added GET /api/v1/leaderboard → modules/leaderboard/api.php
```

## Database Query

Uses existing `ctf_submissions` table with `users`:

```sql
SELECT
    u.id,
    u.name,
    u.customer_id,
    COALESCE(SUM(cs.points), 0) as total_points,
    COUNT(DISTINCT cs.vuln_key) as flags_captured
FROM users u
LEFT JOIN ctf_submissions cs ON u.id = cs.user_id
GROUP BY u.id, u.name, u.customer_id
ORDER BY total_points DESC, u.name ASC
```

## API Response Example

```json
{
  "success": true,
  "leaderboard": [
    {
      "rank": 1,
      "id": 1,
      "name": "John Doe",
      "customer_id": "CUST001",
      "points": 500,
      "flags": 5,
      "is_current_user": false
    },
    {
      "rank": 2,
      "id": 2,
      "name": "Jane Smith",
      "customer_id": "CUST002",
      "points": 450,
      "flags": 4,
      "is_current_user": true
    }
  ],
  "total_participants": 2
}
```

## Styling

- Uses Tailwind CSS (dark theme, consistent with BankSploit UI)
- Responsive table layout
- Color-coded ranks (gold, silver, bronze)
- Highlighted current user row
- Badge-style point displays

## Access Points

1. **Sidebar Navigation** - "Leaderboard" link in main menu
2. **Direct URL** - `http://localhost:9443/leaderboard`
3. **API** - `http://localhost:9443/api/v1/leaderboard`

## Usage

### View Leaderboard in Browser
```
1. Login to BankSploit
2. Click "Leaderboard" in sidebar
3. See all participants ranked by points
4. Your row is highlighted
```

### Fetch via API
```bash
curl -H "Cookie: PHPSESSID=<session>" http://localhost:9443/api/v1/leaderboard
```

## Notes

- **Real-time updates** - Rankings reflect latest submissions from `ctf_submissions` table
- **Ties** - Users with same points are sorted by name
- **Zero flags** - Users with no submissions appear at bottom with 0 points
- **No permissions** - All authenticated users see all other users (by design for CTF competition)
- **Performance** - Query uses indexes on `ctf_submissions.user_id` and `.points` for fast sorting

## Future Enhancements

- Leaderboard pagination for large participant counts
- Filter/search by participant name
- Export leaderboard to CSV/PDF
- Historical rankings snapshots
- Points over time chart
- Category-based leaderboards (by challenge group)
