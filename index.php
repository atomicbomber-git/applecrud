<?php
require __DIR__ . '/vendor/autoload.php';

use Slim\App as Slim;

session_start();

$app = new Slim([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

include './src/App/set_up_db_connection.php';
include './src/App/set_up_dependencies.php';
include './src/App/set_up_routes.php';

$app->get("/hello", function ($req, $res) {
	return "Jumbo";
});

$app->run();