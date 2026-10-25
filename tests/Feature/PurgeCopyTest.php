<?php

use Banago\PHPloy\Connection;

test('purge-before[] removes untracked remote files before deploy', function () {
    $testDir = '/tmp/phploy-purge-before-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    mkdir('dist');
    file_put_contents('dist/app.js', 'console.log("app");');
    file_put_contents('.gitignore', 'phploy.ini');
    shell_exec('git add -A && git commit -m "Initial commit"');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    purge-before[] = "dist/"
INI;
    file_put_contents('phploy.ini', $phployConfig);

    // First deploy
    shell_exec('php /app/bin/phploy --fresh 2>&1');
    sleep(2);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ]);

    expect($ftp->has('dist/app.js'))->toBeTrue();

    // Simulate a stale file on the server that git does not track
    $ftp->put('dist/stale.txt', 'stale');

    // Second deploy (fresh forces re-upload; purge-before runs first)
    shell_exec('php /app/bin/phploy --fresh 2>&1');
    sleep(2);

    expect($ftp->has('dist/app.js'))->toBeTrue();       // re-uploaded after purge
    expect($ftp->has('dist/stale.txt'))->toBeFalse();   // purged before deploy

    shell_exec('rm -rf ' . $testDir);
});

test('copy[] copies a remote directory from -> to', function () {
    $testDir = '/tmp/phploy-copy-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    mkdir('public');
    file_put_contents('public/app.js', 'console.log("app");');
    file_put_contents('.gitignore', 'phploy.ini');
    shell_exec('git add -A && git commit -m "Initial commit"');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    copy[] = "public->www"
INI;
    file_put_contents('phploy.ini', $phployConfig);

    shell_exec('php /app/bin/phploy --fresh 2>&1');
    sleep(2);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ]);

    expect($ftp->has('public/app.js'))->toBeTrue();   // original uploaded
    expect($ftp->has('www/app.js'))->toBeTrue();      // copied

    shell_exec('rm -rf ' . $testDir);
});
