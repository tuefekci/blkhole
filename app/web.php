<?php

namespace GT\BLK;

use Amp\ByteStream\ResourceOutputStream;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Request;
use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Amp\Loop;
use Psr\Log\NullLogger;


use Amp\File\File;
use Amp\File\Filesystem;
use function Amp\File\filesystem;
use function Amp\Http\formatDateHeader;

use Amp\Promise;
use function Amp\ParallelFunctions\parallel;
use function Amp\ParallelFunctions\parallelMap;
use function Amp\Promise\wait;

class Web {

    private $documentRoot;
    private $router;

    private $app;

    private Filesystem $filesystem;

    public function __construct($app) {
        $_this = $this;
        $this->app = $app;
        $this->documentRoot = realpath(__PUBLIC__);
        $this->router = new \Amp\Http\Server\Router;
        $this->filesystem = filesystem();

        $this->router->addRoute('GET', '/', new CallableRequestHandler(function () use ($_this) {
            return $_this->index();
        }));

        $this->router->addRoute('GET', '/hello', new CallableRequestHandler(function () {
            return new Response(Status::OK, ['content-type' => 'text/plain'], 'Hello, world!');
        }));

        $this->router->setFallback(new CallableRequestHandler(function (\Amp\Http\Server\Request $request) use ($_this) {
            $requestPath = $request->getUri();
            $path = realpath(__PUBLIC__.$request->getUri()->getPath());

            try {
                $args = $request->getAttributes();
            } catch (\Throwable $e) {
                # code...
            }

            
            try {
                if($this->filesystem->exists($path) && $this->filesystem->isFile($path)) {
                    return new Response(Status::OK, [], yield $this->filesystem->read($path));
                }
            } catch (\Throwable $e) {
                var_dump($e->getMessage());
            }

            /*
            if(file_exists($path)) {
                return new Response(Status::OK, [], file_get_contents($path));
            }*/

            //return $_this->index();
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

            $servers = [
                Socket\Server::listen("0.0.0.0:1337"),
                Socket\Server::listen("[::]:1337"),
            ];

            $server = new HttpServer($servers, $_this->router, new \Wa72\SimpleLogger\EchoLogger());
        
            yield $server->start();
        
        });

    }

}