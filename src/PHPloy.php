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
 * @link http://wplancer.com
 * @licence MIT Licence
 * @version 3.0.7-alpha
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
    protected $phployVersion = '3.0.7-alpha';

    /**
     * @var string $revision
     */
    public $revision;

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
     * If you need support for sub-submodules, ensure this is set to TRUE
     * Set to false when the --skip-subsubmodules command line option is used
     * 
     * @var bool $scanForSubSubmodules
     */
    public $scanForSubSubmodules = true;

    /**
     * @var array $servers
     */
    public $servers = array();

    /**
     * @var array $submodules
     */
    public $submodules = array();

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
    protected $shortopts = 'ls:';

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
     *      --skip-subsubmodules              Skips the scanning of sub-submodules which is currently quite slow
     *      --debug                           Displays extra messages including git and FTP commands
     * 
     * @var array $longopts
     */
    protected $longopts  = array('no-colors', 'help', 'list', 'rollback::', 'server:', 'sync::', 'skip-subsubmodules', 'debug', 'version');

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
     * Constructor
     */
    public function __construct()
    {
        $this->parseOptions();

        $this->output("\r\n<bgGreen>---------------------------------------------------");
        $this->output("<bgGreen>|              PHPloy v{$this->phployVersion}                |");
        $this->output("<bgGreen>---------------------------------------------------<reset>\r\n");

        if ($this->displayHelp) {
            $this->displayHelp();
            return;
        }

        if ($this->displayVersion) {
            return;
        }

        if (file_exists("$this->repo/.git")) {

            if ($this->listFiles)
                $this->output("<yellow>phploy is running in LIST mode.  No remote files will be modified.\r\n");

            $this->output('Scanning repository...');
            $this->checkSubmodules($this->repo);
            $this->deploy($this->revision);

        } else {
            throw new \Exception("'{$this->repo}' is not Git repository.");
        }
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

        if (isset($options['sync'])) {
            $this->sync = empty($options['sync']) ? 'sync' : $options['sync'];
        }

        if (isset($options['rollback'])) {
            $this->revision = ($options['rollback'] == '') ? 'HEAD^' : $options['rollback'];
        } else {
            $this->revision = 'HEAD';
        }

        if (isset($options['skip-subsubmodules'])) {
            $this->scanForSubSubmodules = false;
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
        $output = $this->gitCommand('submodule status', $repo);

        $this->output('   Found '.count($output).' submodules.');
        if (count($output) > 0) {
            foreach ($output as $line) {
                $line = explode(' ', trim($line));
                $this->submodules[] = array('revision' => $line[0], 'name' => $line[1], 'path' => $repo.'/'.$line[1]);
                $this->filesToIgnore[] = $line[1];
                $this->output(sprintf('   Found submodule %s. %s', 
                    $line[1],
                    $this->scanForSubSubmodules ? 'Scanning for sub-submodules...' : null
                ));
                // The call to checkSubSubmodules also calls a git foreach
                // So perhaps it should be *outside* the loop here?
                if ($this->scanForSubSubmodules)
                    $this->checkSubSubmodules($repo, $line[1]);
            }
            if (!$this->scanForSubSubmodules)
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
            'port' => 21,
            'path' => '/',
            'passive' => true,
            'clean_directories' => array()
        );
        
        $ini = getcwd() . DIRECTORY_SEPARATOR . $this->deployIniFilename;
        $servers = $this->parseCredentials($ini);

        foreach ($servers as $name => $options) {
            if ( isset( $options['quickmode'] ) ) {
                $this->servers[$name] = $options['quickmode'];
                continue;
            }

            $options = array_merge($defaults, $options);

            if(!empty($servers[$name]['skip']))
                $this->filesToIgnore[$name] = array_merge($this->globalFilesToIgnore, $servers[$name]['skip']);

            $this->filesToIgnore[$name][] = $this->deployIniFilename;

            // Turn options into an URL so that Bridge can accept it.
            $this->servers[$name] = http_build_url('', $options);
        }
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
        if (! $repoPath)
            $repoPath = $this->repo;
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
        if (empty($remoteRevision)) {
            $command = 'ls-files';
        } else if ($localRevision == 'HEAD') {
            $command = 'diff --name-status '.$remoteRevision.'...'.$localRevision;
        } else {
            $command = 'diff --name-status '.$remoteRevision.'... '.$localRevision;
        }

        $output = $this->gitCommand($command);

		if (! empty($remoteRevision)) {
	        foreach ($output as $line) {
	            if ($line[0] == 'A' or $line[0] == 'C' or $line[0] == 'M') {
	                // Added (A), Modified (C), Unmerged (M)
	                $filesToUpload[] = trim(substr($line, 1));
	            } elseif ($line[0] == 'D') {
	                // Deleted (D)
	                $filesToDelete[] = trim(substr($line, 1));
	            } else {
	                throw new \Exception("Unknown git-diff status: {$line[0]}");
	            }
	        }
        } else {
		    $filesToUpload = $output;
		}

        foreach($filesToUpload as $file) {
            foreach($this->filesToIgnore[$this->currentlyDeploying] as $pattern) {
                if($this->patternMatch($pattern, $file)) {
                    $filesToSkip[] = $file;
                    break;
                }
            }
        }

        $filesToUpload = array_values(array_diff($filesToUpload, $filesToSkip));

        return array(
            $this->currentlyDeploying => array(
                'upload' => $filesToUpload,
                'delete' => $filesToDelete,
                'skip' => $filesToSkip,
            )
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
            }

            if (count($this->submodules) > 0) {
                foreach ($this->submodules as $submodule) {
                    $this->repo = $submodule['path'];
                    $this->currentSubmoduleName = $submodule['name'];
                    
                    $this->output("\r\n<gray>SUBMODULE: ".$this->currentSubmoduleName);
                    
                    $files = $this->compare($revision);

                    if ($this->listFiles === true) {
                        $this->listFiles($files[$this->currentlyDeploying]);
                    } else {
                        $this->push($files);
                    } 
                }
                // We've finished deploying submodules, reset settings for the next server
                $this->repo = $this->mainRepo;
                $this->currentSubmoduleName = false;
            }
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

        if (count($files['upload']) > 0) {
            $this->output("   <green>Files that will be uploaded in next deployment:");

            foreach ($files['upload'] as $file_to_upload) {
                $this->output("      ".$file_to_upload);
            }
        }

        if (count($files['delete']) > 0) {
            $this->output("   <red>Files that will be deleted in next deployment:");

            foreach ($files['delete'] as $file_to_delete) {
                $this->output("      ".$file_to_delete);
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
        // If revision is not HEAD, the current one, it means this is a rollback.
        // So, we have to revert the files the the state they were in that revision.
        if ($this->revision != 'HEAD') {
            $this->output("   Rolling back working copy");

            // BUG: This does NOT work correctly for submodules & subsubmodules (and leaves them in an incorrect state)
            //      It technically should do a submodule update in the parent, not a checkout inside the submodule
            $this->gitCommand('checkout '.$this->revision);
        }

        $filesToUpload = $files['upload'];
        $filesToDelete = $files['delete'];
        $numberOfFiles = count($files['upload']) + count($files['delete']);
        unset($files);

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
            
            $fileNo = str_pad(++$fileNo, strlen($numberOfFiles), ' ', STR_PAD_LEFT);
            $this->output("<green> ^ $fileNo of $numberOfFiles <white>{$file}");
        }

        // todo: perhaps detect whether file is actually present, and whether delete is successful/skipped/failed
        foreach ($filesToDelete as $i => $file) {
            $this->connection->rm($file);
            $fileNo = str_pad(++$fileNo, strlen($numberOfFiles), ' ', STR_PAD_LEFT);
            $this->output("<red>removed $fileNo of $numberOfFiles <white>{$file}");
        }

        // If deploy.ini specifies some directories to "clean", wipe all files within
        if (! empty($server['clean_directories'])) {
            foreach ($server['clean_directories'] as $dir) {
                $this->debug("Now cleaning $dir");
                if (! $tmpFiles = $this->connection->ls($dir)) {
                    $this->output("{$dir} already empty");
                    continue;
                }

                foreach ($tmpFiles as $file) {
                    $this->connection->rm($file);
                }

                $this->output("   <red>emptied <white>{$dir}");
            }
        }

        if (count($filesToUpload) > 0 or count($filesToDelete) > 0) {            
            // Set revision on server
            $this->setRevision();              
        } else {
            $this->output("   <gray>No files to upload.");
        }

        // If $this->revision is not HEAD, it means the rollback command was provided
        // The working copy was rolled back earlier to run the deployment, and we now want to return the working copy
        // back to its original state, but this is BUGGY (see below)
        if ($this->revision != 'HEAD') {
            // BUG: What if the original deployment was not done from the master branch?  This will return the working copy to the wrong branch
            //      Perhaps we need to detect (and store) the branch at the beginning and reset it here
            //      This also may be different for each submodule
            $this->gitCommand('checkout master');
        }
    }

    /**
     * Sets version hash on the server.
     */
    public function setRevision()
    {
        // By default we update the revision file to the HEAD commit, 
        // unless the sync command was called with a specific revision
        $isHeadRevision = $this->sync == 'sync' || $this->sync == false;
        if ( $isHeadRevision ) {
            // Find the revision number of HEAD
            $localRevision = $this->gitCommand('rev-parse HEAD');
            // exec(escapeshellcmd($command), );
            $localRevision = $localRevision[0];
        } else {
            $localRevision = $this->sync;
        }
        
        $consoleMessage = "Updating remote revision file to ".
                ($isHeadRevision ? 'current HEAD ('.$localRevision.')' : $localRevision);

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
