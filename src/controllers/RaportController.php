<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\JucatorRepository;
use App\Repositories\MutareRepository;
use App\Repositories\PartidaRepository;
use App\Services\Template;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * RaportController - reprezentarea HTML a unei partide, generata cu un motor
 * de template-ing (vezi App\Services\Template).
 *
 * Este o reprezentare alternativa (HTML) a resursei REST "partida": acelasi
 * obiect care prin /api/partide/{id} vine ca JSON, aici e randat ca pagina
 * HTML printr-un template - adica template-ing integrat in operatiile REST.
 *
 *   GET /partide/{idPartida}/rezumat
 */
final class RaportController
{
    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly JucatorRepository $jucatori,
        private readonly MutareRepository $mutari,
        private readonly Template $template,
    ) {}

    public function rezumat(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['idPartida'] ?? 0);
        $p  = $this->partide->findById($id);

        if ($p === null) {
            $response->getBody()->write('<h1>404</h1><p>Partida nu exista.</p>');
            return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        $jucatori = array_map(static fn(array $j): array => [
            'nume'      => $j['nume'],
            'culoare'   => $j['culoare'] ?? '-',
            'prestigiu' => (int) $j['prestigiu'],
        ], $this->jucatori->findByPartida($id));

        $evenimente = array_map(static fn(array $m): array => [
            'tip'   => $m['tip'],
            'mesaj' => $m['mesaj'],
        ], $this->mutari->findByPartida($id, 10));

        $html = $this->template->randeaza('rezumat_partida.html', [
            'partida' => [
                'id'     => (int) $p['id'],
                'nume'   => $p['nume'],
                'status' => $p['status'],
                'faza'   => $p['faza'],
            ],
            'jucatori'   => $jucatori,
            'evenimente' => $evenimente,
            'generatLa'  => date('Y-m-d H:i:s'),
        ]);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
