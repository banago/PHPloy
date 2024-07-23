<?php

/**
 * PHPloy - A PHP Deployment Tool.
 *
 * @author Baki Goxhaj <banago@gmail.com>
 *
 * @link https://github.com/banago/PHPloy
 * @licence MIT Licence
 *
 * @version 4.9.3
 */

namespace Banago\PHPloy;

use League\Flysystem\FilesystemException;
use League\CLImate\CLImate;

define('QUOTE', "'");
define('DQUOTE', '"');

class PHPloy
{
    /**
     * @var string
     */
    protected $version = '4.9.3';

    /**
     * @var string
     */
    public $revision = 'HEAD';

    /**
     * @var \League\CLImate\CLImate
     */
    public $cli;

    /**
     * @var Git
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
    public $currentServerName = '';

    /**
     * The local directory that corresponds to the remote base directory. Defaults to an empty string, which corresponds
     * to the Git repository base. This needs to end with '/'.
     */
    public string $base = '';

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
    public $copyDirs = [];

    /**
     * @var array
     */
    public $purgeBeforeDirs = [];

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
     * @var array
     */
    public $preDeployRemote = [];

    /**
     * @var array
     */
    public $postDeployRemote = [];

    /**
     * The name of the file on remote servers that stores the current revision hash.
     *
     * @var string
     */
    public $dotRevisionFileName = '.revision';

    /**
     * The filename from which to read remote server details.
     *
     * @var string
     */
    public $iniFileName = 'phploy.ini';

    /**
     * The filename from which to read server password.
     *
     * @var string
     */
    public $passFile = '.phploy';

    /**
     * @var \League\Flysystem\Filesystem;
     */
    protected ?\League\Flysystem\Filesystem $connection = null;

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
     * For the main repository this will be the value of $dotRevisionFileName ('.revision' by default)
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
     */
    protected string $sync = '';

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
     * Whether the --force command line option was given.
     *
     * @var bool init
     */
    protected $force = false;

    /**
     * Whether the --fresh command line option was given.
     *
     * @var bool init
     */
    protected $fresh = false;

    /**
     * @var Options
     */
    protected $opt;

    /**
     * @var array
     */
    private $currentServerInfo;

    /**
     * Constructor.
     *
     * @param Options|null $opt an optional set of Options, if null options will be read from CLI args
     * @throws \Exception
     */
    public function __construct(Options $opt = null)
    {
        $this->opt = $opt !== null ? $opt : new Options(new \League\CLImate\CLImate());
        $this->cli = $this->opt->cli;

        $this->cli->backgroundGreen()->bold()->out('-------------------------------------------------');
        $this->cli->backgroundGreen()->bold()->out('|                     PHPloy                    |');
        $this->cli->backgroundGreen()->bold()->out('-------------------------------------------------');

        if ($this->cli->arguments->defined('dryrun')) {
            $this->cli->bold()->yellow('DRY RUN, PHPloy will not check or alter the remote servers');
        }

        // Setup PHPloy
        $this->setup();

        // Check if only valid arguments are given
        // @Todo: Breaks this format: --sync="asdfasdfads"
        $arg = $this->checkArguments();
        if ($arg) {
            $this->cli->bold()->error("Argument '{$arg}' is unknown.");
            $this->cli->usage();

            return;
        };

        if ($this->cli->arguments->get('help')) {
            $this->cli->usage();

            return;
        }

        if ($this->cli->arguments->get('init')) {
            $this->createIniFile();

            return;
        }

        if ($this->cli->arguments->get('version')) {
            $this->cli->bold()->info('PHPloy v'.$this->version);

            return;
        }

        if ($this->cli->arguments->get('dryrun')) {
            $this->cli->bold()->yellow('DRY RUN, PHPloy will not go any further');

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

        if ($this->cli->arguments->defined('force')) {
            $this->force = true;
        }

        if ($this->cli->arguments->defined('fresh')) {
            $this->fresh = true;
        }

        $this->repo = getcwd();
        $this->mainRepo = $this->repo;
    }

    /**
     * Checks if all given arguments are defined.
     *
     * @return string the argument that is undefined, or FALSE if all arguments are defined
     */
    public function checkArguments() : string|false
    {
        $prefixes = array_reduce($this->cli->arguments->all(), function ($result, $a) {
            if ($a->prefix()) {
                $result[] = '-'.$a->prefix();
            };

            return $result;
        }, []);

        $prefixes = array_reduce($this->cli->arguments->all(), function ($result, $a) {
            if ($a->longprefix()) {
                $result[] = '--'.$a->longprefix();
            };

            return $result;
        }, $prefixes);

        global $argv;
        foreach ($argv as $arg) {
            if (strpos($arg, '-') === 0 && !in_array($arg, $prefixes)) {
                return $arg;
            }
        }

        return false;
    }

    /**
     * Parse an ini file and return values as array.
     *
     * @param string $iniFile
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function parseIniFile($iniFile)
    {
        if (!file_exists($iniFile)) {
            throw new \Exception("'$iniFile' does not exist.");
        } else {
            $values = parse_ini_file($iniFile, true);
            if (!$values) {
                throw new \Exception("'$iniFile' is not a valid .ini file.");
            } else {
                return $values;
            }
        }
    }

    /**
     * Prepares the default value with an optional shared configuration
     *
     * @param array $iniShared the shared configuration read from the ini file with the '*' special server name
     * @return array an array of default values
     */
    public function prepareDefaults($iniShared = [])
    {
        $defaults = [
            'scheme' => 'ftp',
            'host' => '',
            'user' => '',
            'pass' => '',
            'path' => '/',
            'privkey' => '',
            'port' => null,
            'passive' => null,
            'timeout' => null,
            'ssl' => false,
            'visibility' => 'public',
            'permPublic' => 0774,
            'permPrivate' => 0700,
            'permissions' => null,
            'directoryPerm' => 0755,
            'branch' => '',
            'include' => [],
            'exclude' => [],
            'copy' => [],
            'purge' => [],
            'purge-before' => [],
            'pre-deploy' => [],
            'post-deploy' => [],
            'pre-deploy-remote' => [],
            'post-deploy-remote' => [],
        ];
        if (!empty($iniShared)) {
            foreach ($defaults as $name => $value) {
                if (isset($iniShared[$name])) {
                    $defaults[$name] = $iniShared[$name];
                }
            }
        }

        return $defaults;
    }

    /**
     * Merges two array options. This method should be used to merge shared configurations and preserve "array" values
     * like "exclude" or "include" keys.
     *
     * @param array $existing the existing options in which to merge the new options
     * @param array $new the new options to merge into the existing options
     * @param bool $overwriteArrayValues true to overwrite (not merge) values which are arrays, false otherwise
     * @return array
     */
    private function mergeOptions($existing, $new, $overwriteArrayValues = false)
    {
        $merged = $existing;
        foreach ($existing as $k => $v) {
            if (!$overwriteArrayValues && is_array($v) && isset($new[$k]) && is_array($new[$k])) {
                $merged[$k] = array_merge($v, $new[$k]);
            } elseif (isset($new[$k])) {
                $merged[$k] = $new[$k];
            } else {
                $merged[$k] = $v;
            }
        }
        foreach ($new as $k => $v) {
            if (!is_array($v)) {
                $merged[$k] = $v;
            }
        }
        return $merged;
    }

    /**
     * Reads the phploy.ini file and populates the $this->servers array.
     *
     * @param string $iniFile an optional ini file path to read server configuration from, defaults to null which means
     * it will use the CLI args
     * @return array an associative array of server names and options
     * @throws \Exception
     */
    public function prepareServers($iniFile = null)
    {
        if (empty($iniFile)) {
            $iniFile = $this->repo . DIRECTORY_SEPARATOR . $this->iniFileName;
        }

        $servers = $this->parseIniFile($iniFile);

        $defaults = $this->prepareDefaults(isset($servers['*']) ? $servers['*'] : []);

        unset($servers['*']);

        foreach ($servers as $name => $options) {

            // If a server is specified, skip others
            if ($this->server != '' && $this->server != $name) {
                continue;
            }

            if ($name == 'default') {
                $this->defaultServer = true;
            }

            $options = $this->mergeOptions($defaults, $options);

            if (isset($options['quickmode'])) {
                $options = array_merge($options, parse_url($options['quickmode']));
            }

            // Set host from environment variable if it does not exist in the config
            if (empty($options['host']) && !empty(getenv('PHPLOY_HOST'))) {
                $options['host'] = getenv('PHPLOY_HOST');
            }

            // Set port number from environment variable if it does not exist in the config
            if (empty($options['port']) && !empty(getenv('PHPLOY_PORT'))) {
                $options['port'] = getenv('PHPLOY_PORT');
            }

            // Set username from .phploy config file or environment variable if it does not exist in the config
            if (empty($options['user'])) {

                // Look for .phploy config file
                if (file_exists($this->getPasswordFile())) {
                    $options['user'] = $this->getUserFromIniFile($name);
                } elseif (!empty(getenv('PHPLOY_USER'))) {
                    $options['user'] = getenv('PHPLOY_USER');
                } else {
                    $this->cli->red()->out('No user has been provided.');
                }
            }

            if (empty($options['privkey']) && !empty(getenv('PHPLOY_PRIVKEY'))) {
                $options['privkey'] = getenv('PHPLOY_PRIVKEY');
            }

            // Ask for a password if it is empty and a private key is not provided
            if ($options['pass'] === '' && $options['privkey'] === '') {
                // Look for .phploy config file
                if (file_exists($this->getPasswordFile())) {
                    $options['pass'] = $this->getPasswordFromIniFile($name);
                    $options['privkey'] = $this->getPrivateKeyFromIniFile($name);
                } elseif (!empty(getenv('PHPLOY_PASS'))) {
                    $options['pass'] = getenv('PHPLOY_PASS');
                } else {
                    fwrite(STDOUT, 'No password has been provided for user "'.$options['user'].'". Please enter a password: ');
                    $options['pass'] = input_password();
                    $this->cli->lightGreen()->out(PHP_EOL . 'Password received. Continuing deployment ...');
                }
            }

            // Ignoring for the win
            $this->filesToExclude[$name] = $this->globalFilesToExclude;
            $this->filesToExclude[$name][] = $this->iniFileName;

            if (!empty($options['base'])) {
                $this->base = rtrim($options['base'], '/').'/';
            }

            if (!empty($options['exclude'])) {
                $this->filesToExclude[$name] = array_merge($this->filesToExclude[$name], $options['exclude']);
            }

            if (!empty($options['include'])) {
                $this->filesToInclude[$name] = $options['include'];
            }

            if (!empty($options['copy'])) {
                $this->copyDirs[$name] = $options['copy'];
            }

            if (!empty($options['purge-before'])) {
                $this->purgeBeforeDirs[$name] = $options['purge-before'];
            }

            if (!empty($options['purge'])) {
                $this->purgeDirs[$name] = $options['purge'];
            }

            if (!empty($options['pre-deploy'])) {
                $this->preDeploy[$name] = $options['pre-deploy'];
            }

            if (!empty($options['post-deploy'])) {
                $this->postDeploy[$name] = $options['post-deploy'];
            }

            if (!empty($options['pre-deploy-remote'])) {
                $this->preDeployRemote[$name] = $options['pre-deploy-remote'];
            }

            if (!empty($options['post-deploy-remote'])) {
                $this->postDeployRemote[$name] = $options['post-deploy-remote'];
            }

            // Set the path from environment variable if it does not exist in the config
            if ($options['path'] === '/' && !empty(getenv('PHPLOY_PATH'))) {
                $options['path'] = getenv('PHPLOY_PATH');
            }

            $this->servers[$name] = $options;
        }

        return $this->servers;
    }

    /**
     * Returns the full path to password file.
     *
     * @return string
     */
    public function getPasswordFile()
    {
        return $this->repo.DIRECTORY_SEPARATOR.$this->passFile;
    }

    /**
     * Try to fetch password from .phploy file if not found, an empty string will be returned.
     *
     * @param string $servername Server to fetch password for
     *
     * @return string
     * @throws \Exception
     */
    public function getPasswordFromIniFile($servername)
    {
        $values = $this->parseIniFile($this->getPasswordFile());
        if (isset($values[$servername]['pass']) === true) {
            return $values[$servername]['pass'];
        }

        if (isset($values[$servername]['password']) === true) {
            throw new \Exception('Please rename password to pass in '.$this->getPasswordFile());
        }

        return '';
    }

    /**
     * Try to fetch user from .phploy file, if not found an empty string will be returned.
     *
     * @param string $servername Server to fetch user for
     *
     * @return string
     */
    public function getUserFromIniFile($servername)
    {
        $values = $this->parseIniFile($this->getPasswordFile());
        if (isset($values[$servername]['user']) === true) {
            return $values[$servername]['user'];
        }

        return '';
    }

    /**
     * Try to fetch private key from .phploy file if not found, an empty string will be returned.
     *
     * @param string $servername Server to fetch user for
     *
     * @return string
     * @throws \Exception
     */
    public function getPrivateKeyFromIniFile($servername) {
        $values = $this->parseIniFile($this->getPasswordFile());
        if (isset($values[$servername]['privkey']) === true) {
            return $values[$servername]['privkey'];
        }

        return '';
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
            foreach ($this->filesToExclude[$this->currentServerName] as $pattern) {
                if (pattern_match($pattern, $file)) {
                    unset($files[$i]);
                    $filesToSkip[] = $file;
                    break;
                }
            }
        }

        $files = array_values($files);

        return [
            'files' => $files,
            'filesToSkip' => $filesToSkip,
        ];
    }

    /**
     * Filter files form base path.
     *
     * @param array $files Array of files which needed to be filtered
     *
     * @return array
     */
    private function filterBasePathFiles(array $files) : array
    {
        if (!$this->base) {
            return $files;
        }

        $base = $this->base;


        return array_values(
            array_filter(
                $files,
                function ($file) use ($base) {
                    return preg_match('/^'.preg_quote($base, '/').'/', $file);
                }
            )
        );
    }

    /**
     * Remove bath path from file / path
     *
     * @param string $file
     *
     * @return string
     */
    private function removeBasePath(string $file) : string
    {
        return $this->base ? preg_replace('/^'.preg_quote($this->base, '/').'/', '', $file) : $file;
    }

    /**
     * Filter included files.
     *
     * @param array $files        Array of files which needed to be filtered
     * @param array $changedFiles Array of files changed since last upload
     *
     * @return array $filteredFiles
     */
    private function filterIncludedFiles($files, $changedFiles)
    {
        $filteredFiles = [];
        foreach ($files as $i => $file) {
            $condition = explode(':', $file);
            if (isset($condition[1])) {
                list($file, $changed) = $condition;
            }

            if (empty($changed) || in_array($changed, $changedFiles)) {
                $name = getcwd().'/'.$file;
                if (is_dir($name)) {
                    $filteredFiles = array_merge($filteredFiles, array_map('rel_path', dir_tree($name, true)));
                } else {
                    $filteredFiles[] = $file;
                }
            }
        }

        return $filteredFiles;
    }

    /**
     * Connect to server.
     * @throws \Exception
     */
    public function connect($server)
    {
        $connection = new Connection($server);
        $this->connection = $connection->server;
    }

    /**
     * Deploy (or list) changed files.
     * @throws \Exception
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
            throw new \Exception("The server \"{$this->server}\" is not defined in {$this->iniFileName}.");
        }

        // Loop through all the servers in phploy.ini
        foreach ($this->servers as $name => $server) {
            $this->currentServerName = $name;
            $this->currentServerInfo = $server;

            // If a server is specified, it's deployed only to that
            if ($this->server != '' && $this->server != $name) {
                continue;
            }

            // If no server was specified in the command line but a default server
            // configuration exists, we'll use that (as long as --all was not specified)
            elseif ($this->server == '' && $this->defaultServer == true && $name != 'default' && $this->deployAll == false) {
                continue;
            }

            if ($this->force) {
                $this->cli->comment("Creating deployment directory: '".$server['path']."'.");

                $path = $server['path'];
                $server['path'] = '/';

                $this->connect($server);

                $this->connection->createDirectory($path);
                $this->cli->green('Deployment directory created. Ready to deploy.');

                $this->connection = null;
                $server['path'] = $path;
            }

            $this->connect($server);

            if ($this->sync) {
                $this->dotRevision = $this->dotRevisionFileName;
                $this->setRevision();
                continue;
            }

            $files = $this->compare($this->revision);

            $this->cli->bold()->white()->out(PHP_EOL . "SERVER: {$name}");

            if ($this->listFiles) {
                $this->listFiles($files[$this->currentServerName]);

                if ($this->scanSubmodules && count($this->submodules) > 0) {
                    foreach ($this->submodules as $submodule) {
                        $this->repo = $submodule['path'];
                        $this->currentSubmoduleName = $submodule['name'];

                        $this->cli->gray()->out("SUBMODULE: ".$this->currentSubmoduleName);
                        $files = $this->compare($submodule['revision']);
                        $this->listFiles($files[$this->currentServerName]);
                    }
                    // We've finished deploying submodules, reset settings for the next server
                    $this->repo = $this->mainRepo;
                    $this->currentSubmoduleName = '';
                }
            } else {
                // Pre Deploy
                if (isset($this->preDeploy[$name]) && count($this->preDeploy[$name]) > 0) {
                    $this->preDeploy($this->preDeploy[$name]);
                }
                // Pre Deploy Remote
                if (isset($this->preDeployRemote[$name]) && count($this->preDeployRemote[$name]) > 0) {
                    $this->preDeployRemote($this->preDeployRemote[$name]);
                }
                // Purge before deploy
                if (isset($this->purgeBeforeDirs[$name]) && count($this->purgeBeforeDirs[$name]) > 0) {
                    $this->purge($this->purgeBeforeDirs[$name]);
                }
                // Push repository
                $this->push($files[$this->currentServerName]);
                // Push Submodules
                if ($this->scanSubmodules && count($this->submodules) > 0) {
                    foreach ($this->submodules as $submodule) {
                        $this->repo = $submodule['path'];
                        $this->currentSubmoduleName = $submodule['name'];

                        $this->cli->gray()->out(PHP_EOL . "SUBMODULE: {$this->currentSubmoduleName}");
                        $files = $this->compare($submodule['revision']);

                        if ($this->listFiles === true) {
                            $this->listFiles($files[$this->currentServerName]);
                        } else {
                            $this->push($files[$this->currentServerName], $submodule['revision']);
                        }
                    }
                    // We've finished deploying submodules, reset settings for the next server
                    $this->repo = $this->mainRepo;
                    $this->currentSubmoduleName = '';
                }
                // Copy
                if (isset($this->copyDirs[$name]) && count($this->copyDirs[$name]) > 0) {
                    $this->copy($this->copyDirs[$name]);
                }
                // Purge
                if (isset($this->purgeDirs[$name]) && count($this->purgeDirs[$name]) > 0) {
                    $this->purge($this->purgeDirs[$name]);
                }
                // Post Deploy
                if (isset($this->postDeploy[$name]) && count($this->postDeploy[$name]) > 0) {
                    $this->postDeploy($this->postDeploy[$name]);
                }
                // Post Deploy Remote
                if (isset($this->postDeployRemote[$name]) && count($this->postDeployRemote[$name]) > 0) {
                    $this->postDeployRemote($this->postDeployRemote[$name]);
                }
            }

            // Done
            if (!$this->listFiles) {
                $this->cli->bold()->lightGreen(PHP_EOL . "|---------------[ ".human_filesize($this->deploymentSize).' Deployed ]---------------|');
                $this->deploymentSize = 0;
            }
        }
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
                $this->cli->out('      '.$file_to_delete);
            }
        }

        if (count($files['upload']) > 0) {
            $this->cli->lightGreen('   Files that will be uploaded in next deployment:');

            foreach ($files['upload'] as $file_to_upload) {
                $this->cli->out('      '.$file_to_upload);
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
     * @throws \Exception if unknown git diff status
     *
     * @return array
     */
    public function compare($localRevision)
    {
        $remoteRevision = null;
        $filesToUpload = [];
        $filesToDelete = [];

        if ($this->currentSubmoduleName) {
            $this->dotRevision = $this->currentSubmoduleName.'/'.$this->dotRevisionFileName;
        } else {
            $this->dotRevision = $this->dotRevisionFileName;
        }

        if ($this->fresh) {
            $this->cli->out('Manual fresh upload...');
        } elseif ($this->connection->has($this->dotRevision)) {
            $remoteRevision = $this->connection->read($this->dotRevision);
            $this->debug('Remote revision: <bold>'.$remoteRevision);
        } else {
            $this->cli->out('No revision found. Fresh upload...');
        }

        if (!empty($this->servers[$this->currentServerName]['branch'])) {
            $output = $this->git->checkout($this->servers[$this->currentServerName]['branch'], $this->repo);

            if (isset($output[0])) {
                if (strpos($output[0], 'error') === 0) {
                    throw new \Exception('Stash your modifications before deploying.');
                }
            }

            if (isset($output[1])) {
                if ($output[1][0] === 'M') {
                    throw new \Exception('Stash your modifications before deploying.');
                }
            }

            if (isset($output[0])) {
                $this->cli->out($output[0]);
            }
        }

        $output = $this->git->diff($remoteRevision, $localRevision, $this->repo);
        $this->debug(implode(PHP_EOL, $output));

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
                $status = $line[0];

                if (strpos($line, 'warning: CRLF will be replaced by LF in') !== false) {
                    continue;
                } elseif (strpos($line, 'The file will have its original line endings in your working directory.') !== false) {
                    continue;
                } elseif ($status === 'A' or $status === 'C' or $status === 'M' or $status === 'T') {
                    $filesToUpload[] = trim(substr($line, 1));
                } elseif ($status == 'D') {
                    $filesToDelete[] = trim(substr($line, 1));
                } elseif ($status === 'R') {
                    list(, $oldFile, $newFile) = preg_split('/\s+/', $line);

                    $filesToDelete[] = trim($oldFile);
                    $filesToUpload[] = trim($newFile);
                } else {
                    throw new \Exception("Unknown git-diff status. Use '--sync' to update remote revision or use '--debug' to see what's wrong.");
                }
            }
        } else {
            $filesToUpload = $output;
        }

        $filteredFilesToUpload = $this->filterIgnoredFiles($filesToUpload);
        $filteredFilesToDelete = $this->filterIgnoredFiles($filesToDelete);
        $filteredFilesToInclude = isset($this->filesToInclude[$this->currentServerName]) ? $this->filterIncludedFiles($this->filesToInclude[$this->currentServerName], $filesToUpload) : [];

        $filesToUpload = array_merge($filteredFilesToUpload['files'], $filteredFilesToInclude);
        $filesToDelete = $filteredFilesToDelete['files'];

        $filesToUpload = $this->filterBasePathFiles($filesToUpload);
        $filesToDelete = $this->filterBasePathFiles($filesToDelete);

        $filesToSkip = array_merge($filteredFilesToUpload['filesToSkip'], $filteredFilesToDelete['filesToSkip']);

        return [
            $this->currentServerName => [
                'delete' => $filesToDelete,
                'upload' => $filesToUpload,
                'exclude' => $filesToSkip,
            ],
        ];
    }

    /**
     * Update the current remote server with the array of files provided.
     *
     * @param array $files 2-dimensional array with 2 indices: 'upload' and 'delete'
     *                     Each of these contains an array of filenames and paths.
     * @throws \Exception
     */
    public function push($files, $localRevision = null)
    {
        if (empty($localRevision)) {
            // We will write this in the server
            $localRevision = $this->currentRevision();
        }

        $initialBranch = $this->currentBranch();

        // If revision is not HEAD, the current one, it means this is a rollback.
        // So, we have to revert the files the the state they were in that revision.
        if ($this->revision != 'HEAD') {
            $this->cli->out('   Rolling back working copy');

            // BUG: This does NOT work correctly for submodules & subsubmodules (and leaves them in an incorrect state)
            //      It technically should do a submodule update in the parent, not a checkout inside the submodule
            $this->git->command('checkout '.$this->revision, $this->repo);
        }

        $filesToDelete = $files['delete'];
        // Add deleted directories to the list of files to delete. Git does not handle this.
        $dirsToDelete = [];
        if (count($filesToDelete) > 0) {
            $dirsToDelete = $this->hasDeletedDirectories($filesToDelete);
        }
        $filesToUpload = $files['upload'];

        unset($files); // No longer needed

        // Upload Files
        if (count($filesToUpload) > 0) {
            foreach ($filesToUpload as $fileNo => $file) {
                if ($this->currentSubmoduleName) {
                    $file = $this->currentSubmoduleName.'/'.$file;
                }

                // Remove base path, if set.
                $fileBaseless = $this->removeBasePath($file);

                // Make sure the folder exists in the FTP server.
                $dir = explode('/', dirname($fileBaseless));
                $path = '';
                $ret = true;

                // Skip mkdir if dir is basedir
                if ($dir[0] !== '.') {
                    // Loop through each folder in the path /a/b/c/d.txt to ensure that it exists
                    // @TODO Can be improved by using: $filesystem->write('path/to/file.txt', 'contents');
                    for ($i = 0, $count = count($dir); $i < $count; ++$i) {
                        $path .= $dir[$i].'/';
                        if (!isset($pathsThatExist[$path])) {
                            if (!$this->connection->has($path)) {
                                $this->connection->createDirectory($path);
                                $this->cli->out(" + Created directory '$path'.");
                                $pathsThatExist[$path] = true;
                            } else {
                                $pathsThatExist[$path] = true;
                            }
                        }
                    }
                }

                $filePath = $this->repo.'/'.($this->currentSubmoduleName ? str_replace($this->currentSubmoduleName.'/', '', $file) : $file);
                $data = @file_get_contents($filePath);

                // It can happen the path is wrong, especially with included files.
                if ($data === false) {
                    $this->cli->error(' ! File not found - please check path: '.$filePath);
                    continue;
                }

                // If base is set, remove it from filename
                $remoteFile = $fileBaseless;

                try {
                  $this->connection->write($remoteFile, $data);
                } catch (FilesystemException $ex) {
                    $this->cli->error(" ! Failed to upload {$fileBaseless}.");

                    if (!$this->connection) {
                        $this->cli->info(' * Connection lost, trying to reconnect...');
                        $this->connect($this->currentServerInfo);
                        $this->connection->write($remoteFile, $data);
                    }
                }

                $this->deploymentSize += filesize($this->repo.'/'.($this->currentSubmoduleName ? str_replace($this->currentSubmoduleName.'/', '', $file) : $file));
                $total = count($filesToUpload);
                $fileNo = str_pad(++$fileNo, strlen($total), ' ', STR_PAD_LEFT);
                $this->cli->lightGreen(" ^ $fileNo of $total <white>{$fileBaseless}");
            }
        }

        // Delete files
        if (count($filesToDelete) > 0) {
            foreach ($filesToDelete as $fileNo => $file) {
                if ($this->currentSubmoduleName) {
                    $file = $this->currentSubmoduleName.'/'.$file;
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
                    $dir = $this->currentSubmoduleName.'/'.$dir;
                }
                $numberOfdirsToDelete = count($dirsToDelete);
                $dirNo = str_pad(++$dirNo, strlen($numberOfdirsToDelete), ' ', STR_PAD_LEFT);
                if ($this->connection->has($dir)) {
                    $this->connection->deleteDirectory($dir);
                    $this->cli->out("<red> × $dirNo of $numberOfdirsToDelete <white>{$dir}");
                } else {
                    $this->cli->out("<red> ! $dirNo of $numberOfdirsToDelete <white>{$dir} not found");
                }
            }
        }

        if (count($filesToUpload) > 0 or count($filesToDelete) > 0) {
            // If $this->revision is not HEAD, it means the rollback command was provided
            if ($this->revision != 'HEAD') {
                // Get rollback revision (current HEAD is on rollback revision)
                $revision = $this->git->command('rev-parse HEAD');
                $this->setRevision($revision[0]);
            } else {
                $this->setRevision($localRevision);
            }
        } else {
            $this->cli->gray()->out('   No files to upload or delete.');
        }

        // If $this->revision is not HEAD, it means the rollback command was provided
        // The working copy was rolled back earlier to run the deployment, and we
        // now want to return the working copy back to its original state.
        if ($this->revision != 'HEAD') {
            $this->git->command('checkout '.($initialBranch ?: 'master'));
        }

        $this->log('[SHA: '.$localRevision.'] Deployment to server: "'.$this->currentServerName.'" from branch "'.
            $initialBranch.'". '.count($filesToUpload).' files uploaded; '.count($filesToDelete).' files deleted.');
    }

    /**
     * Sets revision on the server.
     */
    public function setRevision($localRevision = null)
    {
        if (empty($localRevision)) {
            $localRevision = $this->currentRevision();
        }

        if ($this->sync) {
            if ($this->sync != 'LAST') {
                $localRevision = $this->sync;
            }
            $this->cli->info("Setting remote revision to: $localRevision");
        }

        $this->connection->write($this->dotRevision, $localRevision);
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
    private function currentBranch() : string|false
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
            $this->cli->out('   Found '.count($output).' submodules.');
        }

        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));

                // If submodules are turned off, don't add them to queue
                if ($this->scanSubmodules) {
                    $this->submodules[] = [
                        'revision' => $line[0],
                        'name' => $line[1],
                        'path' => $repo.'/'.$line[1],
                    ];
                    $this->cli->out(sprintf(
                        '   Found submodule %s. %s',
                        $line[1],
                        $this->scanSubSubmodules ? PHP_EOL.'      Scanning for sub-submodules...' : null
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
                        'name' => $name.'/'.$line[1],
                        'path' => $repo.'/'.$name.'/'.$line[1],
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
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function purge($purgeDirs)
    {
        foreach ($purgeDirs as $dir) {
            $this->cli->out("<red>Purging directory <white>{$dir}");

            // Recursive file/dir listing
            $contents = $this->connection->listContents($dir, true)->toArray();

            if (count($contents) < 1) {
                $this->cli->out(" - Nothing to purge in {$dir}");

                return;
            }

            $innerDirs = [];
            foreach ($contents as $item) {
                if ($item['type'] === 'file') {
                    $this->connection->delete($item['path']);
                    $this->cli->out("<red> × {$item['path']} is removed from directory");
                } elseif ($item['type'] === 'dir') {
                    // Directories need to be stacked to be
                    // deleted at the end when they are empty
                    $innerDirs[] = $item['path'];
                }
            }

            if (count($innerDirs) > 0) {
                foreach ($innerDirs as $innerDir) {
                    $this->connection->deleteDirectory($innerDir);
                    $this->cli->out("<red> ×  {$innerDir} directory");
                }
            }

            $this->cli->out("<red>Purged <white>{$dir}");
        }
    }

    /**
     * Copy given directory's contents.
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \League\Flysystem\FileExistsException
     */
    public function copy($copyDirs)
    {
        $dirNameTrimFunc = function ($name) {
            return rtrim(str_replace('\\', '/', trim($name)), '/');
        };

        foreach ($copyDirs as $copyRule) {
            list($fromDir, $toDir) = array_map($dirNameTrimFunc, array_pad(explode('->', $copyRule), 2, '.'));
            // Skip to next element if to and from are the same
            if ($fromDir == $toDir) {
                $this->cli->out("<red>Omitting directory <white>{$fromDir}<red>, as it would copy on itself");
                break;
            }
            // Skip to next element if from is not present
            if (!$this->connection->has($fromDir)) {
                $this->cli->out("<red>Omitting directory <white>{$fromDir}<red>, as it does not exist on the server");
                break;
            }
            $this->cli->out("<red>Copying directory <white>{$fromDir}<red> to <white>{$toDir}");

            // File/dir listing
            $contents = $this->connection->listContents($fromDir, false)->toArray();

            if (count($contents) < 1) {
                $this->cli->out(" - Nothing to copy in {$fromDir}");

                return;
            }

            foreach ($contents as $item) {
                if ($item['type'] === 'file') {
                    $newPath = $toDir.'/'.pathinfo($item['path'], PATHINFO_BASENAME);
                    if ($this->connection->has($newPath)) {
                        $this->connection->delete($newPath);
                    }
                    $this->connection->copy($item['path'], $newPath);
                    $this->cli->out("<red> × {$item['path']} is copied to {$newPath}");
                } elseif ($item['type'] === 'dir') {
                    $dirParts = explode('/', $item['path']);
                    $this->copy([$fromDir.'/'.end($dirParts).'->'.$toDir.'/'.end($dirParts)]);
                }
            }

            $this->cli->out("<red>Copied <white>{$fromDir} <red>to <white>{$toDir}");
        }
    }

    /**
     * Execute pre commands.
     *
     * @param array $commands
     */
    public function preDeploy(array $commands)
    {
        foreach ($commands as $command) {
            $this->cli->out("Execute : <white>{$command}");

            $output = $this->git->exec($command, true);

            $output = implode(PHP_EOL, $output);
            $this->cli->out("Result : <white>{$output}");
        }
    }

    /**
     * Execute post commands.
     *
     * @param array $commands
     */
    public function postDeploy(array $commands)
    {
        foreach ($commands as $command) {
            $this->cli->out("Execute : <white>{$command}");

            $output = $this->git->exec($command);

            $output = implode(PHP_EOL, $output);
            $this->cli->out("Result : <white>{$output}");
        }
    }

    /**
     * Execute pre commands on remote server.
     *
     * @param array $commands
     */
    public function preDeployRemote(array $commands)
    {
        $this->executeOnRemoteServer($commands);
    }

    /**
     * Execute post commands on remote server.
     *
     * @param array $commands
     */
    public function postDeployRemote(array $commands)
    {
        $this->executeOnRemoteServer($commands);
    }

    /**
     * @param array $commands
     */
    public function executeOnRemoteServer(array $commands)
    {
        /*
         * @var \phpseclib\Net\SFTP
         */
        $connection = $this->connection->getAdapter()->getConnection();

        if ($this->servers[$this->currentServerName]['scheme'] != 'sftp') {
            $this->cli->yellow()->out(PHP_EOL . "Connection scheme is not 'sftp' ignoring [pre/post]-deploy-remote");

            return;
        }

        if (!$connection->isConnected()) {
            $this->cli->red()->out(PHP_EOL . "SFTP adapter connection problem skipping '[pre/post]-deploy-remote' commands");

            return;
        }

        foreach ($commands as $command) {
            $this->cli->blue()->out("Executing on remote server: <bold>{$command}");
            $command = "cd {$this->servers[$this->currentServerName]['path']}; {$command}";
            $output = $connection->exec($command);
            $this->cli->lightBlue()->out("<bold>{$output}");
        }
    }

    /**
     * Checks for deleted directories. Git cares only about files.
     *
     * @param array $filesToDelete
     *
     * @return array
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
                    $prefix .= $parts[$x].'/';
                }

                $part = $prefix.$part;

                // If directory doesn't exist, add to files to delete
                // Relative path won't work consistently, thus getcwd().
                if (!is_dir(getcwd().'/'.$part)) {
                    $dirsToDelete[] = $part;
                }
            }
        }

        // Remove duplicates
        $dirsToDeleteUnique = array_unique($dirsToDelete);

        // Reverse order to delete inner children before parents
        $dirsToDeleteOrder = array_reverse($dirsToDeleteUnique);

        $this->debug('Directories to be deleted: '.print_r($dirsToDeleteOrder, true));

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
     * Creates sample ini file.
     */
    protected function createIniFile()
    {
        $iniFile = getcwd().DIRECTORY_SEPARATOR.$this->iniFileName;

        $data = file_get_contents(__DIR__.'/../phploy.ini');

        if (file_exists($iniFile)) {
            $this->cli->info("\nphploy.ini file already exists.\n");

            return;
        }

        if (file_put_contents($iniFile, $data)) {
            $this->cli->info("\nSample phploy.ini file created.\n");
        }
    }

    /**
     * Log a message to file.
     *
     * @param string $message The message to write
     * @param string $type    The type of log message (e.g. INFO, DEBUG, ERROR, etc.)
     */
    protected function log($message, $type = 'INFO')
    {
        if (isset($this->servers[$this->currentServerName]['logger']) && $this->servers[$this->currentServerName]['logger']) {
            $filename = getcwd().DIRECTORY_SEPARATOR.'phploy.log';
            if (!file_exists($filename)) {
                touch($filename);
            }

            // Format: time --- type: message
            file_put_contents($filename, date('Y-m-d H:i:sP').' --- '.$type.': '.$message.PHP_EOL, FILE_APPEND);
        }
    }
}
