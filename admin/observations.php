<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable']);

$pdo  = db();
$role = $_SESSION['role'] ?? '';
$msg  = null;

// Règles de visibilité / réponse par type de cible (superadmin voit et répond toujours à tout)
$visibiliteParType = [
    'espace'      => ['admin_espaces','ministre'],
    'reservation' => ['admin_espaces','ministre'],
    'paiement'    => ['admin_comptable','ministre'],
    'activite'    => ['admin_activites','ministre'],
];
$reponseParType = [
    'espace'      => ['admin_espaces'],
    'reservation' => ['admin_espaces'],
    'paiement'    => ['admin_comptable'],
    'activite'    => ['admin_activites'],
];
$typesVisiblesPourMoi = is_superadmin() ? ['reservation','paiement','activite','espace']
    : array_keys(array_filter($visibiliteParType, fn($roles) => in_array($role, $roles, true)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action    = $_POST['action']    ?? '';
        $cibleType = $_POST['cible_type']?? '';
        $cibleId   = (int)($_POST['cible_id'] ?? 0);
        $contenu   = trim($_POST['contenu']   ?? '');

        $typesValides = ['reservation','paiement','activite','espace'];

        if ($action === 'ajouter' && in_array($cibleType, $typesValides) && $cibleId && mb_strlen($contenu) >= 5) {
            $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

            $autorise = is_superadmin()
                || (!$parentId && in_array($cibleType, $typesVisiblesPourMoi, true))
                || ($parentId && in_array($role, $reponseParType[$cibleType] ?? [], true));

            if (!$autorise) {
                $msg = ['err', "Vous n'êtes pas autorisé à agir sur ce type d'observation."];
                goto finAjouter;
            }

            $pdo->prepare("INSERT INTO observations (auteur_id, cible_type, cible_id, contenu, parent_id) VALUES (?,?,?,?,?)")
                ->execute([$_SESSION['user_id'], $cibleType, $cibleId, $contenu, $parentId]);
            $auteurNom = $_SESSION['nom_complet'] ?? 'Administrateur';
            if ($parentId) {
                $parentAuteur = $pdo->prepare("SELECT auteur_id FROM observations WHERE id = ?");
                $parentAuteur->execute([$parentId]);
                $parentAuteurId = $parentAuteur->fetchColumn();
                if ($parentAuteurId && $parentAuteurId != $_SESSION['user_id']) {
                    notify('', 'reponse_observation', "«$auteurNom» a répondu à votre observation sur " . ucfirst($cibleType) . " #$cibleId", "observations.php", (int)$parentAuteurId);
                }
                log_activity('observation_repondue', 'messages', "Réponse à l'observation #$parentId");
            } else {
                notify('superadmin', 'nouvelle_observation', "Nouvelle observation de «$auteurNom» sur " . ucfirst($cibleType) . " #$cibleId", "observations.php");
                log_activity('observation_ajoutee', 'messages', "Observation sur $cibleType #$cibleId");
            }
            $msg = ['ok', $parentId ? 'Réponse envoyée.' : 'Observation enregistrée.'];
        }
        finAjouter:

        if ($action === 'supprimer' && is_superadmin()) {
            $id = (int)($_POST['obs_id'] ?? 0);
            $pdo->prepare("DELETE FROM observations WHERE id = ?")->execute([$id]);
            $msg = ['ok', 'Observation supprimée.'];
        }
    }
}

$filterType = $_GET['type'] ?? '';
$search     = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
// Toujours restreindre aux types que ce rôle a le droit de voir
if (!$typesVisiblesPourMoi) {
    $where[] = '1=0'; // aucun type autorisé pour ce rôle
} else {
    $in = implode(',', array_fill(0, count($typesVisiblesPourMoi), '?'));
    $where[] = "o.cible_type IN ($in)";
    $params = array_merge($params, $typesVisiblesPourMoi);
}
if ($filterType && in_array($filterType, $typesVisiblesPourMoi, true)) {
    $where[]  = 'o.cible_type = ?';
    $params[] = $filterType;
}
if ($search) {
    $where[]  = '(o.contenu LIKE ? OR u.nom_complet LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%"]);
}
$where[] = 'o.parent_id IS NULL';
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$observations = $pdo->prepare("
    SELECT o.*, u.nom_complet AS auteur_nom, u.role AS auteur_role
    FROM observations o
    JOIN users u ON u.id = o.auteur_id
    $whereSQL
    ORDER BY o.created_at DESC
");
$observations->execute($params);
$observations = $observations->fetchAll();

// Réponses, groupées par observation parente
$reponsesParParent = [];
if ($observations) {
    $ids = array_column($observations, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $rq  = $pdo->prepare("
        SELECT o.*, u.nom_complet AS auteur_nom, u.role AS auteur_role
        FROM observations o
        JOIN users u ON u.id = o.auteur_id
        WHERE o.parent_id IN ($in)
        ORDER BY o.created_at ASC
    ");
    $rq->execute($ids);
    foreach ($rq->fetchAll() as $rep) { $reponsesParParent[$rep['parent_id']][] = $rep; }
}

$espaces      = $pdo->query("SELECT id, nom FROM espaces ORDER BY nom")->fetchAll();
$activites    = $pdo->query("SELECT id, nom FROM activites ORDER BY nom")->fetchAll();
$reservations = $pdo->query("
    SELECT r.id, u.nom_complet, e.nom AS espace_nom, r.date_resa
    FROM reservations r
    JOIN users u   ON u.id = r.user_id
    JOIN espaces e ON e.id = r.espace_id
    ORDER BY r.created_at DESC LIMIT 20
")->fetchAll();

// Pré-remplissage venant d'un lien externe (dashboard, fiche réservation) —
// si la cible demandée n'est pas dans les 20 plus récentes, on l'ajoute à part.
$preCibleType = in_array($_GET['cible_type'] ?? '', ['reservation','paiement','activite','espace'], true) ? $_GET['cible_type'] : '';
$preCibleId   = (int)($_GET['cible_id'] ?? 0);
if ($preCibleType && $preCibleId && in_array($preCibleType, ['reservation','paiement'], true)
    && !in_array($preCibleId, array_column($reservations, 'id'))) {
    $s = $pdo->prepare("
        SELECT r.id, u.nom_complet, e.nom AS espace_nom, r.date_resa
        FROM reservations r JOIN users u ON u.id = r.user_id JOIN espaces e ON e.id = r.espace_id
        WHERE r.id = ?
    ");
    $s->execute([$preCibleId]);
    if ($extra = $s->fetch()) array_unshift($reservations, $extra);
}

$typeIcons = [
    'reservation' => ['fa-calendar-check', 'bg-amber-50 text-amber-600',   'Réservation'],
    'paiement'    => ['fa-cash-register',  'bg-green-50 text-green-600',   'Paiement'],
    'activite'    => ['fa-star',           'bg-purple-50 text-purple-600', 'Activité'],
    'espace'      => ['fa-building',       'bg-blue-50 text-blue-600',     'Espace'],
];

$pageTitle = "Observations & Notes";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Observations & Notes</h1>
    <p class="text-sm text-slate-500 mt-0.5"><?= count($observations) ?> observation(s)</p>
  </div>
  <button onclick="document.getElementById('obsForm').classList.toggle('hidden')"
          class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-red-700 transition shadow-lg">
    <i class="fas fa-plus"></i> Nouvelle observation
  </button>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<!-- Formulaire -->
<div id="obsForm" class="hidden mb-6 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
    <h2 class="font-black text-primary text-sm uppercase italic flex items-center gap-2">
      <i class="fas fa-pen text-accent"></i> Nouvelle observation
    </h2>
    <button onclick="document.getElementById('obsForm').classList.add('hidden')"
            class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent transition">
      <i class="fas fa-times text-xs"></i>
    </button>
  </div>
  <form method="POST" class="p-6 space-y-5">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action"     value="ajouter">

    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Concerne <span class="text-accent">*</span></label>
        <div class="relative">
          <select name="cible_type" required onchange="onCibleTypeChange(this.value)"
                  class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm appearance-none">
            <option value="">— Choisir —</option>
            <?php $labelsCible = ['reservation'=>'Réservation','paiement'=>'Paiement','activite'=>'Activité','espace'=>'Espace']; ?>
            <?php foreach ($typesVisiblesPourMoi as $tv): ?>
            <option value="<?= $tv ?>"><?= $labelsCible[$tv] ?></option>
            <?php endforeach; ?>
          </select>
          <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        </div>
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Sélectionner <span class="text-accent">*</span></label>
        <div class="relative">
          <select name="cible_id" required id="cibleSelect"
                  class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm appearance-none">
            <option value="">— Choisissez d'abord un type —</option>
          </select>
          <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
        </div>
      </div>
    </div>

    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Observation / Note <span class="text-accent">*</span></label>
      <div class="relative">
        <i class="fas fa-pen absolute left-4 top-4 text-slate-400 text-sm"></i>
        <textarea name="contenu" rows="4" required minlength="5"
                  placeholder="Rédigez votre observation ou remarque..."
                  class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm resize-none"></textarea>
      </div>
    </div>

    <div class="flex justify-end">
      <button type="submit"
              class="flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition">
        <i class="fas fa-save"></i> Enregistrer
      </button>
    </div>
  </form>
</div>

<!-- Filtres -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[160px]">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Rechercher</label>
      <div class="relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Contenu, auteur..."
               class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-primary outline-none focus:border-primary">
      </div>
    </div>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Type</label>
      <div class="relative">
        <select name="type" class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none appearance-none pr-8">
          <option value="">Tous</option>
          <?php foreach ($typeIcons as $k => [$i,$c,$l]): ?>
            <option value="<?= $k ?>" <?= $filterType===$k?'selected':''?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
      </div>
    </div>
    <button class="bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    <?php if ($search || $filterType): ?>
    <a href="observations.php" class="text-xs font-black text-slate-400 hover:text-primary px-3 py-2.5 rounded-xl border border-slate-200 transition">
      <i class="fas fa-times mr-1"></i> Reset
    </a>
    <?php endif; ?>
  </form>
</div>

<!-- Liste -->
<div class="space-y-4">
  <?php if (empty($observations)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 py-16 text-center shadow-sm">
    <i class="fas fa-eye text-4xl text-slate-200 mb-3"></i>
    <p class="text-slate-400 font-semibold">Aucune observation pour le moment.</p>
  </div>
  <?php else: ?>
  <?php foreach ($observations as $obs):
    [$oicon, $ocls, $olabel] = $typeIcons[$obs['cible_type']] ?? ['fa-circle','bg-slate-100 text-slate-400','—'];
  ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:shadow-md transition">
    <div class="flex items-start gap-4">
      <div class="w-10 h-10 <?= $ocls ?> rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
        <i class="fas <?= $oicon ?> text-sm"></i>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[10px] font-black px-2.5 py-1 rounded-full <?= $ocls ?>"><?= $olabel ?> #<?= $obs['cible_id'] ?></span>
            <span class="text-xs font-black text-primary"><?= e($obs['auteur_nom']) ?></span>
            <span class="text-[9px] text-slate-400"><?= date('d/m/Y à H:i', strtotime($obs['created_at'])) ?></span>
          </div>
          <?php if (is_superadmin()): ?>
          <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette observation ?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action"  value="supprimer">
            <input type="hidden" name="obs_id"  value="<?= $obs['id'] ?>">
            <button class="text-xs text-slate-300 hover:text-accent transition"><i class="fas fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </div>
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
          <p class="text-sm text-slate-700 leading-relaxed font-semibold whitespace-pre-line"><?= e($obs['contenu']) ?></p>
        </div>

        <?php if (!empty($reponsesParParent[$obs['id']])): ?>
        <div class="mt-3 ml-4 space-y-2 border-l-2 border-slate-100 pl-4">
          <?php foreach ($reponsesParParent[$obs['id']] as $rep): ?>
          <div class="bg-white rounded-xl p-3 border border-slate-100">
            <div class="flex items-center gap-2 mb-1.5">
              <span class="text-xs font-black text-primary"><?= e($rep['auteur_nom']) ?></span>
              <span class="text-[9px] text-slate-400"><?= date('d/m/Y à H:i', strtotime($rep['created_at'])) ?></span>
            </div>
            <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line"><?= e($rep['contenu']) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php $peutRepondre = is_superadmin() || in_array($role, $reponseParType[$obs['cible_type']] ?? [], true); ?>
        <?php if ($peutRepondre): ?>
        <button onclick="document.getElementById('replyForm<?= $obs['id'] ?>').classList.toggle('hidden')"
                class="mt-3 text-[11px] font-black text-accent uppercase tracking-widest hover:underline">
          <i class="fas fa-reply mr-1"></i>Répondre
        </button>
        <form method="POST" id="replyForm<?= $obs['id'] ?>" class="hidden mt-3 flex gap-2">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="ajouter">
          <input type="hidden" name="cible_type" value="<?= e($obs['cible_type']) ?>">
          <input type="hidden" name="cible_id" value="<?= $obs['cible_id'] ?>">
          <input type="hidden" name="parent_id" value="<?= $obs['id'] ?>">
          <input type="text" name="contenu" required minlength="5" placeholder="Votre réponse..."
                 onkeydown="if(event.key==='Enter'){event.preventDefault();}"
                 class="flex-1 rounded-xl border-2 border-slate-100 bg-white px-3 py-2 text-sm font-semibold text-primary outline-none focus:border-primary">
          <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-4 py-2 rounded-xl hover:bg-slate-800 transition">Envoyer</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
const espaces      = <?= json_encode(array_map(fn($e) => ['id'=>$e['id'],'nom'=>$e['nom']], $espaces)) ?>;
const activites    = <?= json_encode(array_map(fn($a) => ['id'=>$a['id'],'nom'=>$a['nom']], $activites)) ?>;
const reservations = <?= json_encode(array_map(fn($r) => ['id'=>$r['id'],'nom'=>"#{$r['id']} — {$r['nom_complet']} · {$r['espace_nom']} ({$r['date_resa']})"], $reservations)) ?>;

function onCibleTypeChange(type) {
    const sel  = document.getElementById('cibleSelect');
    sel.innerHTML = '<option value="">— Sélectionner —</option>';
    const data = type === 'espace' ? espaces : type === 'activite' ? activites : (type === 'reservation' || type === 'paiement') ? reservations : [];
    data.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.nom;
        sel.appendChild(opt);
    });
}

const preCibleType = <?= json_encode($preCibleType) ?>;
const preCibleId    = <?= json_encode($preCibleId) ?>;
if (preCibleType && preCibleId) {
    document.getElementById('obsForm').classList.remove('hidden');
    document.querySelector('select[name="cible_type"]').value = preCibleType;
    onCibleTypeChange(preCibleType);
    document.getElementById('cibleSelect').value = preCibleId;
    document.getElementById('obsForm').scrollIntoView({behavior:'smooth', block:'start'});
}
</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>