<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Sécurité : Vérification du rôle admin
require_admin('/index.php');

$pdo = db();
$message = "";
$isEdit = false;

// Initialisation des données par défaut
$act = [
    'nom' => '', 'sous_titre' => '', 'description' => '', 
    'missions' => '', 'chiffres_cles' => '', 'image_principale' => ''
];
$liens_existants = []; 
$galerie_existante = [];

// --- 1. DÉTECTION DU MODE (MODIFICATION) ---
if (isset($_GET['id'])) {
    $isEdit = true;
    $id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM activites WHERE id = ?");
    $stmt->execute([$id]);
    $act = $stmt->fetch();
    if (!$act) { header('Location: activites.php'); exit; }

    try {
        $stmt_liens = $pdo->prepare("SELECT * FROM activite_liens WHERE activite_id = ?");
        $stmt_liens->execute([$id]);
        $liens_existants = $stmt_liens->fetchAll();
    } catch (PDOException $e) { $liens_existants = []; }

    // On récupère la galerie groupée par catégorie
    $gal = $pdo->prepare("SELECT * FROM activite_galerie WHERE activite_id = ?");
    $gal->execute([$id]);
    while ($row = $gal->fetch(PDO::FETCH_ASSOC)) {
        $galerie_existante[$row['categorie']][] = $row;
    }
}

// --- 2. TRAITEMENT DU FORMULAIRE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        $nom = $_POST['nom'];
        $sous_titre = $_POST['sous_titre'] ?? '';
        $description = $_POST['description'];
        $missions = $_POST['missions'] ?? '';
        $chiffres_cles = $_POST['chiffres_cles'] ?? '';
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nom)));

        $image_principale = $isEdit ? $act['image_principale'] : "default-hero.jpg";
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $path = "../assets/images/activites/";
            if (!file_exists($path)) mkdir($path, 0777, true);
            $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
            $image_principale = "hero-" . $slug . "-" . uniqid() . "." . $ext;
            move_uploaded_file($_FILES["image"]["tmp_name"], $path . $image_principale);
        }

        if ($isEdit) {
            $sql = "UPDATE activites SET nom=?, slug=?, image_principale=?, sous_titre=?, description=?, missions=?, chiffres_cles=? WHERE id=?";
            $pdo->prepare($sql)->execute([$nom, $slug, $image_principale, $sous_titre, $description, $missions, $chiffres_cles, $id]);
            $activite_id = $id;
            try { $pdo->prepare("DELETE FROM activite_liens WHERE activite_id = ?")->execute([$id]); } catch (Exception $e) {}
        } else {
            $sql = "INSERT INTO activites (nom, slug, image_principale, sous_titre, description, missions, chiffres_cles) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$nom, $slug, $image_principale, $sous_titre, $description, $missions, $chiffres_cles]);
            $activite_id = $pdo->lastInsertId();
        }

        // Liens
        if (!empty($_POST['lien_titre'])) {
            foreach ($_POST['lien_titre'] as $i => $titre) {
                $url = $_POST['lien_url'][$i];
                if (!empty($titre) && !empty($url)) {
                    try { $pdo->prepare("INSERT INTO activite_liens (activite_id, titre, url) VALUES (?, ?, ?)")->execute([$activite_id, $titre, $url]); } catch (Exception $e) {}
                }
            }
        }

        // Galerie (Nouvelles photos seulement)
        if (!empty($_POST['groupes_categories'])) {
            $galerie_path = "../assets/images/galerie/";
            if (!file_exists($galerie_path)) mkdir($galerie_path, 0777, true);
            foreach ($_POST['groupes_categories'] as $index => $categorie_nom) {
                $description_cat = $_POST['descriptions_categories'][$index] ?? '';
                if (isset($_FILES['galerie_fichiers']['name'][$index])) {
                    $files = $_FILES['galerie_fichiers'];
                    foreach ($files['name'][$index] as $key => $filename) {
                        if ($files['error'][$index][$key] == 0) {
                            $ext = pathinfo($filename, PATHINFO_EXTENSION);
                            $new_name = "gal-" . uniqid() . "." . $ext;
                            move_uploaded_file($files['tmp_name'][$index][$key], $galerie_path . $new_name);
                            $pdo->prepare("INSERT INTO activite_galerie (activite_id, image_path, categorie, legende) VALUES (?, ?, ?, ?)")->execute([$activite_id, $new_name, $categorie_nom, $description_cat]);
                        }
                    }
                }
            }
        }

        $pdo->commit();
        header('Location: activites.php?success=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='mb-6 rounded-2xl bg-red-50 border border-red-100 p-4 text-sm font-bold text-red-800'>❌ Erreur : " . $e->getMessage() . "</div>";
    }
}

require __DIR__ . '/_admin_header.php';
?>

<main class="min-h-screen bg-[#f1f5f9] pb-20">
    <form action="" method="POST" enctype="multipart/form-data" class="max-w-7xl mx-auto px-4 pt-8">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-[#0a214a] tracking-tight uppercase"><?= $isEdit ? "Modifier" : "Nouvelle Activité" ?></h1>
            <a href="activites.php" class="text-sm font-bold text-slate-500 hover:text-[#0a214a]">← Retour</a>
        </div>

        <?= $message ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-8 space-y-8">
                <!-- Bloc Gauche (Identique à ta capture) -->
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-[11px] font-black uppercase text-blue-600 mb-3">Missions</label>
                            <textarea name="missions" rows="5" class="w-full rounded-xl border border-slate-200 p-4 text-[#0a214a] font-bold"><?= htmlspecialchars($act['missions']) ?></textarea>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black uppercase text-blue-600 mb-3">Chiffres clés</label>
                            <textarea name="chiffres_cles" rows="5" class="w-full rounded-xl border border-slate-200 p-4 text-[#0a214a] font-bold"><?= htmlspecialchars($act['chiffres_cles']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section Galerie Corrigée -->
                <div class="rounded-3xl bg-white p-10 shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="font-black text-[#0a214a] uppercase text-sm tracking-widest">Galerie Photos</h3>
                        <button type="button" onclick="addCategoryBlock()" class="px-4 py-2 rounded-lg bg-blue-50 text-[10px] font-black text-blue-600 uppercase hover:bg-blue-600 hover:text-white transition-all">+ Nouveau Dossier</button>
                    </div>
                    
                    <div id="categories-wrapper" class="space-y-6">
                        <?php 
                        $idx = 0;
                        foreach($galerie_existante as $cat_nom => $photos): 
                        ?>
                        <div class="category-block p-6 rounded-2xl bg-slate-50 border-2 border-white shadow-sm relative" data-index="<?= $idx ?>">
                            <div class="grid grid-cols-1 gap-4 mb-4">
                                <input type="text" name="groupes_categories[]" value="<?= htmlspecialchars($cat_nom) ?>" class="w-full rounded-xl p-3 text-xs font-black border border-slate-200 outline-none">
                                <input type="text" name="descriptions_categories[]" value="<?= htmlspecialchars($photos[0]['legende']) ?>" class="w-full rounded-xl p-3 text-[10px] bg-transparent border border-slate-200 outline-none">
                            </div>
                            <div class="photos-grid grid grid-cols-4 gap-3 mb-4">
                                <?php foreach($photos as $p): ?>
                                    <div class="relative aspect-square rounded-xl overflow-hidden border border-slate-200">
                                        <img src="../assets/images/galerie/<?= $p['image_path'] ?>" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 flex items-center justify-center transition-all">
                                            <span class="text-white text-[8px] font-bold">Déjà en ligne</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" onclick="addPhotoToBlock(this)" class="w-full py-3 rounded-xl border-2 border-dashed border-slate-200 text-[9px] font-black text-slate-400 uppercase">+ Ajouter des photos à ce dossier</button>
                        </div>
                        <?php $idx++; endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <div class="lg:col-span-4 space-y-6">
                <div class="rounded-3xl bg-white p-8 shadow-sm border border-slate-100">
                    <label class="block text-[11px] font-black uppercase text-blue-600 mb-6 text-center">Image de Couverture</label>
                    <div class="relative aspect-[16/10] rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center overflow-hidden">
                        <input type="file" name="image" class="absolute inset-0 opacity-0 cursor-pointer z-20" onchange="previewHero(this)">
                        <?php if($isEdit && $act['image_principale']): ?>
                            <img id="hero-preview" src="../assets/images/activites/<?= $act['image_principale'] ?>" class="h-full w-full object-cover z-10">
                        <?php else: ?>
                            <img id="hero-preview" class="hidden h-full w-full object-cover z-10">
                            <span class="text-slate-400 font-black uppercase text-[9px]">CLIQUEZ POUR AJOUTER</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-8 shadow-sm border border-slate-100">
                    <label class="text-[11px] font-black uppercase text-blue-600 block mb-6">Liens Utiles</label>
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
                    🚀 <?= $isEdit ? "Mettre à jour" : "Publier" ?>
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

<template id="category-template">
    <div class="category-block p-6 rounded-2xl bg-slate-50 border-2 border-white shadow-sm relative">
        <button type="button" onclick="this.parentElement.remove()" class="absolute top-4 right-4 text-slate-300">×</button>
        <div class="grid grid-cols-1 gap-4 mb-4">
            <input type="text" name="groupes_categories[]" placeholder="Titre du dossier" class="w-full rounded-xl p-3 text-xs font-black border border-slate-200 outline-none">
            <input type="text" name="descriptions_categories[]" placeholder="Légende" class="w-full rounded-xl p-3 text-[10px] bg-transparent border border-slate-200 outline-none">
        </div>
        <div class="photos-grid grid grid-cols-4 gap-3 mb-4"></div>
        <button type="button" onclick="addPhotoToBlock(this)" class="w-full py-3 rounded-xl border-2 border-dashed border-slate-200 text-[9px] font-black text-slate-400 uppercase">+ Ajouter des photos</button>
    </div>
</template>

<script>
let groupIndex = <?= count($galerie_existante) ?>;
function addLinkRow() {
    document.getElementById('links-wrapper').appendChild(document.getElementById('link-row-template').content.cloneNode(true));
}
function addCategoryBlock() {
    const wrapper = document.getElementById('categories-wrapper');
    const clone = document.getElementById('category-template').content.cloneNode(true);
    clone.querySelector('.category-block').dataset.index = groupIndex;
    wrapper.appendChild(clone);
    groupIndex++;
}
function addPhotoToBlock(button) {
    const block = button.closest('.category-block');
    const index = block.dataset.index;
    const grid = block.querySelector('.photos-grid');
    const div = document.createElement('div');
    div.className = "relative aspect-square bg-white rounded-xl border border-slate-200 flex items-center justify-center overflow-hidden";
    div.innerHTML = `<input type="file" name="galerie_fichiers[${index}][]" class="absolute inset-0 opacity-0 cursor-pointer z-20" onchange="previewThumb(this)" required><img class="hidden h-full w-full object-cover z-10 absolute inset-0"><span class="text-slate-300 text-lg">+</span>`;
    grid.appendChild(div);
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
function previewThumb(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = input.parentElement.querySelector('img');
            img.src = e.target.result;
            img.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>