<?php

use Banago\PHPloy\Connection;

test('conditional include[] deploys target only when the source changed', function () {
    $testDir = '/tmp/phploy-conditional-include-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    mkdir('dist');
    mkdir('src');
    file_put_contents('src/style.css', 'body { color: red; }');      // tracked source
    file_put_contents('other.txt', 'v1');                              // tracked, unrelated
    file_put_contents('dist/app.min.js', '// built');                  // gitignored build artifact
    file_put_contents('.gitignore', "phploy.ini\ndist/\n");
    shell_exec('git add -A && git commit -m "Initial commit"');

    $phployConfig = <<<INI
[ftp]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    include[] = "dist/app.min.js:src/style.css"
INI;
    file_put_contents('phploy.ini', $phployConfig);

    $ftp = new Connection([
        'scheme' => 'ftp',
        'host' => 'ftp-server',
        'user' => 'testuser',
        'pass' => 'testpass',
        'path' => '/',
        'timeout' => 30
    ]);

    // 1. Fresh deploy: everything changed, so src/style.css is in the diff -> include triggers
    shell_exec('php /app/bin/phploy --fresh 2>&1');
    sleep(2);
    expect($ftp->has('dist/app.min.js'))->toBeTrue();

    // 2. Remove the artifact and deploy with an UNRELATED change -> condition not met, not re-included
    $ftp->delete('dist/app.min.js');
    file_put_contents('other.txt', 'v2');
    shell_exec('git add other.txt && git commit -m "unrelated"');
    shell_exec('php /app/bin/phploy 2>&1');
    sleep(2);
    expect($ftp->has('dist/app.min.js'))->toBeFalse();

    // 3. Change the source -> condition met, artifact re-included
    file_put_contents('src/style.css', 'body { color: blue; }');
    shell_exec('git add src/style.css && git commit -m "source change"');
    shell_exec('php /app/bin/phploy 2>&1');
    sleep(2);
    expect($ftp->has('dist/app.min.js'))->toBeTrue();

    shell_exec('rm -rf ' . $testDir);
});
