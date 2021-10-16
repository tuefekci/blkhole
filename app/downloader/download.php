<?php

namespace GT\BLK\Downloader;

class Download {

    public $done = false;
    public $error = false;
    public $id = false;
    public $path = false;
    public $dir = false;
    public $url = false;
    public $size = 0;
    public $currentSize = 0;
    public $percent = 0;
    public $speed = 0;
    public $speedText = "";
    private $speedLimit = 0;
    public $time;
    public $timeText;
    public $secData = 0;
    public $secDataHistory = [];

    public function __construct($downloader, $id, $path, $url) {

        $app = $downloader->app;
        $app->info("added", "Download");

        $this->id = $id;
        $this->path = $path;
        $this->dir = dirname($path);
        $this->url = $url;


        if((int)$downloader->app->config['downloader']['paralel']) {
            $this->speedLimit = ((int)$downloader->app->config['downloader']['bandwith']*1000)/(int)$downloader->app->config['downloader']['paralel'];
        } else {
            $this->speedLimit = ((int)$downloader->app->config['downloader']['bandwith']*1000);
        }

        $app->filesystem->file($this->dir)->exists()->then(function () use ($app) {
            $app->info($this->dir, "found");
            $this->download($app);
        }, function () use ($app) {
            $app->info($this->dir, "create");
            $app->filesystem->dir($this->dir)->createRecursive('rwxrwx---')->then(function() use ($app) {
                $this->download($app);
            }, function ($e) use ($app) {
                $app->error($e->getMessage(), "Download->folder");
                $this->error = $e->getMessage();
            });
        });

        return $this;
    }

    private function download($app) {

        $file = \React\Promise\Stream\unwrapWritable($app->filesystem->file($this->path)->open('cw'));
        $app->browser->requestStreaming('GET', $this->url, array())->then(function (\Psr\Http\Message\ResponseInterface $response) use ($file, $app) {

            $body = $response->getBody();
            assert($body instanceof \Psr\Http\Message\StreamInterface);
            assert($body instanceof \React\Stream\ReadableStreamInterface);

            $this->size = $response->getHeaders()['Content-Length'][0];

            $timer = $app->loop->addPeriodicTimer(1, function ($timer) use ($app, $body) {
                // speed reduction resume
                $body->resume();

                $this->secDataHistory[] = $this->secData;
                $this->secDataHistory = array_slice($this->secDataHistory, -10, 10);
                $this->secData = 0;

                $this->speed = array_sum($this->secDataHistory)/count($this->secDataHistory);
                $this->speedText = $app->filesize_formatted($this->speed);

                $this->time = ($this->size-$this->currentSize)/$this->speed;
                $this->timeText = gmdate('H:i:s', $this->time);
            });

            $body->on('data', function ($chunk) use ($file, $body) {
                $file->write($chunk);
                $this->currentSize += strlen($chunk);
                $this->secData += strlen($chunk);
                $this->percent = number_format($this->currentSize / $this->size * 100, 0);

                // speed reduction pause
                if($this->secData >= $this->speedLimit) {
                    $body->pause();
                }
            });

            $body->on('error', function (\Exception $e) use ($file, $app, $timer) {
                $app->error($e->getMessage(), "Download->request");
                $this->error = $e->getMessage();
                $file->end();
                $app->loop->cancelTimer($timer);
            });
        
            $body->on('close', function () use ($file, $app, $timer) {
                $this->done = true;
                $file->end();
                $app->loop->cancelTimer($timer);
            });

        }, function ($e) use ($app, $file) {
            $app->error($e->getMessage(), "Download->request");
            $this->error = $e->getMessage();
            $file->end();
        });

    }

    

}