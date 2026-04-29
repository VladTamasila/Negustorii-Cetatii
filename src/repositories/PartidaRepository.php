<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * PartidaRepository
 *
 * Acces la date pentru tabela `partide`.
 *
 * O partida are mai multe stari:
 *   - status: in_asteptare | activa | finalizata | arhivata (status "public")
 *   - faza:   in_asteptare | asezare_initiala | joc | finalizata (logica interna)
 *   - jucator_activ_id: cine e la mutare
 *   - tura_curenta: numarul rundei curente in faza de joc
 *   - runda_setup, pas_setup: pentru a sti unde suntem in faza de asezare initiala
 */
final class PartidaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Lista de partide cu numarul de jucatori inscrisi.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function findAll(?string $status, int $pagina, int $dimensiunePagina): array
    {
        $offset = ($pagina - 1) * $dimensiunePagina;

        $sqlCount = "SELECT COUNT(*) FROM partide" . ($status !== null ? " WHERE status = :status" : "");
        $stmtCount = $this->pdo->prepare($sqlCount);
        if ($status !== null) {
            $stmtCount->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT p.id, p.nume, p.status, p.faza, p.jucatori_maxim,
                   COUNT(j.id) AS jucatori_curenti
            FROM partide p
            LEFT JOIN jucatori j ON j.partida_id = p.id
            " . ($status !== null ? "WHERE p.status = :status" : "") . "
            GROUP BY p.id
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        if ($status !== null) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $dimensiunePagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,           PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(static fn(array $r): array => [
            'id'              => (int) $r['id'],
            'nume'            => $r['nume'],
            'status'          => $r['status'],
            'faza'            => $r['faza'],
            'jucatoriCurenti' => (int) $r['jucatori_curenti'],
            'jucatoriMaxim'   => (int) $r['jucatori_maxim'],
        ], $stmt->fetchAll());

        return ['items' => $items, 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM partide WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $nume, int $jucatoriMaxim, int $punctajCastig): int
    {
        $sql = "INSERT INTO partide (nume, status, faza, jucatori_maxim, punctaj_castig)
                VALUES (:nume, 'in_asteptare', 'in_asteptare', :jmax, :pcastig)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nume',    $nume,          PDO::PARAM_STR);
        $stmt->bindValue(':jmax',    $jucatoriMaxim, PDO::PARAM_INT);
        $stmt->bindValue(':pcastig', $punctajCastig, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Trecere de la 'in_asteptare' la 'asezare_initiala': harta e generata,
     * primul jucator (ordine=1) e activ, asteptam asezarea initiala.
     */
    public function pornesteSetup(int $idPartida, int $idPrimulJucator): void
    {
        $sql = "UPDATE partide
                SET status = 'activa', faza = 'asezare_initiala',
                    runda_setup = 1, pas_setup = 'asezare',
                    jucator_activ_id = :j
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':j',  $idPrimulJucator, PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,       PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Schimba pasul de setup intre 'asezare' si 'drum'.
     */
    public function setPasSetup(int $idPartida, string $pas): void
    {
        $stmt = $this->pdo->prepare("UPDATE partide SET pas_setup = :p WHERE id = :id");
        $stmt->bindValue(':p',  $pas,       PDO::PARAM_STR);
        $stmt->bindValue(':id', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Avanseaza setup-ul: trece la urmatorul jucator (snake draft) sau
     * la faza 'joc' cand toata lumea a terminat.
     */
    public function actualizeazaSetup(int $idPartida, int $idJucatorUrmator, int $rundaSetup, string $pasSetup, ?string $faza = null): void
    {
        $faza ??= 'asezare_initiala';
        $sql = "UPDATE partide
                SET jucator_activ_id = :j, runda_setup = :rs, pas_setup = :ps, faza = :f
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':j',  $idJucatorUrmator, PDO::PARAM_INT);
        $stmt->bindValue(':rs', $rundaSetup,       PDO::PARAM_INT);
        $stmt->bindValue(':ps', $pasSetup,         PDO::PARAM_STR);
        $stmt->bindValue(':f',  $faza,             PDO::PARAM_STR);
        $stmt->bindValue(':id', $idPartida,        PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Setup s-a terminat - intram in faza de joc normala. Setam tura 1 si
     * primul jucator (ordine=1) ca jucator activ.
     */
    public function intraInJoc(int $idPartida, int $idPrimulJucator): void
    {
        $sql = "UPDATE partide
                SET faza = 'joc', tura_curenta = 1, jucator_activ_id = :j,
                    pas_setup = 'asezare'
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':j',  $idPrimulJucator, PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,       PDO::PARAM_INT);
        $stmt->execute();
    }

    public function avanseazaTura(int $idPartida, int $idJucatorUrmator, int $turaNoua): void
    {
        $sql = "UPDATE partide
                SET jucator_activ_id = :j, tura_curenta = :t
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':j',  $idJucatorUrmator, PDO::PARAM_INT);
        $stmt->bindValue(':t',  $turaNoua,         PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,        PDO::PARAM_INT);
        $stmt->execute();
    }

    public function finalizeaza(int $idPartida, int $idCastigator): void
    {
        $sql = "UPDATE partide
                SET status = 'finalizata', faza = 'finalizata', castigator_id = :c
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':c',  $idCastigator, PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,    PDO::PARAM_INT);
        $stmt->execute();
    }
}
