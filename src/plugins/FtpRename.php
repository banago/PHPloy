<?php

namespace Banago\PHPloy\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class FtpRename extends AbstractPlugin
{
    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        return 'ftpRename';
    }

    public function handle($path, $newpath)
    {

        return (bool) $this->filesystem->getAdapter()->rename($path, $newpath);

    }
}
