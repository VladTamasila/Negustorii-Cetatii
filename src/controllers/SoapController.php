<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SoapService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SoapServer;

/**
 * SoapController - expune un endpoint SOAP (protocol alternativ fata de REST).
 *
 *   GET  /soap  - pagina informativa (ce operatii exista, cum se apeleaza)
 *   POST /soap  - endpoint-ul SOAP propriu-zis (apelat de un SoapClient)
 *
 * Folosim modul "non-WSDL" (mai simplu): clientul trebuie sa stie location + uri.
 * Vezi exemplul din tests/soap_client.php.
 */
final class SoapController
{
    private const URI = 'urn:negustorii';

    public function __construct(private readonly SoapService $soap) {}

    /** GET /soap - explica endpoint-ul SOAP. */
    public function info(Request $request, Response $response): Response
    {
        $html = <<<HTML
        <!DOCTYPE html><html lang="ro"><head><meta charset="UTF-8">
        <title>SOAP - Negustorii Cetatii</title>
        <style>body{font-family:system-ui;background:#1a1a1a;color:#eee;max-width:720px;margin:40px auto;padding:0 16px}
        code{background:#2a2a2a;padding:2px 6px;border-radius:4px}pre{background:#2a2a2a;padding:12px;border-radius:8px;overflow:auto}
        h1{color:#c69b40}a{color:#c69b40}</style></head><body>
        <h1>Endpoint SOAP (protocol alternativ)</h1>
        <p>Pe langa API-ul REST/JSON, aceleasi date sunt expuse si prin <b>SOAP</b>.</p>
        <p>Endpoint (location): <code>/soap</code> &nbsp; URI: <code>urn:negustorii</code></p>
        <h3>Operatii disponibile</h3>
        <ul>
          <li><code>numarPartide()</code> - numarul total de partide</li>
          <li><code>listaPartide()</code> - lista partidelor</li>
          <li><code>detaliiPartida(int id)</code> - detaliile unei partide</li>
        </ul>
        <h3>Exemplu de apel (PHP)</h3>
        <pre>\$c = new SoapClient(null, [
    'location' => 'http://localhost:8080/soap',
    'uri'      => 'urn:negustorii',
]);
echo \$c->numarPartide();
print_r(\$c->listaPartide());</pre>
        <p>Vezi <code>tests/soap_client.php</code> pentru un exemplu complet.
        Apelurile efective se fac prin <b>POST</b> catre acest URL.</p>
        <p><a href="/">&larr; Inapoi la joc</a></p>
        </body></html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** POST /soap - serverul SOAP propriu-zis. */
    public function server(Request $request, Response $response): Response
    {
        // ext-soap trebuie activata in php.ini (extension=soap). De obicei e activa in XAMPP.
        if (!class_exists(SoapServer::class)) {
            $response->getBody()->write(
                'Extensia php-soap nu este activata. Adauga "extension=soap" in php.ini si reporneste serverul.'
            );
            return $response->withStatus(501)->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $server = new SoapServer(null, ['uri' => self::URI]);
        $server->setObject($this->soap);

        // SoapServer scrie raspunsul in output buffer; il capturam si il punem in
        // raspunsul PSR-7. Ii dam explicit body-ul cererii (nu ne bazam pe php://input).
        ob_start();
        $server->handle((string) $request->getBody());
        $xml = ob_get_clean();

        $response->getBody()->write($xml !== false ? $xml : '');
        return $response->withHeader('Content-Type', 'text/xml; charset=utf-8');
    }
}
