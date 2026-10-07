<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$json = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

$repondre = function (bool $ok, string $message) use ($json): void {
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: index.php?suggestion=' . ($ok ? 'merci' : 'erreur') . '#suggestion');
    }
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $repondre(false, 'Session expirée : rechargez la page puis réessayez.');
}

if (trim((string)($_POST['site_web'] ?? '')) !== '') {
    $repondre(true, 'Merci pour votre suggestion.');
}

$affiche = (int)($_SESSION['suggestion_affichee'] ?? 0);
if (!$affiche || time() - $affiche < 3) {
    $repondre(false, 'Merci de prendre quelques secondes pour rédiger votre suggestion.');
}

$envois = array_filter($_SESSION['suggestion_envois'] ?? [], fn($t) => $t > time() - 3600);
if (count($envois) >= 3) {
    $repondre(false, 'Vous avez déjà envoyé plusieurs suggestions : merci de réessayer un peu plus tard.');
}

$contenu = trim(strip_tags((string)($_POST['contenu'] ?? '')));
$contenu = preg_replace("/\r\n?/", "\n", $contenu);
if (mb_strlen($contenu) < 10) {
    $repondre(false, 'Votre suggestion est trop courte (10 caractères minimum).');
}
if (mb_strlen($contenu) > 2000) {
    $repondre(false, 'Votre suggestion est trop longue (2000 caractères maximum).');
}

$pdo = db();
if (!suggestions_disponibles($pdo)) {
    $repondre(false, 'La boîte à suggestions est momentanément indisponible.');
}

$recentes = (int)$pdo->query("SELECT COUNT(*) FROM suggestions WHERE created_at > NOW() - INTERVAL 10 MINUTE")->fetchColumn();
if ($recentes >= 30) {
    $repondre(false, 'La boîte à suggestions reçoit beaucoup de messages : merci de réessayer dans quelques minutes.');
}

$pdo->prepare("INSERT INTO suggestions (contenu) VALUES (?)")->execute([$contenu]);

$envois[] = time();
$_SESSION['suggestion_envois'] = array_values($envois);
$_SESSION['suggestion_affichee'] = time();

notify('superadmin', 'nouvelle_suggestion', 'Nouvelle suggestion anonyme reçue.', 'suggestions.php');

$repondre(true, 'Merci ! Votre suggestion a bien été transmise, de façon anonyme, à l\'équipe du Palais.');
