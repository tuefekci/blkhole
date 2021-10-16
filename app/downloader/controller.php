<?php

namespace GT\BLK\Downloader;

class Controller {

    public $app;
    public $loop;

    public $downloads = [];
    public $downloadQueue = [];

    public $paralel = 3;

    public function __construct($app) {
        $this->app = $app;
        $this->loop = $app->loop;

        $this->app->log("loaded", $this->getNameOfClass());

        $this->loop->addPeriodicTimer(1, function () {
            $this->stats();
            $this->cycle();
        });

        $app->loop->addTimer(99999999999, function () {
            $this->add("test1", __DATA__."/downloads/".uniqid("test")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
            $this->add("test2", __DATA__."/downloads/".uniqid("test")."BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
            $this->add("test3", __DATA__."/downloads/".uniqid("test")."/BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
            $this->add("test4", __DATA__."/downloads/".uniqid("test")."/BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
            $this->add("test5", __DATA__."/downloads/".uniqid("test")."/BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
            $this->add("test6", __DATA__."/downloads/".uniqid("test")."/BigBuckBunny.mp4", "https://file-examples-com.github.io/uploads/2017/04/file_example_MP4_1920_18MG.mp4");
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
        print_r($this->downloads);
        echo PHP_EOL;
    }

    public function add($id, $path, $url) {
        $this->downloadQueue[] = array("id"=>$id, "path"=>$path, "url"=>$url);
    }

    public function remove($id) {
        unset($this->downloads[$id]);
    }

}