<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo  = db();
$slug = $_GET['slug'] ?? '';

// 1. Récupération des informations de l'espace
$stmt = $pdo->prepare("
    SELECT e.*, c.nom AS categorie,
    (SELECT COUNT(*) FROM reservations r 
     WHERE r.espace_id = e.id 
     AND r.statut = 'validee' 
     AND r.date_resa = CURRENT_DATE) as occupation_actuelle
    FROM espaces e
    JOIN categories c ON c.id = e.categorie_id
    WHERE e.slug = :slug
");
$stmt->execute([':slug' => $slug]);
$espace = $stmt->fetch();

if (!$espace) {
    http_response_code(404);
    echo "Espace non trouvé.";
    exit;
}

$isOccupied = ($espace['occupation_actuelle'] > 0);

// 2. Galeries et Tarifs
$imgsStmt = $pdo->prepare("SELECT chemin FROM espace_images WHERE espace_id = :id ORDER BY id ASC");
$imgsStmt->execute([':id' => $espace['id']]);
$galerie = $imgsStmt->fetchAll();

$tarifsStmt = $pdo->prepare("SELECT * FROM tarifs WHERE espace_id = :id ORDER BY id");
$tarifsStmt->execute([':id' => $espace['id']]);
$tarifs = $tarifsStmt->fetchAll();

// 3. Occupation pour le calendrier
$resStmt = $pdo->prepare("
    SELECT date_resa, COUNT(*) as nb 
    FROM reservations 
    WHERE espace_id = :id AND statut = 'validee' 
    AND date_resa >= DATE_FORMAT(NOW() ,'%Y-%m-01')
    GROUP BY date_resa
");
$resStmt->execute([':id' => $espace['id']]);
$occupations = $resStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = e($espace['nom']) . ' — Palais des Pionniers';
require __DIR__ . '/includes/header.php';
?>
<style>html { scroll-behavior: smooth; }</style>

<div class="container mx-auto px-4 py-4 md:py-8">
    <!-- Retour -->
    <a href="espaces.php" class="inline-flex items-center gap-1.5 text-xs font-black text-primary hover:text-accent transition uppercase tracking-widest bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full">
        <i class="fas fa-arrow-left"></i> Retour aux espaces
    </a>

    <!-- Grille Principale -->
    <div class="mt-5 flex flex-col gap-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:items-start">

        <!-- 1. GALERIE (toujours en premier) -->
        <div class="lg:col-start-2 lg:row-start-1 space-y-4">
            <!-- Bouton Réserver visible dès le haut de page (desktop uniquement — sur mobile la carte d'image est déjà en haut) -->
            <a href="#cta-reservation" class="hidden lg:flex items-center justify-center gap-2 w-full bg-primary text-white py-3.5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-slate-800 transition-all shadow-lg">
                <i class="fas fa-calendar-check"></i> Réserver cet espace
            </a>

            <!-- Image Principale -->
            <div class="relative aspect-[4/3] md:aspect-video lg:aspect-[16/11] overflow-hidden rounded-[2.5rem] bg-slate-100 border border-slate-200 shadow-inner">
                <?php $premiereImg = !empty($galerie) ? 'uploads/'.e($galerie[0]['chemin']) : 'assets/img/placeholder.jpg'; ?>
                <img id="mainImg" src="<?= $premiereImg ?>" class="w-full h-full object-cover transition-all duration-300">
            </div>

            <!-- Miniatures (Thumbnails) -->
            <?php if(count($galerie) > 1): ?>
            <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
                <?php foreach($galerie as $index => $img): ?>
                <button onclick="updateMainImg(this, 'uploads/<?= e($img['chemin']) ?>')"
                        class="flex-none w-20 h-20 rounded-2xl overflow-hidden border-2 <?= $index === 0 ? 'border-accent shadow-lg' : 'border-transparent opacity-60' ?> transition-all hover:opacity-100 js-thumb">
                    <img src="uploads/<?= e($img['chemin']) ?>" class="w-full h-full object-cover">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (count($galerie) > 1): ?>
            <p class="text-center text-[10px] text-slate-400 italic lg:hidden"><i class="fas fa-arrows-alt-h mr-1"></i>Faites glisser les miniatures pour voir toutes les photos</p>
            <?php endif; ?>
        </div>

        <!-- 2. INFOS DE LA SALLE -->
        <div class="lg:col-start-1 lg:row-start-1 lg:row-span-3 flex flex-col space-y-5">
            <div>
                <span class="inline-flex rounded-full bg-accent/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-accent mb-2 italic"><?= e($espace['categorie']) ?></span>
                <h1 class="text-3xl md:text-5xl font-black text-primary uppercase italic tracking-tighter leading-none mb-4"><?= e($espace['nom']) ?></h1>

                <div class="prose prose-slate max-w-none text-slate-600 text-sm leading-relaxed mb-5">
                    <?= nl2br(e($espace['description'])) ?>
                </div>
            </div>

            <!-- Caractéristiques Rapides -->
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Capacité</div>
                    <p class="font-black text-2xl text-primary italic tracking-tighter"><?= e($espace['capacite']) ?></p>
                </div>
                <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Localisation</div>
                    <p class="font-black text-xl text-primary italic tracking-tighter">Magnambougou / Dianéguéla</p>
                </div>
            </div>

            <!-- LA TARIFICATION -->
            <div class="bg-white rounded-[2.5rem] border border-slate-100 p-6 shadow-sm">
                <?php if (!empty($espace['gerant_externe'])): ?>
                <div class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-5 flex items-start gap-3">
                    <i class="fas fa-user-tie text-amber-500 mt-0.5 text-lg"></i>
                    <div>
                        <p class="text-xs font-black uppercase tracking-widest text-amber-700 mb-2">En bail</p>
                        <p class="text-sm text-amber-700 leading-relaxed"><?= e($espace['gerant_externe']) ?></p>
                        <p class="text-xs text-amber-600 mt-3 italic">Le Palais ne fixe pas les tarifs de cet espace — la tarification et la réservation dépendent entièrement du gestionnaire.</p>
                    </div>
                </div>
                <?php else: ?>
                <h3 class="font-black text-primary uppercase italic text-xs mb-4 flex items-center gap-2">
                    <i class="fas fa-tags text-accent"></i> Grille Tarifaire
                </h3>
                <div class="space-y-4">
                    <?php foreach($tarifs as $t): ?>
                    <div class="pb-3 border-b border-slate-50 last:border-0">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-600">
                                <?= e($t['libelle']) ?>
                                <?php if ($t['est_bail']): ?><span class="ml-1 text-[8px] font-black uppercase bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded-full">Location en bail</span><?php endif; ?>
                            </span>
                            <span class="font-black text-primary text-sm"><?= number_format($t['montant'], 0, ',', ' ') ?> <small class="text-[9px]">FCFA/<?= e(str_replace('_','/',$t['unite'])) ?></small></span>
                        </div>
                        <?php if ($t['est_bail'] && (!empty($t['gerant_nom']) || !empty($t['gerant_contact']))): ?>
                        <p class="mt-1.5 text-[10px] text-amber-600 font-semibold">
                            <i class="fas fa-user-tie mr-1"></i><?= e($t['gerant_nom'] ?: 'Gestionnaire') ?>
                            <?php if (!empty($t['gerant_contact'])): ?>
                            — <a href="tel:<?= e(preg_replace('/\s+/', '', $t['gerant_contact'])) ?>" class="underline"><?= e($t['gerant_contact']) ?></a>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (array_filter($tarifs, fn($t) => $t['est_bail'])): ?>
                <p class="mt-4 text-[10px] text-slate-400 italic">Les locations en bail sont des engagements longue durée négociés directement avec le gestionnaire concerné — non réservables en ligne.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. PLANNING + COMMENT ÇA MARCHE -->
        <div class="lg:col-start-2 lg:row-start-2 space-y-4">
            <!-- Bouton Planning -->
            <button onclick="toggleModal('calModal')" class="group flex items-center gap-4 w-full p-4 rounded-3xl bg-white border border-slate-100 shadow-sm hover:shadow-md transition-all text-left">
                <div class="w-14 h-14 rounded-2xl bg-slate-50 flex items-center justify-center text-primary/20 group-hover:text-primary transition-colors">
                    <i class="fas fa-calendar-alt text-2xl"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase text-slate-300 tracking-widest leading-none mb-1">Planning</p>
                    <p class="text-sm font-black text-primary uppercase italic">Voir les disponibilités</p>
                </div>
            </button>

            <!-- COMMENT ÇA MARCHE -->
            <div class="rounded-[2.5rem] bg-primary/5 border border-primary/10 p-6">
                <h3 class="text-lg font-black text-primary flex items-center gap-2 mb-5 uppercase italic tracking-tighter">
                    <i class="fas fa-info-circle"></i> Comment ça marche ?
                </h3>
                <div class="space-y-5">
                    <div class="flex gap-5">
                        <div class="flex-none w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-black shadow-lg shadow-primary/20">1</div>
                        <div>
                            <p class="font-black text-primary uppercase italic text-sm mb-1">Demande en ligne</p>
                            <p class="text-xs text-slate-500 leading-relaxed">Choisissez votre créneau et remplissez le formulaire.</p>
                        </div>
                    </div>
                    <div class="flex gap-5">
                        <div class="flex-none w-10 h-10 rounded-full bg-accent text-white flex items-center justify-center font-black shadow-lg shadow-accent/20">2</div>
                        <div>
                            <p class="font-black text-primary uppercase italic text-sm mb-1">Paiement au Guichet</p>
                            <p class="text-xs text-slate-500 leading-relaxed">Réglez sous 48h au Palais des Pionniers.</p>
                        </div>
                    </div>
                    <div class="flex gap-5">
                        <div class="flex-none w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-black shadow-lg shadow-emerald-500/20">3</div>
                        <div>
                            <p class="font-black text-primary uppercase italic text-sm mb-1">Confirmation</p>
                            <p class="text-xs text-slate-500 leading-relaxed">Votre réservation est alors définitivement validée.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. CTA RÉSERVER (tout en bas) -->
        <div id="cta-reservation" class="lg:col-start-2 lg:row-start-3">
            <?php $tarifsReservables = array_filter($tarifs, fn($t) => !$t['est_bail']); ?>
            <?php $aOptionBail = array_filter($tarifs, fn($t) => $t['est_bail']); ?>
            <div class="bg-primary rounded-[2.5rem] p-6 shadow-xl shadow-primary/20">
                <?php if (!empty($espace['gerant_externe'])): ?>
                    <div class="mb-4 pb-4 border-b border-white/15">
                        <p class="text-[10px] font-black uppercase tracking-widest text-accent mb-1.5"><i class="fas fa-info-circle mr-1"></i>Qu'est-ce qu'un espace "en bail" ?</p>
                        <p class="text-xs text-white/70 leading-relaxed">Cet espace est loué sur une longue durée à un tiers (association, club, entreprise...), qui en gère l'usage au quotidien. Le Palais ne prend donc plus de réservation ponctuelle dessus.</p>
                    </div>
                    <?php $aFiche = !empty($espace['gerant_nom']) || !empty($espace['gerant_email']) || !empty($espace['gerant_contact']); ?>
                    <?php if ($aFiche): ?>
                    <button type="button" onclick="document.getElementById('ficheGestionnaire').classList.toggle('hidden')"
                       class="block w-full text-center bg-white text-primary py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all">
                        <i class="fas fa-address-card mr-2"></i>Contacter le gestionnaire
                    </button>
                    <div id="ficheGestionnaire" class="hidden mt-4 bg-white/10 border border-white/20 rounded-2xl p-5 space-y-2.5">
                        <?php if (!empty($espace['gerant_nom']) || !empty($espace['gerant_prenom'])): ?>
                        <p class="text-sm text-white"><i class="fas fa-user w-5 text-white/60"></i> <?= e(trim(($espace['gerant_prenom'] ?? '') . ' ' . ($espace['gerant_nom'] ?? ''))) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($espace['gerant_email'])): ?>
                        <p class="text-sm text-white"><i class="fas fa-envelope w-5 text-white/60"></i> <a href="mailto:<?= e($espace['gerant_email']) ?>" class="underline"><?= e($espace['gerant_email']) ?></a></p>
                        <?php endif; ?>
                        <?php if (!empty($espace['gerant_contact'])): ?>
                        <p class="text-sm text-white"><i class="fas fa-phone-alt w-5 text-white/60"></i> <a href="tel:<?= e(preg_replace('/\s+/', '', $espace['gerant_contact'])) ?>" class="underline"><?= e($espace['gerant_contact']) ?></a></p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="bg-white/10 border border-white/20 rounded-2xl p-4 mb-3">
                        <p class="text-xs text-white/80 leading-relaxed"><i class="fas fa-exclamation-circle mr-1.5"></i>Les coordonnées directes du gestionnaire ne sont pas encore renseignées pour cet espace. Contactez l'administration du Palais, qui fera le lien.</p>
                    </div>
                    <a href="contact.php?sujet=reservation_espace&espace=<?= (int)$espace['id'] ?>"
                       class="block w-full text-center bg-white text-primary py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all">
                        <i class="fas fa-paper-plane mr-2"></i>Contacter l'administration du Palais
                    </a>
                    <?php endif; ?>
                    <p class="mt-3 text-center text-[10px] text-white/70">La réservation en ligne n'est pas disponible pour cet espace — il est loué et géré directement par un tiers.</p>
                <?php elseif (!$tarifsReservables && $aOptionBail): ?>
                    <div class="mb-4 pb-4 border-b border-white/15">
                        <p class="text-[10px] font-black uppercase tracking-widest text-accent mb-1.5"><i class="fas fa-info-circle mr-1"></i>Location en bail — comment ça marche ?</p>
                        <p class="text-xs text-white/70 leading-relaxed">Cet espace ne se réserve pas ponctuellement, mais se loue sur une longue durée (mensuelle, trimestrielle, semestrielle ou annuelle). Faites votre demande ci-dessous, l'administration vous recontacte pour finaliser les modalités.</p>
                    </div>
                    <a href="demande-bail.php?espace_id=<?= (int)$espace['id'] ?>"
                       class="block w-full text-center bg-white text-primary py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all">
                        <i class="fas fa-file-signature mr-2"></i>Demander à prendre en bail
                    </a>
                    <p class="mt-3 text-center text-[10px] text-white/70">Cet espace se loue uniquement en bail longue durée, pas de réservation ponctuelle en ligne.</p>
                <?php elseif (!$tarifsReservables): ?>
                    <a href="contact.php?sujet=reservation_espace&espace=<?= (int)$espace['id'] ?>"
                       class="block w-full text-center bg-white text-primary py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all">
                        <i class="fas fa-paper-plane mr-2"></i>Nous contacter
                    </a>
                    <p class="mt-3 text-center text-[10px] text-white/70">Cet espace se loue uniquement sur devis, en direct avec l'administration.</p>
                <?php elseif (is_logged_in() && ($_SESSION['role'] ?? 'user') === 'user'): ?>
                    <a href="reserver.php?espace_id=<?= (int)$espace['id'] ?>"
                       class="block w-full text-center bg-white text-primary py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all">
                        Réserver cet espace
                    </a>
                    <?php if ($aOptionBail): ?>
                    <a href="demande-bail.php?espace_id=<?= (int)$espace['id'] ?>"
                       class="block w-full text-center mt-3 text-white/70 hover:text-white text-[11px] font-bold uppercase tracking-widest transition-all">
                        <i class="fas fa-file-signature mr-1"></i>Ou demander à le prendre en bail
                    </a>
                    <?php endif; ?>
                <?php elseif (is_logged_in()): ?>
                    <a href="admin/dashboard.php" class="block w-full text-center bg-white/10 border border-white/20 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-white hover:text-primary transition-all">
                        Retour à l'administration
                    </a>
                    <p class="mt-3 text-center text-[10px] text-white/70">Les comptes administrateurs ne peuvent pas réserver en tant que client.</p>
                <?php else: ?>
                    <a href="login.php?redirect=<?= urlencode('reserver.php?espace_id=' . (int)$espace['id']) ?>" class="block w-full text-center bg-white/10 border border-white/20 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-white hover:text-primary transition-all">
                        Connectez-vous pour réserver
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- MODALE CALENDRIER (Inchangée) -->
<div id="calModal" class="fixed inset-0 z-[100] hidden bg-slate-900/90 backdrop-blur-md flex items-center justify-center p-4" onclick="if(event.target === this) toggleModal('calModal')">
    <div class="bg-white rounded-[2.5rem] w-full max-w-md overflow-hidden shadow-2xl animate-in fade-in zoom-in duration-300">
        <div class="relative p-8 bg-slate-50/50 border-b border-slate-100">
            <button onclick="toggleModal('calModal')" class="absolute right-6 top-6 w-10 h-10 flex items-center justify-center rounded-full bg-white shadow-sm border border-slate-100 text-slate-400 hover:text-red-500 transition-colors">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="text-xl font-black text-primary uppercase italic tracking-tighter">Disponibilités</h3>
        </div>
        <div class="p-8">
            <div id="monthLabel" class="text-center font-black text-primary uppercase italic tracking-widest text-sm mb-8"></div>
            <div class="grid grid-cols-7 gap-2 text-center text-[10px] font-black text-slate-300 uppercase mb-4 italic">
                <span>Lu</span><span>Ma</span><span>Me</span><span>Je</span><span>Ve</span><span>Sa</span><span>Di</span>
            </div>
            <div id="calGrid" class="grid grid-cols-7 gap-2"></div>
        </div>
    </div>
</div>

<script>
// Logique de changement d'image
function updateMainImg(btn, src) {
    const mainImg = document.getElementById('mainImg');
    mainImg.style.opacity = '0.7';
    setTimeout(() => {
        mainImg.src = src;
        mainImg.style.opacity = '1';
    }, 150);

    document.querySelectorAll('.js-thumb').forEach(t => {
        t.classList.remove('border-accent', 'shadow-lg');
        t.classList.add('border-transparent', 'opacity-60');
    });
    btn.classList.add('border-accent', 'shadow-lg');
    btn.classList.remove('border-transparent', 'opacity-60');
}

function toggleModal(id) {
    const m = document.getElementById(id);
    m.classList.toggle('hidden');
    if (!m.classList.contains('hidden')) renderCal();
}

function renderCal() {
    const occupations = <?= json_encode($occupations) ?>;
    const grid = document.getElementById('calGrid');
    const label = document.getElementById('monthLabel');
    const now = new Date();
    const y = now.getFullYear(), m = now.getMonth();
    label.innerText = new Intl.DateTimeFormat('fr-FR', {month:'long', year:'numeric'}).format(now);
    const first = new Date(y, m, 1).getDay();
    const days = new Date(y, m+1, 0).getDate();
    let offset = (first === 0) ? 6 : first - 1;
    grid.innerHTML = '';
    for(let i=0; i<offset; i++) grid.appendChild(document.createElement('div'));
    for(let d=1; d<=days; d++) {
        const key = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const n = occupations[key] || 0;
        const el = document.createElement('div');
        el.className = "aspect-square flex items-center justify-center rounded-xl text-[11px] font-black transition-all";
        el.innerText = d;
        if(n >= 3) el.className += " bg-red-500 text-white";
        else if(n > 0) el.className += " bg-orange-400 text-white";
        else el.className += " bg-slate-50 text-slate-400";
        grid.appendChild(el);
    }
}
</script>

<style>
/* Cacher la scrollbar mais garder le défilement horizontal */
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>