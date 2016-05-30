<?php
use TQ\Git\Repository\Repository;

class SftpCommitTest extends PHPUnit_Framework_TestCase
{
    protected $workspace;
    protected $repositoriesPath;
    protected $share;

    protected function setup()
    {
      echo "setup\n";
      // make this path configuratble?
      $this->workspace = "/tmp/PHPloyTestWorkspace";
      echo "workspace: $this->workspace\n";
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
    }

    protected function tearDown()
    {
      echo "tearDown";
    }

    protected function safeDeleteInWorkspace($relativePath)
    {
      $realPath = realpath($this->workspace."/".$relativePath);
      // make sure at least workspace is part of the path to be deleted
      // just to be safe
      if (strpos($realPath, '/PHPloyTestWorkspace') !== false)
      {
        echo "deleting $realPath\n";
        exec("rm -rf \"$realPath\"");
      }
      else
      {
        echo "do not delete workspace, there seems to be something wrong with it!";
      }
    }

    protected function safeDeleteDirectoryContentInWorkspace($relativePath)
    {
      $realPath = realpath($this->workspace."/".$relativePath);
      echo "delete directory content $realPath\n";
      foreach (new DirectoryIterator($realPath) as $fileInfo)
      {
        if(!$fileInfo->isDot() && strcmp($fileInfo->getFilename(),".gitkeep") != 0)
        {
          $this->safeDeleteInWorkspace($relativePath."/".$fileInfo->getFilename());
        }
      }
    }

    public function testRunWithoutConfigurationShouldReturnError()
    {
        // TODO: discuess start docker container inside test or outside test
        echo "create repository "."$this->repositoriesPath/testrepo.git"."\n";
        $git = Repository::open($this->repositoriesPath."/testrepo.git", '/usr/bin/git', 0755);
        $commit = $git->writeFile('test.txt', 'Test', 'Added test.txt');
        // Assert
        $this->assertEquals(0, 1);
    }

    // function put_ini_file($file, $array, $i = 0){
    //   $str="";
    //   foreach ($array as $k => $v){
    //     if (is_array($v)){
    //       $str.=str_repeat(" ",$i*2)."[$k]".PHP_EOL;
    //       $str.=put_ini_file("",$v, $i+1);
    //     }
    //     else
    //     {
    //       $str.=str_repeat(" ",$i*2)."$k = $v".PHP_EOL;
    //     }
    //   }
    //   if($file)
    //     return file_put_contents($file,$str);
    //   else
    //     return $str;
    // }

}
