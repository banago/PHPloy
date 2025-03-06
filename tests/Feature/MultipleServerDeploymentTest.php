<?php

use Banago\PHPloy\Connection;

test('deploys only to default server when no --all flag', function () {
    // 1. Setup test repository
    $testDir = '/tmp/phploy-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    // Initialize git and create test file
    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    file_put_contents('test.txt', 'Hello PHPloy!');
    shell_exec('git add test.txt');
    shell_exec('git commit -m "Initial commit"');

    // 2. Create and configure phploy.ini with default server
    $phployConfig = <<<INI
[default]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    
[sftp]
    scheme = sftp
    host = sftp-server
    user = testuser
    pass = testpass
    path = /upload
INI;
    file_put_contents('phploy.ini', $phployConfig);

    // Add phploy.ini to .gitignore
    file_put_contents('.gitignore', 'phploy.ini');

    // 3. Run phploy command with full path and capture output
    $output = shell_exec('php /app/bin/phploy --fresh --debug 2>&1');
    echo "PHPloy output: " . $output . PHP_EOL;

    // Give it time to finish deployment
    sleep(2);

    // 4. Verify deployment - only default server should have the file
    $ftpServer = [
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ];
    $ftpConnection = new Connection($ftpServer);
    expect($ftpConnection->has('test.txt'))->toBeTrue();

    $sftpServer = [
        'scheme' => 'sftp',
        'host' => 'sftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/upload',
        'timeout' => 30
    ];
    $sftpConnection = new Connection($sftpServer);
    expect($sftpConnection->has('test.txt'))->toBeFalse();

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});

test('deploys to all servers when --all flag is used', function () {
    // 1. Setup test repository
    $testDir = '/tmp/phploy-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    // Initialize git and create test file
    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    file_put_contents('test.txt', 'Hello PHPloy!');
    shell_exec('git add test.txt');
    shell_exec('git commit -m "Initial commit"');

    // 2. Create and configure phploy.ini with default server
    $phployConfig = <<<INI
[default]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    
[sftp]
    scheme = sftp
    host = sftp-server
    user = testuser
    pass = testpass
    path = /upload
INI;
    file_put_contents('phploy.ini', $phployConfig);

    // Add phploy.ini to .gitignore
    file_put_contents('.gitignore', 'phploy.ini');

    // 3. Run phploy command with full path and capture output
    $output = shell_exec('php /app/bin/phploy --all --fresh --debug 2>&1');
    echo "PHPloy output: " . $output . PHP_EOL;

    // Give it time to finish deployment
    sleep(2);

    // 4. Verify deployment
    $ftpServer = [
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ];
    $ftpConnection = new Connection($ftpServer);
    expect($ftpConnection->has('test.txt'))->toBeTrue();

    $sftpServer = [
        'scheme' => 'sftp',
        'host' => 'sftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/upload',
        'timeout' => 30
    ];
    $sftpConnection = new Connection($sftpServer);
    expect($sftpConnection->has('test.txt'))->toBeTrue();

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
