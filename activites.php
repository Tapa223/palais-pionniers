<?php
require_once __DIR__ . '/includes/auth.php';

// 1. Connexion via ton système de fonction
require_once __DIR__ . '/config/database.php'; 

// On récupère l'instance PDO en appelant la fonction db() définie dans ton fichier
try {
    $pdo = db(); 
} catch (Exception $e) {
    die("<div class='bg-red-100 text-red-700 p-4'>Erreur de connexion : " . $e->getMessage() . "</div>");
}

$pageTitle = "Nos Activités — Palais des Pionniers";
$page = 'activites.php';
require __DIR__ . '/includes/header.php';

// 2. Récupération des activités
$all_activites = [];
try {
    $stmt = $pdo->query("SELECT * FROM activites ORDER BY id DESC");
    $all_activites = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='bg-red-100 text-red-700 p-4'>Erreur de requête : " . $e->getMessage() . "</div>";
}
?>

<section class="py-24 bg-slate-50">
    <div class="container mx-auto px-4">
        <div class="mb-16">
            <h1 class="text-6xl font-black italic uppercase tracking-tighter text-slate-900">
                Nos <span class="text-red-600">Activités</span>
            </h1>
            <p class="text-slate-500 mt-4 max-w-xl font-medium text-lg">
                Découvrez nos programmes d'excellence pour la jeunesse malienne.
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-10">
            
            <?php if (empty($all_activites)): ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-slate-400 italic text-xl">Aucune activité n'est disponible pour le moment.</p>
                </div>
            <?php else: ?>
                
                <?php foreach ($all_activites as $act): ?>
                <div class="group bg-white rounded-[2.5rem] overflow-hidden border border-slate-100 shadow-sm hover:shadow-2xl transition-all duration-500">
                    <div class="relative h-80 overflow-hidden">
                        <?php 
                            $imagePath = !empty($act['image_principale']) 
                                ? 'assets/images/activites/' . htmlspecialchars($act['image_principale']) 
                                : 'assets/images/logoeci.png'; 
                        ?>
                        <img src="<?= $imagePath ?>" 
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        
                        <div class="absolute top-6 left-6 flex flex-col gap-2">
                            <div class="bg-white/95 backdrop-blur-md px-3 py-2 rounded-xl flex items-center gap-3 shadow-lg">
                                <img src="assets/images/logominis.jpg" class="w-6 h-6 object-contain">
                                <span class="text-[8px] font-black uppercase tracking-widest text-slate-900">Ministère de la Jeunesse</span>
                            </div>
                            <div class="bg-yellow-400 px-3 py-1 rounded-full shadow-md w-fit">
                                <span class="text-[8px] font-bold uppercase text-slate-900">Vision Mali Kura</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-10">
                        <h3 class="text-3xl font-black text-slate-900 uppercase italic mb-4 leading-none">
                            <?= htmlspecialchars($act['nom']) ?>
                        </h3>
                        <p class="text-slate-500 text-sm mb-8 leading-relaxed line-clamp-3">
                            <?= htmlspecialchars($act['sous_titre'] ?? '') ?>
                        </p>
                        
                        <a href="detail-activite.php?slug=<?= urlencode($act['slug']) ?>" 
                           class="flex items-center justify-between font-black uppercase text-xs tracking-[0.2em] text-slate-900 group/link">
                            Découvrir le programme
                            <span class="w-12 h-12 rounded-full bg-slate-900 text-white flex items-center justify-center group-hover/link:bg-red-600 transition-all">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>