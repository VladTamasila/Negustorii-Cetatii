<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JucatorRepository;
use App\Repositories\MutareRepository;
use App\Repositories\PartidaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * JucatorController
 *   - GET    /api/partide/{idPartida}/jucatori
 *   - POST   /api/partide/{idPartida}/jucatori
 *   - GET    /api/partide/{idPartida}/jucatori/{idJucator}
 *   - PUT    /api/partide/{idPartida}/jucatori/{idJucator}   (nume / culoare, doar in lobby)
 *   - DELETE /api/partide/{idPartida}/jucatori/{idJucator}   (doar in lobby)
 */
final class JucatorController
{
    use JsonResponseTrait;

    private const CULORI_PERMISE = ['albastru', 'rosu', 'verde', 'galben'];

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly JucatorRepository $jucatori,
        private readonly MutareRepository $mutari,
    ) {}

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
        if ($partida === null) return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        if ($partida['status'] !== 'in_asteptare') {
            return $this->jsonError($response, 400, 'STARE_INVALIDA',
                'Jucatorii pot fi adaugati doar in faza de asteptare.');
        }
        if ($this->jucatori->countByPartida($idPartida) >= (int) $partida['jucatori_maxim']) {
            return $this->jsonError($response, 400, 'PARTIDA_PLINA', 'Partida are deja numarul maxim de jucatori.');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $nume = trim((string) ($body['nume'] ?? ''));
        if ($nume === '' || mb_strlen($nume) > 40) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'Numele jucatorului este obligatoriu (1-40 caractere).');
        }
        $culoare = isset($body['culoare']) ? trim((string) $body['culoare']) : null;
        if ($culoare === '') $culoare = null;

        if ($culoare !== null && !in_array($culoare, self::CULORI_PERMISE, true)) {
            return $this->jsonError($response, 400, 'CULOARE_INVALIDA',
                'Culoarea trebuie sa fie una din: ' . implode(', ', self::CULORI_PERMISE) . '.');
        }
        if ($culoare !== null && $this->jucatori->existaCuloareInPartida($idPartida, $culoare)) {
            return $this->jsonError($response, 409, 'CULOARE_DEJA_FOLOSITA',
                "Culoarea '$culoare' este deja folosita de un alt jucator din aceasta partida.");
        }

        $id = $this->jucatori->create($idPartida, $nume, $culoare);

        // Notificare async: anunta ceilalti ca un jucator nou s-a alaturat in lobby.
        $this->mutari->create($idPartida, $id, 'alaturare', ['idJucator' => $id],
            sprintf('%s s-a alaturat partidei.', $nume), 0);

        return $this->json($response, $this->mapJucator($this->jucatori->findById($id)), 201);
    }

    /** GET /api/partide/{idPartida}/jucatori/{idJucator} */
    public function detalii(Request $request, Response $response, array $args): Response
    {
        $idPartida = (int) ($args['idPartida'] ?? 0);
        $idJucator = (int) ($args['idJucator'] ?? 0);
        if ($idPartida < 1 || $idJucator < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'ID-uri invalide.');
        }
        $j = $this->jucatori->findById($idJucator);
        if ($j === null || (int)$j['partida_id'] !== $idPartida) {
            return $this->jsonError($response, 404, 'JUCATOR_INEXISTENT', 'Jucatorul nu apartine acestei partide.');
        }
        return $this->json($response, $this->mapJucator($j));
    }

    /** PUT /api/partide/{idPartida}/jucatori/{idJucator} - doar in lobby. */
    public function actualizeaza(Request $request, Response $response, array $args): Response
    {
        $idPartida = (int) ($args['idPartida'] ?? 0);
        $idJucator = (int) ($args['idJucator'] ?? 0);
        if ($idPartida < 1 || $idJucator < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'ID-uri invalide.');
        }

        $partida = $this->partide->findById($idPartida);
        if ($partida === null) return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        if ($partida['faza'] !== 'in_asteptare') {
            return $this->jsonError($response, 409, 'CONFLICT_STARE',
                'Profilul jucatorului poate fi modificat doar in lobby.');
        }
        $j = $this->jucatori->findById($idJucator);
        if ($j === null || (int)$j['partida_id'] !== $idPartida) {
            return $this->jsonError($response, 404, 'JUCATOR_INEXISTENT', 'Jucatorul nu apartine acestei partide.');
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $nume = trim((string) ($body['nume'] ?? $j['nume']));
        if ($nume === '' || mb_strlen($nume) > 40) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'Numele jucatorului este obligatoriu (1-40 caractere).');
        }
        $culoare = array_key_exists('culoare', $body)
            ? ($body['culoare'] === '' || $body['culoare'] === null ? null : trim((string) $body['culoare']))
            : $j['culoare'];

        if ($culoare !== null && !in_array($culoare, self::CULORI_PERMISE, true)) {
            return $this->jsonError($response, 400, 'CULOARE_INVALIDA',
                'Culoarea trebuie sa fie una din: ' . implode(', ', self::CULORI_PERMISE) . '.');
        }
        if ($culoare !== null && $this->jucatori->existaCuloareInPartidaExceptand($idPartida, $culoare, $idJucator)) {
            return $this->jsonError($response, 409, 'CULOARE_DEJA_FOLOSITA',
                "Culoarea '$culoare' este deja folosita de un alt jucator.");
        }

        $this->jucatori->actualizeazaProfil($idJucator, $nume, $culoare);
        return $this->json($response, $this->mapJucator($this->jucatori->findById($idJucator)));
    }

    /** DELETE /api/partide/{idPartida}/jucatori/{idJucator} - doar in lobby. */
    public function elimina(Request $request, Response $response, array $args): Response
    {
        $idPartida = (int) ($args['idPartida'] ?? 0);
        $idJucator = (int) ($args['idJucator'] ?? 0);
        if ($idPartida < 1 || $idJucator < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'ID-uri invalide.');
        }
        $partida = $this->partide->findById($idPartida);
        if ($partida === null) return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        if ($partida['faza'] !== 'in_asteptare') {
            return $this->jsonError($response, 409, 'CONFLICT_STARE',
                'Jucatorii nu mai pot fi eliminati dupa startul partidei.');
        }
        $j = $this->jucatori->findById($idJucator);
        if ($j === null || (int)$j['partida_id'] !== $idPartida) {
            return $this->jsonError($response, 404, 'JUCATOR_INEXISTENT', 'Jucatorul nu apartine acestei partide.');
        }
        $this->jucatori->delete($idJucator);
        return $response->withStatus(204);
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
