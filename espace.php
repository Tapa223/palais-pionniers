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

<div class="container mx-auto px-4 py-6 md:py-14">
    <!-- Retour -->
    <a href="espaces.php" class="inline-flex items-center gap-1.5 text-xs font-black text-slate-400 hover:text-primary transition uppercase tracking-widest">
        <i class="fas fa-arrow-left"></i> Retour aux espaces
    </a>

    <!-- Grille Principale -->
    <div class="mt-8 flex flex-col-reverse lg:grid lg:grid-cols-2 gap-8 lg:items-start">
        
        <!-- COLONNE GAUCHE (Description + Tarifs + Infos) -->
        <div class="flex flex-col space-y-6">
            <div>
                <span class="inline-flex rounded-full bg-accent/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-accent mb-2 italic"><?= e($espace['categorie']) ?></span>
                <h1 class="text-3xl md:text-5xl font-black text-primary uppercase italic tracking-tighter leading-none mb-6"><?= e($espace['nom']) ?></h1>
                
                <div class="prose prose-slate max-w-none text-slate-600 text-sm leading-relaxed mb-8">
                    <?= nl2br(e($espace['description'])) ?>
                </div>
            </div>

            <!-- Caractéristiques Rapides -->
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <div class="text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Capacité</div>
                    <p class="font-black text-2xl text-primary italic tracking-tighter"><?= e($espace['capacite']) ?></p>
                </div>
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <div class="text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Localisation</div>
                    <p class="font-black text-xl text-primary italic tracking-tighter">Magnambougou</p>
                </div>
            </div>

            <!-- LA TARIFICATION -->
            <div class="bg-white rounded-[2.5rem] border border-slate-100 p-8 shadow-sm">
                <h3 class="font-black text-primary uppercase italic text-xs mb-6 flex items-center gap-2">
                    <i class="fas fa-tags text-accent"></i> Grille Tarifaire
                </h3>
                <div class="space-y-4">
                    <?php foreach($tarifs as $t): ?>
                    <div class="flex justify-between items-center pb-3 border-b border-slate-50 last:border-0">
                        <span class="text-xs font-bold text-slate-600"><?= e($t['libelle']) ?></span>
                        <span class="font-black text-primary text-sm"><?= number_format($t['montant'], 0, ',', ' ') ?> <small class="text-[9px]">FCFA</small></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- CTA RÉSERVER -->
            <div class="bg-primary rounded-[2.5rem] p-6 shadow-xl shadow-primary/20">
                <?php if (is_logged_in()): ?>
                    <a href="reserver.php?espace_id=<?= (int)$espace['id'] ?>" 
                       class="block w-full text-center bg-white text-primary py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-accent hover:text-white transition-all">
                        Réserver cet espace
                    </a>
                <?php else: ?>
                    <a href="login.php" class="block w-full text-center bg-white/10 border border-white/20 text-white py-5 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-white hover:text-primary transition-all">
                        Connectez-vous pour réserver
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- COLONNE DROITE (Galerie + Planning + Comment ça marche) -->
        <div class="space-y-6">
            <!-- Galerie Photo Interactive -->
            <div class="space-y-4">
                <!-- Image Principale -->
                <div class="relative aspect-[4/3] md:aspect-video lg:aspect-[16/11] overflow-hidden rounded-[2.5rem] bg-slate-100 border border-slate-200 shadow-inner">
                    <?php $premiereImg = !empty($galerie) ? 'uploads/'.e($galerie[0]['chemin']) : 'assets/img/placeholder.jpg'; ?>
                    <img id="mainImg" src="<?= $premiereImg ?>" class="w-full h-full object-cover transition-all duration-300">
                    
                    <span class="absolute left-6 top-6 inline-flex items-center gap-2 rounded-full px-4 py-2 text-[10px] font-black uppercase tracking-widest <?= $isOccupied ? 'bg-red-500' : 'bg-[#10b981]' ?> text-white shadow-lg z-10">
                        <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                        <?= $isOccupied ? 'Occupé' : 'Disponible' ?>
                    </span>
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
            </div>

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
            <div class="rounded-[2.5rem] bg-primary/5 border border-primary/10 p-8">
                <h3 class="text-lg font-black text-primary flex items-center gap-2 mb-8 uppercase italic tracking-tighter">
                    <i class="fas fa-info-circle"></i> Comment ça marche ?
                </h3>
                <div class="space-y-8">
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