<?php
/**
 * db_config.php - Conexiune PDO pentru server.php (jocul standalone)
 *
 * Folosit de server.php care gestioneaza jocul cu resurse Aur/Lemn/Fier/Argilă.
 * Schema necesara: vezi database/games_schema.sql
 */

$host    = '127.0.0.1';
$port    = '3306';
$dbname  = 'negustorii_cetatii';
$user    = 'root';
$pass    = '';  // XAMPP implicit nu are parola

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}
