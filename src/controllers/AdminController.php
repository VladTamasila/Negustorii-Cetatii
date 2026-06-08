<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\UtilizatorRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AdminController - operatii rezervate administratorilor (ACL).
 *
 * Toate rutele de aici sunt protejate de AuthMiddleware + AdminMiddleware,
 * deci ajungem aici doar daca utilizatorul e logat SI are rol 'admin'.
 *
 *   GET    /api/admin/statistici          - sumar global al aplicatiei
 *   GET    /api/admin/utilizatori         - lista conturilor
 *   POST   /api/admin/utilizatori         - creeaza un cont nou
 *   DELETE /api/admin/utilizatori/{id}    - sterge un cont
 */
final class AdminController
{
    use JsonResponseTrait;

    private const ROLURI = ['admin', 'jucator'];

    public function __construct(
        private readonly UtilizatorRepository $utilizatori,
        private readonly PDO $pdo,
    ) {}

    /** GET /api/admin/statistici */
    public function statistici(Request $request, Response $response): Response
    {
        $partidePeStatus = [];
        $stmt = $this->pdo->query("SELECT status, COUNT(*) AS n FROM partide GROUP BY status");
        foreach ($stmt->fetchAll() as $row) {
            $partidePeStatus[$row['status']] = (int) $row['n'];
        }

        return $this->json($response, [
            'partide' => [
                'total'    => (int) $this->pdo->query("SELECT COUNT(*) FROM partide")->fetchColumn(),
                'peStatus' => $partidePeStatus,
            ],
            'jucatori'    => (int) $this->pdo->query("SELECT COUNT(*) FROM jucatori")->fetchColumn(),
            'mutari'      => (int) $this->pdo->query("SELECT COUNT(*) FROM mutari")->fetchColumn(),
            'utilizatori' => [
                'total'    => (int) $this->pdo->query("SELECT COUNT(*) FROM utilizatori")->fetchColumn(),
                'admini'   => $this->utilizatori->countByRol('admin'),
                'jucatori' => $this->utilizatori->countByRol('jucator'),
            ],
            'generatLa' => date(DATE_ATOM),
        ]);
    }

    /** GET /api/admin/utilizatori */
    public function listaUtilizatori(Request $request, Response $response): Response
    {
        $items = array_map(static function (array $u): array {
            return [
                'id'         => (int) $u['id'],
                'utilizator' => $u['utilizator'],
                'rol'        => $u['rol'],
                'createdAt'  => $u['created_at'],
            ];
        }, $this->utilizatori->all());

        return $this->json($response, ['items' => $items, 'total' => count($items)]);
    }

    /** POST /api/admin/utilizatori - {utilizator, parola, rol} */
    public function creeazaUtilizator(Request $request, Response $response): Response
    {
        $body       = (array) ($request->getParsedBody() ?? []);
        $utilizator = trim((string) ($body['utilizator'] ?? ''));
        $parola     = (string) ($body['parola'] ?? '');
        $rol        = (string) ($body['rol'] ?? 'jucator');

        if ($utilizator === '' || mb_strlen($utilizator) > 40) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Numele de utilizator e obligatoriu (max 40).');
        }
        if (mb_strlen($parola) < 6) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Parola trebuie sa aiba minim 6 caractere.');
        }
        if (!in_array($rol, self::ROLURI, true)) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Rolul trebuie sa fie admin sau jucator.');
        }
        if ($this->utilizatori->existaNume($utilizator)) {
            return $this->jsonError($response, 409, 'UTILIZATOR_EXISTA', 'Acest nume de utilizator e deja folosit.');
        }

        $id = $this->utilizatori->create($utilizator, password_hash($parola, PASSWORD_BCRYPT), $rol);

        return $this->json($response, [
            'id'         => $id,
            'utilizator' => $utilizator,
            'rol'        => $rol,
        ], 201);
    }

    /** PUT /api/admin/utilizatori/{id}/parola - {parola} - reseteaza parola unui cont. */
    public function reseteazaParola(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'id invalid.');
        }
        if ($this->utilizatori->findById($id) === null) {
            return $this->jsonError($response, 404, 'UTILIZATOR_INEXISTENT', 'Utilizatorul nu exista.');
        }

        $body   = (array) ($request->getParsedBody() ?? []);
        $parola = (string) ($body['parola'] ?? '');
        if (mb_strlen($parola) < 6) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Parola noua trebuie sa aiba minim 6 caractere.');
        }

        $this->utilizatori->actualizeazaParola($id, password_hash($parola, PASSWORD_BCRYPT));
        return $this->json($response, ['id' => $id, 'mesaj' => 'Parola a fost resetata.']);
    }

    /** DELETE /api/admin/utilizatori/{id} */
    public function stergeUtilizator(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'id invalid.');
        }

        $tinta = $this->utilizatori->findById($id);
        if ($tinta === null) {
            return $this->jsonError($response, 404, 'UTILIZATOR_INEXISTENT', 'Utilizatorul nu exista.');
        }

        // Nu lasam adminul sa-si stearga propriul cont (ca sa nu ramana blocat).
        $eu = $request->getAttribute('utilizator');
        if ((int) $eu['id'] === $id) {
            return $this->jsonError($response, 409, 'AUTO_STERGERE_INTERZISA',
                'Nu iti poti sterge propriul cont de admin.');
        }
        // Nu lasam stergerea ultimului admin.
        if ($tinta['rol'] === 'admin' && $this->utilizatori->countByRol('admin') <= 1) {
            return $this->jsonError($response, 409, 'ULTIMUL_ADMIN',
                'Nu poti sterge ultimul cont de administrator.');
        }

        $this->utilizatori->delete($id);
        return $response->withStatus(204);
    }
}
