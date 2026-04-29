<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\MutareRepository;
use App\Repositories\PartidaRepository;
use App\Services\JocService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * MutareController
 *
 * Faza de setup:
 *   - POST /api/partide/{id}/setup/asezare   { idVarf }
 *   - POST /api/partide/{id}/setup/drum      { idMuchie }
 *
 * Faza de joc:
 *   - POST /api/partide/{id}/mutari/zar
 *   - POST /api/partide/{id}/mutari/asezare    { idVarf }
 *   - POST /api/partide/{id}/mutari/drum       { idMuchie }
 *   - POST /api/partide/{id}/mutari/cetate     { idAsezare }
 *   - POST /api/partide/{id}/mutari/paseaza
 *
 * Citire:
 *   - GET  /api/partide/{id}/mutari
 */
final class MutareController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly MutareRepository $mutari,
        private readonly JocService $joc,
    ) {
    }

    public function lista(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');
        if ($this->partide->findById($id) === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }

        $rows = $this->mutari->findByPartida($id);
        $items = array_map(static fn(array $r): array => [
            'id'        => (int) $r['id'],
            'jucator'   => ['id' => (int) $r['jucator_id'], 'nume' => $r['jucator_nume']],
            'tip'       => $r['tip'],
            'mesaj'     => $r['mesaj'],
            'runda'     => (int) $r['runda'],
            'payload'   => json_decode((string) $r['payload_json'], true),
            'createdAt' => $r['created_at'],
        ], $rows);

        return $this->json($response, ['items' => $items, 'total' => count($items)]);
    }

    // ----- Setup -----

    public function setupAsezare(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $body   = (array) ($request->getParsedBody() ?? []);
        $idVarf = (int) ($body['idVarf'] ?? 0);
        if ($idVarf < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idVarf obligatoriu.');

        try { $r = $this->joc->aseazaInitiala($id, $idVarf); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r, 201);
    }

    public function setupDrum(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $body     = (array) ($request->getParsedBody() ?? []);
        $idMuchie = (int) ($body['idMuchie'] ?? 0);
        if ($idMuchie < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idMuchie obligatoriu.');

        try { $r = $this->joc->construiesteDrumInitial($id, $idMuchie); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r, 201);
    }

    // ----- Joc normal -----

    public function aruncaZarul(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        try { $r = $this->joc->aruncaZarul($id); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r);
    }

    public function asezare(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $body   = (array) ($request->getParsedBody() ?? []);
        $idVarf = (int) ($body['idVarf'] ?? 0);
        if ($idVarf < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idVarf obligatoriu.');

        try { $r = $this->joc->construiesteAsezare($id, $idVarf); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r, 201);
    }

    public function drum(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $body     = (array) ($request->getParsedBody() ?? []);
        $idMuchie = (int) ($body['idMuchie'] ?? 0);
        if ($idMuchie < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idMuchie obligatoriu.');

        try { $r = $this->joc->construiesteDrum($id, $idMuchie); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r, 201);
    }

    public function cetate(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $body      = (array) ($request->getParsedBody() ?? []);
        $idAsezare = (int) ($body['idAsezare'] ?? 0);
        if ($idAsezare < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idAsezare obligatoriu.');

        try { $r = $this->joc->upgradeCetate($id, $idAsezare); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r);
    }

    public function paseaza(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        try { $r = $this->joc->paseaza($id); }
        catch (RuntimeException $e) { return $this->jsonError($response, 400, 'MUTARE_INVALIDA', $e->getMessage()); }

        return $this->json($response, $r);
    }

    private function idValid(array $args): ?int
    {
        $id = (int) ($args['idPartida'] ?? 0);
        return $id < 1 ? null : $id;
    }
}
