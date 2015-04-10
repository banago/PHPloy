<?php
require __DIR__ . '/vendor/autoload.php';

use Banago\PHPloy\PHPloy;
use Banago\PHPloy\Ansi;

/**
 * Transforms all PHP errors into Exceptions
 *
 * I added this because, when implementing key-based SSH authentication, I discovered
 * that the ssh2_auth_pubkey_file function (which is used internally by Bridge)
 * triggers an E_WARNING error when it cannot authenticate.
 *
 * Therefore, in order to display the error message in a friendly way (using the Ansi class),
 * all PHP errors must be converted to Exceptions
 */
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}, error_reporting());

/**
 * Run deployment
 */
try {
    $phploy = new PHPloy();
} catch (Exception $e) {
    echo Ansi::tagsToColors("\r\n<red>Oh Snap: {$e->getMessage()}\r\n");
}