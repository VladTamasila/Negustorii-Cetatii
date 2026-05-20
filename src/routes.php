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
 * /docs  - Swagger UI pentru testare interactiva.
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
        $api->put('/partide/{idPartida}',        [PartidaController::class, 'actualizeaza']);
        $api->delete('/partide/{idPartida}',     [PartidaController::class, 'sterge']);
        $api->post('/partide/{idPartida}/start', [PartidaController::class, 'porneste']);

        // Jucatori
        $api->get('/partide/{idPartida}/jucatori',                [JucatorController::class, 'lista']);
        $api->post('/partide/{idPartida}/jucatori',               [JucatorController::class, 'adauga']);
        $api->get('/partide/{idPartida}/jucatori/{idJucator}',    [JucatorController::class, 'detalii']);
        $api->put('/partide/{idPartida}/jucatori/{idJucator}',    [JucatorController::class, 'actualizeaza']);
        $api->delete('/partide/{idPartida}/jucatori/{idJucator}', [JucatorController::class, 'elimina']);

        // Harta (hexagoane, varfuri, muchii, constructii)
        $api->get('/partide/{idPartida}/harta', [HartaController::class, 'harta']);

        // Setup (asezare initiala + drum initial, snake draft)
        $api->post('/partide/{idPartida}/setup/asezare', [MutareController::class, 'setupAsezare']);
        $api->post('/partide/{idPartida}/setup/drum',    [MutareController::class, 'setupDrum']);

        // Mutari in faza de joc
        $api->get('/partide/{idPartida}/mutari',             [MutareController::class, 'lista']);
        $api->get('/partide/{idPartida}/mutari/{idMutare}',  [MutareController::class, 'detalii']);
        $api->post('/partide/{idPartida}/mutari/zar',        [MutareController::class, 'aruncaZarul']);
        $api->post('/partide/{idPartida}/mutari/asezare',    [MutareController::class, 'asezare']);
        $api->post('/partide/{idPartida}/mutari/drum',       [MutareController::class, 'drum']);
        $api->post('/partide/{idPartida}/mutari/cetate',     [MutareController::class, 'cetate']);
        $api->post('/partide/{idPartida}/mutari/paseaza',    [MutareController::class, 'paseaza']);

    })->add(JsonMiddleware::class);

    // Favicon - intoarce 204 No Content ca sa nu mai apara 404 in loguri.
    // (Browserele cer automat /favicon.ico, chiar daca pagina nu il referenntiaza.)
    $app->get('/favicon.ico', function ($request, $response) {
        return $response->withStatus(204);
    });

    // UI - serveste pagina HTML
    $app->get('/', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/joc.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Swagger UI - pagina interactiva de documentatie (testare GET/POST/PUT/DELETE)
    $app->get('/docs', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/docs.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // OpenAPI - serveste docs/pw.yaml. Folosit de Swagger UI la /docs.
    //
    // IMPORTANT: NU folosim ".yaml" in URL si nici nu il punem in grupul /api/*
    //   - PHP CLI server (`php -S localhost:8080 -t public`) intercepteaza URL-urile
    //     cu extensii cunoscute (.yaml, .json, .css, ...) si raspunde direct 404
    //     daca nu gaseste fisierul - fara sa mai treaca prin index.php.
    //   - JsonMiddleware (atasat pe /api/*) ar suprascrie Content-Type-ul YAML
    //     cu application/json, ceea ce ar incurca parser-ul Swagger UI.
    $app->get('/openapi', function ($request, $response) {
        $yaml = file_get_contents(__DIR__ . '/../docs/pw.yaml');
        $response->getBody()->write($yaml);
        return $response
            ->withHeader('Content-Type', 'application/x-yaml; charset=utf-8')
            ->withHeader('Access-Control-Allow-Origin', '*');
    });
};
