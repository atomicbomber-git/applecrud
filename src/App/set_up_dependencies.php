<?php
/* Expects $app, an instance of Slim\App */

$container = $app->getContainer();

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('src/View');
    $view->addExtension(new \Slim\Views\TwigExtension(
        $container['router'],
        $container['request']->getUri()
    ));

    return $view;
};