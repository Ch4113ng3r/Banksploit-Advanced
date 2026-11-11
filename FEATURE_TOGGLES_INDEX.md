# Feature Toggles — Complete Documentation Index

## 🎯 Start Here

**New to Feature Toggles?**

1. **Read:** [FEATURE_TOGGLES_QUICK_REFERENCE.md](FEATURE_TOGGLES_QUICK_REFERENCE.md) (5 min read)
   - One-page quick reference
   - Common scenarios
   - What each feature does

2. **Try:** Go to `http://localhost:8888/features`
   - Login as instructor
   - Toggle a feature
   - Test it works

3. **Learn:** [FEATURE_TOGGLES_GUIDE.md](FEATURE_TOGGLES_GUIDE.md) (20 min read)
   - Complete reference
   - Architecture details
   - Security considerations

## 📚 Documentation Files

### Quick References (For During Events)

| File | Purpose | Read Time |
|------|---------|-----------|
| [FEATURE_TOGGLES_QUICK_REFERENCE.md](FEATURE_TOGGLES_QUICK_REFERENCE.md) | One-page cheat sheet | 5 min |
| [FEATURE_TOGGLES_SUMMARY.txt](FEATURE_TOGGLES_SUMMARY.txt) | Complete overview | 10 min |
| **Print these during events!** | | |

### Comprehensive Guides

| File | Purpose | Read Time |
|------|---------|-----------|
| [FEATURE_TOGGLES_GUIDE.md](FEATURE_TOGGLES_GUIDE.md) | Complete reference guide | 20 min |
| [FEATURE_TOGGLES_IMPLEMENTATION.md](FEATURE_TOGGLES_IMPLEMENTATION.md) | How it was built | 15 min |
| [FEATURE_TOGGLES_UI_GUIDE.md](FEATURE_TOGGLES_UI_GUIDE.md) | UI walkthrough | 10 min |
| [FEATURE_TOGGLES_API_EXAMPLES.md](FEATURE_TOGGLES_API_EXAMPLES.md) | Code examples | 15 min |

### This File

| File | Purpose |
|------|---------|
| [FEATURE_TOGGLES_INDEX.md](FEATURE_TOGGLES_INDEX.md) | Navigation & overview (you are here) |

## 🎯 Find What You Need

### "I want to..."

**...get started immediately**
→ [FEATURE_TOGGLES_QUICK_REFERENCE.md](FEATURE_TOGGLES_QUICK_REFERENCE.md)

**...understand the complete system**
→ [FEATURE_TOGGLES_GUIDE.md](FEATURE_TOGGLES_GUIDE.md)

**...see how it works architecturally**
→ [FEATURE_TOGGLES_IMPLEMENTATION.md](FEATURE_TOGGLES_IMPLEMENTATION.md)

**...understand the UI**
→ [FEATURE_TOGGLES_UI_GUIDE.md](FEATURE_TOGGLES_UI_GUIDE.md)

**...integrate it into my code**
→ [FEATURE_TOGGLES_API_EXAMPLES.md](FEATURE_TOGGLES_API_EXAMPLES.md)

**...plan my event timeline**
→ [FEATURE_TOGGLES_GUIDE.md](FEATURE_TOGGLES_GUIDE.md) → Real-World Event Timeline section

**...troubleshoot an issue**
→ [FEATURE_TOGGLES_GUIDE.md](FEATURE_TOGGLES_GUIDE.md) → Troubleshooting section

**...automate feature toggles**
→ [FEATURE_TOGGLES_API_EXAMPLES.md](FEATURE_TOGGLES_API_EXAMPLES.md) → Event Automation Examples section

## 📂 Files Modified/Created

### New Files

```
sql/03-feature-toggles.sql
├─ Database schema
├─ 8 default features
└─ Auto-loaded by start.sh

src-superadmin/pages/features.php
├─ Instructor portal UI
├─ Toggle buttons
└─ Real-time database updates

FEATURE_TOGGLES_*.md (5 files)
├─ Complete documentation
├─ Examples and guides
└─ Quick references
```

### Modified Files

```
src/core/helpers.php
├─ is_feature_enabled() function
└─ get_all_features() function

src/index.php
├─ Feature toggle middleware
└─ 4 security checks

src-superadmin/index.php
├─ Route to /features page
└─ Page registration

src-superadmin/templates/layout.php
├─ Navigation link added
└─ Sidebar menu updated
```

## ✨ Key Features

- ✅ **8 Toggleable Features** - Complete control over CTF functionality
- ✅ **Instant Effect** - No restart needed
- ✅ **Database Backed** - Persists across restarts
- ✅ **CSRF Protected** - Secure toggling
- ✅ **Instructor Only** - Port 8888 access required
- ✅ **Timestamp Tracking** - Know when changes were made
- ✅ **Clean UI** - Simple one-click toggles
- ✅ **Fully Documented** - 5 comprehensive guides

## 🚀 Quick Setup

```bash
# 1. Load database schema
./start.sh setup
# (or: mysql -u banksploit -p banksploit < sql/03-feature-toggles.sql)

# 2. Start the app
./start.sh start

# 3. Go to Feature Toggles page
# http://localhost:8888/features

# 4. Login as instructor
# username: instructor
# password: instructor@123

# 5. Toggle features!
```

## 📊 Available Features

| # | Feature | Default | Purpose |
|---|---------|---------|---------|
| 1 | **training_lab** | ON | CTF challenges & Lab Mode |
| 2 | **leaderboard** | ON | Participant rankings |
| 3 | **flag_submission** | ON | Flag submission API |
| 4 | **code_inspector** | ON | Request/response logs |
| 5 | **swagger_docs** | ON | API documentation |
| 6 | **debug_mode** | OFF | Debug endpoints |
| 7 | **otp_display** | ON | OTP display on login |
| 8 | **request_logging** | ON | HTTP request logging |

## 🎯 Common Scenarios

### Before Event
```
training_lab    OFF  (don't let them start early)
leaderboard     OFF  (no early peeking)
flag_submission ON   (ready to go)
```

### During Event
```
training_lab    ON   (challenges accessible)
leaderboard     ON   (show progress)
swagger_docs    ON   (help with discovery)
```

### After 1 Hour
```
swagger_docs    OFF  (force exploration)
leaderboard     OFF  (prevent score rushing)
```

### After 2 Hours
```
Announce hints available!
```

### End of Event
```
flag_submission OFF  (freeze scores)
leaderboard     ON   (show final rankings)
```

## 🔐 Security

- ✅ CSRF token validation
- ✅ Instructor-only access
- ✅ No public API
- ✅ Database-backed
- ✅ Timestamp audit trail

## 💻 Technologies

- **Database:** MySQL 8.0 (feature_toggles table)
- **Backend:** PHP 8.3 (middleware + UI)
- **Frontend:** HTML + Tailwind CSS
- **Authentication:** Instructor portal login
- **Communication:** AJAX/Fetch API

## 🧪 Testing Checklist

- [ ] Database table created
- [ ] Can access /features page
- [ ] Can toggle features
- [ ] Features block correctly
- [ ] Error messages display
- [ ] CSRF protection works
- [ ] Features persist after restart
- [ ] Documentation is clear

## 📞 Support

**Question not answered here?**

1. Check [FEATURE_TOGGLES_GUIDE.md](FEATURE_TOGGLES_GUIDE.md)
2. Search for keyword in documentation
3. Check troubleshooting section
4. Review API examples

## 📖 Reading Order

**For Beginners:**
1. This file (FEATURE_TOGGLES_INDEX.md)
2. FEATURE_TOGGLES_QUICK_REFERENCE.md
3. FEATURE_TOGGLES_GUIDE.md

**For Developers:**
1. FEATURE_TOGGLES_IMPLEMENTATION.md
2. FEATURE_TOGGLES_API_EXAMPLES.md
3. Source code: src-superadmin/pages/features.php

**For Event Organizers:**
1. FEATURE_TOGGLES_QUICK_REFERENCE.md
2. FEATURE_TOGGLES_GUIDE.md (Event Timeline section)
3. FEATURE_TOGGLES_UI_GUIDE.md

**For Admins:**
1. FEATURE_TOGGLES_IMPLEMENTATION.md
2. FEATURE_TOGGLES_API_EXAMPLES.md
3. Database documentation

## ✅ Verification

Your Feature Toggles are working if:

1. ✅ Can access `http://localhost:8888/features`
2. ✅ See 8 features listed
3. ✅ Can click [Toggle] button
4. ✅ Status changes immediately
5. ✅ Changes persist after page refresh
6. ✅ Disabled features block on port 9443
7. ✅ Error messages display correctly

## 🎉 Success!

You now have full control over the CTF from the Instructor Portal!

**Next steps:**
- Review the quick reference
- Test toggling a feature
- Plan your event timeline
- Brief your team
- Run the event!

---

**Version:** 1.0  
**Last Updated:** 2026-08-09  
**Status:** ✅ Production Ready

**Questions?** See the comprehensive guides above.  
**Ready to start?** Go to [FEATURE_TOGGLES_QUICK_REFERENCE.md](FEATURE_TOGGLES_QUICK_REFERENCE.md)
