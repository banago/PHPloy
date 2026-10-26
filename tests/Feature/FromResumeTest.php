<?php

use Banago\PHPloy\Connection;

test('--from resumes uploads at the given 1-based index', function () {
    $testDir = '/tmp/phploy-from-test-' . uniqid();
    $remotePath = '/from-resume-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');

    // Three files so `git ls-files` yields a deterministic alphabetical order:
    // a.txt (index 1), b.txt (index 2), c.txt (index 3).
    file_put_contents('a.txt', 'aaa');
    file_put_contents('b.txt', 'bbb');
    file_put_contents('c.txt', 'ccc');
    shell_exec('git add -A && git commit -m "Initial commit"');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = {$remotePath}
INI;
    file_put_contents('phploy.ini', $phployConfig);

    // Resume from index 2: a.txt must be skipped, b.txt and c.txt uploaded.
    shell_exec('php /app/bin/phploy --fresh --force --from 2 2>&1');
    sleep(2);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => $remotePath,
        'timeout' => 30,
    ]);

    expect($ftp->has('a.txt'))->toBeFalse();    // skipped by --from 2
    expect($ftp->has('b.txt'))->toBeTrue();     // resumed from here
    expect($ftp->has('c.txt'))->toBeTrue();     // and continued
    expect($ftp->has('.revision'))->toBeTrue(); // revision still written after resume

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});

test('--from beyond the file count uploads nothing and does not advance revision', function () {
    $testDir = '/tmp/phploy-from-over-test-' . uniqid();
    $remotePath = '/from-over-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');

    file_put_contents('a.txt', 'aaa');
    shell_exec('git add -A && git commit -m "Initial commit"');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = {$remotePath}
INI;
    file_put_contents('phploy.ini', $phployConfig);

    shell_exec('php /app/bin/phploy --fresh --force --from 5 2>&1');
    sleep(2);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => $remotePath,
        'timeout' => 30,
    ]);

    expect($ftp->has('a.txt'))->toBeFalse();     // nothing uploaded
    expect($ftp->has('.revision'))->toBeFalse(); // and revision not advanced

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
