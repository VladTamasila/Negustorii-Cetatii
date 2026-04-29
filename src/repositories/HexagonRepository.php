<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * HexagonRepository
 *
 * Acces la tabela `hexagoane` (terenurile de pe harta).
 */
final class HexagonRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Toate hexagoanele unei partide, cu lista de id-uri varfuri pentru fiecare.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByPartida(int $idPartida): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT h.* FROM hexagoane h
             WHERE h.partida_id = :p
             ORDER BY h.r ASC, h.q ASC"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Hexagoanele cu un anumit numar token (folosit la aruncarea zarului).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByNumar(int $idPartida, int $numar): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM hexagoane
             WHERE partida_id = :p AND numar_token = :n"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':n', $numar,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Returneaza id-urile celor 6 varfuri ale unui hexagon.
     *
     * @return int[]
     */
    public function varfuriHexagon(int $hexagonId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT varf_id FROM hexagon_varfuri WHERE hexagon_id = :h"
        );
        $stmt->bindValue(':h', $hexagonId, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static fn($r): int => (int) $r['varf_id'], $stmt->fetchAll());
    }

    /**
     * Maparea inversa: pentru fiecare hexagon, lista id-urilor de varfuri.
     * Util la randare in UI ca sa nu facem N+1 queries.
     *
     * @return array<int, int[]>  hexagon_id => [varf_id, ...]
     */
    public function maparVarfuriPerHexagon(int $idPartida): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT hv.hexagon_id, hv.varf_id
             FROM hexagon_varfuri hv
             JOIN hexagoane h ON h.id = hv.hexagon_id
             WHERE h.partida_id = :p"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->execute();

        $rezultat = [];
        foreach ($stmt->fetchAll() as $r) {
            $rezultat[(int) $r['hexagon_id']][] = (int) $r['varf_id'];
        }
        return $rezultat;
    }
}
