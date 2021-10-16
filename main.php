<?php
/**
 *
 * @copyright       Copyright (c) 2021. Giacomo Tüfekci (https://www.tuefekci.de)
 * @github          https://github.com/tuefekci
 * @license         https://www.tuefekci.de/LICENSE.md
 *
 */
require_once(__DIR__ . '/vendor/autoload.php');


// =================================================================
// Defines
define("__ROOT__", __DIR__);
define("__APP__", __ROOT__."/app");
define("__DATA__", __ROOT__."/data");

define("__BLACKHOLE__", __DATA__."/blackhole");
define("__DOWNLOADS__", __DATA__."/downloads");



define("__CONF__", __ROOT__."/config");
define("__PUBLIC__", __ROOT__."/web/public");
// =================================================================

// =================================================================
// Ini Sets
ini_set('always_populate_raw_post_data', -1);
ini_set("memory_limit","1024M");
// =================================================================

// =================================================================
// Define Timezone
date_default_timezone_set('Europe/Berlin');
// =================================================================

// =================================================================
// DEV OR NOT DEV!
$debug = true;

if(!empty(getenv("DEBUG")) && filter_var(getenv("DEBUG"), FILTER_VALIDATE_BOOLEAN)) {
    $debug = filter_var(getenv("DEBUG"), FILTER_VALIDATE_BOOLEAN);
}

// In DEV Mode we want full error reporting.
if($debug) {
    error_reporting(E_ALL);
} else {
    // Todo: Sentry or Logfile Error Reporting
}

define("DEBUG", $debug);


$verbose = true;

if(!empty(getenv("VERBOSE")) && filter_var(getenv("VERBOSE"), FILTER_VALIDATE_BOOLEAN)) {
    $verbose = filter_var(getenv("VERBOSE"), FILTER_VALIDATE_BOOLEAN);
}

if(!$verbose) {
    $options = getopt("v");
    if(isset($options['v'])) {
        $verbose = true;
    }
}

define("VERBOSE", $verbose);
// =================================================================

// =================================================================
// Custom Autoload
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'GT\BLK') !== false) {
        $class_name = str_replace("GT\BLK", "", $class_name);
        $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
        $class_name = strtolower($class_name);
        include __APP__. DIRECTORY_SEPARATOR . $class_name . '.php';
    }
});
// =================================================================

$app = new GT\BLK\App($loop);
$web = new GT\BLK\Web($app);

// =================================================================
// Last Line, start the Loops
$app->run();
$web->run();
// =================================================================