<?php
/**
 * Diagnostic script to test Sandbox path resolution and flag file creation
 */

echo "=== SANDBOX DIAGNOSTIC ===\n\n";

// Test 1: System info
echo "1. System Information:\n";
echo "   OS Family: " . PHP_OS_FAMILY . "\n";
echo "   OS: " . PHP_OS . "\n";
echo "   sys_get_temp_dir(): " . sys_get_temp_dir() . "\n";
echo "   is_dir(sys_get_temp_dir()): " . (is_dir(sys_get_temp_dir()) ? "YES" : "NO") . "\n";
echo "   Writable: " . (is_writable(sys_get_temp_dir()) ? "YES" : "NO") . "\n";

// Test 2: Can we create a test file?
echo "\n2. Test File Creation:\n";
$tmpDir = sys_get_temp_dir();
$testFile = $tmpDir . DIRECTORY_SEPARATOR . "test_" . bin2hex(random_bytes(4)) . ".txt";
$created = @file_put_contents($testFile, "test\n");
if ($created !== false) {
    echo "   ✓ Created: $testFile\n";
    echo "   File exists: " . (file_exists($testFile) ? "YES" : "NO") . "\n";
    echo "   Is file: " . (is_file($testFile) ? "YES" : "NO") . "\n";
    echo "   Readable: " . (is_readable($testFile) ? "YES" : "NO") . "\n";
    @unlink($testFile);
    echo "   Deleted: OK\n";
} else {
    echo "   ✗ Failed to create test file\n";
}

// Test 3: Test Sandbox path resolution
echo "\n3. Sandbox Path Resolution Logic:\n";

$testPaths = [
    '/tmp',
    '/tmp/',
    '/tmp/file.txt',
    '/tmp\\file.txt',
    '/TMP/file.txt',  // different case
];

foreach ($testPaths as $testPath) {
    echo "\n   Testing path: '{$testPath}'\n";

    // Simulate the resolvePath logic
    $path = $testPath;
    $root = rtrim(sys_get_temp_dir(), '\\/');

    echo "   Root: '$root'\n";

    // Apply Windows /tmp mapping
    if (PHP_OS_FAMILY === 'Windows') {
        if ($path === '/tmp') {
            $path = $root;
            echo "   Mapped /tmp → root\n";
        } elseif (str_starts_with($path, '/tmp/') || str_starts_with($path, '/tmp\\')) {
            $newPath = $root . DIRECTORY_SEPARATOR . substr($path, 5);
            echo "   Mapped {$testPath} → $newPath\n";
            $path = $newPath;
        }
    }

    // Check if it's absolute
    $isAbsPath = str_starts_with($path, '/') || str_starts_with($path, '\\') ||
                 preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
    echo "   Is absolute: " . ($isAbsPath ? "YES" : "NO") . "\n";

    // If it's absolute, normalize, else make it relative to root
    if ($isAbsPath) {
        echo "   Will normalize as absolute path\n";
    } else {
        echo "   Will be made relative to root\n";
        $path = $root . DIRECTORY_SEPARATOR . $path;
    }

    // After normalization check
    $startsWithRoot = str_starts_with($path, $root);
    echo "   Starts with root: " . ($startsWithRoot ? "YES" : "NO") . "\n";

    if (!$startsWithRoot) {
        echo "   Result: NULL (escapes sandbox)\n";
    } else {
        echo "   Result: {$path}\n";
        echo "   is_dir(): " . (is_dir($path) ? "YES" : "NO") . "\n";
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
?>
