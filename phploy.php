<?php

require __DIR__.'/vendor/autoload.php';

/*
 * Run PHPloy
 */
try {
    // Enable error reporting for better debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Initialize PHPloy
    $phploy = new Banago\PHPloy\PHPloy();
} catch (Exception $e) {
    echo "\r\nOh Snap: {$e->getMessage()}\r\n";
    // Return 1 to indicate error to caller
    exit(1);
}
