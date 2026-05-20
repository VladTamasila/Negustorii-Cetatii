<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * HartaService
 *
 * Genereaza harta hexagonala pentru o partida:
 *   1. 19 hexagoane in axial layout 3-4-5-4-3 (|q|<=2, |r|<=2, |q+r|<=2)
 *   2. Distributie inteligenta a terenurilor: max 2 hexagoane adiacente
 *      de acelasi tip (fara campii lipite intre ele)
 *   3. Distributie numere: 6 si 8 nu cad niciodata adiacente
 *   4. Calcul colturi (varfuri) si laturi (muchii) deduplicate intre hexagoane
 *
 * Cantitatile sunt cele clasice Catan:
 *   4 padure, 4 camp, 4 pasune, 3 deal, 3 munte, 1 desert (= 19)
 *   numere: 2,3,3,4,4,5,5,6,6,8,8,9,9,10,10,11,11,12 (fara 7)
 *
 * Geometrie pointy-top: cx = size*sqrt(3)*(q+r/2), cy = size*1.5*r
 */
final class HartaService
{
    public const HEX_SIZE = 50;
    private const MAX_ADIACENTE_ACELASI_TIP = 2;
    private const MAX_INCERCARI_SHUFFLE = 200;

    public function __construct(private readonly PDO $pdo) {}

    public function genereaza(int $idPartida): void
    {
        // 1. Pozitiile celor 19 hexagoane
        $hexPositions = [];
        for ($r = -2; $r <= 2; $r++) {
            for ($q = -2; $q <= 2; $q++) {
                if (abs($q + $r) > 2) continue;
                $hexPositions[] = ['q' => $q, 'r' => $r];
            }
        }

        // 2. Maparea vecinilor (pentru anti-cluster)
        $vecini = $this->calculeazaVecini($hexPositions);

        // 3. Distributie terenuri si numere cu constrangeri
        $terrains = $this->distribuieTerenuri($vecini);
        $numere   = $this->distribuieNumere($terrains, $vecini);

        // 4. Insereaza in DB
        $size = self::HEX_SIZE;
        $sqrt3 = sqrt(3);

        // 6 directii pointy-top: top, top-right, bottom-right, bottom, bottom-left, top-left
        $offsetVarfuri = [
            [0,                  -$size],
            [$size * $sqrt3 / 2, -$size / 2],
            [$size * $sqrt3 / 2,  $size / 2],
            [0,                   $size],
            [-$size * $sqrt3 / 2, $size / 2],
            [-$size * $sqrt3 / 2,-$size / 2],
        ];

        $varfMap   = []; // "x,y" -> id
        $muchieMap = []; // "min-max" -> id

        $tokenIdx = 0;
        foreach ($hexPositions as $i => $pos) {
            $terrain = $terrains[$i];
            $numar   = $terrain === 'desert' ? null : $numere[$tokenIdx++];

            $hexId = $this->insertHexagon($idPartida, $pos['q'], $pos['r'], $terrain, $numar);

            $cx = $size * $sqrt3 * ($pos['q'] + $pos['r'] / 2);
            $cy = $size * 1.5   *  $pos['r'];

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
                $this->attachHexagonVarf($hexId, $varfId);
            }

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

    // ---- Distributie cu constrangeri ----

    /** @param array<int,array{q:int,r:int}> $pozitii @return array<int,int[]> */
    private function calculeazaVecini(array $pozitii): array
    {
        $directii = [[1,0],[-1,0],[0,1],[0,-1],[1,-1],[-1,1]];
        $indexDupaCheie = [];
        foreach ($pozitii as $i => $p) {
            $indexDupaCheie[$p['q'] . ',' . $p['r']] = $i;
        }
        $vecini = [];
        foreach ($pozitii as $i => $p) {
            $vecini[$i] = [];
            foreach ($directii as $d) {
                $cheie = ($p['q'] + $d[0]) . ',' . ($p['r'] + $d[1]);
                if (isset($indexDupaCheie[$cheie])) {
                    $vecini[$i][] = $indexDupaCheie[$cheie];
                }
            }
        }
        return $vecini;
    }

    /** @param array<int,int[]> $vecini @return string[] */
    private function distribuieTerenuri(array $vecini): array
    {
        $bag = array_merge(
            array_fill(0, 4, 'padure'),
            array_fill(0, 4, 'camp'),
            array_fill(0, 4, 'pasune'),
            array_fill(0, 3, 'deal'),
            array_fill(0, 3, 'munte'),
            ['desert']
        );
        for ($i = 0; $i < self::MAX_INCERCARI_SHUFFLE; $i++) {
            shuffle($bag);
            if ($this->nuAreClustereMari($bag, $vecini)) return $bag;
        }
        $this->repararClustere($bag, $vecini);
        return $bag;
    }

    /** @param string[] $terrains @param array<int,int[]> $vecini */
    private function nuAreClustereMari(array $terrains, array $vecini): bool
    {
        $vizitat = [];
        foreach ($terrains as $i => $t) {
            if (isset($vizitat[$i])) continue;
            $stack = [$i]; $marime = 0;
            while ($stack) {
                $nod = array_pop($stack);
                if (isset($vizitat[$nod])) continue;
                $vizitat[$nod] = true; $marime++;
                if ($marime > self::MAX_ADIACENTE_ACELASI_TIP) return false;
                foreach ($vecini[$nod] as $v) {
                    if (!isset($vizitat[$v]) && $terrains[$v] === $t) $stack[] = $v;
                }
            }
        }
        return true;
    }

    /** @param string[] $terrains @param array<int,int[]> $vecini */
    private function repararClustere(array &$terrains, array $vecini): void
    {
        for ($pas = 0; $pas < 200; $pas++) {
            if ($this->nuAreClustereMari($terrains, $vecini)) return;
            $n = count($terrains);
            $i = random_int(0, $n - 1);
            $j = random_int(0, $n - 1);
            if ($terrains[$i] !== $terrains[$j]) {
                [$terrains[$i], $terrains[$j]] = [$terrains[$j], $terrains[$i]];
            }
        }
    }

    /** @param string[] $terrains @param array<int,int[]> $vecini @return int[] */
    private function distribuieNumere(array $terrains, array $vecini): array
    {
        $indiciCuNumar = [];
        foreach ($terrains as $i => $t) {
            if ($t !== 'desert') $indiciCuNumar[] = $i;
        }
        $bag = [2, 3, 3, 4, 4, 5, 5, 6, 6, 8, 8, 9, 9, 10, 10, 11, 11, 12];
        for ($i = 0; $i < self::MAX_INCERCARI_SHUFFLE; $i++) {
            shuffle($bag);
            $numarPerHex = [];
            foreach ($indiciCuNumar as $pozitie => $idxHex) {
                $numarPerHex[$idxHex] = $bag[$pozitie];
            }
            if ($this->fara68Adiacente($numarPerHex, $vecini)) return $bag;
        }
        return $bag;
    }

    /** @param array<int,int> $numarPerHex @param array<int,int[]> $vecini */
    private function fara68Adiacente(array $numarPerHex, array $vecini): bool
    {
        foreach ($numarPerHex as $idx => $n) {
            if ($n !== 6 && $n !== 8) continue;
            foreach ($vecini[$idx] as $v) {
                $nv = $numarPerHex[$v] ?? null;
                if ($nv === 6 || $nv === 8) return false;
            }
        }
        return true;
    }

    // ---- DB helpers ----

    private function insertHexagon(int $idPartida, int $q, int $r, string $terrain, ?int $numar): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO hexagoane (partida_id, q, r, terrain, numar_token)
             VALUES (:p, :q, :r, :t, :n)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':q', $q, PDO::PARAM_INT);
        $stmt->bindValue(':r', $r, PDO::PARAM_INT);
        $stmt->bindValue(':t', $terrain, PDO::PARAM_STR);
        if ($numar === null) $stmt->bindValue(':n', null, PDO::PARAM_NULL);
        else $stmt->bindValue(':n', $numar, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    private function insertVarf(int $idPartida, float $x, float $y): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO varfuri (partida_id, x, y) VALUES (:p, :x, :y)");
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':x', $x);
        $stmt->bindValue(':y', $y);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    private function attachHexagonVarf(int $hexagonId, int $varfId): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO hexagon_varfuri (hexagon_id, varf_id) VALUES (:h, :v)");
        $stmt->bindValue(':h', $hexagonId, PDO::PARAM_INT);
        $stmt->bindValue(':v', $varfId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function insertMuchie(int $idPartida, int $varfA, int $varfB): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO muchii (partida_id, varf_a_id, varf_b_id) VALUES (:p, :a, :b)");
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':a', $varfA, PDO::PARAM_INT);
        $stmt->bindValue(':b', $varfB, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }
}
