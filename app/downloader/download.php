<?php

namespace GT\BLK\Downloader;

class Download {

    public $done = false;
    public $error = false;
    public $id = false;
    public $path = false;
    public $dir = false;
    public $url = false;
    public $size = 0;
    public $sizeText;
    public $currentSize = 0;
    public $percent = 0;
    public $speed = 0;
    public $speedText = "";
    public $speedLimit = 0;
    public $time;
    public $timeText;
    public $secData = 0;
    public $secDataHistory = [];

    private $app;

    public function __construct($downloader, $id, $path, $url, $size=false) {

        $this->app = $downloader->app;
        $this->downloader = $downloader;

        $app = $downloader->app;
        $app->info("added ".$url, "Download");

        $this->id = $id;
        $this->path = $path;
        $this->dir = dirname($path);
        $this->url = $url;

        if($size) {
            $this->size = $size;
        }

        $this->speedLimit();

        $app->filesystem->exists($this->dir)->onResolve(function ($error, $exists) use ($app) {
            if ($error) {
                $app->error("download->createFolder", $error->getMessage());
            } else {

                if($exists) {
                    $this->download();
                } else {
                    $app->filesystem->createDirectoryRecursively($this->dir)->onResolve(function ($error, $value) use ($app) {
                        if ($error) {
                            $app->error("download->createFolder", $error->getMessage());
                        } else {
                            $this->download();
                        }
                    });
                }

            }

        });

        
        \Amp\Loop::repeat($msInterval = 60000, function ($watcherId) use ($app) {

            $this->app->info($this->percent."% / speed: ".$this->speedText." / tta: ".$this->timeText , "download->".$this->path);

            if($this->done) {
                \Amp\Loop::cancel($watcherId);
            }
            
        });

        \Amp\Loop::repeat($msInterval = 1000, function ($watcherId) use ($app) {

            $this->speedLimit();
 
            $this->secDataHistory[] = $this->secData;
            $this->secDataHistory = array_slice($this->secDataHistory, -10, 10);
            $this->secData = 0;

            $this->speed = array_sum($this->secDataHistory)/count($this->secDataHistory);
            $this->speedText = $app->filesize_formatted($this->speed);

            $this->sizeText = $app->filesize_formatted($this->size);

            if(!empty($this->size) && !empty($this->currentSize) && !empty($this->speed)) {
                $this->time = ($this->size-$this->currentSize)/$this->speed;
                $this->timeText = gmdate('H:i:s', (int) round($this->time));
            }

            if($this->done) {
                \Amp\Loop::cancel($watcherId);
            }
            
        });

        return $this;
    }

    private function speedLimit() {
        if(count($this->downloader->downloads)) {
            $this->speedLimit = (int) ((int)$this->downloader->app->config['downloader']['bandwith']*1000)/count($this->downloader->downloads);
        } else {
            $this->speedLimit = ((int)$this->downloader->app->config['downloader']['bandwith']*1000);
        }
    }
 
    private function download() {


        $app = $this->app;

        $app->filesystem->touch($this->path)->onResolve(function ($error, $value) use ($app) {
            if ($error) {
                $app->error("download->createFile", $error->getMessage());
            } else {

                $file = yield \Amp\File\openFile($this->path, "w");


                try {

                    $client = \Amp\Http\Client\HttpClientBuilder::buildDefault();

                    $request = new \Amp\Http\Client\Request($this->url);
                    $request->setBodySizeLimit((int)(99999 * 1024 * 1024));
                    $request->setTransferTimeout((int)(12 * 60 * 60 * 1000));
                    //$request->setTlsHandshakeTimeout(1);
    
                    $client->request($request)->onResolve(function ($error, $response) use ($app, $file) {
    
                        if ($error) {
                            $app->error("download->request", $error->getMessage());
    
                            $this->downloader->remove($this->id);
                            $this->downloader->add($this->id, $this->path, $this->url, $this->size);
                        } else {
    
                            $headers = $response->getHeaders();
    
                            if(!empty($headers['content-length'][0])) {
                                $this->size = $headers['content-length'][0];
                            }
    
                            $body = $response->getBody();
    
                            while (null !== $chunk = yield $body->read()) {
                                yield $file->write($chunk);
    
                                $this->currentSize += strlen($chunk);
                                $this->secData += strlen($chunk);
    
                                $this->percent = number_format($this->currentSize / $this->size * 100, 0);
    
                                // speed reduction pause
                                if($this->secData >= $this->speedLimit) {
                                    yield \Amp\delay(1000);
                                }
    
    
                            }
    
                            yield $file->close();
                            $this->done = true;
    
                            $app->info("completed ".$this->url, "Download");
    
                        }
    
    
                    });

                } catch (\Throwable|\Amp\Http\Client\TimeoutException $th) {
                    $app->error("download->request", $th->getMessage());
    
                    $this->downloader->remove($this->id);
                    $this->downloader->add($this->id, $this->path, $this->url, $this->size);
                }


            }
        });

    }

}