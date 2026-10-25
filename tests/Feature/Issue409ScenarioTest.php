<?php

use Banago\PHPloy\Connection;

test('409 scenario: base, exclude, include and logger all take effect', function () {
    $testDir = '/tmp/phploy-409-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);

    shell_exec('git init');
    shell_exec('git config user.email "test@phploy.org"');
    shell_exec('git config user.name "PHPloy Test"');
    mkdir('src');
    mkdir('vendor');
    file_put_contents('src/index.php', '<?php echo "hi";');
    file_put_contents('src/style.scss', '$color: red;');        // should be excluded
    file_put_contents('composer.json', '{}');                     // outside base
    file_put_contents('vendor/lib.php', '<?php // vendor');       // gitignored + included
    file_put_contents('.gitignore', "phploy.ini\nvendor/\n");
    shell_exec('git add -A && git commit -m "Initial commit"');

    // Replicate issue #409's config (adjusted for the test FTP host)
    $phployConfig = <<<INI
[hassio]
    scheme = ftp
    host = ftp-server
    user = testuser
    pass = testpass
    path = /
    base = "src/"
    logger = on
    exclude[] = ".gitignore"
    exclude[] = "src/*.scss"
    exclude[] = "composer*"
    include[] = "vendor/"
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

    // base: src/index.php deployed as index.php (prefix stripped)
    expect($ftp->has('index.php'))->toBeTrue();
    expect($ftp->has('src/index.php'))->toBeFalse();
    // base: file outside base is not deployed
    expect($ftp->has('composer.json'))->toBeFalse();
    // exclude[]: src/*.scss excluded
    expect($ftp->has('style.scss'))->toBeFalse();
    // include[]: gitignored vendor/ pulled in, at a clean (single-slash) path
    expect($ftp->has('vendor/lib.php'))->toBeTrue();
    // logger: phploy.log written locally
    expect(file_exists($testDir . '/phploy.log'))->toBeTrue();

    shell_exec('rm -rf ' . $testDir);
});
