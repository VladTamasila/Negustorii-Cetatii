<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\SesiuneRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * AuthMiddleware - verifica autentificarea pe rutele protejate.
 *
 * Citeste header-ul "Authorization: Bearer <token>", verifica token-ul in
 * tabelul `sesiuni` si, daca e valid, ataseaza utilizatorul la cerere
 * (atributul 'utilizator') ca sa-l poata folosi controllerele si verificarea
 * de rol (ACL). Daca lipseste sau e invalid, raspunde 401.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SesiuneRepository $sesiuni) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            $token = trim($m[1]);
        }

        if ($token === '') {
            return $this->eroare(401, 'NEAUTENTIFICAT', 'Lipseste token-ul de autentificare.');
        }

        $utilizator = $this->sesiuni->findUtilizatorValid($token);
        if ($utilizator === null) {
            return $this->eroare(401, 'TOKEN_INVALID', 'Token invalid sau expirat. Logheaza-te din nou.');
        }

        // Atasam utilizatorul la cerere, ca sa fie disponibil mai departe.
        $request = $request->withAttribute('utilizator', $utilizator);
        return $handler->handle($request);
    }

    private function eroare(int $status, string $cod, string $mesaj): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'cod'       => $cod,
            'mesaj'     => $mesaj,
            'status'    => $status,
            'timestamp' => date(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
