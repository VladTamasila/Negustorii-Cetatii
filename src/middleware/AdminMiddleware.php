<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * AdminMiddleware - ACL: lasa sa treaca doar utilizatorii cu rol 'admin'.
 *
 * Se pune DUPA AuthMiddleware (care a atasat deja 'utilizator' la cerere).
 * Daca rolul nu e admin, raspunde 403 (autentificat, dar fara drepturi).
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $utilizator = $request->getAttribute('utilizator');

        if (!is_array($utilizator) || ($utilizator['rol'] ?? '') !== 'admin') {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'cod'       => 'INTERZIS',
                'mesaj'     => 'Aceasta operatie necesita rol de administrator.',
                'status'    => 403,
                'timestamp' => date(DATE_ATOM),
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        return $handler->handle($request);
    }
}
