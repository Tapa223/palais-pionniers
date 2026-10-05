<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$formations = $pdo->query("SELECT * FROM formations WHERE actif = 1 ORDER BY ordre ASC, nom ASC")->fetchAll();

// Photos du hero : d'abord les vraies photos de formations si présentes,
// sinon des photos réelles déjà prises au Palais (galerie des activités)
$photosFormations = array_values(array_filter(
    array_map(fn($f) => $f['photo'] ? 'assets/images/formations/' . $f['photo'] : null, $formations),
    fn($p) => $p && file_exists(__DIR__ . '/' . $p)
));
if ($photosFormations) {
    $heroSlides = array_slice($photosFormations, 0, 30);
} else {
    // 3 images ciblées pour les formations — modifie simplement ces noms de
    // fichiers pour changer les photos affichées dans ce bandeau.
    $imagesCibleesFormations = [
        'assets/images/formation1.jpg',
        'assets/images/formation2.jpg',
        'assets/images/formation3.jpg',
    ];
    $heroSlides = array_values(array_filter($imagesCibleesFormations, fn($p) => file_exists(__DIR__ . '/' . $p)));
    if (!$heroSlides) {
        $fallback = ['assets/images/porte.jpeg', 'assets/images/adminis.jpeg', 'assets/images/groupewague.jpg'];
        $heroSlides = array_values(array_filter($fallback, fn($p) => file_exists(__DIR__ . '/' . $p)));
    }
}

$pageTitle = "Formations — Palais des Pionniers";
$page = 'formations.php';
require __DIR__ . '/includes/header.php';
?>

<section class="relative overflow-hidden bg-slate-900 text-white max-h-[26vh] sm:max-h-[35vh]">
    <div class="absolute inset-0 bg-gradient-to-r from-primary/90 via-primary/30 to-transparent z-10"></div>
    <div id="heroCarouselFormations" class="absolute inset-0 z-0">
        <?php foreach ($heroSlides as $index => $src): ?>
        <div class="carousel-img-formations absolute inset-0 w-full h-full transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>">
            <div class="w-full h-full kenburns" style="background: url('<?= e($src) ?>') center/cover no-repeat;"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (count($heroSlides) > 1): ?>
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex gap-2">
        <?php foreach ($heroSlides as $index => $src): ?>
        <span class="h-1.5 rounded-full transition-all <?= $index === 0 ? 'w-8 bg-accent' : 'w-1.5 bg-white/40' ?>" data-dot-f="<?= $index ?>"></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="container relative z-20 mx-auto flex min-h-[26vh] sm:min-h-[35vh] items-center px-4 py-3 sm:py-5">
        <div class="max-w-3xl">
            <h2 class="text-accent font-black tracking-[0.2em] sm:tracking-[0.3em] uppercase text-[9px] sm:text-xs mb-2 sm:mb-4">Transmettre un métier</h2>
            <h1 class="text-2xl sm:text-4xl md:text-5xl font-black italic tracking-tighter uppercase mb-2 sm:mb-4 drop-shadow-2xl">
                Nos <span class="text-accent">Formations</span>
            </h1>
            <p class="max-w-2xl text-xs sm:text-base text-slate-200 font-medium leading-relaxed drop-shadow-md">
                Des formations pratiques dispensées au sein du Palais, pour outiller la jeunesse malienne de compétences concrètes et directement utiles.
            </p>
        </div>
    </div>
</section>

<?php if (count($heroSlides) > 1): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const slidesF = document.querySelectorAll('.carousel-img-formations');
    const dotsF = document.querySelectorAll('[data-dot-f]');
    let currentF = 0;
    setInterval(() => {
        slidesF[currentF].classList.replace('opacity-100', 'opacity-0');
        if (dotsF[currentF]) { dotsF[currentF].classList.remove('w-8','bg-accent'); dotsF[currentF].classList.add('w-1.5','bg-white/40'); }
        currentF = (currentF + 1) % slidesF.length;
        slidesF[currentF].classList.replace('opacity-0', 'opacity-100');
        if (dotsF[currentF]) { dotsF[currentF].classList.remove('w-1.5','bg-white/40'); dotsF[currentF].classList.add('w-8','bg-accent'); }
    }, 4000);
});
</script>
<?php endif; ?>

<section class="pt-2 pb-8 sm:pt-6 sm:pb-24 bg-white">
    <div class="container mx-auto px-4">
        <?php if (!$formations): ?>
        <p class="text-center text-slate-400 italic py-10">Aucune formation n'est disponible pour le moment.</p>
        <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-8">
            <?php foreach ($formations as $f):
                $estTerminee = !empty($f['date_fin']) && strtotime($f['date_fin']) < strtotime('today');
            ?>
            <a href="formation.php?id=<?= (int)$f['id'] ?>" class="block bg-slate-50 rounded-xl sm:rounded-[2rem] overflow-hidden border border-slate-100 hover:shadow-2xl transition-all group">
                <div class="aspect-square sm:aspect-[4/3] bg-slate-200 overflow-hidden relative">
                    <?php if ($f['photo']): ?>
                    <img src="assets/images/formations/<?= e($f['photo']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105 <?= $estTerminee ? 'grayscale' : '' ?>">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-2xl sm:text-6xl text-slate-300"><i class="fas fa-graduation-cap"></i></div>
                    <?php endif; ?>
                    <?php if ($estTerminee): ?>
                    <span class="absolute top-1.5 left-1.5 sm:top-3 sm:left-3 bg-slate-800 text-white text-[8px] sm:text-[10px] font-black uppercase px-2 sm:px-3 py-1 sm:py-1.5 rounded-full"><i class="fas fa-flag-checkered mr-1"></i>Terminée</span>
                    <?php endif; ?>
                </div>
                <div class="p-2.5 sm:p-6">
                    <h3 class="font-black text-primary text-xs sm:text-xl uppercase italic tracking-tighter group-hover:text-accent transition leading-tight"><?= e($f['nom']) ?></h3>
                    <div class="flex flex-wrap gap-1 sm:gap-2 mt-1.5 sm:mt-3">
                        <?php if ($f['duree']): ?>
                        <span class="text-[9px] sm:text-[11px] font-black text-primary bg-white border border-slate-200 rounded-full px-2 sm:px-3 py-0.5 sm:py-1.5"><i class="fas fa-clock mr-1"></i><?= e($f['duree']) ?></span>
                        <?php endif; ?>
                        <span class="hidden sm:inline-flex">
                        <?php if ($f['public_cible']): ?>
                        <span class="text-[11px] font-black text-primary bg-white border border-slate-200 rounded-full px-3 py-1.5"><i class="fas fa-users mr-1"></i><?= e($f['public_cible']) ?></span>
                        <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($f['description']): ?>
                    <p class="mt-1.5 sm:mt-4 text-[10px] sm:text-sm text-slate-600 leading-snug sm:leading-relaxed line-clamp-2 sm:line-clamp-3"><?= nl2br(e($f['description'])) ?></p>
                    <?php endif; ?>
                    <?php if ($estTerminee): ?>
                    <p class="mt-4 text-xs font-black text-slate-400 uppercase tracking-widest hidden sm:block"><i class="fas fa-info-circle mr-1"></i>Cette formation s'est déroulée au Palais — d'autres sessions pourront être organisées</p>
                    <?php elseif ($f['contact']): ?>
                    <p class="mt-1.5 sm:mt-4 text-[9px] sm:text-xs font-black text-accent uppercase tracking-widest truncate"><i class="fas fa-paper-plane mr-1"></i>Inscription : <?= e($f['contact']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1.5 sm:mt-4 text-[9px] sm:text-[11px] font-black text-accent uppercase tracking-widest">Voir détails <i class="fas fa-arrow-right ml-1"></i></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>