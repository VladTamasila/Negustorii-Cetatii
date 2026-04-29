<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * MuchieRepository
 *
 * Acces la tabela `muchii` (laturile hexagoanelor, locul unde se pun drumuri).
 */
final class MuchieRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByPartida(int $idPartida): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM muchii WHERE partida_id = :p");
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $muchieId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM muchii WHERE id = :id");
        $stmt->bindValue(':id', $muchieId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Muchiile care au un anumit varf la unul din capete.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByVarf(int $varfId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM muchii WHERE varf_a_id = :v OR varf_b_id = :v"
        );
        $stmt->bindValue(':v', $varfId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
