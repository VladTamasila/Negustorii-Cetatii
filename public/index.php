<?php
declare(strict_types=1);

/**
 * Negustorii Cetatii - Punct de intrare
 *
 * Toate request-urile (HTTP) sunt rutate aici prin .htaccess.
 */

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// 1) Construim containerul DI cu definitiile noastre
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/../config/dependencies.php');
$container = $containerBuilder->build();

// 2) Atasam containerul la Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// 3) Middleware-uri globale
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware - in dev afiseaza detalii, in prod le ascunde
$settings = $container->get('settings');
$errorMiddleware = $app->addErrorMiddleware(
    (bool) $settings['app']['debug'],
    true,
    true
);

// Handler custom pentru erori - raspunde JSON pe rutele /api/*
$errorMiddleware->setDefaultErrorHandler(function (
    Psr\Http\Message\ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails
) use ($app): Psr\Http\Message\ResponseInterface {
    $response = $app->getResponseFactory()->createResponse();

    $status = 500;
    $cod    = 'EROARE_INTERNA';
    $mesaj  = 'A aparut o eroare interna. Incearca din nou mai tarziu.';

    if ($exception instanceof Slim\Exception\HttpNotFoundException) {
        $status = 404;
        $cod    = 'RUTA_INEXISTENTA';
        $mesaj  = 'Endpoint-ul nu exista.';
    } elseif ($exception instanceof Slim\Exception\HttpMethodNotAllowedException) {
        $status = 405;
        $cod    = 'METODA_NEPERMISA';
        $mesaj  = 'Metoda HTTP nu este permisa pentru aceasta ruta.';
    }

    $payload = [
        'cod'       => $cod,
        'mesaj'     => $mesaj,
        'status'    => $status,
        'timestamp' => date(DATE_ATOM),
    ];
    if ($displayErrorDetails) {
        $payload['debug'] = [
            'exception' => get_class($exception),
            'message'   => $exception->getMessage(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
        ];
    }

    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
});

// 4) Inregistram rutele
$registerRoutes = require __DIR__ . '/../src/routes.php';
$registerRoutes($app);

// 5) Pornim aplicatia
$app->run();
