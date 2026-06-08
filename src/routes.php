<?php
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\HartaController;
use App\Controllers\JucatorController;
use App\Controllers\MutareController;
use App\Controllers\PartidaController;
use App\Controllers\RaportController;
use App\Controllers\SoapController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\JsonMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Definirea rutelor aplicatiei.
 *
 * /api/* - JSON, gestionate de controllere.
 * /      - pagina HTML (UI-ul jocului).
 * /admin - panou de administrare (login + ACL).
 * /docs  - Swagger UI pentru testare interactiva.
 */
return function (App $app): void {

    $app->group('/api', function (RouteCollectorProxy $api): void {

        // Healthcheck
        $api->get('/ping', function ($request, $response) {
            $response->getBody()->write(json_encode(['ok' => true, 'time' => date(DATE_ATOM)]));
            return $response;
        });

        // ---- Autentificare ----
        // login e public; logout si "eu" cer un token valid (AuthMiddleware).
        $api->post('/auth/register', [AuthController::class, 'register']);
        $api->post('/auth/login',  [AuthController::class, 'login']);
        $api->post('/auth/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
        $api->get('/auth/eu',      [AuthController::class, 'eu'])->add(AuthMiddleware::class);

        // ---- Zona de administrare (ACL) ----
        // Grup protejat: intai AuthMiddleware (cine esti), apoi AdminMiddleware
        // (ai voie doar daca rolul e 'admin'). In Slim, ultimul .add() ruleaza primul.
        $api->group('/admin', function (RouteCollectorProxy $admin): void {
            $admin->get('/statistici',          [AdminController::class, 'statistici']);
            $admin->get('/utilizatori',         [AdminController::class, 'listaUtilizatori']);
            $admin->post('/utilizatori',        [AdminController::class, 'creeazaUtilizator']);
            $admin->put('/utilizatori/{id}/parola', [AdminController::class, 'reseteazaParola']);
            $admin->delete('/utilizatori/{id}', [AdminController::class, 'stergeUtilizator']);
        })->add(AdminMiddleware::class)->add(AuthMiddleware::class);

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

        // Notificari asincrone (feed de evenimente pentru polling din UI)
        $api->get('/partide/{idPartida}/notificari', [MutareController::class, 'notificari']);

        // Chat (componenta sociala) - mesaje intre jucatori in cadrul unei partide
        $api->get('/partide/{idPartida}/chat',  [ChatController::class, 'lista']);
        // Trimiterea unui mesaj cere autentificare: autorul = contul logat.
        $api->post('/partide/{idPartida}/chat', [ChatController::class, 'trimite'])->add(AuthMiddleware::class);

        // Mutari in faza de joc
        $api->get('/partide/{idPartida}/mutari',            [MutareController::class, 'lista']);
        $api->get('/partide/{idPartida}/mutari/{idMutare}', [MutareController::class, 'detalii']);
        $api->post('/partide/{idPartida}/mutari/zar',       [MutareController::class, 'aruncaZarul']);
        $api->post('/partide/{idPartida}/mutari/asezare',   [MutareController::class, 'asezare']);
        $api->post('/partide/{idPartida}/mutari/drum',      [MutareController::class, 'drum']);
        $api->post('/partide/{idPartida}/mutari/cetate',    [MutareController::class, 'cetate']);
        $api->post('/partide/{idPartida}/mutari/paseaza',   [MutareController::class, 'paseaza']);

    })->add(JsonMiddleware::class);

    // Favicon - intoarce 204 No Content ca sa nu mai apara 404 in loguri.
    $app->get('/favicon.ico', function ($request, $response) {
        return $response->withStatus(204);
    });

    // UI - serveste pagina HTML
    $app->get('/', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/joc.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Panou de administrare - pagina HTML cu login + management (consuma /api/admin/*)
    $app->get('/admin', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/admin.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // Rezumat HTML al unei partide, generat cu motorul de template-ing
    // (reprezentare alternativa, HTML, a resursei REST "partida").
    $app->get('/partide/{idPartida}/rezumat', [RaportController::class, 'rezumat']);

    // SOAP - protocol alternativ (in afara grupului /api ca sa nu fie fortat JSON).
    $app->get('/soap',  [SoapController::class, 'info']);
    $app->post('/soap', [SoapController::class, 'server']);

    // Swagger UI - pagina interactiva de documentatie (testare GET/POST/PUT/DELETE)
    $app->get('/docs', function ($request, $response) {
        $html = file_get_contents(__DIR__ . '/../public/docs.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    // OpenAPI - serveste docs/pw.yaml. Folosit de Swagger UI la /docs.
    // NU folosim ".yaml" in URL si nici grupul /api/* (PHP CLI server intercepteaza
    // extensiile cunoscute, iar JsonMiddleware ar suprascrie Content-Type-ul YAML).
    $app->get('/openapi', function ($request, $response) {
        $yaml = file_get_contents(__DIR__ . '/../docs/pw.yaml');
        $response->getBody()->write($yaml);
        return $response
            ->withHeader('Content-Type', 'application/x-yaml; charset=utf-8')
            ->withHeader('Access-Control-Allow-Origin', '*');
    });
};
