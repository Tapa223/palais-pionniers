<?php
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json');

$espace_id = isset($_GET['espace_id']) ? (int)$_GET['espace_id'] : 0;

if ($espace_id > 0) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, libelle, montant, unite FROM tarifs WHERE espace_id = ? ORDER BY montant ASC");
    $stmt->execute([$espace_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    echo json_encode([]);
}