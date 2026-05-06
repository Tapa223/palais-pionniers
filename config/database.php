<?php
/**
 * Configuration de la connexion MySQL via PDO
 * À adapter selon votre environnement local (XAMPP/WAMP)
 */

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'palais_pionniers';
const DB_USER = 'root';
const DB_PASS = ''; // ⚠ XAMPP par défaut : vide. WAMP : 'root' ou ''.
const DB_CHARSET = 'utf8mb4';

/**
 * Retourne une instance PDO singleton.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // En production, ne JAMAIS afficher le détail de l'erreur.
        http_response_code(500);
        die('Erreur de connexion à la base de données.');
    }

    return $pdo;
}
