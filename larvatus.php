<?php

class Larvatus
{
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => []
    ];

    private $url;
    private $method;
    private $environment;
    private $routePrefix = '';
    private $pdo;

    public function __construct($environment = 'production')
    {
        $this->environment = $environment;

        // Initialize URL and method from the server request
        $this->url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // Use parse_url to get only path part
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Set error reporting based on environment
        $this->setErrorReporting();

        // Start session
        session_start();
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

    private function addRoute($method, $route, $handler)
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
        call_user_func($callback);
        $this->routePrefix = $currentPrefix;
    }

    public function listen()
    {
        $routes = $this->routes[$this->method] ?? [];

        foreach ($routes as $route => $handler) {
            if (preg_match($route, $this->url, $matches)) {
                // Remove the full match
                array_shift($matches);

                // Create request and response objects
                $request = new Request();
                $response = new Response();

                // Set route parameters in the request object
                $request->setParams($matches);

                // Pass request and response objects to the handler
                return call_user_func_array($handler, [$request, $response]);
            }
        }
        $this->errorResponse(404, 'Not Found');
    }

    public function jsonResponse($data, $status)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit; // Terminate script execution after sending response
    }

    private function errorResponse($status, $message)
    {
        if ($this->method === 'GET') {
            echo "<h1>$status $message</h1>";
            exit;
        } else {
            $this->jsonResponse(['error' => ['message' => $message]], $status);
        }
    }
    
    public static function redirect($url, $permanent = false)
    {
        if (headers_sent() === false) {
            header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
        }
        exit();
    }

    public function __destruct()
    {
        // Close database connection if needed
        $this->pdo = null;
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


