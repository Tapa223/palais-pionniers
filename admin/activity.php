<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','superadmin']);

$pdo = db();

// ---- Filtres ----
$filterModule = $_GET['module'] ?? '';
$filterUser   = $_GET['user']   ?? '';
$search       = trim($_GET['q'] ?? '');
$dateFrom     = $_GET['from']   ?? '';
$dateTo       = $_GET['to']     ?? '';
$page         = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 20;

$modules = ['espaces','activites','reservations','messages','users'];

$moduleIcons = [
    'espaces'      => ['fa-building',       'bg-blue-50 text-blue-600',   'border-blue-200'],
    'activites'    => ['fa-star',           'bg-purple-50 text-purple-600','border-purple-200'],
    'reservations' => ['fa-calendar-check', 'bg-amber-50 text-amber-600', 'border-amber-200'],
    'messages'     => ['fa-envelope',       'bg-green-50 text-green-600', 'border-green-200'],
    'users'        => ['fa-users',          'bg-slate-100 text-slate-600','border-slate-200'],
];

$actionLabels = [
    // Espaces
    'espace_cree'              => ['Espace créé',              'fa-landmark'],
    'espace_modifie'           => ['Espace modifié',           'fa-pen'],
    'espace_supprime'          => ['Espace supprimé',          'fa-trash'],
    'image_supprimee'          => ['Image supprimée',          'fa-image'],
    // Réservations
    'reservation_validee'      => ['Réservation validée',      'fa-check-circle'],
    'reservation_refusee'      => ['Réservation refusée',      'fa-times-circle'],
    'reservation_en_attente'   => ['Remise en attente',        'fa-rotate'],
    // Activités
    'activite_creee'           => ['Activité créée',           'fa-star'],
    'activite_modifiee'        => ['Activité modifiée',        'fa-pen'],
    'activite_supprimee'       => ['Activité supprimée',       'fa-trash'],
    // Utilisateurs
    'user_cree'                => ['Compte créé',              'fa-user'],
    'user_role_change'         => ['Rôle modifié',             'fa-key'],
    'user_bloque'              => ['Compte bloqué',            'fa-ban'],
    'user_debloque'            => ['Compte débloqué',          'fa-check-circle'],
    'user_supprime'            => ['Compte supprimé',          'fa-trash'],
    // Messages
    'message_lu'               => ['Message lu',               'fa-envelope-open'],
    'message_supprime'         => ['Message supprimé',         'fa-trash'],
    // Paiements
    'paiement_enregistre'      => ['Paiement enregistré',      'fa-money-bill'],
    // Guichet
    'resa_guichet'             => ['Réservation guichet',       'fa-store'],
    // Observations
    'observation_ajoutee'      => ['Observation ajoutée',      'fa-eye'],
];

$roleMini = [
    'superadmin'      => ['Super Admin',     'bg-accent text-white'],
    'admin_espaces'   => ['Admin Espaces',   'bg-blue-100 text-blue-700'],
    'admin_activites' => ['Admin Activités', 'bg-purple-100 text-purple-700'],
    'admin_messages'  => ['Admin Messages',  'bg-green-100 text-green-700'],
    'user'            => ['Utilisateur',     'bg-slate-100 text-slate-500'],
];

// ---- Construction requête ----
$where   = [];
$params  = [];

if ($filterModule && in_array($filterModule, $modules)) {
    $where[] = 'l.module = ?';
    $params[] = $filterModule;
}
if ($filterUser) {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$filterUser;
}
if ($search) {
    $where[] = '(l.user_nom LIKE ? OR l.details LIKE ? OR l.action LIKE ?)';
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($dateFrom) {
    $where[] = 'DATE(l.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = 'DATE(l.created_at) <= ?';
    $params[] = $dateTo;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log l $whereSQL");
$totalStmt->execute($params);
$total     = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Logs paginés
$stmt = $pdo->prepare("
    SELECT l.*, u.role AS user_role
    FROM activity_log l
    LEFT JOIN users u ON u.id = l.user_id
    $whereSQL
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Stats par module
$statsModules = [];
foreach ($modules as $mod) {
    $statsModules[$mod] = (int)$pdo->query("SELECT COUNT(*) FROM activity_log WHERE module='$mod'")->fetchColumn();
}
$totalLogs = array_sum($statsModules);

// Admins pour filtre
$admins = $pdo->query("SELECT id, nom_complet, role FROM users WHERE role NOT IN ('user','partenaire') ORDER BY nom_complet")->fetchAll();

// Détail d'une action
$detailId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detailLog = null;
if ($detailId) {
    $s = $pdo->prepare("SELECT l.*, u.role AS user_role, u.email AS user_email FROM activity_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.id=?");
    $s->execute([$detailId]);
    $detailLog = $s->fetch();
}

$pageTitle = "Journal d'activité";
require __DIR__ . '/_admin_header.php';
?>

<!-- En-tête -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight flex items-center gap-2">
      <i class="fas fa-history text-accent"></i> Journal d'activité
    </h1>
    <p class="text-sm text-slate-500 mt-0.5"><?= number_format($total) ?> action(s) enregistrée(s)</p>
  </div>
  <?php if ($total > 0): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['export'=>1])) ?>"
     class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
    <i class="fas fa-file-excel text-accent"></i> Exporter en Excel
  </a>
  <?php endif; ?>
</div>

<!-- ---- Stats par module ---- -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
  <?php foreach ($modules as $mod):
    [$icon,$cls,$border] = $moduleIcons[$mod];
    $pct = $totalLogs > 0 ? round($statsModules[$mod]/$totalLogs*100) : 0;
  ?>
  <a href="?module=<?= $mod ?>"
     class="bg-white rounded-2xl border-2 p-4 hover:shadow-md transition group <?= $filterModule===$mod ? 'border-primary shadow-sm' : 'border-slate-100' ?>">
    <div class="flex items-center justify-between mb-2">
      <div class="w-8 h-8 <?= $cls ?> rounded-xl flex items-center justify-center">
        <i class="fas <?= $icon ?> text-xs"></i>
      </div>
      <?php if ($filterModule===$mod): ?>
        <i class="fas fa-times text-[10px] text-slate-400 hover:text-accent"></i>
      <?php endif; ?>
    </div>
    <p class="text-xl font-black text-primary"><?= $statsModules[$mod] ?></p>
    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide capitalize mt-0.5"><?= $mod ?></p>
    <!-- Barre de proportion -->
    <div class="mt-2 h-1 bg-slate-100 rounded-full overflow-hidden">
      <div class="h-full bg-accent rounded-full" style="width:<?= $pct ?>%"></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- ---- Filtres ---- -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <?php if ($filterModule): ?>
      <input type="hidden" name="module" value="<?= e($filterModule) ?>">
    <?php endif; ?>

    <div class="flex-1 min-w-[160px]">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Rechercher</label>
      <div class="relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Nom, action, détails..."
               class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-primary outline-none focus:border-primary">
      </div>
    </div>

    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Administrateur</label>
      <div class="relative">
        <select name="user" class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none pr-8 appearance-none min-w-[150px]">
          <option value="">Tous</option>
          <?php foreach ($admins as $adm): ?>
            <option value="<?= $adm['id'] ?>" <?= $filterUser==(string)$adm['id']?'selected':''?>>
              <?= e($adm['nom_complet']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
      </div>
    </div>

    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Du</label>
      <input type="date" name="from" value="<?= e($dateFrom) ?>"
             class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none focus:border-primary">
    </div>

    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Au</label>
      <input type="date" name="to" value="<?= e($dateTo) ?>"
             class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none focus:border-primary">
    </div>

    <button class="bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    <?php if ($search || $filterUser || $dateFrom || $dateTo || $filterModule): ?>
    <a href="activity.php" class="text-xs font-black text-slate-400 hover:text-primary px-3 py-2.5 rounded-xl border border-slate-200 transition">
      <i class="fas fa-times mr-1"></i> Réinitialiser
    </a>
    <?php endif; ?>
  </form>
</div>

<!-- ---- Layout : liste + détail ---- -->
<div class="grid lg:grid-cols-5 gap-5">

  <!-- Liste logs -->
  <div class="lg:col-span-3">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">

      <?php if (empty($logs)): ?>
        <div class="py-16 text-center">
          <i class="fas fa-history text-4xl text-slate-200 mb-3"></i>
          <p class="text-slate-400 font-semibold">Aucune activité enregistrée.</p>
        </div>
      <?php else: ?>
      <div class="divide-y divide-slate-50">
        <?php foreach ($logs as $log):
          [$icon,$cls,$border] = $moduleIcons[$log['module']] ?? ['fa-circle','bg-slate-100 text-slate-400','border-slate-200'];
          [$label,$emoji] = $actionLabels[$log['action']] ?? [ucfirst(str_replace('_',' ',$log['action'])),'fa-cog'];
          [$rl,$rc] = $roleMini[$log['user_role']] ?? ['?','bg-slate-100 text-slate-400'];
          $isOpen = ($detailLog && $detailLog['id'] == $log['id']);
        ?>
        <a href="activity.php?id=<?= $log['id'] ?>&<?= http_build_query(array_diff_key($_GET,['id'=>1,'p'=>1])) ?>"
           class="flex items-start gap-4 px-5 py-3.5 hover:bg-slate-50 transition <?= $isOpen ? 'bg-primary/5 border-l-4 border-primary' : '' ?>">
          <!-- Icône module -->
          <div class="w-9 h-9 <?= $cls ?> rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 border <?= $border ?>">
            <i class="fas <?= $icon ?> text-xs"></i>
          </div>
          <!-- Contenu -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-black text-primary"><i class="fas <?= $emoji ?> text-accent mr-1"></i> <?= $label ?></span>
              <span class="text-[9px] font-black px-2 py-0.5 rounded-full <?= $rc ?>"><?= $rl ?></span>
            </div>
            <p class="text-xs font-semibold text-slate-600 mt-0.5"><?= e($log['user_nom']) ?></p>
            <?php if ($log['details']): ?>
              <p class="text-xs text-slate-400 mt-0.5 truncate"><?= e($log['details']) ?></p>
            <?php endif; ?>
          </div>
          <!-- Date -->
          <div class="text-right flex-shrink-0">
            <p class="text-[10px] font-black text-slate-500"><?= date('d/m/Y', strtotime($log['created_at'])) ?></p>
            <p class="text-[10px] text-slate-400"><?= date('H:i:s', strtotime($log['created_at'])) ?></p>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="flex items-center justify-between px-5 py-4 border-t border-slate-100 bg-slate-50">
        <p class="text-xs text-slate-400">
          Page <strong><?= $page ?></strong> / <?= $totalPages ?>
          · <?= $total ?> entrée(s)
        </p>
        <div class="flex gap-2">
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['p'=>$page-1])) ?>"
               class="flex items-center gap-1 text-xs font-black text-primary border border-slate-200 hover:border-primary px-3 py-2 rounded-xl transition">
              <i class="fas fa-chevron-left text-[10px]"></i> Précédent
            </a>
          <?php endif; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET,['p'=>$page+1])) ?>"
               class="flex items-center gap-1 text-xs font-black text-primary border border-slate-200 hover:border-primary px-3 py-2 rounded-xl transition">
              Suivant <i class="fas fa-chevron-right text-[10px]"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Détail action -->
  <div class="lg:col-span-2">
    <?php if ($detailLog):
      [$icon,$cls,$border] = $moduleIcons[$detailLog['module']] ?? ['fa-circle','bg-slate-100 text-slate-400','border-slate-200'];
      [$label,$emoji] = $actionLabels[$detailLog['action']] ?? [ucfirst(str_replace('_',' ',$detailLog['action'])),'fa-cog'];
      [$rl,$rc] = $roleMini[$detailLog['user_role']] ?? ['?','bg-slate-100 text-slate-400'];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden sticky top-24">

      <!-- Header détail -->
      <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 <?= $cls ?> rounded-xl flex items-center justify-center border <?= $border ?> flex-shrink-0">
            <i class="fas <?= $icon ?>"></i>
          </div>
          <div>
            <p class="font-black text-primary text-sm"><i class="fas <?= $emoji ?> text-accent mr-1"></i> <?= $label ?></p>
            <p class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide font-bold"><?= $detailLog['module'] ?></p>
          </div>
        </div>
        <a href="activity.php?<?= http_build_query(array_diff_key($_GET,['id'=>1])) ?>"
           class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent transition flex-shrink-0">
          <i class="fas fa-times text-xs"></i>
        </a>
      </div>

      <!-- Corps détail -->
      <div class="p-6 space-y-5">

        <!-- Qui -->
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 flex items-center gap-1.5">
            <i class="fas fa-user text-accent text-[9px]"></i> Effectué par
          </p>
          <div class="flex items-center gap-3 bg-slate-50 rounded-xl p-3 border border-slate-100">
            <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center font-black text-white text-sm flex-shrink-0">
              <?= strtoupper(substr($detailLog['user_nom'],0,1)) ?>
            </div>
            <div>
              <p class="font-black text-primary text-sm"><?= e($detailLog['user_nom']) ?></p>
              <p class="text-xs text-slate-500"><?= e($detailLog['user_email'] ?? '') ?></p>
              <span class="inline-block mt-1 text-[9px] font-black px-2 py-0.5 rounded-full <?= $rc ?>"><?= $rl ?></span>
            </div>
          </div>
        </div>

        <!-- Quand -->
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 flex items-center gap-1.5">
            <i class="fas fa-clock text-accent text-[9px]"></i> Date & heure
          </p>
          <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
            <p class="font-black text-primary text-sm">
              <?= date('l d F Y', strtotime($detailLog['created_at'])) ?>
            </p>
            <p class="text-sm text-slate-500 mt-0.5">à <?= date('H:i:s', strtotime($detailLog['created_at'])) ?></p>
          </div>
        </div>

        <!-- Détails -->
        <?php if ($detailLog['details']): ?>
        <div>
          <p class="text-[10px] font-black uppercase tracking-widests text-slate-400 mb-2 flex items-center gap-1.5">
            <i class="fas fa-info-circle text-accent text-[9px]"></i> Détails
          </p>
          <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
            <p class="text-sm text-slate-700 font-semibold leading-relaxed"><?= e($detailLog['details']) ?></p>
          </div>
        </div>
        <?php endif; ?>

        <!-- Action technique -->
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 flex items-center gap-1.5">
            <i class="fas fa-code text-accent text-[9px]"></i> Code action
          </p>
          <code class="block bg-slate-900 text-green-400 text-xs font-mono px-4 py-3 rounded-xl">
            <?= e($detailLog['action']) ?>
          </code>
        </div>

        <!-- ID -->
        <p class="text-[9px] text-slate-300 text-center">Entrée #<?= $detailLog['id'] ?></p>
      </div>
    </div>

    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 h-full flex flex-col items-center justify-center py-20 text-center">
      <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100">
        <i class="fas fa-hand-pointer text-2xl text-slate-300"></i>
      </div>
      <p class="font-black text-slate-400 text-sm uppercase tracking-wide">Sélectionnez une action</p>
      <p class="text-xs text-slate-300 mt-1">Cliquez sur une ligne pour voir les détails</p>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>