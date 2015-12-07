<?php

require __DIR__.'/vendor/autoload.php';

use Banago\PHPloy\PHPloy;

/*
 * Run deployment
 */
try {
    $phploy = new PHPloy();
} catch (Exception $e) {
    echo "\r\nOh Snap: {$e->getMessage()}\r\n";
}
