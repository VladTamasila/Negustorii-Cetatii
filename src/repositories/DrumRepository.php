<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * DrumRepository
 *
 * Acces la tabela `drumuri` (drumurile jucatorilor pe muchii).
 */
final class DrumRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $idPartida, int $idJucator, int $idMuchie): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drumuri (partida_id, jucator_id, muchie_id)
             VALUES (:p, :j, :m)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':j', $idJucator, PDO::PARAM_INT);
        $stmt->bindValue(':m', $idMuchie,  PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function findByMuchie(int $idPartida, int $idMuchie): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM drumuri WHERE partida_id = :p AND muchie_id = :m LIMIT 1"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':m', $idMuchie,  PDO::PARAM_INT);
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
            "SELECT d.*, j.nume AS jucator_nume, j.culoare
             FROM drumuri d
             JOIN jucatori j ON j.id = d.jucator_id
             WHERE d.partida_id = :p"
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
        $stmt = $this->pdo->prepare("SELECT * FROM drumuri WHERE jucator_id = :j");
        $stmt->bindValue(':j', $idJucator, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Verifica daca jucatorul are drum care atinge un varf dat.
     * Util pentru regula: o asezare in faza normala trebuie pusa la
     * capatul unui drum propriu.
     */
    public function jucatorAreDrumLaVarf(int $idPartida, int $idJucator, int $idVarf): bool
    {
        $sql = "SELECT 1
                FROM drumuri d
                JOIN muchii m ON m.id = d.muchie_id
                WHERE d.partida_id = :p
                  AND d.jucator_id = :j
                  AND (m.varf_a_id = :v1 OR m.varf_b_id = :v2)
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':p',  $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':j',  $idJucator, PDO::PARAM_INT);
        $stmt->bindValue(':v1', $idVarf,    PDO::PARAM_INT);
        $stmt->bindValue(':v2', $idVarf,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Verifica daca jucatorul are un drum sau o asezare conectate la una
     * dintre capetele unei muchii (pentru regula de plasare a unui drum nou).
     */
    public function jucatorAreConexiuneLaMuchie(int $idPartida, int $idJucator, int $idMuchie): bool
    {
        $sql = "SELECT m.varf_a_id, m.varf_b_id FROM muchii m WHERE m.id = :m";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':m', $idMuchie, PDO::PARAM_INT);
        $stmt->execute();
        $m = $stmt->fetch();
        if ($m === false) return false;

        // Drum propriu care atinge unul din varfuri?
        if ($this->jucatorAreDrumLaVarf($idPartida, $idJucator, (int) $m['varf_a_id'])) return true;
        if ($this->jucatorAreDrumLaVarf($idPartida, $idJucator, (int) $m['varf_b_id'])) return true;

        // Asezare proprie pe unul din varfuri?
        $sqlA = "SELECT 1 FROM asezari
                 WHERE partida_id = :p AND jucator_id = :j AND varf_id IN (:va, :vb)
                 LIMIT 1";
        $stmt = $this->pdo->prepare($sqlA);
        $stmt->bindValue(':p',  $idPartida,         PDO::PARAM_INT);
        $stmt->bindValue(':j',  $idJucator,         PDO::PARAM_INT);
        $stmt->bindValue(':va', (int) $m['varf_a_id'], PDO::PARAM_INT);
        $stmt->bindValue(':vb', (int) $m['varf_b_id'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }
}
