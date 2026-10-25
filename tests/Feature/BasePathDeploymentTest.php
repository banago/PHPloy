<?php

use Banago\PHPloy\Connection;

test('base option restricts deploy and strips the base prefix', function () {
    // 1. Setup test repository
    $testDir = '/tmp/phploy-base-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    // Initialize git and create test files: one inside base, one outside
    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    mkdir('wordpress');
    file_put_contents('wordpress/index.php', '<?php echo "hi";');
    file_put_contents('wordpress/style.css', 'body {}');
    file_put_contents('composer.json', '{}');
    shell_exec('git add -A');
    shell_exec('git commit -m "Initial commit"');

    // 2. Create and configure phploy.ini
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

    // Add phploy.ini to .gitignore
    file_put_contents('.gitignore', 'phploy.ini');
    shell_exec('git add .gitignore && git commit -m "gitignore"');

    // 3. Run phploy command
    $output = shell_exec('php /app/bin/phploy --fresh --debug 2>&1');
    echo "PHPloy output: " . $output . PHP_EOL;

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

    // Base prefix stripped: files land at the remote root
    expect($ftpConnection->has('index.php'))->toBeTrue();
    expect($ftpConnection->has('style.css'))->toBeTrue();

    // Files outside base must NOT be deployed
    expect($ftpConnection->has('composer.json'))->toBeFalse();

    // The base directory name itself must NOT be deployed
    expect($ftpConnection->has('wordpress/index.php'))->toBeFalse();

    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
