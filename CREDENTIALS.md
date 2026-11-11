# BankSploit CTF — Credentials & Access

## URLs

| Service | URL | Notes |
|---------|-----|-------|
| BankSploit (Participant App) | `https://localhost` | Main banking app (HTTPS, port 443) |
| Superadmin Panel (Instructor) | `https://localhost:8888` | CTF management, hints, scoring |
| OTP Inbox (Simulated SMS) | `https://localhost/otp-inbox` | View OTPs for any user (in-app) |

> **Note:** If running on a custom host/port, replace `localhost` with the configured `APP_HOST` and `APP_PORT` values from your environment.

---

## Instructor (Superadmin)

| Field | Value |
|-------|-------|
| URL | `https://localhost:8888` |
| Username | `instructor` |
| Password | `instructor@123` |
| Role | Superadmin (CTF controller) |

### Instructor Capabilities
- Enable/disable vulnerability levels per challenge
- Enable/disable participant-facing hints (global + per-hint)
- Edit hint titles and descriptions
- View scoring and flag submissions
- Monitor participant progress
- Access training engine controls (Lab Mode)

---

## Participants (Standard Users)

All participants log in at `https://localhost/login` with their **Customer ID** and **Password**.  
After credentials, a 4-digit OTP is required — view it at the **OTP Inbox** (`/otp-inbox`).

| # | Name | Customer ID | Password | Role |
|---|------|-------------|----------|------|
| 1 | Rahul Sharma | `BS100234` | `password123` | user |
| 2 | Priya Menon | `BS100235` | `priya@123` | user |
| 3 | Vikram Singh | `BS100236` | `vikram@123` | user |
| 4 | Aditya Rao | `BS100237` | `aditya@123` | user |
| 5 | Ankit Kumar | `BS100238` | `1234` | user |
| 6 | Admin User | `ADMIN001` | `admin@123` | admin |

> **Recommended for participants:** Use `BS100234` / `password123` (Rahul Sharma) as the primary test account. Other accounts exist to demonstrate IDOR exploitation (viewing other users' statements, transferring from other accounts).

---

## Account Numbers (per user)

| User | Savings | Current | FD |
|------|---------|---------|-----|
| Rahul Sharma (BS100234) | `50100100000001` | `50200100000001` | `50300100000001` |
| Priya Menon (BS100235) | `50100100000002` | `50200100000002` | — |
| Vikram Singh (BS100236) | `50100100000003` | `50200100000003` | — |
| Aditya Rao (BS100237) | `50100100000004` | `50200100000004` | — |
| Ankit Kumar (BS100238) | `50100100000006` | `50200100000006` | — |
| Admin User (ADMIN001) | `50100100000005` | — | — |

---

## Quick Start

### For the Instructor
1. Open `https://localhost:8888`
2. Login with `instructor` / `instructor@123`
3. Navigate to **Hints** to enable/disable participant hints
4. Use the **Training** panel to set vulnerability levels (0 = fully vulnerable, 3 = fully patched)

### For Participants
1. Open `https://localhost/login`
2. Enter Customer ID: `BS100234`, Password: `password123`
3. Check OTP at `https://localhost/otp-inbox` → enter the 4-digit code
4. You're in — explore Dashboard, Statements, Transfer pages
5. Use browser DevTools (Network tab, Console, Sources) to find flags

---

## Notes
- Passwords are stored as bcrypt hashes (and intentionally also as MD5 for the SQLi challenge)
- The `ADMIN001` account has the `admin` role in the banking app — this is separate from the instructor/superadmin panel
- OTPs are 4-digit codes; at vulnerable levels they may leak via JavaScript files (check DevTools → Sources)
- Profile page is read-only — no editable fields
