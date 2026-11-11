<?php
/**
 * Verification script for Sandbox command injection fixes
 */

require_once __DIR__ . '/src/core/Sandbox.php';

echo "=== Sandbox Verification ===\n\n";

// Test 1: Get temp directory
$tmpDir = sys_get_temp_dir();
echo "System temp directory: " . $tmpDir . "\n";

// Test 2: Create a test flag file
$testUserId = 99;
$testFlagFile = $tmpDir . DIRECTORY_SEPARATOR . "flag_g9_{$testUserId}.txt";
$testFlagContent = "TEST_FLAG_FOR_USER_" . $testUserId;

@file_put_contents($testFlagFile, $testFlagContent . "\n");
echo "Created test flag file: " . $testFlagFile . "\n";
echo "File exists: " . (file_exists($testFlagFile) ? "YES" : "NO") . "\n";

// Test 3: Test Sandbox with Unix /tmp path
echo "\n--- Test 3: Sandbox ls /tmp ---\n";
$sandbox = new Sandbox(null, $testUserId);
$output = $sandbox->exec("ls /tmp");
echo "Output:\n" . $output;
if (strpos($output, "flag_g9_{$testUserId}.txt") !== false) {
    echo "✓ PASS: Flag file found in ls output\n";
} else {
    echo "✗ FAIL: Flag file NOT found in ls output\n";
}

// Test 4: Test Sandbox cat own flag
echo "\n--- Test 4: Sandbox cat /tmp/flag_g9_{$testUserId}.txt ---\n";
$output = $sandbox->exec("cat /tmp/flag_g9_{$testUserId}.txt");
echo "Output:\n" . $output;
if (strpos($output, $testFlagContent) !== false) {
    echo "✓ PASS: Flag content retrieved\n";
} else {
    echo "✗ FAIL: Flag content NOT retrieved\n";
}

// Test 5: Test Sandbox permission denied on other user's flag
echo "\n--- Test 5: Sandbox cat /tmp/flag_g9_100.txt (different user) ---\n";
$otherFlagFile = $tmpDir . DIRECTORY_SEPARATOR . "flag_g9_100.txt";
@file_put_contents($otherFlagFile, "OTHER_USER_FLAG\n");

$output = $sandbox->exec("cat /tmp/flag_g9_100.txt");
echo "Output:\n" . $output;
if (strpos($output, "Permission denied") !== false) {
    echo "✓ PASS: Permission denied for other user's flag\n";
} else {
    echo "✗ FAIL: Permission NOT denied for other user's flag\n";
}

// Test 6: Test id command
echo "\n--- Test 6: Sandbox id command ---\n";
$output = $sandbox->exec("id");
echo "Output:\n" . $output;
if (strpos($output, "uid={$testUserId}") !== false) {
    echo "✓ PASS: User ID correct in id output\n";
} else {
    echo "✗ FAIL: User ID NOT correct\n";
}

// Cleanup
@unlink($testFlagFile);
@unlink($otherFlagFile);
echo "\n=== Verification Complete ===\n";
?>
