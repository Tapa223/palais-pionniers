<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_activites','ministre']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && ($_POST['action'] ?? '') === 'supprimer') {
            // Suppression d'une erreur ou d'un test : Direction uniquement
            if (!is_superadmin()) {
                $msg = ['err', 'Seule la Direction peut supprimer une inscription.'];
            } else {
                $jSupp = $pdo->prepare("SELECT nom, prenom FROM jeunes_engages WHERE id = ?");
                $jSupp->execute([$id]);
                if ($jSupp = $jSupp->fetch()) {
                    $pdo->prepare("DELETE FROM jeunes_engages WHERE id = ?")->execute([$id]);
                    log_activity('jeune_engage_supprime', 'activites', "Inscription « S'engager » #$id supprimée (erreur ou test) — " . trim(($jSupp['prenom'] ?? '') . ' ' . $jSupp['nom']));
                    $msg = ['ok', 'Inscription supprimée définitivement.'];
                }
            }
        } elseif ($id) {
            $pdo->prepare("UPDATE jeunes_engages SET statut = 'contacte' WHERE id = ?")->execute([$id]);
            $msg = ['ok', 'Marqué comme contacté.'];
        }
    }
}

$filterStatut = $_GET['statut'] ?? 'nouveau';
$where = in_array($filterStatut, ['nouveau','contacte'], true) ? 'WHERE statut = ?' : '';
$stmt = $pdo->prepare("SELECT * FROM jeunes_engages $where ORDER BY created_at DESC");
$stmt->execute($where ? [$filterStatut] : []);
$jeunes = $stmt->fetchAll();

$total = (int)$pdo->query("SELECT COUNT(*) FROM jeunes_engages")->fetchColumn();

$pageTitle = "Jeunes engagés";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Jeunes engagés</h1>
      <p class="text-sm text-slate-500 mt-0.5"><?= $total ?> inscription(s) au total</p>
    </div>
    <a href="export.php?type=jeunes_engages" target="_blank" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-file-excel"></i> Exporter en Excel
    </a>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="flex gap-2 mb-5">
    <?php foreach (['nouveau'=>'Nouveaux','contacte'=>'Contactés'] as $val=>$lab): ?>
    <a href="?statut=<?= $val ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filterStatut===$val ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>"><?= $lab ?></a>
    <?php endforeach; ?>
  </div>

  <div class="space-y-3">
    <?php foreach ($jeunes as $j): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <p class="font-black text-primary text-sm"><?= e(trim(($j['prenom'] ?? '').' '.$j['nom'])) ?><?= $j['age'] ? ', '.(int)$j['age'].' ans' : '' ?><?= !empty($j['date_naissance']) ? ' — né(e) le '.date('d/m/Y', strtotime($j['date_naissance'])) : '' ?></p>
        <p class="text-xs text-slate-400 mt-0.5"><?= e($j['telephone']) ?><?= $j['email'] ? ' · '.e($j['email']) : '' ?><?= $j['commune'] ? ' · '.e($j['commune']) : '' ?><?= $j['profession'] ? ' · '.e($j['profession']) : '' ?></p>
        <?php if ($j['domaine_interet']): ?><p class="text-xs text-accent font-bold mt-1"><?= e($j['domaine_interet']) ?></p><?php endif; ?>
        <?php if ($j['motivation']): ?><p class="text-xs text-slate-500 mt-1 italic">« <?= e($j['motivation']) ?> »</p><?php endif; ?>
      </div>
      <?php if ($j['statut'] === 'nouveau' && !$readonly): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= $j['id'] ?>">
        <button type="submit" class="bg-emerald-500 text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-emerald-600 transition flex-shrink-0">Marquer contacté</button>
      </form>
      <?php elseif ($j['statut'] === 'contacte'): ?>
      <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700 flex-shrink-0">Contacté</span>
      <?php endif; ?>
      <?php if (is_superadmin()): ?>
      <form method="POST" class="flex-shrink-0" onsubmit="return confirm('Supprimer définitivement cette inscription ? (erreur ou test)')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
        <button type="submit" class="text-[10px] font-black uppercase text-slate-400 hover:text-red-600 transition" title="Supprimer définitivement (erreur ou test)"><i class="fas fa-trash-alt mr-1"></i>Supprimer</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$jeunes): ?>
    <p class="text-sm text-slate-400 italic text-center py-10">Aucune inscription dans cette catégorie.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
