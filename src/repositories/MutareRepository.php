<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * MutareRepository - istoricul actiunilor dintr-o partida.
 */
final class MutareRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(int $idPartida, int $idJucator, string $tip, array $payload, string $mesaj, int $runda): int
    {
        $sql = "INSERT INTO mutari (partida_id, jucator_id, tip, payload_json, mesaj, runda)
                VALUES (:partida_id, :jucator_id, :tip, :payload, :mesaj, :runda)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':partida_id', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':jucator_id', $idJucator, PDO::PARAM_INT);
        $stmt->bindValue(':tip',        $tip,       PDO::PARAM_STR);
        $stmt->bindValue(':payload',    json_encode($payload, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
        $stmt->bindValue(':mesaj',      $mesaj,     PDO::PARAM_STR);
        $stmt->bindValue(':runda',      $runda,     PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function zarAruncatInTura(int $idPartida, int $idJucator, int $tura): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM mutari WHERE partida_id = :p AND jucator_id = :j AND tip = 'zar' AND runda = :r LIMIT 1");
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':j', $idJucator, PDO::PARAM_INT);
        $stmt->bindValue(':r', $tura,      PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function findById(int $idMutare): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, j.nume AS jucator_nume FROM mutari m
             JOIN jucatori j ON j.id = m.jucator_id
             WHERE m.id = :id LIMIT 1"
        );
        $stmt->bindValue(':id', $idMutare, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findByPartida(int $idPartida, int $limita = 50): array
    {
        $sql = "SELECT m.*, j.nume AS jucator_nume FROM mutari m
                JOIN jucatori j ON j.id = m.jucator_id
                WHERE m.partida_id = :id
                ORDER BY m.id DESC
                LIMIT :limita";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id',     $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':limita', $limita,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
