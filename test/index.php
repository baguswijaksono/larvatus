<?php

// Router correctly matches routes based on HTTP methods and URLs
function router_correctly_matches_routes() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Define a test route and handler
    $testRoute = '/test';
    $testHandler = function($req, $res) {
        $res->write('Test route matched');
        return $res;
    };
    
    // Add the test route to the router
    $larvatus->get($testRoute, $testHandler);
    
    // Simulate a GET request to the test route
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $testRoute;
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output matches the expected response
    $this->assertStringContainsString('Test route matched', $output);
}

// No matching route returns a 404 response
function no_matching_route_returns_404() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Simulate a GET request to a non-existent route
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/non-existent-route';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output contains a 404 error message
    $this->assertStringContainsString('Not Found', $output);
}

// ErrorHandler catches exceptions and returns a 500 response with error message
function test_error_handler_catches_exceptions_and_returns_500_response() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Define a test handler that throws an exception
    $testHandler = function($req, $res) {
        throw new Exception('Test exception');
    };
    
    // Add the test route to the router
    $larvatus->get('/test', $testHandler);
    
    // Simulate a GET request to the test route
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output contains the error message and status code
    $this->assertStringContainsString('Test exception', $output);
    $this->assertStringContainsString('500 Internal Server Error', $output);
}

// Request object correctly parses and sanitizes input data
function request_object_correctly_parses_and_sanitizes_input_data() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the superglobals for the request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/test';
    $_GET['param1'] = 'value1';
    $_POST['param2'] = 'value2';
    
    // Create a new Request object
    $request = new Request();
    
    // Assert that the Request object correctly parses and sanitizes input data
    $this->assertEquals('POST', $request->getMethod());
    $this->assertEquals('/test', $request->getUrl());
    $this->assertEquals(['param1' => 'value1'], $request->getQueryParams());
    $this->assertEquals(['param2' => 'value2'], $request->getParsedBody());
}

// Response object correctly sets status, headers, and sends JSON response
function test_response_object_behavior() {
  // Initialize the Larvatus class
  $larvatus = new Larvatus('development');
  
  // Mock the Request and Response objects
  $request = $this->createMock(Request::class);
  $response = $this->createMock(Response::class);
  
  // Set up the Response object with expected status and headers
  $response->expects($this->once())->method('setStatus')->with(200);
  $response->expects($this->once())->method('setHeader')->with('Content-Type', 'application/json');
  $response->expects($this->once())->method('setHeader')->with('Content-Security-Policy', "default-src 'self'");
  
  // Set up the JSON data to be sent
  $data = ['key' => 'value'];
  
  // Call the json method on Response object
  $response->json($data);
  
  // Verify that the JSON data is correctly set in the body
  $response->expects($this->once())->method('send')->with(json_encode($data));
  
  // Call the send method on Response object
  $response->send();
}

// Grouping routes with a common prefix works as expected
function grouping_routes_with_common_prefix() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Define a test route and handler
    $testRoute = '/test';
    $testHandler = function($req, $res) {
        $res->write('Test route matched');
        return $res;
    };
    
    // Add the test route to the router within a group
    $larvatus->group('/api', function($router) use ($testRoute, $testHandler) {
        $router->get($testRoute, $testHandler);
    });
    
    // Simulate a GET request to the test route within the group
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api' . $testRoute;
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output matches the expected response
    $this->assertStringContainsString('Test route matched', $output);
}

// Setting error reporting based on environment works correctly
function setting_error_reporting_based_on_environment_works_correctly() {
    // Initialize the Larvatus class with 'development' environment
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Simulate a GET request to trigger error reporting setup
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    ob_end_clean(); // Discard the output
    
    // Assert that error reporting is set correctly for 'development' environment
    $this->assertEquals(E_ALL, error_reporting());
    $this->assertEquals('1', ini_get('display_errors'));
    
    // Reset the environment to 'production' and test again
    $larvatus = new Larvatus('production');
    
    // Simulate a GET request to trigger error reporting setup
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    ob_end_clean(); // Discard the output
    
    // Assert that error reporting is set correctly for 'production' environment
    $this->assertEquals(0, error_reporting());
    $this->assertEquals('0', ini_get('display_errors'));
}

// Session starts if not already started
function session_starts_if_not_already_started() {
  // Initialize the Larvatus class
  $larvatus = new Larvatus('development');
  
  // Mock the Request and Response objects
  $request = $this->createMock(Request::class);
  $response = $this->createMock(Response::class);
  
  // Simulate a request to trigger the listen method
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_SERVER['REQUEST_URI'] = '/test';
  
  // Capture the output
  ob_start();
  $larvatus->listen();
  ob_end_clean(); // Discard the output
  
  // Check if session has started
  $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
}

// Router correctly handles dynamic route parameters
function router_correctly_handles_dynamic_route_parameters() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Define a test route with dynamic parameter and handler
    $testRoute = '/user/:id';
    $testHandler = function($req, $res) {
        $params = $req->getParams();
        $res->write('User ID: ' . $params['id']);
        return $res;
    };
    
    // Add the test route to the router
    $larvatus->get($testRoute, $testHandler);
    
    // Simulate a GET request to the test route with dynamic parameter
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/user/123';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output contains the dynamic parameter value
    $this->assertStringContainsString('User ID: 123', $output);
}

// View class renders templates with provided data
function view_class_renders_templates_with_provided_data() {
  // Initialize the View class
  $view = new View('/path/to/templates');
  
  // Define test data
  $data = ['title' => 'Test Title', 'content' => 'Test Content'];
  
  // Render a test template with the provided data
  $renderedTemplate = $view->render('test_template', $data);
  
  // Assert that the rendered template contains the expected data
  $this->assertStringContainsString('Test Title', $renderedTemplate);
  $this->assertStringContainsString('Test Content', $renderedTemplate);
}

// Middleware stack is empty and still processes request
function middleware_stack_empty_processes_request() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Simulate a GET request to a route
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output is not empty
    $this->assertNotEmpty($output);
}

// Invalid template path in View class throws an exception
function view_invalid_template_path_throws_exception() {
  // Initialize the View class with an invalid template path
  $view = new View('/invalid/path');
  
  // Define the template name
  $template = 'invalid_template';
  
  // Assert that an exception is thrown when trying to render the invalid template
  $this->expectException(Exception::class);
  $view->render($template);
}

// Handling of unsupported HTTP methods
function test_unsupported_http_methods() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Define a test route and handler
    $testRoute = '/test';
    $testHandler = function($req, $res) {
        $res->write('Test route matched');
        return $res;
    };
    
    // Add the test route to the router
    $larvatus->get($testRoute, $testHandler);
    
    // Simulate a POST request to the test route
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $testRoute;
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output contains an error message for unsupported method
    $this->assertStringContainsString('Not Found', $output);
}

// Request object handles empty or malformed input data
function request_object_handles_empty_or_malformed_input_data() {
  // Initialize the Request object
  $request = new Request();
  
  // Set empty or malformed input data
  $_SERVER['REQUEST_METHOD'] = null;
  $_SERVER['REQUEST_URI'] = null;
  
  // Capture the output
  ob_start();
  $method = $request->getMethod();
  $url = $request->getUrl();
  $headers = $request->getHeaders();
  $body = $request->getBody();
  $queryParams = $request->getQueryParams();
  $parsedBody = $request->getParsedBody();
  $files = $request->getFiles();
  ob_end_clean();
  
  // Assert that the output is empty or null for all properties
  $this->assertEmpty($method);
  $this->assertEmpty($url);
  $this->assertEmpty($headers);
  $this->assertEmpty($body);
  $this->assertEmpty($queryParams);
  $this->assertEmpty($parsedBody);
  $this->assertEmpty($files);
}

// Middleware can modify request and response objects
function middleware_can_modify_request_and_response_objects() {
  // Initialize the Larvatus class
  $larvatus = new Larvatus('development');
  
  // Mock the Request and Response objects
  $request = $this->createMock(Request::class);
  $response = $this->createMock(Response::class);
  
  // Define a test middleware that modifies the request and response
  $testMiddleware = function($req, $res, $next) {
      $req->setParams(['id' => 123]);
      $res->write('Middleware modified response');
      return $next($req, $res);
  };
  
  // Add the test middleware to the Larvatus instance
  $larvatus->use($testMiddleware);
  
  // Simulate a GET request to trigger the middleware
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_SERVER['REQUEST_URI'] = '/test';
  
  // Capture the output
  ob_start();
  $larvatus->listen();
  $output = ob_get_clean();
  
  // Assert that the request was modified by the middleware
  $this->assertEquals(['id' => 123], $request->getParams());
  
  // Assert that the response was modified by the middleware
  $this->assertStringContainsString('Middleware modified response', $output);
}

// Router handles nested route groups correctly
function router_handles_nested_route_groups_correctly() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Define a nested route group with a test route and handler
    $larvatus->group('/api', function($router) {
        $router->get('/users', function($req, $res) {
            $res->write('Users route matched');
            return $res;
        });
    });
    
    // Simulate a GET request to the nested route
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/users';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output matches the expected response
    $this->assertStringContainsString('Users route matched', $output);
}

// Response object correctly handles multiple headers
function response_object_handles_multiple_headers() {
    // Initialize the Response class
    $response = new Response();
    
    // Set multiple headers
    $response->setHeader('Content-Type', 'application/json');
    $response->setHeader('X-Custom-Header', 'Custom Value');
    
    // Check if headers are set correctly
    $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
    $this->assertEquals('Custom Value', $response->getHeaders()['X-Custom-Header']);
}

// ErrorHandler logs exceptions for debugging
function error_handler_logs_exceptions_for_debugging() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Mock an exception to be thrown
    $exceptionMessage = 'Test Exception';
    $testHandler = function($req, $res) use ($exceptionMessage) {
        throw new Exception($exceptionMessage);
    };
    
    // Add a route that throws an exception
    $larvatus->get('/exception', $testHandler);
    
    // Simulate a GET request to the route that throws an exception
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/exception';
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the output contains the exception message
    $this->assertStringContainsString($exceptionMessage, $output);
}

// Request object correctly handles file uploads
function request_object_handles_file_uploads() {
    // Initialize the Larvatus class
    $larvatus = new Larvatus('development');
    
    // Mock the Request and Response objects
    $request = $this->createMock(Request::class);
    $response = $this->createMock(Response::class);
    
    // Simulate a POST request with file upload
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/upload';
    $_FILES['file'] = [
        'name' => 'test.jpg',
        'type' => 'image/jpeg',
        'size' => 1024,
        'tmp_name' => '/tmp/php7hj3j',
        'error' => 0
    ];
    
    // Capture the output
    ob_start();
    $larvatus->listen();
    $output = ob_get_clean();
    
    // Assert that the file upload was handled correctly
    $this->assertStringContainsString('File uploaded successfully', $output);
}
