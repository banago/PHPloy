<?php

class PHPloyTestCase extends PHPUnit_Framework_TestCase
{
  protected $workspace;
  protected $repositoriesPath;
  protected $share;
  protected $repository;

  protected function runSync()
  {
    $phployScriptPath = realpath(dirname(__FILE__)."/../phploy.php");
    $output = [];
    $returnValue;
    chdir($this->repository);
    exec("php $phployScriptPath", $output, $returnValue);
    // foreach ($output as $line)
    // {
    //   echo $line."\n";
    // }
    return $returnValue;
  }

  protected function assertShareInSync()
  {
    exec("diff -r --exclude=\".git\" --exclude=\"phploy.ini\" --exclude=\".revision\" $this->share $this->repository", $output, $returnValue);
    if ($returnValue != 0)
    {
      echo "sync failed:\n";
      foreach ($output as $line)
      {
        echo $line."\n";
      }
    }
    $this->assertEquals(0, $returnValue);
  }

  protected function setup()
  {
    //echo "setup\n";
    // make this path configuratble?
    $this->workspace = "/tmp/PHPloyTestWorkspace";
    // echo "workspace: $this->workspace\n";
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
    $this->safeDeleteDirectoryContentInWorkspace("share");

    $this->repository = $this->repositoriesPath."/".$this->generateRandomString().".git";
    $this->share = $this->workspace."/share";
  }

  protected function tearDown()
  {
    // echo "tearDown";
  }

  protected function safeDeleteInWorkspace($relativePath)
  {
    $realPath = realpath($this->workspace."/".$relativePath);
    // make sure at least workspace is part of the path to be deleted
    // just to be safe
    if (strpos($realPath, '/PHPloyTestWorkspace') !== false)
    {
      // echo "deleting $realPath\n";
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
    // echo "delete directory content $realPath\n";
    foreach (new DirectoryIterator($realPath) as $fileInfo)
    {
      if(!$fileInfo->isDot() && strcmp($fileInfo->getFilename(),".gitkeep") != 0)
      {
        $this->safeDeleteInWorkspace($relativePath."/".$fileInfo->getFilename());
      }
    }
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
}
