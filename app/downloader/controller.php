<?php

namespace GT\BLK\Downloader;

use Amp\Loop;

class Controller {

    public $app;

    public $downloads = [];
    public $downloadQueue = [];

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
        // Handle downloadQueue
        if(!empty($this->downloadQueue) && (int)$this->app->config['downloader']['paralel'] > count($this->downloads)) {
            $dlData = $this->downloadQueue[array_key_first($this->downloadQueue)];
            unset($this->downloadQueue[array_key_first($this->downloadQueue)]);

            $this->downloads[$dlData['id']] = new Download($this, $dlData['id'], $dlData['path'], $dlData['url']);
        }
    }

    public function stats() {
        //print_r($this->downloads);
        //echo PHP_EOL;
    }

    public function add($id, $path, $url) {
        $this->downloadQueue[] = array("id"=>$id, "path"=>$path, "url"=>$url);
    }

    public function remove($id) {
        unset($this->downloads[$id]);
    }

}