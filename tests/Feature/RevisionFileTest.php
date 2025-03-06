<?php

use Banago\PHPloy\Connection;

test('creates revision file on first deployment', function () {
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
    $commitHash = trim(shell_exec('git rev-parse HEAD'));

    // 2. Create and configure phploy.ini
    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
INI;
    file_put_contents('phploy.ini', $phployConfig);

    // Add phploy.ini to .gitignore
    file_put_contents('.gitignore', 'phploy.ini');

    // 3. Run phploy command with full path and capture output
    $output = shell_exec('php /app/bin/phploy --fresh --debug 2>&1');
    echo "PHPloy output: " . $output . PHP_EOL;

    // Give it time to finish deployment
    sleep(5);

    // 4. Verify .revision file exists and contains correct commit hash
    $ftpServer = [
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ];
    $ftpConnection = new Connection($ftpServer);
    expect($ftpConnection->has('.revision'))->toBeTrue();

    $revisionContent = $ftpConnection->read('.revision');
    expect($revisionContent)->toBe($commitHash);

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});

test('updates revision file on subsequent deployments', function () {
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
    $firstCommitHash = trim(shell_exec('git rev-parse HEAD'));

    // 2. Create and configure phploy.ini
    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
INI;
    file_put_contents('phploy.ini', $phployConfig);

    // Add phploy.ini to .gitignore
    file_put_contents('.gitignore', 'phploy.ini');

    // 3. First deployment
    $output = shell_exec('php /app/bin/phploy --fresh --debug 2>&1');
    echo "First deployment output: " . $output . PHP_EOL;
    sleep(2);

    // 4. Make second commit
    file_put_contents('test.txt', 'Updated content!');
    shell_exec('git add test.txt');
    shell_exec('git commit -m "Second commit"');
    $secondCommitHash = trim(shell_exec('git rev-parse HEAD'));

    // 5. Second deployment
    $output = shell_exec('php /app/bin/phploy --debug 2>&1');
    echo "Second deployment output: " . $output . PHP_EOL;
    sleep(5);

    // 6. Verify .revision file is updated
    $ftpServer = [
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ];
    $ftpConnection = new Connection($ftpServer);
    expect($ftpConnection->has('.revision'))->toBeTrue();

    $revisionContent = $ftpConnection->read('.revision');
    expect($revisionContent)->toBe($secondCommitHash);

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
