<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * AsezareRepository
 *
 * Acces la tabela `asezari` (constructiile jucatorilor pe varfuri).
 * tip: 'asezare' (1 prestigiu, 1 resursa) sau 'cetate' (2 prestigiu, 2 resurse).
 */
final class AsezareRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $idPartida, int $idJucator, int $idVarf, string $tip = 'asezare'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO asezari (partida_id, jucator_id, varf_id, tip)
             VALUES (:p, :j, :v, :t)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':j', $idJucator, PDO::PARAM_INT);
        $stmt->bindValue(':v', $idVarf,    PDO::PARAM_INT);
        $stmt->bindValue(':t', $tip,       PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTip(int $idAsezare, string $tip): void
    {
        $stmt = $this->pdo->prepare("UPDATE asezari SET tip = :t WHERE id = :id");
        $stmt->bindValue(':t',  $tip,        PDO::PARAM_STR);
        $stmt->bindValue(':id', $idAsezare,  PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findByVarf(int $idPartida, int $idVarf): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM asezari WHERE partida_id = :p AND varf_id = :v LIMIT 1"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':v', $idVarf,    PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByPartida(int $idPartida): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, j.nume AS jucator_nume, j.culoare
             FROM asezari a
             JOIN jucatori j ON j.id = a.jucator_id
             WHERE a.partida_id = :p"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByJucator(int $idJucator): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM asezari WHERE jucator_id = :j");
        $stmt->bindValue(':j', $idJucator, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Numara asezarile + cetatile pe un varf - de fapt e mereu 0 sau 1
     * (cheie unica), dar metoda e utila pentru regula distantei aplicata
     * pe varfurile vecine.
     */
    public function numarPeVarf(int $idPartida, int $idVarf): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM asezari WHERE partida_id = :p AND varf_id = :v"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':v', $idVarf,    PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
