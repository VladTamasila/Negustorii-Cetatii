<?php
declare(strict_types=1);

use App\Controllers\HartaController;
use App\Controllers\JucatorController;
use App\Controllers\MutareController;
use App\Controllers\PartidaController;
use App\Middleware\JsonMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Definirea rutelor aplicatiei.
 *
 * /api/* - JSON, gestionate de controllere.
 * /      - pagina HTML (UI-ul jocului).
 */
return function (App $app): void {

    $app->group('/api', function (RouteCollectorProxy $api): void {

        // Healthcheck
        $api->get('/ping', function ($request, $response) {
            $response->getBody()->write(json_encode(['ok' => true, 'time' => date(DATE_ATOM)]));
            return $response;
        });

        // Partide
        $api->get('/partide',                    [PartidaController::class, 'lista']);
        $api->post('/partide',                   [PartidaController::class, 'creeaza']);
        $api->get('/partide/{idPartida}',        [PartidaController::class, 'detalii']);
        $api->post('/partide/{idPartida}/start', [PartidaController::class, 'porneste']);

        // Jucatori
        $api->get('/partide/{idPartida}/jucatori',  [JucatorController::class, 'lista']);
        $api->post('/partide/{idPartida}/jucatori', [JucatorController::class, 'adauga']);

        // Harta (hexagoane, varfuri, muchii, constructii)
        $api->get('/partide/{idPartida}/harta', [HartaController::class, 'harta']);

        // Setup (asezare initiala + drum initial, snake draft)
        $api->post('/partide/{idPartida}/setup/asezare', [MutareController::class, 'setupAsezare']);
        $api->post('/partide/{idPartida}/setup/drum',    [MutareController::class, 'setupDrum']);

        // Mutari in faza de joc
        $api->get('/partide/{idPartida}/mutari',          [MutareController::class, 'lista']);
        $api->post('/partide/{idPartida}/mutari/zar',     [MutareController::class, 'aruncaZarul']);
        $api->post('/partide/{idPartida}/mutari/asezare', [MutareController::class, 'asezare']);
        $api->post('/partide/{idPartida}/mutari/drum',    [MutareController::class, 'drum']);
        $api->post('/partide/{idPartida}/mutari/cetate',  [MutareController::class, 'cetate']);
        $api->post('/partide/{idPartida}/mutari/paseaza', [MutareController::class, 'paseaza']);

    })->add(JsonMiddleware::class);

    // UI - serveste pagina HTML
    $app->get('/', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/joc.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });
};
