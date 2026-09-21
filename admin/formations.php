<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','admin_activites']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;

$extensionsValides = ['jpg','jpeg','png','webp'];
$mimesValides      = ['image/jpeg','image/png','image/webp'];
$uploadDir         = __DIR__ . '/../assets/images/formations/';

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $old = $pdo->prepare("SELECT photo FROM formations WHERE id = ?");
            $old->execute([$id]);
            $old = $old->fetch();
            if ($old && $old['photo'] && file_exists($uploadDir . $old['photo'])) unlink($uploadDir . $old['photo']);
            $pdo->prepare("DELETE FROM formations WHERE id = ?")->execute([$id]);
            log_activity('formation_supprimee', 'activites', "Formation #$id supprimée");
            header('Location: formations.php?success=1'); exit;
        }

        if ($action === 'save') {
            $id          = (int)($_POST['id'] ?? 0);
            $nom         = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '') ?: null;
            $duree       = trim($_POST['duree'] ?? '') ?: null;
            $publicCible = trim($_POST['public_cible'] ?? '') ?: null;
            $contact     = trim($_POST['contact'] ?? '') ?: null;
            $dateFin     = trim($_POST['date_fin'] ?? '') ?: null;
            $actif       = isset($_POST['actif']) ? 1 : 0;
            $ordre       = (int)($_POST['ordre'] ?? 0);

            if ($nom === '') {
                $msg = ['err', 'Le nom de la formation est obligatoire.'];
            } else {
                $photo = null;
                if ($id) {
                    $existing = $pdo->prepare("SELECT photo FROM formations WHERE id = ?");
                    $existing->execute([$id]);
                    $photo = $existing->fetchColumn() ?: null;
                }

                if (!empty($_POST['supprimer_photo'])) {
                    if ($photo && file_exists($uploadDir . $photo)) unlink($uploadDir . $photo);
                    $photo = null;
                }

                if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = finfo_file($finfo, $_FILES['photo']['tmp_name']);
                    finfo_close($finfo);

                    if (in_array($ext, $extensionsValides, true) && in_array($mime, $mimesValides, true)) {
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $newName = 'formation-' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                            if ($photo && file_exists($uploadDir . $photo)) unlink($uploadDir . $photo);
                            $photo = $newName;
                        }
                    } else {
                        $msg = ['err', 'Format de photo non autorisé (JPG, PNG, WEBP uniquement).'];
                    }
                }

                if (!$msg) {
                    if ($id) {
                        $pdo->prepare("UPDATE formations SET nom=?, description=?, duree=?, public_cible=?, photo=?, contact=?, date_fin=?, actif=?, ordre=? WHERE id=?")
                            ->execute([$nom, $description, $duree, $publicCible, $photo, $contact, $dateFin, $actif, $ordre, $id]);
                        log_activity('formation_modifiee', 'activites', "Formation modifiée : $nom");
                    } else {
                        $pdo->prepare("INSERT INTO formations (nom, description, duree, public_cible, photo, contact, date_fin, actif, ordre) VALUES (?,?,?,?,?,?,?,?,?)")
                            ->execute([$nom, $description, $duree, $publicCible, $photo, $contact, $dateFin, $actif, $ordre]);
                        log_activity('formation_creee', 'activites', "Nouvelle formation : $nom");
                    }
                    header('Location: formations.php?success=1'); exit;
                }
            }
        }
    }
}

if (isset($_GET['success'])) $msg = ['ok', 'Enregistré avec succès.'];

$formations = $pdo->query("SELECT * FROM formations ORDER BY ordre ASC, nom ASC")->fetchAll();

$editId = (!$readonly && isset($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$editData = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM formations WHERE id = ?");
    $s->execute([$editId]);
    $editData = $s->fetch();
}
$showForm = !$readonly && (isset($_GET['add']) || $editData);

$pageTitle = "Formations";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Formations</h1>
      <p class="text-sm text-slate-500 mt-0.5">Formations dispensées au sein du Palais (Savonnerie, Informatique...)</p>
    </div>
    <?php if (!$readonly): ?>
    <a href="?add=1" class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-accent-dark transition shadow-lg">
      <i class="fas fa-graduation-cap"></i> Ajouter une formation
    </a>
    <?php else: ?>
    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</span>
    <?php endif; ?>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <?php if ($showForm): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-8">
    <h2 class="font-black text-primary uppercase italic text-sm mb-5"><?= $editData ? 'Modifier' : 'Nouvelle formation' ?></h2>
    <form method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editData['id'] ?? 0 ?>">

      <div class="sm:col-span-2">
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom de la formation <span class="text-accent">*</span></label>
        <input type="text" name="nom" required value="<?= e($editData['nom'] ?? '') ?>" placeholder="Ex: Savonnerie, Informatique..."
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Durée</label>
        <input type="text" name="duree" placeholder="Ex: 3 mois" value="<?= e($editData['duree'] ?? '') ?>"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Public visé</label>
        <input type="text" name="public_cible" placeholder="Ex: Jeunes 15-25 ans" value="<?= e($editData['public_cible'] ?? '') ?>"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Contact / Inscription</label>
        <input type="text" name="contact" placeholder="Téléphone ou email" value="<?= e($editData['contact'] ?? '') ?>"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Date de fin (optionnel)</label>
        <input type="date" name="date_fin" value="<?= e($editData['date_fin'] ?? '') ?>"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
        <p class="text-[10px] text-slate-400 mt-1">Si dépassée, la formation reste affichée mais avec un badge "Terminée".</p>
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Ordre d'affichage</label>
        <input type="number" name="ordre" value="<?= e($editData['ordre'] ?? 0) ?>"
               class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Photo (JPG, PNG, WEBP)</label>
        <?php if (!empty($editData['photo'])): ?>
        <div class="flex items-center gap-3 mb-2">
          <img src="../assets/images/formations/<?= e($editData['photo']) ?>" class="w-20 h-20 object-cover rounded-xl border border-slate-100">
          <label class="flex items-center gap-2 text-xs font-bold text-accent cursor-pointer">
            <input type="checkbox" name="supprimer_photo" value="1" class="w-4 h-4 accent-accent">
            Supprimer la photo actuelle
          </label>
        </div>
        <?php endif; ?>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
               class="w-full rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Description</label>
        <textarea name="description" rows="4"
                  class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-medium text-primary outline-none focus:border-primary text-sm resize-none"><?= e($editData['description'] ?? '') ?></textarea>
      </div>
      <div class="sm:col-span-2 flex items-center gap-3">
        <label class="relative inline-flex items-center cursor-pointer gap-3">
          <input type="checkbox" name="actif" class="sr-only peer" <?= (!isset($editData) || $editData['actif']) ? 'checked' : '' ?>>
          <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-accent transition-all relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
          <span class="text-xs font-bold text-slate-600">Visible sur le site public</span>
        </label>
      </div>
      <div class="sm:col-span-2 flex justify-end gap-3 pt-2 border-t border-slate-100">
        <a href="formations.php" class="px-6 py-3 rounded-xl font-black text-xs uppercase text-slate-500 hover:bg-slate-100 transition">Annuler</a>
        <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">Enregistrer</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($formations as $f): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
      <div class="aspect-[4/3] bg-slate-100 flex items-center justify-center overflow-hidden relative">
        <?php if ($f['photo']): ?>
        <img src="../assets/images/formations/<?= e($f['photo']) ?>" class="w-full h-full object-cover">
        <?php else: ?>
        <i class="fas fa-graduation-cap text-4xl text-slate-300"></i>
        <?php endif; ?>
        <?php if (!$f['actif']): ?>
        <span class="absolute top-2 right-2 bg-slate-800 text-white text-[9px] font-black uppercase px-2 py-1 rounded-full">Masquée</span>
        <?php endif; ?>
      </div>
      <div class="p-4 flex-1 flex flex-col">
        <p class="font-black text-primary text-sm"><?= e($f['nom']) ?></p>
        <?php if ($f['duree']): ?><p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5"><i class="fas fa-clock mr-1"></i><?= e($f['duree']) ?></p><?php endif; ?>
        <?php if (!$readonly): ?>
        <div class="mt-auto pt-3 flex gap-2">
          <a href="?edit=<?= $f['id'] ?>" class="flex-1 text-center py-2 rounded-lg bg-blue-50 text-blue-600 text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition">Modifier</a>
          <a href="formation-galerie.php?id=<?= $f['id'] ?>" class="flex-1 text-center py-2 rounded-lg bg-purple-50 text-purple-600 text-[10px] font-black uppercase hover:bg-purple-600 hover:text-white transition">Galerie</a>
          <form method="POST" onsubmit="return confirm('Supprimer cette formation ?')" class="flex-1">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $f['id'] ?>">
            <button type="submit" class="w-full py-2 rounded-lg bg-red-50 text-red-400 text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition">Supprimer</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$formations): ?>
    <p class="text-sm text-slate-400 italic col-span-full text-center py-10">Aucune formation enregistrée pour l'instant.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
