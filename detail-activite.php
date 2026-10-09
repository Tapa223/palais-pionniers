<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

try {
    $pdo = db();
} catch (Exception $e) {
    die('Erreur de connexion.');
}

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: activites.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM activites WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    require __DIR__ . '/404.php';
    exit;
}

$stmtPh = $pdo->prepare('
    SELECT image_path
    FROM activite_galerie
    WHERE activite_id = ?
    ORDER BY id ASC
');
$stmtPh->execute([$activite['id']]);

$photosGalerie = array_column(
    $stmtPh->fetchAll(PDO::FETCH_ASSOC),
    'image_path'
);

$toutesLesPhotos = [];

$imagePrincipale = trim($activite['image_principale'] ?? '');

if ($imagePrincipale !== '' && $imagePrincipale !== 'default-hero.jpg') {
    $toutesLesPhotos[] = [
        'chemin' => 'assets/images/activites/' . basename($imagePrincipale),
        'legende_absente' => true,
    ];
}

foreach ($photosGalerie as $photo) {
    $photo = trim($photo);

    if ($photo !== '') {
        $toutesLesPhotos[] = [
            'chemin' => 'assets/images/galerie/' . basename($photo),
        ];
    }
}

$stmtLiens = $pdo->prepare('
    SELECT *
    FROM activite_liens
    WHERE activite_id = ?
    ORDER BY id ASC
');
$stmtLiens->execute([$activite['id']]);
$liens = $stmtLiens->fetchAll(PDO::FETCH_ASSOC);
$liens = array_values(array_filter($liens, fn($l) => url_web_valide((string)($l['url'] ?? ''))));

$accent = trim($activite['couleur'] ?? '') ?: '#E61E2A';

$photosMosaique = array_slice($toutesLesPhotos, 0, 3);
$photosCarrousel = array_slice($toutesLesPhotos, 3);

$pageTitle = $activite['nom'] . ' | Palais des Pionniers';
$pageDescription = resume_texte(($activite['sous_titre'] ?? '') !== '' ? $activite['sous_titre'] . '. ' . ($activite['description'] ?? '') : ($activite['description'] ?? ''))
    ?: ($activite['nom'] . ' : une activité du Palais des Pionniers à Bamako.');
if ($toutesLesPhotos) {
    $pageImage = $toutesLesPhotos[0]['chemin'];
}
$page = 'activites.php';
ob_start();
?>
    <link rel="stylesheet" href="assets/vendor/luminous/luminous-basic.min.css">
    <style>
        .gallery-image {
            transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: zoom-in;
        }

        .group:hover .gallery-image {
            transform: scale(1.08);
        }

        .lum-lightbox {
            z-index: 1000;
        }

        .activity-description {
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>
<?php $pageHeadExtra = ob_get_clean(); ?>

    <?php
    require __DIR__ . '/includes/header.php';
    ?>

    <div class="container mx-auto px-4 sm:px-6 pt-4 sm:pt-6">
        <a
            href="<?= lien_page('activites.php') ?>"
            class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary hover:text-accent transition bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full"
        >
            <i class="fas fa-arrow-left"></i>
            Retour aux activités
        </a>
    </div>

    <section class="relative h-[28vh] sm:h-[45vh] flex items-center bg-[#0a214a] overflow-hidden">

        <?php if ($toutesLesPhotos): ?>
            <?php foreach ($toutesLesPhotos as $i => $p): ?>
                <img
                    src="<?= e($p['chemin']) ?>"
                    alt=""
                    class="detail-slide absolute inset-0 w-full h-full object-cover scale-110 blur-sm transition-opacity duration-700 ease-in-out <?= $i === 0 ? 'opacity-100' : 'opacity-0' ?>"
                >
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="absolute inset-0 bg-[#0a214a]/60"></div>

        <div class="container mx-auto px-4 sm:px-6 relative z-10">
            <h1 class="text-2xl sm:text-5xl md:text-6xl font-black text-white uppercase italic tracking-tighter leading-tight">
                <?= e($activite['nom']) ?>
            </h1>

            <div
                class="h-1.5 sm:h-2 w-14 sm:w-20 mt-2 sm:mt-4"
                style="background:<?= e($accent) ?>"
            ></div>
        </div>
    </section>

    <section class="py-8 sm:py-16">
        <div class="container mx-auto px-4 sm:px-6">

            <div class="space-y-10 sm:space-y-14">

                <?php if (count($photosMosaique) >= 2): ?>

                    <div class="grid grid-cols-1 items-start gap-y-8 md:grid-cols-2 md:gap-x-12 lg:gap-x-20 xl:gap-x-24">

                        <div class="min-w-0 max-w-full">

                            <?php if (!empty($activite['sous_titre'])): ?>
                                <p
                                    class="mb-4 text-sm sm:text-lg font-bold uppercase tracking-widest"
                                    style="color:<?= e($accent) ?>"
                                >
                                    <?= e($activite['sous_titre']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($activite['description'])): ?>
                                <div class="activity-description max-w-full text-base sm:text-lg leading-relaxed text-slate-600 font-light">
                                    <?= nl2br(e($activite['description'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($activite['chiffres_cles'])): ?>
                                <div
                                    class="mt-6 sm:mt-8 rounded-2xl border-l-4 bg-white px-5 py-4 shadow-sm"
                                    style="border-color:<?= e($accent) ?>"
                                >
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">
                                        Chiffres clés
                                    </p>

                                    <p
                                        class="text-sm sm:text-base font-black uppercase tracking-wide"
                                        style="color:<?= e($accent) ?>"
                                    >
                                        <i class="fas fa-chart-simple mr-1"></i>
                                        <?= e($activite['chiffres_cles']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($liens): ?>
                                <div class="mt-6 sm:mt-8 flex flex-wrap gap-3">
                                    <?php foreach ($liens as $l): ?>
                                        <?php if (!empty($l['url'])): ?>
                                            <a
                                                href="<?= e($l['url']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-2 px-4 sm:px-5 py-3 rounded-xl text-white text-[10px] sm:text-xs font-black uppercase tracking-widest hover:opacity-90 transition"
                                                style="background:<?= e($accent) ?>"
                                            >
                                                <i class="fas fa-arrow-up-right-from-square"></i>
                                                <?= e($l['titre']) ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="grid min-w-0 grid-cols-2 gap-3 sm:gap-4">

                            <?php foreach ($photosMosaique as $i => $p): ?>
                                <a
                                    href="<?= e($p['chemin']) ?>"
                                    class="luminous-gallery group relative overflow-hidden rounded-xl sm:rounded-2xl bg-slate-50 shadow-sm transition-all hover:shadow-xl <?= $i === 0 ? 'col-span-2 aspect-[16/9]' : 'aspect-[4/3]' ?>"
                                >
                                    <img
                                        src="<?= e($p['chemin']) ?>"
                                        class="gallery-image w-full h-full object-cover"
                                        alt="<?= e($activite['nom']) ?>"
                                    >

                                    <div class="absolute inset-0 bg-[#0a214a]/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <div
                                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-white"
                                            style="background:<?= e($accent) ?>"
                                        >
                                            <i class="fas fa-expand-alt text-xs"></i>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                        </div>
                    </div>

                <?php else: ?>

                    <div class="max-w-3xl">

                        <?php if (!empty($activite['sous_titre'])): ?>
                            <p
                                class="text-sm sm:text-lg font-bold uppercase tracking-widest"
                                style="color:<?= e($accent) ?>"
                            >
                                <?= e($activite['sous_titre']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($activite['description'])): ?>
                            <div class="activity-description mt-4 max-w-full text-base sm:text-xl leading-relaxed text-slate-600 font-light">
                                <?= nl2br(e($activite['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($activite['chiffres_cles'])): ?>
                            <div
                                class="mt-6 rounded-2xl border-l-4 bg-white px-5 py-4 shadow-sm"
                                style="border-color:<?= e($accent) ?>"
                            >
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">
                                    Chiffres clés
                                </p>

                                <p
                                    class="text-sm sm:text-base font-black uppercase tracking-wide"
                                    style="color:<?= e($accent) ?>"
                                >
                                    <i class="fas fa-chart-simple mr-1"></i>
                                    <?= e($activite['chiffres_cles']) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <?php if ($liens): ?>
                            <div class="mt-6 flex flex-wrap gap-3">
                                <?php foreach ($liens as $l): ?>
                                    <?php if (!empty($l['url'])): ?>
                                        <a
                                            href="<?= e($l['url']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-2 px-4 sm:px-5 py-3 rounded-xl text-white text-[10px] sm:text-xs font-black uppercase tracking-widest hover:opacity-90 transition"
                                            style="background:<?= e($accent) ?>"
                                        >
                                            <i class="fas fa-arrow-up-right-from-square"></i>
                                            <?= e($l['titre']) ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    ?>
                    <?php if ($photosMosaique && !$photosCarrousel): ?>
                        <?php $photosCarrousel = $photosMosaique; ?>
                    <?php endif; ?>

                <?php endif; ?>

                <?php if ($photosCarrousel): ?>
                    <div class="relative rounded-2xl sm:rounded-[2rem] overflow-hidden h-[38vh] min-h-[260px] sm:h-[55vh] sm:min-h-[420px] bg-slate-100">

                        <?php foreach ($photosCarrousel as $i => $p): ?>
                            <a
                                href="<?= e($p['chemin']) ?>"
                                class="luminous-gallery content-slide group absolute inset-0 block transition-opacity duration-700 ease-in-out <?= $i === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none' ?>"
                            >
                                <img
                                    src="<?= e($p['chemin']) ?>"
                                    class="w-full h-full object-cover"
                                    alt="<?= e($activite['nom']) ?>"
                                >

                                <div class="absolute inset-0 bg-[#0a214a]/0 group-hover:bg-[#0a214a]/30 transition-colors flex items-center justify-center">
                                    <div
                                        class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity"
                                        style="background:<?= e($accent) ?>"
                                    >
                                        <i class="fas fa-expand-alt text-sm"></i>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <?php if (count($photosCarrousel) > 1): ?>
                            <div class="absolute bottom-3 sm:bottom-5 left-1/2 -translate-x-1/2 z-30 flex gap-1.5 pointer-events-none">
                                <?php foreach ($photosCarrousel as $i => $p): ?>
                                    <span
                                        class="content-dot h-1.5 rounded-full transition-all <?= $i === 0 ? 'w-6' : 'w-1.5 bg-white/50' ?>"
                                        style="<?= $i === 0 ? "background:" . e($accent) : '' ?>"
                                    ></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <section class="py-8 sm:py-20 bg-slate-50">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="max-w-5xl mx-auto bg-white rounded-2xl sm:rounded-[3rem] shadow-2xl overflow-hidden flex flex-col md:flex-row">

                <div class="md:w-1/3 bg-[#0a214a] p-8 sm:p-12 text-white">
                    <h3 class="text-2xl sm:text-3xl font-black uppercase italic mb-6">
                        Un mot sur ce projet ?
                    </h3>

                    <p class="text-slate-400 text-sm leading-relaxed">
                        Pour toute information supplémentaire concernant l'activité
                        <?= e($activite['nom']) ?>, contactez-nous.
                    </p>
                </div>

                <div class="md:w-2/3 p-8 sm:p-12">
                    <form action="#" method="POST" class="space-y-5 sm:space-y-6">

                        <div class="grid md:grid-cols-2 gap-5 sm:gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">
                                    Nom complet
                                </label>

                                <input
                                    type="text"
                                    name="nom"
                                    class="w-full px-5 sm:px-6 py-3.5 sm:py-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-red-600 outline-none"
                                >
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    class="w-full px-5 sm:px-6 py-3.5 sm:py-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-red-600 outline-none"
                                >
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">
                                Message
                            </label>

                            <textarea
                                name="message"
                                rows="4"
                                class="w-full px-5 sm:px-6 py-3.5 sm:py-4 bg-slate-50 rounded-2xl border-none focus:ring-2 focus:ring-red-600 outline-none"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-4 sm:py-5 bg-red-600 text-white rounded-2xl font-black uppercase text-xs tracking-[0.2em] hover:bg-red-700 transition-all shadow-lg shadow-red-600/20"
                        >
                            Envoyer le message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        include __DIR__ . '/includes/footer.php';
    }
    ?>

    <script src="assets/vendor/luminous/luminous.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            var galleryLinks = document.querySelectorAll('.luminous-gallery');

            if (galleryLinks.length > 0) {
                new LuminousGallery(
                    galleryLinks,
                    {
                        arrowNavigation: true
                    },
                    {
                        caption: function (trigger) {
                            var image = trigger.querySelector('img');
                            var d = document.createElement('div');
                            d.textContent = image ? image.getAttribute('alt') : '';
                            return d.innerHTML;
                        }
                    }
                );
            }

            var detailSlides = document.querySelectorAll('.detail-slide');
            var detailDots = document.querySelectorAll('.detail-dot');
            var accentCouleur = <?= json_encode($accent) ?>;

            if (detailSlides.length > 1) {
                var detailIdx = 0;

                setInterval(function () {
                    detailSlides[detailIdx].classList.replace('opacity-100', 'opacity-0');
                    detailSlides[detailIdx].classList.replace('z-10', 'z-0');

                    if (detailDots[detailIdx]) {
                        detailDots[detailIdx].classList.replace('w-6', 'w-1.5');
                        detailDots[detailIdx].classList.add('bg-white/40');
                        detailDots[detailIdx].style.background = '';
                    }

                    detailIdx = (detailIdx + 1) % detailSlides.length;

                    detailSlides[detailIdx].classList.replace('opacity-0', 'opacity-100');
                    detailSlides[detailIdx].classList.replace('z-0', 'z-10');

                    if (detailDots[detailIdx]) {
                        detailDots[detailIdx].classList.replace('w-1.5', 'w-6');
                        detailDots[detailIdx].classList.remove('bg-white/40');
                        detailDots[detailIdx].style.background = accentCouleur;
                    }
                }, 4000);
            }

            var contentSlides = document.querySelectorAll('.content-slide');
            var contentDots = document.querySelectorAll('.content-dot');

            if (contentSlides.length > 1) {
                var contentIdx = 0;

                setInterval(function () {
                    contentSlides[contentIdx].classList.replace('opacity-100', 'opacity-0');
                    contentSlides[contentIdx].classList.replace('z-10', 'z-0');
                    contentSlides[contentIdx].classList.add('pointer-events-none');

                    if (contentDots[contentIdx]) {
                        contentDots[contentIdx].classList.replace('w-6', 'w-1.5');
                        contentDots[contentIdx].classList.add('bg-white/50');
                        contentDots[contentIdx].style.background = '';
                    }

                    contentIdx = (contentIdx + 1) % contentSlides.length;

                    contentSlides[contentIdx].classList.replace('opacity-0', 'opacity-100');
                    contentSlides[contentIdx].classList.replace('z-0', 'z-10');
                    contentSlides[contentIdx].classList.remove('pointer-events-none');

                    if (contentDots[contentIdx]) {
                        contentDots[contentIdx].classList.replace('w-1.5', 'w-6');
                        contentDots[contentIdx].classList.remove('bg-white/50');
                        contentDots[contentIdx].style.background = accentCouleur;
                    }
                }, 4500);
            }
        });
    </script>
