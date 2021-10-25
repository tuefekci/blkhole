<?php

namespace GT\BLK\Provider;

class Alldebrid extends ProviderInterface {

    public $config;
    public $agent;
    public $apiKey;
    public $apiUrl;

    public $status;
    public $downloadLinks;

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

    public function getDownload($id) {

        /*
        if(empty($this->status)) {
            return \React\Promise\reject();
        }

        $status = $this->status[$id];

        $pending = [];

        if(empty($status->links)) {
            return \React\Promise\reject();
        } else {
            foreach($status->links as $link) {

                $pending[] = $this->app->browserGet($this->apiUrl."link/unlock?agent=".$this->agent ."&apikey=".$this->apiKey."&link=".urlencode($link->link))->then(function($response) use ($id) {

                    $data = json_decode($response->body);
        
                    if($data->status == "success") {
                        $this->app->info($data->status, "Alldebrid->getDownload(".$id.")");

                        if(isset($data->data->delayed)) {

                            $this->app->loop->addPeriodicTimer(5, function ($timer) use ($id, $data) {
                                // https://api.alldebrid.com/v4/link/delayed?agent=myAppName&apikey=someValidApikeyYouGenerated&id=ID

                                $pending[] = $this->app->browserGet($this->apiUrl."link/delayed?agent=".$this->agent ."&apikey=".$this->apiKey."&id=".urlencode($data->data->delayed))->then(function($response) use ($id, $timer) {
                                    $data = json_decode($response->body);

                                    if($data->status == "success") {

                                        $this->app->info($data->data->status, "Alldebrid->getDownloadDelayed");

                                        switch ($data->data->status) {
                                            case 1:
                                                // Still generating DL link
                                                break;
                                            case 2:
                                                return $data->data;
                                                break;
                                            case 3:
                                                $this->app->loop->cancelTimer($timer);
                                                break;
                                        }

                                    }

                                }, function ($e) use ($id, $timer) {
                                    $this->app->error($e->getMessage(), "Alldebrid->getDownloadDelayed(".$id.")");
                                    $this->app->loop->cancelTimer($timer);
                                });

                            });

                        } else {
                            return $data->data;
                        }

                    } else {
                        $this->app->error($data->status, "Alldebrid->getDownload(".$id.")");
                    }
        
                }, function ($e) use ($id) {
                    $this->app->error($e->getMessage(), "Alldebrid->getDownload(".$id.")");
                });

            }

            return \React\Promise\all($pending)->then(function($resolved) use ($id) {
                $this->downloadLinks[$id] = $resolved; // [10, 20]
                return $this->downloadLinks[$id];
            });
        }
*/
    }

    public function delete($id) {
        //https://api.alldebrid.com/v4/magnet/delete?agent=myAppName&apikey=someValidApikeyYouGenerated&id=MAGNETID
        return $this->app->browserGet($this->apiUrl."magnet/delete?agent=".$this->agent ."&apikey=".$this->apiKey."&id=".$id)->then(function($response) {
            
            $data = json_decode($response->body);

            if($data->status == "success") {
                return $data->data;
            }

        }, function ($e) use ($id) {
            $this->app->error($e->getMessage(), "Alldebrid->delete(".$id.")");
        });
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
