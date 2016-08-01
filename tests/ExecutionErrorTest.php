<?php
use TQ\Git\Repository\Repository;

require_once('PHPLoyTestHelper.php');

class ExecutionErrorTest extends PHPUnit_Framework_TestCase
{
  public function testRunWithoutConfigurationShouldReturnError()
  {
    $testHelper = new PHPLoyTestHelper('sftp', false);
    $git = Repository::open($testHelper->repository, '/usr/bin/git', 0755);
    $commit = $git->writeFile('test.txt', 'Test', 'Added test.txt');

    $executionResult = $testHelper->whenRepositoryIsSynchronized();
    $this->assertEquals(1, $executionResult);
  }

  public function testRunWithoutGitRepositoryShouldReturnError()
  {
    $testHelper = new PHPLoyTestHelper('sftp', false);
    mkdir($testHelper->repository);
    copy(realpath(dirname(__FILE__)."/resources/sftp_phploy.ini"), $testHelper->repository."/phploy.ini");

    $executionResult = $testHelper->whenRepositoryIsSynchronized();
    $this->assertEquals(1, $executionResult);
  }
}
