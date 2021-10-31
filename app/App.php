<?php

namespace tuefekci\blk;

use Amp\Loop;
use Amp\File\File;
use Amp\File\Filesystem;
use function Amp\File\filesystem;

class App {

    public Filesystem $filesystem;

    public array $torrents = array();
    public array $magnets = array();

    public \tuefekci\helpers\Logger $logger;
    public \tuefekci\helpers\Store $store;

    public function __construct() {
        $app = $this;

        $this->filesystem = filesystem();

        // =================================================================
        // Init Environment & Config Variables

        // If store exists load it.
        if(\tuefekci\helpers\Files::exists(__CONF__.'/store.blk')) {
            \tuefekci\helpers\Store::load(__CONF__.'/store.blk');
        }

        // If running docker load env.
        if(\tuefekci\helpers\System::isDocker()) {
        
            // convert environment variables to constants
            foreach ($_ENV as $key => $value) {
                if(!\tuefekci\helpers\Store::has($key)) {
                    \tuefekci\helpers\Store::set($key, $value);
                }
            }

        }

        // If config.ini exists load it.
        if(\tuefekci\helpers\Files::exists(__CONF__.'/config.ini')) {
            $config = \Noodlehaus\Config::load(__CONF__.'/config.ini')->all();

            if($config) {
                foreach($config as $key => $value) {
    
                    $key = strtoupper($key);
    
                    if(is_array($value)) {
    
                        foreach($value as $subKey => $subValue) {
                            $subKey = strtoupper($subKey);
    
                            if(!is_array($subValue)) {
                                \tuefekci\helpers\Store::set($key."_".$subKey, $subValue);
                            }
                        }
    
                    } else {
    
                        if(!\tuefekci\helpers\Store::has($key)) {
                            \tuefekci\helpers\Store::set($key, $value);
                        }
    
                    }
    
                }
            }

        }

        // =================================================================
        // Check every possible config option and set it to the store.



        var_dump(\tuefekci\helpers\Store::all());

        die();

        // =================================================================
        // Create needed folders for the Application
        $this->createFolders();

        // =================================================================
        // Init Utilities
        
        $this->logger = new \tuefekci\helpers\Logger();

    }

    public function run() {

        $app = $this;

        \tuefekci\helpers\Cli::banner("blkhole", "https://github.com/tuefekci/blkhole");

        // =================================================================
        // init Providers
        $provider = new Provider\Alldebrid($this);
        $this->provider = $provider;

        // =================================================================
        // init Download Clients
        $downloadClient = new DownloadClient\Controller($this);
        $this->downloadClient = $downloadClient;

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

    // Create needed folders for the Application
    public function createFolders() {
        $app = $this;

        $this->createFolder(__CONF__);
        $this->createFolder(__LOGS__);
        $this->createFolder(__CACHE__);
        $this->createFolder(__TMP__);
        $this->createFolder(__BLACKHOLE__);
        $this->createFolder(__DOWNLOADS__);
        $this->createFolder(__BLACKHOLE__."/webinterface");
        $this->createFolder(__DOWNLOADS__."/webinterface");
    }

    public function createFolder($path) {
        $app = $this;
        $path = realpath($path);

        $app->filesystem->exists($path)->onResolve(function ($error, $exists) use ($app, $path) {
            if ($error) {
                $app->error("createFolder->checkFolder", $error->getMessage());


            } else {

                if($exists) {

                } else {
                    $app->filesystem->createDirectoryRecursively($path)->onResolve(function ($error, $value) use ($app, $path) {
                        if ($error) {
                            $app->error("createFolder->createFolder", $error->getMessage());
                        } else {

                        }
                    });
                }

            }

        });
    }

    // Check Downloads for completed downloads and then clean up!
    private function checkDownload() {

        foreach($this->magnets as $path => $magnet) {
            if(!empty($magnet['downloads'])) {

                $check = array();

                foreach($magnet['downloads'] as $dlId) {
                    $download = $this->downloadClient->get($dlId);

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
                        $download = $this->downloadClient->remove($dlId);
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
                                        $this->downloadClient->add($dlId, __DOWNLOADS__."/".basename($magnet['dirname'])."/".$magnet['filename']."/".$data->filename, $data->link, $link->size);
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
                $this->error("torrents listFiles", $error->getMessage());
            } else {

                foreach($files as $file) {
                    if($this->filesystem->isDirectory(__BLACKHOLE__.DIRECTORY_SEPARATOR.$file)) {

                        $path = __BLACKHOLE__.DIRECTORY_SEPARATOR.$file;

                        $this->filesystem->listFiles(__BLACKHOLE__.DIRECTORY_SEPARATOR.$file)->onResolve(function ($error, $files) use ($path) {
                            if ($error) {
                                $this->error("torrents listFiles", $error->getMessage());
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