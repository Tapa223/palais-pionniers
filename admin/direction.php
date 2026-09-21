<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','superadmin']);

$pdo      = db();
$readonly = !in_array($_SESSION['role'] ?? '', ['superadmin'], true);
$msg      = null;

$extensionsValides = ['jpg','jpeg','png','webp'];
$mimesValides      = ['image/jpeg','image/png','image/webp'];
$uploadDir         = __DIR__ . '/../assets/images/';

$directionRoles = ['ministre' => 'Ministre', 'dg' => 'Directeur Général', 'dga' => 'Directeur Général Adjoint'];

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $roleKey  = $_POST['role_key'] ?? '';
        $nom      = trim($_POST['nom'] ?? '');
        $titre    = trim($_POST['titre'] ?? '') ?: null;
        $citation = trim($_POST['citation'] ?? '') ?: null;
        $texte    = trim($_POST['texte'] ?? '') ?: null;

        if (!isset($directionRoles[$roleKey]) || $nom === '') {
            $msg = ['err', 'Champs obligatoires manquants.'];
        } else {
            $photo = null;
            $existing = $pdo->prepare("SELECT photo FROM direction WHERE role_key = ?");
            $existing->execute([$roleKey]);
            $photo = $existing->fetchColumn() ?: null;

            // Supprimer la photo actuelle (retour à l'avatar par initiales)
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
                    $newName = 'direction-' . $roleKey . '-' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                        if ($photo && file_exists($uploadDir . $photo)) unlink($uploadDir . $photo);
                        $photo = $newName;
                    }
                } else {
                    $msg = ['err', 'Format de photo non autorisé (JPG, PNG, WEBP uniquement).'];
                }
            }

            if (!$msg) {
                $pdo->prepare("INSERT INTO direction (role_key, nom, titre, citation, texte, photo, ordre)
                               VALUES (?,?,?,?,?,?,0)
                               ON DUPLICATE KEY UPDATE nom=VALUES(nom), titre=VALUES(titre), citation=VALUES(citation), texte=VALUES(texte), photo=VALUES(photo)")
                    ->execute([$roleKey, $nom, $titre, $citation, $texte, $photo]);
                log_activity('direction_modifiee', 'activites', "Fiche {$directionRoles[$roleKey]} modifiée");
                header('Location: direction.php?success=1'); exit;
            }
        }
    }
}

if (isset($_GET['success'])) $msg = ['ok', 'Enregistré avec succès.'];

$parRole = [];
foreach ($pdo->query("SELECT * FROM direction ORDER BY ordre ASC")->fetchAll() as $f) {
    $parRole[$f['role_key']] = $f;
}

$pageTitle = "Direction (Ministre / DG / DGA)";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="mb-6">
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Direction</h1>
    <p class="text-sm text-slate-500 mt-0.5">Ministre, Directeur Général, Directeur Général Adjoint — affichés sur l'accueil et la page À propos</p>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <?php if ($readonly): ?>
  <div class="mb-6 text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</div>
  <?php endif; ?>

  <div class="space-y-8">
    <?php foreach ($directionRoles as $key => $label):
        $f = $parRole[$key] ?? ['nom'=>'','titre'=>'','citation'=>'','texte'=>'','photo'=>null];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
      <h2 class="font-black text-primary uppercase italic text-sm mb-5 flex items-center gap-2">
        <i class="fas fa-user-tie text-accent"></i> <?= $label ?>
      </h2>
      <form method="POST" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="role_key" value="<?= $key ?>">

        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom complet <span class="text-accent">*</span></label>
          <input type="text" name="nom" required <?= $readonly ? 'disabled' : '' ?> value="<?= e($f['nom']) ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm disabled:opacity-60">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Titre / Fonction</label>
          <input type="text" name="titre" <?= $readonly ? 'disabled' : '' ?> value="<?= e($f['titre']) ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm disabled:opacity-60">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Citation (phrase mise en avant)</label>
          <input type="text" name="citation" <?= $readonly ? 'disabled' : '' ?> value="<?= e($f['citation']) ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm disabled:opacity-60">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Texte complet</label>
          <textarea name="texte" rows="4" <?= $readonly ? 'disabled' : '' ?>
                    class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-medium text-primary outline-none focus:border-primary text-sm resize-none disabled:opacity-60"><?= e($f['texte']) ?></textarea>
        </div>
        <?php if (!$readonly): ?>
        <div class="sm:col-span-2">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Photo (JPG, PNG, WEBP) — vide = avatar par initiales</label>
          <?php if (!empty($f['photo'])): ?>
          <div class="flex items-center gap-3 mb-2">
            <img src="../assets/images/<?= e($f['photo']) ?>" class="w-16 h-16 object-cover rounded-xl border border-slate-100">
            <label class="flex items-center gap-2 text-xs font-bold text-accent cursor-pointer">
              <input type="checkbox" name="supprimer_photo" value="1" class="w-4 h-4 accent-accent">
              Supprimer la photo actuelle
            </label>
          </div>
          <?php endif; ?>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                 class="w-full rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm">
        </div>
        <div class="sm:col-span-2 flex justify-end pt-2 border-t border-slate-100">
          <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">Enregistrer</button>
        </div>
        <?php endif; ?>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
