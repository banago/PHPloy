<?php
/**
 * PHPloy - A PHP Deployment Script
 *
 * @package PHPloy
 * @author Baki Goxhaj <banago@gmail.com>
 * @author Bruno De Barros <bruno@terraduo.com>
 * @author Fadion Dashi <jonidashi@gmail.com>
 * @author Simon East <simon+github@yump.com.au>
 * @author Mark Beech <mbeech@mark-beech.co.uk>
 * @author Guido Hendriks 
 * @link https://github.com/banago/PHPloy
 * @licence MIT Licence
 * @version 3.0.16-stable
 */
 
namespace Banago\PHPloy;

use Banago\PHPloy\Ansi;
use Banago\Bridge\Bridge;

/**
 * PHPloy Class
 */
class PHPloy
{

    /**
     * @var string $phployVersion
     */
    protected $phployVersion = '3.0.16-stable';

    /**
     * @var string $revision
     */
    public $revision;
    
    /**
     * @var string $localRevision
     */
    public $localRevision;

    /**
     * Keep track of which server we are currently deploying to
     *
     * @var string $currentlyDeploying
     */
    public $currentlyDeploying = '';

    /**
     * A list of files that should NOT be uploaded to the remote server
     *
     * @var array $filesToIgnore
     */
    public $filesToIgnore = array();
    
    /**
     * A list of files that should NOT be uploaded to the any defined server
     *
     * @var array $globalFilesToIgnore
     */
    public $globalFilesToIgnore = array(
        '.gitignore',
        '.gitmodules',
    );

    /**
     * To activate submodule deployment use the --submodules argument
     * 
     * @var bool $scanSubmodules
     */
    public $scanSubmodules = false;

    /**
     * If you need support for sub-submodules, ensure this is set to TRUE
     * Set to false when the --skip-subsubmodules command line option is used
     * 
     * @var bool $scanSubSubmodules
     */
    public $scanSubSubmodules = true;

    /**
     * @var array $servers
     */
    public $servers = array();

    /**
     * @var array $submodules
     */
    public $submodules = array();


    /**
     * @var array $purgeDirs
     */
    public $purgeDirs = array();

    /**
     * The name of the file on remote servers that stores the current revision hash
     * 
     * @var string $dotRevisionFilename
     */
    public $dotRevisionFilename = '.revision';

    /**
     * The filename from which to read remote server details
     * 
     * @var string $deplyIniFilename
     */
    public $deployIniFilename = 'deploy.ini';
    
    /**
     * List of available "short" command line options, prefixed by a single hyphen
     * Colon suffix indicates that the option requires a value
     * Double-colon suffix indicates that the option *may* accept a value
     * See descriptions below
     *
     * @var string $shortops
     */
    protected $shortopts = 'los:';

    /**
     * List of available "long" command line options, prefixed by double-hyphen
     * Colon suffix indicates that the option requires a value
     * Double-colon suffix indicates that the option *may* accept a value
     * 
     *      --help or -?                      Displays command line options
     *      --list or -l                      Lists the files that *would* be deployed if run without this option
     *      --rollback                        Deploys the previous commit/revision
     *      --rollback="[revision hash]"      Deploys the specific commit/revision
     *      --server="[server name]"          Deploys to the server entry listed in deploy.ini
     *        or -s [server name]
     *      --sync                            Updates the remote .revision file with the hash of the current HEAD
     *      --sync="[revision hash]"          Updates the remove .revision file with the provided hash
     *      --submodules                      Deploy submodules; turned off by default
     *      --skip-subsubmodules              Skips the scanning of sub-submodules which is currently quite slow
     *      --others                          Uploads files even if they are excluded in .gitignore
     *      --debug                           Displays extra messages including git and FTP commands
     *      --all                             Deploys to all configured servers (unless one was specified in the command line)
     * 
     * @var array $longopts
     */
    protected $longopts  = array('no-colors', 'help', 'list', 'rollback::', 'server:', 'sync::', 'submodules', 'skip-subsubmodules', 'others', 'debug', 'version', 'all');

    /**
     * @var bool|resource $connection
     */
    protected $connection = false;

    /**
     * @var string $server
     */
    protected $server = '';

    /**
     * @var string $repo
     */
    protected $repo;

    /**
     * @var string $mainRepo
     */
    protected $mainRepo;

    /**
     * @var bool|string $currentSubmoduleName
     */
    protected $currentSubmoduleName = false;

    /**
     * Holds the path to the .revision file
     * For the main repository this will be the value of $dotRevisionFilename ('.revision' by default)
     * but for submodules, the submodule path will be prepended
     * 
     * @var string $dotRevision
     */
    protected $dotRevision;

    /**
     * Whether phploy is running in list mode (--list or -l commands)
     * @var bool $listFiles
     */
    protected $listFiles = false;

    /**
     * Whether the --help command line option was given
     * @var bool $displayHelp
     */
    protected $displayHelp = false;

    /**
     * Whether the --version command line option was given
     * @var bool $displayHelp
     */
    protected $displayVersion = false;

    /**
     * Whether the --sync command line option was given
     * @var bool $sync
     */
    protected $sync = false;

    /**
     * Whether phploy should ignore .gitignore (--others or -o commands)
     * @var bool $others
     */
    protected $others = false;

    /**
     * Whether to print extra debugging info to the console, especially for git & FTP commands
     * Activated using --debug command line option
     * @var bool $debug
     */
    protected $debug = false;

    /**
     * Keep track of current deployment size
     * @var int $deploymentSize
     */
    protected $deploymentSize = 0;
    
    /**
     * Keep track of if a default server has been configured
     * @var bool $defaultServer
     */
    protected $defaultServer = false;

    /**
     * Weather the --all command line option was given
     * @var bool deployAll
     */
    protected $deployAll = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->parseOptions();

        $this->output("\r\n<bgGreen>---------------------------------------------------");
        $this->output("<bgGreen>|              PHPloy v{$this->phployVersion}              |");
        $this->output("<bgGreen>---------------------------------------------------<reset>\r\n");

        if ($this->displayHelp) {
            $this->displayHelp();
            return;
        }

        if ($this->displayVersion) {
            return;
        }

        if (file_exists("$this->repo/.git")) {

            if ($this->listFiles) {
                $this->output("<yellow>PHPloy is running in LIST mode. No remote files will be modified.\r\n");
            }
            
            // Submodules are turned off by default
            if( $this->scanSubmodules ) {
                $this->checkSubmodules($this->repo);
            }

            // Find the revision number of HEAD at this point so that if 
            // you make commit during deployment, the rev will be right.
            $this->localRevision = $this->currentRevision();
            
            $this->deploy($this->revision);

        } else {
            throw new \Exception("'{$this->repo}' is not Git repository.");
        }
    }
    
    /**
     * Get current revision
     *
     * @return string with current revision hash
     */
    private function currentRevision() {
        $currentRevision = $this->gitCommand('rev-parse HEAD');
        return $currentRevision[0];
    }

    /**
     * Displays the various command line options
     *
     * @return null
     */
    public function displayHelp()
    {
        // $this->output();
        $readMe = __DIR__ . '/readme.md';
        if (file_exists($readMe))
            $this->output(file_get_contents($readMe));
    }

    /**
     * Parse CLI options
     * For descriptions of the various options, see the comments for $this->longopts
     *
     * @return null
     */
    public function parseOptions()
    {
        $options = getopt($this->shortopts, $this->longopts);
        $this->debug('Command line options detected: ' . print_r($options, true));

        if (isset($options['no-colors'])) {
            Ansi::$enabled = false;
        }

        // -? command is not correctly parsed by getopt() (at least on Windows)
        // so need to check $argv variable instead
        global $argv;
        if (in_array('-?', $argv) or isset($options['help'])) {
            $this->displayHelp = true;
        }

        if (isset($options['debug'])) {
            $this->debug = true;
        }

        if (isset($options['version'])) {
            $this->displayVersion = true;
        }

        if (isset($options['l']) or isset($options['list'])) {
            $this->listFiles = true;
        }

        if (isset($options['s']) or isset($options['server'])) {
            $this->server = isset($options['s']) ? $options['s'] : $options['server'];
        }

        if (isset($options['o']) or isset($options['others'])) {
            $this->others = true;
        }

        if (isset($options['sync'])) {
            $this->sync = empty($options['sync']) ? 'sync' : $options['sync'];
        }

        if (isset($options['rollback'])) {
            $this->revision = ($options['rollback'] == '') ? 'HEAD^' : $options['rollback'];
        } else {
            $this->revision = 'HEAD';
        }

        if (isset($options['submodules'])) {
            $this->scanSubmodules = true;
        }

        if (isset($options['skip-subsubmodules'])) {
            $this->scanSubSubmodules = false;
        }

        if (isset($options['all'])) {
            $this->deployAll = true;
        }

        $this->repo = isset($opts['repo']) ? rtrim($opts['repo'], '/') : getcwd();
        $this->mainRepo = $this->repo;
    }

    /**
     * Check for submodules
     * 
     * @param string $repo
     * @return null
     */
    public function checkSubmodules($repo)
    {
        $this->output('Scanning repository...');
            
        $output = $this->gitCommand('submodule status', $repo);

        $this->output('   Found '.count($output).' submodules.');
        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));
                $this->submodules[] = array('revision' => $line[0], 'name' => $line[1], 'path' => $repo.'/'.$line[1]);
                $this->filesToIgnore[] = $line[1];
                $this->output(sprintf('   Found submodule %s. %s', 
                    $line[1],
                    $this->scanSubSubmodules ? PHP_EOL . '      Scanning for sub-submodules...' : null
                ));
                // The call to checkSubSubmodules also calls a git foreach
                // So perhaps it should be *outside* the loop here?
                if ($this->scanSubSubmodules)
                    $this->checkSubSubmodules($repo, $line[1]);
            }
            if (!$this->scanSubSubmodules)
                $this->output('   Skipping search for sub-submodules.');
        }
    }

    /**
     * Check for sub-submodules
     *
     * @todo This function is quite slow (at least on Windows it often takes several seconds for each call).
     *       Can it be optimized?
     *       It appears that this is called for EACH submodule, but then also does another `git submodule foreach`
     * @param string $repo
     * @param string $name
     * @return null
     */
    public function checkSubSubmodules($repo, $name)
    {
        $output = $this->gitCommand('submodule foreach git submodule status', $repo);

        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));

                if (trim($line[0]) == 'Entering') continue;
                
                $this->submodules[] = array(
                    'revision' => $line[0], 
                    'name' => $name.'/'.$line[1], 
                    'path' => $repo.'/'.$name.'/'.$line[1]
                );
                $this->filesToIgnore[] = $line[1];
                $this->output(sprintf('      Found sub-submodule %s.', "$name/$line[1]"));
            }
        }
    }

    /**
     * Parse Credentials
     * 
     * @param string $deploy The filename to obtain the list of servers from, normally $this->deployIniFilename
     * @return array of servers listed in the file $deploy
     */
    public function parseCredentials($deploy)
    {
        if (! file_exists($deploy)) {
            throw new \Exception("'$deploy' does not exist.");
        } else {
            $servers = parse_ini_file($deploy, true);

            if (! $servers) {
                 throw new \Exception("'$deploy' is not a valid .ini file.");
            } else {
                return $servers;
            }
        }
    }

    /**
     * Reads the deploy.ini file and populates the $this->servers array
     *
     * @return null
     */
    public function prepareServers()
    {
        $defaults = array(
            'scheme' => 'ftp',
            'host' => '',
            'user' => '',
            'pass' => '',
            'port' => '',
            'path' => '/',
            'passive' => true,
            'skip' => array(),
            'purge' => array()
        );
        
        $ini = getcwd() . DIRECTORY_SEPARATOR . $this->deployIniFilename;
        
        $servers = $this->parseCredentials($ini);

        foreach ($servers as $name => $options) {

            $options = array_merge($defaults, $options);

            // Determine if a default server is configured
            if ($name == 'default')  {
                $this->defaultServer = true;
            }
            
            // Re-merge parsed url in quickmode
            if( isset( $options['quickmode'] ) ) {
                $options = array_merge($options, parse_url($options['quickmode']));
            }

            if(! empty($servers[$name]['skip'])){
                $this->filesToIgnore[$name] = array_merge($this->globalFilesToIgnore, $servers[$name]['skip']);
            }

            if(! empty($servers[$name]['purge'])){
                $this->purgeDirs[$name] = $servers[$name]['purge'];
            }            
            
            $this->filesToIgnore[$name][] = $this->deployIniFilename;
            
            // Ask user a password if it empty
            if( $options['pass'] === '' ) {
                fputs(STDOUT, 'You have not provided a password for user "'. $options['user'] .'". Please enter a password: ');
                $input = urlencode($this->getPassword());
             
                if( $input == '' ) {
                    $this->output("\r\n<green>You entered an empty password. All good, continuing deployment ...");                    
                } else {
                    $options['pass'] = $input;
                    $this->output("\r\n<green>We got your password, thanks. Continuing deployment ...");
                }
            }
            
            // Turn options into an URL so that Bridge can work with it.
            $this->servers[$name] = http_build_url('', $options);
        }
    }
    
    /**
     * Gets the password from user input, hiding password and replaces it
     * with stars (*) if user users Unix / Mac.
     * 
     * @return string the user entered
     */
    private function getPassword() {
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
            } else if (ord($char) === 127) {
                if (strlen($password) > 0) {
                    fwrite(STDOUT, "\x08 \x08");
                    $password = substr($password, 0, -1);
                }
            } else {
                fwrite(STDOUT, "*");
                $password .= $char;
            }
        }
        
        shell_exec('stty ' . $oldStyle);
        return $password;
    }

    /**
     * Executes a console command and returns the output (as an array)
     * 
     * @return array of all lines that were output to the console during the command (STDOUT)
     */
    public function runCommand($command)
    {
        // Escape special chars in string with a backslash
        $command = escapeshellcmd($command);

        $this->debug("<yellow>CONSOLE:<darkYellow> $command");

        exec($command, $output);

        $this->debug('<darkYellow>' . implode("\r\n<darkYellow>", $output));

        return $output;
    }

    /**
     * Runs a git command and returns the output (as an array)
     * 
     * @param string $command "git [your-command-here]"
     * @param string $repoPath Defaults to $this->repo
     * @return array Lines of the output
     */
    public function gitCommand($command, $repoPath = null)
    {
        if (! $repoPath){
            $repoPath = $this->repo;
        }
        
        $command = 'git --git-dir="' . $repoPath . '/.git" --work-tree="' . $repoPath . '" ' . $command;

        return $this->runCommand($command);
    }

    /**
     * Compare revisions and returns array of files to upload:
     *
     *      array(
     *          'upload' => $filesToUpload,
     *          'delete' => $filesToDelete
     *      );
     *
     * @param string $localRevision
     * @return array
     * @throws Exception if unknown git diff status
     */
    public function compare($localRevision)
    {
        $remoteRevision = null;
        $tmpFile = tmpfile();
        $filesToUpload = array();
        $filesToDelete = array();
        $filesToSkip = array();
        $output = array();

        if ($this->currentSubmoduleName) {
            $this->dotRevision = $this->currentSubmoduleName.'/'.$this->dotRevisionFilename;
        } else {
            $this->dotRevision = $this->dotRevisionFilename;
        }

        // Fetch the .revision file from the server and write it to $tmpFile
        $this->ftpDebug("Fetching {$this->dotRevision} file");
        
        if ( $this->connection->exists($this->dotRevision) ) {
            $remoteRevision = $this->connection->get($this->dotRevision);
        } else {
            $this->output('<yellow>|----[ No revision found. Fresh deployment - grab a coffee ]----|');
        }

        // Use git to list the changed files between $remoteRevision and $localRevision
        // "-c core.quotepath=false" in command fixes special chars issue like ë, ä or ü in file names
        if($this->others){
            $command = '-c core.quotepath=false ls-files -o';
        } elseif (empty($remoteRevision)) {
            $command = '-c core.quotepath=false ls-files';
        } else if ($localRevision == 'HEAD') {
            $command = '-c core.quotepath=false diff --name-status '.$remoteRevision.'...'.$localRevision;
        } else {
            $command = '-c core.quotepath=false diff --name-status '.$remoteRevision.'... '.$localRevision;
        }

        $output = $this->gitCommand($command);

        /**
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

		if (! empty($remoteRevision)) {
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
        
        $filesToUpload = $filteredFilesToUpload['files'];
        $filesToDelete = $filteredFilesToDelete['files'];
        
        $filesToSkip = array_merge($filteredFilesToUpload['filesToSkip'], $filteredFilesToDelete['filesToSkip']);

        return array(
            $this->currentlyDeploying => array(
                'delete' => $filesToDelete,
                'upload' => $filesToUpload,
                'skip' => $filesToSkip,
            )
        );
    }
    
    /**
     * Filter ignore files
     * 
     * @param array $files Array of files which needed to be filtered
     * @return Array with `files` (filtered) and `filesToSkip`
     */
    private function filterIgnoredFiles($files) {
        $filesToSkip = array();
        
        foreach($files as $i => $file) {
            foreach($this->filesToIgnore[$this->currentlyDeploying] as $pattern) {
                if($this->patternMatch($pattern, $file)) {
                    unset($files[$i]);
                    $filesToSkip[] = $file;
                    break;
                }
            }
        }
        
        $files = array_values($files);
        
        return array(
            'files' => $files,
            'filesToSkip' => $filesToSkip
        );
    }

    /**
     * Deploy (or list) changed files
     * 
     * @param string $revision
     */
    public function deploy($revision = 'HEAD') 
    {
        $this->prepareServers();

        // Exit with an error if the specified server does not exist in deploy.ini
        if ($this->server != '' && !array_key_exists($this->server, $this->servers))
            throw new \Exception("The server \"{$this->server}\" is not defined in {$this->deployIniFilename}.");

        // Loop through all the servers in deploy.ini
        foreach ($this->servers as $name => $server) {

            $this->currentlyDeploying = $name;
            
            // Deploys to ALL servers by default
            // If a server is specified, we skip all servers that don't match the one specified
            if ($this->server != '' && $this->server != $name) continue;

            // If no server was specified in the command line but a default server configuration exists, we'll use that (as long as --all was not specified)
            elseif ($this->server == '' && $this->defaultServer == true && $name != 'default' && $this->deployAll == false) continue;

            $this->connect($server);
            
            if( $this->sync ) {
                $this->dotRevision = $this->dotRevisionFilename;
                $this->setRevision();
                continue;
            }
            
            $files = $this->compare($revision);

            $this->output("\r\n<white>SERVER: ".$name);
            if ($this->listFiles === true) {
                $this->listFiles($files[$this->currentlyDeploying]);
            } else {
                $this->push($files[$this->currentlyDeploying]);
                // Purge
                if( isset( $this->purgeDirs[$name] ) && count($this->purgeDirs[$name]) > 0 ) {
                    $this->purge($this->purgeDirs[$name]);
                }
            }

            if ( $this->scanSubmodules && count($this->submodules) > 0) {
                foreach ($this->submodules as $submodule) {
                    $this->repo = $submodule['path'];
                    $this->currentSubmoduleName = $submodule['name'];
                    
                    $this->output("\r\n<gray>SUBMODULE: ".$this->currentSubmoduleName);
                    
                    $files = $this->compare($revision);

                    if ($this->listFiles === true) {
                        $this->listFiles($files[$this->currentlyDeploying]);
                    } else {
                        $this->push($files[$this->currentlyDeploying]);
                    } 
                }
                // We've finished deploying submodules, reset settings for the next server
                $this->repo = $this->mainRepo;
                $this->currentSubmoduleName = false;
            }          
            
            // Done
            if (! $this->listFiles) {
                $this->output("\r\n<green>----------------[ ".$this->humanFilesize($this->deploymentSize)." Deployed ]----------------");
                $this->deploymentSize = 0;
            }
        }         
    }

    /**
     * Return a human readable filesize
     * 
     * @param int $bytes
     * @param int $decimals
     */
    public function humanFilesize($bytes, $decimals = 2) {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    /**
     * Glob the file path
     * 
     * @param string $pattern
     * @param string $string
     */
    function patternMatch($pattern, $string) {
        return preg_match("#^".strtr(preg_quote($pattern, '#'), array('\*' => '.*', '\?' => '.'))."$#i", $string);
    }

    /**
     * Check what files will be uploaded/deleted
     * 
     * @param array $files
     */
    public function listFiles($files)
    {
        if (count($files['upload']) == 0 && count($files['delete']) == 0) {
            $this->output("   No files to upload.");
        }

        if (count($files['delete']) > 0) {
            $this->output("   <red>Files that will be deleted in next deployment:");

            foreach ($files['delete'] as $file_to_delete) {
                $this->output("      ".$file_to_delete);
            }
        }
        
        if (count($files['upload']) > 0) {
            $this->output("   <green>Files that will be uploaded in next deployment:");

            foreach ($files['upload'] as $file_to_upload) {
                $this->output("      ".$file_to_upload);
            }
        }
    }

    /**
     * Connect to the Server
     * 
     * @param string $server
     * @throws Exception if it can't connect to FTP server
     */
    public function connect($server)
    {
        try {
            $connection = new Bridge($server);
            $this->connection = $connection;            
        } catch (\Exception $e) {
            echo Ansi::tagsToColors("\r\n<red>Oh Snap: {$e->getMessage()}\r\n");
            // If we could not connect, what's the point of existing
            die();
        }        
    }

    /**
     * Update the current remote server with the array of files provided
     *
     * @param array $files 2-dimensional array with 2 indices: 'upload' and 'delete'
     *                     Each of these contains an array of filenames and paths (relative to repository root)
     */
    public function push($files)
    {
        $initialBranch = $this->currentBranch();
        
        // If revision is not HEAD, the current one, it means this is a rollback.
        // So, we have to revert the files the the state they were in that revision.
        if ($this->revision != 'HEAD') {
            $this->output("   Rolling back working copy");

            // BUG: This does NOT work correctly for submodules & subsubmodules (and leaves them in an incorrect state)
            //      It technically should do a submodule update in the parent, not a checkout inside the submodule
            $this->gitCommand('checkout '.$this->revision);
            
            // Updating local revision - so the right revision will be set to server after rolling back
            $this->localRevision = $this->currentRevision();
        }

        $filesToDelete = $files['delete'];
        $filesToUpload = $files['upload'];
        
        unset($files);

        // TODO: perhaps detect whether file is actually present, and whether delete is successful/skipped/failed
        foreach ($filesToDelete as $fileNo => $file) {
            
            $numberOfFilesToDelete = count($filesToDelete);
            
            $this->connection->rm($file);
            $fileNo = str_pad(++$fileNo, strlen($numberOfFilesToDelete), ' ', STR_PAD_LEFT);
            $this->output("<red>removed $fileNo of $numberOfFilesToDelete <white>{$file}");
        }

        // Upload Files
        foreach ($filesToUpload as $fileNo => $file) {
            if ($this->currentSubmoduleName) $file = $this->currentSubmoduleName.'/'.$file;

            // Make sure the folder exists in the FTP server.
            $dir = explode("/", dirname($file));
            $path = "";
            $ret = true;
            
            // Skip mkdir if dir is basedir
            if( $dir[0] !== '.' ) {
                // Loop through each folder in the path /a/b/c/d.txt to ensure that it exists
                for ($i = 0, $count = count($dir); $i < $count; $i++) {
                    $path .= $dir[$i].'/';
    
                    if (! isset($pathsThatExist[$path])) {
                        $origin = $this->connection->pwd();
    
                        if (! $this->connection->exists($path)) {
                            $this->connection->mkdir($path);
                            $this->output("Created directory '$path'.");
                            $pathsThatExist[$path] = true;                     
                        } else {
                            $this->connection->cd($path);
                            $pathsThatExist[$path] = true;
                        }
                        
                        // Go home
                        $this->connection->cd($origin);
                    }
                }
            }

            // Now upload the file, attempting 10 times 
            // before exiting with a failure message
            $uploaded = false;
            $attempts = 1;            
            while (! $uploaded) {
                if ($attempts == 10) {
                    throw new \Exception("Tried to upload $file 10 times and failed. Something is wrong...");
                }

                $data = file_get_contents($file);
                $remoteFile = $file;         
                $uploaded = $this->connection->put($data, $remoteFile);

                if (! $uploaded) {
                    $attempts = $attempts + 1;
                    $this->output("<darkRed>Failed to upload {$file}. Retrying (attempt $attempts/10)... ");
                }
                else {
                    $this->deploymentSize += filesize(getcwd() . '/' .$file);
                }
            }
            
            $numberOfFilesToUpdate = count($filesToUpload);
            
            $fileNo = str_pad(++$fileNo, strlen($numberOfFilesToUpdate), ' ', STR_PAD_LEFT);
            $this->output("<green> ^ $fileNo of $numberOfFilesToUpdate <white>{$file}");
        }

        if (count($filesToUpload) > 0 or count($filesToDelete) > 0) {            
            // Set revision on server
            $this->setRevision();              
        } else {
            $this->output("   <gray>No files to upload.");
        }
        
        // If $this->revision is not HEAD, it means the rollback command was provided
        // The working copy was rolled back earlier to run the deployment, and we now want to return the working copy
        // back to its original state
        if ($this->revision != 'HEAD') {
            $this->gitCommand('checkout '.($initialBranch ?: 'master'));
        }
    }
    
    /**
     * Gets the current branch name.
     *
     * @return string - current branch name or false if not in branch
     */
    private function currentBranch() {
        $currentBranch = $this->gitCommand('rev-parse --abbrev-ref HEAD')[0];
        if ($currentBranch != 'HEAD') {
            return $currentBranch;
        }
        return false;
    }

    /**
     * Sets version hash on the server.
     */
    public function setRevision()
    {
        // By default we update the revision file to the local revision, 
        // unless the sync command was called with a specific revision
        $localRevision = $this->localRevision;
        if ($this->sync && $this->sync != 'sync') {
            $localRevision = $this->sync;
        }
        $consoleMessage = "Updating remote revision file to ".$localRevision;

        if ( $this->sync ) {
            $this->output("\r\n<yellow>SYNC: $consoleMessage");
        } else {
            $this->ftpDebug($consoleMessage);
        }
        
        try {
            $this->connection->put($localRevision, $this->dotRevision);
        } catch (\Exception $e) {
            throw new \Exception("Could not update the revision file on server: $e->getMessage()");   
        }                
    }

    /**
     * Purge given directory's contents
     * 
     * @var string $purgeDirs
     */
    public function purge($purgeDirs) 
    {
        foreach ($purgeDirs as $dir) {
            
            $origin = $this->connection->pwd();
            $this->connection->cd($dir);

            if (! $tmpFiles = $this->connection->ls()) {
                $this->output("Nothing to purge in {$dir}");
                continue;
            }

            $this->output("<red>Purging <white> ...");
            
            foreach ($tmpFiles as $file) {               
                $this->connection->rm($file);
            }

            $this->output("<red>Purged <white>{$dir}");            
            $this->connection->cd($origin);
        }
    }
    
    /**
     * Helper method to display messages on the screen.
     * 
     * @param string $message
     */
    public function output($message) 
    {
        echo Ansi::tagsToColors($message) . "\r\n";
    }

    /**
     * Helper method to output messages to the console (only in debug mode)
     * Debug mode is activated by setting $this->debug = true or using the command line option --debug
     * 
     * @param string $message Message to display on the console
     */
    public function debug($message) 
    {
        if ($this->debug)
            $this->output("$message");
    }

    /**
     * Helper method to output messages to the console (only in debug mode)
     * Debug mode is activated by setting $this->debug = true or using the command line option --debug
     * 
     * @param string $message Message to display on the console
     */
    public function ftpDebug($message) 
    {
        $this->debug("<yellow>FTP: <darkYellow>$message");
    }

}
