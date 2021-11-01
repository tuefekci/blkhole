<?php

namespace tuefekci\blk\DownloadClient;

class Download {

    public $ready = false;
    public $done = false;
    public $paused = false;
    public $error = false;
    public $id = false;
    public $path = false;
    public $dir = false;
    public $url = false;
    public $size = 0;
    public $providerSize = 0;
    public $currentSize = 0;
    public $percent = 0;
    public $speed = 0;
    public $speedLimit = 0;
    public $time;
    public $secData = 0;
    public $secDataHistory = [];

    public $connections = 1;

    // Chunks
    public $chunkSize = 0;
    public $chunkCount = 0;
    public $chunkCurrent = 0;
    public $chunkDone = 0;
    public $chunkError = 0;



    private $app;
    private \Amp\Http\Client\HttpClient $client;

    public function __construct($manager, $id, $path, $url, $providerSize=false) {

        $_this = $this;
        $this->client = \Amp\Http\Client\HttpClientBuilder::buildDefault();

        $this->app = $manager->app;
        $this->manager = $manager;

        $app = $manager->app;
        $app->logger->log("INFO", "[DownloadClient] Download added ".$url);

        $this->id = $id;
        $this->path = $path;
        $this->dir = dirname($path);
        $this->url = $url;

        if($providerSize) {
            $this->providerSize = $providerSize;
        }

        if(\tuefekci\helpers\Store::has("DOWNLOAD_CONNECTIONS")) {
            $this->connections = \tuefekci\helpers\Store::get("DOWNLOAD_CONNECTIONS");
        } else {
            $this->app->logger->log("ERROR", "[DownloadClient] No DOWNLOAD_CONNECTIONS found, please set it in the settings.");
        }

        $app->createFolder($this->dir)->onResolve(function ($error, $exists) use ($app, $_this) {
            if($error) {
                $app->logger->log("ERROR", "[DownloadClient] Error creating folder ".$this->dir);
                $this->error[] = "Error creating folder";
                return;
            }

            $this->getHeaders()->onResolve(function ($error, $headers) use ($app, $_this) {
                if($error) {            
                    $app->logger->log("ERROR", "[DownloadClient] Error getting headers ".$this->url);
                    $this->error[] = "Error getting headers";
                    return;
                }

                // We have all the needed data lets start.
                $this->initChunks();
                $this->ready = true;
                $this->download();
            });
        });

        
        \Amp\Loop::repeat($msInterval = 10000, function ($watcherId) use ($app) {

            if(!$this->ready) {
                $app->logger->log("DEBUG", "[DownloadClient] not yet ready ".$this->path);
                return;
            }

            $app->logger->log("DEBUG", "[DownloadClient] ".$this->percent."% / speed: ".$this->speed." / tta: ".$this->time ." / ". "download->".$this->path);

            if($this->done) {
                \Amp\Loop::cancel($watcherId);
            }
            
        });

        \Amp\Loop::repeat($msInterval = 1000, function ($watcherId) use ($app) {

            if(!$this->ready) {
                $app->logger->log("DEBUG", "[DownloadClient] not yet ready ".$this->path);
                return;
            }

            $this->speedLimit();
 
            $this->secDataHistory[] = $this->secData;
            $this->secDataHistory = array_slice($this->secDataHistory, -10, 10);
            $this->secData = 0;

            $this->speed = array_sum($this->secDataHistory)/count($this->secDataHistory);

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

    private function initChunks() {
        //============================================================
        // Chunk Settings
        $this->chunkSize = (int) 32 * 1024 * 1024; // 32MB 
        $this->chunkCount = (int) ceil($this->size / $this->chunkSize);
        $this->chunkCurrent = 0;
        $this->chunkDone = 0;
        $this->chunkError = 0;
        //============================================================
    }

    private function speedLimit() {

        if(\tuefekci\helpers\Store::has("DOWNLOAD_BANDWIDTH")) {
            $bandwidth = \tuefekci\helpers\Store::get("DOWNLOAD_BANDWIDTH");
        } else {
            $this->app->logger->log("ERROR", "[DownloadClient] No DOWNLOAD_PARALLEL found, please set it in the settings.");
        }


        if(count($this->manager->downloads)) {
            $this->speedLimit = (int) ((int)$bandwidth*1000)/count($this->manager->downloads);
        } else {
            $this->speedLimit = ((int)$bandwidth*1000);
        }
    }

    /**
     * Get Headers
     * 
     * gets the headers for the requested download so we can determine how many chunks etc.
     * 
     */
    public function getHeaders() {

        $deferred = new \Amp\Deferred;

        $this->app->logger->log("DEBUG", "[DownloadClient] getHeaders ".$this->path);

        $app = $this->app;

        $request = new \Amp\Http\Client\Request($this->url);
        $request->setMethod("HEAD");

        $this->client->request($request)->onResolve(function ($error, $response) use ($app, $deferred) {

            if($error) {
                $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
                $this->error[] = "getHeaders failed";
                $deferred->fail($error);
            } else {

                $headers = $response->getHeaders();

                if(empty($headers['content-length'][0])) {
                    $deferred->fail(new \Throwable("Download getHeaders content-lenth empty."));
                } else {
                    $this->size = (int) $headers['content-length'][0];
                    $deferred->resolve($this->size);
                }

            }


        });

        return $deferred->promise();


    }
    



    /**
     * Start Download
     * 
     * @return void
     */
    private function download() {


        $app = $this->app;
        $_this = $this;

        $chunks = array();
        for ($i=0; $i < $this->chunkCount+20; $i++) { 

            $start = $i * $this->chunkSize;
            $end = ($i+1)*$this->chunkSize;

            if($i == $this->chunkCount-1) {
                $end = $this->size;
            }

            $chunks[] = (object) ['id' => ($i+1), 'start'=>$start , 'end'=>$end, $path = $this->path."/".$i];

        }

        $chunkedChunks = array_chunk($chunks, $this->connections);


        var_dump(\tuefekci\helpers\Strings::filesizeFormatted($this->size));
        var_dump(\tuefekci\helpers\Strings::filesizeFormatted($this->chunkSize));
        var_dump($this->chunkCount);
        var_dump(count($chunks));
        var_dump(count($chunkedChunks));


        foreach($chunkedChunks as $key => $chunkedChunk) {

            $urls = [
                'https://secure.php.net',
                'https://amphp.org',
                'https://github.com',			
            ];
    
            $promises = [];
            foreach ($urls as $url) {
                $promises[$url] = \Amp\call(function() use ($url) {
                    $deferred = new \Amp\Deferred();
    
                    \Amp\Loop::delay(3 * 1000, function () use ($url, $deferred) {
                        $deferred->resolve($url);
                    });
    
                    return $deferred->promise();
                });
            }
    
            $responses = yield \Amp\Promise\all($promises);
    
            foreach ($responses as $url => $response) {
                \printf("Read %d bytes from %s\n", \strlen($response), $url);
            }

            /*
            $promises = array();

            foreach($chunkedChunk as $chunk) {
                $promises[] = $this->testChunk($chunk);
            }


            $app->logger->log("DEBUG", $this->path.": chunkedChunk ".$key);
            $results = yield \Amp\Promise\all($promises);
            $app->logger->log("DEBUG", $this->path.": chunkedChunk ".$key, $results);
            */

        }


        /*
        $tmpSize = 0;
        foreach($chunks as $chunk) {
            $tmpSize += $chunk->end - $chunk->start;
        }

        var_dump(\tuefekci\helpers\Strings::filesizeFormatted($this->size));
        var_dump(\tuefekci\helpers\Strings::filesizeFormatted($tmpSize));
        var_dump(\tuefekci\helpers\Strings::filesizeFormatted($this->chunkSize));
        var_dump($this->chunkCount);
        var_dump(count($chunks));
        var_dump(count($chunkedChunks));
        var_dump($chunkedChunks[0]);
        */

    }

    private function downloadChunk() {

        $app = $this->app;

        $app->filesystem->touch($this->path)->onResolve(function ($error, $value) use ($app) {
            if ($error) {
                $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
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
                            $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
    
                            $this->manager->remove($this->id);
                            $this->manager->add($this->id, $this->path, $this->url, $this->size);
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
    
                            $app->logger->log("INFO", "[DownloadClient] Download completed ".$this->path);
    
                        }
    
    
                    });

                } catch (\Throwable|\Amp\Http\Client\TimeoutException $error) {

                    $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
    
                    $this->manager->remove($this->id);
                    $this->manager->add($this->id, $this->path, $this->url, $this->size);
                }


            }
        });

    }

    /**
     * Pause Download
     * 
     * @return void
     */
    public function pause() {
        $this->paused = true;
    } 

    /**
     * Resume Download
     * 
     * @return void
     */ 
    public function unpause() {
        $this->paused = false;
    } 


}