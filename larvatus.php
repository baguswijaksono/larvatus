<?php

/**
 * Class Larvatus
 * 
 * Handles routing, middleware, error handling, and request/response processing.
 * Provides methods for defining routes, adding middleware, handling HTTP methods, and listening for requests.
 */
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
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
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

    public function get(string $route, callable $handler)
    {
        $this->router->get($route, $handler);
    }

    public function post(string $route, callable $handler)
    {
        $this->router->post($route, $handler);
    }

    public function put(string $route, callable $handler)
    {
        $this->router->put($route, $handler);
    }

    public function delete(string $route, callable $handler)
    {
        $this->router->delete($route, $handler);
    }

    public function group(string $prefix, callable $callback)
    {
        $this->router->group($prefix, $callback);
    }

    public function use(string $middleware)
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

    public function errorResponse(Response $response, int $status, string $message)
    {
        $response->setStatus($status);
        $response->json(['error' => $message]);
        $response->send();
    }

    public function __destruct()
    {
        
    }
}

/**
 * Class Request
 * 
 * Represents an HTTP request and provides methods to access request data such as method, URL, headers, body, query parameters, and files.
 */
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
        $this->method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING);
        $this->url = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
        $this->headers = getallheaders();
        $this->body = file_get_contents('php://input');
        $this->queryParams = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
        $this->parsedBody = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
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

/**
 * Class Response
 * 
 * Represents an HTTP response and provides methods to set status, headers, and body content.
 * Also provides methods to send JSON responses and send the response to the client.
 */
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
        $this->setHeader('Content-Security-Policy', "default-src 'self'");
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

/**
 * Class Middleware
 * 
 * Manages a stack of middleware and provides methods to add middleware and handle requests through the middleware stack.
 */
class Middleware
{
    private $middlewareStack = [];

    public function add($middleware)
    {
        $this->middlewareStack[] = $middleware;
    }

    public function handle(Request $request, Response $response, callable $next)
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

/**
 * Class ErrorHandler
 * 
 * Handles exceptions that occur during request processing and sends a JSON response with the error message and a 500 status code.
 */
class ErrorHandler
{
    public function handle(Request $request, Response $response, callable $next)
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

/**
 * Class Router
 * 
 * Manages route definitions and matches incoming requests to the appropriate route handler.
 * Supports route grouping with prefixes and dynamic route parameters.
 */
class Router
{
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => []
    ];

    private $routePrefix = '';

    public function addRoute(string $method, string $route, callable $handler)
    {
        $route = $this->routePrefix . $route;
        $route = preg_replace('/:(\w+)/', '(?P<$1>[^/]+)', $route);
        $route = '#^' . $route . '$#';
        $this->routes[$method][$route] = $handler;
    }

    public function get(string $route, callable $handler)
    {
        $this->addRoute('GET', $route, $handler);
    }

    public function post(string $route, callable $handler)
    {
        $this->addRoute('POST', $route, $handler);
    }

    public function put(string $route, callable $handler)
    {
        $this->addRoute('PUT', $route, $handler);
    }

    public function delete(string $route, callable $handler)
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

    public function match(string $method, string $url): array
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

/**
 * Class View
 * 
 * Handles rendering of templates and passing data to templates.
 * Provides methods to set data, render templates, and display rendered content.
 */
class View
{
    private $path;
    private $data;

    public function __construct($path)
    {
        $this->path = $path;
        $this->data = [];
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function render($template, $data = [])
    {
        $data = array_merge($this->data, $data);
        $templatePath = $this->path . '/' . $template . '.php';

        if (file_exists($templatePath)) {
            extract($data);
            ob_start();
            include $templatePath;
            $content = ob_get_clean();
            return $content;
        } else {
            throw new Exception("Template not found: " . $templatePath);
        }
    }

    public function display($template, $data = [])
    {
        echo $this->render($template, $data);
    }
}
