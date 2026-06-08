<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ChatRepository;
use App\Repositories\PartidaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * ChatController - componenta sociala: chat-ul dintr-o partida.
 *
 *   GET  /api/partide/{idPartida}/chat?dupa={ultimulId}  - mesajele noi (polling)
 *   POST /api/partide/{idPartida}/chat                   - trimite un mesaj
 */
final class ChatController
{
    use JsonResponseTrait;

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly ChatRepository $chat,
    ) {}

    /** GET - mesajele noi de la ultimul id vazut (la fel ca feed-ul de notificari). */
    public function lista(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['idPartida'] ?? 0);
        if ($id < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');
        if ($this->partide->findById($id) === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }

        $dupa = (int) ($request->getQueryParams()['dupa'] ?? 0);
        $rows = $this->chat->listaDupa($id, $dupa);

        $mesaje = array_map(static fn(array $r): array => [
            'id'        => (int) $r['id'],
            'autor'     => $r['autor'],
            'text'      => $r['text'],
            'createdAt' => $r['created_at'],
        ], $rows);

        $ultimulId = empty($mesaje) ? $dupa : end($mesaje)['id'];
        return $this->json($response, ['mesaje' => $mesaje, 'ultimulId' => $ultimulId]);
    }

    /** POST - trimite un mesaj nou in chat-ul partidei. */
    public function trimite(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['idPartida'] ?? 0);
        if ($id < 1) return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'idPartida invalid.');
        if ($this->partide->findById($id) === null) {
            return $this->jsonError($response, 404, 'PARTIDA_INEXISTENTA', 'Partida nu exista.');
        }

        // Autorul NU mai vine de la client - il luam din contul logat (token),
        // ca nimeni sa nu poata scrie sub numele altcuiva. Ruta e protejata de
        // AuthMiddleware, deci atributul 'utilizator' exista mereu aici.
        $u     = $request->getAttribute('utilizator');
        $autor = is_array($u) ? (string) $u['utilizator'] : 'Anonim';

        $body = (array) ($request->getParsedBody() ?? []);
        $text = trim((string) ($body['text'] ?? ''));

        if ($text === '' || mb_strlen($text) > 500) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA',
                'Mesajul este obligatoriu (1-500 caractere).');
        }

        $idMesaj = $this->chat->create($id, $autor, $text);
        return $this->json($response, [
            'id'    => $idMesaj,
            'autor' => $autor,
            'text'  => $text,
        ], 201);
    }
}
