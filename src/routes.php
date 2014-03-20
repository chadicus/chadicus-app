<?php
return function(\Slim\Slim $app) {
    $app->get('/', function() use($app) {
        $app->render('home.html', ['page' => 'home']);
    })->name('home');

    $app->get('/hulk', function() use ($app) {

        $client = new \Chadicus\Marvel\Api\Client(
            getenv('MARVEL_PRIVATE_KEY'),
            getenv('MARVEL_PUBLIC_KEY'),
            new \Chadicus\Marvel\Api\CurlAdapter()
        );

        $response = $client->search('comics', ['characters' => 1009351]);

        $app->render('hulk.html', ['comics' => $response->getBody()['data']['results']]);
    })->name('hulk');

    $app->get('/inventory', function() use($app) {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $inventory = [
            [
                'name' => '',
            ],
        ];

        $items = array_slice($inventory, $offset, $limit);
        $result = [
            'total' => count($inventory),
            'limit' => count($items),
            'offset' => $offset,
            'items' => $inventory,
        ];

        header('Content-Type: application/json');
        echo json_encode($result);
    })->name('inventory');
};
