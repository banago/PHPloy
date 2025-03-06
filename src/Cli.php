<?php

namespace Banago\PHPloy;

use League\CLImate\CLImate;

/**
 * Handles CLI interactions using League\CLImate.
 */
class Cli
{
    /**
     * @var CLImate
     */
    protected $climate;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->climate = new CLImate();
        $this->registerArguments();
    }

    /**
     * Register available CLI arguments.
     */
    protected function registerArguments(): void
    {
        $this->climate->description('PHPloy - Incremental Git FTP/SFTP deployment tool that supports multiple servers, submodules and rollbacks.');

        $this->climate->arguments->add([
            'list' => [
                'prefix' => 'l',
                'longPrefix' => 'list',
                'description' => 'Lists the files and directories to be uploaded or deleted',
                'noValue' => true,
            ],
            'server' => [
                'prefix' => 's',
                'longPrefix' => 'server',
                'description' => 'Deploy to the given server',
            ],
            'rollback' => [
                'longPrefix' => 'rollback',
                'description' => 'Rolls the deployment back to a given version',
                'defaultValue' => 'HEAD^',
            ],
            'sync' => [
                'longPrefix' => 'sync',
                'description' => 'Syncs revision to a given version',
                'defaultValue' => 'LAST',
            ],
            'submodules' => [
                'prefix' => 'm',
                'longPrefix' => 'submodules',
                'description' => 'Includes submodules in next deployment',
                'noValue' => true,
            ],
            'init' => [
                'longPrefix' => 'init',
                'description' => 'Creates sample deploy.ini file',
                'noValue' => true,
            ],
            'force' => [
                'longPrefix' => 'force',
                'description' => 'Creates directory to the deployment path if it does not exist',
                'noValue' => true,
            ],
            'fresh' => [
                'longPrefix' => 'fresh',
                'description' => 'Deploys all files even if some already exist on server. Ignores server revision.',
                'noValue' => true,
            ],
            'all' => [
                'longPrefix' => 'all',
                'description' => 'Deploys to all specified servers when a default exists',
                'noValue' => true,
            ],
            'debug' => [
                'prefix' => 'd',
                'longPrefix' => 'debug',
                'description' => 'Shows verbose output for debugging',
                'noValue' => true,
            ],
            'version' => [
                'prefix' => 'v',
                'longPrefix' => 'version',
                'description' => 'Shows PHPloy version',
                'noValue' => true,
            ],
            'help' => [
                'prefix' => 'h',
                'longPrefix' => 'help',
                'description' => 'Lists commands and their usage',
                'noValue' => true,
            ],
            'dryrun' => [
                'longPrefix' => 'dryrun',
                'description' => 'Stops after parsing arguments and do not alter the remote servers',
                'noValue' => true,
            ]
        ]);

        $this->climate->arguments->parse();
    }

    /**
     * Show welcome message.
     */
    public function showWelcome(): void
    {
        $this->climate->backgroundGreen()->bold()->out('-------------------------------------------------');
        $this->climate->backgroundGreen()->bold()->out('|                     PHPloy                    |');
        $this->climate->backgroundGreen()->bold()->out('-------------------------------------------------');
    }

    /**
     * Show help message.
     */
    public function showHelp(): void
    {
        $this->climate->usage();
    }

    /**
     * Show version message.
     */
    public function showVersion(string $version): void
    {
        $this->climate->bold()->info('PHPloy v' . $version);
    }

    /**
     * Show dry run message.
     */
    public function showDryRunMessage(): void
    {
        $this->climate->bold()->yellow('DRY RUN, PHPloy will not check or alter the remote servers');
    }

    /**
     * Show list mode message.
     */
    public function showListModeMessage(): void
    {
        $this->climate->lightYellow('LIST mode: No remote files will be modified.');
    }

    /**
     * Check if an argument is defined.
     */
    public function hasArgument(string $name): bool
    {
        return $this->climate->arguments->defined($name);
    }

    /**
     * Get an argument value.
     *
     * @return mixed
     */
    public function getArgument(string $name)
    {
        return $this->climate->arguments->get($name);
    }

    /**
     * Output an error message.
     */
    public function error(string $message): void
    {
        $this->climate->bold()->error($message);
    }

    /**
     * Output a success message.
     */
    public function success(string $message): void
    {
        $this->climate->bold()->green($message);
    }

    /**
     * Output an info message.
     */
    public function info(string $message): void
    {
        $this->climate->info($message);
    }

    /**
     * Output a warning message.
     */
    public function warning(string $message): void
    {
        $this->climate->bold()->yellow($message);
    }

    /**
     * Output a debug message.
     */
    public function debug(string $message): void
    {
        $this->climate->comment($message);
    }

    /**
     * Log a deployment event
     */
    public function logDeployment(
        string $sha,
        string $server,
        string $branch,
        int $filesUploaded,
        int $filesDeleted
    ): void {
        $message = sprintf(
            '[SHA: %s] Deployment to server: "%s" from branch "%s". %d files uploaded; %d files deleted.',
            $sha,
            $server,
            $branch,
            $filesUploaded,
            $filesDeleted
        );

        $this->info($message);
    }
}
