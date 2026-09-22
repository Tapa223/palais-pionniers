<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erreur de connexion.');
}

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header('Location: activites.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Récupération de l'activité
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare('SELECT * FROM activites WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$activite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$activite) {
    header('Location: activites.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Photos de la galerie
|--------------------------------------------------------------------------
| Pas de shuffle() : les photos restent dans le même ordre.
*/
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

/*
|--------------------------------------------------------------------------
| Image principale + photos galerie
|--------------------------------------------------------------------------
*/
$toutesLesPhotos = [];

$imagePrincipale = trim($activite['image_principale'] ?? '');

if ($imagePrincipale !== '' && $imagePrincipale !== 'default-hero.jpg') {
    $toutesLesPhotos[] = [
        'chemin' => 'assets/images/activites/' . basename($imagePrincipale),
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

/*
|--------------------------------------------------------------------------
| Liens facultatifs
|--------------------------------------------------------------------------
*/
$stmtLiens = $pdo->prepare('
    SELECT *
    FROM activite_liens
    WHERE activite_id = ?
    ORDER BY id ASC
');
$stmtLiens->execute([$activite['id']]);
$liens = $stmtLiens->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Photos de présentation
|--------------------------------------------------------------------------
*/
$photosMosaique = array_slice($toutesLesPhotos, 0, 3);
$photosCarrousel = array_slice($toutesLesPhotos, 3);

$accent = trim($activite['couleur'] ?? '') ?: '#E61E2A';

$pageTitle = $activite['nom'] . ' — Palais des Pionniers';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/fonts.css">
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
            z-index: 9999;
        }

        /*
        Permet de couper automatiquement une longue chaîne,
        même lorsqu'elle ne contient aucun espace.
        */
        .activity-description {
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
    </style>
</head>

<body class="bg-[#f8fafc] text-slate-900 font-sans">

    <?php
    if (file_exists(__DIR__ . '/includes/header.php')) {
        include __DIR__ . '/includes/header.php';
    }
    ?>

    <!-- Bouton retour -->
    <div class="container mx-auto px-4 sm:px-6 pt-4 sm:pt-6">
        <a
            href="activites.php"
            class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-3.5 py-2 text-xs font-black uppercase tracking-widest text-primary transition hover:bg-accent/10 hover:text-accent"
        >
            <i class="fas fa-arrow-left"></i>
            Retour aux activités
        </a>
    </div>

    <!-- Hero -->
    <section class="relative mt-4 flex min-h-[300px] items-center overflow-hidden bg-[#0a214a] sm:mt-6 sm:min-h-[430px]">

        <?php if (!empty($toutesLesPhotos)): ?>
            <?php foreach ($toutesLesPhotos as $index => $photo): ?>
                <img
                    src="<?= e($photo['chemin']) ?>"
                    alt=""
                    class="detail-slide absolute inset-0 h-full w-full scale-105 object-cover transition-opacity duration-700 ease-in-out <?= $index === 0 ? 'z-10 opacity-100' : 'z-0 opacity-0' ?>"
                >
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="absolute inset-0 z-10 bg-[#0a214a]/65"></div>

        <div class="container relative z-20 mx-auto px-4 sm:px-6">
            <div class="max-w-4xl">
                <h1 class="text-3xl font-black uppercase italic leading-tight tracking-tighter text-white sm:text-5xl md:text-6xl">
                    <?= e($activite['nom']) ?>
                </h1>

                <div
                    class="mt-3 h-1.5 w-16 sm:mt-5 sm:h-2 sm:w-24"
                    style="background:<?= e($accent) ?>"
                ></div>

                <?php if (!empty($activite['sous_titre'])): ?>
                    <p class="mt-4 max-w-2xl text-sm font-medium leading-relaxed text-white/80 sm:text-lg">
                        <?= e($activite['sous_titre']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($toutesLesPhotos) > 1): ?>
            <div class="absolute bottom-5 left-1/2 z-30 flex -translate-x-1/2 gap-2 sm:bottom-7">
                <?php foreach ($toutesLesPhotos as $index => $photo): ?>
                    <span
                        class="detail-dot h-1.5 rounded-full transition-all <?= $index === 0 ? 'w-7' : 'w-1.5 bg-white/50' ?>"
                        style="<?= $index === 0 ? 'background:' . e($accent) : '' ?>"
                    ></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Présentation -->
    <section class="py-8 sm:py-16">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="mx-auto max-w-6xl space-y-10">

                <?php
                /*
                On affiche les deux colonnes uniquement si au moins
                deux images sont disponibles pour la mosaïque.
                */
                ?>

                <?php if (count($photosMosaique) >= 2): ?>

                    <div class="grid items-start gap-x-10 gap-y-6 md:grid-cols-2 lg:gap-x-16">

                        <!-- Colonne texte -->
                        <div class="min-w-0 max-w-full">

                            <?php if (!empty($activite['sous_titre'])): ?>
                                <p
                                    class="mb-4 text-sm font-bold uppercase tracking-widest sm:text-lg"
                                    style="color:<?= e($accent) ?>"
                                >
                                    <?= e($activite['sous_titre']) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($activite['description'])): ?>
                                <div class="activity-description max-w-full text-base font-light leading-relaxed text-slate-600 sm:text-lg">
                                    <?= nl2br(e($activite['description'])) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($activite['chiffres_cles'])): ?>
                                <div
                                    class="mt-6 rounded-2xl border-l-4 bg-white px-5 py-4 shadow-sm"
                                    style="border-color:<?= e($accent) ?>"
                                >
                                    <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                        Chiffres clés
                                    </p>

                                    <p class="text-base font-black sm:text-lg" style="color:<?= e($accent) ?>">
                                        <i class="fas fa-chart-simple mr-1"></i>
                                        <?= e($activite['chiffres_cles']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Colonne images -->
                        <div
                            class="grid min-w-0 grid-cols-2 gap-3 sm:gap-4"
                            style="grid-template-rows: 160px 110px;"
                        >
                            <?php foreach ($photosMosaique as $index => $photo): ?>
                                <a
                                    href="<?= e($photo['chemin']) ?>"
                                    class="luminous-gallery group relative min-w-0 overflow-hidden rounded-xl bg-slate-50 shadow-sm transition-all hover:shadow-xl sm:rounded-2xl <?= $index === 0 ? 'col-span-2' : '' ?>"
                                    style="grid-row:<?= $index === 0 ? '1' : '2' ?>"
                                >
                                    <img
                                        src="<?= e($photo['chemin']) ?>"
                                        class="gallery-image h-full w-full object-cover"
                                        alt="<?= e($activite['nom']) ?>"
                                    >

                                    <div class="absolute inset-0 flex items-center justify-center bg-[#0a214a]/60 opacity-0 transition-opacity group-hover:opacity-100">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full text-white sm:h-10 sm:w-10"
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

                    <!-- Affichage lorsque l'activité a moins de deux photos -->
                    <div class="max-w-3xl">

                        <?php if (!empty($activite['sous_titre'])): ?>
                            <p
                                class="text-sm font-bold uppercase tracking-widest sm:text-lg"
                                style="color:<?= e($accent) ?>"
                            >
                                <?= e($activite['sous_titre']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($activite['description'])): ?>
                            <div class="activity-description mt-4 max-w-full text-base font-light leading-relaxed text-slate-600 sm:text-xl">
                                <?= nl2br(e($activite['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($activite['chiffres_cles'])): ?>
                            <div
                                class="mt-6 rounded-2xl border-l-4 bg-white px-5 py-4 shadow-sm"
                                style="border-color:<?= e($accent) ?>"
                            >
                                <p class="mb-1 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                    Chiffres clés
                                </p>

                                <p class="text-base font-black sm:text-lg" style="color:<?= e($accent) ?>">
                                    <i class="fas fa-chart-simple mr-1"></i>
                                    <?= e($activite['chiffres_cles']) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php
                    /*
                    S'il reste une seule photo, elle sera affichée dans
                    le carrousel plus bas.
                    */
                    ?>
                    <?php if (!empty($photosMosaique) && empty($photosCarrousel)): ?>
                        <?php $photosCarrousel = $photosMosaique; ?>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- Liens -->
                <?php if (!empty($liens)): ?>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach ($liens as $lien): ?>
                            <?php
                            $url = trim($lien['url'] ?? '');

                            if ($url === '') {
                                continue;
                            }
                            ?>

                            <a
                                href="<?= e($url) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:opacity-90"
                                style="background:<?= e($accent) ?>"
                            >
                                <i class="fas fa-arrow-up-right-from-square"></i>
                                <?= e($lien['titre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Carrousel secondaire -->
                <?php if (!empty($photosCarrousel)): ?>
                    <div class="relative h-[38vh] min-h-[260px] overflow-hidden rounded-2xl bg-slate-100 sm:h-[55vh] sm:min-h-[420px] sm:rounded-[2rem]">

                        <?php foreach ($photosCarrousel as $index => $photo): ?>
                            <a
                                href="<?= e($photo['chemin']) ?>"
                                class="luminous-gallery content-slide group absolute inset-0 block transition-opacity duration-700 ease-in-out <?= $index === 0 ? 'z-10 opacity-100' : 'pointer-events-none z-0 opacity-0' ?>"
                            >
                                <img
                                    src="<?= e($photo['chemin']) ?>"
                                    class="h-full w-full object-cover"
                                    alt="<?= e($activite['nom']) ?>"
                                >

                                <div class="absolute inset-0 flex items-center justify-center bg-[#0a214a]/0 transition-colors group-hover:bg-[#0a214a]/30">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full text-white opacity-0 transition-opacity group-hover:opacity-100 sm:h-12 sm:w-12"
                                        style="background:<?= e($accent) ?>"
                                    >
                                        <i class="fas fa-expand-alt text-sm"></i>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>

                        <?php if (count($photosCarrousel) > 1): ?>
                            <div class="pointer-events-none absolute bottom-4 left-1/2 z-30 flex -translate-x-1/2 gap-2 sm:bottom-6">
                                <?php foreach ($photosCarrousel as $index => $photo): ?>
                                    <span
                                        class="content-dot h-1.5 rounded-full transition-all <?= $index === 0 ? 'w-7' : 'w-1.5 bg-white/50' ?>"
                                        style="<?= $index === 0 ? 'background:' . e($accent) : '' ?>"
                                    ></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="bg-slate-50 py-8 sm:py-20">
        <div class="container mx-auto px-4 sm:px-6">
            <div class="mx-auto flex max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl md:flex-row sm:rounded-[3rem]">

                <div class="bg-[#0a214a] p-8 text-white md:w-1/3 sm:p-12">
                    <h2 class="mb-6 text-2xl font-black uppercase italic sm:text-3xl">
                        Un mot sur ce projet ?
                    </h2>

                    <p class="text-sm leading-relaxed text-slate-300">
                        Pour toute information supplémentaire concernant l'activité
                        <?= e($activite['nom']) ?>, contactez-nous.
                    </p>
                </div>

                <div class="p-8 md:w-2/3 sm:p-12">
                    <form action="#" method="post" class="space-y-5">

                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    for="nom"
                                    class="ml-2 block text-[10px] font-black uppercase tracking-widest text-slate-400"
                                >
                                    Nom complet
                                </label>

                                <input
                                    id="nom"
                                    name="nom"
                                    type="text"
                                    required
                                    class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-5 py-3.5 text-sm outline-none transition focus:border-red-600 focus:ring-2 focus:ring-red-600/20"
                                >
                            </div>

                            <div class="space-y-2">
                                <label
                                    for="email"
                                    class="ml-2 block text-[10px] font-black uppercase tracking-widest text-slate-400"
                                >
                                    Email
                                </label>

                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-5 py-3.5 text-sm outline-none transition focus:border-red-600 focus:ring-2 focus:ring-red-600/20"
                                >
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                for="message"
                                class="ml-2 block text-[10px] font-black uppercase tracking-widest text-slate-400"
                            >
                                Message
                            </label>

                            <textarea
                                id="message"
                                name="message"
                                rows="5"
                                required
                                class="w-full resize-y rounded-2xl border border-slate-100 bg-slate-50 px-5 py-3.5 text-sm outline-none transition focus:border-red-600 focus:ring-2 focus:ring-red-600/20"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-2xl bg-red-600 py-4 text-xs font-black uppercase tracking-[0.18em] text-white shadow-lg shadow-red-600/20 transition hover:bg-red-700"
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
        const accent = <?= json_encode($accent) ?>;

        /*
        |--------------------------------------------------------------------------
        | Lightbox galerie
        |--------------------------------------------------------------------------
        */
        const galleryLinks = document.querySelectorAll('.luminous-gallery');

        if (galleryLinks.length > 0) {
            new LuminousGallery(
                galleryLinks,
                {
                    arrowNavigation: true
                },
                {
                    caption: function (trigger) {
                        const image = trigger.querySelector('img');
                        return image ? image.getAttribute('alt') : '';
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Carrousel hero
        |--------------------------------------------------------------------------
        */
        const detailSlides = document.querySelectorAll('.detail-slide');
        const detailDots = document.querySelectorAll('.detail-dot');

        if (detailSlides.length > 1) {
            let detailIndex = 0;

            setInterval(function () {
                detailSlides[detailIndex].classList.remove('opacity-100', 'z-10');
                detailSlides[detailIndex].classList.add('opacity-0', 'z-0');

                if (detailDots[detailIndex]) {
                    detailDots[detailIndex].classList.remove('w-7');
                    detailDots[detailIndex].classList.add('w-1.5', 'bg-white/50');
                    detailDots[detailIndex].style.background = '';
                }

                detailIndex = (detailIndex + 1) % detailSlides.length;

                detailSlides[detailIndex].classList.remove('opacity-0', 'z-0');
                detailSlides[detailIndex].classList.add('opacity-100', 'z-10');

                if (detailDots[detailIndex]) {
                    detailDots[detailIndex].classList.remove('w-1.5', 'bg-white/50');
                    detailDots[detailIndex].classList.add('w-7');
                    detailDots[detailIndex].style.background = accent;
                }
            }, 4500);
        }

        /*
        |--------------------------------------------------------------------------
        | Carrousel inférieur
        |--------------------------------------------------------------------------
        */
        const contentSlides = document.querySelectorAll('.content-slide');
        const contentDots = document.querySelectorAll('.content-dot');

        if (contentSlides.length > 1) {
            let contentIndex = 0;

            setInterval(function () {
                contentSlides[contentIndex].classList.remove('opacity-100', 'z-10');
                contentSlides[contentIndex].classList.add('opacity-0', 'z-0', 'pointer-events-none');

                if (contentDots[contentIndex]) {
                    contentDots[contentIndex].classList.remove('w-7');
                    contentDots[contentIndex].classList.add('w-1.5', 'bg-white/50');
                    contentDots[contentIndex].style.background = '';
                }

                contentIndex = (contentIndex + 1) % contentSlides.length;

                contentSlides[contentIndex].classList.remove('opacity-0', 'z-0', 'pointer-events-none');
                contentSlides[contentIndex].classList.add('opacity-100', 'z-10');

                if (contentDots[contentIndex]) {
                    contentDots[contentIndex].classList.remove('w-1.5', 'bg-white/50');
                    contentDots[contentIndex].classList.add('w-7');
                    contentDots[contentIndex].style.background = accent;
                }
            }, 5000);
        }
    });
    </script>
</body>
</html>