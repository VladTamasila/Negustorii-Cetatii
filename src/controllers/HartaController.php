<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AsezareRepository;
use App\Repositories\DrumRepository;
use App\Repositories\HexagonRepository;
use App\Repositories\MuchieRepository;
use App\Repositories\PartidaRepository;
use App\Repositories\VarfRepository;
use App\Services\HartaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * HartaController
 *
 *   - GET /api/partide/{idPartida}/harta
 *
 * Returneaza tot ce trebuie afisat in UI: hexagoane, varfuri, muchii,
 * plus asezarile/drumurile deja plasate. Marimea hexagonului in pixeli e
 * inclusa pentru ca clientul sa stie cum sa le deseneze (poligon hexagonal).
 */
final class HartaController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly HexagonRepository $hexagoane,
        private readonly VarfRepository $varfuri,
        private readonly MuchieRepository $muchii,
        private readonly AsezareRepository $asezari,
        private readonly DrumRepository $drumuri,
    ) {
    }

    public function harta(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['idPartida'] ?? 0);
        if ($id < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');

        $partida = $this->partide->findById($id);
        if ($partida === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }

        // Hexagoane + maparea lor catre varfuri (ca sa nu apara N+1 queries)
        $hexagoane = $this->hexagoane->findByPartida($id);
        $varfuriPerHex = $this->hexagoane->maparVarfuriPerHexagon($id);

        $hexJson = array_map(static fn(array $h) => [
            'id'         => (int) $h['id'],
            'q'          => (int) $h['q'],
            'r'          => (int) $h['r'],
            'terrain'    => $h['terrain'],
            'numarToken' => $h['numar_token'] !== null ? (int) $h['numar_token'] : null,
            'varfuriIds' => array_map('intval', $varfuriPerHex[(int) $h['id']] ?? []),
        ], $hexagoane);

        $varfJson = array_map(static fn(array $v) => [
            'id' => (int) $v['id'],
            'x'  => (float) $v['x'],
            'y'  => (float) $v['y'],
        ], $this->varfuri->findByPartida($id));

        $muchieJson = array_map(static fn(array $m) => [
            'id'      => (int) $m['id'],
            'varfAId' => (int) $m['varf_a_id'],
            'varfBId' => (int) $m['varf_b_id'],
        ], $this->muchii->findByPartida($id));

        $asezareJson = array_map(static fn(array $a) => [
            'id'         => (int) $a['id'],
            'idVarf'     => (int) $a['varf_id'],
            'idJucator'  => (int) $a['jucator_id'],
            'jucator'    => $a['jucator_nume'],
            'culoare'    => $a['culoare'],
            'tip'        => $a['tip'],
        ], $this->asezari->findByPartida($id));

        $drumJson = array_map(static fn(array $d) => [
            'id'        => (int) $d['id'],
            'idMuchie'  => (int) $d['muchie_id'],
            'idJucator' => (int) $d['jucator_id'],
            'jucator'   => $d['jucator_nume'],
            'culoare'   => $d['culoare'],
        ], $this->drumuri->findByPartida($id));

        return $this->json($response, [
            'hexSize'   => HartaService::HEX_SIZE,
            'hexagoane' => $hexJson,
            'varfuri'   => $varfJson,
            'muchii'    => $muchieJson,
            'asezari'   => $asezareJson,
            'drumuri'   => $drumJson,
        ]);
    }
}
