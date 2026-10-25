<?php

namespace Banago\PHPloy;

use Banago\PHPloy\Traits\DebugTrait;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToWriteFile;

/**
 * Class Connection.
 */
class Connection
{
    use DebugTrait;

    /**
     * @var Filesystem
     */
    public $server;

    /**
     * Connection constructor.
     *
     * @param array $server
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
            'visibility' => $server['visibility'] ?? 'public',
            'permPublic' => $server['permPublic'] ?? 0644,
            'permPrivate' => $server['permPrivate'] ?? 0600,
            'directoryPerm' => $server['directoryPerm'] ?? 0755,
        ];

        return $options;
    }

    /**
     * Connects to the FTP Server.
     *
     * @param array $server
     *
     * @throws \Exception if it can't connect to FTP server
     *
     * @return Filesystem|null
     */
    protected function connectToFtp($server)
    {
        try {
            $options = $this->getCommonOptions($server);

            $config = new FtpConnectionOptions(
                $options['host'],
                $options['root'],
                $options['username'],
                $options['password'],
                $server['port'] ?? 21,
                $server['ssl'] ?? false,
                $options['timeout'] ?? 90,
                false, //utf8
                $server['passive'] ?? true,
                FTP_BINARY, // transferMode
                null, // ignorePassiveAddress
                30, // timestampsOnUnixListingsEnabled
                true // recurseManually
            );

            $visibility = PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => $options['permPublic'],
                    'private' => $options['permPrivate'],
                ],
                'dir' => [
                    'public' => $options['directoryPerm'],
                    'private' => $options['directoryPerm'],
                ],
            ]);

            return new Filesystem(new FtpAdapter($config, null, null, $visibility));
        } catch (\Exception $e) {
            echo "\r\nOh Snap: {$e->getMessage()}\r\n";
            throw $e;
        }
    }

    /**
     * Connects to the SFTP Server.
     *
     * @param array $server
     *
     * @throws \Exception if it can't connect to SFTP server
     *
     * @return Filesystem|null
     */
    protected function connectToSftp($server)
    {
        try {
            $options = $this->getCommonOptions($server);

            if (!empty($server['privkey']) && '~' === $server['privkey'][0] && getenv('HOME') !== null) {
                $options['privkey'] = substr_replace($server['privkey'], getenv('HOME'), 0, 1);
            }

            if (!empty($options['privkey']) && !is_file($options['privkey']) && "---" !== substr($options['privkey'], 0, 3)) {
                throw new \Exception("Private key {$options['privkey']} doesn't exists.");
            }
            if (!empty($server['port'])) {
                $options['port'] = (int)$server['port'];
            }

            $connectionProvider = new SftpConnectionProvider(
                $options['host'],
                $options['username'],
                $options['password'],
                $options['privkey'] ?? null, // privkey
                $options['passphrase'] ?? null, // passphrase
                $options['port'] ?? 22,
                false, // use agent
                30, // timeout
                3, // max tries
                null, // host fingerprint
                null // connectivity checker
            );

            $visibility = PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => $options['permPublic'],
                    'private' => $options['permPrivate'],
                ],
                'dir' => [
                    'public' => $options['directoryPerm'],
                    'private' => $options['directoryPerm'],
                ],
            ]);

            return new Filesystem(new SftpAdapter($connectionProvider, $options['root'], $visibility));
        } catch (\Exception $e) {
            echo "\r\nOh Snap: {$e->getMessage()}\r\n";
            throw $e;
        }
    }

    /**
     * Check if a file exists
     *
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        return $this->server->fileExists($path);
    }

    /**
     * Check if a directory exists
     *
     * @param string $path
     * @return bool
     */
    public function directoryExists($path)
    {
        try {
            return $this->server->directoryExists($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Read a file
     *
     * @param string $path
     * @return string
     */
    public function read($path)
    {
        try {
            return $this->server->read($path);
        } catch (UnableToReadFile $e) {
            return '';
        }
    }

    /**
     * Write a file
     *
     * @param string $path
     * @param string $contents
     * @return bool
     */
    public function put($path, $contents)
    {
        try {
            $this->server->write($path, $contents);
            return true;
        } catch (UnableToWriteFile $e) {
            return false;
        }
    }

    /**
     * Delete a file
     *
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        try {
            $this->server->delete($path);
            return true;
        } catch (UnableToDeleteFile $e) {
            return false;
        }
    }

    /**
     * Delete a directory
     *
     * @param string $path
     * @return bool
     */
    public function deleteDir($path)
    {
        try {
            $this->server->deleteDirectory($path);
            return true;
        } catch (UnableToDeleteDirectory $e) {
            return false;
        }
    }

    /**
     * Create a directory
     *
     * @param string $path
     * @return bool
     */
    public function createDir($path)
    {
        try {
            $this->server->createDirectory($path);
            return true;
        } catch (UnableToCreateDirectory $e) {
            return false;
        }
    }

    /**
     * List directory contents
     *
     * @param string $path
     * @param bool $recursive
     * @return array
     */
    public function listContents($path, $recursive = false)
    {
        $contents = [];
        $listing = $this->server->listContents($path, $recursive);

        foreach ($listing as $item) {
            $contents[] = [
                'path' => $item->path(),
                'type' => $item->isFile() ? 'file' : 'dir',
            ];
        }

        return $contents;
    }
}
