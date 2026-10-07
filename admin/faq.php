<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre', 'admin_activites', 'admin_espaces']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;
$installe = faq_disponible($pdo);

if ($installe && !$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            $question  = trim((string)($_POST['question'] ?? ''));
            $reponse   = trim((string)($_POST['reponse'] ?? ''));
            $categorie = trim((string)($_POST['categorie'] ?? '')) ?: null;
            $ordre     = (int)($_POST['ordre'] ?? 0);
            $actif     = !empty($_POST['actif']) ? 1 : 0;

            if (mb_strlen($question) < 5 || mb_strlen($question) > 255) {
                $msg = ['err', 'La question est obligatoire (5 à 255 caractères).'];
            } elseif (mb_strlen($reponse) < 5) {
                $msg = ['err', 'La réponse est obligatoire.'];
            } elseif ($categorie !== null && mb_strlen($categorie) > 100) {
                $msg = ['err', 'La catégorie ne doit pas dépasser 100 caractères.'];
            } else {
                if ($id) {
                    $pdo->prepare("UPDATE faq SET question = ?, reponse = ?, categorie = ?, ordre = ?, actif = ? WHERE id = ?")
                        ->execute([$question, $reponse, $categorie, $ordre, $actif, $id]);
                    log_activity('faq_modifiee', 'activites', "Question de la FAQ modifiée : $question");
                } else {
                    $pdo->prepare("INSERT INTO faq (question, reponse, categorie, ordre, actif) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$question, $reponse, $categorie, $ordre, $actif]);
                    log_activity('faq_creee', 'activites', "Question ajoutée à la FAQ : $question");
                }
                header('Location: faq.php?success=1'); exit;
            }
        }

        if ($action === 'toggle' && $id) {
            $pdo->prepare("UPDATE faq SET actif = 1 - actif WHERE id = ?")->execute([$id]);
            log_activity('faq_modifiee', 'activites', "Visibilité de la question #$id modifiée");
            header('Location: faq.php?success=1'); exit;
        }

        if ($action === 'delete' && $id) {
            $q = $pdo->prepare("SELECT question FROM faq WHERE id = ?");
            $q->execute([$id]);
            if ($libelle = $q->fetchColumn()) {
                $pdo->prepare("DELETE FROM faq WHERE id = ?")->execute([$id]);
                log_activity('faq_supprimee', 'activites', "Question supprimée de la FAQ : $libelle");
            }
            header('Location: faq.php?success=1'); exit;
        }
    }
}

if (isset($_GET['success'])) $msg = ['ok', 'Enregistré avec succès.'];

$questions = $categories = [];
$editData  = null;
if ($installe) {
    $questions  = $pdo->query("SELECT * FROM faq ORDER BY (SELECT MIN(f2.ordre) FROM faq f2 WHERE f2.categorie <=> faq.categorie), COALESCE(categorie, 'zzz'), ordre, id")->fetchAll();
    $categories = $pdo->query("SELECT DISTINCT categorie FROM faq WHERE categorie IS NOT NULL AND categorie <> '' ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);
    if (!$readonly && isset($_GET['edit'])) {
        $s = $pdo->prepare("SELECT * FROM faq WHERE id = ?");
        $s->execute([(int)$_GET['edit']]);
        $editData = $s->fetch() ?: null;
    }
    if ($msg && $msg[0] === 'err' && ($_POST['action'] ?? '') === 'save') {
        $editData = [
            'id' => (int)($_POST['id'] ?? 0), 'question' => $_POST['question'] ?? '', 'reponse' => $_POST['reponse'] ?? '',
            'categorie' => $_POST['categorie'] ?? '', 'ordre' => (int)($_POST['ordre'] ?? 0), 'actif' => !empty($_POST['actif']),
        ];
    }
}
$showForm = $installe && !$readonly && (isset($_GET['add']) || $editData);
$nbVisibles = count(array_filter($questions, fn($q) => (int)$q['actif'] === 1));

$pageTitle = 'FAQ';
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">FAQ</h1>
    <p class="text-sm text-slate-500 mt-0.5">Questions fréquentes affichées sur la page publique « FAQ » · <?= $nbVisibles ?> visible(s) sur <?= count($questions) ?></p>
  </div>
  <div class="flex items-center gap-2">
    <a href="../faq.php" target="_blank" rel="noopener" class="flex items-center gap-2 text-xs font-black uppercase px-4 py-3 rounded-xl border border-slate-200 text-primary hover:border-primary transition">
      <i class="fas fa-up-right-from-square"></i> Voir la page publique
    </a>
    <?php if ($installe && !$readonly): ?>
    <a href="?add=1" class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-accent-dark transition shadow-lg">
      <i class="fas fa-plus"></i> Ajouter une question
    </a>
    <?php elseif ($readonly): ?>
    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</span>
    <?php endif; ?>
  </div>
</div>

<?php if (!$installe): ?>
<div class="mb-5 rounded-2xl p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm font-bold">
  <i class="fas fa-circle-info mr-2"></i>La FAQ n'est pas encore installée : exécutez le fichier database/migration_faq.sql dans phpMyAdmin.
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-8">
  <h2 class="font-black text-primary uppercase italic text-sm mb-5"><?= !empty($editData['id']) ? 'Modifier la question' : 'Nouvelle question' ?></h2>
  <form method="POST" class="grid sm:grid-cols-3 gap-5">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
    <div style="grid-column:1/-1">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Question <span class="text-accent">*</span></label>
      <input type="text" name="question" required maxlength="255" value="<?= e($editData['question'] ?? '') ?>" placeholder="Ex : Comment payer ma réservation ?"
             class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
    </div>
    <div style="grid-column:1/-1">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Réponse <span class="text-accent">*</span></label>
      <textarea name="reponse" rows="6" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-medium text-primary outline-none focus:border-primary text-sm"><?= e($editData['reponse'] ?? '') ?></textarea>
      <p class="text-[11px] text-slate-400 mt-1">Texte simple : les retours à la ligne sont conservés.</p>
    </div>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Catégorie</label>
      <input type="text" name="categorie" list="faqCategories" maxlength="100" value="<?= e($editData['categorie'] ?? '') ?>" placeholder="Ex : Paiements"
             class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      <datalist id="faqCategories">
        <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
      </datalist>
    </div>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Ordre d'affichage</label>
      <input type="number" name="ordre" value="<?= (int)($editData['ordre'] ?? 0) ?>"
             class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
    </div>
    <div class="flex items-end">
      <label class="flex items-center gap-2 text-sm font-bold text-primary cursor-pointer pb-3">
        <input type="checkbox" name="actif" value="1" <?= !isset($editData['actif']) || !empty($editData['actif']) ? 'checked' : '' ?> class="w-4 h-4 accent-accent">
        Visible sur le site public
      </label>
    </div>
    <div class="flex justify-end gap-3 pt-2 border-t border-slate-100" style="grid-column:1/-1">
      <a href="faq.php" class="px-6 py-3 rounded-xl font-black text-xs uppercase text-slate-500 hover:bg-slate-100 transition">Annuler</a>
      <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">Enregistrer</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($installe): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <?php if (!$questions): ?>
  <p class="py-12 text-center text-sm text-slate-400">Aucune question pour l'instant.</p>
  <?php else: ?>
  <ul class="divide-y divide-slate-50">
    <?php $catCourante = false; foreach ($questions as $q):
      $cat = $q['categorie'] ?: 'Sans catégorie';
      if ($cat !== $catCourante): $catCourante = $cat; ?>
    <li class="px-5 py-2 bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400"><?= e($cat) ?></li>
    <?php endif; ?>
    <li class="px-5 py-4 flex items-start gap-4 <?= (int)$q['actif'] ? '' : 'opacity-60' ?>">
      <div class="flex-1 min-w-0">
        <p class="font-black text-primary text-sm">
          <?= e($q['question']) ?>
          <?php if (!(int)$q['actif']): ?><span class="ml-1 text-[10px] font-black px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Masquée</span><?php endif; ?>
        </p>
        <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= e($q['reponse']) ?></p>
        <p class="text-[10px] text-slate-400 mt-1">Ordre <?= (int)$q['ordre'] ?></p>
      </div>
      <?php if (!$readonly): ?>
      <div class="flex items-center gap-2 flex-shrink-0">
        <a href="?edit=<?= (int)$q['id'] ?>" title="Modifier" class="w-8 h-8 flex items-center justify-center rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition text-xs"><i class="fas fa-pen"></i></a>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <button type="submit" title="<?= (int)$q['actif'] ? 'Masquer sur le site' : 'Afficher sur le site' ?>" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-500 hover:bg-primary hover:text-white transition text-xs"><i class="fas <?= (int)$q['actif'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button>
        </form>
        <form method="POST" onsubmit="return confirm('Supprimer définitivement cette question ?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <button type="submit" title="Supprimer" class="w-8 h-8 flex items-center justify-center rounded-xl bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition text-xs"><i class="fas fa-trash"></i></button>
        </form>
      </div>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_admin_footer.php'; ?>
