<?php

namespace GT\BLK\Provider;

class Alldebrid extends ProviderInterface {

    public $config;
    public $agent;
    public $apiKey;
    public $apiUrl;

    public array $status = array();

    public function __construct($app) {
        parent::__construct($app);

        if(!isset($app->config) || empty($app->config)) {
            $this->agent = "apiShowcase";
            $this->apiKey = "apiShowcaseStaticApikey";
        } else {
            $this->agent = $app->config['alldebrid']['agent'];
            $this->apiKey = $app->config['alldebrid']['apiKey'];
        }

        $this->apiUrl = "https://api.alldebrid.com/v4/";
    }

    public function addTorrent() {

    }

    public function addMagnet($magnet) {

        $deferred = new \Amp\Deferred;

        $app = $this->app;
 
        $client = \Amp\Http\Client\HttpClientBuilder::buildDefault();
        $request = new \Amp\Http\Client\Request($this->apiUrl."magnet/upload?agent=".$this->agent ."&apikey=".$this->apiKey."&magnets[]=".urlencode($magnet));

        $client->request($request)->onResolve(function ($error, $response) use ($app, $deferred) {

            if ($error) {
                $app->error("addMagnet->request", $error->getMessage());
            } else {
                $data = yield $response->getBody()->buffer();
                $data = json_decode($data);

                $deferred->resolve($data->data->magnets[0]);
            }

        });

        return $deferred->promise();
    }

    public function upload($data) {

    }

    public function getDownload($link) {


        $deferred = new \Amp\Deferred;

        $app = $this->app;
 
        $client = \Amp\Http\Client\HttpClientBuilder::buildDefault();
        $request = new \Amp\Http\Client\Request($this->apiUrl."link/unlock?agent=".$this->agent ."&apikey=".$this->apiKey."&link=".urlencode($link));

        $client->request($request)->onResolve(function ($error, $response) use ($app, $deferred, $link) {

            if ($error) {
                $app->error("getDownload->request", $error->getMessage());
            } else {
                $data = yield $response->getBody()->buffer();
                $data = json_decode($data);

                if($data->status == "success") {
                    $app->info($data->status, "Alldebrid->getDownload(".$link.")");

                    if(isset($data->data->delayed)) {
                        $deferred->fail(new \Throwable("Download link delayed."));
                    } else {
                        $deferred->resolve($data->data);
                    }
                }

            }

        });

        return $deferred->promise();
    }

    public function delete($id) {

        //https://api.alldebrid.com/v4/magnet/delete?agent=myAppName&apikey=someValidApikeyYouGenerated&id=MAGNETID
        $deferred = new \Amp\Deferred;


        $app = $this->app;
 
        $client = \Amp\Http\Client\HttpClientBuilder::buildDefault();
        $request = new \Amp\Http\Client\Request($this->apiUrl."magnet/delete?agent=".$this->agent ."&apikey=".$this->apiKey."&id=".$id);

        $client->request($request)->onResolve(function ($error, $response) use ($app, $deferred) {

            if ($error) {
                $app->error("getStatus->request", $error->getMessage());
            } else {

                $data = yield $response->getBody()->buffer();
                $data = json_decode($data);

                if($data->status == "success") {
                    $deferred->resolve($data->data);
                } else {
                    $deferred->fail(new \Throwable("Delete failed."));
                }

            }

        });

        return $deferred->promise();
    }

    public function restart($id) {
        return $this->app->browserGet($this->apiUrl."magnet/restart?agent=".$this->agent ."&apikey=".$this->apiKey."&id=".$id)->then(function($response) {
            
            $data = json_decode($response->body);

            if($data->status == "success") {
                return $data->data;
            }

        }, function ($e) use ($id) {
            $this->app->error($e->getMessage(), "Alldebrid->delete(".$id.")");
        });
    }

    public function status($id=null) {

        if($id) {
            return $this->status[$id];
        } else {
            return $this->status;
        }
        
    }


    public function getStatus() {

        $app = $this->app;
 
        $client = \Amp\Http\Client\HttpClientBuilder::buildDefault();
        $request = new \Amp\Http\Client\Request($this->apiUrl."magnet/status?agent=".$this->agent ."&apikey=".$this->apiKey);

        $client->request($request)->onResolve(function ($error, $response) use ($app) {

            if ($error) {
                $app->error("getStatus->request", $error->getMessage());
            } else {

                $data = yield $response->getBody()->buffer();
                $data = json_decode($data);

                $this->status = array();

                foreach($data->data->magnets  as $magnet) {
                    $this->status[$magnet->id] = $magnet;
                }

            }

        });

    }

}
