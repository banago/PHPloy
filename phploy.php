<?php
/**
 * Configuration
 */
ini_set('memory_limit', '512M');
set_time_limit(60*60*60); // 60 min
define('BASE_PATH', __DIR__);

/**
 * Base
 */
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . BASE_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH);


require __DIR__.'/vendor/autoload.php';

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