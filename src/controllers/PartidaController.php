<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JucatorRepository;
use App\Repositories\PartidaRepository;
use App\Services\JocService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * PartidaController
 *
 *   - GET  /api/partide
 *   - POST /api/partide
 *   - GET  /api/partide/{idPartida}
 *   - POST /api/partide/{idPartida}/start
 */
final class PartidaController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly JucatorRepository $jucatori,
        private readonly JocService $joc,
    ) {
    }

    public function lista(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $status = $this->validateStatus($params['status'] ?? null);
        if ($status === false) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'Parametrul status este invalid.',
                ['valori valide: in_asteptare, activa, finalizata, arhivata']);
        }

        $pagina = max(1, (int) ($params['pagina'] ?? 1));
        $dim    = max(1, min(50, (int) ($params['dimensiunePagina'] ?? 10)));

        return $this->json($response, $this->partide->findAll($status, $pagina, $dim));
    }

    public function creeaza(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);

        $nume = trim((string) ($body['nume'] ?? ''));
        if ($nume === '' || mb_strlen($nume) > 80) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'Numele partidei este obligatoriu (1-80 caractere).');
        }
        $jmax  = max(2, min(4, (int) ($body['jucatoriMaxim'] ?? 4)));
        $vmax  = max(5, min(20, (int) ($body['punctajCastig'] ?? 10)));

        $id = $this->partide->create($nume, $jmax, $vmax);
        return $this->json($response,
            $this->mapDetalii($this->partide->findById($id), []),
            201
        );
    }

    public function detalii(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['idPartida'] ?? 0);
        if ($id < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'idPartida invalid.');
        }

        $partida = $this->partide->findById($id);
        if ($partida === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA',
                'Partida nu exista.');
        }

        return $this->json($response,
            $this->mapDetalii($partida, $this->jucatori->findByPartida($id))
        );
    }

    public function porneste(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['idPartida'] ?? 0);
        if ($id < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'idPartida invalid.');
        }

        try {
            $partida = $this->joc->pornestePartida($id);
        } catch (RuntimeException $e) {
            return $this->jsonError($response, 400, 'STARE_INVALIDA', $e->getMessage());
        }

        return $this->json($response,
            $this->mapDetalii($partida, $this->jucatori->findByPartida($id))
        );
    }

    private function mapDetalii(?array $p, array $jucatori): array
    {
        if ($p === null) return [];
        return [
            'id'             => (int) $p['id'],
            'nume'           => $p['nume'],
            'status'         => $p['status'],
            'faza'           => $p['faza'],
            'rundaSetup'     => (int) $p['runda_setup'],
            'pasSetup'       => $p['pas_setup'],
            'turaCurenta'    => (int) $p['tura_curenta'],
            'jucatoriMaxim'  => (int) $p['jucatori_maxim'],
            'jucatorActivId' => $p['jucator_activ_id'] !== null ? (int) $p['jucator_activ_id'] : null,
            'punctajCastig'  => (int) $p['punctaj_castig'],
            'castigatorId'   => $p['castigator_id'] !== null ? (int) $p['castigator_id'] : null,
            'jucatori'       => array_map(static fn(array $j): array => [
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
            ], $jucatori),
        ];
    }

    private function validateStatus(?string $s): string|null|false
    {
        if ($s === null || $s === '') return null;
        return in_array($s, ['in_asteptare','activa','finalizata','arhivata'], true) ? $s : false;
    }
}
