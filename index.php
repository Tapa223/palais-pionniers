<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

$espaces = $pdo->query("
    SELECT e.id, e.slug, e.nom, e.capacite, c.nom AS categorie,
           (SELECT chemin FROM espace_images WHERE espace_id = e.id ORDER BY id ASC LIMIT 1) as photo
    FROM espaces e
    JOIN categories c ON c.id = e.categorie_id
    ORDER BY e.id ASC
    LIMIT 4
")->fetchAll();

$nbEspaces    = (int)$pdo->query("SELECT COUNT(*) FROM espaces WHERE disponible = 1")->fetchColumn();
$nbActivites  = (int)$pdo->query("SELECT COUNT(*) FROM activites")->fetchColumn();
$nbCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$nbChambres   = (int)$pdo->query("SELECT COALESCE(SUM(quantite_disponible),0) FROM tarifs t JOIN espaces e ON e.id = t.espace_id WHERE e.mode_reservation = 'sejour'")->fetchColumn();
$nbPersonnalites = (int)$pdo->query("SELECT COUNT(*) FROM personnalites")->fetchColumn();

$direction = [];

foreach ($pdo->query("SELECT * FROM direction")->fetchAll() as $d) {
    $direction[$d['role_key']] = $d;
}

function initiales($nom) {
    $mots = preg_split('/\s+/', trim($nom));
    $init = '';

    foreach ($mots as $m) {
        if ($m !== '') {
            $init .= mb_strtoupper(mb_substr($m, 0, 1));
        }
    }

    return mb_substr($init, 0, 3);
}

$pageTitle = "Accueil | Palais des Pionniers du Mali";
$page = 'index.php';

require __DIR__ . '/includes/header.php';
?>


<?php
$heroSlidesTextes = [
    [
        'titre' => 'Le Temple du <br><span class="text-accent italic">Citoyen</span>',
        'sujet' => "L'esprit civique d'une génération se forge ici."
    ],
    [
        'titre' => 'Ta Place <br><span class="text-accent italic">T\'Attend</span>',
        'sujet' => "Sport, formation, civisme : rejoins-nous."
    ],
    [
        'titre' => 'Bâtir le Mali <br><span class="text-accent italic">de Demain</span>',
        'sujet' => "Une jeunesse formée et unie."
    ],
];
?>

<section class="relative overflow-hidden bg-slate-900 text-white">

    <div class="absolute inset-0 bg-gradient-to-r from-primary/90 via-primary/30 to-transparent z-10"></div>

    <div id="heroCarousel" class="absolute inset-0 z-0">

        <?php
        $slides = [
            'assets/images/porte.jpeg',
            'assets/images/adminis.jpeg',
            'assets/images/groupewague.jpg'
        ];

        foreach ($slides as $index => $src):
        ?>

            <div class="carousel-img absolute inset-0 w-full h-full transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>">

                <div
                    class="w-full h-full kenburns"
                    style="background: url('<?= $src ?>') center/cover no-repeat;"
                ></div>

            </div>

        <?php endforeach; ?>

    </div>

    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex gap-2">

        <?php foreach ($slides as $index => $src): ?>

            <button
                onclick="goToSlide(<?= $index ?>)"
                data-dot="<?= $index ?>"
                class="carousel-dot h-1.5 rounded-full transition-all <?= $index === 0 ? 'w-8 bg-accent' : 'w-1.5 bg-white/40 hover:bg-white/70' ?>"
            ></button>

        <?php endforeach; ?>

    </div>

    <div class="container relative z-20 mx-auto grid min-h-0 sm:min-h-[62vh] items-center gap-12 px-4 py-8 sm:py-10 md:grid-cols-12">

        <div class="md:col-span-8 lg:col-span-7">

            <div class="inline-flex items-center gap-2 sm:gap-3 rounded-full border border-white/20 bg-white/10 px-4 sm:px-5 py-2 text-[10px] sm:text-xs font-bold tracking-[0.15em] sm:tracking-[0.2em] text-accent backdrop-blur-xl uppercase mb-3 sm:mb-4">

                <i class="fas fa-landmark animate-bounce"></i>

                <span>
                    Établissement Public à Caractère Scientifique et Technologique • Vision Mali Kura
                </span>

            </div>

            <div id="heroTexteBloc" class="transition-opacity duration-500 ease-in-out">

                <h1
                    id="heroTitre"
                    class="text-4xl font-black leading-[1.05] sm:text-5xl md:text-6xl lg:text-7xl drop-shadow-2xl mb-3"
                >
                    <?= $heroSlidesTextes[0]['titre'] ?>
                </h1>

                <p
                    id="heroSujet"
                    class="text-sm sm:text-lg text-white/80 max-w-lg font-medium mb-3"
                >
                    <?= e($heroSlidesTextes[0]['sujet']) ?>
                </p>

            </div>

            <div class="flex gap-1.5 h-1.5 w-32 mb-4 rounded-full overflow-hidden">
                <div class="flex-1 bg-[#14B53A]"></div>
                <div class="flex-1 bg-[#FCD116]"></div>
                <div class="flex-1 bg-[#CE1126]"></div>
            </div>

            <p class="max-w-2xl text-sm sm:text-base text-slate-200 font-medium leading-relaxed drop-shadow-md hidden sm:block">
                Le socle de l'État pour la formation citoyenne et l'épanouissement de la jeunesse malienne.
            </p>

            <div class="mt-4 sm:mt-6 flex flex-col sm:flex-row flex-wrap gap-3 sm:gap-4">

                <a
                    href="#description"
                    class="text-center bg-accent text-white px-6 sm:px-8 py-3 sm:py-4 rounded-2xl font-black shadow-2xl transition-all hover:scale-105 hover:bg-accent-dark text-sm sm:text-base"
                >
                    Découvrir l'Institution
                    <i class="fas fa-chevron-down ml-2"></i>
                </a>

                <a
                    href="sengager.php"
                    class="text-center bg-white/10 backdrop-blur-md text-white border-2 border-white/20 px-6 sm:px-8 py-3 sm:py-4 rounded-2xl font-black transition-all hover:bg-white/20 text-sm sm:text-base"
                >
                    <i class="fas fa-hand-fist mr-2"></i>
                    S'engager
                </a>

            </div>

        </div>

    </div>

</section>


<section class="bg-white border-b border-slate-100 relative z-20 -mt-1">

    <div class="container mx-auto px-4">

        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-slate-100">

            <?php

            $chiffres = [
                ['fa-building', $nbEspaces, 'Espaces disponibles'],
                ['fa-star', $nbActivites, 'Activités proposées'],
                ['fa-th-large', $nbCategories, "Catégories d'infrastructures"],
                ['fa-bed', $nbChambres, 'Chambres en hébergement'],
            ];

            foreach ($chiffres as $i => [$icon, $val, $label]):

            ?>

                <div class="flex flex-col items-center justify-center text-center px-4 py-8 sm:py-10">

                    <i class="fas <?= $icon ?> text-accent text-xl mb-3"></i>

                    <span
                        class="counter text-3xl sm:text-4xl font-black text-primary tracking-tighter"
                        data-target="<?= $val ?>"
                    >
                        0
                    </span>

                    <span class="mt-1 text-[10px] sm:text-xs font-bold uppercase tracking-widest text-slate-400 leading-tight max-w-[10rem]">
                        <?= $label ?>
                    </span>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<?php

$presentationSlides = [
    'assets/images/description/description-1.jpg',
    'assets/images/description/description-2.jpg',
    'assets/images/description/description-3.jpg',
    'assets/images/description/description-4.jpg',
    'assets/images/description/description-5.jpeg',
    'assets/images/description/description-6.jpg',
    'assets/images/description/description-7.jpg',
];
$presentationSlides = array_values(array_filter($presentationSlides, fn($img) => is_file(__DIR__ . '/' . $img)));

?>

<section id="description" class="py-10 sm:py-24 bg-white relative overflow-hidden">

    <div class="container mx-auto px-4">

        <div class="grid lg:grid-cols-2 gap-6 sm:gap-16 items-center">

            <div class="relative">

                <div class="aspect-square rounded-2xl sm:rounded-[4rem] overflow-hidden shadow-xl sm:shadow-2xl border-4 sm:border-8 border-slate-50 relative">

                    <?php foreach ($presentationSlides as $index => $src): ?>

                        <img
                            src="<?= e($src) ?>"
                            loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                            class="carousel-img-presentation absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
                        >

                    <?php endforeach; ?>

                </div>

                <div class="absolute -bottom-4 -right-2 sm:-bottom-8 sm:-right-8 bg-primary text-white p-3 sm:p-10 rounded-xl sm:rounded-[3rem] shadow-xl sm:shadow-3xl max-w-[9rem] sm:max-w-xs">

                    <p class="text-xs sm:text-2xl font-black mb-0.5 sm:mb-2 italic">
                        Mali Kura
                    </p>

                    <p class="text-[8px] sm:text-sm font-bold uppercase tracking-widest text-white/80">
                        L'intégrité comme fondement
                    </p>

                </div>

            </div>

            <div>

                <h2 class="text-[10px] sm:text-sm font-black text-accent uppercase tracking-[0.2em] sm:tracking-[0.3em] mb-2 sm:mb-6">
                    Présentation du Palais
                </h2>

                <h3 class="text-2xl sm:text-4xl font-black text-slate-900 mb-4 sm:mb-8 leading-snug sm:leading-tight uppercase italic tracking-tighter">
                    Un Pilier de la
                    <span class="text-primary">Souveraineté Nationale</span>
                </h3>

                <div class="space-y-4 sm:space-y-6 text-sm sm:text-lg text-slate-600 leading-relaxed text-justify">

    <p>
        Créé par la Loi n°2022-022 du 28 juin 2022, le Palais des Pionniers est un
        <strong>Établissement Public à caractère Scientifique et Technologique (EPST)</strong>,
        placé sous la tutelle du Ministère de la Jeunesse et des Sports, chargé de l’Instruction Civique et de la Construction Citoyenne.
    </p>

    <p>
        Véritable carrefour du Mali Kura, le Palais des Pionniers est un espace dédié à la jeunesse malienne,
        à son épanouissement, à son apprentissage et à son engagement citoyen. Il contribue à la formation
        d’une jeunesse responsable, patriote et engagée.
    </p>

    <div class="p-6 bg-slate-50 rounded-2xl border-l-4 border-accent">

        <p class="text-slate-800 font-bold italic">
            Le Palais dispose également d’espaces de qualité pouvant être réservés pour des réunions,
            formations, conférences et divers événements.
        </p>

    </div>

</div>

            </div>

        </div>

    </div>

</section>


<?php
$dMin = $direction['ministre'] ?? null;
if ($dMin):
?>


<section class="py-10 sm:py-24 bg-primary relative overflow-hidden text-white">

    <div class="container mx-auto px-4">

        <div class="max-w-5xl mx-auto flex flex-col md:flex-row items-center gap-4 sm:gap-16">

            <div class="w-full md:w-2/5 flex justify-center md:block">

                <?php if (!empty($dMin['photo'])): ?>

                    <img
                        src="assets/images/<?= e($dMin['photo']) ?>"
                        class="w-40 h-48 sm:w-full sm:h-auto object-cover rounded-2xl sm:rounded-[4rem] shadow-xl sm:shadow-2xl border-4 sm:border-8 border-white/10"
                        alt="<?= e($dMin['nom']) ?>"
                    >

                <?php else: ?>

                    <div class="w-40 h-48 sm:w-full sm:h-64 rounded-2xl sm:rounded-[4rem] shadow-xl sm:shadow-2xl border-4 sm:border-8 border-white/10 bg-white/10 flex items-center justify-center">

                        <span class="text-white text-2xl sm:text-6xl font-black italic">
                            <?= e(initiales($dMin['nom'])) ?>
                        </span>

                    </div>

                <?php endif; ?>

            </div>

            <div class="w-full md:w-3/5 text-center md:text-left">

                <h2 class="text-accent font-black tracking-widest uppercase text-[10px] sm:text-sm mb-2 sm:mb-6 italic">
                    La Vision Ministérielle
                </h2>

                <p class="text-lg font-black mb-1">
                    <?= e($dMin['nom']) ?>
                </p>

                <p class="text-[10px] font-bold text-accent uppercase tracking-widest mb-4 sm:hidden">
                    <?= e($dMin['titre']) ?>
                </p>

                <i class="fas fa-quote-left text-3xl sm:text-5xl text-accent/30 mb-2 sm:mb-6 hidden sm:block"></i>

                <?php if ($dMin['citation']): ?>

                    <h3 class="text-lg sm:text-3xl md:text-4xl font-black mb-3 sm:mb-8 leading-snug sm:leading-tight">
                        <?= e($dMin['citation']) ?>
                    </h3>

                <?php endif; ?>

                <?php if ($dMin['texte']): ?>

                    <p class="text-sm sm:text-xl text-white/80 leading-relaxed mb-4 sm:mb-8 italic">
                        <?= nl2br(e($dMin['texte'])) ?>
                    </p>

                <?php endif; ?>

                <div class="hidden sm:flex items-center gap-4">

                    <div class="w-12 h-px bg-accent"></div>

                    <div>

                        <p class="text-2xl font-black">
                            <?= e($dMin['nom']) ?>
                        </p>

                        <p class="text-xs font-bold text-accent uppercase tracking-widest">
                            <?= e($dMin['titre']) ?>
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<?php endif; ?>


<section class="py-14 sm:py-24 bg-slate-900 text-white">

    <div class="container mx-auto px-4">

        <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-20">

            <h2 class="text-accent font-black tracking-widest uppercase text-xs sm:text-sm mb-3 sm:mb-4">
                Missions & Objectifs
            </h2>

            <h3 class="text-2xl sm:text-4xl md:text-5xl font-black uppercase tracking-tighter italic">
                Nos Missions
            </h3>

            <p class="mt-4 text-xs sm:text-sm text-slate-400">
                Conformément à la Loi n°2022-022 du 28 juin 2022 portant création du Palais des Pionniers
            </p>

        </div>


        <div class="sm:hidden flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 -mx-4 px-4 scrollbar-hide">

            <div class="snap-center flex-shrink-0 w-[82%] bg-white/5 p-5 rounded-2xl border border-white/10">

                <h4 class="text-lg font-black mb-3 text-accent flex items-center gap-3 italic uppercase tracking-tighter">
                    Mission première
                </h4>

                <ul class="space-y-2.5 text-xs text-slate-300">

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Sensibiliser et former les pouvoirs publics et les communautés sur l'éducation pionnière.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Élaborer et mettre en œuvre des programmes de recherche sur l'éducation pionnière.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Contribuer à la définition et à la mise en œuvre de la construction citoyenne.
                    </li>

                </ul>

            </div>


            <div class="snap-center flex-shrink-0 w-[82%] bg-white/5 p-5 rounded-2xl border border-white/10">

                <h4 class="text-lg font-black mb-3 text-white flex items-center gap-3 italic uppercase tracking-tighter">
                    Attributions
                </h4>

                <ul class="space-y-2.5 text-xs text-slate-300">

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Appuyer les organisations de promotion de la citoyenneté et du civisme.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Diffuser les résultats d'études et de recherches.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Assurer un appui technique aux programmes de construction citoyenne.
                    </li>

                </ul>

            </div>


            <div class="snap-center flex-shrink-0 w-[82%] bg-accent/20 p-5 rounded-2xl border border-accent/20 shadow-2xl">

                <h4 class="text-lg font-black mb-3 text-accent flex items-center gap-3 italic uppercase tracking-tighter">
                    Au quotidien
                </h4>

                <ul class="space-y-2.5 text-xs text-white">

                    <li class="flex gap-3">
                        <i class="fas fa-star mt-1"></i>
                        Fournir des prestations de service dans ses domaines de compétence.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-star mt-1"></i>
                        Offrir un cadre d'échanges et des espaces de loisirs sains.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-star mt-1"></i>
                        Appuyer la formation des ressources humaines à l'éducation pionnière.
                    </li>

                </ul>

            </div>

        </div>


        <div class="hidden sm:grid sm:grid-cols-3 gap-8">

            <div class="bg-white/5 p-10 rounded-[3rem] border border-white/10 hover:bg-white/10 transition">

                <h4 class="text-2xl font-black mb-6 text-accent flex items-center gap-3 italic uppercase tracking-tighter">
                    Mission première
                </h4>

                <ul class="space-y-4 text-sm text-slate-300">

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Sensibiliser et former les pouvoirs publics et les communautés sur l'éducation pionnière.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Élaborer et mettre en œuvre des programmes de recherche sur l'éducation pionnière.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Contribuer à la définition et à la mise en œuvre de la construction citoyenne.
                    </li>

                </ul>

            </div>


            <div class="bg-white/5 p-10 rounded-[3rem] border border-white/10 hover:bg-white/10 transition">

                <h4 class="text-2xl font-black mb-6 text-white flex items-center gap-3 italic uppercase tracking-tighter">
                    Attributions
                </h4>

                <ul class="space-y-4 text-sm text-slate-300">

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Appuyer les organisations de promotion de la citoyenneté et du civisme.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Diffuser les résultats d'études et de recherches.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-check text-accent mt-1"></i>
                        Assurer un appui technique aux programmes de construction citoyenne.
                    </li>

                </ul>

            </div>


            <div class="bg-accent/20 p-10 rounded-[3rem] border border-accent/20 hover:bg-accent/30 transition shadow-2xl">

                <h4 class="text-2xl font-black mb-6 text-accent flex items-center gap-3 italic uppercase tracking-tighter">
                    Au quotidien
                </h4>

                <ul class="space-y-4 text-sm text-white">

                    <li class="flex gap-3">
                        <i class="fas fa-star mt-1"></i>
                        Fournir des prestations de service dans ses domaines de compétence.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-star mt-1"></i>
                        Offrir un cadre d'échanges et des espaces de loisirs sains.
                    </li>

                    <li class="flex gap-3">
                        <i class="fas fa-star mt-1"></i>
                        Appuyer la formation des ressources humaines à l'éducation pionnière.
                    </li>

                </ul>

            </div>

        </div>


        <p class="sm:hidden text-center text-[10px] text-white/30 uppercase tracking-widest font-bold mt-2">
            <i class="fas fa-arrows-alt-h mr-1"></i>
            Glissez pour voir la suite
        </p>

    </div>

</section>


<div class="flex h-1 w-full">
    <div class="flex-1 bg-[#14B53A]"></div>
    <div class="flex-1 bg-[#FCD116]"></div>
    <div class="flex-1 bg-[#CE1126]"></div>
</div>


<?php
$dDg = $direction['dg'] ?? null;
if ($dDg):
?>


<section class="py-10 sm:py-24 bg-slate-50">

    <div class="container mx-auto px-4">

        <div class="max-w-5xl mx-auto flex flex-col md:flex-row-reverse items-center gap-4 sm:gap-16">

            <div class="w-full md:w-2/5 flex justify-center md:block">

                <?php if (!empty($dDg['photo'])): ?>

                    <img
                        src="assets/images/<?= e($dDg['photo']) ?>"
                        class="w-40 h-48 sm:w-full sm:h-auto object-cover rounded-2xl sm:rounded-[4rem] shadow-xl sm:shadow-2xl border-4 sm:border-8 border-white"
                        alt="<?= e($dDg['nom']) ?>"
                    >

                <?php else: ?>

                    <div class="w-40 h-48 sm:w-full sm:h-64 rounded-2xl sm:rounded-[4rem] shadow-xl sm:shadow-2xl border-4 sm:border-8 border-white bg-primary flex items-center justify-center">

                        <span class="text-white text-2xl sm:text-6xl font-black italic">
                            <?= e(initiales($dDg['nom'])) ?>
                        </span>

                    </div>

                <?php endif; ?>

            </div>


            <div class="w-full md:w-3/5 text-center md:text-right">

                <h2 class="text-accent font-black tracking-widest uppercase text-[10px] sm:text-sm mb-2 sm:mb-6">
                    Expertise Institutionnelle
                </h2>

                <p class="text-lg font-black text-primary mb-1">
                    <?= e($dDg['nom']) ?>
                </p>

                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4 sm:hidden">
                    <?= e($dDg['titre']) ?>
                </p>

                <i class="fas fa-quote-right text-3xl sm:text-5xl text-primary/10 mb-2 sm:mb-6 hidden sm:block"></i>

                <?php if ($dDg['citation']): ?>

                    <h3 class="text-lg sm:text-3xl md:text-4xl font-black text-slate-900 mb-3 sm:mb-8 leading-snug sm:leading-tight">
                        Le Palais des Pionniers, un espace pour la jeunesse et ses initiatives.
                    </h3>

                <?php endif; ?>

                <?php if ($dDg['texte']): ?>

                    <p class="text-sm sm:text-xl text-slate-600 leading-relaxed mb-4 sm:mb-8 italic">
                        Le Palais est avant tout le vôtre. Un cadre pour apprendre,
                        développer vos compétences, partager vos idées et concrétiser vos projets.
                        <br><br>
                        Ses espaces de qualité sont également disponibles à la réservation
                        pour vos formations, conférences, réunions et événements.
                    </p>

                <?php endif; ?>

                <div class="hidden sm:flex items-center gap-4 justify-center md:justify-end">

                    <div>

                        <p class="text-2xl font-black text-primary">
                            <?= e($dDg['nom']) ?>
                        </p>

                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                            <?= e($dDg['titre']) ?>
                        </p>

                    </div>

                    <div class="w-12 h-px bg-primary hidden md:block"></div>

                </div>

            </div>

        </div>

    </div>

</section>

<?php endif; ?>


<?php
$dDga = $direction['dga'] ?? null;
?>

<?php if ($dDga): ?>

<section class="py-10 sm:py-24 bg-white">

    <div class="container mx-auto px-4">

        <div class="max-w-5xl mx-auto flex flex-col md:flex-row items-center gap-4 sm:gap-16">

            <div class="w-full md:w-2/5 flex justify-center">

                <?php if (!empty($dDga['photo'])): ?>

                    <img
                        src="assets/images/dga.jpeg"
                        class="w-36 h-44 sm:w-64 sm:h-64 object-cover rounded-2xl sm:rounded-[4rem] shadow-xl sm:shadow-2xl border-4 sm:border-8 border-slate-50"
                        alt="<?= e($dDga['nom']) ?>"
                    >

                <?php else: ?>

                    <div class="w-36 h-44 sm:w-64 sm:h-64 rounded-2xl sm:rounded-[4rem] shadow-xl sm:shadow-2xl border-4 sm:border-8 border-slate-50 bg-primary flex items-center justify-center">

                        <span class="text-white text-2xl sm:text-6xl font-black italic">
                            <?= e(initiales($dDga['nom'])) ?>
                        </span>

                    </div>

                <?php endif; ?>

            </div>


            <div class="w-full md:w-3/5 text-center md:text-left">

                <h2 class="text-accent font-black tracking-widest uppercase text-[10px] sm:text-sm mb-2 sm:mb-6">
                    Relève & Engagement
                </h2>

                <p class="text-lg font-black text-primary mb-1">
                    <?= e($dDga['nom']) ?>
                </p>

                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4 sm:hidden">
                    <?= e($dDga['titre']) ?>
                </p>

                <i class="fas fa-quote-left text-3xl sm:text-5xl text-accent/10 mb-2 sm:mb-6 hidden sm:block"></i>

                <?php if ($dDga['citation']): ?>

                    <h3 class="text-lg sm:text-3xl md:text-4xl font-black text-slate-900 mb-3 sm:mb-8 leading-snug sm:leading-tight">
                        <?= e($dDga['citation']) ?>
                    </h3>

                <?php endif; ?>

                <?php if ($dDga['texte']): ?>

                    <p class="text-sm sm:text-xl text-slate-600 leading-relaxed mb-4 sm:mb-8 italic">
                        <?= nl2br(e($dDga['texte'])) ?>
                    </p>

                <?php endif; ?>

                <div class="hidden sm:flex items-center gap-4 justify-center md:justify-start">

                    <div class="w-12 h-px bg-accent hidden md:block"></div>

                    <div>

                        <p class="text-2xl font-black text-primary">
                            <?= e($dDga['nom']) ?>
                        </p>

                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                            <?= e($dDga['titre']) ?>
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<?php endif; ?>


<?php if ($nbPersonnalites > 0): ?>

    <?php

    $officielsTeaser = [
        'assets/images/officiels/president.jpg',
        'assets/images/officiels/premier-ministre.jpg'
    ];

    $officielsTeaser = array_values(
        array_filter(
            $officielsTeaser,
            fn($f) => file_exists(__DIR__ . '/' . $f)
        )
    );

    $directionTeaser = [];

    foreach ($pdo->query("SELECT photo FROM direction") as $d) {

        if (
            !empty($d['photo']) &&
            file_exists(__DIR__ . '/assets/images/' . $d['photo'])
        ) {
            $directionTeaser[] = 'assets/images/' . $d['photo'];
        }

    }

    $persoTeaser = $pdo->query("
        SELECT photo
        FROM personnalites
        WHERE photo IS NOT NULL
        AND photo != ''
        ORDER BY ordre ASC
        LIMIT 10
    ")->fetchAll();

    $persoTeaser = array_values(
        array_filter(
            array_map(
                fn($p) => 'assets/images/personnalites/' . $p['photo'],
                $persoTeaser
            ),
            fn($f) => file_exists(__DIR__ . '/' . $f)
        )
    );

    $teaserPhotos = array_merge(
        $officielsTeaser,
        $directionTeaser,
        $persoTeaser
    );

    ?>


    <section class="relative py-16 sm:py-20 bg-primary text-white overflow-hidden">

        <?php if ($teaserPhotos): ?>

            <div class="absolute inset-0 z-0">

                <div class="marquee-bg-slow flex gap-3 sm:gap-6 w-max h-full opacity-50">

                    <?php foreach (array_merge($teaserPhotos, $teaserPhotos, $teaserPhotos) as $tp): ?>

                        <div class="relative flex-shrink-0 w-44 sm:w-56 h-full">

                            <img
                                src="<?= e($tp) ?>"
                                loading="lazy"
                                class="w-full h-full object-cover"
                            >

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="absolute inset-0 bg-primary/60 z-10"></div>

        <?php endif; ?>


        <div class="container mx-auto px-4 relative z-20 flex flex-col sm:flex-row items-center justify-between gap-6">

            <div class="text-center sm:text-left">

                <h2 class="text-accent font-black tracking-widest uppercase text-xs mb-2">
                    Mémoire & Héritage
                </h2>

                <h3 class="text-2xl sm:text-3xl font-black uppercase italic tracking-tighter">
                    Les Icônes du Palais
                </h3>

            </div>


            <a
                href="personnalites.php"
                class="flex-shrink-0 bg-white text-primary px-8 py-4 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all"
            >
                Découvrir les icônes
                <i class="fas fa-arrow-right ml-2"></i>
            </a>

        </div>

    </section>

<?php endif; ?>


<section class="py-24 bg-white">

    <div class="container mx-auto px-4">

        <div class="flex justify-between items-end mb-16">

            <div>

                <h2 class="text-sm font-black text-accent uppercase tracking-widest mb-4">
                    Réserver nos services
                </h2>

                <h3 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter">
                    Nos <span class="text-accent">Espaces</span>
                </h3>

            </div>

            <a
                href="espaces.php"
                class="bg-primary text-white px-8 py-4 rounded-xl font-black transition hover:bg-slate-900 uppercase text-xs tracking-widest"
            >
                Catalogue complet
            </a>

        </div>


        <div class="grid grid-cols-2 gap-3 sm:gap-8 lg:grid-cols-3 xl:grid-cols-4">

            <?php foreach ($espaces as $e):

                $urlDetails = "espace.php?slug=" . urlencode($e['slug']);

            ?>

                <a
                    href="<?= $urlDetails ?>"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-2 hover:shadow-2xl relative"
                >

                    <div class="relative aspect-[16/11] overflow-hidden bg-slate-100">

                        <?php if ($e['photo']): ?>

                            <img
                                src="uploads/<?= e($e['photo']) ?>" alt="<?= e($e['nom']) ?>"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                            >

                        <?php else: ?>

                            <div class="absolute inset-0 flex items-center justify-center text-5xl text-slate-400 bg-slate-200 opacity-50">
                                <i class="fas fa-landmark"></i>
                            </div>

                        <?php endif; ?>


                        <span class="absolute right-1.5 sm:right-4 top-1.5 sm:top-4 rounded-full bg-white/95 px-1.5 sm:px-3 py-0.5 sm:py-1.5 text-[7px] sm:text-[10px] font-black uppercase tracking-widest text-primary shadow-sm">
                            <?= e($e['categorie']) ?>
                        </span>

                    </div>


                    <div class="flex flex-1 flex-col p-3 sm:p-6">

                        <h3 class="text-sm sm:text-xl font-black text-slate-900 uppercase italic tracking-tighter transition-colors group-hover:text-primary">
                            <?= e($e['nom']) ?>
                        </h3>

                        <p class="mt-1 sm:mt-2 text-[9px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest italic">
                            <?= e($e['capacite']) ?>
                        </p>

                        <div class="mt-auto pt-3 sm:pt-6 inline-flex w-full items-center justify-center rounded-lg border border-slate-300 px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.1em] sm:tracking-[0.15em] transition-all group-hover:bg-black group-hover:border-black group-hover:text-white">
                            Voir détails
                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<?php

$viePalaisSlides = [
    'assets/images/vie-palais/palais-1.jpg',
    'assets/images/vie-palais/palais-2.jpg',
    'assets/images/vie-palais/palais-3.jpeg',
    'assets/images/vie-palais/palais-4.jpg',
];

?>

<section
    id="vie-au-palais"
    class="relative overflow-hidden bg-slate-900 h-96"
>

    <div
        id="viePalaisCarousel"
        class="absolute inset-0 w-full h-full"
    >

        <?php foreach ($viePalaisSlides as $index => $src): ?>

            <div
                class="vie-palais-slide absolute inset-0 w-full h-full transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
            >

                <div
                    class="w-full h-full kenburns"
                    style="background: url('<?= $src ?>') center/cover no-repeat;"
                ></div>

            </div>

        <?php endforeach; ?>

    </div>


    <div class="absolute inset-0 bg-slate-900/60 z-10"></div>


    <div class="absolute inset-0 z-20 flex items-center justify-center px-4 text-center">

        <div>

            <p
                class="text-accent font-black tracking-[0.25em] uppercase text-[10px] sm:text-xs mb-3"
                style="text-shadow: 0 2px 6px rgba(0,0,0,1);"
            >
                Au cœur du Palais
            </p>

            <h2
                class="text-white text-4xl sm:text-5xl md:text-6xl font-black uppercase tracking-tight leading-none"
                style="text-shadow: 0 3px 5px rgba(0,0,0,1), 0 0 15px rgba(0,0,0,0.95);"
            >
                La Vie au Palais
            </h2>

            <p
                class="mt-4 text-white text-sm sm:text-base md:text-lg font-semibold leading-relaxed"
                style="text-shadow: 0 2px 4px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.95);"
            >
                Des activités, des rencontres et des moments qui font vivre le Palais des Pionniers.
            </p>

        </div>

    </div>


    <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-30 flex gap-2">

        <?php foreach ($viePalaisSlides as $index => $src): ?>

            <button
                type="button"
                onclick="goToViePalaisSlide(<?= $index ?>)"
                data-vie-palais-dot="<?= $index ?>"
                aria-label="Afficher l'image <?= $index + 1 ?>"
                class="vie-palais-dot h-1.5 rounded-full transition-all <?= $index === 0 ? 'w-8 bg-accent' : 'w-1.5 bg-white/50 hover:bg-white/80' ?>"
            ></button>

        <?php endforeach; ?>

    </div>

</section>

<section class="py-10 sm:py-20">

    <div class="container mx-auto px-4">

        <div class="bg-primary rounded-2xl sm:rounded-[4rem] p-6 sm:p-12 md:p-24 text-center relative overflow-hidden shadow-2xl">

            <div class="absolute inset-0 bg-slate-900/10"></div>

            <div class="relative z-10 max-w-3xl mx-auto">

                <h3 class="text-2xl sm:text-4xl md:text-6xl font-black text-white mb-3 sm:mb-8 uppercase italic tracking-tighter leading-tight sm:leading-none">
                    Participez à la Renaissance Civique.
                </h3>

                <p class="text-white/80 text-sm sm:text-xl mb-5 sm:mb-12">
                    Le Palais met son expertise à votre disposition pour vos formations, événements et initiatives citoyennes.
                </p>

                <div class="flex flex-wrap justify-center gap-6">

                    <a
                        href="contact.php"
                        class="bg-accent text-white px-8 sm:px-12 py-3 sm:py-5 rounded-xl sm:rounded-2xl font-black text-sm sm:text-lg hover:shadow-2xl transition uppercase tracking-widest"
                    >
                        Contactez-nous
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>


<section class="py-14 sm:py-24 relative overflow-hidden">

    <img
        src="assets/images/jeunes.jpeg"
        class="absolute inset-0 w-full h-full object-cover scale-110 blur-sm"
        alt=""
    >

    <div class="absolute inset-0 bg-slate-900/60"></div>

    <div class="container mx-auto px-4 relative z-10 text-center">

        <div class="inline-block max-w-2xl bg-slate-900/75 rounded-[2rem] px-6 py-8 sm:px-14 sm:py-12">

            <h2 class="text-accent font-black tracking-[0.2em] sm:tracking-[0.3em] uppercase text-[10px] sm:text-xs mb-3">
                Tu es jeune ?
            </h2>

            <h3 class="text-2xl sm:text-5xl font-black italic uppercase tracking-tighter text-white mb-5 sm:mb-8">
                Engage-toi dès aujourd'hui
            </h3>

            <a
                href="sengager.php"
                class="inline-flex items-center gap-2 bg-white text-accent px-8 sm:px-10 py-4 sm:py-5 rounded-2xl font-black uppercase tracking-widest text-xs sm:text-sm hover:bg-accent hover:text-white transition-all shadow-2xl"
            >
                <i class="fas fa-hand-fist"></i>
                Je m'engage
            </a>

        </div>

    </div>

</section>


<script>

document.addEventListener("DOMContentLoaded", function() {


    const presSlides = document.querySelectorAll('.carousel-img-presentation');

    if (presSlides.length > 1) {

        let presCurrent = 0;

        setInterval(() => {

            presSlides[presCurrent].classList.replace('opacity-100', 'opacity-0');

            presCurrent = (presCurrent + 1) % presSlides.length;

            presSlides[presCurrent].classList.replace('opacity-0', 'opacity-100');

        }, 4000);

    }


    const slides = document.querySelectorAll('.carousel-img');
    const dots = document.querySelectorAll('.carousel-dot');

    let current = 0;
    let carouselTimer = null;

    const heroTextes = <?= json_encode($heroSlidesTextes) ?>;


    function showSlide(index) {

        if (!slides.length) return;

        slides[current].classList.replace('opacity-100', 'opacity-0');

        dots[current]?.classList.replace('w-8', 'w-1.5');
        dots[current]?.classList.replace('bg-accent', 'bg-white/40');

        const bloc = document.getElementById('heroTexteBloc');

        if (bloc) {

            bloc.classList.remove('opacity-100');
            bloc.classList.add('opacity-0');

            setTimeout(() => {

                const titre = document.getElementById('heroTitre');
                const sujet = document.getElementById('heroSujet');

                if (titre) {
                    titre.innerHTML = heroTextes[index].titre;
                }

                if (sujet) {
                    sujet.textContent = heroTextes[index].sujet;
                }

                bloc.classList.remove('opacity-0');
                bloc.classList.add('opacity-100');

            }, 400);

        }

        current = index;

        slides[current].classList.replace('opacity-0', 'opacity-100');

        dots[current]?.classList.replace('w-1.5', 'w-8');
        dots[current]?.classList.replace('bg-white/40', 'bg-accent');

    }


    function startCarousel() {

        if (carouselTimer) {
            clearInterval(carouselTimer);
        }

        carouselTimer = setInterval(() => {

            showSlide(
                (current + 1) % slides.length
            );

        }, 4000);

    }


    window.goToSlide = function(index) {

        if (index === current) return;

        showSlide(index);
        startCarousel();

    };


    if (slides.length > 0) {
        startCarousel();
    }


    const viePalaisSlides = document.querySelectorAll('.vie-palais-slide');
    const viePalaisDots = document.querySelectorAll('.vie-palais-dot');

    let viePalaisCurrent = 0;
    let viePalaisInterval = null;


    window.goToViePalaisSlide = function(index) {

        if (!viePalaisSlides.length) return;

        viePalaisSlides[viePalaisCurrent].classList.remove('opacity-100');
        viePalaisSlides[viePalaisCurrent].classList.add('opacity-0');

        if (viePalaisDots[viePalaisCurrent]) {

            viePalaisDots[viePalaisCurrent].classList.remove(
                'w-8',
                'bg-accent'
            );

            viePalaisDots[viePalaisCurrent].classList.add(
                'w-1.5',
                'bg-white/50'
            );

        }


        viePalaisCurrent = index;


        viePalaisSlides[viePalaisCurrent].classList.remove('opacity-0');
        viePalaisSlides[viePalaisCurrent].classList.add('opacity-100');


        if (viePalaisDots[viePalaisCurrent]) {

            viePalaisDots[viePalaisCurrent].classList.remove(
                'w-1.5',
                'bg-white/50'
            );

            viePalaisDots[viePalaisCurrent].classList.add(
                'w-8',
                'bg-accent'
            );

        }

    };


    if (viePalaisSlides.length > 1) {

        viePalaisInterval = setInterval(() => {

            const nextSlide =
                (viePalaisCurrent + 1) % viePalaisSlides.length;

            window.goToViePalaisSlide(nextSlide);

        }, 4000);

    }


    const observer = new IntersectionObserver((entries) => {

        entries.forEach(entry => {

            if (entry.isIntersecting) {

                entry.target.classList.add(
                    'opacity-100',
                    'translate-y-0'
                );

                entry.target.classList.remove(
                    'opacity-0',
                    'translate-y-12'
                );

                observer.unobserve(entry.target);

            }

        });

    }, {
        threshold: 0.1
    });


    document.querySelectorAll('section').forEach(section => {

        section.classList.add(
            'transition-all',
            'duration-1000',
            'opacity-0',
            'translate-y-12'
        );

        observer.observe(section);

    });


    const counters = document.querySelectorAll('.counter');

    const counterObserver = new IntersectionObserver((entries) => {

        entries.forEach(entry => {

            if (entry.isIntersecting) {

                const el = entry.target;

                const target =
                    parseInt(el.dataset.target, 10) || 0;

                const duration = 1200;
                const start = performance.now();


                function tick(now) {

                    const progress =
                        Math.min(
                            (now - start) / duration,
                            1
                        );

                    const eased =
                        1 - Math.pow(
                            1 - progress,
                            3
                        );

                    el.textContent =
                        Math.round(eased * target);


                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    }

                }


                requestAnimationFrame(tick);

                counterObserver.unobserve(el);

            }

        });

    }, {
        threshold: 0.5
    });


    counters.forEach(el => {
        counterObserver.observe(el);
    });

});

</script>


<?php
require __DIR__ . '/includes/suggestion-bulle.php';
?>

<?php
require __DIR__ . '/includes/footer.php';
?>