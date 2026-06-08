<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * UtilizatorRepository - conturile de login (pentru autentificare + ACL).
 */
final class UtilizatorRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** Cauta un utilizator dupa numele de login (folosit la autentificare). */
    public function findByNume(string $utilizator): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM utilizatori WHERE utilizator = :u LIMIT 1");
        $stmt->bindValue(':u', $utilizator, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM utilizatori WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Lista tuturor conturilor (fara hash-ul parolei). */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, utilizator, rol, created_at FROM utilizatori ORDER BY id ASC"
        );
        return $stmt->fetchAll();
    }

    public function create(string $utilizator, string $parolaHash, string $rol): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilizatori (utilizator, parola_hash, rol)
             VALUES (:u, :h, :r)"
        );
        $stmt->bindValue(':u', $utilizator, PDO::PARAM_STR);
        $stmt->bindValue(':h', $parolaHash, PDO::PARAM_STR);
        $stmt->bindValue(':r', $rol,        PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM utilizatori WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** Schimba parola (hash) unui cont. Folosit de admin la resetare. */
    public function actualizeazaParola(int $id, string $parolaHash): void
    {
        $stmt = $this->pdo->prepare("UPDATE utilizatori SET parola_hash = :h WHERE id = :id");
        $stmt->bindValue(':h',  $parolaHash, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id,         PDO::PARAM_INT);
        $stmt->execute();
    }

    public function existaNume(string $utilizator): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM utilizatori WHERE utilizator = :u LIMIT 1");
        $stmt->bindValue(':u', $utilizator, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function countByRol(string $rol): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM utilizatori WHERE rol = :r");
        $stmt->bindValue(':r', $rol, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
