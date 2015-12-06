<?php

namespace Banago\PHPloy;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Sftp\SftpAdapter as SftpAdapter;

class Connection
{
    public $server;

    public function __construct($server)
    {
        if (!isset($server['scheme'])) {
            throw new \Exception("Please provide a connection protocol such as 'ftp' or 'sftp'.");
        }

        if ($server['scheme'] === 'ftp') {
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
     * @throws Exception if it can't connect to FTP server
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
            ]));
        } catch (\Exception $e) {
            print("\r\nOh Snap: {$e->getMessage()}\r\n");
        }
    }

    /**
     * Connects to the SFTP Server.
     *
     * @param string $server
     *
     * @throws Exception if it can't connect to FTP server
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
            print("\r\nOh Snap: {$e->getMessage()}\r\n");
        }
    }
}
