<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$idPreselectionne = isset($_GET['espace_id']) ? (int)$_GET['espace_id'] : 0;

$espacesData = $pdo->query("
    SELECT e.id, e.nom, e.capacite, 
    (SELECT chemin FROM espace_images WHERE espace_id = e.id LIMIT 1) as photo 
    FROM espaces e ORDER BY e.nom ASC
")->fetchAll(PDO::FETCH_UNIQUE);

// CORRECTION : Ajout de l'ID dans le SELECT des tarifs
$tarifsData = $pdo->query("SELECT espace_id, id, libelle, montant FROM tarifs ORDER BY montant ASC")->fetchAll(PDO::FETCH_GROUP);

$occupations = $pdo->query("
    SELECT espace_id, date_resa, COUNT(*) as nb 
    FROM reservations 
    WHERE statut = 'validee' AND date_resa >= CURRENT_DATE 
    GROUP BY espace_id, date_resa
")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

$pageTitle = "Réserver un espace — Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto max-w-5xl px-4 py-8 md:py-16">
    <div class="mb-10">
        <a href="espaces.php" class="text-xs font-black text-slate-400 uppercase tracking-widest hover:text-primary transition">← Retour aux espaces</a>
        <h1 class="text-3xl md:text-5xl font-black text-primary uppercase italic tracking-tighter mt-4">
            Finaliser votre <span class="text-accent">réservation</span>
        </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-xl overflow-hidden">
                <form action="traitement-reservation.php" method="POST" class="p-8 md:p-10 space-y-8">
                    
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Quel espace souhaitez-vous ?</label>
                        <div class="relative group">
                            <select name="espace_id" id="espaceSelect" required onchange="updateEspaceInfos()"
                                    class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-6 py-5 font-black text-primary uppercase italic text-sm focus:ring-4 focus:ring-primary/5 outline-none transition appearance-none cursor-pointer">
                                <option value="">Sélectionner un espace...</option>
                                <?php foreach ($espacesData as $id => $esp): ?>
                                    <option value="<?= $id ?>" <?= ($idPreselectionne == $id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($esp['nom']) ?> (<?= htmlspecialchars($esp['capacite']) ?> places)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div id="tarifSection" class="hidden">
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3">Options & Tarifs</label>
                        <div id="tarifGrid" class="grid gap-3"></div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3 text-accent">1. Choisir la date</label>
                            <input type="date" name="date_resa" id="dateInput" required min="<?= date('Y-m-d') ?>" onchange="syncCalWithInput()"
                                   class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-6 py-5 font-black text-primary outline-none focus:border-accent transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3 text-accent">2. Choisir l'heure</label>
                            <div class="relative">
                                <select name="heure_resa" required class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-6 py-5 font-black text-primary outline-none appearance-none cursor-pointer">
                                    <?php for($h=8; $h<=22; $h++): ?>
                                        <option value="<?= $h ?>:00"><?= $h ?>:00</option>
                                        <option value="<?= $h ?>:30"><?= $h ?>:30</option>
                                    <?php endfor; ?>
                                </select>
                                <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-primary">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 tracking-widest mb-3 italic">Votre Téléphone</label>
                        <input type="tel" name="telephone" placeholder="+223 00 00 00 00" required
                               class="w-full rounded-2xl border border-slate-100 bg-slate-50 px-6 py-5 font-black text-primary outline-none focus:border-accent transition">
                    </div>

                    <button type="submit" class="w-full bg-primary text-white py-6 rounded-[2rem] font-black uppercase tracking-widest text-sm hover:bg-slate-900 transition-all shadow-2xl">
                        Envoyer ma demande
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div id="espacePreview" class="bg-white rounded-[2.5rem] p-6 border border-slate-100 shadow-sm hidden">
                <img id="previewImg" src="" class="w-full h-44 object-cover rounded-2xl mb-4">
                <h3 id="previewNom" class="font-black text-primary uppercase italic tracking-tighter text-2xl"></h3>
            </div>

            <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white shadow-2xl">
                <div class="flex items-center justify-between mb-8">
                    <button onclick="changeMonth(-1)" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-accent hover:text-white transition-all text-lg">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div id="monthLabel" class="text-center font-black uppercase italic text-xs text-accent tracking-widest"></div>
                    <button onclick="changeMonth(1)" class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 hover:bg-accent hover:text-white transition-all text-lg">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div id="calGrid" class="grid grid-cols-7 gap-2"></div>
            </div>
        </div>
    </div>
</div>

<script>
const espaces = <?= json_encode($espacesData) ?>;
const tarifs = <?= json_encode($tarifsData) ?>;
const occupations = <?= json_encode($occupations) ?>;
let currentViewDate = new Date();

function updateEspaceInfos() {
    const id = document.getElementById('espaceSelect').value;
    const section = document.getElementById('tarifSection');
    const grid = document.getElementById('tarifGrid');
    const preview = document.getElementById('espacePreview');

    if (!id) {
        section.classList.add('hidden');
        preview.classList.add('hidden');
        return;
    }

    const esp = espaces[id];
    document.getElementById('previewNom').innerText = esp.nom;
    document.getElementById('previewImg').src = esp.photo ? 'uploads/' + esp.photo : 'https://placehold.co/600x400';
    preview.classList.remove('hidden');

    grid.innerHTML = '';
    if (tarifs[id]) {
        tarifs[id].forEach((t, index) => {
            const label = document.createElement('label');
            label.className = "flex items-center justify-between p-5 rounded-2xl border border-slate-100 bg-white cursor-pointer hover:border-accent transition group shadow-sm";
            label.innerHTML = `
                <div class="flex items-center gap-4">
                    <input type="radio" name="tarif_id" value="${t.id}" ${index === 0 ? 'checked' : ''} class="w-4 h-4 accent-accent">
                    <span class="text-[11px] font-black uppercase text-slate-600 italic">${t.libelle}</span>
                </div>
                <span class="font-black text-primary text-sm">${new Intl.NumberFormat().format(t.montant)} FCFA</span>
            `;
            grid.appendChild(label);
        });
        section.classList.remove('hidden');
    }
    renderCal();
}

// ... garder les fonctions changeMonth, syncCalWithInput, selectDateFromCal et renderCal ...
function changeMonth(dir) { currentViewDate.setMonth(currentViewDate.getMonth() + dir); renderCal(); }
function syncCalWithInput() { const val = document.getElementById('dateInput').value; if(val) { currentViewDate = new Date(val); renderCal(); } }
function selectDateFromCal(d, m, y) { document.getElementById('dateInput').value = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`; renderCal(); }

function renderCal() {
    const id = document.getElementById('espaceSelect').value;
    const grid = document.getElementById('calGrid');
    const label = document.getElementById('monthLabel');
    const inputVal = document.getElementById('dateInput').value;
    const y = currentViewDate.getFullYear(), m = currentViewDate.getMonth();
    label.innerText = new Intl.DateTimeFormat('fr-FR', {month:'long', year:'numeric'}).format(currentViewDate);
    grid.innerHTML = '';
    const first = new Date(y, m, 1).getDay();
    const days = new Date(y, m+1, 0).getDate();
    let offset = (first === 0) ? 6 : first - 1;
    for(let i=0; i<offset; i++) grid.appendChild(document.createElement('div'));
    for(let d=1; d<=days; d++) {
        const dStr = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isSelected = inputVal === dStr;
        const el = document.createElement('div');
        el.className = `aspect-square flex items-center justify-center rounded-xl text-[10px] font-black cursor-pointer transition-all bg-white/5 text-slate-400 hover:bg-accent hover:text-white`;
        if (isSelected) el.className += " bg-accent text-white ring-2 ring-white";
        el.innerText = d;
        el.onclick = () => selectDateFromCal(d, m, y);
        grid.appendChild(el);
    }
}
updateEspaceInfos();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>