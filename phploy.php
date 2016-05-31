<?php

require __DIR__.'/vendor/autoload.php';

use Banago\PHPloy\PHPloy;

/*
 * Run PHPloy
 */
try {
    $phploy = new PHPloy();
} catch (Exception $e) {
    echo "\r\nOh Snap: {$e->getMessage()}\r\n";
    // Return 1 to indicate error to caller
    exit(1);
}
