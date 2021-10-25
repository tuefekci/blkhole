<?php

namespace GT\BLK;

use Amp\Cache\Cache;
use Amp\Cache\FileCache;
use Amp\Cache\PrefixCache;
use Amp\Sync\LocalKeyedMutex;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Loop;
use Amp\File\File;
use Amp\File\Filesystem;
use function Amp\File\filesystem;

class App {
    public $cli;
    public $config;



    public PrefixCache $cache;
    public Filesystem $filesystem;

    public array $torrents = array();
    public array $magnets = array();

    public function __construct() {
        $app = $this;

        $this->filesystem = filesystem();
        $this->cli = new \League\CLImate\CLImate;
        $this->config = \Noodlehaus\Config::load(__CONF__.'/config.ini')->all();


        // =================================================================
        // init cache
        $app->filesystem->exists(__CONF__.'/cache')->onResolve(function ($error, $exists) use ($app) {
            if ($error) {
                $app->error("cache->exists", $error->getMessage());
            } else {

                if($exists) {
                    $this->cache = new PrefixCache(new FileCache(__CONF__.'/cache', new \Amp\Sync\LocalKeyedMutex()), 'amphp-cache-');
                } else {
                    $app->filesystem->createDirectoryRecursively(__CONF__.'/cache')->onResolve(function ($error, $value) use ($app) {
                        if ($error) {
                            $app->error("cache->createFolder", $error->getMessage());
                        } else {
                            $this->cache = new PrefixCache(new FileCache(__CONF__.'/cache', new \Amp\Sync\LocalKeyedMutex()), 'amphp-cache-');
                        }
                    });
                }

            }
        });
        // =================================================================
    }

    public function run() {

        $app = $this;

        $this->cli->clear();
        $this->cli->break();
        $this->cli->lightGreen()->border("*");
        $this->cli->lightGreen()->out('* blkHole');
        $this->cli->lightGreen()->out('* (c) 2020-'.date("Y").' Giacomo Tüfekci');
        $this->cli->lightGreen()->out('* https://github.com/tuefekci/blkhole');
        $this->cli->lightGreen()->border("*");
        $this->cli->lightGreen()->break();

        // =================================================================
        // init providers
        $provider = new Provider\Alldebrid($this);
        $this->provider = $provider;

        // =================================================================
        // init downloaders
        $downloader = new Downloader\Controller($this);
        $this->downloader = $downloader;

        // =================================================================
        // loop

        /*
        $this->loop->addPeriodicTimer(30, function () {
            // Read Files
            // Upload to Provider
            // Check Provider
            // Download
            // Remove Task/Download
        });
        */

        /*
        $downloader->add("test1", __DATA__."/downloads/test/".uniqid("test_")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
        $downloader->add("test2", __DATA__."/downloads/test/".uniqid("test_")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
        $downloader->add("test3", __DATA__."/downloads/test/".uniqid("test_")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
        $downloader->add("test4", __DATA__."/downloads/test/".uniqid("test_")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
        $downloader->add("test5", __DATA__."/downloads/test/".uniqid("test_")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
        $downloader->add("test6", __DATA__."/downloads/test/".uniqid("test_")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");

*/

        Loop::repeat($msInterval = 1000, function () {
            //var_dump($this->torrents);
            //var_dump($this->magnets);
        });

        $this->checkFiles();
        Loop::repeat($msInterval = 10000, function () {
            $this->checkFiles();
        });

        \Amp\Loop::repeat($msInterval = 5000, function ($watcherId) use ($provider) {
            $provider->getStatus();
            $this->handleMagnets();
        });


        /*
        Loop::repeat($msInterval = 3000, function () {
            echo "test3".PHP_EOL;
        });

        Loop::repeat($msInterval = 4000, function () {
            echo "test4".PHP_EOL;
        });
        */

        //Loop::delay($msDelay = 5000, "Amp\\Loop::stop");



        // =================================================================
    }

    private function handleMagnets() {

        $_this = $this;

        foreach($this->magnets as $path => $magnet) {
            if(empty($magnet['provider'])) {

                $this->filesystem->read($path)->onResolve(function ($error, $magnet) use ($_this, $path) {
                    if ($error) {
                        $this->error("handleMagnets->read", $error->getMessage());
                    } else {

                        $this->provider->addMagnet($magnet)->onResolve(function ($error, $data) use ($_this, $path) {
                            if ($error) {
                                $this->error("handleMagnets->addMagnet", $error->getMessage());
                            } else {
                                $_this->magnets[$path]['provider'] = $data;
                            }
                        });

                    }
                });

            }
        }


    }

    private function checkFiles() {
        $this->info(__BLACKHOLE__, "checkFiles->blackhole");

        $this->filesystem->listFiles(__BLACKHOLE__)->onResolve(function ($error, $files) {
            if ($error) {
                $this->error("torrents", $error->getMessage());
            } else {

                foreach($files as $file) {
                    if($this->filesystem->isDirectory(__BLACKHOLE__.DIRECTORY_SEPARATOR.$file)) {

                        $path = __BLACKHOLE__.DIRECTORY_SEPARATOR.$file;

                        $this->filesystem->listFiles(__BLACKHOLE__.DIRECTORY_SEPARATOR.$file)->onResolve(function ($error, $files) use ($path) {
                            if ($error) {
                                $this->error("torrents", $error->getMessage());
                            } else {

                                foreach($files as $file) {

                                    $filePath = $path.DIRECTORY_SEPARATOR.$file;
                                    $pathInfo = pathinfo($filePath);

                                    if($pathInfo['extension'] == "magnet") {

                                        if(!isset($this->magnets[$filePath])) {
                                            $this->magnets[$filePath] = $pathInfo;
                                        }

                                    }elseif($pathInfo['extension'] == "torrent") {
                                        
                                        if(!isset($this->torrents[$filePath])) {
                                            $this->torrents[$filePath] = $pathInfo;
                                        }

                                    }

                                }

                            }
          
                        });
                    }
                }
            }
        });
        
    }

    private function out($color, $message, $header=false) {

        $this->cli->$color()->inline("[".date("Y-m-d H:i:s")."] ");

        if($header && is_string($header)) {
            $this->cli->$color()->inline("(".$header.") ");
        }

        if(is_string($message)) {
            $this->cli->inline($message);
            $this->cli->break();
        } elseif(is_array($message)) {
            var_dump($message);
        } else {
            $this->cli->break();
        }
    }

    public function log($message, $header=false) {
        $this->out("Yellow", $message, $header);
    }

    public function info($message, $header=false) {
        if(!VERBOSE) {
            return true;
        }
        $this->out("Cyan", $message, $header);
    }

    public function warn($message, $header=false) {
        $this->out("Orange", $message, $header);
    }

    public function error($message, $header=false) {
        $this->out("Red", $message, $header);
    }

    function filesize_formatted($size)
    {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

}