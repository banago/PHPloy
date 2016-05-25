<?php

namespace Banago\PHPloy;

use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter as SftpAdapter;

/**
 * Class Connection.
 */
class Connection
{
    /**
     * @var Filesystem
     */
    public $server;

    /**
     * Connection constructor.
     *
     * @param string $server
     *
     * @return Connection
     *
     * @throws \Exception
     */
    public function __construct($server)
    {
        if (!isset($server['scheme'])) {
            throw new \Exception("Please provide a connection protocol such as 'ftp' or 'sftp'.");
        }

        if ($server['scheme'] === 'ftp' or $server['scheme'] === 'ftps') {
            $this->server = $this->connectToFtp($server);
        } elseif ($server['scheme'] === 'sftp') {
            $this->server = $this->connectToSftp($server);
        } else {
            throw new \Exception("Please provide a known connection protocol such as 'ftp' or 'sftp'.");
        }
    }

    /**
     * Connects to the FTP Server.
     *
     * @param string $server
     *
     * @return Filesystem|null
     *
     * @throws \Exception if it can't connect to FTP server
     */
    protected function connectToFtp($server)
    {
        try {
            return new Filesystem(new FtpAdapter([
                'host' => $server['host'],
                'username' => $server['user'],
                'password' => $server['pass'],
                'port' => ($server['port'] ?: 21),
                'root' => $server['path'],
                'passive' => ($server['passive'] ?: true),
                'timeout' => ($server['timeout'] ?: 30),
                'ssl' => ($server['ssl'] ?: false),
            ]), [
                'visibility' => ($server['visibility'] ?: 'private')
            ]);
        } catch (\Exception $e) {
            echo "\r\nOh Snap: {$e->getMessage()}\r\n";
        }

        return;
    }

    /**
     * Connects to the SFTP Server.
     *
     * @param string $server
     *
     * @return Filesystem|null
     *
     * @throws \Exception if it can't connect to FTP server
     */
    protected function connectToSftp($server)
    {
        try {
            return new Filesystem(new SftpAdapter([
                'host' => $server['host'],
                'username' => $server['user'],
                'password' => $server['pass'],
                'port' => ($server['port'] ?: 22),
                'root' => $server['path'],
                'timeout' => ($server['timeout'] ?: 30),
                'privateKey' => $server['privkey'],
            ]));
        } catch (\Exception $e) {
            echo "\r\nOh Snap: {$e->getMessage()}\r\n";
        }

        return;
    }
}
