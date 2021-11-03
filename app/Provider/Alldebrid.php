<?php

namespace tuefekci\blk\Provider;

class Alldebrid extends ProviderInterface {

    public $agent;
    public $apiKey;
    public $apiUrl;

    public function __construct($app) {
        parent::__construct($app);

        $this->agent = "blkhole";
        if(\tuefekci\helpers\Store::has("ALLDEBRID_AGENT")) {
            $this->agent = \tuefekci\helpers\Store::get("ALLDEBRID_AGENT");
        }

        if(\tuefekci\helpers\Store::has("ALLDEBRID_APIKEY")) {
            $this->apiKey = \tuefekci\helpers\Store::get("ALLDEBRID_APIKEY");
        } else {
            $app->logger->log("ERROR", "[Alldebrid] No API key found, please set it in the settings.");
        }

        $this->apiUrl = "https://api.alldebrid.com/v4/";
    }

    public function addTorrent() {

    }

    public function addMagnet($magnet) {

        $deferred = new \Amp\Deferred;

        $app = $this->app;
        $client = $this->client;


        \Amp\asyncCall(function() use ($app, $client, $deferred, $magnet) {
            $request = new \Amp\Http\Client\Request($this->apiUrl."magnet/upload?agent=".$this->agent ."&apikey=".$this->apiKey."&magnets[]=".urlencode($magnet));

            $client->request($request)->onResolve(function ($error, $response) use ($app, $deferred) {

                if ($error) {
                    $app->logger->log("ERROR", "[Alldebrid] addMagnet->request", ['exception'=>$error]);
                } else {
                    $data = yield $response->getBody()->buffer();
                    $data = json_decode($data);

                    if($data && $data->status == "success") {
                        $deferred->resolve($data->data->magnets[0]);
                    } else {
                        yield new \Amp\Delayed(1000);
                        $deferred->fail(new \Exception("addMagnet failed."));
                    }

                }

            });
        });

        return $deferred->promise();
    }

    public function upload($data) {

    }

    public function getDownload($link) {


        $deferred = new \Amp\Deferred;


        \Amp\asyncCall(function() use ($deferred, $link) {

            $app = $this->app;
    
            $client = $this->client;
            $request = new \Amp\Http\Client\Request($this->apiUrl."link/unlock?agent=".$this->agent ."&apikey=".$this->apiKey."&link=".urlencode($link));

            $client->request($request)->onResolve(function ($error, $response) use ($app, $deferred, $link) {

                if ($error) {
                    $app->logger->log("ERROR", "[Alldebrid] addMagnet->request", ['exception'=>$error]);
                } else {
                    $data = yield $response->getBody()->buffer();
                    $data = json_decode($data);

                    if($data && $data->status == "success") {
                        $app->logger->log("DEBUG", "[Alldebrid] getDownload->(".$link.")", ['status'=>$data->status, 'exception'=>$error]);

                        if(isset($data->data->delayed)) {
                            $deferred->fail(new \Throwable("Download link delayed."));
                        } else {
                            $deferred->resolve($data->data);
                        }
                    }

                }

            });
        });

        return $deferred->promise();
    }

    public function delete($id) {

        //https://api.alldebrid.com/v4/magnet/delete?agent=myAppName&apikey=someValidApikeyYouGenerated&id=MAGNETID
        $deferred = new \Amp\Deferred;

        \Amp\asyncCall(function() use ($deferred, $id) {

            $app = $this->app;
    
            $client = $this->client;
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

        });

        return $deferred->promise();
    }

    public function restart($id) {
        $_this = $this;

        return $this->app->browserGet($this->apiUrl."magnet/restart?agent=".$this->agent ."&apikey=".$this->apiKey."&id=".$id)->then(function($response) use ($_this) {
            
            $data = json_decode($response->body);

            if($data->status == "success") {
                return $data->data;
            }

        }, function ($error) use ($id, $_this) {
            $_this->app->logger->log("ERROR", "[Alldebrid] delete->(".$id.")", ['exception'=>$error]);
        });
    }


    public function getStatus() {

        \Amp\asyncCall(function() {

            $app = $this->app;
    
            $client = $this->client;
            $request = new \Amp\Http\Client\Request($this->apiUrl."magnet/status?agent=".$this->agent ."&apikey=".$this->apiKey);

            $client->request($request)->onResolve(function ($error, $response) use ($app) {

                if ($error) {
                    $app->logger->log("ERROR", "[Alldebrid] getStatus->request", ['exception'=>$error]);
                } else {

                    $data = yield $response->getBody()->buffer();
                    $data = json_decode($data);

                    $this->status = array();

                    foreach($data->data->magnets  as $magnet) {
                        $this->status[$magnet->id] = $magnet;
                    }

                }

            });
        });

    }

}
