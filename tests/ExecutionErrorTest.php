<?php
use TQ\Git\Repository\Repository;
require_once('PHPloyTestCase.php');

class ExecutionErrorTest extends PHPloyTestCase
{
    public function testRunWithoutConfigurationShouldReturnError()
    {
        $repositoryPath = $this->repositoriesPath."/testrepo.git";
        // echo "create repository ".$repositoryPath."\n";
        $git = Repository::open($repositoryPath, '/usr/bin/git', 0755);
        $commit = $git->writeFile('test.txt', 'Test', 'Added test.txt');

        chdir($repositoryPath);
        $executionResult = $this->runSync();
        $this->assertEquals(1, $executionResult);
    }

    public function testRunWithoutGitRepositoryShouldReturnError()
    {
        $repositoryPath = $this->repositoriesPath."/testrepo.git";
        // echo "create repository ".$repositoryPath."\n";
        mkdir($repositoryPath);
        // TODO add valid phploy.ini configuration
        chdir($repositoryPath);
        $executionResult = $this->runSync();

        $this->assertEquals(1, $executionResult);
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
