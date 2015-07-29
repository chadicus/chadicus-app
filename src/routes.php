<?php
use DominionEnterprises\Filterer;
use DominionEnterprises\Util;
use DominionEnterprises\Util\Arrays;

return function(\Slim\Slim $app) {
    $app->get('/', function() use($app) {
        $projects = require_once __DIR__ . '/projects.php';
        $app->render('home.html', ['page' => 'home', 'projects' => $projects]);
    })->name('home');

    $app->get('/resume', function() use ($app) {
        $body = file_get_contents(__DIR__ . '/../data/resume.pdf');
        $app->response->headers->set('Content-Type', "application/pdf");
        $app->response->headers->set('Pragma', "public");
        $app->response->headers->set('Content-disposition:', 'attachment; filename=chad_gray_resume.pdf');
        $app->response->headers->set('Content-Transfer-Encoding', 'binary');
        $app->response->headers->set('Content-Length', strlen($body));
        $app->response->setBody($body);
    });

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
                $allBooks = Arrays::where($allBooks, ['genre' => $genre]);
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

        $app->contentType('application/json');
        $app->response->setBody(json_encode($result));
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

        $app->contentType('application/json');
        $app->response->setBody(json_encode($result));
    })->name('book');

    $app->get('/random-title', function () use ($app) {
        try {
            $filters = [
                'startsWith' => [['string', false, 1, 1], ['strtolower']],
                'limit' => [['uint']],
                'key' => ['required' => true,  ['string']],
            ];

            list($success, $filteredGet, $error) = Filterer::filter($filters, $_GET);
            Util::ensure(true, $success, $error);

            Util::ensure(getenv('CHADICUS_API_KEY'), $filteredGet['key'], 'http', ['Forbidden', 401]);

            $letters = range('a', 'z');

            $letter = Arrays::get($filteredGet, 'startsWith', $letters[array_rand($letters)]);
            $limit = Arrays::get($filteredGet, 'limit', 1);

            $nouns = require_once __DIR__ . '/nouns.php';
            $adjectives = require_once __DIR__ . '/adjectives.php';

            $result = [];
            for ($i = 0; $i < $limit; $i++) {
                $noun = $nouns[array_rand(preg_grep("/^{$letter}/", $nouns))];
                $adjective = $adjectives[array_rand(preg_grep("/^{$letter}/", $adjectives))];
                $result[] = ucwords("{$adjective} {$noun}");
            }
        } catch (\Exception $e) {
            $app->response()->status(400);
            $result = ['error' => $e->getMessage()];
        }

        $app->contentType('application/json');
        $app->response->setBody(json_encode($result));
    });
};
