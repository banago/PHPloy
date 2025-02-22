<?php

require __DIR__.'/vendor/autoload.php';

/*
 * Run PHPloy
 */
try {
    $phploy = new Banago\PHPloy\PHPloy();
} catch (Exception $e) {
    echo PHP_EOL, "Oh Snap: {$e->getMessage()}", PHP_EOL;
    // Return 1 to indicate error to caller
    exit(1);
}
