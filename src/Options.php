<?php

namespace Banago\PHPloy;

use League\CLImate\CLImate;

/**
 * Class Options.
 */
class Options
{
    /**
     * @var CLImate
     */
    public $cli;

    /**
     * Options constructor.
     *
     * @param CLImate $climate
     */
    public function __construct($climate)
    {
        $this->cli = $climate;

        $this->build();
        $this->parse();
    }

    /**
     * Register available options.
     */
    protected function build()
    {
        $this->cli->description('PHPloy - Incremental Git FTP/SFTP deployment tool that supports multiple servers, submodules and rollbacks.');
        $this->cli->arguments->add([
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
        ]);
    }


    protected function parse()
    {
        $this->cli->arguments->parse();
    }
}
