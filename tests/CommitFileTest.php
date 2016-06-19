<?php
require_once('PHPLoyTestHelper.php');

class CommitFileTest extends PHPUnit_Framework_TestCase
{
  public function provider()
  {
    return array(
      array('ftp'),
      array('sftp')
    );
  }

  protected $testHelper;
  /**
  * @dataProvider provider
  */
  public function testSyncAddedFileShouldSucceed($testHelper)
  {
    $this->testHelper = new PHPLoyTestHelper($testHelper);
    $this->testHelper->givenRepositoryWithConfiguration();
    $this->whenFileIsAdded();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  /**
  * @dataProvider provider
  */
  public function testSyncDeletedFileShouldSucceed($testHelper)
  {
    $this->testHelper = new PHPLoyTestHelper($testHelper);
    $this->givenSynchronizedRepositoryWithSingleFile();
    $this->whenFileIsDeleted();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  /**
  * @dataProvider provider
  */
  public function testSyncChangedFileShouldSucceed($testHelper)
  {
    $this->testHelper = new PHPLoyTestHelper($testHelper);
    $this->givenSynchronizedRepositoryWithSingleFile();
    $this->whenFileIsChanged();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  protected function givenSynchronizedRepositoryWithSingleFile()
  {
    $this->testHelper->givenRepositoryWithConfiguration();
    $this->whenFileIsAdded();
    $this->thenRepositoryIsSynchronizedSuccessfully();
  }

  protected function whenFileIsAdded()
  {
    $commit = $this->testHelper->git->writeFile('test.txt', 'Test', 'Added test.txt');
    $this->testHelper->whenRepositoryIsSynchronized();
  }

  protected function whenFileIsDeleted()
  {
    $commit = $this->testHelper->git->removeFile('test.txt', 'Remove test.txt');
    $this->testHelper->whenRepositoryIsSynchronized();
  }

  protected function whenFileIsChanged()
  {
    $result = $this->testHelper->git->transactional(function(TQ\Vcs\Repository\Transaction $t) {
      $myfile = file_put_contents($this->testHelper->repository."/test.txt", "change".PHP_EOL , FILE_APPEND);
      $t->setCommitMsg('Changed test.txt');
    });
    $this->testHelper->whenRepositoryIsSynchronized();
  }

  public function thenRepositoryIsSynchronizedSuccessfully()
  {
    $this->assertEquals(0, $this->testHelper->getSynchronizationResult());
    $this->assertEquals(true, $this->testHelper->shareInSync());
  }

}
