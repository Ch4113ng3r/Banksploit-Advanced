# Feature Toggles API — Examples & Recipes

## Overview

This document shows you how to interact with the Feature Toggles system programmatically via the database and HTTP API.

## HTTP API

### Toggle a Feature (AJAX/Fetch)

**Endpoint:** `POST /features`

**Requires:** Instructor login on port 8888 + Valid CSRF token

**Request Body:**
```json
{
  "action": "toggle",
  "feature": "training_lab",
  "csrf_token": "hexadecimal_csrf_token_here"
}
```

**Example JavaScript:**
```javascript
// Get CSRF token from page
const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;

// Toggle training_lab feature
fetch('/features', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        action: 'toggle',
        feature: 'training_lab',
        csrf_token: csrfToken
    })
})
.then(response => response.json())
.then(data => {
    if (data.ok) {
        console.log(`Feature ${data.feature} is now ${data.is_enabled ? 'enabled' : 'disabled'}`);
        location.reload(); // Reload page to show new state
    } else {
        console.error('Error:', data.error);
    }
})
.catch(error => console.error('Request failed:', error));
```

**Example curl:**
```bash
# Get CSRF token first (requires being logged in)
CSRF=$(curl -s -b cookies.txt http://localhost:8888/features | \
       grep -oP 'value="\K[^"]*(?=[^"]*csrf_token)' | head -1)

# Toggle feature
curl -X POST http://localhost:8888/features \
  -b cookies.txt \
  -H "Content-Type: application/json" \
  -d "{
    \"action\": \"toggle\",
    \"feature\": \"training_lab\",
    \"csrf_token\": \"$CSRF\"
  }"
```

**Example Python:**
```python
import requests
import json

# Login to get session
session = requests.Session()
login_response = session.post('http://localhost:8888/login', data={
    'username': 'instructor',
    'password': 'instructor@123'
})

# Get CSRF token from page
import re
features_page = session.get('http://localhost:8888/features').text
csrf_match = re.search(r'value="([a-f0-9]+)".*csrf_token', features_page)
csrf_token = csrf_match.group(1) if csrf_match else None

# Toggle feature
response = session.post('http://localhost:8888/features', json={
    'action': 'toggle',
    'feature': 'training_lab',
    'csrf_token': csrf_token
})

result = response.json()
if result['ok']:
    print(f"Feature {result['feature']} is now {'enabled' if result['is_enabled'] else 'disabled'}")
else:
    print(f"Error: {result.get('error')}")
```

**Success Response:**
```json
{
  "ok": true,
  "feature": "training_lab",
  "is_enabled": false
}
```

**Error Response (CSRF Token Invalid):**
```json
{
  "ok": false,
  "error": "CSRF token invalid."
}
```

**Error Response (Feature Not Found):**
```json
{
  "ok": false,
  "error": "Unknown action or feature not found."
}
```

## Database API

### View All Features

```sql
SELECT 
    feature_key,
    is_enabled,
    description,
    updated_at
FROM feature_toggles
ORDER BY feature_key;
```

**Example Output:**
```
+------------------+------------+-----------------------------------------+---------------------+
| feature_key      | is_enabled | description                             | updated_at          |
+------------------+------------+-----------------------------------------+---------------------+
| code_inspector   |          1 | Enable/disable /inspector page          | 2026-08-09 14:35:22 |
| debug_mode       |          0 | Enable/disable debug endpoints          | 2026-08-09 10:15:00 |
| flag_submission  |          1 | Enable/disable flag submission API      | 2026-08-09 14:30:00 |
| leaderboard      |          0 | Enable/disable /leaderboard page        | 2026-08-09 13:45:11 |
| otp_display      |          1 | Show OTP codes on login page            | 2026-08-09 12:00:00 |
| request_logging  |          1 | Log all requests to database            | 2026-08-09 08:00:00 |
| swagger_docs     |          1 | Enable/disable /swagger-ui page         | 2026-08-09 11:20:30 |
| training_lab     |          0 | Enable/disable /training page           | 2026-08-09 14:35:22 |
+------------------+------------+-----------------------------------------+---------------------+
```

### Check Single Feature Status

```sql
SELECT is_enabled FROM feature_toggles WHERE feature_key = 'training_lab';
```

**Output:**
```
+------------+
| is_enabled |
+------------+
|          0 |
+------------+
```

### Enable Specific Feature

```sql
UPDATE feature_toggles 
SET is_enabled = 1 
WHERE feature_key = 'training_lab';
```

**Verify:**
```sql
SELECT feature_key, is_enabled FROM feature_toggles WHERE feature_key = 'training_lab';
```

### Disable Specific Feature

```sql
UPDATE feature_toggles 
SET is_enabled = 0 
WHERE feature_key = 'leaderboard';
```

### Disable Multiple Features

```sql
UPDATE feature_toggles 
SET is_enabled = 0 
WHERE feature_key IN ('swagger_docs', 'code_inspector', 'leaderboard');
```

### Enable Multiple Features

```sql
UPDATE feature_toggles 
SET is_enabled = 1 
WHERE feature_key IN ('training_lab', 'leaderboard', 'flag_submission');
```

### Reset All to Defaults

```sql
-- Enable all except debug_mode
UPDATE feature_toggles SET is_enabled = 1;

-- Then disable debug_mode
UPDATE feature_toggles SET is_enabled = 0 WHERE feature_key = 'debug_mode';
```

**Verify:**
```sql
SELECT feature_key, is_enabled FROM feature_toggles ORDER BY feature_key;
```

### Get Features Changed in Last N Hours

```sql
SELECT 
    feature_key,
    is_enabled,
    updated_at,
    TIMESTAMPDIFF(HOUR, updated_at, NOW()) as hours_ago
FROM feature_toggles
WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
ORDER BY updated_at DESC;
```

### Audit Trail (Last 10 Changes)

Since we don't track WHO changed what, you can check timestamps:

```sql
SELECT 
    feature_key,
    is_enabled,
    updated_at,
    DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as formatted_time
FROM feature_toggles
ORDER BY updated_at DESC
LIMIT 10;
```

## PHP Helper Functions

Located in `src/core/helpers.php`

### Check if Feature is Enabled

```php
if (is_feature_enabled('training_lab')) {
    // Feature is enabled - allow request
    echo "Training Lab is available";
} else {
    // Feature is disabled
    echo "Training Lab is currently disabled";
}
```

### Get All Features

```php
$features = get_all_features();

foreach ($features as $feature) {
    echo $feature['feature_key'] . ": " . 
         ($feature['is_enabled'] ? "ON" : "OFF") . 
         " (Updated: " . $feature['updated_at'] . ")\n";
}

/*
Output:
code_inspector: ON (Updated: 2026-08-09 14:35:22)
debug_mode: OFF (Updated: 2026-08-09 10:15:00)
flag_submission: ON (Updated: 2026-08-09 14:30:00)
leaderboard: OFF (Updated: 2026-08-09 13:45:11)
otp_display: ON (Updated: 2026-08-09 12:00:00)
request_logging: ON (Updated: 2026-08-09 08:00:00)
swagger_docs: ON (Updated: 2026-08-09 11:20:30)
training_lab: OFF (Updated: 2026-08-09 14:35:22)
*/
```

### Use in Route Guards

```php
// In any module/page
if (!is_feature_enabled('training_lab')) {
    http_response_code(403);
    render_layout('Feature Disabled', 
        '<div class="text-center"><h2>🔒 Training Lab is Disabled</h2></div>',
        ['active' => '']
    );
    exit;
}

// Continue with normal page logic
echo "Training Lab content here";
```

## Event Automation Examples

### Example 1: Script to Toggle Features at Specific Times

```bash
#!/bin/bash
# event-toggle.sh - Auto-toggle features at specific times during event

DB_USER="banksploit"
DB_PASS="banksploit_pass"
DB_NAME="banksploit"

# Helper function to execute SQL
run_sql() {
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$1"
}

# Helper function to toggle
toggle_feature() {
    run_sql "UPDATE feature_toggles SET is_enabled = $2 WHERE feature_key = '$1';"
    echo "[$(date '+%H:%M:%S')] Toggled $1 to $([ $2 -eq 1 ] && echo 'ON' || echo 'OFF')"
}

# Event Timeline
echo "BankSploit CTF Event - Auto Toggle Started"

# T+0:00 - Event starts
echo "Waiting until event start..."
while [ $(date +%H:%M) != "14:00" ]; do
    sleep 10
done
toggle_feature "training_lab" 1
echo "Event Started! 🎉"

# T+1:00 - Disable swagger docs (force discovery)
echo "Waiting 1 hour..."
sleep 3600
toggle_feature "swagger_docs" 0
toggle_feature "leaderboard" 0
echo "Challenge mode: Swagger docs hidden, leaderboard hidden"

# T+2:00 - Release hints
sleep 3600
echo "Hints are now available! Check /training"

# T+3:00 - Show leaderboard
sleep 3600
toggle_feature "leaderboard" 1
echo "Leaderboard is now visible!"

# T+4:00 - Freeze scores
sleep 3600
toggle_feature "flag_submission" 0
echo "Event ending soon - Scores frozen!"

echo "Event automation complete"
```

**Usage:**
```bash
chmod +x event-toggle.sh
./event-toggle.sh
```

### Example 2: Monitor Feature Changes

```bash
#!/bin/bash
# monitor-features.sh - Watch for feature changes

DB_USER="banksploit"
DB_PASS="banksploit_pass"
DB_NAME="banksploit"

echo "Monitoring feature toggles (refreshes every 30 seconds)..."
echo "Press Ctrl+C to stop"

while true; do
    clear
    echo "=== Feature Toggles Status ==="
    echo "Last updated at: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
    
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
        "SELECT feature_key, is_enabled, updated_at FROM feature_toggles ORDER BY feature_key;"
    
    sleep 30
done
```

**Usage:**
```bash
chmod +x monitor-features.sh
./monitor-features.sh
```

### Example 3: Bulk Reset Before New Event

```bash
#!/bin/bash
# reset-features.sh - Reset all features to defaults

DB_USER="banksploit"
DB_PASS="banksploit_pass"
DB_NAME="banksploit"

echo "Resetting all features to defaults..."

mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << EOF
UPDATE feature_toggles SET is_enabled = 1;
UPDATE feature_toggles SET is_enabled = 0 WHERE feature_key = 'debug_mode';
EOF

echo "✓ All features reset to defaults:"
echo "  ✓ training_lab ON"
echo "  ✓ leaderboard ON"
echo "  ✓ flag_submission ON"
echo "  ✓ code_inspector ON"
echo "  ✓ swagger_docs ON"
echo "  � debug_mode OFF"
echo "  ✓ otp_display ON"
echo "  ✓ request_logging ON"
```

**Usage:**
```bash
chmod +x reset-features.sh
./reset-features.sh
```

## Integration Examples

### Integration 1: Check Feature in PHP Middleware

```php
// In src/index.php
$requestPath = $_SERVER['REQUEST_URI'] ?? '/';

// Define feature-to-path mapping
$featureGuards = [
    'training_lab' => ['/lab', '/training', '/training/code', '/inspector'],
    'leaderboard' => ['/leaderboard'],
    'swagger_docs' => ['/swagger-ui'],
];

// Check each guarded feature
foreach ($featureGuards as $feature => $paths) {
    foreach ($paths as $path) {
        if (strpos($requestPath, $path) === 0) {
            if (!is_feature_enabled($feature)) {
                http_response_code(403);
                exit("Feature is disabled");
            }
        }
    }
}
```

### Integration 2: Show Feature Status in Admin Dashboard

```php
// In src-superadmin/pages/dashboard.php
$features = get_all_features();
$enabledCount = array_sum(array_column($features, 'is_enabled'));
$totalCount = count($features);

echo "Features Enabled: <strong>$enabledCount / $totalCount</strong>";

// Show warning if debug_mode is ON
$debugMode = array_find($features, fn($f) => $f['feature_key'] === 'debug_mode');
if ($debugMode && $debugMode['is_enabled']) {
    echo '<div class="alert alert-warning">⚠️ Debug Mode is ON - disable when not needed</div>';
}
```

## Testing

### Unit Test Example (PHPUnit)

```php
class FeatureToggleTest extends TestCase
{
    public function test_is_feature_enabled_returns_true_for_enabled_feature()
    {
        // Assuming training_lab is enabled in test DB
        $this->assertTrue(is_feature_enabled('training_lab'));
    }

    public function test_is_feature_enabled_returns_false_for_disabled_feature()
    {
        // Assuming debug_mode is disabled in test DB
        $this->assertFalse(is_feature_enabled('debug_mode'));
    }

    public function test_get_all_features_returns_array()
    {
        $features = get_all_features();
        $this->assertIsArray($features);
        $this->assertCount(8, $features);
    }

    public function test_toggle_api_requires_csrf_token()
    {
        $response = $this->post('/features', [
            'action' => 'toggle',
            'feature' => 'training_lab'
            // No csrf_token
        ]);

        $response->assertStatus(200)
                 ->assertJson(['ok' => false, 'error' => 'CSRF token invalid.']);
    }
}
```

## Troubleshooting & Debug

### Check if middleware is working

```bash
# Should return 403 if training_lab is disabled
curl -v http://localhost:9443/training 2>&1 | grep "HTTP/"

# Should return 200 if training_lab is enabled
curl -v http://localhost:9443/training 2>&1 | grep "HTTP/"
```

### Check database connection

```php
try {
    $features = get_all_features();
    echo "Database connection: OK";
    echo "Features loaded: " . count($features);
} catch (Exception $e) {
    echo "Database connection: FAILED";
    echo "Error: " . $e->getMessage();
}
```

### Enable debug logging

```php
// Add to src/index.php for debugging
error_log("Feature check for training_lab: " . (is_feature_enabled('training_lab') ? 'enabled' : 'disabled'));
```

---

**End of API Examples**

Print this for reference when integrating feature toggles with custom scripts!
