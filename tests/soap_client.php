<?php
declare(strict_types=1);

/**
 * Exemplu de client SOAP pentru Negustorii Cetatii.
 *
 * Demonstreaza protocolul alternativ (SOAP) peste aceleasi date ca REST.
 *
 * RULARE (serverul trebuie pornit):
 *   php tests/soap_client.php
 *   php tests/soap_client.php http://localhost:8080
 *
 * Necesita extensia php-soap activata (extension=soap in php.ini).
 */

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8080', '/');

if (!class_exists('SoapClient')) {
    fwrite(STDERR, "Extensia php-soap nu e activata. Adauga extension=soap in php.ini.\n");
    exit(1);
}

// Mod non-WSDL: ii dam manual location (URL-ul) si uri (namespace-ul).
$client = new SoapClient(null, [
    'location'   => $baseUrl . '/soap',
    'uri'        => 'urn:negustorii',
    'trace'      => 1,
    'exceptions' => true,
]);

echo "== numarPartide() ==\n";
echo $client->numarPartide() . " partide\n\n";

echo "== listaPartide() ==\n";
$lista = $client->listaPartide();
foreach ((array) $lista as $p) {
    $p = (array) $p;
    echo "  #{$p['id']} {$p['nume']} [{$p['status']}]\n";
}

echo "\n== detaliiPartida(1) ==\n";
try {
    $d = (array) $client->detaliiPartida(1);
    echo "  {$d['nume']} - faza {$d['faza']}\n";
} catch (SoapFault $e) {
    echo "  SoapFault: {$e->getMessage()}\n";
}
