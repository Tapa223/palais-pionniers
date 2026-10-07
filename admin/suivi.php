<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/_suivi_ligne.php';

$pdo      = db();
$installe = suivi_disponible($pdo);
$msg      = null;
$vues     = ['a_venir' => 'À venir', 'a_confirmer' => 'À confirmer', 'effectuees' => 'Effectuées'];

if ($installe && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $code   = 'erreur';
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $code = 'requete';
    } elseif (!peut_cocher_effectuee()) {
        $code = 'droits';
    } elseif ($id && $action === 'effectuer') {
        $st = $pdo->prepare("
            UPDATE reservations r JOIN espaces e ON e.id = r.espace_id
            SET r.effectuee_le = NOW(), r.effectuee_par = ?
            WHERE r.id = ? AND r.effectuee_le IS NULL AND r.statut = 'validee'
              AND r.statut_paiement IN ('paye', 'partiellement_paye')
              AND (CASE WHEN e.mode_reservation = 'sejour' THEN TIMESTAMP(r.date_resa, '14:00:00')
                        ELSE TIMESTAMP(r.date_resa, COALESCE(r.heure_debut, '00:00:00')) END) <= NOW()
        ");
        $st->execute([(int)$_SESSION['user_id'], $id]);
        if ($st->rowCount()) {
            log_activity('resa_effectuee', 'reservations', 'Réservation ' . ref_resa($id) . ' confirmée comme effectuée');
            $code = 'effectuee';
        } else {
            $code = 'impossible';
        }
    } elseif ($id && $action === 'annuler_effectuee') {
        $st = $pdo->prepare("UPDATE reservations SET effectuee_le = NULL, effectuee_par = NULL WHERE id = ? AND effectuee_le IS NOT NULL");
        $st->execute([$id]);
        if ($st->rowCount()) {
            log_activity('resa_effectuee_annulee', 'reservations', 'Confirmation « Effectuée » retirée pour la réservation ' . ref_resa($id));
            $code = 'decochee';
        }
    }
    $retour = ($_POST['retour'] ?? '') === 'dashboard'
        ? 'dashboard.php?suivi=' . $code . '#suivi'
        : 'suivi.php?vue=' . (isset($vues[$_POST['retour'] ?? '']) ? $_POST['retour'] : 'a_venir') . '&suivi=' . $code;
    header('Location: ' . $retour);
    exit;
}

$messages = [
    'effectuee'  => ['ok', 'Réservation confirmée comme effectuée.'],
    'decochee'   => ['ok', 'Confirmation « Effectuée » retirée.'],
    'impossible' => ['err', 'Cette réservation ne peut pas encore être cochée : elle doit être validée, payée (au moins un acompte) et avoir commencé.'],
    'droits'     => ['err', 'Votre rôle permet de consulter le suivi, pas de cocher « Effectuée ».'],
    'requete'    => ['err', 'Requête invalide : rechargez la page et recommencez.'],
];
if (isset($messages[$_GET['suivi'] ?? ''])) {
    $msg = $messages[$_GET['suivi']];
}

$vue = isset($vues[$_GET['vue'] ?? '']) ? $_GET['vue'] : 'a_venir';
$compteurs = [];
foreach (array_keys($vues) as $v) {
    $compteurs[$v] = $installe ? compter_suivi($pdo, $v) : 0;
}
$liste = $installe ? reservations_suivi($pdo, $vue, 200) : [];

$pageTitle = 'Suivi des réservations';
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Suivi des réservations</h1>
    <p class="text-sm text-slate-500 mt-0.5">Réservations payées (soldées ou avec acompte) : à venir, à confirmer et effectuées</p>
  </div>
  <?php if (!peut_cocher_effectuee()): ?>
  <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Consultation</span>
  <?php endif; ?>
</div>

<?php if (!$installe): ?>
<div class="mb-5 rounded-2xl p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm font-bold">
  <i class="fas fa-circle-info mr-2"></i>Le suivi n'est pas encore installé : exécutez le fichier database/migration_suivi_effectuee.sql dans phpMyAdmin.
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<div class="flex gap-2 mb-5 flex-wrap">
  <?php foreach ($vues as $v => $lib): ?>
  <a href="?vue=<?= $v ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $vue === $v ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
    <?= $lib ?> (<?= $compteurs[$v] ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <?php if (!$liste): ?>
  <p class="px-5 py-10 text-center text-sm text-slate-400">
    <?= ['a_venir' => 'Aucune réservation payée en cours ou dans les 7 prochains jours.', 'a_confirmer' => 'Aucune réservation en attente de confirmation.', 'effectuees' => 'Aucune réservation confirmée pour le moment.'][$vue] ?>
  </p>
  <?php else: ?>
  <ul class="divide-y divide-slate-50">
    <?php foreach ($liste as $r) { suivi_ligne($r, $vue); } ?>
  </ul>
  <?php endif; ?>
</div>
<?php if ($vue === 'effectuees' && count($liste) >= 200): ?>
<p class="text-xs text-slate-400 mt-3">Les 200 confirmations les plus récentes sont affichées.</p>
<?php endif; ?>

<?php require __DIR__ . '/_admin_footer.php'; ?>
