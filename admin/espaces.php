<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','admin_espaces']);

$pdo = db();
$msg = null;
$readonly = is_readonly_admin();

if (isset($_GET['success'])) $msg = ['ok', 'Opération réalisée avec succès !'];
if (isset($_GET['error']))   $msg = ['err', 'Une erreur est survenue.'];

if (!empty($_SESSION['espaces_flash'])) {
    $msg = $_SESSION['espaces_flash'];
    unset($_SESSION['espaces_flash']);
}

const ESPACE_PHOTO_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const ESPACE_PHOTO_TAILLE_MAX = 5 * 1024 * 1024;

function taille_ini_octets(string $valeur): int
{
    $valeur = trim($valeur);
    $unite  = strtolower(substr($valeur, -1));
    $nombre = (int)$valeur;
    return match ($unite) {
        'g' => $nombre * 1024 ** 3,
        'm' => $nombre * 1024 ** 2,
        'k' => $nombre * 1024,
        default => (int)$valeur,
    };
}

$photoTailleMax  = min(ESPACE_PHOTO_TAILLE_MAX, taille_ini_octets((string)ini_get('upload_max_filesize')) ?: ESPACE_PHOTO_TAILLE_MAX);
$photoNombreMax  = max(1, (int)ini_get('max_file_uploads') ?: 20);
$envoiTailleMax  = taille_ini_octets((string)ini_get('post_max_size'));

function enregistrer_photos_espace(PDO $pdo, int $espaceId, array $fichiers, int $tailleMax): array
{
    $ajoutees = 0;
    $refus    = [];
    $dossier  = __DIR__ . '/../uploads/';

    $empreintes = [];
    $existantes = $pdo->prepare("SELECT chemin FROM espace_images WHERE espace_id = ?");
    $existantes->execute([$espaceId]);
    foreach ($existantes->fetchAll(PDO::FETCH_COLUMN) as $chemin) {
        $cheminComplet = $dossier . basename($chemin);
        if (is_file($cheminComplet)) {
            $empreintes[sha1_file($cheminComplet)] = true;
        }
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($fichiers['tmp_name'] ?? [] as $k => $tmp) {
        $nomOrigine = basename((string)($fichiers['name'][$k] ?? 'fichier'));
        $erreur     = (int)($fichiers['error'][$k] ?? UPLOAD_ERR_NO_FILE);

        if ($erreur === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($erreur === UPLOAD_ERR_INI_SIZE || $erreur === UPLOAD_ERR_FORM_SIZE) {
            $refus[] = "« $nomOrigine » : fichier trop volumineux";
            continue;
        }
        if ($erreur !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            $refus[] = "« $nomOrigine » : envoi incomplet";
            continue;
        }
        if (filesize($tmp) > $tailleMax) {
            $refus[] = "« $nomOrigine » : dépasse " . round($tailleMax / 1048576, 1) . " Mo";
            continue;
        }

        $mime = $finfo->file($tmp);
        if (!isset(ESPACE_PHOTO_TYPES[$mime]) || @getimagesize($tmp) === false) {
            $refus[] = "« $nomOrigine » : format non accepté (JPG, PNG ou WebP uniquement)";
            continue;
        }

        $empreinte = sha1_file($tmp);
        if (isset($empreintes[$empreinte])) {
            $refus[] = "« $nomOrigine » : photo déjà présente";
            continue;
        }

        $fichier = 'esp_' . $espaceId . '_' . bin2hex(random_bytes(8)) . '.' . ESPACE_PHOTO_TYPES[$mime];
        if (!move_uploaded_file($tmp, $dossier . $fichier)) {
            $refus[] = "« $nomOrigine » : enregistrement impossible";
            continue;
        }

        $pdo->prepare("INSERT INTO espace_images (espace_id, chemin) VALUES (?,?)")->execute([$espaceId, $fichier]);
        $empreintes[$empreinte] = true;
        $ajoutees++;
    }

    return [$ajoutees, $refus];
}

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $msg = ['err', 'Envoi trop volumineux (maximum ' . round($envoiTailleMax / 1048576) . ' Mo au total) : rien n\'a été enregistré. Ajoutez les photos en plusieurs fois.'];
} elseif (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check($_POST['csrf_token'] ?? '')) {
    $msg = ['err', 'Requête invalide ou expirée : rechargez la page et recommencez.'];
} elseif (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_image') {
        $imgId = (int)($_POST['image_id'] ?? 0);
        $img = $pdo->prepare("SELECT chemin, espace_id FROM espace_images WHERE id = ?");
        $img->execute([$imgId]);
        $img = $img->fetch();
        if ($img) {
            @unlink(__DIR__ . '/../uploads/' . basename($img['chemin']));
            $pdo->prepare("DELETE FROM espace_images WHERE id = ?")->execute([$imgId]);
            log_activity("image_supprimee","espaces","Image supprimée de l'espace ID {$img['espace_id']}"); header("Location: espaces.php?edit={$img['espace_id']}&success=1"); exit;
        }
    }

    if ($action === 'delete_espace') {
        $id = (int)($_POST['id'] ?? 0);
        $dependances = [];
        foreach ([
            'réservation(s)'        => "SELECT COUNT(*) FROM reservations WHERE espace_id = ?",
            'demande(s) de bail'    => "SELECT COUNT(*) FROM demandes_bail WHERE espace_id = ?",
            'paiement(s) de bail'   => "SELECT COUNT(*) FROM bail_paiements WHERE espace_id = ?",
        ] as $lib => $sqlDep) {
            try {
                $stDep = $pdo->prepare($sqlDep);
                $stDep->execute([$id]);
                $nbDep = (int)$stDep->fetchColumn();
            } catch (PDOException $ex) {
                $nbDep = 0;
            }
            if ($nbDep > 0) {
                $dependances[] = "$nbDep $lib";
            }
        }
        if ($dependances) {
            $_SESSION['espaces_flash'] = ['err', 'Suppression impossible : cet espace est lié à ' . implode(', ', $dependances)
                . '. Pour le retirer du site sans perdre l\'historique, décochez « Disponible » dans sa fiche.'];
            header("Location: espaces.php");
            exit;
        }
        $imgs = $pdo->prepare("SELECT chemin FROM espace_images WHERE espace_id = ?");
        $imgs->execute([$id]);
        $fichiers = array_column($imgs->fetchAll(), 'chemin');
        $del = $pdo->prepare("DELETE FROM espaces WHERE id = ?");
        $del->execute([$id]);
        if ($del->rowCount() === 1) {
            foreach ($fichiers as $f) @unlink(__DIR__ . '/../uploads/' . basename($f));
            log_activity("espace_supprime","espaces","Espace ID $id supprimé");
        }
        header("Location: espaces.php?success=1"); exit;
    }

    if ($action === 'terminer_bail') {
        $id = (int)($_POST['id'] ?? 0);
        $esp = $pdo->prepare("SELECT nom FROM espaces WHERE id = ?"); $esp->execute([$id]); $nomEsp = $esp->fetchColumn();
        $pdo->prepare("UPDATE espaces SET gerant_externe=NULL, gerant_nom=NULL, gerant_prenom=NULL, gerant_email=NULL, gerant_contact=NULL, gerant_user_id=NULL, resiliation_demandee=0, resiliation_demandee_le=NULL, resiliation_note=NULL WHERE id=?")
            ->execute([$id]);
        log_activity("bail_termine","espaces","Bail terminé pour : $nomEsp");
        header("Location: espaces.php?success=1"); exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id      = (int)($_POST['id'] ?? 0);
        $nom     = trim($_POST['nom']         ?? '');
        $cat     = (int)($_POST['categorie_id'] ?? 0);
        $cap     = trim($_POST['capacite']    ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $equip   = trim($_POST['equipements'] ?? '');
        $dispo   = isset($_POST['disponible']) ? 1 : 0;
        $modeResa = ($_POST['mode_reservation'] ?? 'creneau') === 'sejour' ? 'sejour' : 'creneau';
        $gerantExterne = trim($_POST['gerant_externe'] ?? '') ?: null;
        $gerantNom     = trim($_POST['gerant_nom'] ?? '') ?: null;
        $gerantPrenom  = trim($_POST['gerant_prenom'] ?? '') ?: null;
        $gerantEmail   = trim($_POST['gerant_email'] ?? '') ?: null;
        $gerantContact = trim($_POST['gerant_contact'] ?? '') ?: null;
        $gerantUserId  = null;
        if ($gerantEmail) {
            $ru = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'user'");
            $ru->execute([$gerantEmail]);
            $gerantUserId = $ru->fetchColumn() ?: null;
        }
        $typeBail      = in_array($_POST['type_bail'] ?? '', ['mensuel','trimestriel','semestriel','annuel'], true) ? $_POST['type_bail'] : 'mensuel';
        $optionVip     = isset($_POST['option_vip']) ? 1 : 0;
        $prixVip       = $optionVip && $_POST['prix_vip'] !== '' ? (float)$_POST['prix_vip'] : null;
        $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace([' ', "'"], ['-', ''], $nom)));

        if ($action === 'create') {
            $s = $pdo->prepare("INSERT INTO espaces (nom, slug, categorie_id, capacite, description, equipements, mode_reservation, gerant_externe, gerant_nom, gerant_prenom, gerant_email, gerant_contact, gerant_user_id, type_bail, option_vip, prix_vip, disponible) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $s->execute([$nom, $slug, $cat, $cap, $desc, $equip, $modeResa, $gerantExterne, $gerantNom, $gerantPrenom, $gerantEmail, $gerantContact, $gerantUserId, $typeBail, $optionVip, $prixVip, $dispo]);
            $id = (int)$pdo->lastInsertId(); log_activity("espace_cree","espaces","Nouvel espace : $nom");
        } else {
            $s = $pdo->prepare("UPDATE espaces SET nom=?, slug=?, categorie_id=?, capacite=?, description=?, equipements=?, mode_reservation=?, gerant_externe=?, gerant_nom=?, gerant_prenom=?, gerant_email=?, gerant_contact=?, gerant_user_id=?, type_bail=?, option_vip=?, prix_vip=?, disponible=? WHERE id=?");
            $s->execute([$nom, $slug, $cat, $cap, $desc, $equip, $modeResa, $gerantExterne, $gerantNom, $gerantPrenom, $gerantEmail, $gerantContact, $gerantUserId, $typeBail, $optionVip, $prixVip, $dispo, $id]); log_activity("espace_modifie","espaces","Espace modifié : $nom");
        }

        $photosRefusees = [];
        $photosAjoutees = 0;
        if (!empty($_FILES['galerie']['name'][0])) {
            [$photosAjoutees, $photosRefusees] = enregistrer_photos_espace($pdo, $id, $_FILES['galerie'], $photoTailleMax);
            if ($photosAjoutees > 0) {
                log_activity("photos_ajoutees", "espaces", "$photosAjoutees photo(s) ajoutée(s) à l'espace ID $id");
            }
        }

        if (!empty($_POST['tarif_libelle'])) {
            foreach ($_POST['tarif_libelle'] as $k => $libelle) {
                $libelle = trim($libelle);
                $montant = (float)($_POST['tarif_montant'][$k] ?? 0);
                $unite   = trim($_POST['tarif_unite'][$k]   ?? 'jour');
                $qte     = trim($_POST['tarif_qte'][$k]     ?? '');
                $qte     = ($qte !== '') ? (int)$qte : null;
                $estBail = (($_POST['tarif_bail'][$k] ?? '0') === '1') ? 1 : 0;
                $gNom    = trim($_POST['tarif_gerant_nom'][$k]     ?? '') ?: null;
                $gContact= trim($_POST['tarif_gerant_contact'][$k] ?? '') ?: null;
                $tid     = (int)($_POST['tarif_id'][$k]     ?? 0);
                if (!$libelle || !$montant) continue;
                if ($tid) {
                    $pdo->prepare("UPDATE tarifs SET libelle=?, montant=?, unite=?, quantite_disponible=?, est_bail=?, gerant_nom=?, gerant_contact=?, espace_id=? WHERE id=?")
                        ->execute([$libelle, $montant, $unite, $qte, $estBail, $gNom, $gContact, $id, $tid]);
                } else {
                    $pdo->prepare("INSERT INTO tarifs (espace_id, libelle, montant, unite, quantite_disponible, est_bail, gerant_nom, gerant_contact) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$id, $libelle, $montant, $unite, $qte, $estBail, $gNom, $gContact]);
                }
            }
        }
        if (!empty($_POST['tarif_delete'])) {
            $tarifUtilise = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE tarif_id = ?");
            foreach ($_POST['tarif_delete'] as $tid) {
                $tarifUtilise->execute([(int)$tid]);
                if ((int)$tarifUtilise->fetchColumn() > 0) {
                    $tarifsConserves = ($tarifsConserves ?? 0) + 1;
                    continue;
                }
                $pdo->prepare("DELETE FROM tarifs WHERE id = ? AND espace_id = ?")->execute([(int)$tid, $id]);
            }
        }

        if ($photosRefusees) {
            $_SESSION['espaces_flash'] = ['err', 'Espace enregistré'
                . ($photosAjoutees ? " ($photosAjoutees photo(s) ajoutée(s))" : '')
                . '. Photo(s) non ajoutée(s) : ' . implode(' ; ', $photosRefusees) . '.'];
        } elseif ($photosAjoutees) {
            $_SESSION['espaces_flash'] = ['ok', "Espace enregistré, $photosAjoutees photo(s) ajoutée(s)."];
        }
        if (!empty($tarifsConserves)) {
            $flash = $_SESSION['espaces_flash'] ?? ['ok', 'Espace enregistré.'];
            $flash[1] .= " $tarifsConserves tarif(s) non supprimé(s) : déjà utilisé(s) par des réservations (historique conservé).";
            $_SESSION['espaces_flash'] = $flash;
        }
        header("Location: espaces.php?edit={$id}&success=1"); exit;
    }
}

$editId      = (!$readonly && isset($_GET['edit'])) ? (int)$_GET['edit'] : 0;
$showForm    = !$readonly && (isset($_GET['add']) || $editId);
$espaceEdit  = null;
$imagesEdit  = [];
$tarifsEdit  = [];

if ($editId) {
    $s = $pdo->prepare("SELECT * FROM espaces WHERE id = ?");
    $s->execute([$editId]);
    $espaceEdit = $s->fetch();

    $s = $pdo->prepare("SELECT * FROM espace_images WHERE espace_id = ?");
    $s->execute([$editId]);
    $imagesEdit = $s->fetchAll();

    $s = $pdo->prepare("SELECT * FROM tarifs WHERE espace_id = ? ORDER BY montant ASC");
    $s->execute([$editId]);
    $tarifsEdit = $s->fetchAll();
}

$allEspaces = $pdo->query("
    SELECT e.*, c.nom AS cat_nom,
    (SELECT COUNT(*) FROM espace_images WHERE espace_id = e.id) AS nb_photos,
    (SELECT COUNT(*) FROM tarifs WHERE espace_id = e.id) AS nb_tarifs
    FROM espaces e
    JOIN categories c ON e.categorie_id = c.id
    ORDER BY e.id DESC
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom ASC")->fetchAll();

$pageTitle = "Gestion des Espaces";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Gestion des Espaces</h1>
    <p class="text-sm text-slate-500 mt-0.5">Infrastructures, galeries photos et tarifs</p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
  <a href="export.php?type=espaces" target="_blank" rel="noopener" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition"><i class="fas fa-file-excel"></i> Exporter (Excel)</a>
  <?php if (!$readonly): ?>
  <a href="?add=1"
     class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-accent-dark transition shadow-lg shadow-accent/20">
    <i class="fas fa-plus-circle"></i> Ajouter un espace
  </a>
  <?php else: ?>
  <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</span>
  <?php endif; ?>
  </div>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">

  <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
    <div class="flex items-center gap-3">
      <a href="espaces.php"
         class="flex items-center gap-1.5 text-xs font-black text-slate-400 hover:text-primary transition">
        <i class="fas fa-arrow-left"></i> Retour à la liste
      </a>
      <span class="text-slate-300">/</span>
      <h2 class="font-black text-primary text-sm uppercase italic">
        <?= $editId ? 'Modifier : '.e($espaceEdit['nom'] ?? '') : 'Nouvel espace' ?>
      </h2>
    </div>
    <a href="espaces.php" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent hover:border-accent transition">
      <i class="fas fa-times text-xs"></i>
    </a>
  </div>

  <?php if ($editId && !empty($imagesEdit)): ?>
  <form id="formSupprPhoto" method="POST" action="espaces.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete_image">
  </form>
  <?php endif; ?>

  <form id="formEspace" action="espaces.php" method="POST" enctype="multipart/form-data" class="p-6 md:p-8 space-y-7">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= $editId ?>">

    <div>
      <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 flex items-center gap-2">
        <i class="fas fa-info-circle text-accent"></i> Informations générales
      </p>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom de l'espace <span class="text-accent">*</span></label>
          <input type="text" name="nom" required value="<?= e($espaceEdit['nom'] ?? '') ?>"
                 placeholder="Ex: Salle Seydou BADIAN"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Catégorie <span class="text-accent">*</span></label>
          <div class="relative">
            <select name="categorie_id" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm appearance-none">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (isset($espaceEdit['categorie_id']) && $espaceEdit['categorie_id'] == $cat['id']) ? 'selected' : '' ?>>
                  <?= e($cat['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Capacité d'accueil</label>
          <input type="text" name="capacite" value="<?= e($espaceEdit['capacite'] ?? '') ?>"
                 placeholder="Ex: 400 places"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Équipements</label>
          <input type="text" name="equipements" value="<?= e($espaceEdit['equipements'] ?? '') ?>"
                 placeholder="Wifi, Sono, Clim..."
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Mode de réservation <span class="text-accent">*</span></label>
          <div class="relative">
            <select name="mode_reservation" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm appearance-none">
              <option value="creneau" <?= (($espaceEdit['mode_reservation'] ?? 'creneau') === 'creneau') ? 'selected' : '' ?>>Créneau (date + heure)</option>
              <option value="sejour"  <?= (($espaceEdit['mode_reservation'] ?? '') === 'sejour') ? 'selected' : '' ?>>Séjour (arrivée/départ en nuitées)</option>
            </select>
            <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
          </div>
          <p class="text-[10px] text-slate-400 mt-1">« Séjour » = hébergement (chambres). Le client choisit une date d'arrivée et de départ au lieu d'un horaire.</p>
        </div>
        <div class="sm:col-span-2 flex items-center justify-between">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400">En bail (géré par un tiers) — présentation</label>
          <?php if (!empty($espaceEdit['gerant_externe'])): ?>
          <button type="submit" name="action" value="terminer_bail" formnovalidate
                  onclick="return confirm('Terminer ce bail ? Toutes les infos du gestionnaire seront effacées d\'un coup.')"
                  class="text-[10px] font-black uppercase text-accent hover:underline">
            <i class="fas fa-file-signature mr-1"></i>Terminer le bail
          </button>
          <?php endif; ?>
        </div>
        <div class="sm:col-span-2">
          <input type="text" name="gerant_externe" value="<?= e($espaceEdit['gerant_externe'] ?? '') ?>"
                 placeholder="Laisser vide si géré normalement par le Palais. Ex: cet espace est loué à l'année..."
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary transition text-sm">
          <p class="text-[10px] text-slate-400 mt-1">Si rempli, la réservation en ligne est désactivée pour cet espace — ce texte + la fiche ci-dessous s'affichent au client.</p>
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom du gestionnaire</label>
          <input type="text" name="gerant_nom" value="<?= e($espaceEdit['gerant_nom'] ?? '') ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Prénom du gestionnaire</label>
          <input type="text" name="gerant_prenom" value="<?= e($espaceEdit['gerant_prenom'] ?? '') ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Email du gestionnaire</label>
          <input type="email" name="gerant_email" value="<?= e($espaceEdit['gerant_email'] ?? '') ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Téléphone du gestionnaire</label>
          <input type="text" name="gerant_contact" value="<?= e($espaceEdit['gerant_contact'] ?? '') ?>"
                 placeholder="Ex: +223 XX XX XX XX"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary transition text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Type de bail (durée du contrat)</label>
          <div class="relative">
            <select name="type_bail" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm appearance-none">
              <?php foreach (['mensuel'=>'Mensuel','trimestriel'=>'Trimestriel','semestriel'=>'Semestriel','annuel'=>'Annuel'] as $val=>$lab): ?>
              <option value="<?= $val ?>" <?= (($espaceEdit['type_bail'] ?? 'mensuel') === $val) ? 'selected' : '' ?>><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
          </div>
          <p class="text-[10px] text-slate-400 mt-1">Détermine la fréquence de paiement attendue dans le suivi des baux.</p>
        </div>
        <div class="sm:col-span-2 flex items-end gap-4">
          <label class="relative inline-flex items-center cursor-pointer gap-3 flex-shrink-0">
            <input type="checkbox" name="option_vip" id="optionVipCheck" value="1" <?= !empty($espaceEdit['option_vip']) ? 'checked' : '' ?> class="sr-only peer" onchange="document.getElementById('prixVipField').classList.toggle('hidden', !this.checked)">
            <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-accent transition-all relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></div>
            <span class="text-xs font-bold text-slate-600">Propose un accueil VIP en option</span>
          </label>
          <div id="prixVipField" class="<?= empty($espaceEdit['option_vip']) ? 'hidden' : '' ?> flex-1">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Supplément VIP (FCFA)</label>
            <input type="number" name="prix_vip" value="<?= e($espaceEdit['prix_vip'] ?? '') ?>" placeholder="Ex: 150000"
                   class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary transition text-sm">
          </div>
        </div>
        <div class="flex items-center gap-3 pt-6">
          <label class="relative inline-flex items-center cursor-pointer gap-3">
            <input type="checkbox" name="disponible" value="1" <?= (!isset($espaceEdit) || $espaceEdit['disponible']) ? 'checked' : '' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-primary rounded-full peer peer-checked:bg-primary transition-all"></div>
            <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-all peer-checked:translate-x-5"></div>
            <span class="text-sm font-bold text-slate-700 pl-12">Espace disponible</span>
          </label>
        </div>
      </div>
    </div>

    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Présentation détaillée</label>
      <textarea name="description" rows="3"
                placeholder="Décrivez l'espace, ses atouts, son histoire..."
                class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary transition text-sm resize-none"><?= e($espaceEdit['description'] ?? '') ?></textarea>
    </div>

    <div>
      <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 flex items-center gap-2">
        <i class="fas fa-tags text-accent"></i> Tarifs de cet espace
        <span class="text-slate-300 font-normal normal-case tracking-normal">— Ajoutez autant de tarifs que nécessaire</span>
      </p>

      <div id="tarifsContainer" class="space-y-3 mb-4">
        <?php if (!empty($tarifsEdit)): ?>
          <?php foreach ($tarifsEdit as $tidx => $t): ?>
          <div class="tarif-row grid grid-cols-12 gap-3 items-end bg-slate-50 rounded-2xl p-4 border border-slate-100">
            <input type="hidden" name="tarif_id[]" value="<?= $t['id'] ?>">
            <div class="col-span-12 sm:col-span-4">
              <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Libellé</label>
              <input type="text" name="tarif_libelle[]" value="<?= e($t['libelle']) ?>"
                     placeholder="Ex: Tarif journée entière"
                     class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
            </div>
            <div class="col-span-6 sm:col-span-2">
              <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Montant (FCFA)</label>
              <input type="number" name="tarif_montant[]" value="<?= $t['montant'] ?>"
                     placeholder="0"
                     class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-black text-accent outline-none focus:border-accent text-sm">
            </div>
            <div class="col-span-6 sm:col-span-2">
              <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Unité</label>
              <div class="relative">
                <select name="tarif_unite[]" class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-bold text-primary outline-none text-sm appearance-none">
                  <?php foreach (['heure'=>'Heure','demi-journee'=>'Demi-journée','jour'=>'Jour','mois'=>'Mois','match'=>'Match','nuitée'=>'Nuitée','événement'=>'Événement','seance'=>'Séance','personne_jour'=>'Pers/jour','personne_mois'=>'Pers/mois','personne_an'=>'Pers/an','activite'=>'Activité','support'=>'Support/manif.'] as $val=>$lab): ?>
                    <option value="<?= $val ?>" <?= $t['unite']===$val?'selected':''?>><?= $lab ?></option>
                  <?php endforeach; ?>
                </select>
                <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[9px] pointer-events-none"></i>
              </div>
            </div>
            <div class="col-span-6 sm:col-span-2">
              <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Qté (chambres/salles)</label>
              <input type="number" name="tarif_qte[]" value="<?= e($t['quantite_disponible'] ?? '') ?>"
                     placeholder="1 seul"
                     class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
            </div>
            <div class="col-span-4 sm:col-span-1 flex items-center justify-center pb-2">
              <label class="flex flex-col items-center gap-1 cursor-pointer" title="Location en bail — non réservable en ligne">
                <input type="hidden" name="tarif_bail[<?= $tidx ?>]" value="0" class="tarif-bail-hidden">
                <input type="checkbox" name="tarif_bail[<?= $tidx ?>]" value="1" onchange="toggleGerantFields(this)" <?= !empty($t['est_bail']) ? 'checked' : '' ?> class="w-4 h-4 accent-accent">
                <span class="text-[8px] font-black text-slate-400 uppercase">Bail</span>
              </label>
            </div>
            <div class="col-span-2 sm:col-span-1 flex items-center justify-center pb-2">
              <button type="button" onclick="removeTarif(this)"
                      class="w-9 h-9 flex items-center justify-center rounded-xl bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition">
                <i class="fas fa-trash text-xs"></i>
              </button>
            </div>
            <div class="gerant-fields col-span-12 grid grid-cols-2 gap-3 <?= empty($t['est_bail']) ? 'hidden' : '' ?>">
              <div>
                <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Nom du gestionnaire</label>
                <input type="text" name="tarif_gerant_nom[]" value="<?= e($t['gerant_nom'] ?? '') ?>"
                       placeholder="Ex: M. Diallo"
                       class="w-full rounded-xl border-2 border-amber-100 bg-amber-50/50 px-3 py-2.5 font-semibold text-primary outline-none focus:border-amber-300 text-sm">
              </div>
              <div>
                <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Contact (téléphone)</label>
                <input type="text" name="tarif_gerant_contact[]" value="<?= e($t['gerant_contact'] ?? '') ?>"
                       placeholder="Ex: +223 XX XX XX XX"
                       class="w-full rounded-xl border-2 border-amber-100 bg-amber-50/50 px-3 py-2.5 font-semibold text-primary outline-none focus:border-amber-300 text-sm">
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <button type="button" onclick="addTarif()"
              class="flex items-center gap-2 text-xs font-black text-primary border-2 border-dashed border-slate-200 hover:border-primary hover:bg-primary/5 px-5 py-3 rounded-xl transition w-full justify-center">
        <i class="fas fa-plus-circle text-accent"></i> Ajouter un tarif
      </button>
    </div>

    <div>
      <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4 flex items-center gap-2">
        <i class="fas fa-images text-accent"></i> Galerie photos
      </p>

      <label id="zonePhotos" class="flex flex-col items-center justify-center w-full border-2 border-dashed border-slate-200 rounded-2xl py-8 px-4 text-center cursor-pointer hover:border-primary hover:bg-primary/5 transition bg-slate-50">
        <i class="fas fa-cloud-upload-alt text-2xl text-slate-300 mb-2"></i>
        <p class="text-sm font-bold text-slate-500">Cliquez pour ajouter des photos</p>
        <p class="text-xs text-slate-400 mt-1">JPG, PNG ou WebP · <?= round($photoTailleMax / 1048576, 1) ?> Mo maximum par photo · vous pouvez ajouter plusieurs fois</p>
        <input type="file" id="inputPhotos" name="galerie[]" multiple accept="image/jpeg,image/png,image/webp" class="hidden"
               data-taille-max="<?= (int)$photoTailleMax ?>" data-nombre-max="<?= (int)$photoNombreMax ?>" data-envoi-max="<?= (int)$envoiTailleMax ?>">
      </label>
      <p id="photosMessage" class="hidden mt-2 text-xs font-bold text-accent"></p>

      <div id="photosEnAttente" class="hidden mt-4">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">
          <span id="photosEnAttenteNombre">0</span> photo(s) à ajouter — enregistrées au clic sur « <?= $editId ? 'Enregistrer les modifications' : 'Créer l\'espace' ?> »
        </p>
        <div id="photosEnAttenteGrille" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3"></div>
      </div>

      <?php if (!empty($imagesEdit)): ?>
      <div class="mt-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3"><?= count($imagesEdit) ?> photo(s) en ligne</p>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
          <?php foreach ($imagesEdit as $img): ?>
          <div class="group relative aspect-square rounded-xl overflow-hidden border-2 border-slate-100 shadow-sm">
            <img src="../uploads/<?= e($img['chemin']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" alt="">
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
              <button type="submit" form="formSupprPhoto" name="image_id" value="<?= (int)$img['id'] ?>" formnovalidate
                      onclick="return confirm('Supprimer cette photo ?')" title="Supprimer cette photo"
                      class="w-9 h-9 bg-accent text-white rounded-xl flex items-center justify-center hover:scale-110 transition shadow">
                <i class="fas fa-trash text-xs"></i>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
      <a href="espaces.php" class="flex items-center gap-2 text-sm font-bold text-slate-400 hover:text-primary transition">
        <i class="fas fa-arrow-left"></i> Annuler
      </a>
      <button type="submit" name="action" value="<?= $editId ? 'update' : 'create' ?>"
              class="flex items-center gap-2 bg-accent hover:bg-accent-dark text-white px-8 py-3.5 rounded-xl font-black uppercase tracking-widest text-sm transition shadow-lg shadow-accent/25 active:scale-95">
        <i class="fas fa-save"></i>
        <?= $editId ? 'Enregistrer les modifications' : 'Créer l\'espace' ?>
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50/50">
    <h2 class="font-black text-[10px] uppercase tracking-widest text-slate-400 flex items-center gap-2">
      <i class="fas fa-list text-accent"></i>
      <?= count($allEspaces) ?> espace(s) répertorié(s)
    </h2>
  </div>

  <?php if (empty($allEspaces)): ?>
    <div class="py-16 text-center">
      <i class="fas fa-building text-4xl text-slate-200 mb-3"></i>
      <p class="text-slate-400 font-semibold">Aucun espace enregistré.</p>
      <a href="?add=1" class="inline-flex items-center gap-2 mt-4 text-xs font-black text-accent hover:underline">
        <i class="fas fa-plus"></i> Ajouter le premier espace
      </a>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[500px]">
      <thead class="bg-slate-50 text-[10px] uppercase tracking-widest text-slate-400 text-left">
        <tr>
          <th class="px-5 py-3">Espace</th>
          <th class="px-5 py-3">Catégorie</th>
          <th class="px-5 py-3 text-center">Capacité</th>
          <th class="px-5 py-3 text-center">Tarifs</th>
          <th class="px-5 py-3 text-center">Photos</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($allEspaces as $esp): ?>
        <tr class="hover:bg-slate-50 transition">
          <td class="px-5 py-4">
            <p class="font-black text-primary"><?= e($esp['nom']) ?></p>
            <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wide mt-0.5"><?= e($esp['slug']) ?></p>
            <?php if (($esp['mode_reservation'] ?? 'creneau') === 'sejour'): ?>
            <span class="inline-block mt-1 text-[9px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full"><i class="fas fa-bed mr-1"></i>Séjour</span>
            <?php endif; ?>
          </td>
          <td class="px-5 py-4">
            <span class="bg-primary/5 text-primary text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full border border-primary/10">
              <?= e($esp['cat_nom']) ?>
            </span>
          </td>
          <td class="px-5 py-4 text-center font-bold text-slate-600 text-sm"><?= e($esp['capacite']) ?></td>
          <td class="px-5 py-4 text-center">
            <span class="inline-flex items-center gap-1 text-xs font-black <?= $esp['nb_tarifs']>0 ? 'text-green-600' : 'text-slate-300' ?>">
              <i class="fas fa-tag text-[10px]"></i> <?= $esp['nb_tarifs'] ?>
            </span>
          </td>
          <td class="px-5 py-4 text-center">
            <span class="inline-flex items-center gap-1 text-xs font-black <?= $esp['nb_photos']>0 ? 'text-blue-600' : 'text-slate-300' ?>">
              <i class="fas fa-image text-[10px]"></i> <?= $esp['nb_photos'] ?>
            </span>
          </td>
          <td class="px-5 py-4 text-center">
            <span class="text-[10px] font-black px-2.5 py-1 rounded-full <?= $esp['disponible'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
              <?= $esp['disponible'] ? 'Disponible' : 'Indisponible' ?>
            </span>
          </td>
          <td class="px-5 py-4">
            <?php if (!$readonly): ?>
            <div class="flex items-center justify-center gap-2">
              <a href="?edit=<?= $esp['id'] ?>"
                 class="w-8 h-8 flex items-center justify-center rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition text-xs"
                 title="Modifier">
                <i class="fas fa-edit"></i>
              </a>
              <form method="POST" onsubmit="return confirm('Supprimer définitivement cet espace et ses photos ? (Refusé s\'il a des réservations ou un bail : rendez-le alors indisponible.)')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id"     value="<?= $esp['id'] ?>">
                <input type="hidden" name="action" value="delete_espace">
                <button type="submit"
                        class="w-8 h-8 flex items-center justify-center rounded-xl bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition text-xs"
                        title="Supprimer">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
            <?php else: ?>
            <div class="text-center text-slate-300"><i class="fas fa-eye text-xs"></i></div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const unites = <?= json_encode(['heure'=>'Heure','demi-journee'=>'Demi-journée','jour'=>'Jour','mois'=>'Mois','match'=>'Match','nuitée'=>'Nuitée','événement'=>'Événement','seance'=>'Séance','personne_jour'=>'Pers/jour','personne_mois'=>'Pers/mois','personne_an'=>'Pers/an','activite'=>'Activité','support'=>'Support/manif.']) ?>;

function addTarif() {
    const container = document.getElementById('tarifsContainer');
    const row = document.createElement('div');
    row.className = 'tarif-row grid grid-cols-12 gap-3 items-end bg-slate-50 rounded-2xl p-4 border border-slate-100';
    row.innerHTML = `
        <input type="hidden" name="tarif_id[]" value="0">
        <div class="col-span-12 sm:col-span-4">
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Libellé</label>
            <input type="text" name="tarif_libelle[]" placeholder="Ex: Tarif journée entière"
                   class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>
        <div class="col-span-6 sm:col-span-2">
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Montant (FCFA)</label>
            <input type="number" name="tarif_montant[]" placeholder="0"
                   class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-black text-accent outline-none focus:border-accent text-sm">
        </div>
        <div class="col-span-6 sm:col-span-2">
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Unité</label>
            <div class="relative">
                <select name="tarif_unite[]" class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-bold text-primary outline-none text-sm appearance-none">
                    ${Object.entries(unites).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}
                </select>
                <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[9px] pointer-events-none"></i>
            </div>
        </div>
        <div class="col-span-6 sm:col-span-2">
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Qté (chambres/salles)</label>
            <input type="number" name="tarif_qte[]" placeholder="1 seul"
                   class="w-full rounded-xl border-2 border-slate-100 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>
        <div class="col-span-4 sm:col-span-1 flex items-center justify-center pb-2">
            <label class="flex flex-col items-center gap-1 cursor-pointer" title="Location en bail — non réservable en ligne">
                <input type="hidden" name="tarif_bail[]" value="0" class="tarif-bail-hidden">
                <input type="checkbox" name="tarif_bail[]" value="1" onchange="toggleGerantFields(this)" class="w-4 h-4 accent-accent">
                <span class="text-[8px] font-black text-slate-400 uppercase">Bail</span>
            </label>
        </div>
        <div class="col-span-2 sm:col-span-1 flex items-center justify-center pb-2">
            <button type="button" onclick="removeTarif(this)"
                    class="w-9 h-9 flex items-center justify-center rounded-xl bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition">
                <i class="fas fa-trash text-xs"></i>
            </button>
        </div>
        <div class="gerant-fields col-span-12 grid grid-cols-2 gap-3 hidden">
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Nom du gestionnaire</label>
                <input type="text" name="tarif_gerant_nom[]" placeholder="Ex: M. Diallo"
                       class="w-full rounded-xl border-2 border-amber-100 bg-amber-50/50 px-3 py-2.5 font-semibold text-primary outline-none focus:border-amber-300 text-sm">
            </div>
            <div>
                <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Contact (téléphone)</label>
                <input type="text" name="tarif_gerant_contact[]" placeholder="Ex: +223 XX XX XX XX"
                       class="w-full rounded-xl border-2 border-amber-100 bg-amber-50/50 px-3 py-2.5 font-semibold text-primary outline-none focus:border-amber-300 text-sm">
            </div>
        </div>
    `;
    container.appendChild(row);
    row.querySelector('input[name="tarif_libelle[]"]').focus();
}

function toggleGerantFields(checkbox) {
    const row = checkbox.closest('.tarif-row');
    const fields = row.querySelector('.gerant-fields');
    fields.classList.toggle('hidden', !checkbox.checked);
}

function removeTarif(btn) {
    const row = btn.closest('.tarif-row');
    const idInput = row.querySelector('input[name="tarif_id[]"]');
    if (idInput && parseInt(idInput.value) > 0) {
        const hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = 'tarif_delete[]';
        hidden.value = idInput.value;
        document.querySelector('form').appendChild(hidden);
    }
    row.remove();
}

document.getElementById('formEspace')?.addEventListener('submit', function() {
    document.querySelectorAll('#tarifsContainer .tarif-row').forEach((row, idx) => {
        const hidden = row.querySelector('.tarif-bail-hidden');
        const checkbox = row.querySelector('input[type="checkbox"][name^="tarif_bail"]');
        if (hidden) hidden.name = `tarif_bail[${idx}]`;
        if (checkbox) checkbox.name = `tarif_bail[${idx}]`;
    });
});

(function () {
    const input = document.getElementById('inputPhotos');
    if (!input || typeof DataTransfer === 'undefined') return;
    const zone = document.getElementById('zonePhotos');
    const bloc = document.getElementById('photosEnAttente');
    const grille = document.getElementById('photosEnAttenteGrille');
    const nombre = document.getElementById('photosEnAttenteNombre');
    const message = document.getElementById('photosMessage');
    const tailleMax = parseInt(input.dataset.tailleMax, 10);
    const nombreMax = parseInt(input.dataset.nombreMax, 10);
    const envoiMax = parseInt(input.dataset.envoiMax, 10) || 0;
    const typesOk = ['image/jpeg', 'image/png', 'image/webp'];
    let selection = [];
    const cle = f => f.name + '|' + f.size + '|' + f.lastModified;

    function afficherMessage(lignes) {
        message.textContent = lignes.join(' ');
        message.classList.toggle('hidden', lignes.length === 0);
    }
    function synchroniser() {
        const dt = new DataTransfer();
        selection.forEach(f => dt.items.add(f));
        input.files = dt.files;
        grille.querySelectorAll('img').forEach(i => URL.revokeObjectURL(i.src));
        grille.innerHTML = '';
        selection.forEach((f, index) => {
            const carte = document.createElement('div');
            carte.className = 'relative aspect-square rounded-xl overflow-hidden border-2 border-primary/20 shadow-sm bg-slate-50';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.alt = '';
            img.className = 'w-full h-full object-cover';
            const retirer = document.createElement('button');
            retirer.type = 'button';
            retirer.title = 'Retirer cette photo';
            retirer.setAttribute('aria-label', 'Retirer ' + f.name);
            retirer.className = 'absolute top-1.5 right-1.5 w-8 h-8 bg-white text-accent rounded-lg flex items-center justify-center shadow';
            retirer.innerHTML = '<i class="fas fa-times text-xs"></i>';
            retirer.addEventListener('click', () => { selection.splice(index, 1); afficherMessage([]); synchroniser(); });
            carte.append(img, retirer);
            grille.appendChild(carte);
        });
        nombre.textContent = selection.length;
        bloc.classList.toggle('hidden', selection.length === 0);
        zone.classList.toggle('border-primary', selection.length > 0);
    }
    input.addEventListener('change', function () {
        const refus = [];
        Array.from(input.files).forEach(f => {
            if (selection.some(s => cle(s) === cle(f))) { refus.push(`« ${f.name} » déjà sélectionnée.`); return; }
            if (!typesOk.includes(f.type)) { refus.push(`« ${f.name} » : format non accepté (JPG, PNG, WebP).`); return; }
            if (f.size > tailleMax) { refus.push(`« ${f.name} » : dépasse ${(tailleMax / 1048576).toFixed(1)} Mo.`); return; }
            if (selection.length >= nombreMax) { refus.push(`Maximum ${nombreMax} photos par enregistrement : enregistrez puis ajoutez les suivantes.`); return; }
            const total = selection.reduce((t, s) => t + s.size, 0) + f.size;
            if (envoiMax && total > envoiMax * 0.95) { refus.push(`« ${f.name} » : volume total trop important pour un seul envoi, enregistrez d'abord.`); return; }
            selection.push(f);
        });
        afficherMessage([...new Set(refus)]);
        synchroniser();
    });
})();
</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>