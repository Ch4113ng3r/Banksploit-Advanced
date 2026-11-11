# CSRF Token Fix - Testing Guide

## Problem
Mode B (Manual Replace) was throwing "CSRF token invalid" error because the `{CSRF}` placeholder in the JavaScript and HTML forms was not being replaced with the actual token value.

## Root Cause
In `src-superadmin/pages/bulk_replace_users.php`, the placeholder replacement was only happening for the `bulk` tab:
```php
if ($activeTab === 'bulk') {
    $content = str_replace(['{CSRF}', '{RESULTS}'], [$csrf, $resultsHtml], $content);
}
```

This meant when users accessed the `single` tab (Mode A/B), the `{CSRF}` placeholders in lines 433, 536, 459, and 571 were never replaced with the actual token value.

## Solution
Moved the `{CSRF}` replacement outside the conditional so it applies to both tabs:
```php
// Replace placeholders (for both bulk and single tabs)
$content = str_replace(['{CSRF}'], [$csrf], $content);

if ($activeTab === 'bulk') {
    $content = str_replace(['{RESULTS}'], [$resultsHtml], $content);
}
```

## Code Changes
**File:** `src-superadmin/pages/bulk_replace_users.php`
**Lines:** 614-619

**Before:**
```php
}

// Replace placeholders
if ($activeTab === 'bulk') {
    $content = str_replace(['{CSRF}', '{RESULTS}'], [$csrf, $resultsHtml], $content);
}

$content .= '</div>';
```

**After:**
```php
}

// Replace placeholders (for both bulk and single tabs)
$content = str_replace(['{CSRF}'], [$csrf], $content);

if ($activeTab === 'bulk') {
    $content = str_replace(['{RESULTS}'], [$resultsHtml], $content);
}

$content .= '</div>';
```

## Where CSRF Token is Used
1. **Line 347** - Bulk tab form hidden input: `<input type="hidden" name="csrf_token" value="{CSRF}">`
2. **Line 433** - Single Mode A form hidden input: `<input type="hidden" name="csrf_token" value="{CSRF}">`
3. **Line 536** - Single Mode B form hidden input: `<input type="hidden" name="csrf_token" value="{CSRF}">`
4. **Line 459** - Mode A JavaScript constant: `const CSRF = "{CSRF}";`
5. **Line 571** - Mode B JavaScript constant: `const CSRF = "{CSRF}";`

## Testing Steps

### Test 1: Mode B Manual Replace (Fixed)
1. Navigate to http://127.0.0.1:8888/bulk-replace-users?tab=single&mode=manual
2. Select a user from dropdown (e.g., BS100462)
3. Enter new Customer ID (e.g., G01197938)
4. Enter new Full Name (e.g., Test User)
5. Click "Replace User"
6. **Expected:** Success ✓ (not "CSRF token invalid" error)

### Test 2: Mode A Auto-Match Replace (Should Also Work)
1. Navigate to http://127.0.0.1:8888/bulk-replace-users?tab=single&mode=auto
2. Enter new Customer ID (e.g., G01197939)
3. Enter new Full Name (e.g., Auto Test)
4. Click "Replace Next Slot"
5. **Expected:** Success ✓

### Test 3: Bulk Format A (Should Still Work)
1. Navigate to http://127.0.0.1:8888/bulk-replace-users?tab=bulk
2. Upload CSV with 2 columns (Customer ID, Full Name)
3. **Expected:** Success ✓

### Test 4: Bulk Format B (Should Still Work)
1. Navigate to http://127.0.0.1:8888/bulk-replace-users?tab=bulk
2. Upload CSV with 3 columns (Old ID, New ID, New Name)
3. **Expected:** Success ✓

## Verification
- [ ] Mode B replace works without CSRF error
- [ ] Mode A replace works without CSRF error
- [ ] Bulk Format A works
- [ ] Bulk Format B works
- [ ] Database shows replaced user with new customer_id
- [ ] replacement_status updated to 'replaced' for bulk operations

## Impact
- **Severity:** High (Feature blocking - all single-user manual replacements failed)
- **Scope:** Single User Replacement Tab only (Mode A and Mode B)
- **Risk:** None (No breaking changes, pure bug fix)
- **Backwards Compatibility:** Full (doesn't change any APIs or behaviors)
