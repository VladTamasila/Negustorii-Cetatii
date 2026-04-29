<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * JucatorRepository
 *
 * Acces la tabela `jucatori`. Acum cu 5 resurse (lemn, piatra, aur, grau, lana)
 * si o coloana `ordine` pentru pozitia jucatorului in setup (1, 2, 3, 4).
 */
final class JucatorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByPartida(int $idPartida): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM jucatori WHERE partida_id = :id ORDER BY ordine ASC, id ASC"
        );
        $stmt->bindValue(':id', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM jucatori WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Creeaza un jucator. `ordine` se calculeaza automat - urmatorul disponibil
     * in partida.
     */
    public function create(int $idPartida, string $nume, ?string $culoare): int
    {
        // Aflam urmatoarea ordine disponibila
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(ordine), 0) + 1 AS proxima
             FROM jucatori WHERE partida_id = :p"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        $ordine = (int) $stmt->fetchColumn();

        $sql = "INSERT INTO jucatori (partida_id, nume, culoare, ordine)
                VALUES (:p, :n, :c, :o)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':n', $nume,      PDO::PARAM_STR);
        $stmt->bindValue(':c', $culoare,   $culoare === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':o', $ordine,    PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Modifica resursele si prestigiul unui jucator (valori absolute, nu deltas).
     *
     * @param array{lemn:int, piatra:int, aur:int, grau:int, lana:int, prestigiu:int} $r
     */
    public function setResurse(int $idJucator, array $r): void
    {
        $sql = "UPDATE jucatori
                SET lemn = :lemn, piatra = :piatra, aur = :aur,
                    grau = :grau, lana = :lana, prestigiu = :prestigiu
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lemn',      $r['lemn'],      PDO::PARAM_INT);
        $stmt->bindValue(':piatra',    $r['piatra'],    PDO::PARAM_INT);
        $stmt->bindValue(':aur',       $r['aur'],       PDO::PARAM_INT);
        $stmt->bindValue(':grau',      $r['grau'],      PDO::PARAM_INT);
        $stmt->bindValue(':lana',      $r['lana'],      PDO::PARAM_INT);
        $stmt->bindValue(':prestigiu', $r['prestigiu'], PDO::PARAM_INT);
        $stmt->bindValue(':id',        $idJucator,      PDO::PARAM_INT);
        $stmt->execute();
    }

    public function countByPartida(int $idPartida): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM jucatori WHERE partida_id = :id");
        $stmt->bindValue(':id', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica daca o culoare este deja folosita de un jucator in partida.
     * Folosit ca regula de unicitate la adaugarea unui jucator nou.
     * NULL nu e considerat duplicat (jucatorii fara culoare nu se ciocnesc).
     */
    public function existaCuloareInPartida(int $idPartida, string $culoare): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM jucatori
             WHERE partida_id = :p AND culoare = :c
             LIMIT 1"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':c', $culoare,   PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }
}
