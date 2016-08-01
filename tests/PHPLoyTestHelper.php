<?php
use TQ\Git\Repository\Repository;

class PHPloyTestHelper
{
  public $git;
  public $repository;

  protected $helperType;
  protected $configFileName;
  protected $workspace;
  protected $repositoriesPath;
  protected $share;
  protected $synchronizationResult;
  protected $logErroneousSync = true;

  public function __construct($helperType, $logErroneousSync = true)
  {
    $this->logErroneousSync = $logErroneousSync;
    $this->helperType = $helperType;
    $this->configFileName = $helperType."_phploy.ini";
    $this->setup();
  }

  public function givenRepositoryWithConfiguration()
  {
    $this->git = Repository::open($this->repository, '/usr/bin/git', 0755);
    $result = $this->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      copy(realpath(dirname(__FILE__)."/resources/$this->configFileName"), $this->repository."/phploy.ini");
      $t->setCommitMsg('Add configuration');
    });
  }

  public function whenRepositoryIsSynchronized()
  {
    $phployScriptPath = realpath(dirname(__FILE__)."/../phploy.php");
    $output = [];
    $returnValue;
    chdir($this->repository);
    exec("php $phployScriptPath", $output, $returnValue);
    $this->synchronizationResult = $returnValue;
    if ($this->synchronizationResult != 0 && $this->logErroneousSync)
    {
      echo "======== Synchronizing ========\n";
      echo "protocol: $this->helperType \n";
      echo "repository: $this->repository \n";
      foreach ($output as $line)
      {
        echo $line."\n";
      }
    }
    return $returnValue;
  }

  public function shareInSync()
  {
    exec("diff -r --exclude=\".git\" --exclude=\"phploy.ini\" --exclude=\".revision\" $this->share $this->repository", $output, $returnValue);
    if ($returnValue != 0)
    {
      echo "sync failed:\n";
      foreach ($output as $line)
      {
        echo $line."\n";
      }
      return false;
    }
    return true;
  }

  public function getSynchronizationResult()
  {
    return $this->synchronizationResult;
  }

  protected function setup()
  {
    $this->workspace = "/tmp/PHPloyTestWorkspace";
    if (!file_exists($this->workspace))
    {
      mkdir($this->workspace, 0777, true);
    }
    $this->repositoriesPath = $this->workspace."/repositories";
    if (!file_exists($this->repositoriesPath))
    {
      mkdir($this->repositoriesPath, 0777, true);
    }
    $this->safeDeleteDirectoryContentInWorkspace("repositories");
    $this->safeDeleteDirectoryContentInWorkspace($this->helperType."_share/share");

    $this->repository = $this->repositoriesPath."/".$this->generateRandomString().".git";
    $this->share = $this->workspace."/".$this->helperType."_share/share";
  }

  // Thank you stackoverflow
  // http://stackoverflow.com/questions/4356289/php-random-string-generator
  protected function generateRandomString($length = 10)
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  protected function safeDeleteInWorkspace($relativePath)
  {
    $realPath = realpath($this->workspace."/".$relativePath);
    // make sure at least workspace is part of the path to be deleted
    // just to be safe
    if (strpos($realPath, '/PHPloyTestWorkspace') !== false)
    {
      exec("rm -rf \"$realPath\"");
    }
    else
    {
      echo "do not delete workspace file $relativePath, there seems to be something wrong with it!";
    }
  }

  protected function safeDeleteDirectoryContentInWorkspace($relativePath)
  {
    $realPath = realpath($this->workspace."/".$relativePath);
    foreach (new DirectoryIterator($realPath) as $fileInfo)
    {
      if(!$fileInfo->isDot())
      {
        $this->safeDeleteInWorkspace($relativePath."/".$fileInfo->getFilename());
      }
    }
  }
}
