<?php

use Banago\PHPloy\Connection;

test('base option respects incremental deploys and deletes', function () {
    // 1. Setup test repository
    $testDir = '/tmp/phploy-base-inc-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    mkdir('wordpress');
    file_put_contents('wordpress/index.php', '<?php echo "hi";');
    file_put_contents('wordpress/to-delete.txt', 'delete me');
    file_put_contents('composer.json', '{}');
    shell_exec('git add -A && git commit -m "Initial commit"');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    base = wordpress/
INI;
    file_put_contents('phploy.ini', $phployConfig);
    file_put_contents('.gitignore', 'phploy.ini');
    shell_exec('git add .gitignore && git commit -m "gitignore"');

    // First deploy
    shell_exec('php /app/bin/phploy --fresh 2>&1');
    sleep(2);

    $ftpServer = [
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ];
    $ftp = new Connection($ftpServer);

    expect($ftp->has('index.php'))->toBeTrue();
    expect($ftp->has('to-delete.txt'))->toBeTrue();
    expect($ftp->has('composer.json'))->toBeFalse();

    // Second: change a base file, change a non-base file, delete a base file
    file_put_contents('wordpress/index.php', '<?php echo "updated";');
    unlink('wordpress/to-delete.txt');
    file_put_contents('composer.json', '{"changed":true}');
    shell_exec('git add -A && git commit -m "Second commit"');

    shell_exec('php /app/bin/phploy 2>&1');
    sleep(2);

    expect($ftp->read('index.php'))->toContain('updated');
    expect($ftp->has('to-delete.txt'))->toBeFalse();       // base delete honoured
    expect($ftp->has('composer.json'))->toBeFalse();       // non-base change not deployed

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
