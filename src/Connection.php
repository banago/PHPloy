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
     * @throws \Exception
     *
     * @return Connection
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

    private function getCommonOptions($server)
    {
        $options = [
            'host' => $server['host'],
            'username' => $server['user'],
            'password' => $server['pass'],
            'root' => $server['path'],
            'timeout' => ($server['timeout'] ?: 30),
            'directoryPerm' => $server['directoryPerm'],
        ];
        if ($server['permissions']) {
            $key = sprintf('perm%s', ucfirst($server['visibility']));
            $server[$key] = $server['permissions'];
        }
        if ($server['permPrivate']) {
            $options['permPrivate'] = intval($server['permPrivate'], 0);
        }
        if ($server['permPublic']) {
            $options['permPublic'] = intval($server['permPublic'], 0);
        }
        if ($server['directoryPerm']) {
            $options['directoryPerm'] = intval($server['directoryPerm'], 0);
        }

        return $options;
    }

    /**
     * Connects to the FTP Server.
     *
     * @param string $server
     *
     * @throws \Exception if it can't connect to FTP server
     *
     * @return Filesystem|null
     */
    protected function connectToFtp($server)
    {
        try {
            $options = $this->getCommonOptions($server);
            $options['passive'] = ($server['passive'] ?: true);
            $options['ssl'] = ($server['ssl'] ?: false);
            $options['port'] = ($server['port'] ?: 21);

            return new Filesystem(new FtpAdapter($options));
        } catch (\Exception $e) {
            echo "\r\nOh Snap: {$e->getMessage()}\r\n";
        }
    }

    /**
     * Connects to the SFTP Server.
     *
     * @param string $server
     *
     * @throws \Exception if it can't connect to FTP server
     *
     * @return Filesystem|null
     */
    protected function connectToSftp($server)
    {
        try {
            $options = $this->getCommonOptions($server);
            if (!empty($server['privkey']) && '~' === $server['privkey'][0] && getenv('HOME') !== null) {
                $server['privkey'] = substr_replace($server['privkey'], getenv('HOME'), 0, 1);
            }

            if (!empty($server['privkey']) && !is_file($server['privkey'])) {
                throw new \Exception("Private key {$server['privkey']} doesn't exists.");
            }

            $options['privateKey'] = $server['privkey'];
            $options['port'] = ($server['port'] ?: 22);

            return new Filesystem(new SftpAdapter($options));
        } catch (\Exception $e) {
            echo "\r\nOh Snap: {$e->getMessage()}\r\n";
        }
    }
}
