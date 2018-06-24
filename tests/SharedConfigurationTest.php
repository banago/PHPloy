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
        self::assertArrayHasKey('shard1', $servers);
        self::assertArrayHasKey('shard2', $servers);

        $shard1 = $servers['shard1'];
        $shard2 = $servers['shard2'];

        self::assertArrayHasKey('path', $shard1);
        self::assertArrayHasKey('path', $shard2);
        self::assertEquals($shard1['path'], $shard2['path']);
    }

    /**
     * @throws Exception
     */
    public function testConfigurationPortIsOverwritten()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayHasKey('shard1', $servers);
        self::assertArrayHasKey('shard2', $servers);

        $shard1 = $servers['shard1'];
        $shard2 = $servers['shard2'];

        self::assertArrayHasKey('port', $shard1);
        self::assertArrayHasKey('port', $shard2);
        self::assertNotEquals($shard1['port'], $shard2['port']);
        self::assertNotEquals('2222', $shard1['branch']);
        self::assertNotEquals('2223', $shard2['branch']);
    }

    /**
     * @throws Exception
     */
    public function testConfigurationBranchIsNotShared()
    {
        $phploy = new \Banago\PHPloy\PHPloy(self::getTestOptions());
        $servers = $phploy->prepareServers(__DIR__ . '/resources/shared_phploy.ini');
        self::assertArrayHasKey('shard1', $servers);
        self::assertArrayHasKey('shard2', $servers);

        $shard1 = $servers['shard1'];
        $shard2 = $servers['shard2'];

        self::assertArrayHasKey('branch', $shard1);
        self::assertArrayHasKey('branch', $shard2);
        self::assertNotEquals('dev', $shard1['branch']);
        self::assertNotEquals('dev', $shard2['branch']);
        self::assertNotEquals($shard1['branch'], $shard2['branch']);
    }
}