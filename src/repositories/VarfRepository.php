<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * VarfRepository
 *
 * Acces la tabela `varfuri` (colturile hexagoanelor, locul unde se construiesc
 * asezarile).
 */
final class VarfRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByPartida(int $idPartida): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM varfuri WHERE partida_id = :p");
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $varfId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM varfuri WHERE id = :id");
        $stmt->bindValue(':id', $varfId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Returneaza hexagoanele adiacente unui varf (1, 2 sau 3 hexagoane).
     * Folosit la productia de resurse cand se arunca zarul.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hexagoaneAdiacente(int $varfId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.*
             FROM hexagoane h
             JOIN hexagon_varfuri hv ON hv.hexagon_id = h.id
             WHERE hv.varf_id = :v"
        );
        $stmt->bindValue(':v', $varfId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Returneaza id-urile varfurilor adiacente prin muchie unui varf dat.
     * Folosit pentru regula distantei (asezari nu pot fi adiacente).
     *
     * @return int[]
     */
    public function varfuriAdiacente(int $varfId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT IF(varf_a_id = :v1, varf_b_id, varf_a_id) AS vecin_id
             FROM muchii
             WHERE varf_a_id = :v2 OR varf_b_id = :v3"
        );
        $stmt->bindValue(':v1', $varfId, PDO::PARAM_INT);
        $stmt->bindValue(':v2', $varfId, PDO::PARAM_INT);
        $stmt->bindValue(':v3', $varfId, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static fn($r): int => (int) $r['vecin_id'], $stmt->fetchAll());
    }
}
