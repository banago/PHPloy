<?php
use TQ\Git\Repository\Repository;
require_once('PHPloyTestCase.php');

class ExecutionErrorTest extends PHPloyTestCase
{
    public function testRunWithoutConfigurationShouldReturnError()
    {
        $git = Repository::open($this->repository, '/usr/bin/git', 0755);
        $commit = $git->writeFile('test.txt', 'Test', 'Added test.txt');

        $executionResult = $this->whenRepositoryIsSynchronized();
        $this->assertEquals(1, $executionResult);
    }

    public function testRunWithoutGitRepositoryShouldReturnError()
    {
        mkdir($this->repository);
        copy(realpath(dirname(__FILE__)."/resources/phploy.ini"), $this->repository."/phploy.ini");

        $executionResult = $this->whenRepositoryIsSynchronized();
        $this->assertEquals(1, $executionResult);
    }
}
