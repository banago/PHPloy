<?php

namespace Banago\PHPloy;

/**
 * Class Git.
 */
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

    /**
     * Git constructor.
     *
     * @param string $repo
     */
    public function __construct($repo = null)
    {
        $this->repo = $repo;
        $this->branch = $this->command('rev-parse --abbrev-ref HEAD')[0];
        $this->revision = $this->command('rev-parse HEAD')[0];
    }

    /**
     * Executes a console command and returns the output (as an array).
     *
     * @param string $command Command to execute
     * @param boolean $onErrorStopExecution If there is a problem, stop execution of the code
     *
     * @return array of all lines that were output to the console during the command (STDOUT)
     *
     * @throws \Exception
     */
    public function exec($command, $onErrorStopExecution = false)
    {
        $output = null;

        exec('('.$command.') 2>&1', $output, $exitcode);

        if ($onErrorStopExecution && $exitcode !== 0){
            throw new \Exception('Command [' . $command . '] exited badly');
        }

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

    /**
     * Diff versions.
     *
     * @param string $remoteRevision
     * @param string $localRevision
     * @param string $repoPath
     *
     * @return array
     */
    public function diff($remoteRevision, $localRevision, $repoPath = null)
    {
        if (empty($remoteRevision)) {
            $command = 'ls-files';
        } elseif ($localRevision === 'HEAD') {
            $command = 'diff --name-status '.$remoteRevision.' '.$localRevision;
        } else {
            // What's the point of this ELSE clause?
            $command = 'diff --name-status '.$remoteRevision.' '.$localRevision;
        }

        return $this->command($command, $repoPath);
    }

    /**
     * Checkout given $branch.
     *
     * @param string $branch
     * @param string $repoPath
     *
     * @return array
     */
    public function checkout($branch, $repoPath = null)
    {
        $command = 'checkout '.$branch;

        return $this->command($command, $repoPath);
    }
}
