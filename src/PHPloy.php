<?php

/**
 * PHPloy - A PHP Deployment Tool.
 *
 * @author Baki Goxhaj <banago@gmail.com>
 *
 * @link https://github.com/banago/PHPloy
 * @licence MIT Licence
 *
 * @version 4.0-alpha
 */

namespace Banago\PHPloy;

class PHPloy
{

    /**
     * @var string
     */
    protected $version = '4.0-alpha';

    /**
     * @var string
     */
    public $revision = 'HEAD';

    /**
     * @var string
     */
    public $cli;

    /**
     * @var string
     */
    public $git;

    /**
     * @var array
     */
    public $hooks;

    /**
     * @var string
     */
    public $localRevision;

    /**
     * Keep track of which server we are currently deploying to.
     *
     * @var string
     */
    public $currentlyDeploying = '';

    /**
     * A list of files that should NOT be uploaded to any of the servers.
     *
     * @var array
     */
    public $globalFilesToExclude = [
        '.gitignore',
        '.gitmodules',
    ];

    /**
     * A list of files that should NOT be uploaded to the named server.
     *
     * @var array
     */
    public $filesToExclude = [];

    /**
     * A list of patterns that a file MUST match to be uploaded
     * to the remote server.
     */
    public $filesToInclude = [];

    /**
     * To activate submodule deployment use the --submodules argument.
     *
     * @var bool
     */
    public $scanSubmodules = false;

    /**
     * If you need support for sub-submodules, ensure this is set to TRUE
     * Set to false when the --exclude-subsubmodules command line option is used.
     *
     * @var bool
     */
    public $scanSubSubmodules = true;

    /**
     * @var array
     */
    public $servers = [];

    /**
     * @var array
     */
    public $submodules = [];

    /**
     * @var array
     */
    public $purgeDirs = [];

    /**
     * @var array
     */
    public $preDeploy = [];

    /**
     * @var array
     */
    public $postDeploy = [];

    /**
     * The name of the file on remote servers that stores the current revision hash.
     *
     * @var string
     */
    public $dotRevisionFilename = '.revision';

    /**
     * The filename from which to read remote server details.
     *
     * @var string
     */
    public $iniFilePath = null;
    public $iniFilename = 'phploy.ini';

    /**
     * @var bool|resource
     */
    protected $connection = null;

    /**
     * @var string
     */
    protected $server = '';

    /**
     * @var string
     */
    protected $repo;

    /**
     * @var string
     */
    protected $mainRepo;

    /**
     * @var bool|string
     */
    protected $currentSubmoduleName = '';

    /**
     * Holds the path to the .revision file
     * For the main repository this will be the value of $dotRevisionFilename ('.revision' by default)
     * but for submodules, the submodule path will be prepended.
     *
     * @var string
     */
    protected $dotRevision;

    /**
     * Whether phploy is running in list mode (--list or -l commands).
     *
     * @var bool
     */
    protected $listFiles = false;

    /**
     * Whether the --sync command line option was given.
     *
     * @var bool
     */
    protected $sync = false;

    /**
     * Whether to print extra debugging info to the console, especially for git & FTP commands
     * Activated using --debug command line option.
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Keep track of current deployment size.
     *
     * @var int
     */
    protected $deploymentSize = 0;

    /**
     * Keep track of if a default server has been configured.
     *
     * @var bool
     */
    protected $defaultServer = false;

    /**
     * Weather the --all command line option was given.
     *
     * @var bool deployAll
     */
    protected $deployAll = false;

    /**
     * Whether the --init command line option was given.
     *
     * @var bool init
     */
    protected $init = false;

    /**
     * Set configuration provided via terminal
     *
     * @var string config
     */
    protected $config = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->opt = new \Banago\PHPloy\Options(new \League\CLImate\CLImate());
        $this->cli = $this->opt->cli;

        $this->cli->backgroundGreen()->bold()->out('---------------------------------------------------');
        $this->cli->backgroundGreen()->bold()->out("|                PHPloy v{$this->version}                |");
        $this->cli->backgroundGreen()->bold()->out('---------------------------------------------------');

        // Setup PHPloy
        $this->setup();

        if ($this->cli->arguments->defined('help')) {
            $this->cli->usage();

            return;
        }

        if ($this->cli->arguments->defined('init')) {
            $this->createSampleIniFile();

            return;
        }

        if (file_exists("$this->repo/.git")) {
            $this->git = new \Banago\PHPloy\Git($this->repo);
            $this->deploy();
        } else {
            throw new \Exception("'{$this->repo}' is not a Git repository.");
        }
    }

    /**
     * Setup CLI options.
     */
    public function setup()
    {
        $this->repo = getcwd();

        if ($this->cli->arguments->defined('debug')) {
            $this->debug = true;
        }

        if ($this->cli->arguments->defined('list')) {
            $this->listFiles = true;
        }

        if ($this->cli->arguments->defined('server')) {
            $this->server = $this->cli->arguments->get('server');
        }

        if ($this->cli->arguments->defined('sync')) {
            $this->sync = $this->cli->arguments->get('sync');
        }

        if ($this->cli->arguments->defined('rollback')) {
            $this->revision = $this->cli->arguments->get('rollback');
        }

        if ($this->cli->arguments->defined('submodules')) {
            $this->scanSubmodules = true;
        }

        if ($this->cli->arguments->defined('all')) {
            $this->deployAll = true;
        }

        if ($this->cli->arguments->defined('server')) {
            $this->init = true;
        }

        if ($this->cli->arguments->defined('config')) {
            $this->config = $this->cli->arguments->get('config');
        }

        if ($this->cli->arguments->defined('inifilepath')) {
            $this->iniFilePath = $this->cli->arguments->get('inifilepath');
        } else {
            $this->iniFilePath = $this->repo . DIRECTORY_SEPARATOR;
        }

        if ($this->cli->arguments->defined('inifilename')) {
            $this->iniFilename = $this->cli->arguments->get('inifilename');
        }

        $this->repo = getcwd();
        $this->mainRepo = $this->repo;
    }

    /**
     * Parse Credentials.
     *
     * @param string $deploy The filename to obtain the list of servers from, normally $this->iniFilename
     *
     * @return array of servers listed in the file $deploy
     */
    public function parseCredentials($iniFile)
    {
        if (!file_exists($iniFile)) {
            throw new \Exception("'$iniFile' does not exist.");
        } else {
            $servers = parse_ini_file($iniFile, true);

            if (!$servers) {
                throw new \Exception("'$iniFile' is not a valid .ini file.");
            } else {
                return $servers;
            }
        }
    }

    /**
     * Reads the phploy.ini file and populates the $this->servers array.
     */
    public function prepareServers()
    {
        $defaults = [
            'scheme'      => 'ftp',
            'host'        => '',
            'user'        => '',
            'pass'        => '',
            'path'        => '/',
            'privkey'     => '',
            'port'        => null,
            'passive'     => null,
            'timeout'     => null,
            'branch'      => '',
            'include'     => [],
            'exclude'     => [],
            'purge'       => [],
            'pre-deploy'  => [],
            'post-deploy' => [],
        ];

        $iniFile = $this->iniFilePath . $this->iniFilename;

        $servers = $this->parseCredentials($iniFile);

        // Check if config are defined
        if($this->config) {

            // If so, we overwrite server with our defined configuration from terminal
            $servers = json_decode($this->config, true);

        }

        foreach ($servers as $name => $options) {
            $options = array_merge($defaults, $options);

            // Determine if a default server is configured
            if ($name == 'default') {
                $this->defaultServer = true;
            }

            // Re-merge parsed URL in quickmode
            if (isset($options['quickmode'])) {
                $options = array_merge($options, parse_url($options['quickmode']));
            }

            // Ignoring for the win
            $this->filesToExclude[$name] = $this->globalFilesToExclude;
            $this->filesToExclude[$name][] = $this->iniFilename;

            if (!empty($servers[$name]['exclude'])) {
                $this->filesToExclude[$name] = array_merge($this->filesToExclude[$name], $servers[$name]['exclude']);
            }

            if (!empty($servers[$name]['include'])) {
                $this->filesToInclude[$name] = $servers[$name]['include'];
            }

            if (!empty($servers[$name]['purge'])) {
                $this->purgeDirs[$name] = $servers[$name]['purge'];
            }

            if (!empty($servers[$name]['pre-deploy'])) {
                $this->preDeploy[$name] = $servers[$name]['pre-deploy'];
            }

            if (!empty($servers[$name]['post-deploy'])) {
                $this->postDeploy[$name] = $servers[$name]['post-deploy'];
            }

            // Ask user a password if it is empty, and if a public or private key is not defined
            if ($options['pass'] === '' && $options['privkey'] === '') {
                fwrite(STDOUT, 'No password has been provided for user "' . $options['user'] . '". Please enter a password: ');
                $input = urlencode($this->getPassword());

                if ($input == '') {
                    $this->cli->lightGreen()->out('You entered an empty password. Continuing deployment anyway ...');
                } else {
                    $options['pass'] = $input;
                    $this->cli->lightGreen()->out('Password received. Continuing deployment ...');
                }
            }

            $this->servers[$name] = $options;
        }
    }

    /**
     * Gets the password from user input, hiding password and replaces it
     * with stars (*) if user users Unix / Mac.
     *
     * @return string the user entered
     */
    private function getPassword()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
            return trim(fgets(STDIN));
        }

        $oldStyle = shell_exec('stty -g');
        $password = '';

        shell_exec('stty -icanon -echo min 1 time 0');
        while (true) {
            $char = fgetc(STDIN);
            if ($char === "\n") {
                break;
            } elseif (ord($char) === 127) {
                if (strlen($password) > 0) {
                    fwrite(STDOUT, "\x08 \x08");
                    $password = substr($password, 0, -1);
                }
            } else {
                fwrite(STDOUT, '*');
                $password .= $char;
            }
        }

        shell_exec('stty ' . $oldStyle);

        return $password;
    }

    /**
     * Filter ignore files.
     *
     * @param array $files Array of files which needed to be filtered
     *
     * @return array with `files` (filtered) and `filesToSkip`
     */
    private function filterIgnoredFiles($files)
    {
        $filesToSkip = [];

        foreach ($files as $i => $file) {
            foreach ($this->filesToExclude[$this->currentlyDeploying] as $pattern) {
                if ($this->patternMatch($pattern, $file)) {
                    unset($files[$i]);
                    $filesToSkip[] = $file;
                    break;
                }
            }
        }

        $files = array_values($files);

        return [
            'files'       => $files,
            'filesToSkip' => $filesToSkip,
        ];
    }

    /**
     * Filter included files.
     *
     * @param array $files Array of files which needed to be filtered
     *
     * @return array $filesToGrip
     */
    private function filterIncludedFiles($files)
    {
        $filesToGrip = [];

        foreach ($files as $i => $file) {

            $name = getcwd() . '/' . $file;
            if (is_dir($name)) {
                $filesToGrip = array_merge($filesToGrip, array_map([$this, 'relPath'], $this->directoryToArray($name, false)));
            } else {
                $filesToGrip[] = $file;
            }
        }

        return $filesToGrip;
    }

    /**
     * Deploy (or list) changed files.
     */
    public function deploy()
    {
        if ($this->listFiles) {
            $this->cli->lightYellow('LIST mode: No remote files will be modified.');
        }

        $this->checkSubmodules($this->repo);

        $this->prepareServers();

        // Exit with an error if the specified server does not exist in phploy.ini
        if ($this->server != '' && !array_key_exists($this->server, $this->servers)) {
            throw new \Exception("The server \"{$this->server}\" is not defined in {$this->iniFilename}.");
        }

        // Loop through all the servers in phploy.ini
        foreach ($this->servers as $name => $server) {
            $this->currentlyDeploying = $name;

            // If a server is specified, it's deployed only to that
            if ($this->server != '' && $this->server != $name) {
                continue;
            }

            // If no server was specified in the command line but a default server
            // configuration exists, we'll use that (as long as --all was not specified)
            elseif ($this->server == '' && $this->defaultServer == true && $name != 'default' && $this->deployAll == false) {
                continue;
            }

            $connection = new \Banago\PHPloy\Connection($server);
            $this->connection = $connection->server;

            if ($this->sync) {
                $this->dotRevision = $this->dotRevisionFilename;
                $this->setRevision();
                continue;
            }

            $files = $this->compare($this->revision);

            $this->cli->bold()->white()->out("\r\nSERVER: " . $name);

            if ($this->listFiles) {
                $this->listFiles($files[$this->currentlyDeploying]);
            } else {
                // Pre Deploy
                if (isset($this->preDeploy[$name]) && count($this->preDeploy[$name]) > 0) {
                    $this->preDeploy($this->preDeploy[$name]);
                }
                $this->push($files[$this->currentlyDeploying]);
                // Purge
                if (isset($this->purgeDirs[$name]) && count($this->purgeDirs[$name]) > 0) {
                    $this->purge($this->purgeDirs[$name]);
                }
                // Post Deploy
                if (isset($this->postDeploy[$name]) && count($this->postDeploy[$name]) > 0) {
                    $this->postDeploy($this->postDeploy[$name]);
                }
            }

            if ($this->scanSubmodules && count($this->submodules) > 0) {
                foreach ($this->submodules as $submodule) {
                    $this->repo = $submodule['path'];
                    $this->currentSubmoduleName = $submodule['name'];

                    $this->cli->gray()->out("\r\nSUBMODULE: " . $this->currentSubmoduleName);

                    $files = $this->compare($this->revision);

                    if ($this->listFiles === true) {
                        $this->listFiles($files[$this->currentlyDeploying]);
                    } else {
                        $this->push($files[$this->currentlyDeploying]);
                    }
                }
                // We've finished deploying submodules, reset settings for the next server
                $this->repo = $this->mainRepo;
                $this->currentSubmoduleName = '';
            }

            // Done
            if (!$this->listFiles) {
                $this->cli->bold()->lightGreen("\r\n|----------------[ " . $this->humanFilesize($this->deploymentSize) . ' Deployed ]----------------|');
                $this->deploymentSize = 0;
            }
        }
    }

    /**
     * Return a human readable filesize.
     *
     * @param int $bytes
     * @param int $decimals
     */
    public function humanFilesize($bytes, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    /**
     * Glob the file path.
     *
     * @param string $pattern
     * @param string $string
     */
    public function patternMatch($pattern, $string)
    {
        return preg_match('#^' . strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.']) . '$#i', $string);
    }

    /**
     * Check what files will be uploaded/deleted.
     *
     * @param array $files
     */
    public function listFiles($files)
    {
        if (count($files['upload']) == 0 && count($files['delete']) == 0) {
            $this->cli->out('   No files to upload.');
        }

        if (count($files['delete']) > 0) {
            $this->cli->shout('   Files that will be deleted in next deployment:');

            foreach ($files['delete'] as $file_to_delete) {
                $this->cli->out('      ' . $file_to_delete);
            }
        }

        if (count($files['upload']) > 0) {
            $this->cli->lightGreen('   Files that will be uploaded in next deployment:');

            foreach ($files['upload'] as $file_to_upload) {
                $this->cli->out('      ' . $file_to_upload);
            }
        }
    }

    /**
     * Compare revisions and returns array of files to upload:.
     *
     *      [
     *          'upload' => $filesToUpload,
     *          'delete' => $filesToDelete
     *      ];
     *
     * @param string $localRevision
     *
     * @throws Exception if unknown git diff status
     *
     * @return array
     */
    public function compare($localRevision)
    {
        $remoteRevision = null;
        $tmpFile = tmpfile();
        $filesToUpload = [];
        $filesToDelete = [];
        $filesToSkip = [];
        $output = [];

        if ($this->currentSubmoduleName) {
            $this->dotRevision = $this->currentSubmoduleName . '/' . $this->dotRevisionFilename;
        } else {
            $this->dotRevision = $this->dotRevisionFilename;
        }

        // Fetch the .revision file from the server and write it to $tmpFile
        $this->debug("Fetching {$this->dotRevision} file");

        if ($this->connection->has($this->dotRevision)) {
            $remoteRevision = $this->connection->read($this->dotRevision);
        } else {
            $this->cli->comment('No revision found - uploading everything...');
        }

        if (!empty($this->servers[$this->currentlyDeploying]['branch'])) {
           $output = $this->git->checkout($this->servers[$this->currentlyDeploying]['branch']);

            if(isset($output[0])) {
                if(strpos($output[0], 'error') === 0) {
                    throw new \Exception("Stash your modifications before sync");
                }
            }

            if(isset($output[1])) {
                if($output[1][0] === 'M') {
                    throw new \Exception("Stash your modifications before sync");
                }
            }

            if(isset($output[0])) {
                $this->cli->out($output[0]);
            }
        }

        $output = $this->git->diff($remoteRevision, $localRevision);
        $this->debug(implode("\r\n", $output));

        /*
         * Git Status Codes
         *
         * A: addition of a file
         * C: copy of a file into a new one
         * D: deletion of a file
         * M: modification of the contents or mode of a file
         * R: renaming of a file
         * T: change in the type of the file
         * U: file is unmerged (you must complete the merge before it can be committed)
         * X: "unknown" change type (most probably a bug, please report it)
         */

        if (!empty($remoteRevision)) {
            foreach ($output as $line) {
                if ($line[0] === 'A' or $line[0] === 'C' or $line[0] === 'M' or $line[0] === 'T') {
                    $filesToUpload[] = trim(substr($line, 1));
                } elseif ($line[0] == 'D' or $line[0] === 'T') {
                    $filesToDelete[] = trim(substr($line, 1));
                } else {
                    throw new \Exception("Unsupported git-diff status: {$line[0]}");
                }
            }
        } else {
            $filesToUpload = $output;
        }

        $filteredFilesToUpload = $this->filterIgnoredFiles($filesToUpload);
        $filteredFilesToDelete = $this->filterIgnoredFiles($filesToDelete);
        $filteredFilesToInclude = isset($this->filesToInclude[$this->currentlyDeploying]) ? $this->filterIncludedFiles($this->filesToInclude[$this->currentlyDeploying]) : [];

        $filesToUpload = array_merge($filteredFilesToUpload['files'], $filteredFilesToInclude);
        $filesToDelete = $filteredFilesToDelete['files'];

        $filesToSkip = array_merge($filteredFilesToUpload['filesToSkip'], $filteredFilesToDelete['filesToSkip']);

        return [
            $this->currentlyDeploying => [
                'delete'  => $filesToDelete,
                'upload'  => $filesToUpload,
                'exclude' => $filesToSkip,
            ],
        ];
    }

    /**
     * Update the current remote server with the array of files provided.
     *
     * @param array $files 2-dimensional array with 2 indices: 'upload' and 'delete'
     *                     Each of these contains an array of filenames and paths (relative to repository root)
     */
    public function push($files)
    {
        // We will write this in the server
        $this->localRevision = $this->currentRevision();

        $initialBranch = $this->currentBranch();

        // If revision is not HEAD, the current one, it means this is a rollback.
        // So, we have to revert the files the the state they were in that revision.
        if ($this->revision != 'HEAD') {
            $this->cli->out('   Rolling back working copy');

            // BUG: This does NOT work correctly for submodules & subsubmodules (and leaves them in an incorrect state)
            //      It technically should do a submodule update in the parent, not a checkout inside the submodule
            $this->git->command('checkout ' . $this->revision);
        }

        $filesToDelete = $files['delete'];
        // Add deleted directories to the list of files to delete. Git does not handle this.
        $dirsToDelete = [];
        if (count($filesToDelete) > 0) {
            $dirsToDelete = $this->hasDeletedDirectories($filesToDelete);
        }
        $filesToUpload = $files['upload'];

        // Not needed any longer
        unset($files);

        // Delete files
        if (count($filesToDelete) > 0) {
            foreach ($filesToDelete as $fileNo => $file) {
                if ($this->currentSubmoduleName) {
                    $file = $this->currentSubmoduleName . '/' . $file;
                }
                $numberOfFilesToDelete = count($filesToDelete);
                $fileNo = str_pad(++$fileNo, strlen($numberOfFilesToDelete), ' ', STR_PAD_LEFT);
                if ($this->connection->has($file)) {
                    $this->connection->delete($file);
                    $this->cli->out("<red> × $fileNo of $numberOfFilesToDelete <white>{$file}");
                } else {
                    $this->cli->out("<red> ! $fileNo of $numberOfFilesToDelete <white>{$file} not found");
                }
            }
        }

        // Delete Directories
        if (count($dirsToDelete) > 0) {
            foreach ($dirsToDelete as $dirNo => $dir) {
                if ($this->currentSubmoduleName) {
                    $dir = $this->currentSubmoduleName . '/' . $dir;
                }
                $numberOfdirsToDelete = count($dirsToDelete);
                $dirNo = str_pad(++$dirNo, strlen($numberOfdirsToDelete), ' ', STR_PAD_LEFT);
                if ($this->connection->has($dir)) {
                    $this->connection->deleteDir($dir);
                    $this->cli->out("<red> × $dirNo of $numberOfdirsToDelete <white>{$dir}");
                } else {
                    $this->cli->out("<red> ! $dirNo of $numberOfdirsToDelete <white>{$dir} not found");
                }
            }
        }

        // Upload Files
        if (count($filesToUpload) > 0) {
            foreach ($filesToUpload as $fileNo => $file) {
                if ($this->currentSubmoduleName) {
                    $file = $this->currentSubmoduleName . '/' . $file;
                }

                // Make sure the folder exists in the FTP server.
                $dir = explode('/', dirname($file));
                $path = '';
                $ret = true;

                // Skip mkdir if dir is basedir
                if ($dir[0] !== '.') {
                    // Loop through each folder in the path /a/b/c/d.txt to ensure that it exists
                    // @TODO Can be improved by using: $filesystem->write('path/to/file.txt', 'contents');
                    for ($i = 0, $count = count($dir); $i < $count; ++$i) {
                        $path .= $dir[$i] . '/';
                        if (!isset($pathsThatExist[$path])) {
                            if (!$this->connection->has($path)) {
                                $this->connection->createDir($path);
                                $this->cli->out(" + Created directory '$path'.");
                                $pathsThatExist[$path] = true;
                            } else {
                                $pathsThatExist[$path] = true;
                            }
                        }
                    }
                }

                $filePath = $this->repo . '/' . ($this->currentSubmoduleName ? str_replace($this->currentSubmoduleName . '/', '', $file) : $file);
                $data = @file_get_contents($filePath);

                // It can happen the path is wrong, especially with included files.
                if (!$data) {
                    $this->cli->error(' ! File not found - please check path: ' . $filePath);
                    continue;
                }

                $remoteFile = $file;
                $uploaded = $this->connection->put($remoteFile, $data);

                if (!$uploaded) {
                    $this->cli->error(" ! Failed to upload {$file}.");
                } else {
                    $this->deploymentSize += filesize($this->repo . '/' . ($this->currentSubmoduleName ? str_replace($this->currentSubmoduleName . '/', '', $file) : $file));
                }

                $numberOfFilesToUpdate = count($filesToUpload);

                $fileNo = str_pad(++$fileNo, strlen($numberOfFilesToUpdate), ' ', STR_PAD_LEFT);
                $this->cli->lightGreen(" ^ $fileNo of $numberOfFilesToUpdate <white>{$file}");
            }
        }

        if (count($filesToUpload) > 0 or count($filesToDelete) > 0) {
            // Set revision on server
            $this->setRevision();
        } else {
            $this->cli->gray()->out('   No files to upload or delete.');
        }

        // If $this->revision is not HEAD, it means the rollback command was provided
        // The working copy was rolled back earlier to run the deployment, and we now
        // want to return the working copy back to its original state.
        if ($this->revision != 'HEAD') {
            $this->git->command('checkout ' . ($initialBranch ? : 'master'));
        }
    }

    /**
     * Sets revision on the server.
     */
    public function setRevision()
    {
        $localRevision = $this->currentRevision();

        if ($this->sync && $this->sync != 'sync') {
            $localRevision = $this->sync;
        }

        if ($this->sync) {
            $this->cli->info("SYNC: $localRevision");
        }

        $this->debug('Updating remote revision file to ' . $localRevision);

        $this->connection->put($this->dotRevision, $localRevision);
    }

    /**
     * Get current revision.
     *
     * @return string with current revision hash
     */
    private function currentRevision()
    {
        return $this->git->revision;
    }

    /**
     * Gets the current branch name.
     *
     * @return string - current branch name or false if not in branch
     */
    private function currentBranch()
    {
        $currentBranch = $this->git->branch;
        if ($currentBranch != 'HEAD') {
            return $currentBranch;
        }

        return false;
    }

    /**
     * Check for submodules.
     *
     * @param string $repo
     */
    public function checkSubmodules($repo)
    {
        if ($this->scanSubmodules) {
            $this->cli->out('Scanning repository...');
        }

        $output = $this->git->command('submodule status', $repo);

        if ($this->scanSubmodules) {
            $this->cli->out('   Found ' . count($output) . ' submodules.');
        }

        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));

                // If submodules are turned off, don't add them to queue
                if ($this->scanSubmodules) {
                    $this->submodules[] = [
                        'revision' => $line[0],
                        'name'     => $line[1],
                        'path'     => $repo . '/' . $line[1],
                    ];
                    $this->cli->out(sprintf('   Found submodule %s. %s', $line[1], $this->scanSubSubmodules ? PHP_EOL . '      Scanning for sub-submodules...' : null
                    ));
                }

                $this->globalFilesToExclude[] = $line[1];

                $this->checkSubSubmodules($repo, $line[1]);
            }
            if (!$this->scanSubSubmodules) {
                $this->cli->out('   Skipping search for sub-submodules.');
            }
        }
    }

    /**
     * Check for sub-submodules.
     *
     * @todo This function is quite slow (at least on Windows it often takes several seconds for each call).
     *       Can it be optimized?
     *       It appears that this is called for EACH submodule, but then also does another `git submodule foreach`
     *
     * @param string $repo
     * @param string $name
     */
    public function checkSubSubmodules($repo, $name)
    {
        $output = $this->git->command('submodule foreach git submodule status', $repo);

        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));

                // Skip if string start with 'Entering'
                if (trim($line[0]) == 'Entering') {
                    continue;
                }

                // If sub-submodules are turned off, don't add them to queue
                if ($this->scanSubmodules && $this->scanSubSubmodules) {
                    $this->submodules[] = [
                        'revision' => $line[0],
                        'name'     => $name . '/' . $line[1],
                        'path'     => $repo . '/' . $name . '/' . $line[1],
                    ];
                    $this->cli->out(sprintf('      Found sub-submodule %s.', "$name/$line[1]"));
                }

                // But ignore them nonetheless
                $this->globalFilesToExclude[] = $line[1];
            }
        }
    }

    /**
     * Purge given directory's contents.
     *
     * @var string
     */
    public function purge($purgeDirs)
    {
        foreach ($purgeDirs as $dir) {
            
            $this->cli->out("<red>Purging directory <white>{$dir}");

            if (!$tmpFiles = $this->connection->listContents($dir, true)) {
                $this->cli->out(" - Nothing to purge in {$dir}");
                continue;
            }

            $haveFiles = false;
            $innerDirs = [];
            foreach ($tmpFiles as $file) {
                $haveFiles = true;                
                if ($this->connection->delete($file)) {
                    $this->cli->out(" - {$file} is removed from directory");
                    $innerDirs[] = $file;
                }
            }

            if (!$haveFiles) {
                $this->cli->out(" - Nothing to purge in {$dir}");
            } else {
                $this->cli->out("<red>Purged <white>{$dir}");
            }

            if (count($innerDirs) > 0) {
                // Recursive purging
                //$this->purge($innerDirs);
            }
        }
    }

    /**
     * Execute pre commands
     *
     * @var array
     */
    public function preDeploy(array $commands)
    {
        foreach ($commands as $command) {

            $this->cli->out("Execute : <white>{$command}");

            $this->git->exec($command);
        }
    }

    /**
     * Execute post commands
     *
     * @var array
     */
    public function postDeploy(array $commands)
    {
        foreach ($commands as $command) {

            $this->cli->out("Execute : <white>{$command}");

            $this->git->exec($command);
        }
    }

    /**
     * Checks for deleted directories. Git cares only about files.
     *
     * @param array $filesToDelete
     */
    public function hasDeletedDirectories($filesToDelete)
    {
        $dirsToDelete = [];
        foreach ($filesToDelete as $file) {

            // Break directories into a list of items
            $parts = explode('/', $file);
            // Remove files name from the list
            array_pop($parts);

            foreach ($parts as $i => $part) {
                $prefix = '';
                // Add the parent directories to directory name
                for ($x = 0; $x < $i; ++$x) {
                    $prefix .= $parts[$x] . '/';
                }

                $part = $prefix . $part;

                // If directory doesn't exist, add to files to delete
                // Relative path won't work consistently, thus getcwd().
                if (!is_dir(getcwd() . '/' . $part)) {
                    $dirsToDelete[] = $part;
                }
            }
        }

        // Remove duplicates
        $dirsToDeleteUnique = array_unique($dirsToDelete);

        // Reverse order to delete inner children before parents
        $dirsToDeleteOrder = array_reverse($dirsToDeleteUnique);

        $this->debug('Directories to be deleted: ' . print_r($dirsToDeleteOrder, true));

        return $dirsToDeleteOrder;
    }

    /**
     * Helper method to output messages to the console (only in debug mode)
     * Debug mode is activated by setting $this->debug = true or using the command line option --debug.
     *
     * @param string $message Message to display on the console
     */
    public function debug($message)
    {
        if ($this->debug) {
            $this->cli->comment("$message");
        }
    }

    /**
     * Get an array that represents directory tree
     * Credit: http://php.net/manual/en/function.scandir.php#109140
     *
     * @param string $directory     Directory path
     * @param bool $recursive         Include sub directories
     * @param bool $listDirs         Include directories on listing
     * @param bool $listFiles         Include files on listing
     * @param regex $exclude         Exclude paths that matches this regex
     */
    public function directoryToArray($directory, $recursive = true, $listDirs = false, $listFiles = true, $exclude = '')
    {
        $arrayItems = array();
        $skipByExclude = false;
        $handle = opendir($directory);
        if ($handle) {
            while (false !== ($file = readdir($handle))) {
                preg_match("/(^(([\.]){1,2})$|(\.(svn|git|md))|(Thumbs\.db|\.DS_STORE))$/iu", $file, $skip);
                if ($exclude) {
                    preg_match($exclude, $file, $skipByExclude);
                }
                if (!$skip && !$skipByExclude) {
                    if (is_dir($directory . DIRECTORY_SEPARATOR . $file)) {
                        if ($recursive) {
                            $arrayItems = array_merge($arrayItems, $this->directoryToArray($directory . DIRECTORY_SEPARATOR . $file, $recursive, $listDirs, $listFiles, $exclude));
                        }
                        if ($listDirs) {
                            $file = $directory . DIRECTORY_SEPARATOR . $file;
                            $arrayItems[] = $file;
                        }
                    } else {
                        if ($listFiles) {
                            $file = $directory . DIRECTORY_SEPARATOR . $file;
                            $arrayItems[] = $file;
                        }
                    }
                }
            }
            closedir($handle);
        }
        return $arrayItems;
    }

    /**
     * Strip Absolute Path
     */
    static function relPath($el)
    {
        $abs = getcwd() . '/';
        return str_replace($abs, "", $el);
    }

    /**
     * Creates sample ini file.
     */
    private function createSampleIniFile()
    {
        $data = "; NOTE: If non-alphanumeric characters are present, enclose in value in quotes.\n
[staging]
    quickmode = ftp://example:password@production-example.com:21/path/to/installation\n
[staging]
    scheme = sftp
    user = example
    pass = password
    host = staging-example.com
    path = /path/to/installation
    port = 22";

        if (file_put_contents(getcwd() . '/phploy.ini', $data)) {
            $this->cli->info("\nSample phploy.ini file created.\n");
        }
    }

}
