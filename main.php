<?php
/**
 *
 * @copyright       Copyright (c) 2021. Giacomo Tüfekci (https://www.tuefekci.de)
 * @github          https://github.com/tuefekci
 * @license         https://www.tuefekci.de/LICENSE.md
 *
 */
require_once(__DIR__ . '/vendor/autoload.php');

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Loop;

// =================================================================
// Defines
define("__ROOT__", realpath(__DIR__));
define("__APP__", realpath(__ROOT__."/app"));
define("__DATA__", realpath(__ROOT__."/data"));

define("__BLACKHOLE__", realpath(__DATA__."/blackhole"));
define("__DOWNLOADS__", realpath(__DATA__."/downloads"));

define("__CONF__", realpath(__ROOT__."/config"));
define("__PUBLIC__", realpath(__ROOT__."/web/build"));
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
error_reporting(E_ALL);
define("VERBOSE", true);
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

$app = new GT\BLK\App();
$web = new GT\BLK\Web($app);

// =================================================================
// Last Line, start the Loops
Loop::run(function() use ($app, $web) {
    $app->run();
    $web->run();
});
// =================================================================