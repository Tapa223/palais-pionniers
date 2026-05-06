<?php
require_once __DIR__ . '/config/database.php'; 

try {
    $pdo = db(); 
} catch (Exception $e) {
    die("Erreur de connexion.");
}

$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
if (empty($slug)) { header('Location: activites.php'); exit; }

// Récupération de l'activité
$stmt = $pdo->prepare("SELECT * FROM activites WHERE slug = ?");
$stmt->execute([$slug]);
$activite = $stmt->fetch();

if (!$activite) { exit; }

// Récupération de la galerie groupée par dossier (catégorie)
$stmtImg = $pdo->prepare("SELECT categorie, image_path, legende FROM activite_galerie WHERE activite_id = ? ORDER BY categorie ASC");
$stmtImg->execute([$activite['id']]);
$galerie = $stmtImg->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Séparation des blocs de missions
$blocs_missions = !empty($activite['missions']) ? explode("---", $activite['missions']) : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($activite['nom']) ?> - Palais des Pionniers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS LIGHTBOX -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/luminous-lightbox/2.3.2/luminous-basic.min.css">
    
    <style>
        .gallery-image { transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1); cursor: zoom-in; }
        .group:hover .gallery-image { transform: scale(1.08); }
        /* Style pour s'assurer que la lightbox passe au-dessus du header */
        .lum-lightbox { z-index: 1000; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900 font-sans">

    <?php if(file_exists(__DIR__ . '/includes/header.php')) include __DIR__ . '/includes/header.php'; ?>

    <!-- HERO SECTION -->
    <section class="relative h-[45vh] flex items-center bg-[#0a214a] overflow-hidden">
        <img src="assets/images/activites/<?= htmlspecialchars($activite['image_principale']) ?>" class="absolute inset-0 w-full h-full object-cover opacity-40">
        <div class="container mx-auto px-6 relative z-10">
            <h1 class="text-5xl md:text-6xl font-black text-white uppercase italic tracking-tighter">
                <?= htmlspecialchars($activite['nom']) ?>
            </h1>
            <div class="h-2 w-20 bg-red-600 mt-4"></div>
        </div>
    </section>

    <!-- PRÉSENTATION & BLOCS MODULAIRES -->
    <section class="py-16">
        <div class="container mx-auto px-6">
            <div class="grid lg:grid-cols-3 gap-12">
                <div class="lg:col-span-2 space-y-10">
                    <!-- Présentation Générale -->
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-[0.3em] text-red-600 mb-6 italic">Présentation</h2>
                        <div class="text-xl leading-relaxed text-slate-600 font-light">
                            <?= nl2br(htmlspecialchars($activite['description'])) ?>
                        </div>
                    </div>

                    <!-- Blocs dynamiques -->
                    <div class="grid gap-6">
                        <?php foreach ($blocs_missions as $bloc): 
                            $details = explode(":", $bloc, 2);
                            $titreBloc = (count($details) > 1) ? $details[0] : "Détails";
                            $texteBloc = (count($details) > 1) ? $details[1] : $details[0];
                        ?>
                        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100">
                            <h3 class="text-lg font-black uppercase text-[#0a214a] mb-4 flex items-center italic">
                                <i class="fas fa-check-circle text-red-600 mr-3"></i>
                                <?= htmlspecialchars(trim($titreBloc)) ?>
                            </h3>
                            <div class="text-slate-500 leading-relaxed">
                                <?= nl2br(htmlspecialchars(trim($texteBloc))) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SIDEBAR CHIFFRES -->
                <aside>
                    <div class="sticky top-10 space-y-6">
                        <div class="bg-[#0a214a] text-white p-10 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                            <h3 class="text-xs font-black uppercase tracking-widest mb-8 border-b border-white/10 pb-4">Impact & Chiffres</h3>
                            <div class="text-2xl font-light italic leading-snug">
                                <?= nl2br(htmlspecialchars($activite['chiffres_cles'] ?? 'Données en cours...')) ?>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <!-- GALERIE PHOTO -->
    <section class="py-20 bg-white border-t border-slate-100">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-black uppercase italic text-center mb-16">
                Galerie <span class="text-red-600">Photos</span>
            </h2>

            <?php foreach ($galerie as $categorie => $photos): ?>
                <div class="mb-16">
                    <div class="mb-10">
                        <h3 class="text-2xl font-black uppercase italic text-[#0a214a]">
                            <?= htmlspecialchars($categorie) ?>
                        </h3>
                        <?php 
                        $desc = !empty($photos[0]['legende']) ? $photos[0]['legende'] : ''; 
                        if($desc): 
                        ?>
                            <p class="text-slate-500 mt-2 font-medium text-lg italic"><?= htmlspecialchars($desc) ?></p>
                        <?php endif; ?>
                        <div class="h-1 w-16 bg-red-600 mt-4"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($photos as $p): ?>
                            <!-- Lien avec la classe pour la lightbox -->
                            <a href="assets/images/galerie/<?= htmlspecialchars($p['image_path']) ?>" 
                               class="luminous-gallery group relative h-72 overflow-hidden rounded-[2.5rem] bg-slate-50 shadow-sm transition-all hover:shadow-2xl">
                                
                                <img src="assets/images/galerie/<?= htmlspecialchars($p['image_path']) ?>" 
                                     class="gallery-image w-full h-full object-cover" 
                                     alt="<?= htmlspecialchars($categorie) ?>">
                                
                                <div class="absolute inset-0 bg-[#0a214a]/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center text-white">
                                        <i class="fas fa-expand-alt"></i>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- CONTACT -->
    <section class="py-20 bg-slate-50">
        <div class="container mx-auto px-6">
            <div class="max-w-5xl mx-auto bg-white rounded-[3rem] shadow-2xl overflow-hidden flex flex-col md:flex-row">
                <div class="md:w-1/3 bg-[#0a214a] p-12 text-white">
                    <h3 class="text-3xl font-black uppercase italic mb-6">Un mot sur ce projet ?</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Pour toute information supplémentaire concernant l'activité <?= htmlspecialchars($activite['nom']) ?>, contactez-nous.
                    </p>
                </div>
                <div class="md:w-2/3 p-12">
                    <form action="#" method="POST" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">Nom complet</label>
                                <input type="text" class="w-full px-6 py-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-red-600 outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">Email</label>
                                <input type="email" class="w-full px-6 py-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-red-600 outline-none">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">Message</label>
                            <textarea rows="4" class="w-full px-6 py-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-red-600 outline-none"></textarea>
                        </div>
                        <button class="w-full py-5 bg-red-600 text-white rounded-2xl font-black uppercase text-xs tracking-[0.2em] hover:bg-red-700 transition-all shadow-lg shadow-red-600/20">
                            Envoyer le message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php if(file_exists(__DIR__ . '/includes/footer.php')) include __DIR__ . '/includes/footer.php'; ?>

    <!-- SCRIPTS JS LIGHTBOX -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/luminous-lightbox/2.3.2/Luminous.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sélection de tous les liens de la galerie
            var galleryLinks = document.querySelectorAll(".luminous-gallery");
            
            if (galleryLinks.length > 0) {
                // Initialisation en mode Galerie pour permettre la navigation (Suivant/Précédent)
                new LuminousGallery(galleryLinks, {
                    arrowNavigation: true
                }, {
                    caption: function(trigger) {
                        // Optionnel : affiche le texte de l'image (alt) en légende
                        return trigger.querySelector('img').getAttribute('alt');
                    }
                });
            }
        });
    </script>
</body>
</html>