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

        // A submodule's `.git` is a *file* (a `gitdir:` pointer), not a directory.
        if (!is_dir($repoPath . '/.git') && !is_file($repoPath . '/.git')) {
            throw new \Exception("Not a git repository: {$repoPath}");
        }

        // "-c core.quotepath=false" fixes special characters issue like ë, ä, ü etc., in file names
        $command = 'git -c core.quotepath=false --git-dir="' . $repoPath . '/.git" '
            . '--work-tree="' . $repoPath . '" ' . $command;

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
     * Count how many commits $localRevision is ahead of $remoteRevision.
     *
     * @param string $remoteRevision
     * @param string $localRevision
     * @param string|null $repoPath
     *
     * @return int Number of commits (0 = in sync), or -1 when the remote revision is unknown locally.
     */
    public function commitsBehind($remoteRevision, $localRevision, $repoPath = null)
    {
        $output = $this->command('rev-list --count ' . $remoteRevision . '..' . $localRevision, $repoPath);

        if (empty($output) || !is_numeric($output[0])) {
            return -1;
        }

        return (int) $output[0];
    }

    /**
     * List recent commits (most recent first) for the interactive rollback picker.
     *
     * @param int $limit
     * @return array<int, array{sha: string, subject: string}>
     * @throws \Exception
     */
    public function recentCommits(int $limit = 20): array
    {
        // %x09 is a literal tab — avoids the space that would otherwise split
        // the --format value into two shell arguments.
        $output = $this->command('log --format=%H%x09%s -' . $limit);

        $commits = [];
        foreach ($output as $line) {
            if (preg_match('/^([0-9a-f]{40})\t(.*)$/', $line, $matches)) {
                $commits[] = [
                    'sha' => $matches[1],
                    'subject' => $matches[2],
                ];
            }
        }

        return $commits;
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
