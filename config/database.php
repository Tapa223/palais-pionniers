<?php
declare(strict_types=1);

// Identifiants du serveur en ligne : config/database.local.php (voir database.local.exemple.php)
if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', 3306);
defined('DB_NAME')    || define('DB_NAME', 'palais_pionniers');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');
defined('APP_DEBUG')  || define('APP_DEBUG', in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1'], true));

date_default_timezone_set('Africa/Bamako');
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

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
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        error_log('Connexion base de données : ' . $e->getMessage());
        http_response_code(500);
        die('Erreur de connexion à la base de données.');
    }

    return $pdo;
}
