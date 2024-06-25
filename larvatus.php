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

                // Pass $matches as req.params to handler
                return call_user_func_array($handler, [$matches]);
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

    public function __destruct()
    {
        // Implement database connection closing if needed
    }
}
