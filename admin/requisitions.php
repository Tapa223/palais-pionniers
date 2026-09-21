<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_comptable','ministre']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $id   = (int)($_POST['id'] ?? 0);
        $note = trim($_POST['note_traitement'] ?? '');
        if ($id) {
            $checkChoix = $pdo->prepare("SELECT choix_client FROM requisitions_ministerielles WHERE id = ?");
            $checkChoix->execute([$id]);
            $choixActuel = $checkChoix->fetchColumn();

            if ($choixActuel === 'remboursement' && $note === '') {
                $msg = ['err', 'Merci de préciser comment le remboursement a été effectué.'];
            } else {
                $pdo->prepare("UPDATE requisitions_ministerielles SET statut='traite', traite_par=?, date_traitement=NOW(), note_traitement=? WHERE id=?")
                    ->execute([$_SESSION['user_id'], $note ?: null, $id]);
                log_activity('requisition_traitee', 'reservations', "Réquisition #$id marquée traitée");
                $msg = ['ok', 'Réquisition marquée comme traitée.'];
            }
        }
    }
}

$filtreStatut = $_GET['statut'] ?? 'en_attente_choix';
$where = in_array($filtreStatut, ['en_attente_choix','choix_fait','traite'], true)
    ? ($filtreStatut === 'choix_fait' ? "rm.statut = 'en_attente_choix' AND rm.choix_client IS NOT NULL" : ($filtreStatut === 'en_attente_choix' ? "rm.statut = 'en_attente_choix' AND rm.choix_client IS NULL" : "rm.statut = 'traite'"))
    : "1=1";

$stmt = $pdo->query("
    SELECT rm.*, r.date_resa, e.nom AS espace_nom, u.nom_complet, u.telephone,
           admin1.nom_complet AS declenche_par_nom, admin2.nom_complet AS traite_par_nom,
           (SELECT COALESCE(SUM(montant),0) FROM paiements WHERE reservation_id = r.id) AS montant_verse
    FROM requisitions_ministerielles rm
    JOIN reservations r ON r.id = rm.reservation_id
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN users admin1 ON admin1.id = rm.declenche_par
    LEFT JOIN users admin2 ON admin2.id = rm.traite_par
    WHERE $where
    ORDER BY rm.date_declenchee DESC
");
$requisitions = $stmt->fetchAll();

// Compteurs pour chaque onglet, pour que ce soit visible d'un coup d'œil
$compteurs = [
    'en_attente_choix' => (int)$pdo->query("SELECT COUNT(*) FROM requisitions_ministerielles WHERE statut = 'en_attente_choix' AND choix_client IS NULL")->fetchColumn(),
    'choix_fait'        => (int)$pdo->query("SELECT COUNT(*) FROM requisitions_ministerielles WHERE statut = 'en_attente_choix' AND choix_client IS NOT NULL")->fetchColumn(),
    'traite'            => (int)$pdo->query("SELECT COUNT(*) FROM requisitions_ministerielles WHERE statut = 'traite'")->fetchColumn(),
];

$libellesChoix = ['remboursement' => 'Remboursement', 'nouvelle_date' => 'Nouvelle date', 'autre_espace' => 'Autre espace'];

$pageTitle = "Réquisitions ministérielles";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="mb-6">
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight"><i class="fas fa-landmark text-amber-500 mr-2"></i>Réquisitions ministérielles</h1>
    <p class="text-sm text-slate-500 mt-0.5">Suivi complet des espaces réquisitionnés pour un besoin institutionnel prioritaire</p>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 bg-green-50 border border-green-200 text-green-700">
    <i class="fas fa-check-circle text-green-500"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="flex gap-2 mb-6 flex-wrap">
    <a href="?statut=en_attente_choix" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtreStatut==='en_attente_choix' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">En attente du choix client (<?= $compteurs['en_attente_choix'] ?>)</a>
    <a href="?statut=choix_fait" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtreStatut==='choix_fait' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">Choix fait, à traiter (<?= $compteurs['choix_fait'] ?>)</a>
    <a href="?statut=traite" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtreStatut==='traite' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">Traitées (<?= $compteurs['traite'] ?>)</a>
  </div>

  <div class="space-y-4">
    <?php foreach ($requisitions as $rq): ?>
    <div class="bg-white rounded-2xl border <?= $rq['choix_client'] && $rq['statut'] === 'en_attente_choix' ? 'border-amber-300' : 'border-slate-100' ?> shadow-sm p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
          <p class="font-black text-primary text-sm"><?= e($rq['espace_nom']) ?> <span class="text-slate-400 font-normal">— <?= date('d/m/Y', strtotime($rq['date_resa'])) ?></span></p>
          <p class="text-xs text-slate-400 mt-0.5"><?= e($rq['nom_complet']) ?> · <?= e($rq['telephone']) ?></p>
        </div>
        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full <?= $rq['statut']==='traite' ? 'bg-emerald-100 text-emerald-700' : ($rq['choix_client'] ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500') ?>">
          <?= $rq['statut']==='traite' ? 'Traitée' : ($rq['choix_client'] ? 'Choix fait — à traiter' : 'En attente du client') ?>
        </span>
      </div>

      <p class="text-xs text-slate-500 bg-slate-50 rounded-xl p-3 mb-3"><i class="fas fa-info-circle mr-1.5"></i><?= e($rq['motif']) ?></p>
      <p class="text-[10px] text-slate-400 mb-3">Déclenchée par <?= e($rq['declenche_par_nom'] ?? '—') ?> le <?= date('d/m/Y à H:i', strtotime($rq['date_declenchee'])) ?></p>

      <?php if ($rq['choix_client']): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-3">
        <p class="text-xs font-black text-amber-700 uppercase mb-1"><i class="fas fa-hand-point-right mr-1"></i>Choix du client : <?= e($libellesChoix[$rq['choix_client']] ?? $rq['choix_client']) ?></p>
        <?php if ($rq['choix_client'] === 'remboursement'): ?>
        <p class="text-sm font-black text-accent"><i class="fas fa-coins mr-1"></i>Montant à rembourser : <?= number_format((float)$rq['montant_verse'],0,',',' ') ?> FCFA</p>
        <?php endif; ?>
        <?php if (!empty($rq['details_choix'])): ?>
        <p class="text-xs text-amber-700">« <?= e($rq['details_choix']) ?> »</p>
        <?php endif; ?>
        <p class="text-[10px] text-amber-500 mt-1">Choisi le <?= date('d/m/Y à H:i', strtotime($rq['date_choix'])) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($rq['statut'] === 'traite'): ?>
      <p class="text-[10px] text-emerald-600"><i class="fas fa-check-circle mr-1"></i>Traitée par <?= e($rq['traite_par_nom'] ?? '—') ?> le <?= date('d/m/Y à H:i', strtotime($rq['date_traitement'])) ?><?= $rq['note_traitement'] ? ' — '.e($rq['note_traitement']) : '' ?></p>
      <?php elseif ($rq['choix_client'] && !$readonly): ?>
      <?php $estRemboursement = $rq['choix_client'] === 'remboursement'; ?>
      <form method="POST" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $rq['id'] ?>">
        <input type="text" name="note_traitement" <?= $estRemboursement ? 'required' : '' ?>
               placeholder="<?= $estRemboursement ? 'Comment le remboursement a été fait (obligatoire — ex : espèces, Orange Money...)' : 'Note de traitement (optionnel)' ?>"
               class="flex-1 min-w-[220px] rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-primary outline-none focus:border-primary">
        <button type="submit" class="bg-emerald-500 text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-emerald-600 transition"><?= $estRemboursement ? 'Marquer remboursé' : 'Marquer traitée' ?></button>
      </form>
      <?php elseif (!$rq['choix_client']): ?>
      <p class="text-[10px] text-slate-400 italic">Le client n'a pas encore fait son choix.</p>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$requisitions): ?>
    <p class="text-sm text-slate-400 italic text-center py-10">Aucune réquisition dans cette catégorie.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
