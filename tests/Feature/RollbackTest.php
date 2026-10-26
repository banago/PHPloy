<?php

use Banago\PHPloy\Connection;
use Banago\PHPloy\Git;

test('recentCommits() lists commits newest-first with full SHA and subject', function () {
    $testDir = '/tmp/phploy-recent-commits-' . uniqid();
    mkdir($testDir);
    shell_exec("cd $testDir && git init -q && git config user.email t@t && git config user.name t");
    file_put_contents($testDir . '/f.txt', 'x');
    shell_exec("cd $testDir && git add -A && git commit -qm 'first'");
    file_put_contents($testDir . '/f.txt', 'y');
    shell_exec("cd $testDir && git add -A && git commit -qm 'second'");

    $git = new Git($testDir);
    $commits = $git->recentCommits();

    expect(count($commits))->toBe(2);
    expect($commits[0]['subject'])->toBe('second');
    expect($commits[1]['subject'])->toBe('first');
    expect($commits[0]['sha'])->toMatch('/^[0-9a-f]{40}$/');

    shell_exec('rm -rf ' . $testDir);
});

test('rollback to a previous commit reverts the deployed file', function () {
    $testDir = '/tmp/phploy-rollback-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init && git config user.email t@t && git config user.name t');
    file_put_contents('file.txt', 'v1');
    file_put_contents('.gitignore', 'phploy.ini');
    shell_exec('git add -A && git commit -m "v1"');
    $sha1 = trim(shell_exec('git rev-parse HEAD'));

    file_put_contents('phploy.ini', <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
INI);

    // Deploy v1 (fresh resets any stale remote revision)
    shell_exec('php /app/bin/phploy --fresh 2>&1');
    sleep(1);

    // Deploy v2
    file_put_contents('file.txt', 'v2');
    shell_exec('git add -A && git commit -m "v2"');
    shell_exec('php /app/bin/phploy 2>&1');
    sleep(1);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ]);
    expect($ftp->read('file.txt'))->toBe('v2');

    // Rollback to v1 by explicit SHA
    shell_exec("php /app/bin/phploy --rollback $sha1 2>&1");
    sleep(1);

    expect($ftp->read('file.txt'))->toBe('v1');

    shell_exec('rm -rf ' . $testDir);
});
