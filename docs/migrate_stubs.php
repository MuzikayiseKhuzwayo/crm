<?php

$targetDir = __DIR__ . '/vendor/orchestra/testbench-core/laravel/database/migrations/';

// Clean up any double extensions
foreach (glob($targetDir . '*.php.php') as $badFile) {
    @unlink($badFile);
}

// Copy stubs with proper timestamps and single .php extension
$files = glob(__DIR__ . '/database/migrations/*.stub');
sort($files);

$timestamp = 20240101000100;
foreach ($files as $file) {
    $basename = basename($file, '.stub');
    // Ensure filename starts with a timestamp if it doesn't already
    if (!preg_match('/^\d{4}_\d{2}_\d{2}_/', $basename)) {
        $basename = date('Y_m_d_His', $timestamp++) . '_' . $basename;
    }
    $destination = $targetDir . $basename . '.php';
    copy($file, $destination);
}

echo "All migration stubs copied cleanly with timestamps.\n";
