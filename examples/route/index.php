<?php

require '../Larvatus.php';

$app = new Larvatus('development', [
    'host' => 'localhost',
    'dbname' => 'your_db_name',
    'username' => 'your_db_user',
    'password' => 'your_db_password'
]);

$app = new Larvatus('development', $dbConfig);

$app->get('/', function($request, $response) {
    $response->write('<h1>Welcome to the Larvatus Framework!</h1>');
    $response->send();
});

$app->listen();
