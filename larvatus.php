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

    public function __construct($environment = 'production', $dbConfig = [])
    {
        $this->environment = $environment;

        // Initialize URL and method from the server request
        $this->url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // Use parse_url to get only path part
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Set error reporting based on environment
        $this->setErrorReporting();

        // Start session
        session_start();

        // Initialize database connection if config is provided
        if (!empty($dbConfig)) {
            $this->initializeDb($dbConfig);
        }
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

    private function initializeDb($dbConfig)
    {
        try {
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->errorResponse(500, 'Database connection failed: ' . $e->getMessage());
        }
    }

    public function executeQuery($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->errorResponse(500, 'Query execution failed: ' . $e->getMessage());
        }
    }
    
    public static function sanitizeInput($input)
    {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public static function generateCSRFToken()
    {
        return bin2hex(random_bytes(32));
    }

    public static function validateCSRFToken($token, $sessionToken)
    {
        return hash_equals($token, $sessionToken);
    }
    
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    public static function verifyPassword($password, $hashedPassword)
    {
        return password_verify($password, $hashedPassword);
    }
    
    public static function verifyFileUpload($fileData, $uploadPath, $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'])
    {
        $fileName = basename($fileData['name']);
        $fileTmpName = $fileData['tmp_name'];
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileType, $allowedExtensions)) {
            return false; 
        }
        $newFileName = self::generateRandomString(10) . '_' . $fileName;
        $uploadFilePath = $uploadPath . '/' . $newFileName;
        if (move_uploaded_file($fileTmpName, $uploadFilePath)) {
            return $newFileName; 
        } else {
            return false; 
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

class ORM
{
    protected $pdo;
    protected $table;

    public function __construct($pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all()
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $keys = implode(',', array_keys($data));
        $values = ':' . implode(',:', array_keys($data));
        $sql = "INSERT INTO {$this->table} ({$keys}) VALUES ({$values})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data)
    {
        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= "$key=:$key,";
        }
        $fields = rtrim($fields, ',');
        $sql = "UPDATE {$this->table} SET {$fields} WHERE id = :id";
        $data['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
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


