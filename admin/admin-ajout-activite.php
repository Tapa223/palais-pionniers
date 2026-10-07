<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin_activites']);

$pdo = db();
$message = "";
$isEdit = false;

$act = [
    'nom' => '', 'sous_titre' => '', 'description' => '',
    'chiffres_cles' => '', 'image_principale' => '', 'couleur' => ''
];
$liens_existants = [];
$photos_existantes = [];

if (isset($_GET['id'])) {
    $isEdit = true;
    $id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM activites WHERE id = ?");
    $stmt->execute([$id]);
    $act = $stmt->fetch();
    if (!$act) { header('Location: activites.php'); exit; }

    $stmt_liens = $pdo->prepare("SELECT * FROM activite_liens WHERE activite_id = ?");
    $stmt_liens->execute([$id]);
    $liens_existants = $stmt_liens->fetchAll();

    $stmt_photos = $pdo->prepare("SELECT * FROM activite_galerie WHERE activite_id = ? ORDER BY ordre ASC, id ASC");
    $stmt_photos->execute([$id]);
    $photos_existantes = $stmt_photos->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException("Requête invalide : rechargez la page et recommencez.");
        }
        $nom = trim((string)($_POST['nom'] ?? ''));
        if ($nom === '') {
            throw new RuntimeException("Le nom de l'activité est obligatoire.");
        }
        $sous_titre = (string)($_POST['sous_titre'] ?? '');
        $description = (string)($_POST['description'] ?? '');
        $chiffres_cles = (string)($_POST['chiffres_cles'] ?? '');
        $couleurActive = !empty($_POST['couleur_active']);
        $couleur = ($couleurActive && preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['couleur'] ?? '')) ? $_POST['couleur'] : null;
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nom)));
        $slug = trim($slug, '-') ?: 'activite';

        $slugBase = $slug;
        $suffixe = 2;
        while (true) {
            $checkSlug = $pdo->prepare("SELECT id FROM activites WHERE slug = ?" . ($isEdit ? " AND id != ?" : ""));
            $checkSlug->execute($isEdit ? [$slug, $id] : [$slug]);
            if (!$checkSlug->fetchColumn()) break;
            $slug = $slugBase . '-' . $suffixe;
            $suffixe++;
        }

        $image_principale = $isEdit ? $act['image_principale'] : '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = extension_image_televersee($_FILES['image']['tmp_name']);
            if ($ext === null) {
                throw new RuntimeException("Image de couverture refusée : JPG, PNG ou WebP de 5 Mo maximum.");
            }
            $path = __DIR__ . "/../assets/images/activites/";
            if (!is_dir($path)) mkdir($path, 0755, true);
            $nouvelleImage = "hero-" . $slug . "-" . bin2hex(random_bytes(6)) . "." . $ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $path . $nouvelleImage)) {
                throw new RuntimeException("L'image de couverture n'a pas pu être enregistrée.");
            }
            $image_principale = $nouvelleImage;
        }

        if ($isEdit) {
            $sql = "UPDATE activites SET nom=?, slug=?, image_principale=?, sous_titre=?, description=?, chiffres_cles=?, couleur=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nom, $slug, $image_principale, $sous_titre, $description, $chiffres_cles, $couleur, $id]);
            log_activity('activite_modifiee','activites',"Activité modifiée : $nom");
            $activite_id = $id;
        } else {
            $sql = "INSERT INTO activites (nom, slug, image_principale, sous_titre, description, chiffres_cles, couleur) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nom, $slug, $image_principale, $sous_titre, $description, $chiffres_cles, $couleur]);
            $activite_id = (int)$pdo->lastInsertId();
            log_activity('activite_creee','activites',"Nouvelle activité créée : $nom (id $activite_id)");
        }

        $verifExiste = $pdo->prepare("SELECT COUNT(*) FROM activites WHERE id = ?");
        $verifExiste->execute([$activite_id]);
        if (!$verifExiste->fetchColumn()) {
            throw new RuntimeException("L'activité n'a pas pu être enregistrée.");
        }

        $echecComplements = false;
        $pdo->beginTransaction();
        try {
            if ($isEdit) {
                $pdo->prepare("DELETE FROM activite_liens WHERE activite_id = ?")->execute([$id]);
            }

            if (!empty($_POST['lien_titre']) && is_array($_POST['lien_titre'])) {
                foreach ($_POST['lien_titre'] as $i => $titre) {
                    $titre = trim((string)$titre);
                    $url = trim((string)($_POST['lien_url'][$i] ?? ''));
                    if ($titre !== '' && $url !== '' && !url_web_valide($url)) {
                        $echecComplements = true;
                    }
                    if ($titre !== '' && url_web_valide($url)) {
                        $pdo->prepare("INSERT INTO activite_liens (activite_id, titre, url) VALUES (?, ?, ?)")->execute([$activite_id, $titre, $url]);
                    }
                }
            }

            $galerie_path = __DIR__ . "/../assets/images/galerie/";
            if (!is_dir($galerie_path)) mkdir($galerie_path, 0755, true);

            if ($isEdit) {
                $pdo->prepare("DELETE FROM activite_galerie WHERE activite_id = ?")->execute([$id]);
            }

            $cheminsConnus = array_column($photos_existantes, 'image_path');
            $photosConservees = array_values(array_intersect(
                array_filter(explode(',', (string)($_POST['photos_conservees'] ?? ''))),
                $cheminsConnus
            ));

            $ordrePhoto = 0;
            foreach ($photosConservees as $ancienChemin) {
                $pdo->prepare("INSERT INTO activite_galerie (activite_id, image_path, ordre) VALUES (?, ?, ?)")
                    ->execute([$activite_id, $ancienChemin, $ordrePhoto++]);
            }
            if (isset($_FILES['photos']['name'])) {
                foreach ($_FILES['photos']['name'] as $key => $filename) {
                    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                        $ext = extension_image_televersee($_FILES['photos']['tmp_name'][$key]);
                        if ($ext === null) {
                            $echecComplements = true;
                            continue;
                        }
                        $new_name = "gal-" . bin2hex(random_bytes(8)) . "." . $ext;
                        if (!move_uploaded_file($_FILES['photos']['tmp_name'][$key], $galerie_path . $new_name)) {
                            $echecComplements = true;
                            continue;
                        }
                        $pdo->prepare("INSERT INTO activite_galerie (activite_id, image_path, ordre) VALUES (?, ?, ?)")
                            ->execute([$activite_id, $new_name, $ordrePhoto++]);
                    }
                }
            }

            $pdo->commit();
        } catch (Exception $eSections) {
            $pdo->rollBack();
            $echecComplements = true;
            log_activity('activite_sections_echec','activites',"Sections non enregistrées pour l'activité #$activite_id : " . $eSections->getMessage());
        }

        header('Location: activites.php?success=' . ($echecComplements ? 'partiel' : '1'));
        exit;
    } catch (RuntimeException $e) {
        $message = message_erreur($e, "L'enregistrement a échoué. Vérifiez les informations et recommencez.");
    } catch (Exception $e) {
        error_log('admin-ajout-activite : ' . $e->getMessage());
        $message = "L'enregistrement a échoué. Vérifiez les informations et recommencez.";
    }
}

$pageRetour = false;
require __DIR__ . '/_admin_header.php';
?>

<main class="min-h-screen bg-[#f1f5f9] pb-20">
    <form action="" method="POST" enctype="multipart/form-data" class="max-w-7xl mx-auto px-4 pt-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-[#0a214a] tracking-tight uppercase"><?= $isEdit ? "Modifier" : "Nouvelle Activité" ?></h1>
            <a href="activites.php" class="text-sm font-bold text-slate-500 hover:text-[#0a214a]">← Retour</a>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 rounded-2xl bg-red-50 border border-red-100 p-4 text-sm font-bold text-red-800 flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> <?= e($message) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-8 space-y-8">
                <div class="rounded-3xl bg-white p-10 shadow-sm border border-slate-100">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[11px] font-black uppercase text-blue-600 mb-2">Nom de l'activité</label>
                            <input type="text" name="nom" required value="<?= htmlspecialchars($act['nom']) ?>" class="w-full rounded-xl border border-slate-200 p-4 font-bold text-[#0a214a]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-black uppercase text-blue-600 mb-2">Sous-titre (Slogan)</label>
                            <input type="text" name="sous_titre" value="<?= htmlspecialchars($act['sous_titre']) ?>" class="w-full rounded-xl border border-slate-200 p-4 font-bold text-[#0a214a]">
                        </div>
                        <div>
                            <label class="block text-[11px] font-black uppercase text-blue-600 mb-2">Description détaillée</label>
                            <textarea name="description" rows="10" class="w-full rounded-xl border border-slate-200 p-4 text-[#0a214a]"><?= htmlspecialchars($act['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-10 shadow-sm border border-slate-100">
                    <label class="block text-[11px] font-black uppercase text-blue-600 mb-3">Chiffres clés <span class="text-slate-300 normal-case font-medium">(facultatif, affiché seulement s'il est rempli)</span></label>
                    <textarea name="chiffres_cles" rows="4" class="w-full rounded-xl border border-slate-200 p-4 text-[#0a214a] font-bold"><?= htmlspecialchars($act['chiffres_cles']) ?></textarea>
                </div>

                <div class="rounded-3xl bg-white p-10 shadow-sm border border-slate-100">
                    <label class="flex items-center gap-2 cursor-pointer mb-3">
                        <input type="checkbox" name="couleur_active" id="couleurActive" value="1" <?= !empty($act['couleur']) ? 'checked' : '' ?> onchange="document.getElementById('couleurPicker').classList.toggle('hidden', !this.checked)" class="w-4 h-4 accent-blue-600">
                        <span class="text-[11px] font-black uppercase text-blue-600">Choisir une couleur d'accent <span class="text-slate-300 normal-case font-medium">(facultatif, sinon la couleur du site est utilisée)</span></span>
                    </label>
                    <div id="couleurPicker" class="flex items-center gap-4 <?= empty($act['couleur']) ? 'hidden' : '' ?>">
                        <input type="color" name="couleur" value="<?= htmlspecialchars($act['couleur'] ?: '#E61E2A') ?>" class="w-16 h-16 rounded-2xl border-2 border-slate-200 cursor-pointer">
                        <div class="flex gap-2 flex-wrap">
                            <?php $palette = ['#E61E2A' => 'Rouge', '#0a214a' => 'Bleu marine', '#14B53A' => 'Vert', '#FCD116' => 'Jaune', '#7C3AED' => 'Violet', '#EA580C' => 'Orange']; ?>
                            <?php foreach ($palette as $hex => $nomCouleur): ?>
                            <button type="button" onclick="document.querySelector('input[name=couleur]').value='<?= $hex ?>'" title="<?= $nomCouleur ?>" class="w-8 h-8 rounded-full border-2 border-white shadow-md hover:scale-110 transition" style="background:<?= $hex ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-10 shadow-sm border border-slate-100">
                    <h3 class="font-black text-[#0a214a] uppercase text-sm tracking-widest mb-2">Photos de l'activité</h3>
                    <p class="text-[11px] text-slate-400 mb-6">Ajoutez autant de photos que vous voulez : elles apparaissent dans le carrousel en haut de la fiche.</p>
                    <input type="hidden" name="photos_conservees" id="photosConserveesInput" value="<?= e(implode(',', array_column($photos_existantes, 'image_path'))) ?>">
                    <input type="file" id="photosFileInput" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden" onchange="onPhotosSelected(this)">
                    <div id="photos-grid" class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-4">
                        <?php foreach ($photos_existantes as $p): ?>
                        <div class="relative aspect-square rounded-xl overflow-hidden border border-slate-200" data-chemin="<?= htmlspecialchars($p['image_path']) ?>">
                            <img src="../assets/images/galerie/<?= htmlspecialchars($p['image_path']) ?>" class="w-full h-full object-cover">
                            <button type="button" onclick="retirerPhoto(this)" class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-[10px]">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="ajouterPhoto()" class="w-full py-3 rounded-xl border-2 border-dashed border-slate-200 text-[9px] font-black text-slate-400 uppercase">+ Ajouter des photos</button>
                </div>
            </div>

            <div class="lg:col-span-4 space-y-6">
                <div class="rounded-3xl bg-white p-8 shadow-sm border border-slate-100">
                    <label class="block text-[11px] font-black uppercase text-blue-600 mb-6 text-center">Image de Couverture</label>
                    <div class="relative aspect-[16/10] rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center overflow-hidden">
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 opacity-0 cursor-pointer z-20" onchange="previewHero(this)">
                        <?php if($isEdit && $act['image_principale']): ?>
                            <img id="hero-preview" src="../assets/images/activites/<?= e($act['image_principale']) ?>" class="h-full w-full object-cover z-10">
                        <?php else: ?>
                            <img id="hero-preview" class="hidden h-full w-full object-cover z-10">
                            <span class="text-slate-400 font-black uppercase text-[9px]">CLIQUEZ POUR AJOUTER</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-8 shadow-sm border border-slate-100">
                    <label class="text-[11px] font-black uppercase text-blue-600 block mb-6">Liens Utiles <span class="text-slate-300 normal-case font-medium">(facultatif)</span></label>
                    <div id="links-wrapper" class="space-y-3">
                        <?php foreach($liens_existants as $l): ?>
                            <div class="group relative flex flex-col gap-1 p-3 bg-slate-50 rounded-xl">
                                <input type="text" name="lien_titre[]" value="<?= htmlspecialchars($l['titre']) ?>" class="text-[10px] font-bold bg-transparent">
                                <input type="url" name="lien_url[]" value="<?= htmlspecialchars($l['url']) ?>" class="text-[9px] text-blue-500 bg-transparent">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addLinkRow()" class="mt-4 w-full py-2 bg-blue-50 text-blue-600 text-[10px] font-bold rounded-lg uppercase">+ Ajouter un lien</button>
                </div>

                <button type="submit" class="w-full rounded-2xl bg-[#0a214a] p-6 font-black text-white shadow-xl uppercase text-xs tracking-widest transition-all hover:bg-blue-900">
                    <i class="fas fa-paper-plane mr-1"></i> <?= $isEdit ? "Mettre à jour" : "Publier" ?>
                </button>
            </div>
        </div>
    </form>
</main>

<template id="link-row-template">
    <div class="group relative flex flex-col gap-1 p-3 bg-slate-50 rounded-xl border border-blue-100">
        <input type="text" name="lien_titre[]" placeholder="Titre" class="text-[10px] font-bold bg-transparent outline-none">
        <input type="url" name="lien_url[]" placeholder="URL" class="text-[9px] text-blue-500 bg-transparent outline-none">
        <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-400">×</button>
    </div>
</template>

<script>
function addLinkRow() {
    document.getElementById('links-wrapper').appendChild(document.getElementById('link-row-template').content.cloneNode(true));
}
function ajouterPhoto() {
    document.getElementById('photosFileInput').click();
}

let photosAccumulees = new DataTransfer();

function onPhotosSelected(input) {
    for (const fichier of input.files) {
        photosAccumulees.items.add(fichier);
    }
    input.files = photosAccumulees.files;
    renderNouvellesPhotos();
}

function renderNouvellesPhotos() {
    document.querySelectorAll('#photos-grid [data-nouvelle]').forEach(el => el.remove());
    const grid = document.getElementById('photos-grid');
    Array.from(photosAccumulees.files).forEach((fichier, index) => {
        const div = document.createElement('div');
        div.dataset.nouvelle = index;
        div.className = "relative aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-50";
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; };
        const img = document.createElement('img');
        img.className = "w-full h-full object-cover";
        reader.readAsDataURL(fichier);
        div.appendChild(img);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = "absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-[10px]";
        btn.textContent = '×';
        btn.onclick = () => retirerNouvellePhoto(index);
        div.appendChild(btn);
        grid.appendChild(div);
    });
}

function retirerNouvellePhoto(index) {
    if (!confirm('Retirer cette photo de la sélection ?')) return;
    const nouveau = new DataTransfer();
    Array.from(photosAccumulees.files).forEach((f, i) => { if (i !== index) nouveau.items.add(f); });
    photosAccumulees = nouveau;
    document.getElementById('photosFileInput').files = photosAccumulees.files;
    renderNouvellesPhotos();
}

function retirerPhoto(button) {
    if (!confirm('Supprimer définitivement cette photo ?')) return;
    const carte = button.closest('[data-chemin]');
    const chemin = carte.dataset.chemin;
    const hidden = document.getElementById('photosConserveesInput');
    const restants = hidden.value.split(',').filter(c => c && c !== chemin);
    hidden.value = restants.join(',');
    carte.remove();
}
function previewHero(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('hero-preview');
            img.src = e.target.result;
            img.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>