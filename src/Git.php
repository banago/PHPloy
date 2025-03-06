<?php

namespace Banago\PHPloy;

use Banago\PHPloy\Traits\DebugTrait;

/**
 * Class Git.
 */
class Git
{
    use DebugTrait;

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
     * @param string|null $repo
     * @throws \Exception
     */
    public function __construct($repo = null)
    {
        $this->repo = $repo;
        try {
            $this->branch = $this->command('rev-parse --abbrev-ref HEAD')[0];
            $this->revision = $this->command('rev-parse HEAD')[0];
        } catch (\Exception $e) {
            throw new \Exception("Failed to initialize Git: " . $e->getMessage());
        }
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

        exec('(' . $command . ') 2>&1', $output, $exitcode);

        if ($onErrorStopExecution && $exitcode !== 0) {
            throw new \Exception('Command [' . $command . '] failed with exit code ' . $exitcode);
        }

        return $output;
    }

    /**
     * Runs a git command and returns the output (as an array).
     *
     * @param string $command  "git [your-command-here]"
     * @param string|null $repoPath Defaults to $this->repo
     *
     * @return array Lines of the output
     * @throws \Exception
     */
    public function command($command, $repoPath = null)
    {
        if (!$repoPath) {
            $repoPath = $this->repo;
        }

        if (!is_dir($repoPath)) {
            throw new \Exception("Repository path does not exist: {$repoPath}");
        }

        if (!is_dir($repoPath . '/.git')) {
            throw new \Exception("Not a git repository: {$repoPath}");
        }

        // "-c core.quotepath=false" fixes special characters issue like ë, ä, ü etc., in file names
        $command = 'git -c core.quotepath=false --git-dir="' . $repoPath . '/.git" --work-tree="' . $repoPath . '" ' . $command;

        $output = $this->exec($command);
        $this->debug("Git command: {$command}\nOutput: " . implode("\n", $output));
        return $output;
    }

    /**
     * Diff versions.
     *
     * @param string|null $remoteRevision
     * @param string $localRevision
     * @param string|null $repoPath
     *
     * @return array
     * @throws \Exception
     */
    public function diff($remoteRevision, $localRevision, $repoPath = null)
    {
        if (empty($remoteRevision)) {
            $command = 'ls-files';
        } else {
            $command = 'diff --name-status ' . $remoteRevision . ' ' . $localRevision;
        }

        return $this->command($command, $repoPath);
    }

    /**
     * Checkout given $branch.
     *
     * @param string $branch
     * @param string|null $repoPath
     *
     * @return array
     * @throws \Exception
     */
    public function checkout($branch, $repoPath = null)
    {
        if (empty($branch)) {
            throw new \Exception("Branch name cannot be empty");
        }

        $command = 'checkout ' . $branch;

        return $this->command($command, $repoPath);
    }
}
