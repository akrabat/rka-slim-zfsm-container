<?php
/**
 * Run the example app via the PHP built-in web server:
 *
 *      $ composer update
 *      $ cd example-app
 *      $ php -S 0.0.0.0:8888 index.php
 *
 */

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

// Instantiate the app
$settings = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$container = new RKA\ZsmSlimContainer\Container($settings);
$app = new \Slim\App($container);

// Add routes
$app->get('/', function ($request, $response, $args) {
    $url = $this->router->pathFor('hi', ['name' => 'Rob']);
    $errorUrl = $this->router->pathFor('error');
    return $response->write("Try <a href='$url'>$url</a>, or cause an <a href='$errorUrl'>error</a>!");
})->setName('home');

$app->get('/hello/{name}', function ($request, $response, $args) {
    $name = $request->getAttribute('name');
    return $response->write("<h1>Hello $name!</h1>");
})->setName('hi');

$app->get('/error', function ($request, $response, $args) {
    throw new Exception("An error has occurred");
})->setName('error');

// Run
$app->run();
