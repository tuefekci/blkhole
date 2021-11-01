<?php

namespace tuefekci\blk;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Request;
use Amp\Http\Status;
use Amp\Socket;
use Amp\File\Filesystem;
use Amp\Promise;
use Amp\Http\Server\Middleware;

class Web {

    private string $documentRoot;
    private \Amp\Http\Server\Router $router;

    private App $app;

    private Filesystem $filesystem;

    public function __construct($app) {

        $_this = $this;
        $this->app = $app;
        $this->filesystem = $this->app->filesystem;
        $this->documentRoot = realpath(__PUBLIC__);
        $this->router = new \Amp\Http\Server\Router;

        $middleware = new class implements Middleware {
            public function handleRequest(Request $request, \Amp\Http\Server\RequestHandler $next): Promise {
                return \Amp\call(function () use ($request, $next) {
                    $response = yield $next->handleRequest($request);
                    $response->setHeader("Access-Control-Allow-Origin", "*");
                    $response->setHeader("Access-Control-Request-Headers", "*");
                    $response->setHeader("Access-Control-Max-Age", 86400);
        
                    return $response;
                });
            }
        };

        
        $this->router->stack($middleware);

        $this->router->addRoute('GET', '/', new CallableRequestHandler(function () use ($_this) {
            return $_this->index();
        }));

        $this->router->addRoute('GET', '/test', new CallableRequestHandler(function () use ($_this)  {
            $_this->app->addMagnet("magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny&tr=udp%3A%2F%2Fexplodie.org%3A6969&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A6969&tr=udp%3A%2F%2Ftracker.empire-js.us%3A1337&tr=udp%3A%2F%2Ftracker.leechers-paradise.org%3A6969&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337&tr=wss%3A%2F%2Ftracker.btorrent.xyz&tr=wss%3A%2F%2Ftracker.fastcast.nz&tr=wss%3A%2F%2Ftracker.openwebtorrent.com&ws=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2F&xs=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2Fbig-buck-bunny.torrent");
            $_this->app->addMagnet("magnet:?xt=urn:btih:c9e15763f722f23e98a29decdfae341b98d53056&dn=Cosmos+Laundromat&tr=udp%3A%2F%2Fexplodie.org%3A6969&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A6969&tr=udp%3A%2F%2Ftracker.empire-js.us%3A1337&tr=udp%3A%2F%2Ftracker.leechers-paradise.org%3A6969&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337&tr=wss%3A%2F%2Ftracker.btorrent.xyz&tr=wss%3A%2F%2Ftracker.fastcast.nz&tr=wss%3A%2F%2Ftracker.openwebtorrent.com&ws=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2F&xs=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2Fcosmos-laundromat.torrent");
            $_this->app->addMagnet("magnet:?xt=urn:btih:08ada5a7a6183aae1e09d831df6748d566095a10&dn=Sintel&tr=udp%3A%2F%2Fexplodie.org%3A6969&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A6969&tr=udp%3A%2F%2Ftracker.empire-js.us%3A1337&tr=udp%3A%2F%2Ftracker.leechers-paradise.org%3A6969&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337&tr=wss%3A%2F%2Ftracker.btorrent.xyz&tr=wss%3A%2F%2Ftracker.fastcast.nz&tr=wss%3A%2F%2Ftracker.openwebtorrent.com&ws=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2F&xs=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2Fsintel.torrent");
            $_this->app->addMagnet("magnet:?xt=urn:btih:209c8226b299b308beaf2b9cd3fb49212dbd13ec&dn=Tears+of+Steel&tr=udp%3A%2F%2Fexplodie.org%3A6969&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A6969&tr=udp%3A%2F%2Ftracker.empire-js.us%3A1337&tr=udp%3A%2F%2Ftracker.leechers-paradise.org%3A6969&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337&tr=wss%3A%2F%2Ftracker.btorrent.xyz&tr=wss%3A%2F%2Ftracker.fastcast.nz&tr=wss%3A%2F%2Ftracker.openwebtorrent.com&ws=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2F&xs=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2Ftears-of-steel.torrent");
            $_this->app->addMagnet("magnet:?xt=urn:btih:a88fda5954e89178c372716a6a78b8180ed4dad3&dn=The+WIRED+CD+-+Rip.+Sample.+Mash.+Share&tr=udp%3A%2F%2Fexplodie.org%3A6969&tr=udp%3A%2F%2Ftracker.coppersurfer.tk%3A6969&tr=udp%3A%2F%2Ftracker.empire-js.us%3A1337&tr=udp%3A%2F%2Ftracker.leechers-paradise.org%3A6969&tr=udp%3A%2F%2Ftracker.opentrackr.org%3A1337&tr=wss%3A%2F%2Ftracker.btorrent.xyz&tr=wss%3A%2F%2Ftracker.fastcast.nz&tr=wss%3A%2F%2Ftracker.openwebtorrent.com&ws=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2F&xs=https%3A%2F%2Fwebtorrent.io%2Ftorrents%2Fwired-cd.torrent");
            return new Response(Status::OK, ['content-type' => 'text/plain'], 'Added Tests!'.time());
        }));

        $this->router->addRoute('GET', '/restart', new CallableRequestHandler(function () {
            die("RESTART");
            return new Response(Status::OK, ['content-type' => 'text/plain'], 'Hello, world!'.time());
        }));

        $this->router->addRoute('POST', '/add/magnet', new CallableRequestHandler(function (\Amp\Http\Server\Request $request) use ($_this) {

            try {

                $data = yield $request->getBody()->buffer();
                $data = \json_decode($data);

                if(!empty($data) && !empty($data->magnet)) {
                    $_this->app->addMagnet($data->magnet);
                }

                return new Response(Status::OK, ['content-type' => 'text/plain'], ''.time());

            } catch (\Throwable $th) {
                //throw $th;
                $_this->app->logger->log("ERROR", $th->getMessage(), $th);
            }



        }));


        $this->router->addRoute('GET', '/status', new CallableRequestHandler(function () use ($_this) {

            try {
                $data = $this->app->magnets;

                foreach($data as $path => $magnet) {

                    if(!empty($magnet['provider'])) {

                        $status = $this->app->provider->status($magnet['provider']->id);

                        if(!empty($status)) {
                            $data[$path]['providerStatus'] = $status;
                        }
                        
                    }

                    if(!empty($magnet['downloads'])) {
                        foreach($magnet['downloads'] as $key => $download) {
    
                            $info = $this->app->downloadClient->info($download);
                            if($info) {
                                $data[$path]['downloads'][$key] = $info;
                            } else {
                                $data[$path]['downloads'][$key] = false;
                            }
                        }
                    }
                }
    
                $returnData = \json_encode($data);
    
                return new Response(Status::OK, ['content-type' => 'text/json'], $returnData);
            } catch (\Throwable $th) {
                //throw $th;
                $_this->app->logger->log("ERROR", $th->getMessage(), $th);
            }

        }));

    
        $this->router->setFallback(new CallableRequestHandler(function (\Amp\Http\Server\Request $request) use ($_this) {

            $requestPath = $request->getUri();
            
            $path = realpath(__PUBLIC__.$request->getUri()->getPath());

            try {
                //$args = $request->getAttributes();
            } catch (\Throwable $error) {
                $_this->app->logger->log("ERROR", $error->getMessage(), $error);
            }

            try {
                if($this->filesystem->exists($path) && $this->filesystem->isFile($path)) {
                    return new Response(Status::OK, ['Access-Control-Allow-Origin'=>'*'], yield $this->filesystem->read($path));
                }
            } catch (\Throwable $error) {
                $_this->app->logger->log("ERROR", $error->getMessage(), $error);
            }


        }));

    }

    private function response($content, $status=200) {

        return new Response(
            $status,
            array(
            ),
            $content
        );

    }

    public function index() {
        // Deliver html
        return $this->response(file_get_contents(__PUBLIC__."/index.html"));
    }

    public function run() {

        $_this = $this;

        \Amp\Loop::run(static function () use ($_this) {

            $port = 1337;

            if(\tuefekci\helpers\Store::has("WEBINTERFACE_PORT")) {
                $port = \tuefekci\helpers\Store::get("WEBINTERFACE_PORT");
            } else {
                $_this->app->logger->log("ERROR", "[WebInterface] No Port found, please set it in the settings.");
            }

            $servers = [
                Socket\Server::listen("0.0.0.0:".(int)$port),
                Socket\Server::listen("[::]:".(int)$port),
            ];

            $server = new HttpServer($servers, $_this->router, $_this->app->logger);
        
            yield $server->start();
        
        });

    }

}