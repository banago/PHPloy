<?php

namespace Banago\PHPloy\Traits;

trait DebugTrait
{
    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * Enable or disable debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Output debug message
     */
    protected function debug(string $message): void
    {
        if ($this->debug && isset($this->cli)) {
            $this->cli->debug($message);
        }
    }
}
