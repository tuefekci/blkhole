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
    public $lastChunkSize = 0;
    public $chunkCount = 0;
    public $chunkCurrent = 0;
    public $chunkDone = 0;

    private $chunks = [];
    private $chunkData = [];
    private $chunkErrors = [];
    private $chunksSize = [];

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

            $this->currentSize = 0;
            foreach($this->chunksSize as $id => $chunkSize) {
                $this->currentSize += $chunkSize;
            }

            $this->percent = 0;
            if(!empty($this->currentSize) && !empty($this->size)) {
                $this->percent = round(($this->currentSize / $this->size) * 100, 2);
            }

            if(!empty($this->size) && !empty($this->currentSize) && !empty($this->speed)) {
                $this->time = ($this->size-$this->currentSize)/$this->speed;
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
        $this->chunkSize = (int) 3.2 * 1024 * 1024; // 32MB 
        $this->chunkCount = (int) ceil($this->size / $this->chunkSize);
        $this->chunkCurrent = 0;
        $this->chunkDone = 0;
        $this->chunkError = 0;
        //============================================================

        $this->chunks = array();
        for ($i=0; $i < $this->chunkCount; $i++) { 

            $start = $i * $this->chunkSize;
            $end = (($i+1)*$this->chunkSize)-1;

            if($i == $this->chunkCount-1) {
                $end = $this->size;

                $this->lastChunkSize = $end-$start;
            }

            $this->chunks[($i+1)] = ['id' => ($i+1), 'start'=>$start , 'end'=>$end, 'path' => $this->path."/".$i.".chunk"];

        }

    }

    private function speedLimit() {

        if(\tuefekci\helpers\Store::has("DOWNLOAD_BANDWIDTH")) {
            $bandwidth = \tuefekci\helpers\Store::get("DOWNLOAD_BANDWIDTH");
        } else {
            $this->app->logger->log("ERROR", "[DownloadClient] No DOWNLOAD_BANDWIDTH found, please set it in the settings.");
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

        $chunkedChunks = array_chunk($this->chunks, $this->connections);

        \Amp\asyncCall(function() use ($chunkedChunks, $app, $_this) {
            foreach($chunkedChunks as $key => $chunkedChunk) {

                $promises = [];
                foreach ($chunkedChunk as $chunk) {
                    $promises[$chunk['id']] = \Amp\call(function() use ($chunk) {
                        $deferred = new \Amp\Deferred();

                        $this->downloadChunk($chunk['id'], $chunk['start'], $chunk['end'], $chunk['path'])->onResolve(function ($error, $response) use ($deferred) {
                            if($error) {
                                $deferred->fail($error);
                            } else {
                                $deferred->resolve($response);
                            }
                        });

                        return $deferred->promise();
                    });
                }

            
                list($arrayOfErrors, $arrayOfValues) = yield \Amp\Promise\any($promises);

                foreach($arrayOfErrors as $id => $error) {
                    $this->app->logger->log("WARNING", "[DownloadClient] chunk ".$id." failed ", ['exception'=>$error]);

                    $this->chunkError++;
                    $this->chunkErrors[$id] = $this->chunks[$id];
                    unset($this->chunks[$id]);
                }

                foreach ($arrayOfValues as $id => $value) {
                    $this->app->logger->log("DEBUG", "[DownloadClient] chunk ".$id." done");

                    $this->chunkDone++;
                    $this->chunkData[$id] = $this->chunks[$id];
                    $this->chunkData[$id]['path'] = $value;
                    unset($this->chunks[$id]);
                }

            }

            if(!empty($_this->chunks) OR !empty($_this->chunkErrors)) {

                if(!empty($_this->chunkErrors)) {
                    $this->app->logger->log("WARNING", "[DownloadClient] chunkErrors found, retrying.");

                    foreach($_this->chunkErrors as $chunk) {
                        $this->chunkError--;
                        $_this->chunks[$chunk['id']] = $chunk;
                    }
                }

                // Check if all chunks are done
                $this->app->logger->log("DEBUG", "[DownloadClient] chunks not done yet");
                $this->download();

            } else {

                // All chunks are done
                $this->app->logger->log("DEBUG", "[DownloadClient] chunks done");
                $this->finalizeDownload()->onResolve(function ($error, $value) use ($app) {
                    if($error) {
                        $app->logger->log("ERROR", "[DownloadClient] finalizeDownload failed ".$error->getMessage(), ['exception'=>$error]);
                        $this->download();
                    } else {
                        $this->done = true;
                        $app->logger->log("DEBUG", "[DownloadClient] download done: ".$value);
                    }
                });
                

            }

        });

    }


	private function downloadChunk($id, $start, $end, $path) {

        $path = str_replace(__DOWNLOADS__, __TMP__, $path);
        $dir = dirname($path);

        $deferred = new \Amp\Deferred();

        $_this = $this;

        $app = $this->app;

        $app->createFolder($dir)->onResolve(function ($error, $exists) use ($app, $_this, $deferred, $start, $end, $path, $id) {
            if($error) {
                $app->logger->log("ERROR", "[DownloadClient] Error creating folder: ".$this->dir. " ".$error->getMessage(), ['exception'=>$error]);
                $this->error[] = "Error creating folder";
                $deferred->fail($error);
                return;
            } else {

                $app->filesystem->touch($path)->onResolve(function ($error, $value) use ($app, $deferred, $start, $end, $path, $id) {
                    if ($error) {
                        $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
                        $deferred->fail($error);
                    } else {
        
                        $file = yield \Amp\File\openFile($path, "w");
        
                        $request = new \Amp\Http\Client\Request($this->url);

                        $request->setBodySizeLimit((int)$this->chunkSize + ($this->chunkSize*0.1));
                        $request->setTransferTimeout((int)(60 * 60 * 1000));

                        $request->setHeader("Range", "bytes=".$start."-".$end);
                        //$request->setTlsHandshakeTimeout(1);
        
                        $this->client->request($request)->onResolve(function ($error, $response) use ($app, $file, $deferred, $start, $end, $path, $id) {
        
                            if ($error) {
                                $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
                                $deferred->fail($error);
                                yield $file->close();
                                return;
                            } else {
        
                                $headers = $response->getHeaders();
        
                                $size = $headers['content-length'][0];
                                $body = $response->getBody();
                                $this->chunksSize[$id] = 0;

                                while (null !== $chunk = yield $body->read()) {
                                    yield $file->write($chunk);
        
                                    $this->chunksSize[$id] += strlen($chunk);
                                    $this->secData += strlen($chunk);
        
                                    // speed reduction pause
                                    if($this->secData >= $this->speedLimit) {
                                        yield \Amp\delay(1000);
                                    }
                                }
        
                                yield $file->close();

                                if($this->chunksSize[$id] != $size) {
                                    $deferred->fail(new \Exception("Download chunk failed. Size mismatch."));
                                } else {
                                    $deferred->resolve($path);
                                    $app->logger->log("DEBUG", "[DownloadClient] Chunk completed ".$path);
                                }
        
                            }
        
        
                        });

                    }
                });

            }

        });

        return $deferred->promise();

    }

    private function finalizeDownload() {
           
        $_this = $this;
        $app = $this->app;

        $deferred = new \Amp\Deferred();

        \Amp\asyncCall(function() use ($app, $_this, $deferred) {

            yield new \Amp\Delayed(5000);

            try {
                yield $app->createFolder($this->dir);
            } catch (\Throwable $error) {
                $app->logger->log("ERROR", "[DownloadClient] finalizeDownload createFolder ". $this->dir . ": " . $error->getMessage(), ['exception'=>$error]);
                $deferred->fail($error);
                return;
            }


            $this->app->logger->log("DEBUG", "[DownloadClient] Finalizing download");

            $app->filesystem->touch($this->path)->onResolve(function ($error, $value) use ($app, $deferred) {
                if ($error) {
                    $app->logger->log("ERROR", "[DownloadClient] finalizeDownload touch ". $this->path . ": " . $error->getMessage(), ['exception'=>$error]);
                    $deferred->fail($error);
                    return;
                } else {

                    try {
                        $file = yield \Amp\File\openFile($this->path, "w");
                    } catch (\Throwable $error) {
                        $app->logger->log("ERROR", "[DownloadClient] finalizeDownload write ". $this->path . ": " . $error->getMessage(), ['exception'=>$error]);
                        $deferred->fail($error);   
                        yield $file->close();
                        return;
                    }

                    foreach($this->chunkData as $chunk) {

                        try {
                            yield $file->write(yield $app->filesystem->read($chunk['path']));
                        } catch (\Throwable $error) {
                            $app->logger->log("ERROR", "[DownloadClient] finalizeDownload write ". $this->path . ": " . $error->getMessage(), ['exception'=>$error]);
                            $deferred->fail($error);  
                            yield $file->close(); 
                            return;
                        }

                    }

                    yield $file->close();


                    try {

                        if(yield $app->filesystem->exists($this->path)) {

                            $fileSize = yield $app->filesystem->getSize($this->path);

                            if($fileSize != $this->size) {

                                yield $app->filesystem->deleteFile($this->path);
                                $this->done = false;
                                $deferred->fail(new \Exception("Download finalize failed. Size mismatch."));

                            } else {

                                // Remove chunks
                                foreach($this->chunkData as $chunk) {

                                    try {
                                        $app->filesystem->deleteFile($chunk['path']);
                                    } catch (\Throwable $error) {
                                        $app->logger->log("ERROR", "[DownloadClient] finalizeDownload removeChunk ". $chunk['path'] . ": " . $error->getMessage(), ['exception'=>$error]);
                                    }
            
                                }

                                $app->logger->log("DEBUG", "[DownloadClient] Download finalize completed ".$this->path);
                                $deferred->resolve($this->path);
                            }
                        } else {
                            $deferred->fail(new \Exception("Download finalize failed. File not found."));
                        }

                    } catch (\Throwable $error) {
                        $app->logger->log("ERROR", "[DownloadClient] finalizeDownload end try ". $this->path . ": " . $error->getMessage(), ['exception'=>$error]);
                    }

                }

            });
        });

        return $deferred->promise();

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