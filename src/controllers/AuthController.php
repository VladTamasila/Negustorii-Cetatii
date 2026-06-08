<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SesiuneRepository;
use App\Repositories\UtilizatorRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AuthController - autentificare (login / logout / cine sunt eu).
 *
 *   POST   /api/auth/login    - {utilizator, parola}  -> {token, rol}
 *   POST   /api/auth/logout   - (Bearer token)        -> 204
 *   GET    /api/auth/eu       - (Bearer token)        -> {utilizator, rol}
 */
final class AuthController
{
    use JsonResponseTrait;

    private const ORE_VALABILITATE = 24;

    public function __construct(
        private readonly UtilizatorRepository $utilizatori,
        private readonly SesiuneRepository $sesiuni,
    ) {}

    /** POST /api/auth/login */
    public function login(Request $request, Response $response): Response
    {
        $body       = (array) ($request->getParsedBody() ?? []);
        $utilizator = trim((string) ($body['utilizator'] ?? ''));
        $parola     = (string) ($body['parola'] ?? '');

        if ($utilizator === '' || $parola === '') {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Utilizator si parola sunt obligatorii.');
        }

        $cont = $this->utilizatori->findByNume($utilizator);
        // Acelasi mesaj si pentru user inexistent, si pentru parola gresita
        // (nu dezvaluim care din ele e gresit - bune practici de securitate).
        if ($cont === null || !password_verify($parola, $cont['parola_hash'])) {
            return $this->jsonError($response, 401, 'AUTENTIFICARE_ESUATA', 'Utilizator sau parola gresita.');
        }

        $this->sesiuni->stergeExpirate(); // mica curatenie
        $token = $this->sesiuni->create((int) $cont['id'], self::ORE_VALABILITATE);

        return $this->json($response, [
            'token'        => $token,
            'utilizator'   => $cont['utilizator'],
            'rol'          => $cont['rol'],
            'expiraInOre'  => self::ORE_VALABILITATE,
        ]);
    }

    /**
     * POST /api/auth/register - {utilizator, parola}
     *
     * Inregistrare self-service: oricine isi poate face cont, fara admin.
     * Contul primeste automat rol 'jucator' (NU admin - nu lasam pe nimeni sa-si
     * dea singur drepturi de admin). Dupa creare, il logam direct (intoarcem token).
     */
    public function register(Request $request, Response $response): Response
    {
        $body       = (array) ($request->getParsedBody() ?? []);
        $utilizator = trim((string) ($body['utilizator'] ?? ''));
        $parola     = (string) ($body['parola'] ?? '');

        if ($utilizator === '' || mb_strlen($utilizator) > 40) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Numele de utilizator e obligatoriu (max 40).');
        }
        if (mb_strlen($parola) < 6) {
            return $this->jsonError($response, 400, 'CERERE_INVALIDA', 'Parola trebuie sa aiba minim 6 caractere.');
        }
        if ($this->utilizatori->existaNume($utilizator)) {
            return $this->jsonError($response, 409, 'UTILIZATOR_EXISTA', 'Acest nume de utilizator e deja folosit.');
        }

        $id    = $this->utilizatori->create($utilizator, password_hash($parola, PASSWORD_BCRYPT), 'jucator');
        $token = $this->sesiuni->create($id, self::ORE_VALABILITATE); // login automat

        return $this->json($response, [
            'token'       => $token,
            'utilizator'  => $utilizator,
            'rol'         => 'jucator',
            'expiraInOre' => self::ORE_VALABILITATE,
        ], 201);
    }

    /** POST /api/auth/logout (necesita token valid). */
    public function logout(Request $request, Response $response): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            $this->sesiuni->delete(trim($m[1]));
        }
        return $response->withStatus(204);
    }

    /** GET /api/auth/eu - intoarce utilizatorul curent (din token). */
    public function eu(Request $request, Response $response): Response
    {
        $u = $request->getAttribute('utilizator');
        return $this->json($response, [
            'id'         => (int) $u['id'],
            'utilizator' => $u['utilizator'],
            'rol'        => $u['rol'],
        ]);
    }
}
