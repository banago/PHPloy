<?php
use TQ\Git\Repository\Repository;
require_once('PHPloyTestCase.php');

class CommitFileTest extends PHPloyTestCase
{
  protected $git;

  public function testSyncAddedFileShouldSucceed()
  {
    $this->givenRepositoryWithConfiguration();
    $this->whenFileIsAdded();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  public function testSyncDeletedFileShouldSucceed()
  {
    $this->givenSynchronizedRepositoryWithSingleFile();
    $this->whenFileIsDeleted();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  public function testSyncChangedFileShouldSucceed()
  {
    $this->givenSynchronizedRepositoryWithSingleFile();
    $this->whenFileIsChanged();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  // test helper methods
  protected function givenRepositoryWithConfiguration()
  {
    $this->git = Repository::open($this->repository, '/usr/bin/git', 0755);
    $result = $this->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      copy(realpath(dirname(__FILE__)."/resources/phploy.ini"), $this->repository."/phploy.ini");
      $t->setCommitMsg('Add configuration');
    });
  }

  protected function givenSynchronizedRepositoryWithSingleFile()
  {
    $this->givenRepositoryWithConfiguration();
    $this->whenFileIsAdded();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  protected function whenFileIsAdded()
  {
    $commit = $this->git->writeFile('test.txt', 'Test', 'Added test.txt');
    $this->whenRepositoryIsSynchronized();
  }

  protected function whenFileIsDeleted()
  {
    $result = $this->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      unlink($this->repository."/test.txt");
      $t->setCommitMsg('Deleted test.txt');
    });
    $this->whenRepositoryIsSynchronized();
  }

  protected function whenFileIsChanged()
  {
    $result = $this->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      $myfile = file_put_contents($this->repository."/test.txt", "change".PHP_EOL , FILE_APPEND);
      $t->setCommitMsg('Changed test.txt');
    });
    $this->whenRepositoryIsSynchronized();
  }

  protected function thenRepositoryIsSynchronizedSuccessfully()
  {
    $this->assertEquals(0, $this->synchronizationResult);
    $this->assertShareInSync();
  }

}
