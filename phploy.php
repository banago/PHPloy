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
    // PHPloy so far returned 0 on error which indicated success to the caller, changed to return 1 to indicate error
    exit(1);
}
