<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * HartaService
 *
 * Generatorul de harta. Cand se porneste o partida, acest serviciu:
 *
 *   1. Decide pozitiile celor 19 hexagoane in coordonate axiale (q, r)
 *      - layoutul standard Catan: rinduri 3-4-5-4-3
 *      - regula: |q| <= 2 si |r| <= 2 si |q+r| <= 2
 *
 *   2. Distribuie aleator 6 tipuri de teren si 18 numere "token"
 *      - 4 padure, 3 deal, 3 munte, 4 camp, 4 pasune, 1 desert
 *      - numere: 2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12 (fara 7)
 *      - desertul nu primeste numar
 *
 *   3. Calculeaza pozitiile pixel ale celor 6 colturi (varfuri) ale fiecarui
 *      hexagon, le deduplica intre hexagoane vecine si le scrie in DB.
 *      19 hexagoane * 6 colturi = 114 puncte, dar majoritatea sunt impartite,
 *      asa ca raman 54 varfuri unice.
 *
 *   4. Calculeaza muchiile (laturile dintre 2 colturi consecutive). 72 unice.
 *
 * Toate astea se scriu in DB legate de id-ul partidei. Astfel fiecare partida
 * are propria harta independenta.
 *
 * Geometrie: folosim hexagoane "pointy-top" (cu varfurile sus si jos).
 * Pozitia centrului unui hexagon (q, r) in pixeli este:
 *     cx = size * sqrt(3) * (q + r/2)
 *     cy = size * 3/2 * r
 * Cele 6 colturi sunt la distanta `size` de centru, in 6 directii.
 */
final class HartaService
{
    /** Marimea unui hexagon in pixeli (raza de la centru la varf). */
    public const HEX_SIZE = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Genereaza harta completa pentru o partida.
     * Trebuie apelata o singura data, cand se porneste partida.
     */
    public function genereaza(int $idPartida): void
    {
        // --- 1. Lista de pozitii hexagoane (axial) ----------------------
        $hexPositions = [];
        for ($r = -2; $r <= 2; $r++) {
            for ($q = -2; $q <= 2; $q++) {
                if (abs($q + $r) > 2) continue;
                $hexPositions[] = ['q' => $q, 'r' => $r];
            }
        }
        // -> 19 elemente

        // --- 2. Lista de terenuri amestecata ---------------------------
        $terrains = array_merge(
            array_fill(0, 3, 'padure'),
            array_fill(0, 3, 'deal'),
            array_fill(0, 4, 'munte'),
            array_fill(0, 4, 'camp'),
            array_fill(0, 4, 'pasune'),
            ['desert']
        );
        shuffle($terrains);

        $numere = [2, 3, 3, 4, 4, 5, 5, 6, 6, 8, 8, 9, 9, 10, 10, 11, 11, 12];
        shuffle($numere);

        // --- 3. Insereaza fiecare hexagon, varfurile si muchiile -------
        $size = self::HEX_SIZE;
        $sqrt3 = sqrt(3);

        // Cele 6 directii catre colturi (offset de la centrul hexagonului)
        // Ordinea: top, top-right, bottom-right, bottom, bottom-left, top-left
        $offsetVarfuri = [
            [0,                 -$size],
            [$size * $sqrt3 / 2, -$size / 2],
            [$size * $sqrt3 / 2,  $size / 2],
            [0,                  $size],
            [-$size * $sqrt3 / 2, $size / 2],
            [-$size * $sqrt3 / 2,-$size / 2],
        ];

        // Memorii pentru deduplicare in cadrul partidei curente
        $varfMap   = []; // cheie "x,y" -> id varf
        $muchieMap = []; // cheie "minId-maxId" -> id muchie

        $tokenIdx = 0;
        foreach ($hexPositions as $i => $pos) {
            $terrain = $terrains[$i];
            $numar   = $terrain === 'desert' ? null : $numere[$tokenIdx++];

            // Insereaza hexagonul
            $hexId = $this->insertHexagon($idPartida, $pos['q'], $pos['r'], $terrain, $numar);

            // Pozitia centrului in pixeli
            $cx = $size * $sqrt3 * ($pos['q'] + $pos['r'] / 2);
            $cy = $size * 1.5   *  $pos['r'];

            // Calculeaza si insereaza cele 6 varfuri
            $varfIds = [];
            foreach ($offsetVarfuri as $off) {
                $vx = round($cx + $off[0], 2);
                $vy = round($cy + $off[1], 2);
                $cheie = $vx . ',' . $vy;

                if (!isset($varfMap[$cheie])) {
                    $varfMap[$cheie] = $this->insertVarf($idPartida, (float) $vx, (float) $vy);
                }
                $varfId = $varfMap[$cheie];
                $varfIds[] = $varfId;

                // Leaga hexagonul de acest varf
                $this->attachHexagonVarf($hexId, $varfId);
            }

            // 6 muchii (latura intre varfuri consecutive)
            for ($v = 0; $v < 6; $v++) {
                $a = $varfIds[$v];
                $b = $varfIds[($v + 1) % 6];
                $min = min($a, $b);
                $max = max($a, $b);
                $cheieMuchie = $min . '-' . $max;

                if (!isset($muchieMap[$cheieMuchie])) {
                    $muchieMap[$cheieMuchie] = $this->insertMuchie($idPartida, $min, $max);
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // Operatii directe pe DB (mici, ramin in service ca sa nu mai treaca
    // prin repository - sunt apelate doar la generarea hartii).
    // -----------------------------------------------------------------

    private function insertHexagon(int $idPartida, int $q, int $r, string $terrain, ?int $numar): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO hexagoane (partida_id, q, r, terrain, numar_token)
             VALUES (:p, :q, :r, :t, :n)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':q', $q,         PDO::PARAM_INT);
        $stmt->bindValue(':r', $r,         PDO::PARAM_INT);
        $stmt->bindValue(':t', $terrain,   PDO::PARAM_STR);
        if ($numar === null) {
            $stmt->bindValue(':n', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':n', $numar, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    private function insertVarf(int $idPartida, float $x, float $y): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO varfuri (partida_id, x, y) VALUES (:p, :x, :y)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':x', $x);
        $stmt->bindValue(':y', $y);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    private function attachHexagonVarf(int $hexagonId, int $varfId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO hexagon_varfuri (hexagon_id, varf_id) VALUES (:h, :v)"
        );
        $stmt->bindValue(':h', $hexagonId, PDO::PARAM_INT);
        $stmt->bindValue(':v', $varfId,    PDO::PARAM_INT);
        $stmt->execute();
    }

    private function insertMuchie(int $idPartida, int $varfA, int $varfB): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO muchii (partida_id, varf_a_id, varf_b_id) VALUES (:p, :a, :b)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':a', $varfA,     PDO::PARAM_INT);
        $stmt->bindValue(':b', $varfB,     PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }
}
