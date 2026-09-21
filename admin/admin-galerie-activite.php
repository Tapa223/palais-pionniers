<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_activites']);

$pdo     = db();
$msg     = null;
$preselectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$activites = $pdo->query("SELECT id, nom FROM activites ORDER BY nom ASC")->fetchAll();

$extensionsValides = ['jpg','jpeg','png','webp'];
$mimesValides       = ['image/jpeg','image/png','image/webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $activite_id = (int)($_POST['activite_id'] ?? 0);
        $categorie   = trim($_POST['categorie'] ?? '');
        $legende     = trim($_POST['legende'] ?? '');

        $activiteExiste = $activite_id && in_array($activite_id, array_column($activites, 'id'), true);

        if (!$activiteExiste || $categorie === '') {
            $msg = ['err', 'Activité ou catégorie invalide.'];
        } else {
            $target_dir = __DIR__ . '/../assets/images/galerie/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $uploaded_count = 0;
            $rejected_count = 0;

            foreach ($_FILES['photos']['name'] as $key => $name) {
                if ($_FILES['photos']['error'][$key] !== UPLOAD_ERR_OK) continue;

                $tmpPath   = $_FILES['photos']['tmp_name'][$key];
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mime      = finfo_file($finfo, $tmpPath);

                if (!in_array($extension, $extensionsValides, true) || !in_array($mime, $mimesValides, true)) {
                    $rejected_count++;
                    continue;
                }

                $new_name    = 'gal-' . uniqid() . '.' . $extension;
                $target_path = $target_dir . $new_name;

                if (move_uploaded_file($tmpPath, $target_path)) {
                    $pdo->prepare("INSERT INTO activite_galerie (activite_id, image_path, categorie, legende) VALUES (?, ?, ?, ?)")
                        ->execute([$activite_id, $new_name, $categorie, $legende ?: null]);
                    $uploaded_count++;
                } else {
                    $rejected_count++;
                }
            }
            finfo_close($finfo);

            if ($uploaded_count > 0) {
                log_activity('galerie_photos_ajoutees', 'activites', "$uploaded_count photo(s) ajoutée(s) — catégorie « $categorie »");
                $msg = ['ok', "$uploaded_count image(s) ajoutée(s) dans « $categorie »." . ($rejected_count ? " ($rejected_count fichier(s) ignoré(s) — format non autorisé.)" : '')];
                $preselectId = $activite_id;
            } else {
                $msg = ['err', 'Aucune image valide n\'a pu être ajoutée (formats acceptés : JPG, PNG, WEBP).'];
            }
        }
    }
}

$pageTitle = "Galerie des activités";
require __DIR__ . '/_admin_header.php';
?>

<div class="max-w-3xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Galerie des activités</h1>
      <p class="text-sm text-slate-500 mt-0.5">Ajouter des photos regroupées par dossier/catégorie</p>
    </div>
    <a href="activites.php" class="text-xs font-black text-slate-400 hover:text-primary transition">← Retour</a>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
    <form method="POST" enctype="multipart/form-data" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Activité <span class="text-accent">*</span></label>
        <select name="activite_id" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          <option value="">— Choisir —</option>
          <?php foreach ($activites as $act): ?>
            <option value="<?= $act['id'] ?>" <?= $preselectId === (int)$act['id'] ? 'selected' : '' ?>><?= e($act['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Catégorie / Dossier <span class="text-accent">*</span></label>
        <input type="text" name="categorie" required placeholder="Ex : Immersion, Cérémonie, Formation"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
        <p class="text-xs text-slate-400 mt-1">Les photos seront regroupées sous ce nom sur la page publique.</p>
      </div>

      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Légende (optionnel)</label>
        <input type="text" name="legende" placeholder="Légende commune à ces photos"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
      </div>

      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Images <span class="text-accent">*</span></label>
        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required
               class="w-full rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm">
        <p class="text-xs text-slate-400 mt-1">Formats acceptés : JPG, PNG, WEBP. Plusieurs fichiers possibles.</p>
      </div>

      <div class="flex justify-end">
        <button type="submit"
                class="flex items-center gap-2 bg-accent text-white px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg">
          <i class="fas fa-upload"></i> Lancer l'upload
        </button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
