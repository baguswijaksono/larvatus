<?php

class Larvatus
{
    private $router;
    private $middleware;
    private $url;
    private $method;
    private $environment;
    private $pdo;

    public function __construct($environment = 'production')
    {
        $this->environment = $environment;
        $this->url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->setErrorReporting();
        session_start();
        $this->router = new Router();
        $this->middleware = new Middleware();
    }

    private function setErrorReporting()
    {
        if ($this->environment === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }
    }

    public function get($route, $handler)
    {
        $this->router->get($route, $handler);
    }

    public function post($route, $handler)
    {
        $this->router->post($route, $handler);
    }

    public function put($route, $handler)
    {
        $this->router->put($route, $handler);
    }

    public function delete($route, $handler)
    {
        $this->router->delete($route, $handler);
    }

    public function group($prefix, $callback)
    {
        $this->router->group($prefix, $callback);
    }

    public function use($middleware)
    {
        $this->middleware->add($middleware);
    }

    public function listen()
    {
        $this->middleware->add([new ErrorHandler(), 'handle']);
        $this->middleware->handle(new Request(), new Response(), function($request, $response) {
            list($handler, $params) = $this->router->match($this->method, $this->url);
            if ($handler) {
                $request->setParams($params);
                return call_user_func($handler, $request, $response);
            } else {
                $this->errorResponse($response, 404, 'Not Found');
            }
        });
    }

    private function errorResponse($response, $status, $message)
    {
        $response->setStatus($status);
        $response->json(['error' => $message]);
        $response->send();
    }

    public function __destruct()
    {
        
    }
}


class Request
{
    private $method;
    private $url;
    private $headers;
    private $body;
    private $queryParams;
    private $parsedBody;
    private $files;
    private $params;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->headers = getallheaders();
        $this->body = file_get_contents('php://input');
        $this->queryParams = $_GET;
        $this->parsedBody = $_POST;
        $this->files = $_FILES;
        $this->params = [];
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getQueryParams()
    {
        return $this->queryParams;
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function getFiles()
    {
        return $this->files;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }
}

class Response
{
    private $status;
    private $headers;
    private $body;

    public function __construct()
    {
        $this->status = 200;
        $this->headers = [];
        $this->body = '';
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    public function write($body)
    {
        $this->body .= $body;
    }

    public function json($data)
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->body = json_encode($data);
    }

    public function send()
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
        exit;
    }
}

class Middleware
{
    private $middlewareStack = [];

    public function add($middleware)
    {
        $this->middlewareStack[] = $middleware;
    }

    public function handle($request, $response, $next)
    {
        $middleware = array_shift($this->middlewareStack);
        if ($middleware) {
            return $middleware($request, $response, function() use ($request, $response, $next) {
                return $this->handle($request, $response, $next);
            });
        } else {
            return $next($request, $response);
        }
    }
}

class ErrorHandler
{
    public function handle($request, $response, $next)
    {
        try {
            return $next($request, $response);
        } catch (Exception $e) {
            $response->setStatus(500);
            $response->json(['error' => $e->getMessage()]);
            $response->send();
        }
    }
}

class Router
{
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => []
    ];

    private $routePrefix = '';

    public function addRoute($method, $route, $handler)
    {
        $route = $this->routePrefix . $route;
        $route = preg_replace('/:(\w+)/', '(?P<$1>[^/]+)', $route);
        $route = '#^' . $route . '$#';
        $this->routes[$method][$route] = $handler;
    }

    public function get($route, $handler)
    {
        $this->addRoute('GET', $route, $handler);
    }

    public function post($route, $handler)
    {
        $this->addRoute('POST', $route, $handler);
    }

    public function put($route, $handler)
    {
        $this->addRoute('PUT', $route, $handler);
    }

    public function delete($route, $handler)
    {
        $this->addRoute('DELETE', $route, $handler);
    }

    public function group($prefix, $callback)
    {
        $currentPrefix = $this->routePrefix;
        $this->routePrefix .= $prefix;
        call_user_func($callback, $this);
        $this->routePrefix = $currentPrefix;
    }

    public function match($method, $url)
    {
        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route => $handler) {
            if (preg_match($route, $url, $matches)) {
                array_shift($matches);
                return [$handler, $matches];
            }
        }
        return [null, []];
    }
}
