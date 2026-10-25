<?php

namespace Banago\PHPloy;

use Banago\PHPloy\Traits\DebugTrait;

/**
 * Handles configuration from phploy.ini and environment variables
 */
class Config
{
    use DebugTrait;

    /**
     * @var string
     */
    protected $iniFile = 'phploy.ini';

    /**
     * @var string
     */
    protected $passFile = '.phploy';

    /**
     * @var array
     */
    protected $servers = [];

    /**
     * @var array
     */
    protected $globalFilesToExclude = [
        '.gitignore',
        '.gitmodules',
    ];

    /**
     * @var Cli
     */
    protected $cli;

    /**
     * Constructor.
     */
    public function __construct(Cli $cli)
    {
        $this->cli = $cli;
        $this->loadConfig();
    }

    /**
     * Load configuration from phploy.ini
     */
    protected function loadConfig(): void
    {
        $iniFile = getcwd() . DIRECTORY_SEPARATOR . $this->iniFile;

        if (!file_exists($iniFile)) {
            throw new \Exception("'{$this->iniFile}' does not exist.");
        }

        $config = parse_ini_file($iniFile, true);
        if (!$config) {
            throw new \Exception("'{$this->iniFile}' is not a valid .ini file.");
        }

        // Get shared config if exists
        $shared = $config['*'] ?? [];
        unset($config['*']);

        // Process each server
        foreach ($config as $name => $options) {
            if (! is_array($options)) {
                throw new \Exception("No options could be parsed. Please name your server on your '{$this->iniFile}'.");
            }
            $this->servers[$name] = $this->processServerConfig($name, $options, $shared);
        }
    }

    /**
     * Process server configuration
     */
    protected function processServerConfig(string $name, array $options, array $shared): array
    {
        // Start with default values
        $config = [
            'scheme' => 'ftp',
            'host' => '',
            'user' => '',
            'pass' => '',
            'path' => '/',
            'privkey' => '',
            'port' => null,
            'passive' => null,
            'timeout' => null,
            'ssl' => false,
            'visibility' => 'public',
            'permPublic' => 0774,
            'permPrivate' => 0700,
            'permissions' => null,
            'directoryPerm' => 0755,
            'branch' => '',
            'include' => [],
            'exclude' => array_merge($this->globalFilesToExclude, [$this->iniFile]),
            'copy' => [],
            'purge' => [],
            'purge-before' => [],
            'pre-deploy' => [],
            'post-deploy' => [],
            'pre-deploy-remote' => [],
            'post-deploy-remote' => [],
        ];

        // Merge shared config
        foreach ($shared as $key => $value) {
            if (isset($config[$key]) && is_array($config[$key])) {
                $config[$key] = array_merge($config[$key], (array)$value);
            } else {
                $config[$key] = $value;
            }
        }

        // Merge server specific config
        foreach ($options as $key => $value) {
            if (isset($config[$key]) && is_array($config[$key])) {
                $config[$key] = array_merge($config[$key], (array)$value);
            } else {
                $config[$key] = $value;
            }
        }

        // Merge parsed quickmode details
        if (isset($options['quickmode'])) {
            $parsed_url = parse_url($options['quickmode']);
            if ($parsed_url === false) {
                throw new \Exception('Your quickmode URL cannot be parsed. Please fix it.');
            }
            $config = array_merge($config, $parsed_url);
        }

        // Handle environment variables
        $this->processEnvironmentVariables($config);

        // Handle password file
        $this->processPasswordFile($name, $config);

        return $config;
    }

    /**
     * Process environment variables
     */
    protected function processEnvironmentVariables(array &$config): void
    {
        $envVars = [
            'PHPLOY_HOST' => 'host',
            'PHPLOY_PORT' => 'port',
            'PHPLOY_USER' => 'user',
            'PHPLOY_PASS' => 'pass',
            'PHPLOY_PATH' => 'path',
            'PHPLOY_PRIVKEY' => 'privkey',
        ];

        foreach ($envVars as $env => $key) {
            $value = getenv($env);
            if ($value !== false && empty($config[$key])) {
                $config[$key] = $value;
            }
        }
    }

    /**
     * Process password file
     */
    protected function processPasswordFile(string $name, array &$config): void
    {
        $passFile = getcwd() . DIRECTORY_SEPARATOR . $this->passFile;

        if (!file_exists($passFile)) {
            return;
        }

        $passConfig = parse_ini_file($passFile, true);
        if (!$passConfig || !isset($passConfig[$name])) {
            return;
        }

        if (empty($config['user']) && isset($passConfig[$name]['user'])) {
            $config['user'] = $passConfig[$name]['user'];
        }

        if (empty($config['pass']) && isset($passConfig[$name]['pass'])) {
            $config['pass'] = $passConfig[$name]['pass'];
        }

        if (empty($config['privkey']) && isset($passConfig[$name]['privkey'])) {
            $config['privkey'] = $passConfig[$name]['privkey'];
        }

        // Handle legacy 'password' key
        if (isset($passConfig[$name]['password'])) {
            throw new \Exception('Please rename password to pass in ' . $this->passFile);
        }
    }

    /**
     * Get all servers configuration
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * Get specific server configuration
     */
    public function getServer(string $name): ?array
    {
        return $this->servers[$name] ?? null;
    }

    /**
     * Create sample config file
     */
    public function createSampleConfig(): void
    {
        $iniFile = getcwd() . DIRECTORY_SEPARATOR . $this->iniFile;

        if (file_exists($iniFile)) {
            $this->cli->info("\nphploy.ini file already exists.\n");
            return;
        }

        $sample = <<<INI
; This is a sample configuration file
; Comments start with ';', as in php.ini
; Copy this file as phploy.ini and modify as needed

[staging]
scheme = sftp
host = example.com
path = /path/to/deployment
user = username
pass = password
port = 22
; privkey = path/to/or/contents/of/privatekey
passive = true
ssl = false
timeout = 30

[production]
quickmode = sftp://username:password@example.com:22/path/to/deployment

[*]
; Shared configuration for all servers
exclude[] = "vendor"
include[] = "dist"
INI;

        if (file_put_contents($iniFile, $sample)) {
            $this->cli->info("\nSample phploy.ini file created.\n");
        }
    }
}
