<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode([]); exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$pdo  = db();
$stmt = $pdo->prepare("
    SELECT id, nom_complet, email, telephone
    FROM users
    WHERE (nom_complet LIKE ? OR email LIKE ? OR telephone LIKE ?)
    AND role = 'user' AND actif = 1
    ORDER BY nom_complet ASC
    LIMIT 10
");
$stmt->execute(["%$q%","%$q%","%$q%"]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));