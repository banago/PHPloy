<?php
use TQ\Git\Repository\Repository;
require_once('PHPloyTestCase.php');

class CommitFileTest extends PHPloyTestCase
{
  protected $git;

  protected function givenRepositoryWithConfiguration()
  {
    $this->git = Repository::open($this->repository, '/usr/bin/git', 0755);
    $commit = $this->git->writeFile('test.txt', 'Test', 'Added test.txt');
    $result = $this->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      copy(realpath(dirname(__FILE__)."/resources/phploy.ini"), $this->repository."/phploy.ini");
      $t->setCommitMsg('Add configuration');
    });
  }

  protected function whenFileIsDeleted()
  {
    $result = $this->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      unlink($this->repository."/test.txt");
      $t->setCommitMsg('Add configuration');
    });
  }

  public function testSyncCommittedFileShouldSucceed()
  {
    $this->givenRepositoryWithConfiguration();
    $this->whenRepositoryIsSynchronized();
    $this->assertEquals(0, $this->synchronizationResult);
    $this->assertShareInSync();
  }

  public function testSuncDeletedFileShouldSucceed()
  {
    $this->givenRepositoryWithConfiguration();
    $this->whenRepositoryIsSynchronized();
    $this->assertEquals(0, $this->synchronizationResult);
    $this->assertShareInSync();

    $this->whenFileIsDeleted();
    $this->whenRepositoryIsSynchronized();
    $this->assertEquals(0, $this->synchronizationResult);
    $this->assertShareInSync();

  }
}
