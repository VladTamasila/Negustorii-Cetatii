<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * PartidaRepository - acces la date pentru tabela `partide`.
 */
final class PartidaRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findAll(?string $status, int $pagina, int $dimensiunePagina): array
    {
        $offset = ($pagina - 1) * $dimensiunePagina;

        $sqlCount = "SELECT COUNT(*) FROM partide" . ($status !== null ? " WHERE status = :status" : "");
        $stmtCount = $this->pdo->prepare($sqlCount);
        if ($status !== null) $stmtCount->bindValue(':status', $status, PDO::PARAM_STR);
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
        if ($status !== null) $stmt->bindValue(':status', $status, PDO::PARAM_STR);
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

    public function setPasSetup(int $idPartida, string $pas): void
    {
        $stmt = $this->pdo->prepare("UPDATE partide SET pas_setup = :p WHERE id = :id");
        $stmt->bindValue(':p',  $pas,       PDO::PARAM_STR);
        $stmt->bindValue(':id', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
    }

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
        $sql = "UPDATE partide SET jucator_activ_id = :j, tura_curenta = :t WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':j',  $idJucatorUrmator, PDO::PARAM_INT);
        $stmt->bindValue(':t',  $turaNoua,         PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,        PDO::PARAM_INT);
        $stmt->execute();
    }

    public function finalizeaza(int $idPartida, int $idCastigator): void
    {
        $sql = "UPDATE partide SET status = 'finalizata', faza = 'finalizata', castigator_id = :c WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':c',  $idCastigator, PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,    PDO::PARAM_INT);
        $stmt->execute();
    }

    public function actualizeazaMetadata(int $idPartida, string $nume, int $jucatoriMaxim, int $punctajCastig): void
    {
        $sql = "UPDATE partide SET nume = :n, jucatori_maxim = :jm, punctaj_castig = :pc WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':n',  $nume,           PDO::PARAM_STR);
        $stmt->bindValue(':jm', $jucatoriMaxim,  PDO::PARAM_INT);
        $stmt->bindValue(':pc', $punctajCastig,  PDO::PARAM_INT);
        $stmt->bindValue(':id', $idPartida,      PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $idPartida): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM partide WHERE id = :id");
        $stmt->bindValue(':id', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function arhiveaza(int $idPartida): void
    {
        $stmt = $this->pdo->prepare("UPDATE partide SET status = 'arhivata' WHERE id = :id");
        $stmt->bindValue(':id', $idPartida, PDO::PARAM_INT);
        $stmt->execute();
    }
}
