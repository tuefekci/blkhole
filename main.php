<?php
/**
 *
 * @copyright       Copyright (c) 2021. Giacomo Tüfekci (https://www.tuefekci.de)
 * @github          https://github.com/tuefekci
 * @license         https://www.tuefekci.de/LICENSE.md
 *
 */
require_once(__DIR__ . '/vendor/autoload.php');

use Amp\Loop;

// =================================================================
// Init Environment Variables

if(\tuefekci\helpers\System::isDocker()) {
 
    // convert environment variables to constants
    foreach ($_ENV as $key => $value) {
        define($key, $value);
    }

} else {


    $env = \tuefekci\helpers\System::getEnv();
}



// =================================================================
// Defines
// Static
define("__ROOT__", realpath(__DIR__));
define("__APP__", realpath(__ROOT__."/app"));
define("__DATA__", realpath(__ROOT__."/data"));
define("__PUBLIC__", realpath(__ROOT__."/web/build"));

// Dynamic
define("__CONF__", realpath(__DATA__."/config"));
define("__LOGS__", realpath(__DATA__."/logs"));
define("__CACHE__", realpath(__DATA__."/cache"));

define("__BLACKHOLE__", realpath(__DATA__."/blackhole"));
define("__DOWNLOADS__", realpath(__DATA__."/downloads")); 
// =================================================================

// =================================================================
// Set MEMORY_LIMIT
if(getEnv('MEMORY_LIMIT')) {
    ini_set('memory_limit', getEnv('MEMORY_LIMIT'));
} else {
    ini_set("memory_limit","1024M");
}
// =================================================================

// =================================================================
// Define Timezone
if(getEnv('TZ')) {
    date_default_timezone_set(getEnv('TZ'));
} else {
    date_default_timezone_set('Europe/Berlin');
}
// =================================================================

// =================================================================
// DEV OR NOT DEV!
error_reporting(E_ALL);
define("VERBOSE", true);
// =================================================================



// =================================================================
// Start Application
Loop::run(function() use ($app, $web) {
    $app = new tuefekci\blk\App();
    $web = new tuefekci\blk\Web($app);

    $app->run();
    $web->run();
});
// =================================================================