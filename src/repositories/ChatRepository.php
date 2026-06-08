<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * ChatRepository - mesajele sociale dintr-o partida (chat live).
 */
final class ChatRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(int $idPartida, string $autor, string $text): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO mesaje_chat (partida_id, autor, text)
             VALUES (:p, :a, :t)"
        );
        $stmt->bindValue(':p', $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':a', $autor,     PDO::PARAM_STR);
        $stmt->bindValue(':t', $text,      PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mesajele NOI (id > $dupaId), in ordine cronologica. La fel ca la
     * notificari, clientul face polling cu ultimul id vazut.
     */
    public function listaDupa(int $idPartida, int $dupaId, int $limita = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, autor, text, created_at
             FROM mesaje_chat
             WHERE partida_id = :p AND id > :dupa
             ORDER BY id ASC
             LIMIT :limita"
        );
        $stmt->bindValue(':p',      $idPartida, PDO::PARAM_INT);
        $stmt->bindValue(':dupa',   $dupaId,    PDO::PARAM_INT);
        $stmt->bindValue(':limita', $limita,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
