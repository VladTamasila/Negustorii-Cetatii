<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * JsonResponseTrait
 *
 * Helperi mici, refolositi de toate controllerele:
 *   - json($data, $status)     -> raspuns JSON valid
 *   - jsonError(...)           -> raspuns de eroare structurat
 *
 * E un trait (nu o clasa de baza) ca sa pastrez controllerele "final"
 * si sa nu fortez mostenire artificial.
 */
trait JsonResponseTrait
{
    private function json(Response $response, mixed $data, int $code = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withStatus($code);
    }

    private function jsonError(
        Response $response,
        int $status,
        string $cod,
        string $mesaj,
        array $detalii = []
    ): Response {
        $payload = [
            'cod'       => $cod,
            'mesaj'     => $mesaj,
            'status'    => $status,
            'timestamp' => date(DATE_ATOM),
        ];
        if (!empty($detalii)) {
            $payload['detalii'] = $detalii;
        }
        return $this->json($response, $payload, $status);
    }
}
