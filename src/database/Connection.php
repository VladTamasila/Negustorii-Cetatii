<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Connection
 *
 * Wrapper minim peste PDO. Foloseste un pattern de tip singleton pentru
 * a refolosi aceeasi instanta PDO pe parcursul unui request.
 *
 * Repository-urile primesc instanta PDO prin constructor (vezi
 * dependencies.php), nu o cer direct de aici - asa pastram codul testabil.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    /**
     * Returneaza instanta PDO, creand-o la prima apelare.
     *
     * @param array{host:string,port:string,name:string,user:string,pass:string,charset:string} $config
     */
    public static function get(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Nu s-a putut conecta la baza de date: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$pdo;
    }
}
