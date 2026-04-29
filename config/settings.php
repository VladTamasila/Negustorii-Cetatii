<?php
declare(strict_types=1);

/**
 * Setarile aplicatiei.
 * Citim .env din radacina proiectului si returnam un array structurat.
 */

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

// Incarcam variabilele din .env (daca exista)
if (file_exists($rootPath . '/.env')) {
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->load();
}

return [
    'app' => [
        'env'   => $_ENV['APP_ENV']   ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
    'db' => [
        'host'    => $_ENV['DB_HOST']    ?? '127.0.0.1',
        'port'    => $_ENV['DB_PORT']    ?? '3306',
        'name'    => $_ENV['DB_NAME']    ?? 'negustorii_cetatii',
        'user'    => $_ENV['DB_USER']    ?? 'root',
        'pass'    => $_ENV['DB_PASS']    ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
];
