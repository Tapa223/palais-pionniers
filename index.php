<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

/**
 * RÉCUPÉRATION DES DONNÉES
 */
$espaces = $pdo->query("
    SELECT e.id, e.slug, e.nom, e.capacite, c.nom AS categorie,
           (SELECT chemin FROM espace_images WHERE espace_id = e.id ORDER BY id ASC LIMIT 1) as photo
    FROM espaces e
    JOIN categories c ON c.id = e.categorie_id
    ORDER BY e.id ASC
    LIMIT 4
")->fetchAll();

$pageTitle = "Accueil — Palais des Pionniers du Mali";
$page = 'index.php';
require __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     1. HERO SECTION
     ============================================ -->
<section class="relative overflow-hidden bg-slate-900 text-white">
    <div class="absolute inset-0 bg-gradient-to-r from-primary/90 via-primary/30 to-transparent z-10"></div>
    <div id="heroCarousel" class="absolute inset-0 z-0">
        <?php 
        $slides = ['assets/images/porte.jpeg', 'assets/images/adminis.jpeg', 'assets/images/groupewague.jpeg'];
        foreach ($slides as $index => $src): 
        ?>
        <div class="carousel-img absolute inset-0 w-full h-full transition-opacity duration-1000 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
             style="background: url('<?= $src ?>') center/cover no-repeat;"></div>
        <?php endforeach; ?>
    </div>

    <div class="container relative z-20 mx-auto grid min-h-[85vh] items-center gap-12 px-4 py-24 md:grid-cols-12">
        <div class="md:col-span-8 lg:col-span-7">
            <div class="inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/10 px-5 py-2 text-xs font-bold tracking-[0.2em] text-accent backdrop-blur-xl uppercase mb-8">
                <i class="fas fa-landmark animate-bounce"></i>
                Établissement Public à Caractère Scientifique et Technologique • Vision Mali Kura
            </div>
            <h1 class="text-6xl font-black leading-tight sm:text-7xl md:text-8xl drop-shadow-2xl mb-8">
                Le Temple du <br><span class="text-accent italic">Citoyen</span>
            </h1>
            <p class="mt-6 max-w-2xl text-xl text-slate-200 font-medium leading-relaxed drop-shadow-md">
                Le Palais des Pionniers s'affirme comme le socle de l'État pour la formation citoyenne, la recherche sociale et l'épanouissement de la jeunesse malienne.
            </p>
            <div class="mt-12 flex flex-wrap gap-6">
                <a href="#description" class="bg-accent text-white px-10 py-5 rounded-2xl font-black shadow-2xl transition-all hover:scale-105 hover:bg-accent-dark">
                    Découvrir l'Institution <i class="fas fa-chevron-down ml-2"></i>
                </a>
                <a href="espaces.php" class="bg-white/10 backdrop-blur-md text-white border-2 border-white/20 px-10 py-5 rounded-2xl font-black transition-all hover:bg-white/20">
                    Nos Infrastructures
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     2. DESCRIPTION GÉNÉRALE
     ============================================ -->
<section id="description" class="py-24 bg-white relative overflow-hidden">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="relative">
                <div class="aspect-square rounded-[4rem] overflow-hidden shadow-2xl border-8 border-slate-50">
                    <img src="assets/images/presentation.jpg" class="w-full h-full object-cover" alt="Présentation Palais">
                </div>
                <div class="absolute -bottom-8 -right-8 bg-primary text-white p-10 rounded-[3rem] shadow-3xl max-w-xs">
                    <p class="text-2xl font-black mb-2 italic">Mali Kura</p>
                    <p class="text-sm font-bold uppercase tracking-widest text-white/80">L'intégrité comme fondement</p>
                </div>
            </div>
            <div>
                <h2 class="text-sm font-black text-accent uppercase tracking-[0.3em] mb-6">Présentation du Palais</h2>
                <h3 class="text-4xl font-black text-slate-900 mb-8 leading-tight uppercase italic tracking-tighter">Un Pilier de la <span class="text-primary">Souveraineté Nationale</span></h3>
                <div class="space-y-6 text-lg text-slate-600 leading-relaxed text-justify">
                    <p>
                        Érigé par la volonté des plus hautes autorités de la Transition, le Palais des Pionniers est un EPST placé sous la tutelle du Ministère de la Jeunesse et des Sports.
                    </p>
                    <p>
                        Véritable carrefour du Mali Kura, il offre un cadre unique de brassage, d'apprentissage et d'excellence. Notre ambition est de forger un citoyen nouveau, patriote et engagé, capable de porter fièrement les défis de la souveraineté nationale.
                    </p>
                    <div class="p-6 bg-slate-50 rounded-2xl border-l-4 border-accent">
                        <p class="text-slate-800 font-bold italic">La construction nationale exige une jeunesse éduquée, disciplinée et dévouée au service de la patrie.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     3. MESSAGE DU MINISTRE
     ============================================ -->
<section class="py-24 bg-primary relative overflow-hidden text-white">
    <div class="container mx-auto px-4">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row items-center gap-16">
            <div class="w-full md:w-2/5">
                <img src="assets/images/minis.jpeg" class="rounded-[4rem] shadow-2xl border-8 border-white/10" alt="Le Ministre">
            </div>
            <div class="w-full md:w-3/5">
                <h2 class="text-accent font-black tracking-widest uppercase text-sm mb-6 italic">La Vision Ministérielle</h2>
                <i class="fas fa-quote-left text-5xl text-accent/30 mb-6"></i>
                <h3 class="text-3xl md:text-4xl font-black mb-8 leading-tight">
                    Le Palais est le levier stratégique de notre politique de construction citoyenne.
                </h3>
                <p class="text-xl text-white/80 leading-relaxed mb-8 italic">
                    Sous l'impulsion du Gouvernement, nous avons confié au Palais des Pionniers la mission noble de transformer notre jeunesse en un rempart inébranlable contre l'incivisme. C'est ici que s'enseigne l'amour sacré de la patrie.
                </p>
                <div class="flex items-center gap-4">
                    <div class="w-12 h-px bg-accent"></div>
                    <div>
                        <p class="text-2xl font-black">M. Abdoul Kassim Ibrahim Fomba</p>
                        <p class="text-xs font-bold text-accent uppercase tracking-widest">Ministre de la jeunesse et des sports, chargé de l'Instruction Civique et de la Construction Citoyenne</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     4. MISSIONS & HORIZON 2028
     ============================================ -->
<section class="py-24 bg-slate-900 text-white">
    <div class="container mx-auto px-4">
        <div class="text-center max-w-3xl mx-auto mb-20">
            <h2 class="text-accent font-black tracking-widest uppercase text-sm mb-4">Missions & Objectifs</h2>
            <h3 class="text-4xl md:text-5xl font-black uppercase tracking-tighter italic">Cap sur l'Horizon 2028</h3>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-white/5 p-10 rounded-[3rem] border border-white/10 hover:bg-white/10 transition">
                <h4 class="text-2xl font-black mb-6 text-accent flex items-center gap-3 italic uppercase tracking-tighter">
                    Missions
                </h4>
                <ul class="space-y-4 text-sm text-slate-300">
                    <li class="flex gap-3"><i class="fas fa-check text-accent mt-1"></i> Formation d'élite des cadres pionniers.</li>
                    <li class="flex gap-3"><i class="fas fa-check text-accent mt-1"></i> Recherche scientifique sur le civisme.</li>
                    <li class="flex gap-3"><i class="fas fa-check text-accent mt-1"></i> Brassage socioculturel de la jeunesse.</li>
                </ul>
            </div>
            
            <div class="bg-white/5 p-10 rounded-[3rem] border border-white/10 hover:bg-white/10 transition">
                <h4 class="text-2xl font-black mb-6 text-white flex items-center gap-3 italic uppercase tracking-tighter">
                    Objectifs
                </h4>
                <ul class="space-y-4 text-sm text-slate-300">
                    <li class="flex gap-3"><i class="fas fa-check text-accent mt-1"></i> Renforcement de l'unité nationale.</li>
                    <li class="flex gap-3"><i class="fas fa-check text-accent mt-1"></i> Promotion des valeurs du Mali Kura.</li>
                    <li class="flex gap-3"><i class="fas fa-check text-accent mt-1"></i> Innovation en ingénierie pédagogique.</li>
                </ul>
            </div>

            <div class="bg-accent/20 p-10 rounded-[3rem] border border-accent/20 hover:bg-accent/30 transition shadow-2xl">
                <h4 class="text-2xl font-black mb-6 text-accent flex items-center gap-3 italic uppercase tracking-tighter">
                    Horizon 2028
                </h4>
                <ul class="space-y-4 text-sm text-white">
                    <li class="flex gap-3"><i class="fas fa-star mt-1"></i> Digitalisation intégrale des cursus.</li>
                    <li class="flex gap-3"><i class="fas fa-star mt-1"></i> Déploiement de pôles régionaux d'excellence.</li>
                    <li class="flex gap-3"><i class="fas fa-star mt-1"></i> Certification de 100 000 jeunes au civisme.</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     5. MESSAGE DU DIRECTEUR GÉNÉRAL
     ============================================ -->
<section class="py-24 bg-slate-50">
    <div class="container mx-auto px-4">
        <div class="max-w-5xl mx-auto flex flex-col md:flex-row-reverse items-center gap-16">
            <div class="w-full md:w-2/5">
                <img src="assets/images/dg.jpeg" class="rounded-[4rem] shadow-2xl border-8 border-white" alt="Directeur Général">
            </div>
            <div class="w-full md:w-3/5 text-right">
                <h2 class="text-primary font-black tracking-widest uppercase text-sm mb-6">Expertise Institutionnelle</h2>
                <i class="fas fa-quote-right text-5xl text-primary/10 mb-6"></i>
                <h3 class="text-3xl md:text-4xl font-black text-slate-900 mb-8 leading-tight">
                    Nous transformons la vision nationale en actes concrets pour la patrie.
                </h3>
                <p class="text-xl text-slate-600 leading-relaxed mb-8 italic">
                    En notre qualité d'EPST, nous œuvrons quotidiennement à produire de la compétence citoyenne. Le Palais est une véritable usine à bâtisseurs de nation, garantissant que chaque parcours soit une pierre solide à l'édifice du Mali souverain.
                </p>
                <div class="flex items-center gap-4 justify-end">
                    <div>
                        <p class="text-2xl font-black text-primary">Mr Sidi Dicko</p>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Directeur Géneral du palais des pionniers</p>
                    </div>
                    <div class="w-12 h-px bg-primary"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     6. NOS ESPACES (Design basé sur espaces.php)
     ============================================ -->
<section class="py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-end mb-16">
            <div>
                <h2 class="text-sm font-black text-accent uppercase tracking-widest mb-4">Réserver nos services</h2>
                <h3 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter">Nos <span class="text-accent">Espaces</span></h3>
            </div>
           <a href="espaces.php" class="bg-primary text-white px-8 py-4 rounded-xl font-black transition hover:bg-slate-900 uppercase text-xs tracking-widest">Catalogue complet</a>
        </div>

        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php foreach($espaces as $e): 
                $urlDetails = "espace.php?slug=" . urlencode($e['slug']);
            ?>
            <a href="<?= $urlDetails ?>" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-2 hover:shadow-2xl relative">
                
                <div class="relative aspect-[16/11] overflow-hidden bg-slate-100">
                    <?php if ($e['photo']): ?>
                        <img src="uploads/<?= $e['photo'] ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                    <?php else: ?>
                        <div class="absolute inset-0 flex items-center justify-center text-5xl bg-slate-200 opacity-50">🏛️</div>
                    <?php endif; ?>
                    
                    <span class="absolute right-4 top-4 rounded-full bg-white/95 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-primary shadow-sm">
                        <?= $e['categorie'] ?>
                    </span>
                </div>

                <div class="flex flex-1 flex-col p-6">
                    <h3 class="text-xl font-black text-slate-900 uppercase italic tracking-tighter transition-colors group-hover:text-primary">
                        <?= $e['nom'] ?>
                    </h3>
                    <p class="mt-2 text-xs font-bold text-slate-400 uppercase tracking-widest italic">
                        <?= $e['capacite'] ?> places disponibles
                    </p>
                    
                    <div class="mt-6 inline-flex w-full items-center justify-center rounded-xl border-2 border-slate-900 px-4 py-3 text-[10px] font-black uppercase tracking-[0.2em] transition-all group-hover:bg-black group-hover:text-white">
                        Voir détails
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================
     7. CTA FINAL
     ============================================ -->
<section class="py-20">
    <div class="container mx-auto px-4">
        <div class="bg-primary rounded-[4rem] p-12 md:p-24 text-center relative overflow-hidden shadow-2xl">
            <div class="absolute inset-0 bg-slate-900/10"></div>
            <div class="relative z-10 max-w-3xl mx-auto">
                <h3 class="text-4xl md:text-6xl font-black text-white mb-8 uppercase italic tracking-tighter leading-none">Participez à la Renaissance Civique.</h3>
                <p class="text-white/80 text-xl mb-12">Le Palais met son expertise à votre disposition pour vos formations, événements et initiatives citoyennes.</p>
                <div class="flex flex-wrap justify-center gap-6">
                    <a href="contact.php" class="bg-accent text-white px-12 py-5 rounded-2xl font-black text-lg hover:shadow-2xl transition uppercase tracking-widest">Contactez-nous</a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Hero Carousel
    const slides = document.querySelectorAll('.carousel-img');
    let current = 0;
    if(slides.length > 0) {
        setInterval(() => {
            slides[current].classList.replace('opacity-100', 'opacity-0');
            current = (current + 1) % slides.length;
            slides[current].classList.replace('opacity-0', 'opacity-100');
        }, 5000);
    }

    // Scroll Reveal
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('opacity-100', 'translate-y-0');
                entry.target.classList.remove('opacity-0', 'translate-y-12');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('section').forEach(section => {
        section.classList.add('transition-all', 'duration-1000', 'opacity-0', 'translate-y-12');
        observer.observe(section);
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>