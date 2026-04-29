<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * MutareRepository
 *
 * Acces la date pentru tabela `mutari` (istoricul actiunilor dintr-o partida).
 *
 * Fiecare actiune a unui jucator (a aruncat zarul, a construit, a pasat etc.)
 * devine un rand in tabela `mutari`. Asa avem un istoric complet, replayable.
 */
final class MutareRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Insereaza o mutare noua si returneaza id-ul ei.
     *
     * @param array<string, mixed> $payload   Detaliile specifice tipului (zar, cladire etc.)
     */
    public function create(
        int $idPartida,
        int $idJucator,
        string $tip,
        array $payload,
        string $mesaj,
        int $runda
    ): int {
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

    /**
     * Verifica daca jucatorul a aruncat deja zarul in tura curenta.
     */
    public function zarAruncatInTura(int $idPartida, int $idJucator, int $tura): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM mutari
             WHERE partida_id = :p AND jucator_id = :j AND tip = 'zar' AND runda = :r
             LIMIT 1"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':j', $idJucator, PDO::PARAM_INT);
        $stmt->bindValue(':r', $tura,      PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Returneaza istoricul mutarilor dintr-o partida, cea mai recenta prima.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByPartida(int $idPartida, int $limita = 50): array
    {
        $sql = "SELECT m.*, j.nume AS jucator_nume
                FROM mutari m
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
