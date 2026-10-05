<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = db();
$pageTitle = "À propos — Palais des Pionniers";
$page = 'a-propos.php';

// Images institutionnelles pour le carrousel du hero
$heroPropos = array_values(array_filter(
    ['assets/images/porte.jpeg', 'assets/images/adminis.jpeg', 'assets/images/groupewague.jpg'],
    fn($p) => file_exists(__DIR__ . '/' . $p)
));

require __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     1. HEADER DE PAGE (STYLE INSTITUTIONNEL)
     ============================================ -->
<section class="relative flex items-center min-h-[42vh] sm:min-h-[52vh] py-8 sm:py-14 bg-slate-900 text-white overflow-hidden">

    <div id="proposCarousel" class="absolute inset-0">
        <?php foreach ($heroPropos as $i => $img): ?>
        <img src="<?= e($img) ?>" class="propos-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 ease-in-out <?= $i === 0 ? 'opacity-100' : 'opacity-0' ?>" alt="">
        <?php endforeach; ?>
    </div>
    <div class="absolute inset-0 bg-primary/60"></div>

    <div class="container mx-auto px-4 text-center relative z-20">
        <div class="inline-block max-w-3xl bg-slate-900/75 rounded-2xl sm:rounded-[3rem] px-5 py-6 sm:px-14 sm:py-12">
        <h2 class="text-accent font-black tracking-[0.2em] sm:tracking-[0.3em] uppercase text-[9px] sm:text-xs mb-2 sm:mb-4">Notre Institution</h2>
        <h1 class="text-2xl sm:text-5xl md:text-7xl font-black italic tracking-tighter uppercase mb-2 sm:mb-6">
            L'excellence au service de la <span class="text-accent">Patrie</span>
        </h1>
        <p class="max-w-2xl mx-auto text-xs sm:text-xl text-slate-200 font-medium leading-snug sm:leading-relaxed">
            Découvrez l'histoire, les missions et l'engagement du Palais des Pionniers, pilier de la construction citoyenne au Mali.
        </p>
        </div>
    </div>
</section>

<!-- ============================================
     2. HISTOIRE & IDENTITÉ (AVEC IMAGE)
     ============================================ -->
<section class="py-10 sm:py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-6 sm:gap-16 items-center">
            <div class="space-y-4 sm:space-y-8">
                <div>
                    <h2 class="text-primary font-black text-xl sm:text-4xl uppercase italic tracking-tighter mb-2 sm:mb-6">Un héritage tourné vers l'avenir</h2>
                    <div class="h-1.5 w-24 bg-accent"></div>
                </div>
                <div class="text-sm sm:text-lg text-slate-600 leading-snug sm:leading-relaxed space-y-3 sm:space-y-6 text-justify">
                    <p>
                        Situé au cœur de Magnambougou / Dianéguéla, le <strong>Palais des Pionniers</strong> est bien plus qu'une infrastructure : c'est un Établissement Public à Caractère Scientifique et Technologique (EPST). Sous la tutelle du Ministère de la Jeunesse et des Sports, nous œuvrons pour l'épanouissement intégral de la jeunesse malienne.
                    </p>
                    <p>
                        Notre institution incarne la volonté de l'État de forger des citoyens modèles, imprégnés des valeurs de patriotisme et d'intégrité, essentiels à l'avènement du <strong>Mali Kura</strong>.
                    </p>
                </div>
            </div>
            <div class="relative">
                <img src="assets/images/exterieur.jpg" class="rounded-[3rem] shadow-2xl border-8 border-slate-50" alt="Façade Palais">
                <div class="absolute -bottom-3 -left-3 sm:-bottom-6 sm:-left-6 bg-accent text-white p-3 sm:p-8 rounded-xl sm:rounded-2xl shadow-xl">
                    <span class="text-lg sm:text-4xl font-black block">EPST</span>
                    <span class="text-xs font-bold uppercase tracking-widest">Statut Institutionnel</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     3. NOS PILIERS (MISSIONS / VALEURS / VISION)
     ============================================ -->
<section class="py-10 sm:py-24 bg-slate-50">
    <div class="container mx-auto px-4">
        <div class="grid md:grid-cols-3 gap-12">
            <!-- Mission -->
            <div class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-xl transition group">
                <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center text-primary text-2xl mb-8 group-hover:bg-primary group-hover:text-white transition">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-900 uppercase italic tracking-tighter mb-4">Notre Mission</h3>
                <p class="text-slate-500 leading-relaxed">
                    Assurer la formation civique, le brassage culturel et la recherche scientifique sur les dynamiques sociales de la jeunesse.
                </p>
            </div>

            <!-- Valeurs -->
            <div class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-xl transition group">
                <div class="w-16 h-16 bg-accent/10 rounded-2xl flex items-center justify-center text-accent text-2xl mb-8 group-hover:bg-accent group-hover:text-white transition">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-900 uppercase italic tracking-tighter mb-4">Nos Valeurs</h3>
                <p class="text-slate-500 leading-relaxed">
                    Excellence, Citoyenneté et Solidarité. Ces piliers guident chacune de nos actions en faveur de la nation.
                </p>
            </div>

            <!-- Vision -->
            <div class="bg-white p-10 rounded-[2.5rem] shadow-sm border border-slate-100 hover:shadow-xl transition group">
                <div class="w-16 h-16 bg-slate-900/10 rounded-2xl flex items-center justify-center text-slate-900 text-2xl mb-8 group-hover:bg-slate-900 group-hover:text-white transition">
                    <i class="fas fa-eye"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-900 uppercase italic tracking-tighter mb-4">Notre Vision</h3>
                <p class="text-slate-500 leading-relaxed">
                    Devenir le pôle d'excellence de référence au Mali et dans la sous-région pour l'ingénierie sociale et citoyenne d'ici 2028.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     4. SECTION CHIFFRES (IMPACT)
     ============================================ -->
<section class="py-10 sm:py-20 bg-primary text-white">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div>
                <span class="text-5xl font-black text-accent block mb-2">10+</span>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Espaces Modernes</span>
            </div>
            <div>
                <span class="text-5xl font-black text-accent block mb-2">50k</span>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Jeunes Impactés</span>
            </div>
            <div>
                <span class="text-5xl font-black text-accent block mb-2">100%</span>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Engagement Civique</span>
            </div>
            <div>
                <span class="text-5xl font-black text-accent block mb-2">EPST</span>
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-white/60">Statut de Recherche</span>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     5. DIRECTION ET ÉQUIPE (OPTITIONNEL)
     ============================================ -->
<section class="py-10 sm:py-24 bg-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-sm font-black text-accent uppercase tracking-widest mb-3 sm:mb-4">Gouvernance</h2>
        <h3 class="text-2xl sm:text-4xl font-black text-slate-900 uppercase italic tracking-tighter mb-3 sm:mb-6">Une organisation encadrée par décret</h3>
        <p class="max-w-2xl mx-auto text-sm sm:text-lg text-slate-500 mb-8 sm:mb-16">
            Conformément au Décret n°2022-0401/PT-RM du 11 juillet 2022, le Palais des Pionniers est administré par trois organes.
        </p>

        <div class="max-w-5xl mx-auto grid sm:grid-cols-3 gap-3 sm:gap-8 text-left">
            <div class="group relative bg-slate-50 rounded-xl sm:rounded-[2rem] p-4 sm:p-8 border border-transparent hover:border-primary/20 hover:bg-white hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <span class="absolute top-3 right-4 sm:top-6 sm:right-8 text-3xl sm:text-5xl font-black text-slate-100 group-hover:text-primary/10 transition-colors italic">01</span>
                <div class="relative w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-primary/10 flex items-center justify-center mb-2 sm:mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                    <i class="fas fa-users text-primary group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="relative font-black text-primary uppercase italic text-sm sm:text-lg mb-1 sm:mb-2">Le Conseil d'Administration</h4>
                <p class="relative text-xs sm:text-sm text-slate-600 leading-snug sm:leading-relaxed">Organe délibérant, présidé par le Ministre chargé de la Jeunesse. Il adopte le programme annuel d'activités, le budget prévisionnel et arrête les comptes financiers.</p>
            </div>
            <div class="group relative bg-slate-50 rounded-xl sm:rounded-[2rem] p-4 sm:p-8 border border-transparent hover:border-accent/20 hover:bg-white hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <span class="absolute top-3 right-4 sm:top-6 sm:right-8 text-3xl sm:text-5xl font-black text-slate-100 group-hover:text-accent/10 transition-colors italic">02</span>
                <div class="relative w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-accent/10 flex items-center justify-center mb-2 sm:mb-4 group-hover:bg-accent group-hover:scale-110 transition-all duration-300">
                    <i class="fas fa-user-tie text-accent group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="relative font-black text-primary uppercase italic text-sm sm:text-lg mb-1 sm:mb-2">La Direction Générale</h4>
                <p class="relative text-xs sm:text-sm text-slate-600 leading-snug sm:leading-relaxed">Dirigée par un Directeur Général nommé par décret, assisté d'un Directeur Général Adjoint. Elle coordonne, anime et contrôle l'ensemble des activités du Palais.</p>
            </div>
            <div class="group relative bg-slate-50 rounded-xl sm:rounded-[2rem] p-4 sm:p-8 border border-transparent hover:border-primary/20 hover:bg-white hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
                <span class="absolute top-3 right-4 sm:top-6 sm:right-8 text-3xl sm:text-5xl font-black text-slate-100 group-hover:text-primary/10 transition-colors italic">03</span>
                <div class="relative w-9 h-9 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-primary/10 flex items-center justify-center mb-2 sm:mb-4 group-hover:bg-primary group-hover:scale-110 transition-all duration-300">
                    <i class="fas fa-flask text-primary group-hover:text-white transition-colors"></i>
                </div>
                <h4 class="relative font-black text-primary uppercase italic text-sm sm:text-lg mb-1 sm:mb-2">Le Comité Scientifique et Technique</h4>
                <p class="relative text-xs sm:text-sm text-slate-600 leading-snug sm:leading-relaxed">Organe consultatif chargé de donner son avis sur les orientations en matière d'études et de recherches, et de valider les productions scientifiques et techniques.</p>
            </div>
        </div>
    </div>
</section>

<?php
$personnelActopos = $pdo->query("SELECT * FROM personnel WHERE actif = 1 ORDER BY ordre ASC, nom ASC")->fetchAll();
$personnelAvecPhoto = array_values(array_filter($personnelActopos, fn($p) => !empty($p['photo']) && file_exists(__DIR__ . '/assets/images/personnel/' . $p['photo'])));
?>
<?php if ($personnelActopos): ?>
<!-- ============================================
     5bis. NOTRE ÉQUIPE (carrousel personnel)
     ============================================ 
     <section class="py-10 sm:py-20 bg-slate-900 overflow-hidden">
    <div class="container mx-auto px-4 text-center mb-6 sm:mb-14">
        <h2 class="text-accent font-black tracking-widest uppercase text-[10px] sm:text-sm mb-2 sm:mb-4">L'équipe au quotidien</h2>
        <h3 class="text-xl sm:text-4xl font-black text-white uppercase italic tracking-tighter">Notre Personnel</h3>
    </div>

    <?php if ($personnelAvecPhoto): ?>
    <div class="relative">
        <div class="absolute inset-y-0 left-0 w-12 sm:w-32 bg-gradient-to-r from-slate-900 to-transparent z-10"></div>
        <div class="absolute inset-y-0 right-0 w-12 sm:w-32 bg-gradient-to-l from-slate-900 to-transparent z-10"></div>
        <div class="marquee flex gap-6 sm:gap-10 w-max">
            <?php foreach (array_merge($personnelAvecPhoto, $personnelAvecPhoto) as $p): ?>
            <div class="flex-shrink-0 flex flex-col items-center text-center w-24 sm:w-36">
                <div class="w-20 h-20 sm:w-32 sm:h-32 rounded-full overflow-hidden border-2 sm:border-4 border-white/10">
                    <img src="assets/images/personnel/<?= e($p['photo']) ?>" class="w-full h-full object-cover">
                </div>
                <p class="mt-2 sm:mt-4 text-white font-black text-[11px] sm:text-sm uppercase italic tracking-tight"><?= e($p['prenom'] ? $p['prenom'].' '.$p['nom'] : $p['nom']) ?></p>
                <?php if ($p['poste']): ?><p class="text-accent text-[9px] sm:text-xs font-bold uppercase tracking-widest mt-0.5"><?= e($p['poste']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <p class="text-center text-[11px] text-white/40 italic px-4">Les photos de l'équipe seront affichées ici dès qu'elles seront ajoutées via l'administration.</p>
    <?php endif; ?>
</section> -->
<?php endif; ?>

<!-- ============================================
     6. CTA REJOINDRE
     ============================================ -->
<section class="py-10 sm:py-24">
    <div class="container mx-auto px-4">
        <div class="bg-slate-900 rounded-2xl sm:rounded-[3rem] p-6 sm:p-12 md:p-20 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-1/3 h-full bg-accent skew-x-12 translate-x-20 opacity-10"></div>
            <div class="relative z-10 grid md:grid-cols-2 gap-5 sm:gap-12 items-center">
                <div>
                    <h3 class="text-lg sm:text-3xl md:text-5xl font-black text-white uppercase italic tracking-tighter leading-tight sm:leading-none mb-2 sm:mb-6">
                        Prêt à découvrir nos infrastructures ?
                    </h3>
                    <p class="text-slate-400 text-xs sm:text-lg">Consultez nos espaces disponibles et réservez pour vos événements institutionnels ou privés.</p>
                </div>
                <div class="flex md:justify-end">
                    <a href="espaces.php" class="bg-white text-primary px-6 sm:px-12 py-3 sm:py-5 rounded-xl sm:rounded-2xl font-black text-xs sm:text-base uppercase tracking-widest hover:bg-accent hover:text-white transition shadow-2xl">
                        Voir les espaces
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('opacity-100', 'translate-y-0');
                entry.target.classList.remove('opacity-0', 'translate-y-8');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('section').forEach(section => {
        section.classList.add('transition-all', 'duration-700', 'opacity-0', 'translate-y-8');
        observer.observe(section);
    });

    const proposSlides = document.querySelectorAll('.propos-slide');
    if (proposSlides.length > 1) {
        let proposIdx = 0;
        setInterval(() => {
            proposSlides[proposIdx].classList.replace('opacity-100', 'opacity-0');
            proposIdx = (proposIdx + 1) % proposSlides.length;
            proposSlides[proposIdx].classList.replace('opacity-0', 'opacity-100');
        }, 4000);
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>