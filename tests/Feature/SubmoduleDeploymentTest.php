<?php

use Banago\PHPloy\Connection;

test('deploys submodule contents alongside the main repo', function () {
    $uniq    = uniqid();
    $subName = 'sub_' . $uniq;
    $testDir = '/tmp/phploy-submodule-' . $uniq;
    $smDir   = $testDir . '-sm';
    mkdir($testDir);
    mkdir($smDir);

    // 1. Submodule source repo
    shell_exec("cd $smDir && git init && git config user.email s@s && git config user.name s");
    file_put_contents($smDir . '/sub-lib.txt', 'submodule-content');
    shell_exec("cd $smDir && git add -A && git commit -m sub1");

    // 2. Main repo with the submodule added
    chdir($testDir);
    shell_exec('git init && git config user.email m@m && git config user.name m');
    file_put_contents('main.txt', 'main-content');
    shell_exec('git add -A && git commit -m main1');
    shell_exec("git -c protocol.file.allow=always submodule add $smDir $subName 2>/dev/null");
    shell_exec('git add -A && git commit -m "add submodule"');

    file_put_contents('phploy.ini', <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
INI);

    shell_exec('php /app/bin/phploy --submodules --fresh 2>&1');
    sleep(2);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ]);

    // Main repo file deployed
    expect($ftp->has('main.txt'))->toBeTrue();

    // Submodule is a real directory (not a gitlink uploaded as an empty file)
    expect($ftp->directoryExists($subName))->toBeTrue();

    // Submodule content deployed under its own directory
    expect($ftp->has($subName . '/sub-lib.txt'))->toBeTrue();
    expect($ftp->read($subName . '/sub-lib.txt'))->toBe('submodule-content');

    // Cleanup
    shell_exec('rm -rf ' . $testDir . ' ' . $smDir);
});
