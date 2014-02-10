<?php
return function(\Slim\Slim $app) {
    $app->get('/', function() use($app) {
        $app->render('home.html', ['page' => 'home']);
    })->name('home');

    $app->get('/github', function() use($app) {
        echo file_get_contents(__DIR__ . '/github.out');
    })->name('home');

    $app->post('/github', function() use($app) {
        file_put_contents(__DIR__ . '/github.out', var_export($_POST, 1));
        $app->render('home.html', ['page' => 'home']);
    })->name('home');
};
