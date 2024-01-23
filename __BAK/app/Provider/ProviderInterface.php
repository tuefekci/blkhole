<?php

namespace tuefekci\blk\Provider;

class ProviderInterface {

    public $app;
    public $client;
    public array $status = array();
    
    public function __construct($app) {
        $this->app = $app;

        $app->logger->log("INFO", "loaded->".$this->getNameOfClass());

        $this->client = \Amp\Http\Client\HttpClientBuilder::buildDefault();

        \Amp\Loop::repeat($msInterval = 10000, function () {
            $this->getStatus();
        });
    }

    public function status($id=null) {

        if($id && !empty($this->status[$id])) {
            return $this->status[$id];
        } elseif (empty($id)) {
            return $this->status;
        } else {
            return false;
        }
        
    }

    public function getStatus() {
    }

    public function getNameOfClass()
    {
       return static::class;
    }

}

