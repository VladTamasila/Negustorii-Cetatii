<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JucatorRepository;
use App\Repositories\PartidaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * JucatorController
 *   - GET  /api/partide/{idPartida}/jucatori
 *   - POST /api/partide/{idPartida}/jucatori
 */
final class JucatorController
{
    use JsonResponseTrait;

    /** Culorile permise pentru un jucator (corespund celor afisate in UI). */
    private const CULORI_PERMISE = ['albastru', 'rosu', 'verde', 'galben'];

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly JucatorRepository $jucatori,
    ) {
    }

    public function lista(Request $request, Response $response, array $args): Response
    {
        $idPartida = (int) ($args['idPartida'] ?? 0);
        if ($idPartida < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');
        if ($this->partide->findById($idPartida) === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }

        $items = array_map([$this, 'mapJucator'], $this->jucatori->findByPartida($idPartida));
        return $this->json($response, ['items' => $items, 'total' => count($items)]);
    }

    public function adauga(Request $request, Response $response, array $args): Response
    {
        $idPartida = (int) ($args['idPartida'] ?? 0);
        if ($idPartida < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $partida = $this->partide->findById($idPartida);
        if ($partida === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }
        if ($partida['status'] !== 'in_asteptare') {
            return $this->jsonError($response, 400, 'STARE_INVALIDA',
                'Jucatorii pot fi adaugati doar in faza de asteptare.');
        }
        if ($this->jucatori->countByPartida($idPartida) >= (int) $partida['jucatori_maxim']) {
            return $this->jsonError($response, 400, 'PARTIDA_PLINA',
                'Partida are deja numarul maxim de jucatori.');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $nume = trim((string) ($body['nume'] ?? ''));
        if ($nume === '' || mb_strlen($nume) > 40) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'Numele jucatorului este obligatoriu (1-40 caractere).');
        }
        $culoare = isset($body['culoare']) ? trim((string) $body['culoare']) : null;
        if ($culoare === '') $culoare = null;

        // Validam ca, daca s-a trimis o culoare, sa fie din lista permisa
        if ($culoare !== null && !in_array($culoare, self::CULORI_PERMISE, true)) {
            return $this->jsonError($response, 400, 'CULOARE_INVALIDA',
                'Culoarea trebuie sa fie una din: ' . implode(', ', self::CULORI_PERMISE) . '.');
        }

        // Regula de unicitate: in aceeasi partida, doi jucatori NU pot avea aceeasi culoare
        if ($culoare !== null && $this->jucatori->existaCuloareInPartida($idPartida, $culoare)) {
            return $this->jsonError($response, 409, 'CULOARE_DEJA_FOLOSITA',
                "Culoarea '$culoare' este deja folosita de un alt jucator din aceasta partida.");
        }

        $id = $this->jucatori->create($idPartida, $nume, $culoare);
        return $this->json($response, $this->mapJucator($this->jucatori->findById($id)), 201);
    }

    private function mapJucator(array $j): array
    {
        return [
            'id'        => (int) $j['id'],
            'nume'      => $j['nume'],
            'culoare'   => $j['culoare'],
            'ordine'    => (int) $j['ordine'],
            'prestigiu' => (int) $j['prestigiu'],
            'lemn'      => (int) $j['lemn'],
            'piatra'    => (int) $j['piatra'],
            'aur'       => (int) $j['aur'],
            'grau'      => (int) $j['grau'],
            'lana'      => (int) $j['lana'],
        ];
    }
}
