<?php

namespace GT\BLK\Provider;

class ProviderInterface {

    public $app;
    public $loop;

    public function __construct($app) {
        $this->app = $app;
        $this->loop = $app->loop;

        $this->app->log("loaded", $this->getNameOfClass());
    }

    public function getNameOfClass()
    {
       return static::class;
    }

}

