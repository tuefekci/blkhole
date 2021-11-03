<?php

namespace tuefekci\blk\DownloadClient;

class Download {

    public $ready = false;
    public $done = false;
    public $finished = false;
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

        $this->id = $id;
        $this->path = $path;
        $this->dir = dirname($path);
        $this->url = $url;

        if($providerSize) {
            $this->providerSize = $providerSize;
        }

        // ================================================================


        $app->logger->log("INFO", "[DownloadClient] Download added ".$id." <-> ".$url);


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

                try {
                    if(yield $app->filesystem->exists($this->path)) {
                        $app->logger->log("INFO", "[DownloadClient] File already exists ".$this->path);
                        $size = yield $app->filesystem->getSize($this->path);
    
                        if($size == $this->size) {
                            $app->logger->log("NOTICE", "[DownloadClient] File already exists and is the same size ".$this->path);
                            $this->ready = true;
                            $this->done = true;
                            return;
                        } else {
                            $app->logger->log("WARNING", "[DownloadClient] File already exists but is not the same size ".$this->path);
                        }
    
                    }
                } catch (\Throwable $error) {
                    $app->logger->log("ERROR", "[DownloadClient] Error File exists check: ".$this->dir. " ".$error->getMessage(), ['exception'=>$error]);
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

    public function isDownloading() {
        return !$this->finished;
    }

    private function initChunks() {
        //============================================================
        // Chunk Settings
        $this->chunkSize = (int) 32 * 1024 * 1024; // 32MB 
        $this->chunkCount = (int) ceil($this->size / $this->chunkSize);
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

                    $this->chunkErrors[$id] = $this->chunks[$id];
                    unset($this->chunks[$id]);
                }

                foreach ($arrayOfValues as $id => $value) {
                    //$this->app->logger->log("DEBUG", "[DownloadClient] chunk ".$id." done");

                    $this->chunkData[$id] = $this->chunks[$id];
                    $this->chunkData[$id]['path'] = $value;
                    unset($this->chunks[$id]);
                }

            }

            if(!empty($_this->chunks) OR !empty($_this->chunkErrors)) {

                if(!empty($_this->chunkErrors)) {
                    $this->app->logger->log("WARNING", "[DownloadClient] chunkErrors found, retrying.");

                    foreach($_this->chunkErrors as $chunk) {
                        $_this->chunks[$chunk['id']] = $chunk;
                        unset($_this->chunkErrors[$chunk['id']]);
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

        \Amp\asyncCall(function() use ($app, $_this, $deferred, $start, $end, $path, $id, $dir) {


            // =============================================================
            // Check if chunk is already existing and has the correct size.
            try {
                if(yield $app->filesystem->exists($path)) {

                    $size = yield $app->filesystem->getSize($path);

                    if((int) $size == (int) $this->chunkSize) {
                        $app->logger->log("DEBUG", "[DownloadClient] Chunk already exists and is the same size ".$this->id."->".$id);
                        $this->chunksSize[$id] = $size;
                        $deferred->resolve($path);
                        return;
                    } else {
                        $app->logger->log("WARNING", "[DownloadClient] Chunk already exists but is not the same size (".$size."/".$this->chunkSize.") ".$this->id."->".$id);
                    }

                }
            } catch (\Throwable $error) {
                $app->logger->log("ERROR", "[DownloadClient] Error Chunk exists check: ".$path. " ".$error->getMessage(), ['exception'=>$error]);
            }
            // =============================================================


            $app->createFolder($dir)->onResolve(function ($error, $exists) use ($app, $_this, $deferred, $start, $end, $path, $id) {
                if($error) {
                    $app->logger->log("ERROR", "[DownloadClient] Error creating folder: ".$this->dir. " ".$error->getMessage(), ['exception'=>$error]);
                    $this->error[] = "Error creating folder";
                    $deferred->fail($error);
                    return;
                } else {

                    // =============================================================
                    // Download Chunk
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
            
                            try {

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
                                        }
                
                                    }
                
                
                                });

                            } catch (\Throwable $error) {
                                $app->logger->log("ERROR", $error->getMessage(), ['exception'=>$error]);
                                $deferred->fail($error);
                                yield $file->close();
                                return;
                            }


                        }
                    });
                    // =============================================================

                }

            });


        });

        return $deferred->promise();

    }

    private function finalizeDownload() {
           
        $_this = $this;
        $app = $this->app;

        $deferred = new \Amp\Deferred();

        \Amp\asyncCall(function() use ($app, $_this, $deferred) {

            $tmpFileName = \tuefekci\helpers\Strings::random(16);
            $tmpFilePath = $this->dir."/".$tmpFileName;

            yield new \Amp\Delayed(5000);

            try {
                yield $app->createFolder($this->dir);
            } catch (\Throwable $error) {
                $app->logger->log("ERROR", "[DownloadClient] finalizeDownload createFolder ". $this->dir . ": " . $error->getMessage(), ['exception'=>$error]);
                $deferred->fail($error);
                return;
            }


            $this->app->logger->log("DEBUG", "[DownloadClient] Finalizing download");

            $app->filesystem->touch($tmpFilePath)->onResolve(function ($error, $value) use ($app, $deferred, $tmpFilePath) {
                if ($error) {
                    $app->logger->log("ERROR", "[DownloadClient] finalizeDownload touch ". $this->id . ": " . $error->getMessage(), ['exception'=>$error]);
                    $deferred->fail($error);
                    return;
                } else {

                    try {
                        $file = yield \Amp\File\openFile($tmpFilePath, "w");
                    } catch (\Throwable $error) {
                        $app->logger->log("ERROR", "[DownloadClient] finalizeDownload write ". $this->id . ": " . $error->getMessage(), ['exception'=>$error]);
                        $deferred->fail($error);   
                        yield $file->close();

                        try {
                            yield $app->filesystem->deleteFile($tmpFilePath);
                        } catch (\Throwable $error) {
                            $app->logger->log("ERROR", "[DownloadClient] finalizeDownload error deleteFile ". $tmpFilePath . ": " . $error->getMessage(), ['exception'=>$error]);
                        }

                        return;
                    }

                    foreach($this->chunkData as $chunk) {

                        try {
                            $i = array_search($chunk['id'], array_keys($this->chunkData));
                            $app->logger->log("DEBUG", "[DownloadClient] Download finalize ".$this->id." write chunk ".$i."/".$this->chunkCount ." | ".\tuefekci\helpers\Strings::filesizeFormatted(($this->chunkSize*$i))."/".\tuefekci\helpers\Strings::filesizeFormatted($this->size));
                            yield $file->write(yield $app->filesystem->read($chunk['path']));
                        } catch (\Throwable $error) {
                            $app->logger->log("ERROR", "[DownloadClient] finalizeDownload write ". $this->id . ": " . $error->getMessage(), ['exception'=>$error]);
                            $deferred->fail($error);  
                            yield $file->close(); 

                            try {
                                yield $app->filesystem->deleteFile($tmpFilePath);
                            } catch (\Throwable $error) {
                                $app->logger->log("ERROR", "[DownloadClient] finalizeDownload error deleteFile ". $tmpFilePath . ": " . $error->getMessage(), ['exception'=>$error]);
                            }

                            return;
                        }

                    }

                    // Close file.
                    yield $file->close();

                    yield new \Amp\Delayed(1000);

                    try {

                        $fileSize = yield $app->filesystem->getSize($tmpFilePath);

                        if((int) $fileSize != (int) $this->size) {

                            yield $app->filesystem->deleteFile($tmpFilePath);
                            $this->done = false;
                            $deferred->fail(new \Exception("Size mismatch."));

                        } else {

                            // Remove chunks
                            foreach($this->chunkData as $chunk) {

                                try {
                                    yield $app->filesystem->deleteFile($chunk['path']);
                                } catch (\Throwable $error) {
                                    $app->logger->log("ERROR", "[DownloadClient] finalizeDownload removeChunk ". $chunk['path'] . ": " . $error->getMessage(), ['exception'=>$error]);
                                }
        
                            }

                            $app->logger->log("DEBUG", "[DownloadClient] Download finalize completed ".$this->path);
                            yield $app->filesystem->move($tmpFilePath, $this->path);
                            yield new \Amp\Delayed(1000);
                            $deferred->resolve($this->path);
                        }

                    } catch (\Throwable $error) {
                        $app->logger->log("ERROR", "[DownloadClient] finalizeDownload ". $this->id . ": " . $error->getMessage(), ['exception'=>$error]);
                        try {
                            yield $app->filesystem->deleteFile($tmpFilePath);
                        } catch (\Throwable $error) {
                            $app->logger->log("ERROR", "[DownloadClient] finalizeDownload error deleteFile ". $tmpFilePath . ": " . $error->getMessage(), ['exception'=>$error]);
                        }
                        $deferred->fail($error);
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