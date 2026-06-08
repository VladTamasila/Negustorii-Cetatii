<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * SesiuneRepository - token-ele de autentificare (Bearer token).
 *
 * La login generam un token aleator si il salvam aici cu o data de expirare.
 * La fiecare cerere protejata, AuthMiddleware cauta token-ul si verifica daca
 * mai e valid (neexpirat).
 */
final class SesiuneRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** Creeaza o sesiune noua si intoarce token-ul generat. */
    public function create(int $idUtilizator, int $oreValabilitate = 24): string
    {
        $token = bin2hex(random_bytes(32)); // 64 caractere hex
        $expira = (new \DateTimeImmutable("+{$oreValabilitate} hours"))->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT INTO sesiuni (token, utilizator_id, expira_la)
             VALUES (:t, :u, :e)"
        );
        $stmt->bindValue(':t', $token,         PDO::PARAM_STR);
        $stmt->bindValue(':u', $idUtilizator,  PDO::PARAM_INT);
        $stmt->bindValue(':e', $expira,        PDO::PARAM_STR);
        $stmt->execute();
        return $token;
    }

    /**
     * Intoarce utilizatorul asociat unui token valid (neexpirat), sau null.
     * Aduce si rolul, ca AuthMiddleware sa poata verifica ACL-ul.
     */
    public function findUtilizatorValid(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.utilizator, u.rol, s.expira_la
             FROM sesiuni s
             JOIN utilizatori u ON u.id = s.utilizator_id
             WHERE s.token = :t AND s.expira_la > NOW()
             LIMIT 1"
        );
        $stmt->bindValue(':t', $token, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Sterge o sesiune (logout). */
    public function delete(string $token): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM sesiuni WHERE token = :t");
        $stmt->bindValue(':t', $token, PDO::PARAM_STR);
        $stmt->execute();
    }

    /** Curatenie: sterge sesiunile expirate (apelat ocazional la login). */
    public function stergeExpirate(): void
    {
        $this->pdo->exec("DELETE FROM sesiuni WHERE expira_la <= NOW()");
    }
}
