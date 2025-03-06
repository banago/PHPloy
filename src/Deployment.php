<?php

namespace Banago\PHPloy;

use Banago\PHPloy\Traits\DebugTrait;

/**
 * Handles deployment workflow
 */
class Deployment
{
    /**
     * @var Cli
     */
    protected $cli;

    /**
     * @var Git
     */
    protected $git;

    /**
     * @var Config
     */
    protected $config;


    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $currentServerName = '';

    /**
     * @var array
     */
    protected $currentServerInfo = [];

    /**
     * @var string
     */
    protected $repo;

    /**
     * @var string
     */
    protected $mainRepo;

    /**
     * @var string
     */
    protected $currentSubmoduleName = '';

    /**
     * @var array
     */
    protected $submodules = [];

    /**
     * @var bool
     */
    protected $scanSubmodules = false;

    /**
     * @var bool
     */
    protected $scanSubSubmodules = true;

    /**
     * @var string
     */
    protected $revision = 'HEAD';

    /**
     * @var bool
     */
    protected $listFiles = false;

    /**
     * @var bool|string
     */
    protected $sync = false;

    use DebugTrait;

    /**
     * @var bool
     */
    protected $fresh = false;

    /**
     * @var int
     */
    protected $deploymentSize = 0;

    /**
     * Constructor.
     */
    public function __construct(Cli $cli, Git $git, Config $config)
    {
        $this->cli = $cli;
        $this->git = $git;
        $this->config = $config;

        $this->repo = getcwd();
        $this->mainRepo = $this->repo;

        // Set options from CLI arguments
        $this->listFiles = $cli->hasArgument('list');
        $this->sync = $cli->hasArgument('sync');
        $this->setDebug($cli->hasArgument('debug'));
        $this->scanSubmodules = $cli->hasArgument('submodules');
        $this->fresh = $cli->hasArgument('fresh');
        $this->revision = $cli->hasArgument('rollback') ? $cli->getArgument('rollback') : 'HEAD';
    }

    /**
     * Run deployment process
     */
    public function run(): void
    {
        $servers = $this->config->getServers();
        $hasDefaultServer = isset($servers['default']);
        $deployAll = $this->cli->hasArgument('all') || !$hasDefaultServer;

        // Get target server if specified
        $targetServer = $this->cli->getArgument('server');
        if ($targetServer && !isset($servers[$targetServer])) {
            throw new \Exception("The server \"{$targetServer}\" is not defined in phploy.ini.");
        }

        // Check for submodules
        $this->checkSubmodules();

        // Deploy to each server
        foreach ($servers as $name => $server) {
            // Skip if:
            // 1. A specific server was requested and this isn't it, or
            // 2. No specific server was requested, --all wasn't specified,
            //    a default server exists, and this isn't the default server
            if (
                ($targetServer && $targetServer !== $name) ||
                (!$targetServer && !$deployAll && $hasDefaultServer && $name !== 'default')
            ) {
                continue;
            }

            $this->deployToServer($name, $server);
        }
    }

    /**
     * Deploy to a specific server
     */
    protected function deployToServer(string $name, array $server): void
    {
        $this->currentServerName = $name;
        $this->currentServerInfo = $server;

        // Connect to server
        $this->connection = new Connection($server);

        // Handle sync mode
        if ($this->sync) {
            $this->setRevision();
            return;
        }

        // Get files to deploy
        $files = $this->compare();

        $this->cli->info("\r\nSERVER: " . $name);

        if ($this->listFiles) {
            $this->listFiles($files);
            $this->handleSubmodules($files);
        } else {
            $this->push($files);
            $this->handleSubmodules($files);
        }

        // Show deployment size
        if (!$this->listFiles && $this->deploymentSize > 0) {
            $this->cli->success(
                sprintf(
                    "\r\n|---------------[ %s Deployed ]---------------|",
                    human_filesize($this->deploymentSize)
                )
            );
            $this->deploymentSize = 0;
        }
    }

    /**
     * Handle submodule deployment
     */
    protected function handleSubmodules(array $files): void
    {
        if (!$this->scanSubmodules || empty($this->submodules)) {
            return;
        }

        foreach ($this->submodules as $submodule) {
            $this->repo = $submodule['path'];
            $this->currentSubmoduleName = $submodule['name'];

            $this->cli->info("SUBMODULE: " . $this->currentSubmoduleName);
            $subFiles = $this->compare($submodule['revision']);

            if ($this->listFiles) {
                $this->listFiles($subFiles);
            } else {
                $this->push($subFiles, $submodule['revision']);
            }
        }

        // Reset after submodule deployment
        $this->repo = $this->mainRepo;
        $this->currentSubmoduleName = '';
    }

    /**
     * Check for submodules
     */
    protected function checkSubmodules(): void
    {
        if (!$this->scanSubmodules) {
            return;
        }

        $this->cli->info('Scanning repository...');

        $output = $this->git->command('submodule status', $this->repo);
        $count = count($output);

        $this->cli->info("Found {$count} submodules.");

        if ($count > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));

                $this->submodules[] = [
                    'revision' => $line[0],
                    'name' => $line[1],
                    'path' => $this->repo . '/' . $line[1],
                ];

                $this->cli->info(sprintf(
                    'Found submodule %s. %s',
                    $line[1],
                    $this->scanSubSubmodules ? "\nScanning for sub-submodules..." : ''
                ));

                if ($this->scanSubSubmodules) {
                    $this->checkSubSubmodules($line[1]);
                }
            }
        }
    }

    /**
     * Check for sub-submodules
     */
    protected function checkSubSubmodules(string $name): void
    {
        $output = $this->git->command('submodule foreach git submodule status', $this->repo);

        foreach ($output as $line) {
            if (strpos($line, 'Entering') === 0) {
                continue;
            }

            $line = explode(' ', trim($line));

            $this->submodules[] = [
                'revision' => $line[0],
                'name' => $name . '/' . $line[1],
                'path' => $this->repo . '/' . $name . '/' . $line[1],
            ];

            $this->cli->info("Found sub-submodule {$name}/{$line[1]}");
        }
    }

    /**
     * Compare revisions and get files to deploy
     */
    protected function compare(string $localRevision = null): array
    {
        if ($localRevision === null) {
            $localRevision = $this->revision;
        }

        $remoteRevision = null;
        $dotRevision = $this->currentSubmoduleName
            ? $this->currentSubmoduleName . '/.revision'
            : '.revision';

        // Get remote revision
        if (!$this->fresh && $this->connection->has($dotRevision)) {
            $remoteRevision = $this->connection->read($dotRevision);
            $this->debug("Remote revision: {$remoteRevision}");
        } else {
            $this->cli->info('No revision found. Fresh upload...');
        }

        // Handle branch checkout
        if (!empty($this->currentServerInfo['branch'])) {
            $this->checkoutBranch($this->currentServerInfo['branch']);
        }

        // Get changed files
        $output = $this->git->diff($remoteRevision, $localRevision, $this->repo);
        $this->debug(implode("\r\n", $output));

        return $this->processGitDiff($output, $remoteRevision);
    }

    /**
     * Process git diff output
     */
    protected function processGitDiff(array $output, ?string $remoteRevision): array
    {
        $filesToUpload = [];
        $filesToDelete = [];

        if (empty($remoteRevision)) {
            $filesToUpload = $output;
        } else {
            foreach ($output as $line) {
                $status = $line[0];

                if (
                    strpos($line, 'warning: CRLF') !== false ||
                    strpos($line, 'original line endings') !== false
                ) {
                    continue;
                }

                switch ($status) {
                    case 'A':
                    case 'C':
                    case 'M':
                    case 'T':
                        $filesToUpload[] = trim(substr($line, 1));
                        break;

                    case 'D':
                        $filesToDelete[] = trim(substr($line, 1));
                        break;

                    case 'R':
                        list(, $oldFile, $newFile) = preg_split('/\s+/', $line);
                        $filesToDelete[] = trim($oldFile);
                        $filesToUpload[] = trim($newFile);
                        break;

                    default:
                        throw new \Exception(
                            "Unknown git-diff status. Use '--sync' to update remote revision or use '--debug' to see what's wrong."
                        );
                }
            }
        }

        return [
            'upload' => $filesToUpload,
            'delete' => $filesToDelete,
        ];
    }

    /**
     * Push files to server
     */
    protected function push(array $files, string $localRevision = null): void
    {
        if ($localRevision === null) {
            $localRevision = $this->git->revision;
        }

        $initialBranch = $this->git->branch;

        // Handle rollback
        if ($this->revision !== 'HEAD') {
            $this->cli->info('Rolling back working copy');
            $this->git->command('checkout ' . $this->revision, $this->repo);
        }

        // Upload files
        foreach ($files['upload'] as $i => $file) {
            $this->uploadFile($file, $i + 1, count($files['upload']));
        }

        // Delete files
        foreach ($files['delete'] as $i => $file) {
            $this->deleteFile($file, $i + 1, count($files['delete']));
        }

        // Update revision
        if (!empty($files['upload']) || !empty($files['delete'])) {
            if ($this->revision !== 'HEAD') {
                $revision = $this->git->command('rev-parse HEAD')[0];
                $this->setRevision($revision);
            } else {
                $this->setRevision($localRevision);
            }

            // Log deployment
            $this->cli->logDeployment(
                $localRevision,
                $this->currentServerName,
                $initialBranch ?: 'master',
                count($files['upload']),
                count($files['delete'])
            );
        } else {
            $this->cli->info('No files to upload or delete.');
        }

        // Restore branch after rollback
        if ($this->revision !== 'HEAD') {
            $this->git->command('checkout ' . ($initialBranch ?: 'master'));
        }
    }

    /**
     * Upload a file
     */
    protected function uploadFile(string $file, int $number, int $total): void
    {
        if ($this->currentSubmoduleName) {
            $file = $this->currentSubmoduleName . '/' . $file;
        }

        $filePath = $this->repo . '/' . ($this->currentSubmoduleName
            ? str_replace($this->currentSubmoduleName . '/', '', $file)
            : $file
        );

        $data = @file_get_contents($filePath);
        if ($data === false) {
            $this->cli->error("File not found - please check path: {$filePath}");
            return;
        }

        try {
            $this->connection->put($file, $data);
            $this->deploymentSize += filesize($filePath);

            $fileNo = str_pad((string)$number, strlen((string)$total), ' ', STR_PAD_LEFT);
            $this->cli->success(" ^ {$fileNo} of {$total} {$file}");
        } catch (\Exception $e) {
            $this->cli->error("Failed to upload {$file}: " . $e->getMessage());

            if (!$this->connection) {
                $this->cli->info('Connection lost, trying to reconnect...');
                $this->connection = new Connection($this->currentServerInfo);

                try {
                    $this->connection->put($file, $data);
                    $this->deploymentSize += filesize($filePath);

                    $fileNo = str_pad((string)$number, strlen((string)$total), ' ', STR_PAD_LEFT);
                    $this->cli->success(" ^ {$fileNo} of {$total} {$file}");
                } catch (\Exception $e) {
                    $this->cli->error("Failed to upload {$file} after reconnect: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Delete a file
     */
    protected function deleteFile(string $file, int $number, int $total): void
    {
        if ($this->currentSubmoduleName) {
            $file = $this->currentSubmoduleName . '/' . $file;
        }

        $fileNo = str_pad((string)$number, strlen((string)$total), ' ', STR_PAD_LEFT);

        try {
            if ($this->connection->has($file)) {
                $this->connection->delete($file);
                $this->cli->info(" × {$fileNo} of {$total} {$file}");
            } else {
                $this->cli->warning(" ! {$fileNo} of {$total} {$file} not found");
            }
        } catch (\Exception $e) {
            $this->cli->error("Failed to delete {$file}: " . $e->getMessage());
        }
    }

    /**
     * Set revision on server
     */
    protected function setRevision(?string $localRevision = null): void
    {
        if ($localRevision === null) {
            $localRevision = $this->git->revision;
        }

        if ($this->sync && $this->sync !== 'LAST') {
            $localRevision = $this->sync;
        }

        $dotRevision = $this->currentSubmoduleName
            ? $this->currentSubmoduleName . '/.revision'
            : '.revision';

        if ($this->sync) {
            $this->cli->info("Setting remote revision to: {$localRevision}");
        }

        $this->connection->put($dotRevision, $localRevision);
    }

    /**
     * Checkout branch
     */
    protected function checkoutBranch(string $branch): void
    {
        $output = $this->git->checkout($branch, $this->repo);

        if (!empty($output[0])) {
            if (strpos($output[0], 'error') === 0) {
                throw new \Exception('Stash your modifications before deploying.');
            }
            $this->cli->info($output[0]);
        }

        if (!empty($output[1]) && $output[1][0] === 'M') {
            throw new \Exception('Stash your modifications before deploying.');
        }
    }

    /**
     * List files to be deployed
     */
    protected function listFiles(array $files): void
    {
        if (empty($files['upload']) && empty($files['delete'])) {
            $this->cli->info('No files to upload.');
            return;
        }

        if (!empty($files['delete'])) {
            $this->cli->warning('Files that will be deleted in next deployment:');
            foreach ($files['delete'] as $file) {
                $this->cli->info("   {$file}");
            }
        }

        if (!empty($files['upload'])) {
            $this->cli->success('Files that will be uploaded in next deployment:');
            foreach ($files['upload'] as $file) {
                $this->cli->info("   {$file}");
            }
        }
    }
}
