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
 * MutareController - setup + joc + istoric mutari.
 */
final class MutareController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly MutareRepository $mutari,
        private readonly JocService $joc,
    ) {}

    public function lista(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');
        if ($this->partide->findById($id) === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }
        $rows = $this->mutari->findByPartida($id);
        $items = array_map([$this, 'mapMutare'], $rows);
        return $this->json($response, ['items' => $items, 'total' => count($items)]);
    }

    /**
     * GET /api/partide/{idPartida}/notificari?dupa={ultimulId}
     *
     * Feed de notificari asincrone. Clientul trimite `dupa` = ultimul id de
     * notificare pe care l-a vazut; serverul intoarce doar evenimentele mai noi.
     * UI-ul face polling la cateva secunde si afiseaza ce au facut ceilalti
     * jucatori (mutari, alaturari, start, castig) - notificare push fara reload.
     */
    public function notificari(Request $request, Response $response, array $args): Response
    {
        $id = $this->idValid($args);
        if ($id === null) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');
        if ($this->partide->findById($id) === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }

        $dupa = (int) ($request->getQueryParams()['dupa'] ?? 0);
        $rows = $this->mutari->findNoiDupa($id, $dupa);

        $notificari = array_map(static function (array $r): array {
            return [
                'id'        => (int) $r['id'],
                'tip'       => $r['tip'],
                'mesaj'     => $r['mesaj'],
                'jucator'   => $r['jucator_nume'],
                'culoare'   => $r['jucator_culoare'] ?? null,
                'createdAt' => $r['created_at'],
            ];
        }, $rows);

        // ultimulId = cel mai mare id trimis; clientul il va folosi la urmatorul poll.
        $ultimulId = empty($notificari) ? $dupa : end($notificari)['id'];

        return $this->json($response, [
            'notificari' => $notificari,
            'ultimulId'  => $ultimulId,
        ]);
    }

    /** GET /api/partide/{idPartida}/mutari/{idMutare} */
    public function detalii(Request $request, Response $response, array $args): Response
    {
        $idPartida = $this->idValid($args);
        $idMutare  = (int) ($args['idMutare'] ?? 0);
        if ($idPartida === null || $idMutare < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'ID-uri invalide.');
        }
        $row = $this->mutari->findById($idMutare);
        if ($row === null || (int) $row['partida_id'] !== $idPartida) {
            return $this->jsonError($response, 404, 'MUTARE_INEXISTENTA', 'Mutarea nu apartine acestei partide.');
        }
        return $this->json($response, $this->mapMutare($row));
    }

    private function mapMutare(array $r): array
    {
        return [
            'id'        => (int) $r['id'],
            'jucator'   => ['id' => (int) $r['jucator_id'], 'nume' => $r['jucator_nume']],
            'tip'       => $r['tip'],
            'mesaj'     => $r['mesaj'],
            'runda'     => (int) $r['runda'],
            'payload'   => json_decode((string) $r['payload_json'], true),
            'createdAt' => $r['created_at'],
        ];
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
