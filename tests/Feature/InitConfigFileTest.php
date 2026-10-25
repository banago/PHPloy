<?php

test('create phploy.ini file on init', function () {
    $cwd = getcwd();
    // 1. Setup test repository
    $testDir = '/tmp/phploy-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    // 2. Run phploy command with init argument and capture output
    $output = shell_exec('php ' . $cwd . '/bin/phploy --init --debug 2>&1');
    echo "PHPloy output: " . PHP_EOL . $output . PHP_EOL;

    // 3. Verify phploy.ini file exists
    expect(file_exists($testDir . '/phploy.ini'))->toBeTrue();

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
