<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Nos Activités | Palais des Pionniers";
$page = 'activites.php';
require __DIR__ . '/includes/header.php';

$all_activites = [];
try {
    $all_activites = db()->query("SELECT * FROM activites ORDER BY id DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('activites.php : ' . $e->getMessage());
}
?>

<section class="pt-2 pb-8 sm:pt-6 sm:pb-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="mb-5 sm:mb-8 text-center">
            <h1 class="text-2xl sm:text-4xl font-black italic uppercase tracking-tighter text-slate-900">
                Nos <span class="text-red-600">Activités</span>
            </h1>
            <p class="text-slate-500 mt-2 max-w-2xl mx-auto font-medium text-sm sm:text-base">
                Un aperçu des activités qui se déroulent au sein du Palais : certaines portées par nos équipes, d'autres organisées par des partenaires ou structures externes accueillis dans nos espaces.
            </p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-8">
            
            <?php if (empty($all_activites)): ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-slate-400 italic text-xl">Aucune activité n'est disponible pour le moment.</p>
                </div>
            <?php else: ?>
                
                <?php foreach ($all_activites as $act): $accent = $act['couleur'] ?: '#E61E2A'; ?>
                <a href="detail-activite.php?slug=<?= urlencode($act['slug']) ?>" class="block bg-slate-50 rounded-xl sm:rounded-[2rem] overflow-hidden border border-slate-100 hover:shadow-2xl transition-all group">
                    <div class="aspect-square sm:aspect-[4/3] bg-slate-200 overflow-hidden relative">
                        <?php if (!empty($act['image_principale']) && $act['image_principale'] !== 'default-hero.jpg'): ?>
                        <img src="assets/images/activites/<?= e($act['image_principale']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-2xl sm:text-6xl text-slate-300"><i class="fas fa-star"></i></div>
                        <?php endif; ?>
                        <span class="absolute top-2 left-2 sm:top-4 sm:left-4 w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 rounded-full shadow-lg" style="background:<?= e($accent) ?>"></span>
                        <span class="absolute bottom-2 right-2 sm:bottom-3 sm:right-3 w-6 h-6 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity" style="background:<?= e($accent) ?>">
                            <i class="fas fa-arrow-right text-[9px] sm:text-xs"></i>
                        </span>
                    </div>
                    <div class="p-2.5 sm:p-6">
                        <h3 class="font-black text-primary text-xs sm:text-xl uppercase italic tracking-tighter transition leading-tight" style="--tw-hover-color:<?= e($accent) ?>" onmouseover="this.style.color='<?= e($accent) ?>'" onmouseout="this.style.color=''"><?= e($act['nom']) ?></h3>
                        <?php if (!empty($act['sous_titre'])): ?>
                        <div class="flex flex-wrap gap-1 sm:gap-2 mt-1.5 sm:mt-3">
                            <span class="text-[9px] sm:text-[11px] font-black rounded-full px-2 sm:px-3 py-0.5 sm:py-1.5" style="color:<?= e($accent) ?>; background:color-mix(in srgb, <?= e($accent) ?> 10%, white); border:1px solid color-mix(in srgb, <?= e($accent) ?> 25%, white)"><i class="fas fa-tag mr-1"></i><?= e($act['sous_titre']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($act['description'])): ?>
                        <p class="mt-1.5 sm:mt-4 text-[10px] sm:text-sm text-slate-600 leading-snug sm:leading-relaxed line-clamp-2 sm:line-clamp-3"><?= nl2br(e($act['description'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($act['chiffres_cles'])): ?>
                        <p class="mt-1.5 sm:mt-4 text-[9px] sm:text-xs font-black uppercase tracking-widest truncate" style="color:<?= e($accent) ?>"><i class="fas fa-chart-simple mr-1"></i><?= e($act['chiffres_cles']) ?></p>
                        <?php endif; ?>
                        <p class="mt-1.5 sm:mt-4 text-[9px] sm:text-[11px] font-black uppercase tracking-widest flex items-center gap-1" style="color:<?= e($accent) ?>">Voir plus <i class="fas fa-chevron-right text-[8px]"></i></p>
                    </div>
                </a>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>