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

        // =================================================================
        // Check every possible config option and set it to the store.

        // If store exists load it.
        if(\tuefekci\helpers\Files::exists(__CONF__.'/store.blk')) {
            \tuefekci\helpers\Store::load(__CONF__.'/store.blk');
        }

        $dotenv = \Dotenv\Dotenv::createImmutable(__ROOT__);
        $dotenv->safeLoad();

        // convert environment variables to constants
        foreach ($_ENV as $key => $value) {
            if(!\tuefekci\helpers\Store::has($key)) {
                \tuefekci\helpers\Store::set($key, $value);
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

        //\tuefekci\helpers\Store::save(__CONF__.'/store.blk');

        // =================================================================

        // =================================================================
        // Init Utilities
        $this->filesystem = filesystem();
        $this->logger = new \tuefekci\helpers\Logger();

        // =================================================================
        // Create needed folders for the Application
        $this->createFolders();



        return $this;


    }

    public function run() {

        $app = $this;

        // =================================================================
        // init Providers
        $provider = new Provider\Alldebrid($this);
        $this->provider = $provider;

        // =================================================================
        // init Download Clients
        $downloadClient = new DownloadClient\Manager($this);
        $this->downloadClient = $downloadClient;

        // =================================================================
        // loops
        Loop::repeat($msInterval = 1000, function () {
            //var_dump($this->torrents);
            //var_dump($this->magnets);
        });

        \Amp\Loop::repeat($msInterval = 5000, function () {
            $this->checkFiles();
        });

        \Amp\Loop::repeat($msInterval = 5000, function () {
            $this->handleMagnets();
            $this->checkProvider();
            $this->checkDownload();
        });

        // Clean Up Loop
        \Amp\Loop::repeat($msInterval = 30000, function () {

            if($this->downloadClient->isIdle()) {
                $this->logger->info("DownloadClient is idle, lets clean up.");
            }

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


    public function cleanUp() {

        $app = $this;

        $this->logger->log("INFO", "Cleaning up...");

        $this->logger->info("INFO", "Deleting all files in ".__TMP__);
        $this->filesystem->listFiles(__TMP__)->onResolve(function ($error, $files) use ($app) {
            if ($error) {
                $this->logger->log("ERROR", "cleanUp listFiles", ['exception' => $error]);
            } else {

                foreach ($files as $file) {

                    try {
                        if(yield $this->filesystem->isDirectory($file)) {
                            $this->logger->info("INFO", "Deleting directory ".$file);
                            yield $this->filesystem->deleteDirectory($file);
                        } elseif(yield $this->filesystem->isFile($file)) {
                            $this->logger->info("INFO", "Deleting file ".$file);
                            yield $this->filesystem->deleteFile($file);
                        } else {
                            $app->logger->log("WARNING", "[CleanUp] Path is not Dir or File: ".$file);
                        }
                    } catch (\Throwable $error) {
                        $app->logger->log("ERROR", "cleanUp->tmp (".$file.")", ['exception' => $error]);
                    }

                }

            }

        });

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

        $deferred = new \Amp\Deferred;
        $app = $this;
        $path = $path;

        try {
            $app->filesystem->exists($path)->onResolve(function ($error, $exists) use ($app, $path, $deferred) {
                if ($error) {
                    $app->logger->log("ERROR", "createFolder->checkFolder (".$path.")", ['exception' => $error]);
                    $deferred->fail($error);
                } else {
    
                    if($exists) {

                        $app->filesystem->changePermissions($path, 0777)->onResolve(function ($error, $value) use ($app, $path, $deferred) {
                            if ($error) {
                                $app->logger->log("ERROR", "createFolder->changePermissions (".$path.")", ['exception' => $error]);
                                $deferred->fail($error);
                            } else {
                                $deferred->resolve("exists");
                            }
                        });

                    } else {

                        $app->logger->log("DEBUG", "createFolder (".$path.")");

                        $app->filesystem->createDirectoryRecursively($path, 0777)->onResolve(function ($error, $value) use ($app, $path, $deferred) {
                            if ($error) {
                                $app->logger->log("ERROR", "createFolder->createFolder (".$path.")", ['exception' => $error]);
                                $deferred->fail($error);
                            } else {
                                $deferred->resolve("created");
                            }
                        });
                    }
    
                }
    
            });
        } catch (\Throwable $error) {
            //throw $th;
            $app->logger->log("ERROR", "createFolder->createFolderCatch (".$path.")", ['exception' => $error]);
        }

        return $deferred->promise();

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
                        $this->downloadClient->remove($dlId);
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
                                        $_this->logger->log("ERROR", "handleMagnets->addMagnet", ['exception' => $error]);
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

        \Amp\asyncCall(function() use ($_this) {

            foreach($this->magnets as $path => $magnet) {
                if(empty($magnet['provider']) && yield $_this->filesystem->exists($path)) {

                    $this->filesystem->read($path)->onResolve(function ($error, $magnet) use ($_this, $path) {
                        if ($error) {
                            $_this->logger->log("ERROR", "handleMagnets->read", ['exception' => $error]);
                        } else {

                            $this->provider->addMagnet($magnet)->onResolve(function ($error, $data) use ($_this, $path) {
                                if ($error) {
                                    $_this->logger->log("ERROR", "handleMagnets->addMagnet", ['exception' => $error]);
                                } else {
                                    $_this->magnets[$path]['provider'] = $data;
                                }
                            });

                        }
                    });

                }
            }

        });


    }


    // check blackhole for magnets and torrents
    private function checkFiles() {
        //$this->info(__BLACKHOLE__, "checkFiles->blackhole");

        $this->filesystem->listFiles(__BLACKHOLE__)->onResolve(function ($error, $files) {
            if ($error) {
                $this->logger->log("ERROR", "torrents listFiles", ['exception' => $error]);
            } else {

                foreach($files as $file) {
                    if($this->filesystem->isDirectory(__BLACKHOLE__.DIRECTORY_SEPARATOR.$file)) {

                        $path = __BLACKHOLE__.DIRECTORY_SEPARATOR.$file;

                        $this->filesystem->listFiles(__BLACKHOLE__.DIRECTORY_SEPARATOR.$file)->onResolve(function ($error, $files) use ($path) {
                            if ($error) {
                                $this->logger->log("ERROR", "torrents listFiles", ['exception' => $error]);
                            } else {

                                foreach($files as $file) {

                                    $filePath = $path.DIRECTORY_SEPARATOR.$file;
                                    $pathInfo = pathinfo($filePath);

                                    if($pathInfo['extension'] == "magnet") {

                                        if(count($this->magnets) > 20) {
                                            continue;
                                        }

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

    public function addMagnet($magnetUrl) {
        if (\strpos($magnetUrl, 'magnet:') !== false) {

            $magnetRaw = $magnetUrl;

            if(preg_match('~%[0-9A-F]{2}~i', $magnetRaw)) {
                $magnetRaw = urldecode($magnetRaw);
            }

            preg_match('#magnet:\?xt=urn:btih:(?<hash>.*?)&dn=(?<filename>.*?)&tr=(?<trackers>.*?)$#', $magnetRaw, $magnet);

            if(!empty($magnet['filename']) && is_string($magnet['filename'])) {

                $app = $this;

                $this->filesystem->exists(__BLACKHOLE__."/webinterface")->onResolve(function ($error, $exists) use ($app, $magnet, $magnetRaw) {
                    if ($error) {
                        $app->logger->log("ERROR", "addMagnet->exists ".$error->getMessage(), ['exception' => $error]);
                    } else {
                        if($exists) {
                            $app->filesystem->write(__BLACKHOLE__."/webinterface/".$magnet['filename'].".magnet", $magnetRaw);
                        }
                    }
        
                });

            } else {
                return false;
            }

        } else {
            return false;
        }
    }

}