<?php

namespace GT\BLK\Provider;

class ProviderInterface {

    public $app;

    public function __construct($app) {
        $this->app = $app;
        $this->app->log("loaded", $this->getNameOfClass());
    }

    public function getNameOfClass()
    {
       return static::class;
    }

}

