<?php

namespace tuefekci\blk\DownloadClient;

use Amp\Loop;

class Manager {

    public \tufekci\blk\App $app;

    public $downloads = [];
    public $downloadQueue = [];
    public $downloadsDone = [];

    public $paralel = 3;

    public function __construct($app) {
        $this->app = $app;

        $this->app->log("loaded", $this->getNameOfClass());

        Loop::repeat($msInterval = 1000, function () {
            $this->stats();
            $this->cycle();
        });

    }

    public function getNameOfClass()
    {
       return static::class;
    }

    public function cycle() {
        // ===============================================================
        // Handle downloadQueue
        if(!empty($this->downloadQueue) && (int)$this->app->config['downloader']['paralel'] > count($this->downloads)) {
            $dlData = $this->downloadQueue[array_key_first($this->downloadQueue)];
            unset($this->downloadQueue[array_key_first($this->downloadQueue)]);

            $this->downloads[$dlData['id']] = new Download($this, $dlData['id'], $dlData['path'], $dlData['url'], $dlData['size']);
        }

        // ===============================================================
        // handle completed downloads
        if(!empty($this->downloads)) {
            foreach($this->downloads as $id => $download) {
                if($download->done) {
                    $this->downloadsDone[$download->id] = $download;
                    unset($this->downloads[$download->id]);
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

        return array(
            'done' => $download->done,
            'id' => $download->id,
            'path' => $download->path,
            'dir' => $download->dir,
            'url' => $download->url,
            'size' => $download->size,
            'sizeText' => $download->sizeText,
            'currentSize' => $download->currentSize,
            'percent' => $download->percent,
            'speed' => $download->speed,
            'speedText' => $download->speedText,
            'speedLimit' => $download->speedLimit,
            'time' => $download->time,
            'timeText' => $download->timeText,
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