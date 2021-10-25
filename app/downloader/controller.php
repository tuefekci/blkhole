<?php

namespace GT\BLK\Downloader;

use Amp\Loop;

class Controller {

    public $app;

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

    public function get($id) {

        // TODO: This whole situation how downloads are assigned etc. should be reworked.

        if(!empty($this->downloads[$id])) {
            return $this->downloads[$id];
        }

        if(!empty($this->downloadsDone[$id])) {
            return $this->downloadsDone[$id];
        }

        foreach($this->downloadQueue as $download) {
            if($download['id'] == $id) {
                $download['done'] = false;
                return (object) $download;
            }
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