<?php

namespace GT\BLK;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Loop;


class App {
    public $cli;
    public $loop;
    public $store;
    public $config;

    public $filesystem;
    public $putContentsQueue;
    public $getContentsQueue;

    public $connector;
    public $browser;
    public $browserQueue;

    public function __construct() {

        $this->cli = new \League\CLImate\CLImate;
        $this->filesystem = \React\Filesystem\Filesystem::create($this->loop);
        $this->store = new \Flintstone\Flintstone('store', ['dir' => __DATA__.'/store']);
        $this->config = \Noodlehaus\Config::load(__CONF__.'/config.ini')->all();

        //var_dump($this->config);
        //die();

        // =================================================================
        // FileSystem queue
        $this->putContentsQueue = new \Clue\React\Mq\Queue(10, null, function ($path, $data) {
            return $this->filesystem->file($path)->putContents($data);
        });

        $this->getContentsQueue = new \Clue\React\Mq\Queue(10, null, function ($path) {
            return $this->filesystem->file($path)->getContents();
        });
        // =================================================================

        // =================================================================
        // HTTP/Browser
        /*
        $this->connector = new \React\Socket\Connector($this->loop, array(
            'dns' => '8.8.8.8' // DNS? Dont know why it needs to be set but why not.
        ));
        $this->browser = new \React\Http\Browser($this->loop, $this->connector);
        $this->browserQueue = new \Clue\React\Mq\Queue(5, null, function ($url) {
            return $this->browser->get($url, ['User-Agent'=>"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36"]);
        });
        */


        
        // =================================================================
    }

    public function run() {

        $this->cli->clear();
        $this->cli->break();
        $this->cli->lightGreen()->border("*");
        $this->cli->lightGreen()->out('* blkHole');
        $this->cli->lightGreen()->out('* (c) 2020-'.date("Y").' Giacomo Tüfekci');
        $this->cli->lightGreen()->out('* https://github.com/tuefekci/blkhole');
        $this->cli->lightGreen()->border("*");
        $this->cli->lightGreen()->break();

        //$provider = new Provider\Alldebrid($this);
        //$downloader = new Downloader\Controller($this);

        // =================================================================
        // loop

        /*
        $this->loop->addPeriodicTimer(30, function () {
            // Read Files
            // Upload to Provider
            // Check Provider
            // Download
            // Remove Task/Download
        });
        */

        Loop::run(function() {

            Loop::repeat($msInterval = 5000, function () {
                $this->checkFiles();
            });

            //Loop::delay($msDelay = 5000, "Amp\\Loop::stop");
        });

        // =================================================================
    }


    private function checkFiles() {
        $this->info(__BLACKHOLE__, "checkFiles->blackhole");


        // Torrents
        $this->info("torrents", "checkFiles->blackhole->process");
        foreach (glob(__BLACKHOLE__."/*/*.torrent") as $filename) {
            echo "$filename - Größe: " . filesize($filename) . "\n";
        }

        // Magnets
        $this->info("magnets", "checkFiles->blackhole->process");
        foreach (glob(__BLACKHOLE__."/*/*.magnet") as $filename) {
            echo "$filename - Größe: " . filesize($filename) . "\n";
        }

        //__BLACKHOLE__
        //__DOWNLOADS__
        
    }

    private function out($color, $message, $header=false) {

        $this->cli->$color()->inline("[".date("Y-m-d H:i:s")."] ");

        if($header && is_string($header)) {
            $this->cli->$color()->inline("(".$header.") ");
        }

        if(is_string($message)) {
            $this->cli->inline($message);
            $this->cli->break();
        } elseif(is_array($message)) {
            var_dump($message);
        } else {
            $this->cli->break();
        }
    }

    public function log($message, $header=false) {
        $this->out("Yellow", $message, $header);
    }

    public function info($message, $header=false) {
        if(!VERBOSE) {
            return true;
        }
        $this->out("Cyan", $message, $header);
    }

    public function warn($message, $header=false) {
        $this->out("Orange", $message, $header);
    }

    public function error($message, $header=false) {
        $this->out("Red", $message, $header);
    }

    public function keyGet($key) {
        return $this->store->get(md5($key));
    }

    public function keySet($key, $value) {
        return $this->store->set(md5($key), $value);
    }

    public function browserGet($url) {
        $this->info($url, "browserGet");

        return $this->browserQueue->__invoke($url)->then(function($response) use ($url) {
            $data = array();
            $data['headers'] = (array)$response->getHeaders();
            $data['body'] = (string)$response->getBody();
            $data = (object) $data;

            $this->info($url, "browserGet->Response");
            return $data;
        });
    }

    public function putContents($path, $data) {
        $this->info($path, "putContents");
        return $this->putContentsQueue->__invoke($path, $data);
    }

    public function getContents($path) {
        $this->info($path, "getContents");
        return $this->getContentsQueue->__invoke($path);
    }

    function filesize_formatted($size)
    {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

}