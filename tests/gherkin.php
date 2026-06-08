<?php
declare(strict_types=1);

/**
 * ============================================================================
 *  Runner Gherkin minimal pentru Negustorii Cetatii
 * ============================================================================
 *
 * Citeste fisierele .feature din folderul `features/` (scrise in Gherkin, in
 * limba romana) si executa pasii automat, lovind API-ul real prin Guzzle.
 *
 * De ce un runner propriu (si nu Behat)? Ca sa nu adaugam dependinte noi -
 * folosim doar Guzzle, pe care il avem deja. Fisierele .feature sunt insa
 * Gherkin standard, deci pot fi rulate si cu Behat daca se doreste.
 *
 * RULARE:
 *   1. Porneste serverul:  php -S localhost:8080 -t public
 *   2. In alt terminal:     php tests/gherkin.php
 *      (optional alt host)  php tests/gherkin.php http://localhost:8080
 *
 * Cod de iesire 0 daca toate scenariile trec, 1 daca pica vreunul
 * (util pentru integrare in CI / testare automata).
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

$baseUrl     = rtrim($argv[1] ?? (getenv('BASE_URL') ?: 'http://localhost:8080'), '/');
$featuresDir = __DIR__ . '/../features';

$client = new Client([
    'base_uri'    => $baseUrl,
    'http_errors' => false, // nu aruncam la 4xx/5xx; vrem sa verificam codul de status
    'timeout'     => 10,
    'headers'     => ['ngrok-skip-browser-warning' => 'true'],
]);

// ---------------------------------------------------------------------------
//  Mic ajutor de aserare
// ---------------------------------------------------------------------------
function asigura(bool $conditie, string $mesaj): void
{
    if (!$conditie) {
        throw new RuntimeException($mesaj);
    }
}

/** Trimite o cerere si intoarce [status, json]. Salveaza si in context. */
function cere(array &$ctx, Client $client, string $metoda, string $ruta, ?array $body = null): array
{
    $optiuni = [];
    if ($body !== null)         $optiuni['json'] = $body;
    if (!empty($ctx['_token'])) $optiuni['headers'] = ['Authorization' => 'Bearer ' . $ctx['_token']];

    $raspuns = $client->request($metoda, '/api' . $ruta, $optiuni);
    $status  = $raspuns->getStatusCode();
    $text    = (string) $raspuns->getBody();
    $json    = $text !== '' ? json_decode($text, true) : null;

    $ctx['_status'] = $status;
    $ctx['_json']   = $json;
    return [$status, $json];
}

// ---------------------------------------------------------------------------
//  Definitii de pasi: descriere (regex) -> ce face
// ---------------------------------------------------------------------------
$pasi = [];
function pas(string $regex, callable $fn): void
{
    global $pasi;
    $pasi[$regex] = $fn;
}

pas('/^API-ul (?:este pornit|ruleaza)$/', function (array &$ctx, Client $c) {
    [$st] = cere($ctx, $c, 'GET', '/ping');
    asigura($st === 200, "Serverul nu raspunde la /api/ping (status $st). E pornit?");
});

pas('/^creez o partida cu numele "([^"]+)"$/', function (array &$ctx, Client $c, string $nume) {
    [$st, $j] = cere($ctx, $c, 'POST', '/partide', ['nume' => $nume]);
    if ($st === 201 && isset($j['id'])) $ctx['idPartida'] = $j['id']; // transfer de proprietati
});

pas('/^creez o partida fara nume$/', function (array &$ctx, Client $c) {
    cere($ctx, $c, 'POST', '/partide', ['nume' => '']);
});

pas('/^adaug jucatorul "([^"]+)" cu culoarea "([^"]+)"$/', function (array &$ctx, Client $c, string $nume, string $culoare) {
    asigura(isset($ctx['idPartida']), 'Nu exista o partida creata in acest scenariu.');
    cere($ctx, $c, 'POST', "/partide/{$ctx['idPartida']}/jucatori", ['nume' => $nume, 'culoare' => $culoare]);
});

pas('/^pornesc partida$/', function (array &$ctx, Client $c) {
    asigura(isset($ctx['idPartida']), 'Nu exista o partida creata in acest scenariu.');
    cere($ctx, $c, 'POST', "/partide/{$ctx['idPartida']}/start");
});

pas('/^primesc codul de status (\d+)$/', function (array &$ctx, Client $c, string $cod) {
    $asteptat = (int) $cod;
    asigura(($ctx['_status'] ?? 0) === $asteptat,
        "Asteptam status $asteptat, dar am primit " . ($ctx['_status'] ?? 'nimic') . '.');
});

pas('/^partida are (\d+) jucatori$/', function (array &$ctx, Client $c, string $n) {
    [$st, $j] = cere($ctx, $c, 'GET', "/partide/{$ctx['idPartida']}/jucatori");
    asigura(($j['total'] ?? -1) === (int) $n, "Asteptam {$n} jucatori, dar sunt " . ($j['total'] ?? '?') . '.');
});

pas('/^faza partidei este "([^"]+)"$/', function (array &$ctx, Client $c, string $faza) {
    [$st, $j] = cere($ctx, $c, 'GET', "/partide/{$ctx['idPartida']}");
    asigura(($j['faza'] ?? '') === $faza, "Asteptam faza '$faza', dar e '" . ($j['faza'] ?? '?') . "'.");
});

pas('/^ma loghez cu utilizatorul "([^"]+)" si parola "([^"]+)"$/', function (array &$ctx, Client $c, string $u, string $p) {
    [$st, $j] = cere($ctx, $c, 'POST', '/auth/login', ['utilizator' => $u, 'parola' => $p]);
    if ($st === 200 && isset($j['token'])) $ctx['_token'] = $j['token'];
});

pas('/^primesc un token de autentificare$/', function (array &$ctx, Client $c) {
    asigura(!empty($ctx['_token']), 'Nu am primit niciun token la login.');
});

pas('/^accesez lista de utilizatori fara autentificare$/', function (array &$ctx, Client $c) {
    $tokenVechi = $ctx['_token'] ?? null;
    unset($ctx['_token']); // fortam cererea fara token
    cere($ctx, $c, 'GET', '/admin/utilizatori');
    if ($tokenVechi !== null) $ctx['_token'] = $tokenVechi;
});

pas('/^cer statisticile cu token-ul curent$/', function (array &$ctx, Client $c) {
    cere($ctx, $c, 'GET', '/admin/statistici');
});

pas('/^raspunsul contine campul "([^"]+)"$/', function (array &$ctx, Client $c, string $camp) {
    asigura(is_array($ctx['_json']) && array_key_exists($camp, $ctx['_json']),
        "Raspunsul nu contine campul '$camp'.");
});

pas('/^feed-ul de notificari contine cel putin (\d+) evenimente$/', function (array &$ctx, Client $c, string $n) {
    [$st, $j] = cere($ctx, $c, 'GET', "/partide/{$ctx['idPartida']}/notificari?dupa=0");
    $nr = is_array($j['notificari'] ?? null) ? count($j['notificari']) : 0;
    asigura($nr >= (int) $n, "Asteptam cel putin {$n} notificari, dar sunt {$nr}.");
});

// ---------------------------------------------------------------------------
//  Parser + executor de fisiere .feature
// ---------------------------------------------------------------------------

// Cuvintele cheie Gherkin (ro + en) pe care le scoatem inainte de a cauta pasul.
$cuvinteCheie = ['Date fiind', 'Dat fiind', 'Daca', 'Cand', 'Atunci', 'Si', 'Dar',
                 'Given', 'When', 'Then', 'And', 'But'];

function potrivestePas(string $textPas): array
{
    global $pasi;
    foreach ($pasi as $regex => $fn) {
        if (preg_match($regex, $textPas, $m)) {
            array_shift($m); // scoatem potrivirea completa, raman doar grupurile
            return [$fn, $m];
        }
    }
    return [null, []];
}

function ruleazaScenariu(string $titlu, array $pasiText, Client $client): bool
{
    global $cuvinteCheie;
    $ctx = [];
    echo "  Scenariu: $titlu\n";
    foreach ($pasiText as $linie) {
        $textPas = $linie;
        foreach ($cuvinteCheie as $cc) {
            if (stripos($textPas, $cc . ' ') === 0) { $textPas = trim(substr($textPas, strlen($cc))); break; }
        }
        [$fn, $args] = potrivestePas($textPas);
        if ($fn === null) {
            echo "    ? PAS NEDEFINIT: $linie\n";
            return false;
        }
        try {
            $fn($ctx, $client, ...$args);
            echo "    + $linie\n";
        } catch (Throwable $e) {
            echo "    x $linie\n      -> " . $e->getMessage() . "\n";
            return false;
        }
    }
    return true;
}

$fisiere = glob($featuresDir . '/*.feature') ?: [];
if (empty($fisiere)) {
    fwrite(STDERR, "Nu am gasit fisiere .feature in $featuresDir\n");
    exit(1);
}

$totalScenarii = 0;
$scenariiOk    = 0;

foreach ($fisiere as $fisier) {
    $linii = file($fisier, FILE_IGNORE_NEW_LINES);
    $titluScenariu = null;
    $pasiCurent = [];
    $background = [];
    $inBackground = false;

    $finalizeazaScenariu = function () use (&$titluScenariu, &$pasiCurent, &$background, $client, &$totalScenarii, &$scenariiOk) {
        if ($titluScenariu === null) return;
        $totalScenarii++;
        if (ruleazaScenariu($titluScenariu, array_merge($background, $pasiCurent), $client)) {
            $scenariiOk++;
        }
        $titluScenariu = null;
        $pasiCurent = [];
    };

    echo "Functionalitate: " . basename($fisier) . "\n";
    foreach ($linii as $linie) {
        $t = trim($linie);
        if ($t === '' || str_starts_with($t, '#')) continue;

        if (preg_match('/^(Functionalitate|Feature):/i', $t)) { continue; }
        if (preg_match('/^(Context|Background):/i', $t)) { $finalizeazaScenariu(); $inBackground = true; $background = []; continue; }
        if (preg_match('/^(Scenariu|Scenario):\s*(.*)$/i', $t, $m)) {
            $finalizeazaScenariu();
            $inBackground = false;
            $titluScenariu = $m[2];
            continue;
        }
        // altfel e un pas - sau text de descriere a Functionalitatii, pe care il ignoram.
        if ($inBackground)               $background[] = $t;
        elseif ($titluScenariu !== null) $pasiCurent[] = $t;
        // daca nu suntem nici in background, nici intr-un scenariu, e doar
        // descrierea narativa a functionalitatii - o sarim.
    }
    $finalizeazaScenariu();
    echo "\n";
}

echo "============================================================\n";
echo "  Rezultat: $scenariiOk / $totalScenarii scenarii au trecut\n";
echo "============================================================\n";
exit($scenariiOk === $totalScenarii ? 0 : 1);
