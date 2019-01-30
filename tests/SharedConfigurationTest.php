<?php

class SharedConfigurationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @return \Banago\PHPloy\Options
     * @throws Exception
     */
    private static function getTestOptions()
    {
        $cli = new \League\CLImate\CLImate();
        $cli->output->defaultTo('buffer');
        return new \Banago\PHPloy\Options($cli, ['phploy', '--dryrun']);
    }

    /**
     * @throws Exception
     */
    public function testSharedServerIsNotVisible()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayNotHasKey('*', $servers);
    }

    /**
     * @throws Exception
     */
    public function testConfigurationPathIsShared()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayHasKey('shared1', $servers);
        self::assertArrayHasKey('shared2', $servers);

        $shared1 = $servers['shared1'];
        $shared2 = $servers['shared2'];

        self::assertArrayHasKey('path', $shared1);
        self::assertArrayHasKey('path', $shared2);
        self::assertEquals($shared1['path'], $shared2['path']);
    }

    /**
     * @throws Exception
     */
    public function testConfigurationPortIsOverwritten()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayHasKey('shared1', $servers);
        self::assertArrayHasKey('shared2', $servers);

        $shared1 = $servers['shared1'];
        $shared2 = $servers['shared2'];

        self::assertArrayHasKey('port', $shared1);
        self::assertArrayHasKey('port', $shared2);
        self::assertNotEquals($shared1['port'], $shared2['port']);
        self::assertNotEquals('2222', $shared1['branch']);
        self::assertNotEquals('2223', $shared2['branch']);
    }

    /**
     * @throws Exception
     */
    public function testConfigurationBranchIsNotShared()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayHasKey('shared1', $servers);
        self::assertArrayHasKey('shared2', $servers);

        $shared1 = $servers['shared1'];
        $shared2 = $servers['shared2'];

        self::assertArrayHasKey('branch', $shared1);
        self::assertArrayHasKey('branch', $shared2);
        self::assertNotEquals('dev', $shared1['branch']);
        self::assertNotEquals('dev', $shared2['branch']);
        self::assertNotEquals($shared1['branch'], $shared2['branch']);
    }

    /**
     * @throws Exception
     */
    public function testConfigurationExclusionsAreMerged()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayHasKey('shared1', $servers);
        self::assertArrayHasKey('shared2', $servers);

        $shared1 = $servers['shared1'];
        $shared2 = $servers['shared2'];

        self::assertArrayHasKey('exclude', $shared1);
        self::assertArrayHasKey('exclude', $shared2);
        self::assertCount(2, $shared1['exclude']);
        self::assertCount(2, $shared2['exclude']);
        self::assertContains('exclude_always.txt', $shared1['exclude']);
        self::assertContains('exclude_1.txt', $shared1['exclude']);
        self::assertContains('exclude_always.txt', $shared2['exclude']);
        self::assertContains('exclude_2.txt', $shared2['exclude']);
    }
}