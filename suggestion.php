<?php
/*
 * Boîte à suggestions anonyme — réception d'une suggestion.
 *
 * Anonymat : seuls le texte, la date et le statut sont enregistrés.
 * Aucun nom, e-mail, téléphone, compte, adresse IP ni navigateur n'est
 * lu ou stocké par cette page, même si le visiteur est connecté.
 *
 * Anti-spam compatible avec l'anonymat (rien n'est conservé en base) :
 *  - champ piège invisible (honeypot) ;
 *  - délai minimal entre l'affichage du formulaire et l'envoi ;
 *  - limite par session de navigation (3 envois par heure) ;
 *  - plafond global (30 suggestions sur 10 minutes, tous visiteurs).
 */
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

// Honeypot : un robot remplit ce champ invisible → on fait comme si tout allait bien
if (trim((string)($_POST['site_web'] ?? '')) !== '') {
    $repondre(true, 'Merci pour votre suggestion.');
}

// Délai minimal (3 s) depuis l'affichage du formulaire
$affiche = (int)($_SESSION['suggestion_affichee'] ?? 0);
if (!$affiche || time() - $affiche < 3) {
    $repondre(false, 'Merci de prendre quelques secondes pour rédiger votre suggestion.');
}

// Limite par session : 3 envois par heure
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

// Plafond global (tous visiteurs confondus)
$recentes = (int)$pdo->query("SELECT COUNT(*) FROM suggestions WHERE created_at > NOW() - INTERVAL 10 MINUTE")->fetchColumn();
if ($recentes >= 30) {
    $repondre(false, 'La boîte à suggestions reçoit beaucoup de messages : merci de réessayer dans quelques minutes.');
}

// Enregistrement : texte uniquement (statut « nouvelle », date automatique)
$pdo->prepare("INSERT INTO suggestions (contenu) VALUES (?)")->execute([$contenu]);

$envois[] = time();
$_SESSION['suggestion_envois'] = array_values($envois);
$_SESSION['suggestion_affichee'] = time(); // un nouvel envoi demande à nouveau quelques secondes

// Notification interne sans aucun élément d'identification
notify('superadmin', 'nouvelle_suggestion', 'Nouvelle suggestion anonyme reçue.', 'suggestions.php');

$repondre(true, 'Merci ! Votre suggestion a bien été transmise, de façon anonyme, à l\'équipe du Palais.');
