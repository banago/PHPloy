<?php

use Banago\PHPloy\Connection;

test('large files stream to the server without exhausting memory', function () {
    $testDir = '/tmp/phploy-large-stream-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');

    $size = 32 * 1024 * 1024; // 32 MB
    file_put_contents('big.bin', str_repeat('A', $size));
    file_put_contents('small.txt', 'hello');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
INI;
    file_put_contents('phploy.ini', $phployConfig);
    file_put_contents('.gitignore', 'phploy.ini');
    shell_exec('git add -A && git commit -m "Initial commit"');

    // memory_limit=16M is below the 32M file, so a slurp-based upload would fatal;
    // streaming must get it through.
    $output = shell_exec('php -d memory_limit=16M /app/bin/phploy --fresh 2>&1');
    expect($output)->not->toContain('Allowed memory size');

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30,
    ]);

    expect($ftp->has('big.bin'))->toBeTrue();
    expect($ftp->server->fileSize('big.bin'))->toBe($size);
    expect($ftp->has('small.txt'))->toBeTrue();

    // Cleanup remote + local state so sibling tests see a fresh root.
    $ftp->delete('big.bin');
    $ftp->delete('small.txt');
    $ftp->delete('.revision');
    shell_exec('rm -rf ' . $testDir);
});
