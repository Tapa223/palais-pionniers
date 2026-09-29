<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre', 'admin_espaces', 'admin_activites', 'admin_messages', 'admin_comptable']);

/*
 * Boîte à suggestions anonyme : consultation et suivi.
 * Consultation : Direction, ministre (lecture seule) et profils
 * d'administration. Changement de statut : tous sauf le ministre.
 * Les suggestions ne contiennent aucune donnée d'identification.
 */
$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;
$installe = suggestions_disponibles($pdo);
$statuts  = ['nouvelle' => 'Nouvelles', 'lue' => 'Lues', 'traitee' => 'Traitées'];

if ($installe && !$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $nouveau = $_POST['statut'] ?? '';
        if ($id && ($_POST['action'] ?? '') === 'supprimer') {
            // Ménage : Direction uniquement, et seulement une suggestion déjà traitée
            if (!is_superadmin()) {
                $msg = ['err', 'Seule la Direction peut supprimer une suggestion.'];
            } else {
                $st = $pdo->prepare("DELETE FROM suggestions WHERE id = ? AND statut = 'traitee'");
                $st->execute([$id]);
                if ($st->rowCount() === 1) {
                    log_activity('suggestion_supprimee', 'messages', "Suggestion SUG-$id (traitée) supprimée");
                    $msg = ['ok', "Suggestion SUG-$id supprimée."];
                } else {
                    $msg = ['err', 'Seule une suggestion marquée « Traitée » peut être supprimée.'];
                }
            }
        } elseif ($id && isset($statuts[$nouveau])) {
            $st = $pdo->prepare("UPDATE suggestions SET statut = ?, statut_modifie_par = ?, statut_modifie_le = NOW() WHERE id = ?");
            $st->execute([$nouveau, $_SESSION['user_id'], $id]);
            if ($st->rowCount() === 1) {
                log_activity('suggestion_' . $nouveau, 'messages', "Suggestion #$id marquée « $nouveau »");
            }
            $msg = ['ok', 'Statut mis à jour.'];
        } else {
            $msg = ['err', 'Action non reconnue.'];
        }
    }
}

$filtre = isset($statuts[$_GET['statut'] ?? '']) || ($_GET['statut'] ?? '') === 'toutes' ? $_GET['statut'] : 'nouvelle';
$suggestions = [];
$compteurs = [];
if ($installe) {
    foreach (array_keys($statuts) as $s) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM suggestions WHERE statut = ?");
        $st->execute([$s]);
        $compteurs[$s] = (int)$st->fetchColumn();
    }
    $compteurs['toutes'] = array_sum($compteurs);
    if ($filtre === 'toutes') {
        $suggestions = $pdo->query("SELECT sg.*, u.nom_complet AS modifie_nom FROM suggestions sg LEFT JOIN users u ON u.id = sg.statut_modifie_par ORDER BY sg.created_at DESC")->fetchAll();
    } else {
        $st = $pdo->prepare("SELECT sg.*, u.nom_complet AS modifie_nom FROM suggestions sg LEFT JOIN users u ON u.id = sg.statut_modifie_par WHERE sg.statut = ? ORDER BY sg.created_at DESC");
        $st->execute([$filtre]);
        $suggestions = $st->fetchAll();
    }
}
$couleurs = ['nouvelle' => 'bg-amber-100 text-amber-700', 'lue' => 'bg-sky-100 text-sky-700', 'traitee' => 'bg-emerald-100 text-emerald-700'];
$libelles = ['nouvelle' => 'Nouvelle', 'lue' => 'Lue', 'traitee' => 'Traitée'];

$pageTitle = 'Suggestions';
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Suggestions</h1>
    <p class="text-sm text-slate-500 mt-0.5">Boîte à suggestions anonyme du site (aucune donnée sur l'auteur n'est conservée)</p>
  </div>
  <?php if ($installe): ?>
  <a href="export.php?type=suggestions" class="flex items-center gap-2 bg-white border border-slate-200 text-primary text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-slate-50 transition shadow-sm">
    <i class="fas fa-file-csv"></i> Exporter (CSV)
  </a>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<?php if (!$installe): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-800">
  La boîte à suggestions n'est pas encore installée : exécutez le fichier
  <code class="font-mono">database/migration_partenaires_suggestions_services.sql</code> dans phpMyAdmin.
</div>
<?php else: ?>

<div class="flex flex-wrap gap-2 mb-5">
  <?php foreach ($statuts + ['toutes' => 'Toutes'] as $cle => $lib): ?>
  <a href="?statut=<?= $cle ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtre === $cle ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>"><?= $lib ?> (<?= $compteurs[$cle] ?? 0 ?>)</a>
  <?php endforeach; ?>
</div>

<div class="space-y-3">
  <?php foreach ($suggestions as $sg): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
      <p class="text-[11px] text-slate-400 font-bold">SUG-<?= (int)$sg['id'] ?> · reçue le <?= date('d/m/Y à H:i', strtotime($sg['created_at'])) ?></p>
      <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full <?= $couleurs[$sg['statut']] ?? '' ?>"><?= $libelles[$sg['statut']] ?? e($sg['statut']) ?></span>
    </div>
    <p class="text-sm text-slate-700 whitespace-pre-line" style="overflow-wrap:anywhere"><?= e($sg['contenu']) ?></p>
    <?php if (!empty($sg['statut_modifie_le'])): ?>
    <p class="text-[10px] text-slate-400 mt-2">Statut modifié par <?= e($sg['modifie_nom'] ?? '—') ?> le <?= date('d/m/Y à H:i', strtotime($sg['statut_modifie_le'])) ?></p>
    <?php endif; ?>
    <?php if (!$readonly): ?>
    <form method="POST" class="flex flex-wrap gap-2 mt-3">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" value="<?= (int)$sg['id'] ?>">
      <?php foreach ($libelles as $cle => $lib): if ($cle === $sg['statut']) continue; ?>
      <button type="submit" name="statut" value="<?= $cle ?>" class="text-[11px] font-black uppercase px-3 py-1.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition">Marquer « <?= $lib ?> »</button>
      <?php endforeach; ?>
      <?php if (is_superadmin() && $sg['statut'] === 'traitee'): ?>
      <button type="submit" name="action" value="supprimer" onclick="return confirm('Supprimer définitivement cette suggestion traitée ?')"
              class="text-[11px] font-black uppercase px-3 py-1.5 rounded-xl border border-rose-100 text-rose-500 hover:bg-rose-50 transition">Supprimer</button>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$suggestions): ?>
  <p class="text-sm text-slate-400 italic text-center py-10">Aucune suggestion dans cette catégorie.</p>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/_admin_footer.php'; ?>
