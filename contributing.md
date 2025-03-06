Contributing
============

Before proposing a pull request, please check the following:

* Your code should follow the [PSR-12 coding standard](https://www.php-fig.org/psr/psr-12/). Use `composer lint` to check for violations and `composer lint:fix` to automatically fix them.
* If you commit a new feature, be prepared to help maintaining it. Watch the project on GitHub, and please comment on issues or PRs regarding the feature you contributed.
* You should test your feature well.
* You should run `php build` and check the PHAR file works before submitting your pull request. You may need to change `phar.readonly` php.ini setting to `0` or run the command as `php -d phar.readonly=0 build`.

Once your code is merged, it is available for free to everybody under the MIT License. Publishing your pull request on the PHPloy GitHub repository means that you agree with this license for your contribution.

Thank you for your contribution! PHPloy wouldn't be so great without you.

## Dependencies
The project requires PHP 8.2 or higher. Dependencies are managed through Composer:
```bash
composer install
```

## Testing

The project uses Pest PHP testing framework with Docker-based integration tests. The test environment includes:
- FTP server for FTP protocol testing
- SFTP server for SFTP protocol testing
- PHP test container for running the tests

### Executing tests

1. Install [Docker Engine](https://docs.docker.com/engine/installation/) and Docker Compose

2. Run the tests using one of these methods:

   ```bash
   # Run full test suite (starts Docker, runs tests, stops Docker)
   composer test

   # Or run services separately for development:
   composer test:up      # Start Docker containers
   composer test:exec    # Run tests
   composer test:down    # Stop Docker containers
   ```

### Writing Tests

Tests are written using Pest PHP, a delightful testing framework built on top of PHPUnit. Example test structure:

```php
test('creates revision file on first deployment', function () {
    // 1. Setup test repository and config
    $testDir = '/tmp/phploy-test-' . uniqid();
    mkdir($testDir);
    chdir($testDir);
    
    // 2. Run PHPloy
    $output = shell_exec('php /app/bin/phploy --fresh 2>&1');
    
    // 3. Assert results
    expect($ftpConnection->has('.revision'))->toBeTrue();
    
    // Cleanup
    shell_exec('rm -rf ' . $testDir);
});
```

### Code Quality

The project uses several tools to maintain code quality:

```bash
# Check PSR-12 coding standards
composer lint

# Fix PSR-12 violations automatically
composer lint:fix

# Run static analysis
composer analyze
```
