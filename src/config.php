<?php
return function($app) {
    $twigView = new \Slim\Views\Twig();
    $twigView->parserOptions = ['autoescape' => false];

    $app->config('templates.path', dirname(__DIR__) . '/templates');
    $view = $app->view($twigView);
    $view->parserExtensions = [new \Slim\Views\TwigExtension()];
};
