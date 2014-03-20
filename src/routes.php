<?php
use DominionEnterprises\Filterer;
use DominionEnterprises\Util;
use DominionEnterprises\Util\Arrays;

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

    $app->get('/books', function() use($app) {
        $result = [];
        try {
            $filters = [
                'offset' => [['uint']],
                'limit' => [['uint']],
                'genre' => [['string']],
                'sort' => [['string'], ['strtolower'], ['in', ['genre', 'title', 'price']]],
            ];

            list($success, $filteredGet, $error) = Filterer::filter($filters, $_GET);
            Util::ensure(true, $success, $error);

            $allBooks = require_once __DIR__ . '/books.php';

            $genre = Arrays::get($filteredGet, 'genre');
            if ($genre !== null) {
                $allBooks = Arrays::where($books, ['genre' => $genre]);
            }

            $sort = Arrays::get($filteredGet, 'sort');
            if ($sort !== null) {
                usort($allBooks, function($a, $b) use ($sort) {
                    if ($sort === 'price') {
                        if ($a['price'] == $b['price']) {
                            return 0;
                        }

                        return ($a['price'] < $b['price']) ? -1 : 1;
                    }

                    return strcmp($a[$sort], $b[$sort]);
                });
            }

            $view = [];
            $offset = Arrays::get($filteredGet, 'offset', 0);
            $limit = Arrays::get($filteredGet, 'limit', 5);
            foreach (array_slice($allBooks, $offset, $limit) as $book) {
                $key = count($view);
                $view[$key] = [];
                Arrays::copyIfKeysExist($book, $view[$key], ['url', 'id', 'title', 'genre', 'price']);
            }

            $result = [
                'offset' => $offset,
                'limit' => count($view),
                'total' => count($allBooks),
                'books' => $view,
            ];
        } catch (\Exception $e) {
            $app->response()->status(400);
            $result = ['error' => $e->getMessage()];
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    })->name('books');

    $app->get('/books/:id', function($id) use($app) {
        $result = null;
        $books = require_once __DIR__ . '/books.php';
        if (!array_key_exists($id, $books)) {
            $app->response()->status(404);
            $result = ['error' => "Book with id '{$id}' was not found"];
        } else {
            $result = $books[$id];
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    })->name('book');
};
