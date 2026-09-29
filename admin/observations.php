<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable']);

$pdo  = db();
$role = $_SESSION['role'] ?? '';
$moi  = (int)($_SESSION['user_id'] ?? 0);
$msg  = null;

/*
 * Règles d'accès (includes/auth.php, observations_regles()) :
 *  - un rôle ne voit que les observations des types d'objets auxquels
 *    il a accès (le superadmin voit tout) ;
 *  - peuvent répondre : le rôle responsable de l'objet et l'auteur de
 *    l'observation d'origine (fil : observation → réponse → réponse) ;
 *  - aucune suppression : une observation fait partie de la trace.
 */
$regles = observations_regles();
$typesConnus = ['reservation', 'paiement', 'activite', 'espace', 'requisition', 'remboursement'];
$typesDispo = array_values(array_intersect($typesConnus, observations_types_disponibles($pdo)));
$typesVisiblesPourMoi = is_superadmin()
    ? $typesDispo
    : array_values(array_filter($typesDispo, fn($t) => in_array($role, $regles['voir'][$t] ?? [], true)));

// L'objet visé existe-t-il ?
$cibleExiste = function (string $type, int $id) use ($pdo): bool {
    $tables = [
        'reservation' => 'reservations', 'paiement' => 'paiements', 'activite' => 'activites',
        'espace' => 'espaces', 'requisition' => 'requisitions_ministerielles', 'remboursement' => 'remboursements',
    ];
    if (!isset($tables[$type]) || $id <= 0) {
        return false;
    }
    $st = $pdo->prepare("SELECT 1 FROM {$tables[$type]} WHERE id = ?");
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
};

$labelsCible = [
    'reservation' => 'Réservation', 'paiement' => 'Paiement', 'activite' => 'Activité',
    'espace' => 'Espace', 'requisition' => 'Réquisition', 'remboursement' => 'Remboursement',
];
// Référence lisible d'un objet (RESA-12, REQ-3, reçu, bon…)
$refCible = function (string $type, int $id): string {
    return match ($type) {
        'reservation'   => ref_resa($id),
        'requisition'   => ref_req($id),
        'paiement'      => 'Paiement #' . $id,
        'remboursement' => 'Remboursement #' . $id,
        default         => '#' . $id,
    };
};
// Lien vers l'objet, uniquement vers des pages accessibles au rôle
$lienCible = function (string $type, int $id) use ($role): ?string {
    $compta = in_array($role, ['superadmin', 'ministre', 'admin_comptable'], true);
    return match (true) {
        $type === 'reservation' && $compta                          => 'paiements.php?resa=' . $id,
        $type === 'reservation'                                     => 'reservations.php?q=RESA-' . $id,
        $type === 'paiement' && $compta                             => 'paiements.php?id=' . $id,
        $type === 'requisition' && $compta                          => 'requisition-detail.php?id=' . $id,
        default                                                     => null,
    };
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action    = $_POST['action']    ?? '';
        $cibleType = $_POST['cible_type']?? '';
        $cibleId   = (int)($_POST['cible_id'] ?? 0);
        $contenu   = trim($_POST['contenu']   ?? '');
        $parentId  = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($action === 'ajouter') {
            try {
                if (mb_strlen($contenu) < 5 || mb_strlen($contenu) > 5000) {
                    throw new RuntimeException('L\'observation doit contenir entre 5 et 5000 caractères.');
                }

                if ($parentId) {
                    // Réponse : l'objet est celui du fil d'origine (jamais celui envoyé par le navigateur)
                    $st = $pdo->prepare("SELECT id, auteur_id, cible_type, cible_id FROM observations WHERE id = ? AND parent_id IS NULL");
                    $st->execute([$parentId]);
                    $parent = $st->fetch();
                    if (!$parent) {
                        throw new RuntimeException('Observation introuvable.');
                    }
                    $cibleType = $parent['cible_type'];
                    $cibleId = (int)$parent['cible_id'];
                    $autorise = is_superadmin()
                        || (in_array($cibleType, $typesVisiblesPourMoi, true)
                            && (in_array($role, $regles['repondre'][$cibleType] ?? [], true) || (int)$parent['auteur_id'] === $moi));
                } else {
                    $autorise = in_array($cibleType, $typesVisiblesPourMoi, true);
                }

                if (!$autorise) {
                    throw new RuntimeException("Vous n'êtes pas autorisé à agir sur ce type d'observation.");
                }
                if (!$cibleExiste($cibleType, $cibleId)) {
                    throw new RuntimeException('L\'élément concerné est introuvable.');
                }

                $pdo->prepare("INSERT INTO observations (auteur_id, cible_type, cible_id, contenu, parent_id) VALUES (?,?,?,?,?)")
                    ->execute([$moi, $cibleType, $cibleId, $contenu, $parentId]);

                $auteurNom = $_SESSION['nom_complet'] ?? 'Administrateur';
                $objet = ($labelsCible[$cibleType] ?? $cibleType) . ' ' . $refCible($cibleType, $cibleId);
                $lienObs = 'observations.php?cible_type=' . $cibleType . '&cible_id=' . $cibleId;

                if ($parentId) {
                    if ((int)$parent['auteur_id'] !== $moi) {
                        notify('', 'reponse_observation', "«{$auteurNom}» a répondu à votre observation sur $objet", $lienObs, (int)$parent['auteur_id']);
                    } else {
                        // L'auteur relance le fil : les rôles responsables sont prévenus
                        foreach ($regles['repondre'][$cibleType] ?? [] as $r) {
                            notify($r, 'reponse_observation', "«{$auteurNom}» a complété son observation sur $objet", $lienObs);
                        }
                    }
                    log_activity('observation_repondue', 'messages', "Réponse à l'observation #$parentId ($objet)");
                } else {
                    notify('superadmin', 'nouvelle_observation', "Nouvelle observation de «{$auteurNom}» sur $objet", $lienObs);
                    foreach ($regles['repondre'][$cibleType] ?? [] as $r) {
                        if ($r !== $role) {
                            notify($r, 'nouvelle_observation', "Nouvelle observation de «{$auteurNom}» sur $objet", $lienObs);
                        }
                    }
                    log_activity('observation_ajoutee', 'messages', "Observation sur $objet");
                }
                $msg = ['ok', $parentId ? 'Réponse envoyée.' : 'Observation enregistrée.'];
            } catch (RuntimeException $e) {
                $msg = ['err', $e->getMessage()];
            }
        }
        // Aucune action de suppression : les observations sont conservées (traçabilité).
    }
}

$filterType = $_GET['type'] ?? '';
$search     = trim($_GET['q'] ?? '');

// Lien « Observer » depuis une fiche : filtre sur l'objet et pré-remplit le formulaire
$preCibleType = in_array($_GET['cible_type'] ?? '', $typesVisiblesPourMoi, true) ? $_GET['cible_type'] : '';
$preCibleId   = $preCibleType ? (int)($_GET['cible_id'] ?? 0) : 0;

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
if ($preCibleType && $preCibleId) {
    $where[]  = 'o.cible_type = ? AND o.cible_id = ?';
    $params[] = $preCibleType;
    $params[] = $preCibleId;
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
        ORDER BY o.created_at ASC, o.id ASC
    ");
    $rq->execute($ids);
    foreach ($rq->fetchAll() as $rep) { $reponsesParParent[$rep['parent_id']][] = $rep; }
}

// Listes de choix du formulaire (uniquement pour les types visibles)
$optionsCible = [];
if (in_array('espace', $typesVisiblesPourMoi, true)) {
    $optionsCible['espace'] = array_map(fn($x) => ['id' => (int)$x['id'], 'nom' => $x['nom']],
        $pdo->query("SELECT id, nom FROM espaces ORDER BY nom")->fetchAll());
}
if (in_array('activite', $typesVisiblesPourMoi, true)) {
    $optionsCible['activite'] = array_map(fn($x) => ['id' => (int)$x['id'], 'nom' => $x['nom']],
        $pdo->query("SELECT id, nom FROM activites ORDER BY nom")->fetchAll());
}
if (in_array('reservation', $typesVisiblesPourMoi, true)) {
    $optionsCible['reservation'] = array_map(fn($x) => ['id' => (int)$x['id'], 'nom' => ref_resa((int)$x['id']) . " — {$x['nom_complet']} · {$x['espace_nom']} (" . date('d/m/Y', strtotime($x['date_resa'])) . ')'],
        $pdo->query("
            SELECT r.id, u.nom_complet, e.nom AS espace_nom, r.date_resa
            FROM reservations r JOIN users u ON u.id = r.user_id JOIN espaces e ON e.id = r.espace_id
            ORDER BY r.created_at DESC LIMIT 30
        ")->fetchAll());
}
if (in_array('paiement', $typesVisiblesPourMoi, true)) {
    $optionsCible['paiement'] = array_map(fn($x) => ['id' => (int)$x['id'], 'nom' => ref_recu((int)$x['id'], $x['created_at']) . ' — ' . ref_resa((int)$x['reservation_id']) . " · {$x['nom_complet']} · " . number_format((float)$x['montant'], 0, ',', ' ') . ' FCFA'],
        $pdo->query("
            SELECT p.id, p.created_at, p.reservation_id, p.montant, u.nom_complet
            FROM paiements p JOIN reservations r ON r.id = p.reservation_id JOIN users u ON u.id = r.user_id
            ORDER BY p.created_at DESC LIMIT 30
        ")->fetchAll());
}
if (in_array('requisition', $typesVisiblesPourMoi, true)) {
    $optionsCible['requisition'] = array_map(fn($x) => ['id' => (int)$x['id'], 'nom' => ref_req((int)$x['id']) . ' — ' . ref_resa((int)$x['reservation_id']) . " · {$x['nom_complet']}"],
        $pdo->query("
            SELECT rm.id, rm.reservation_id, u.nom_complet
            FROM requisitions_ministerielles rm JOIN reservations r ON r.id = rm.reservation_id JOIN users u ON u.id = r.user_id
            ORDER BY rm.id DESC LIMIT 30
        ")->fetchAll());
}
if (in_array('remboursement', $typesVisiblesPourMoi, true)) {
    $optionsCible['remboursement'] = array_map(fn($x) => ['id' => (int)$x['id'], 'nom' => 'Remboursement #' . $x['id'] . ' — ' . ref_req((int)$x['requisition_id']) . " · {$x['nom_complet']} · " . number_format((float)$x['montant_a_rembourser'], 0, ',', ' ') . ' FCFA'],
        $pdo->query("
            SELECT rb.id, rb.requisition_id, rb.montant_a_rembourser, u.nom_complet
            FROM remboursements rb JOIN users u ON u.id = rb.client_id
            ORDER BY rb.id DESC LIMIT 30
        ")->fetchAll());
}
// Objet demandé par un lien, absent des listes récentes : ajouté en tête
if ($preCibleType && $preCibleId && !in_array($preCibleId, array_column($optionsCible[$preCibleType] ?? [], 'id'), true)
    && $cibleExiste($preCibleType, $preCibleId)) {
    array_unshift($optionsCible[$preCibleType], ['id' => $preCibleId, 'nom' => $refCible($preCibleType, $preCibleId)]);
}

$typeIcons = [
    'reservation'   => ['fa-calendar-check',  'bg-amber-50 text-amber-600',   'Réservation'],
    'paiement'      => ['fa-cash-register',   'bg-green-50 text-green-600',   'Paiement'],
    'activite'      => ['fa-star',            'bg-purple-50 text-purple-600', 'Activité'],
    'espace'        => ['fa-building',        'bg-blue-50 text-blue-600',     'Espace'],
    'requisition'   => ['fa-landmark',        'bg-amber-50 text-amber-600',   'Réquisition'],
    'remboursement' => ['fa-rotate-left',     'bg-orange-50 text-orange-700', 'Remboursement'],
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
          <?php foreach ($typesVisiblesPourMoi as $k): [$i,$c,$l] = $typeIcons[$k]; ?>
            <option value="<?= $k ?>" <?= $filterType===$k?'selected':''?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
      </div>
    </div>
    <button class="bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    <?php if ($search || $filterType || $preCibleId): ?>
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
            <?php $lienObj = $lienCible($obs['cible_type'], (int)$obs['cible_id']); ?>
            <?php if ($lienObj): ?>
            <a href="<?= e($lienObj) ?>" class="text-[10px] font-black px-2.5 py-1 rounded-full <?= $ocls ?> hover:underline"><?= $olabel ?> · <?= e($refCible($obs['cible_type'], (int)$obs['cible_id'])) ?></a>
            <?php else: ?>
            <span class="text-[10px] font-black px-2.5 py-1 rounded-full <?= $ocls ?>"><?= $olabel ?> · <?= e($refCible($obs['cible_type'], (int)$obs['cible_id'])) ?></span>
            <?php endif; ?>
            <span class="text-xs font-black text-primary"><?= e($obs['auteur_nom']) ?></span>
            <span class="text-[9px] text-slate-400"><?= date('d/m/Y à H:i', strtotime($obs['created_at'])) ?></span>
          </div>
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

        <?php $peutRepondre = is_superadmin() || in_array($role, $regles['repondre'][$obs['cible_type']] ?? [], true) || (int)$obs['auteur_id'] === $moi; ?>
        <?php if ($peutRepondre): ?>
        <button onclick="document.getElementById('replyForm<?= $obs['id'] ?>').classList.toggle('hidden')"
                class="mt-3 text-[11px] font-black text-accent uppercase tracking-widest hover:underline">
          <i class="fas fa-reply mr-1"></i>Répondre
        </button>
        <form method="POST" id="replyForm<?= $obs['id'] ?>" class="hidden mt-3 flex gap-2">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="ajouter">
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
const optionsCible = <?= json_encode($optionsCible, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function onCibleTypeChange(type) {
    const sel  = document.getElementById('cibleSelect');
    sel.innerHTML = '<option value="">— Sélectionner —</option>';
    (optionsCible[type] || []).forEach(item => {
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