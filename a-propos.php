<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = "À propos — Palais des Pionniers";
$page = 'a-propos.php';
require __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     1. HEADER DE PAGE (STYLE INSTITUTIONNEL)
     ============================================ -->
<section class="relative py-24 bg-slate-900 text-white overflow-hidden">
    <div class="absolute inset-0 opacity-20 bg-[url('assets/images/pattern.png')]"></div>
    <div class="container mx-auto px-4 relative z-10 text-center">
        <h2 class="text-accent font-black tracking-[0.3em] uppercase text-xs mb-4">Notre Institution</h2>
        <h1 class="text-5xl md:text-7xl font-black italic tracking-tighter uppercase mb-6">
            L'excellence au service de la <span class="text-accent">Patrie</span>
        </h1>
        <p class="max-w-2xl mx-auto text-xl text-slate-300 font-medium leading-relaxed">
            Découvrez l'histoire, les missions et l'engagement du Palais des Pionniers, pilier de la construction citoyenne au Mali.
        </p>
    </div>
</section>

<!-- ============================================
     2. HISTOIRE & IDENTITÉ (AVEC IMAGE)
     ============================================ -->
<section class="py-24 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div class="space-y-8">
                <div>
                    <h2 class="text-primary font-black text-4xl uppercase italic tracking-tighter mb-6">Un héritage tourné vers l'avenir</h2>
                    <div class="h-1.5 w-24 bg-accent"></div>
                </div>
                <div class="text-lg text-slate-600 leading-relaxed space-y-6 text-justify">
                    <p>
                        Situé au cœur de Magnambougou, le <strong>Palais des Pionniers</strong> est bien plus qu'une infrastructure : c'est un Établissement Public à Caractère Scientifique et Technologique (EPST). Sous la tutelle du Ministère de la Jeunesse et des Sports, nous œuvrons pour l'épanouissement intégral de la jeunesse malienne.
                    </p>
                    <p>
                        Notre institution incarne la volonté de l'État de forger des citoyens modèles, imprégnés des valeurs de patriotisme et d'intégrité, essentiels à l'avènement du <strong>Mali Kura</strong>.
                    </p>
                </div>
            </div>
            <div class="relative">
                <img src="assets/images/exterieur.jpg" class="rounded-[3rem] shadow-2xl border-8 border-slate-50" alt="Façade Palais">
                <div class="absolute -bottom-6 -left-6 bg-accent text-white p-8 rounded-2xl shadow-xl">
                    <span class="text-4xl font-black block">EPST</span>
                    <span class="text-xs font-bold uppercase tracking-widest">Statut Institutionnel</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     3. NOS PILIERS (MISSIONS / VALEURS / VISION)
     ============================================ -->
<section class="py-24 bg-slate-50">
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
<section class="py-20 bg-primary text-white">
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
<section class="py-24 bg-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-sm font-black text-accent uppercase tracking-widest mb-4">Gouvernance</h2>
        <h3 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter mb-16">Une direction engagée</h3>
        
        <div class="max-w-md mx-auto">
            <div class="relative group">
                <div class="aspect-square rounded-[3rem] overflow-hidden mb-6">
                    <img src="assets/images/dg.jpeg" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition duration-500" alt="Directeur Général">
                </div>
                <h4 class="text-2xl font-black text-slate-900 uppercase italic">Le Directeur Général</h4>
                <p class="text-accent font-bold text-sm tracking-widest uppercase mt-2">Palais des Pionniers</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     6. CTA REJOINDRE
     ============================================ -->
<section class="py-24">
    <div class="container mx-auto px-4">
        <div class="bg-slate-900 rounded-[3rem] p-12 md:p-20 relative overflow-hidden">
            <div class="absolute right-0 top-0 w-1/3 h-full bg-accent skew-x-12 translate-x-20 opacity-10"></div>
            <div class="relative z-10 grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-3xl md:text-5xl font-black text-white uppercase italic tracking-tighter leading-none mb-6">
                        Prêt à découvrir nos infrastructures ?
                    </h3>
                    <p class="text-slate-400 text-lg">Consultez nos espaces disponibles et réservez pour vos événements institutionnels ou privés.</p>
                </div>
                <div class="flex md:justify-end">
                    <a href="espaces.php" class="bg-white text-primary px-12 py-5 rounded-2xl font-black uppercase tracking-widest hover:bg-accent hover:text-white transition shadow-2xl">
                        Voir les espaces
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>