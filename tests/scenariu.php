<?php
declare(strict_types=1);

/**
 * Scenariu de testare end-to-end (PHP + Guzzle).
 *
 * Profesorul cere demonstrarea integrarii API + DB prin "scenariu de testare
 * cu transfer automat de proprietati intre pasi". Aici facem exact asta:
 *
 *   1. Creem o partida noua            -> obtinem $idPartida
 *   2. Adaugam 2 jucatori               -> obtinem $idJucator1, $idJucator2
 *   3. PUT pe partida (modificam numele) - testam si UPDATE
 *   4. PUT pe jucator (schimbam culoarea) - testam si UPDATE pe sub-resursa
 *   5. Pornim partida                   -> harta se genereaza
 *   6. GET /harta                       -> obtinem varfuri + muchii random
 *   7. Pas setup pentru jucatorul activ -> POST setup/asezare cu primul varf liber
 *   8. ...si tot asa, prin snake draft, pana iese din faza de asezare
 *   9. La final - GET /mutari pentru istoric, apoi DELETE partida (cleanup).
 *
 * Variabilele *NU* sunt hardcodate: fiecare pas extrage ce-i trebuie din
 * raspunsul JSON al pasului anterior. Asta e exact ideea cu "transfer automat
 * de proprietati" (la fel cum face Postman cu environment variables sau SoapUI
 * cu property transfers).
 *
 * Cum se ruleaza:
 *   1. Porneste serverul: `php -S localhost:8080 -t public`
 *   2. In alt terminal: `php tests/scenariu.php`
 *      sau cu URL custom: `php tests/scenariu.php http://localhost:8080`
 *
 * Necesita: composer require guzzlehttp/guzzle
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// -----------------------------------------------------------------
// Setup client
// -----------------------------------------------------------------
$baseUri = $argv[1] ?? 'http://localhost:8080';

$client = new Client([
    'base_uri' => rtrim($baseUri, '/') . '/',
    'http_errors' => false, // gestionam noi non-2xx ca sa afisam mesaje frumoase
    'timeout' => 10,
]);

// Mini helper - afiseaza un pas si decodifica JSON-ul de raspuns.
function pas(Client $client, string $metoda, string $cale, array $body = null): array
{
    static $nr = 0;
    $nr++;

    $optiuni = [];
    if ($body !== null) {
        $optiuni['json'] = $body;
    }

    $r = $client->request($metoda, ltrim($cale, '/'), $optiuni);
    $status = $r->getStatusCode();
    $text   = (string) $r->getBody();
    $date   = $text === '' ? null : json_decode($text, true);

    $marker = $status >= 200 && $status < 300 ? "\033[32m OK \033[0m" : "\033[31mFAIL\033[0m";
    echo sprintf("[%2d] %s %-6s %-50s -> %d\n", $nr, $marker, $metoda, $cale, $status);

    if ($status >= 400) {
        echo "      Mesaj: " . ($date['mesaj'] ?? $text) . "\n";
        echo "      Cod  : " . ($date['cod']   ?? '?')   . "\n";
        throw new RuntimeException("Pasul $nr a esuat.");
    }

    return $date ?? [];
}

// -----------------------------------------------------------------
// 1. Ping (verificare ca serverul raspunde)
// -----------------------------------------------------------------
echo "=== Scenariu testare Negustorii Cetatii ($baseUri) ===\n\n";

try {
    pas($client, 'GET', 'api/ping');
} catch (GuzzleException $e) {
    fwrite(STDERR, "Nu am putut conecta la $baseUri . Porneste serverul cu:\n");
    fwrite(STDERR, "  php -S localhost:8080 -t public\n");
    exit(1);
}

// -----------------------------------------------------------------
// 2. Creem o partida noua, retinem ID-ul ei
// -----------------------------------------------------------------
$partida = pas($client, 'POST', 'api/partide', [
    'nume'          => 'Scenariu Guzzle ' . date('H:i:s'),
    'jucatoriMaxim' => 4,
    'punctajCastig' => 10,
]);
$idPartida = (int) $partida['id'];
echo "      -> idPartida = $idPartida\n";

// -----------------------------------------------------------------
// 3. PUT pe partida (test UPDATE pe resursa principala)
// -----------------------------------------------------------------
$partida = pas($client, 'PUT', "api/partide/$idPartida", [
    'nume' => 'Scenariu Guzzle (modificat)',
]);
assert($partida['nume'] === 'Scenariu Guzzle (modificat)');

// -----------------------------------------------------------------
// 4. Adaugam 2 jucatori - extragem id-urile din raspuns
// -----------------------------------------------------------------
$j1 = pas($client, 'POST', "api/partide/$idPartida/jucatori",
    ['nume' => 'Vlad',   'culoare' => 'albastru']);
$j2 = pas($client, 'POST', "api/partide/$idPartida/jucatori",
    ['nume' => 'Bianca', 'culoare' => 'rosu']);

$idJucator1 = (int) $j1['id'];
$idJucator2 = (int) $j2['id'];
echo "      -> jucatori: #$idJucator1 ({$j1['nume']}), #$idJucator2 ({$j2['nume']})\n";

// -----------------------------------------------------------------
// 5. PUT pe jucator (test UPDATE pe sub-resursa)
// -----------------------------------------------------------------
$j1 = pas($client, 'PUT', "api/partide/$idPartida/jucatori/$idJucator1",
    ['nume' => 'Vlad', 'culoare' => 'verde']);
assert($j1['culoare'] === 'verde');

// -----------------------------------------------------------------
// 6. GET pe jucator individual (test GET pe sub-resursa)
// -----------------------------------------------------------------
$detalii = pas($client, 'GET', "api/partide/$idPartida/jucatori/$idJucator1");
assert((int)$detalii['id'] === $idJucator1);
assert($detalii['culoare'] === 'verde');

// -----------------------------------------------------------------
// 7. Pornim partida - genereaza harta
// -----------------------------------------------------------------
$partida = pas($client, 'POST', "api/partide/$idPartida/start");
assert($partida['faza'] === 'asezare_initiala');
echo "      -> faza = {$partida['faza']}, jucator activ = #{$partida['jucatorActivId']}\n";

// -----------------------------------------------------------------
// 8. GET /harta - retinem varfurile si muchiile pentru pasul de setup
// -----------------------------------------------------------------
$harta = pas($client, 'GET', "api/partide/$idPartida/harta");
echo "      -> " . count($harta['hexagoane']) . " hexagoane, "
   . count($harta['varfuri']) . " varfuri, "
   . count($harta['muchii']) . " muchii\n";

// -----------------------------------------------------------------
// 9. Faza de setup - snake draft. Punem o asezare + un drum pentru fiecare jucator.
//    Reluam GET /partida dupa fiecare pas ca sa stim cine e activ acum si ce pas urmeaza.
// -----------------------------------------------------------------
$varfuriFolosite = [];
$muchiiFolosite = [];

// Helper: cauta urmatorul varf liber care nu e adiacent unei asezari deja puse.
//   Regula "distance rule" din Catan - asezarile trebuie sa fie la cel putin
//   o muchie distanta una de alta.
$gasesteVarfLiber = function () use (&$harta, &$varfuriFolosite, &$client, $idPartida): int {
    foreach ($harta['varfuri'] as $v) {
        $id = (int) $v['id'];
        if (in_array($id, $varfuriFolosite, true)) continue;
        // Verifica ca nu e adiacent unei asezari existente (prin muchii)
        $estePreaAproape = false;
        foreach ($harta['muchii'] as $m) {
            if ((int)$m['varfAId'] === $id && in_array((int)$m['varfBId'], $varfuriFolosite, true)) {
                $estePreaAproape = true; break;
            }
            if ((int)$m['varfBId'] === $id && in_array((int)$m['varfAId'], $varfuriFolosite, true)) {
                $estePreaAproape = true; break;
            }
        }
        if (!$estePreaAproape) return $id;
    }
    throw new RuntimeException('Nu mai sunt varfuri libere?!');
};

// Helper: gaseste o muchie adiacenta unui varf, care nu e luata.
$gasesteMuchieLanga = function (int $idVarf) use (&$harta, &$muchiiFolosite): int {
    foreach ($harta['muchii'] as $m) {
        $id = (int) $m['id'];
        if (in_array($id, $muchiiFolosite, true)) continue;
        if ((int)$m['varfAId'] === $idVarf || (int)$m['varfBId'] === $idVarf) {
            return $id;
        }
    }
    throw new RuntimeException("Nu am gasit muchie libera langa varful $idVarf.");
};

// Iteram cat timp e faza de asezare initiala
$ultimulVarf = null;
$contor = 0;
while ($partida['faza'] === 'asezare_initiala' && $contor < 30) {
    $contor++;
    if ($partida['pasSetup'] === 'asezare') {
        $idVarf = $gasesteVarfLiber();
        pas($client, 'POST', "api/partide/$idPartida/setup/asezare", ['idVarf' => $idVarf]);
        $varfuriFolosite[] = $idVarf;
        $ultimulVarf = $idVarf;
    } else {
        // pas drum - punem drum langa ultima asezare a jucatorului curent
        $idMuchie = $gasesteMuchieLanga($ultimulVarf ?? $varfuriFolosite[0]);
        pas($client, 'POST', "api/partide/$idPartida/setup/drum", ['idMuchie' => $idMuchie]);
        $muchiiFolosite[] = $idMuchie;
    }
    // refresh stare ca sa stim cine urmeaza
    $partida = pas($client, 'GET', "api/partide/$idPartida");
}

if ($partida['faza'] === 'joc') {
    echo "\n   --> Setup terminat dupa $contor pasi. Partida intra in faza de joc.\n\n";
} else {
    echo "\n   !! Am ajuns la limita de pasi fara sa terminam setup-ul (faza: {$partida['faza']}).\n";
}

// -----------------------------------------------------------------
// 10. Daca am intrat in joc, aruncam zarul cateva ture
// -----------------------------------------------------------------
if ($partida['faza'] === 'joc') {
    for ($tura = 1; $tura <= 3; $tura++) {
        $r = pas($client, 'POST', "api/partide/$idPartida/mutari/zar");
        echo "      -> zar: {$r['zar1']} + {$r['zar2']} = {$r['suma']}\n";
        pas($client, 'POST', "api/partide/$idPartida/mutari/paseaza");
        $partida = pas($client, 'GET', "api/partide/$idPartida");
    }
}

// -----------------------------------------------------------------
// 11. Istoric - test GET pe lista de mutari + GET pe o mutare specifica
// -----------------------------------------------------------------
$mutari = pas($client, 'GET', "api/partide/$idPartida/mutari");
echo "      -> $partida[turaCurenta] ture, " . count($mutari['items']) . " mutari in istoric\n";

if (!empty($mutari['items'])) {
    $primaMutare = $mutari['items'][0];
    pas($client, 'GET', "api/partide/$idPartida/mutari/{$primaMutare['id']}");
}

// -----------------------------------------------------------------
// 12. Lista partide (test paginare + filtru)
// -----------------------------------------------------------------
$lista = pas($client, 'GET', 'api/partide?status=activa&dimensiunePagina=5');
echo "      -> {$lista['total']} partide active in total\n";

// -----------------------------------------------------------------
// 13. Cleanup - sterge sau arhiveaza partida creata
// -----------------------------------------------------------------
$r = pas($client, 'DELETE', "api/partide/$idPartida");
echo "      -> partida #$idPartida " . ($r ? ($r['mesaj'] ?? 'stearsa') : 'stearsa (204)') . "\n";

echo "\n\033[32m=== Scenariu OK ===\033[0m\n";
