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

    public Filesystem $filesystem;

    public array $torrents = array();
    public array $magnets = array();

    public function __construct() {
        $app = $this;

        $this->filesystem = filesystem();
        $this->cli = new \League\CLImate\CLImate;
        $this->config = \Noodlehaus\Config::load(__CONF__.'/config.ini')->all();


        $this->createFolders();
    }

    public function createFolders() {


        $app = $this;

        // Webinterface Blackhole
        $app->filesystem->exists(__BLACKHOLE__."/webinterface")->onResolve(function ($error, $exists) use ($app) {
            if ($error) {
                $app->error("webinterface->checkFolder", $error->getMessage());
            } else {

                if($exists) {
                    
                } else {
                    $app->filesystem->createDirectoryRecursively(__BLACKHOLE__."/webinterface")->onResolve(function ($error, $value) use ($app) {
                        if ($error) {
                            $app->error("webinterface->createFolder", $error->getMessage());
                        } else {
                        }
                    });
                }

            }

        });

        // Webinterface Downloads
        $app->filesystem->exists(__DOWNLOADS__."/webinterface")->onResolve(function ($error, $exists) use ($app) {
            if ($error) {
                $app->error("webinterface->checkFolder", $error->getMessage());
            } else {

                if($exists) {
                    
                } else {
                    $app->filesystem->createDirectoryRecursively(__DOWNLOADS__."/webinterface")->onResolve(function ($error, $value) use ($app) {
                        if ($error) {
                            $app->error("webinterface->createFolder", $error->getMessage());
                        } else {
                        }
                    });
                }

            }

        });

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
        // loops
        Loop::repeat($msInterval = 1000, function () {
            //var_dump($this->torrents);
            //var_dump($this->magnets);
        });

        \Amp\Loop::repeat($msInterval = 10000, function () use ($provider) {
            $provider->getStatus();
        });

        \Amp\Loop::repeat($msInterval = 5000, function () {
            $this->checkFiles();
        });

        \Amp\Loop::repeat($msInterval = 5000, function () {
            $this->handleMagnets();
            $this->checkProvider();
            $this->checkDownload();
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

    // Check Downloads for completed downloads and then clean up!
    private function checkDownload() {

        foreach($this->magnets as $path => $magnet) {
            if(!empty($magnet['downloads'])) {

                $check = array();

                foreach($magnet['downloads'] as $dlId) {
                    $download = $this->downloader->get($dlId);

                    if($download) {
                        $check[] = $download->done;
                    } else {
                        $check[] = false;
                    }
                }


                if(!in_array(false, $check, true)) {
                    // remove magnet and complete downloads etc.

                    $this->filesystem->deleteFile($path);
                    unset($this->magnets[$path]);

                    // remove downloads
                    foreach($magnet['downloads'] as $dlId) {
                        $download = $this->downloader->remove($dlId);
                    }

                    // remove from provider
                    $this->provider->delete($magnet['provider']->id);
                }
                



            }
        }

    }


    // Check Provider if files are availble for download to disk.
    private function checkProvider() {

        $_this = $this;

        foreach($this->provider->status() as $status) {

            foreach($this->magnets as $key => $magnet) {

                if(!empty($magnet['provider']) && empty($magnet['downloads'])) {

                    if($status->id == $magnet['provider']->id) {

                        if(!empty($status->links)) {

                            $this->magnets[$key]['downloads'] = array();

                            foreach($status->links as $link) {

                                // Get Download Link
                                $this->provider->getDownload($link->link)->onResolve(function ($error, $data) use ($_this, $magnet, $key, $link) {
                                    if ($error) {
                                        $_this->error("handleMagnets->addMagnet", $error->getMessage());
                                    } else {

                                        // Start Download
                                        $dlId = uniqid($magnet['provider']->id."_");
                                        $this->downloader->add($dlId, __DOWNLOADS__."/".basename($magnet['dirname'])."/".$magnet['filename']."/".$data->filename, $data->link, $link->size);
                                        $_this->magnets[$key]['downloads'][] = $dlId;
                                        
                                    }
                                });


                            }

                        }

                    }

                }

            }

        }


    }


    // send magnets to Provider for download to cloud.
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


    // check blackhole for magnets and torrents
    private function checkFiles() {
        //$this->info(__BLACKHOLE__, "checkFiles->blackhole");

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