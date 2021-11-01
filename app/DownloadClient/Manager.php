<?php

namespace tuefekci\blk\DownloadClient;

use Amp\Loop;

class Manager {

    public $app;

    public $downloads = [];
    public $downloadQueue = [];
    public $downloadsDone = [];

    public $parallel = 3;

    public function __construct($app) {
        $this->app = $app;

        $app->logger->log("INFO", "loaded->".$this->getNameOfClass());

        Loop::repeat($msInterval = 1000, function () {
            $this->stats();
            $this->cycle();
        });

        Loop::repeat($msInterval = 10000, function () {
            if (count($this->downloadQueue) > 0) {
                $this->app->logger->log("INFO", "DownloadClient ".count($this->downloadQueue)." downloads in queue, ".count($this->downloads)." downloads in progress, ".count($this->downloadsDone)." downloads finished.");
            }
        });

    }

    public function getNameOfClass()
    {
       return static::class;
    }

    public function cycle() {

        if(\tuefekci\helpers\Store::has("DOWNLOAD_PARALLEL")) {
            $this->parallel = \tuefekci\helpers\Store::get("DOWNLOAD_PARALLEL");
        } else {
            $this->app->logger->log("ERROR", "[DownloadClient] No DOWNLOAD_PARALLEL found, please set it in the settings.");
        }


        // ===============================================================
        // Handle downloadQueue
        if(!empty($this->downloadQueue) && (int)$this->parallel > count($this->downloads)) {
            $dlData = $this->downloadQueue[array_key_first($this->downloadQueue)];
            unset($this->downloadQueue[array_key_first($this->downloadQueue)]);

            $this->downloads[$dlData['id']] = new Download($this, $dlData['id'], $dlData['path'], $dlData['url'], $dlData['size']);
        }

        // ===============================================================
        // handle completed downloads
        if(!empty($this->downloads)) {
            foreach($this->downloads as $id => $download) {
                if($download->done || !empty($download->error)) {

                    if($download->done && $download->size !== $download->currentSize) {
                        $download->error[] = "size mismatch";
                    }

                    if($download->error) {

                        // Download has issues, remove it from the list and add it to the list
                        if(is_array($download->error)) {
                            $error = implode("; ", $download->error);
                        } else {
                            $error = $download->error;
                        }

                        $this->app->logger->log("ERROR", "[DownloadClient] Download ".$id." failed: ".$error);

                        $this->remove($download->id);
                        $this->manager->add($download->id, $download->path, $download->url, $download->size);

                    } else {

                        // Download is done!
                        $this->remove($download->id);
                        $this->downloadsDone[$download->id] = $download;
                        $this->app->logger->log("INFO", "[DownloadClient] Download ".$id." finished.");

                    }



                    

                }
            }
        }
    }



    public function stats() {
        //print_r($this->downloads);
        //echo PHP_EOL;
    }



    public function info($id) {

        $download = $this->get($id);

        if(!$download) {
            return false;
        }


        $percent = 0;
        if(!empty($download->currentSize) && !empty($download->size)) {
            $percent = round(($download->currentSize / $download->size) * 100, 2);
        }
        
        return array(
            'done' => $download->done,
            'id' => $download->id,
            'path' => $download->path,
            'dir' => $download->dir,
            'url' => $download->url,
            'size' => $download->size,
            'sizeText' => \tuefekci\helpers\Strings::filesizeFormatted($download->size),
            'currentSize' => $download->currentSize,
            'currentSizeText' => \tuefekci\helpers\Strings::filesizeFormatted($download->currentSize),
            'percent' => $percent,
            'speed' => $download->speed,
            'speedText' => \tuefekci\helpers\Strings::filesizeFormatted($download->speed),
            'speedLimit' => $download->speedLimit,
            'time' => $download->time,
            'timeText' => gmdate('H:i:s', (int) round($download->time)),
            'secData' => $download->secData,
            'secDataHistory' => $download->secDataHistory
        );

    }

    public function get($id) {

        // TODO: This whole situation how downloads are assigned etc. should be reworked.

        if(!empty($this->downloads[$id])) {
            return $this->downloads[$id];
        }

        if(!empty($this->downloadsDone[$id])) {
            return $this->downloadsDone[$id];
        }

        return false;

    }

    public function add($id, $path, $url, $size=null) {
        $this->downloadQueue[] = array("id"=>$id, "path"=>$path, "url"=>$url, "size"=>$size);
    }

    public function remove($id) {
        unset($this->downloads[$id]);
        unset($this->downloadsDone[$id]);

        foreach($this->downloadQueue as $key => $download) {
            if($download['id'] == $id) {
                unset($this->downloadQueue[$key]);
            }
        }
    }

}