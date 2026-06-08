<?php
declare(strict_types=1);

use App\Database\Connection;
use Psr\Container\ContainerInterface;

/**
 * Definitii pentru containerul PHP-DI.
 *
 * Aici inregistram doar dependintele "manuale", care au nevoie de configurare
 * speciala (ex: PDO). Restul claselor - Controllers, Repositories, Services -
 * sunt rezolvate automat de PHP-DI prin autowiring.
 */
return [
    // Setarile aplicatiei
    'settings' => function (): array {
        return require __DIR__ . '/settings.php';
    },

    // Instanta PDO partajata pe request
    PDO::class => function (ContainerInterface $c): PDO {
        $settings = $c->get('settings');
        return Connection::get($settings['db']);
    },

    // Motorul de template-ing - stie unde sunt fisierele .html din templates/
    App\Services\Template::class => function (): App\Services\Template {
        return new App\Services\Template(__DIR__ . '/../templates');
    },
];
