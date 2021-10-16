<?php

namespace GT\BLK;

class Web {

    public $app;
    public $loop;

    public function __construct($app) {

        $this->app = $app;
        $this->loop = $app->loop;

    }

    private function response($content, $status=200) {

        return new \React\Http\Message\Response(
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

        $server = new \React\Http\Server($this->loop, 
        function (\Psr\Http\Message\ServerRequestInterface $request, $next) {
            $filePath = $request->getUri()->getPath();
            $file = __PUBLIC__.$filePath;

            if (file_exists($file) && !is_dir($file)) {
                $fileExt = pathinfo($file, PATHINFO_EXTENSION);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $file);
                finfo_close($finfo);
                $fileContents = file_get_contents($file);

                // Fix for incorrect mime types
                switch ($fileExt) {
                    case 'css':
                        $fileType = 'text/css';
                    break;
                    case 'js':
                        $fileType = 'application/javascript';
                    break;
                }

                $this->app->info($request->getUri()->getPath(), 'Web->Static');
                return new \React\Http\Message\Response(200, ['Content-Type' => $fileType], $fileContents);
            }

            return $next($request);
        },
        function (\Psr\Http\Message\ServerRequestInterface $request) {

            $this->app->info($request->getUri()->getPath(), 'Web->Request');

            //var_dump($request->getMethod());
            //var_dump($request->getUri());

            $dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
                $r->addRoute('GET', '/', 'index');
                // {id} must be a number (\d+)
                $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');
                // The /{title} suffix is optional
                $r->addRoute('GET', '/articles/{id:\d+}[/{title}]', 'get_article_handler');
            });

            $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

            switch ($routeInfo[0]) {
                case \FastRoute\Dispatcher::NOT_FOUND:
                    // ... 404 Not Found
                    break;
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    $allowedMethods = $routeInfo[1];
                    // ... 405 Method Not Allowed
                    break;
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars = $routeInfo[2];
                    // ... call $handler with $vars
                    var_dump($handler);
                    var_dump($vars);

                    return $this->$handler($vars);

                    break;
            }

            return false;
        });
        
        $socket = new \React\Socket\Server(8080, $this->loop);
        $server->listen($socket);
    }

}