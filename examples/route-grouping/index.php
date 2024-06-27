<?php

require '../Larvatus.php';

$app = new Larvatus('development', [
    'host' => 'localhost',
    'dbname' => 'your_db_name',
    'username' => 'your_db_user',
    'password' => 'your_db_password'
]);

$app->group('/api', function() use ($app) {
    $app->get('/users', function($request, $response) {
        $userORM = new ORM($app->pdo, 'users');
        $users = $userORM->all();
        $response->json($users);
        $response->send();
    });

    $app->get('/users/:id', function($request, $response) {
        $userORM = new ORM($app->pdo, 'users');
        $user = $userORM->find($request->getParams()['id']);
        if ($user) {
            $response->json($user);
        } else {
            $response->setStatus(404);
            $response->json(['error' => 'User not found']);
        }
        $response->send();
    });

    $app->post('/users', function($request, $response) {
        $userORM = new ORM($app->pdo, 'users');
        $data = $request->getParsedBody();
        $userId = $userORM->create($data);
        $response->setStatus(201);
        $response->json(['message' => 'User created', 'user_id' => $userId]);
        $response->send();
    });

    $app->put('/users/:id', function($request, $response) {
        $userORM = new ORM($app->pdo, 'users');
        $data = $request->getParsedBody();
        $result = $userORM->update($request->getParams()['id'], $data);
        if ($result) {
            $response->json(['message' => 'User updated']);
        } else {
            $response->setStatus(404);
            $response->json(['error' => 'User not found or not updated']);
        }
        $response->send();
    });

    $app->delete('/users/:id', function($request, $response) {
        $userORM = new ORM($app->pdo, 'users');
        $result = $userORM->delete($request->getParams()['id']);
        if ($result) {
            $response->json(['message' => 'User deleted']);
        } else {
            $response->setStatus(404);
            $response->json(['error' => 'User not found or not deleted']);
        }
        $response->send();
    });
});

$app->listen();
