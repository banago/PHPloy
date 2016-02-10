<?php

namespace Banago\PHPloy;

class LocalCommands
{

    public function __construct()
    {

    }

    /**
     * Executes a console command and returns the output (as an array).
     *
     * @return array of all lines that were output to the console during the command (STDOUT)
     */
    public function exec($command)
    {
        exec(escapeshellcmd($command), $output);

        return $output;
    }

    /**
     * Runs a local command and returns the output (as an array).
     *
     * @param string $command  "[your-command-here]"
     *
     * @return array Lines of the output
     */
    public function command($command)
    {
        return $this->exec($command);
    }

}
