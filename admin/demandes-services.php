<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_espaces','ministre','admin_comptable']);

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
            $pdo->prepare("UPDATE demandes_services SET statut = 'traitee', traite_par = ?, date_traitement = NOW(), note_traitement = ? WHERE id = ?")
                ->execute([$_SESSION['user_id'], $note ?: null, $id]);
            log_activity('demande_service_traitee', 'espaces', "Demande de service #$id marquée traitée");
            $msg = ['ok', 'Demande marquée comme traitée.'];
        }
    }
}

$filterStatut = $_GET['statut'] ?? 'en_attente';
$where = [];
if (in_array($filterStatut, ['en_attente','traitee'], true)) { $where[] = 'ds.statut = ?'; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$demandes = $pdo->prepare("
    SELECT ds.*, s.nom AS service_nom, s.montant, s.unite, u.nom_complet, u.telephone, u.email,
           admin.nom_complet AS traite_par_nom
    FROM demandes_services ds
    JOIN services_annexes s ON s.id = ds.service_id
    JOIN users u ON u.id = ds.user_id
    LEFT JOIN users admin ON admin.id = ds.traite_par
    $whereSQL
    ORDER BY ds.created_at DESC
");
$demandes->execute(in_array($filterStatut, ['en_attente','traitee'], true) ? [$filterStatut] : []);
$demandes = $demandes->fetchAll();

$pageTitle = "Demandes de services";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Demandes de services</h1>
      <p class="text-sm text-slate-500 mt-0.5">Demandes de prestations annexes, faites depuis des comptes clients connectés</p>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="flex gap-2 mb-5">
    <?php foreach (['en_attente'=>'En attente','traitee'=>'Traitées'] as $val=>$lab): ?>
    <a href="?statut=<?= $val ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filterStatut===$val ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>"><?= $lab ?></a>
    <?php endforeach; ?>
  </div>

  <div class="space-y-4">
    <?php foreach ($demandes as $d): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
          <p class="font-black text-primary text-sm"><?= e($d['service_nom']) ?></p>
          <p class="text-xs text-slate-400 mt-0.5"><?= e($d['nom_complet']) ?> · <?= e($d['telephone']) ?><?= $d['email'] ? ' · '.e($d['email']) : '' ?></p>
        </div>
        <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full <?= $d['statut']==='en_attente' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' ?>">
          <?= $d['statut']==='en_attente' ? 'En attente' : 'Traitée' ?>
        </span>
      </div>
      <p class="text-xs text-slate-500 mb-3">Tarif indicatif : <?= number_format((float)$d['montant'],0,',',' ') ?> FCFA/<?= e($d['unite']) ?> · demandé le <?= date('d/m/Y à H:i', strtotime($d['created_at'])) ?></p>
      <?php if (!empty($d['message'])): ?>
      <p class="text-xs text-slate-600 bg-slate-50 rounded-xl p-3 italic mb-3">« <?= e($d['message']) ?> »</p>
      <?php endif; ?>

      <?php if ($d['statut'] === 'traitee'): ?>
      <p class="text-[10px] text-slate-400">Traitée par <?= e($d['traite_par_nom'] ?? '—') ?> le <?= date('d/m/Y à H:i', strtotime($d['date_traitement'])) ?><?= $d['note_traitement'] ? ' — '.e($d['note_traitement']) : '' ?></p>
      <?php elseif (!$readonly): ?>
      <form method="POST" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $d['id'] ?>">
        <input type="text" name="note_traitement" placeholder="Note (optionnel)" class="flex-1 min-w-[160px] rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-primary outline-none focus:border-primary">
        <button type="submit" class="bg-emerald-500 text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-emerald-600 transition">Marquer traitée</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$demandes): ?>
    <p class="text-sm text-slate-400 italic text-center py-10">Aucune demande dans cette catégorie.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
