<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_activites']);

$pdo = db();
$msg = null;
$preselectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$formations = $pdo->query("SELECT id, nom FROM formations ORDER BY nom ASC")->fetchAll();

$extensionsValides = ['jpg','jpeg','png','webp'];
$mimesValides      = ['image/jpeg','image/png','image/webp'];
$targetDir         = __DIR__ . '/../assets/images/formations/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'supprimer') {
            $galId = (int)($_POST['gal_id'] ?? 0);
            $old = $pdo->prepare("SELECT image_path FROM formation_galerie WHERE id = ?");
            $old->execute([$galId]);
            $old = $old->fetch();
            if ($old && file_exists($targetDir . $old['image_path'])) unlink($targetDir . $old['image_path']);
            $pdo->prepare("DELETE FROM formation_galerie WHERE id = ?")->execute([$galId]);
            $msg = ['ok', 'Photo supprimée.'];
        }

        if ($action === 'ajouter' && !empty($_FILES['photos'])) {
            $formationId = (int)($_POST['formation_id'] ?? 0);
            $legende     = trim($_POST['legende'] ?? '');
            $formationExiste = $formationId && in_array($formationId, array_column($formations, 'id'), true);

            if (!$formationExiste) {
                $msg = ['err', 'Formation invalide.'];
            } else {
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $uploaded = 0; $rejected = 0;

                foreach ($_FILES['photos']['name'] as $key => $name) {
                    if ($_FILES['photos']['error'][$key] !== UPLOAD_ERR_OK) continue;
                    $tmpPath   = $_FILES['photos']['tmp_name'][$key];
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $mime      = finfo_file($finfo, $tmpPath);

                    if (!in_array($extension, $extensionsValides, true) || !in_array($mime, $mimesValides, true)) {
                        $rejected++; continue;
                    }
                    $newName = 'formation-gal-' . uniqid() . '.' . $extension;
                    if (move_uploaded_file($tmpPath, $targetDir . $newName)) {
                        $pdo->prepare("INSERT INTO formation_galerie (formation_id, image_path, legende) VALUES (?,?,?)")
                            ->execute([$formationId, $newName, $legende ?: null]);
                        $uploaded++;
                    } else { $rejected++; }
                }
                finfo_close($finfo);

                if ($uploaded > 0) {
                    log_activity('formation_galerie_ajoutee', 'activites', "$uploaded photo(s) ajoutée(s) à la galerie d'une formation");
                    $msg = ['ok', "$uploaded image(s) ajoutée(s)." . ($rejected ? " ($rejected fichier(s) ignoré(s) — format non autorisé.)" : '')];
                    $preselectId = $formationId;
                } else {
                    $msg = ['err', "Aucune image valide n'a pu être ajoutée (formats acceptés : JPG, PNG, WEBP)."];
                }
            }
        }
    }
}

$galerieActuelle = [];
if ($preselectId) {
    $g = $pdo->prepare("SELECT * FROM formation_galerie WHERE formation_id = ? ORDER BY ordre ASC, id ASC");
    $g->execute([$preselectId]);
    $galerieActuelle = $g->fetchAll();
}

$pageTitle = "Galerie des formations";
$pageRetour = false;
require __DIR__ . '/_admin_header.php';
?>

<div class="max-w-3xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Galerie des formations</h1>
      <p class="text-sm text-slate-500 mt-0.5">Ajoutez plusieurs photos par formation, affichées en carrousel sur sa fiche détail</p>
    </div>
    <a href="formations.php" class="text-xs font-black text-slate-400 hover:text-primary transition">← Retour</a>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
    <form method="POST" enctype="multipart/form-data" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="ajouter">

      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Formation <span class="text-accent">*</span></label>
        <select name="formation_id" required onchange="window.location='?id='+this.value"
                class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          <option value="">— Choisir —</option>
          <?php foreach ($formations as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $preselectId === (int)$f['id'] ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
          <?php endforeach; ?>
        </select>
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

  <?php if ($preselectId): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
    <h2 class="font-black text-primary uppercase italic text-sm mb-4">Photos actuelles de cette formation</h2>
    <?php if (!$galerieActuelle): ?>
    <p class="text-sm text-slate-400 italic">Aucune photo de galerie pour l'instant.</p>
    <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <?php foreach ($galerieActuelle as $g): ?>
      <div class="relative group">
        <img src="../assets/images/formations/<?= e($g['image_path']) ?>" class="w-full aspect-square object-cover rounded-xl border border-slate-100">
        <form method="POST" onsubmit="return confirm('Supprimer cette photo ?')" class="absolute top-1.5 right-1.5">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="supprimer">
          <input type="hidden" name="gal_id" value="<?= $g['id'] ?>">
          <button class="w-7 h-7 bg-white/90 rounded-full text-accent hover:bg-accent hover:text-white transition flex items-center justify-center text-xs shadow">
            <i class="fas fa-trash"></i>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
