<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$personnalites = $pdo->query("
    SELECT p.*, e.nom AS espace_nom, e.slug AS espace_slug FROM personnalites p
    LEFT JOIN espaces e ON e.id = p.espace_id
    ORDER BY p.ordre ASC, p.nom ASC
")->fetchAll();
$avecPhoto = array_filter($personnalites, fn($p) => !empty($p['photo']) && file_exists(__DIR__ . '/assets/images/personnalites/' . $p['photo']));

$pageTitle = "Icônes — Palais des Pionniers";
$page = 'personnalites.php';
require __DIR__ . '/includes/header.php';
?>

<?php
// Président et Premier Ministre : pas de fiche admin dédiée, fichiers manuels
$officielsManuels = [
    ['file' => 'assets/images/officiels/president.jpg',        'label' => 'Président de la République'],
    ['file' => 'assets/images/officiels/premier-ministre.jpg', 'label' => 'Premier Ministre'],
];
$bandeauOfficiels = array_values(array_filter($officielsManuels, fn($o) => file_exists(__DIR__ . '/' . $o['file'])));

// Ministre / DG / DGA : gérés depuis Admin > Direction, photo automatiquement reprise ici
$directionLabels = ['ministre' => 'Ministre de la Jeunesse et des Sports', 'dg' => 'Directeur Général du Palais des Pionniers', 'dga' => 'Directeur Général Adjoint du Palais des Pionniers'];
$bandeauDirection = [];
foreach ($pdo->query("SELECT role_key, nom, photo FROM direction") as $d) {
    if (!empty($d['photo']) && file_exists(__DIR__ . '/assets/images/' . $d['photo'])) {
        $bandeauDirection[] = ['file' => 'assets/images/' . $d['photo'], 'label' => $directionLabels[$d['role_key']] ?? $d['nom']];
    }
}

$bandeauIcones = array_values(array_map(fn($p) => [
    'file'  => 'assets/images/personnalites/' . $p['photo'],
    'label' => $p['prenom'] ? $p['prenom'].' '.$p['nom'] : $p['nom'],
], $avecPhoto));
// Officiels manuels d'abord, puis Direction, puis automatiquement toute icône ajoutée en base
$bandeauPhotos = array_merge($bandeauOfficiels, $bandeauDirection, $bandeauIcones);
?>
<section class="relative flex items-center min-h-[42vh] sm:min-h-0 sm:max-h-[52vh] py-8 sm:py-14 bg-slate-900 text-white overflow-hidden">

    <?php if ($bandeauPhotos): ?>
    <!-- Photos en fond, glissant lentement de gauche à droite, sous le texte -->
    <div class="absolute inset-0 z-0">
        <div class="marquee-bg flex gap-3 sm:gap-6 w-max h-full opacity-60">
            <?php foreach (array_merge($bandeauPhotos, $bandeauPhotos, $bandeauPhotos) as $slide): ?>
            <div class="relative flex-shrink-0 w-64 sm:w-72 h-full">
                <img src="<?= e($slide['file']) ?>" loading="lazy" class="w-full h-full object-cover">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="absolute inset-0 bg-gradient-to-b from-slate-900/70 via-slate-900/40 to-slate-900/75 z-10"></div>
    <?php endif; ?>

    <div class="container mx-auto px-4 text-center relative z-20">
        <div class="block mx-6 sm:inline-block sm:mx-auto max-w-3xl bg-slate-900/60 rounded-2xl sm:rounded-[3rem] px-5 py-6 sm:px-14 sm:py-12 overflow-hidden">
        <h2 class="text-accent font-black tracking-[0.2em] sm:tracking-[0.3em] uppercase text-[9px] sm:text-xs mb-2 sm:mb-4">Mémoire & Héritage</h2>
        <h1 class="text-2xl sm:text-5xl md:text-7xl font-black italic tracking-tighter uppercase mb-2 sm:mb-6">
            Les <span class="text-accent">Icônes</span>
        </h1>
        <p class="max-w-2xl mx-auto text-xs sm:text-xl text-slate-300 font-medium leading-snug sm:leading-relaxed">
            Des bâtisseurs et figures nationales qui incarnent l'esprit du Palais — certains ont même donné leur nom à l'un de nos espaces, en hommage à leur parcours.
        </p>
        <?php if (!$bandeauPhotos): ?>
        <p class="mt-8 text-[11px] text-white/40 italic">Les photos officielles seront affichées ici dès qu'elles seront ajoutées dans <code class="bg-white/10 px-1.5 py-0.5 rounded">assets/images/officiels/</code>.</p>
        <?php endif; ?>
        </div>
    </div>
</section>

<section class="pt-3 pb-8 sm:pt-6 sm:pb-24 bg-white">
    <div class="container mx-auto px-4">
        <?php if (!$personnalites): ?>
        <p class="text-center text-slate-400 italic py-10">Aucune icône n'a encore été ajoutée.</p>
        <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-8">
            <?php foreach ($personnalites as $p): ?>
            <a href="personnalite.php?id=<?= (int)$p['id'] ?>" class="bg-slate-50 rounded-2xl sm:rounded-[2rem] overflow-hidden border border-slate-100 hover:shadow-2xl transition-all group block">
                <div class="aspect-[4/3] bg-slate-200 overflow-hidden">
                    <?php if ($p['photo'] && file_exists(__DIR__ . '/assets/images/personnalites/' . $p['photo'])): ?>
                    <img src="assets/images/personnalites/<?= e($p['photo']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-3xl sm:text-6xl text-slate-300"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                </div>
                <div class="p-3 sm:p-6">
                    <h3 class="font-black text-primary text-sm sm:text-xl uppercase italic tracking-tighter group-hover:text-accent transition">
                        <?= e($p['prenom'] ? $p['prenom'].' '.$p['nom'] : $p['nom']) ?>
                    </h3>
                    <?php if ($p['titre']): ?>
                    <p class="text-[10px] sm:text-xs font-bold text-accent uppercase tracking-widest mt-1"><?= e($p['titre']) ?></p>
                    <?php endif; ?>
                    <?php if ($p['espace_nom']): ?>
                    <span class="inline-flex items-center gap-1.5 mt-2 sm:mt-3 text-[9px] sm:text-[11px] font-black text-primary bg-white border border-slate-200 rounded-full px-2 sm:px-3 py-1 sm:py-1.5">
                        <i class="fas fa-building"></i> <?= e($p['espace_nom']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($p['parcours']): ?>
                    <p class="mt-4 text-sm text-slate-600 leading-relaxed line-clamp-3"><?= nl2br(e($p['parcours'])) ?></p>
                    <p class="mt-3 text-[11px] font-black text-accent uppercase tracking-widest">Lire le parcours complet <i class="fas fa-arrow-right ml-1"></i></p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

