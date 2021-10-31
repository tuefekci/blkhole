<?php

namespace tuefekci\blk\Provider;

class ProviderInterface {

    public \tufekci\blk\App $app;

    public function __construct($app) {
        $this->app = $app;
        $this->app->log("loaded", $this->getNameOfClass());

        \Amp\Loop::repeat($msInterval = 10000, function () {
            $this->getStatus();
        });
    }

    public function getStatus() {
    }

    public function getNameOfClass()
    {
       return static::class;
    }

}

