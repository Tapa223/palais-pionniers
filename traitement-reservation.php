<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_client('admin/dashboard.php');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: espaces.php');
    exit;
}

// CSRF
if (!csrf_check($_POST['csrf_token'] ?? '')) {
    header('Location: reserver.php?error=csrf');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$espace_id  = (int)($_POST['espace_id'] ?? 0);
$tarif_id   = !empty($_POST['tarif_id']) ? (int)$_POST['tarif_id'] : null;
$date_resa  = trim($_POST['date_resa'] ?? '');
$date_depart = trim($_POST['date_depart'] ?? '');
$heure_debut = trim($_POST['heure_debut'] ?? '');
$heure_fin   = trim($_POST['heure_fin'] ?? '');
$petit_dej  = !empty($_POST['petit_dejeuner']) ? 1 : 0;
$vip        = !empty($_POST['vip']) ? 1 : 0;
$quantite   = max(1, (int)($_POST['quantite'] ?? 1));
$motif      = trim($_POST['motif'] ?? '');
$telephone  = trim($_POST['telephone'] ?? '');

if (!$espace_id) { header('Location: espaces.php'); exit; }

$espace = $pdo->prepare("SELECT * FROM espaces WHERE id = ?");
$espace->execute([$espace_id]);
$espace = $espace->fetch();
if (!$espace) { header('Location: espaces.php'); exit; }

if (!empty($espace['gerant_externe'])) {
    header('Location: espace.php?slug=' . urlencode($espace['slug']) . '&error=' . urlencode('Cet espace est géré par un tiers, veuillez le/la contacter directement.'));
    exit;
}

$estSejour = ($espace['mode_reservation'] === 'sejour');

// Le petit-déjeuner n'est proposé que par les espaces qui l'activent réellement
// (ex: pas au Necker) — on ne fait jamais confiance à la case cochée côté client.
if (!$estSejour || !$espace['option_petit_dejeuner']) $petit_dej = 0;
if (!$espace['option_vip']) $vip = 0;

// --- Validations basiques ---
$errors = [];

$stmtActif = $pdo->prepare("SELECT actif FROM users WHERE id = ?");
$stmtActif->execute([$user_id]);
if (!$stmtActif->fetchColumn()) {
    $errors[] = "Votre compte ne peut pas effectuer de nouvelle réservation pour le moment. Merci de vous rapprocher de l'administration du Palais.";
}

if (!$tarif_id)  $errors[] = "Veuillez sélectionner un tarif.";
if ($tarif_id) {
    $checkTarif = $pdo->prepare("SELECT est_bail FROM tarifs WHERE id = ? AND espace_id = ?");
    $checkTarif->execute([$tarif_id, $espace_id]);
    $estBailTarif = $checkTarif->fetchColumn();
    if ($estBailTarif === false || (int)$estBailTarif === 1) {
        $errors[] = "Ce tarif n'est pas réservable en ligne — merci de faire une demande de bail si c'est ce que vous recherchez.";
    }
}
if (!$date_resa || $date_resa < date('Y-m-d')) $errors[] = "Date invalide.";

if ($estSejour) {
    if (!$date_depart || $date_depart <= $date_resa) $errors[] = "La date de départ doit être après la date d'arrivée.";
} else {
    if (!$heure_debut || !$heure_fin) $errors[] = "Horaires invalides.";
    if ($heure_fin <= $heure_debut) $errors[] = "L'heure de fin doit être après l'heure de début.";
    $quantite = 1; // la quantité multiple ne concerne que les espaces en séjour (chambres)
}

if ($errors) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: reserver.php?espace_id=$espace_id&error=" . $msg);
    exit;
}

$heure_debut_fmt = $estSejour ? null : $heure_debut . ':00';
$heure_fin_fmt   = $estSejour ? null : $heure_fin   . ':00';

try {
    // --- Anti-conflit : uniquement pour l'hébergement (inventaire réel de chambres) ---
    // Pour les créneaux (salles), toute demande est acceptée : c'est le premier
    // paiement enregistré au guichet qui départage définitivement en cas de
    // créneau partagé entre plusieurs demandes validées.
    if ($estSejour) {
        $disponible = tarif_disponible(
            $pdo, $tarif_id, $date_resa, $date_depart, null, null, null, $quantite
        );
        if (!$disponible) {
            $messageComplet = "Complet pour ces dates. Merci de choisir d'autres dates ou une autre catégorie de chambre.";
            header("Location: reserver.php?espace_id=$espace_id&error=" . urlencode($messageComplet));
            exit;
        }
    }

    // --- Insertion ---
    $stmt = $pdo->prepare("
        INSERT INTO reservations
            (user_id, espace_id, tarif_id, date_resa, date_depart, heure_debut, heure_fin, petit_dejeuner, vip, quantite, statut, motif, created_at, notification_vue)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, NOW(), 0)
    ");
    $stmt->execute([$user_id, $espace_id, $tarif_id, $date_resa, $estSejour ? $date_depart : null, $heure_debut_fmt, $heure_fin_fmt, $petit_dej, $vip, $quantite, $motif]);
    $resaId = (int)$pdo->lastInsertId();

    // Notifier admin_espaces : une nouvelle demande attend sa validation
    $nomClient = $_SESSION['nom_complet'] ?? 'Un client';
    $periode = $estSejour
        ? ('du ' . date('d/m/Y', strtotime($date_resa)) . ' au ' . date('d/m/Y', strtotime($date_depart)) . ($quantite > 1 ? " ($quantite chambres)" : ''))
        : ('le ' . date('d/m/Y', strtotime($date_resa)));
    notify('admin_espaces', 'nouvelle_reservation',
        "Nouvelle demande de $nomClient pour « {$espace['nom']} » $periode",
        "reservations.php"
    );
    notify('superadmin', 'nouvelle_reservation',
        "Nouvelle demande de $nomClient pour « {$espace['nom']} » $periode",
        "reservations.php"
    );

    // Mise à jour du téléphone si absent
    if (!empty($telephone)) {
        $pdo->prepare("UPDATE users SET telephone = ? WHERE id = ? AND (telephone IS NULL OR telephone = '')")
            ->execute([$telephone, $user_id]);
    }

    header('Location: mon-compte.php?success=1');
    exit;

} catch (PDOException $e) {
    header("Location: reserver.php?espace_id=$espace_id&error=" . urlencode("Erreur technique. Veuillez réessayer."));
    exit;
}