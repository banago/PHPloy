<?php

namespace Banago\PHPloy;

/**
 * PHPloy - A PHP Deployment Tool.
 *
 * @author Baki Goxhaj <banago@gmail.com>
 * @link https://github.com/banago/PHPloy
 * @licence MIT Licence
 * @version 5.0.0-beta
 */
class PHPloy
{
    /**
     * @var string
     */
    protected $version = '5.0.0-beta';

    /**
     * @var Cli
     */
    protected $cli;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Git
     */
    protected $git;

    /**
     * @var Deployment
     */
    protected $deployment;

    /**
     * Constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        // Initialize components
        $this->cli = new Cli();

        // Show welcome message
        $this->cli->showWelcome();

        // Handle early exit commands
        if ($this->handleEarlyExitCommands()) {
            return;
        }

        // Initialize configuration
        $this->config = new Config($this->cli);

        // Check if repository is valid
        if (!$this->isValidRepository()) {
            throw new \Exception("'" . getcwd() . "' is not a Git repository.");
        }

        // Initialize Git
        $this->git = new Git(getcwd());

        // Initialize deployment
        $this->deployment = new Deployment(
            $this->cli,
            $this->git,
            $this->config
        );

        // Run deployment
        $this->deploy();
    }

    /**
     * Handle commands that exit early (help, version, init)
     */
    protected function handleEarlyExitCommands(): bool
    {
        // Show help
        if ($this->cli->hasArgument('help')) {
            $this->cli->showHelp();
            return true;
        }

        // Show version
        if ($this->cli->hasArgument('version')) {
            $this->cli->showVersion($this->version);
            return true;
        }

        // Create sample config
        if ($this->cli->hasArgument('init')) {
            $this->config->createSampleConfig();
            return true;
        }

        // Handle dry run
        if ($this->cli->hasArgument('dryrun')) {
            $this->cli->showDryRunMessage();
            return true;
        }

        return false;
    }

    /**
     * Check if current directory is a valid Git repository
     */
    protected function isValidRepository(): bool
    {
        return file_exists(getcwd() . '/.git');
    }

    /**
     * Run the deployment process
     */
    protected function deploy(): void
    {
        // Show list mode message if needed
        if ($this->cli->hasArgument('list')) {
            $this->cli->showListModeMessage();
        }

        // Run deployment
        $this->deployment->run();
    }
}
