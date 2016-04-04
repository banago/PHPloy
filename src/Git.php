<?php

namespace Banago\PHPloy;

class Git
{
    /**
     * @var string Git branch
     */
    public $branch;

    /**
     * @var string Git revision
     */
    public $revision;

    /**
     * @var string Git repository
     */
    protected $repo;

    public function __construct($repo = null)
    {
        $this->repo = $repo;
        $this->branch = $this->command('rev-parse --abbrev-ref HEAD')[0];
        $this->revision = $this->command('rev-parse HEAD')[0];
    }

    /**
     * Executes a console command and returns the output (as an array).
     *
     * @return array of all lines that were output to the console during the command (STDOUT)
     */
    public function exec($command)
    {
        $output = null;

        exec('('.escapeshellcmd($command).') 2>&1', $output);

        return $output;
    }

    /**
     * Runs a git command and returns the output (as an array).
     *
     * @param string $command  "git [your-command-here]"
     * @param string $repoPath Defaults to $this->repo
     *
     * @return array Lines of the output
     */
    public function command($command, $repoPath = null)
    {
        if (!$repoPath) {
            $repoPath = $this->repo;
        }

        // "-c core.quotepath=false" in fixes special characters issue like ë, ä, ü etc., in file names
        $command = 'git -c core.quotepath=false --git-dir="'.$repoPath.'/.git" --work-tree="'.$repoPath.'" '.$command;

        return $this->exec($command);
    }

    public function diff($remoteRevision, $localRevision)
    {
        if (empty($remoteRevision)) {
            $command = 'ls-files';
        } elseif ($localRevision === 'HEAD') {
            $command = 'diff --name-status '.$remoteRevision.' '.$localRevision;
        } else {
            // What's the point of this ELSE clause?
            $command = 'diff --name-status '.$remoteRevision.' '.$localRevision;
        }

        return $this->command($command);
    }

    public function checkout($branch)
    {
        $command = 'checkout '.$branch;

        return $this->command($command);
    }
}
