<?php
require __DIR__ . '/vendor/autoload.php';

use Banago\PHPloy\PHPloy;
use Banago\PHPloy\Ansi;

/**
 * Run deployment
 */
try {
    $phploy = new PHPloy();
} catch (Exception $e) {
    echo Ansi::tagsToColors("\r\n<red>Oh Snap: {$e->getMessage()}\r\n");
}
