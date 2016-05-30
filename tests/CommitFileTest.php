<?php
use TQ\Git\Repository\Repository;
require_once('PHPloyTestCase.php');

class CommitFileTest extends PHPloyTestCase
{
  public function testSyncCommittedFileShouldSucceed()
  {
      $git = Repository::open($this->repository, '/usr/bin/git', 0755);
      $commit = $git->writeFile('test.txt', 'Test', 'Added test.txt');
      $result = $git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
        copy(realpath(dirname(__FILE__)."/resources/phploy.ini"), $this->repository."/phploy.ini");
        $t->setCommitMsg('Add configuration');
      });

      $executionResult = $this->runSync();
      $this->assertEquals(0, $executionResult);

      // verify that repository and share are in sync
  }
}
