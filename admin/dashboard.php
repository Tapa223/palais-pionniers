<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin(); // accepte tous les rôles admin
expirer_reservations_non_payees();

$pdo  = db();
$role = $_SESSION['role'] ?? '';

// ---- Stats globales (filtrées selon rôle) ----
$stats = [];

// Stats espaces/réservations → superadmin + admin_espaces
if (in_array($role, ['superadmin','ministre','admin_espaces'])) {
    $stats['attente']  = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn();
    $stats['validees'] = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'validee'")->fetchColumn();
    $stats['canal_en_ligne'] = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE canal = 'en_ligne'")->fetchColumn();
    $stats['canal_guichet']  = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE canal = 'guichet'")->fetchColumn();
    $stats['espaces']  = (int)$pdo->query("SELECT COUNT(*) FROM espaces WHERE disponible = 1")->fetchColumn();
}

// Stats activités → superadmin + admin_activites
if (in_array($role, ['superadmin','ministre','admin_activites'])) {
    $stats['activites'] = (int)$pdo->query("SELECT COUNT(*) FROM activites")->fetchColumn();
}

// Stats utilisateurs → superadmin uniquement
if (in_array($role, ['superadmin','ministre'])) {
    $stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $stats['admins'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role NOT IN ('user','partenaire')")->fetchColumn();
}

// Stats comptable + ministre
if (in_array($role, ['superadmin','admin_comptable','ministre'])) {
    // Encaissé net = paiements reçus − remboursements effectués
    $stats['paye_brut']  = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements")->fetchColumn();
    $stats['rembourse']  = (float)$pdo->query("SELECT COALESCE(SUM(COALESCE(montant_rembourse, montant_a_rembourser)),0) FROM remboursements WHERE resultat = 'effectue'")->fetchColumn();
    $stats['paye']       = $stats['paye_brut'] - $stats['rembourse'];
    $stats['en_attente_paiement'] = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='validee' AND statut_paiement IN ('non_paye','attente_paiement','partiellement_paye')")->fetchColumn();
    $stats['loyers_encaisses'] = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM bail_paiements")->fetchColumn();
    $stmtLoyersAttente = $pdo->query("
        SELECT COUNT(*) FROM espaces e
        WHERE e.gerant_externe IS NOT NULL AND e.gerant_externe != ''
    ");
    $stats['nb_baux'] = (int)$stmtLoyersAttente->fetchColumn();
}

// Messages non lus → adapté au rôle
$whereMsg = '';
if ($role === 'admin_espaces')   $whereMsg = "AND sujet = 'reservation_espace'";
if ($role === 'admin_activites') $whereMsg = "AND sujet = 'activite'";
$stats['messages_non_lus'] = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE lu = 0 $whereMsg")->fetchColumn();
$stats['messages_total']   = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE 1=1 $whereMsg")->fetchColumn();

// ---- Données récentes selon rôle ----
$recent_reservations = [];
if (in_array($role, ['superadmin','ministre','admin_espaces'])) {
    $recent_reservations = $pdo->query("
        SELECT r.*, u.nom_complet, e.nom AS espace_nom
        FROM reservations r
        JOIN users   u ON u.id = r.user_id
        JOIN espaces e ON e.id = r.espace_id
        ORDER BY r.created_at DESC LIMIT 6
    ")->fetchAll();
}

$recent_activites = [];
if (in_array($role, ['superadmin','ministre','admin_activites'])) {
    $recent_activites = $pdo->query("
        SELECT id, nom, sous_titre, image_principale, created_at
        FROM activites
        ORDER BY created_at DESC LIMIT 5
    ")->fetchAll();
}

$recent_messages = $pdo->query("
    SELECT * FROM messages WHERE 1=1 $whereMsg
    ORDER BY lu ASC, created_at DESC LIMIT 5
")->fetchAll();

$paiements_reduction = [];
$nb_reductions = 0;
if (in_array($role, ['superadmin','ministre','admin_comptable'])) {
    /*
     * Réductions réellement appliquées : nouvelles (reductions_accordees,
     * statut « appliquee ») + anciennes saisies sur un paiement. Une réduction
     * non utilisée ou annulée n'est pas comptée.
     */
    // Les prises en charge suite à réquisition (maintien du tarif) ne sont pas des réductions commerciales
    $filtreCommerciale = reductions_origine_disponible($pdo) ? " AND origine = 'commerciale'" : '';
    $nb_reductions = (int)$pdo->query("SELECT COUNT(*) FROM reductions_accordees WHERE statut = 'appliquee'" . $filtreCommerciale)->fetchColumn()
        + (int)$pdo->query("SELECT COUNT(DISTINCT reservation_id) FROM paiements WHERE motif_reduction IS NOT NULL AND motif_reduction != ''")->fetchColumn();
    $reductionsRecentes = $pdo->query("
        SELECT ra.reservation_id, ra.created_at, ra.motif AS motif_reduction, ra.montant_reduction, e.nom AS espace_nom, u.nom_complet
        FROM reductions_accordees ra
        JOIN reservations r ON r.id = ra.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE ra.statut = 'appliquee'" . str_replace('origine', 'ra.origine', $filtreCommerciale) . "
        ORDER BY ra.created_at DESC LIMIT 6
    ")->fetchAll();
    $anciennes = $pdo->query("
        SELECT p.reservation_id, MAX(p.created_at) AS created_at, MAX(p.motif_reduction) AS motif_reduction, e.nom AS espace_nom, u.nom_complet
        FROM paiements p
        JOIN reservations r ON r.id = p.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE p.motif_reduction IS NOT NULL AND p.motif_reduction != ''
        GROUP BY p.reservation_id, e.nom, u.nom_complet
        ORDER BY created_at DESC LIMIT 6
    ")->fetchAll();
    foreach ($anciennes as $anc) {
        $sAnc = situation_financiere_reservation($pdo, (int)$anc['reservation_id']);
        $anc['montant_reduction'] = $sAnc['reduction_historique'] ?? 0;
        $reductionsRecentes[] = $anc;
    }
    usort($reductionsRecentes, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    $paiements_reduction = array_slice($reductionsRecentes, 0, 6);
}

$baux_apercu = [];
if (in_array($role, ['superadmin','ministre','admin_comptable'])) {
    $espacesBailDash = $pdo->query("
        SELECT id, nom, type_bail FROM espaces
        WHERE gerant_externe IS NOT NULL AND gerant_externe != ''
        ORDER BY nom ASC
    ")->fetchAll();
    $stmtPeriodeDash = $pdo->prepare("SELECT * FROM bail_paiements WHERE espace_id = ? AND periode_debut = ?");
    foreach ($espacesBailDash as $eb) {
        $typeB = $eb['type_bail'] ?: 'mensuel';
        $debutPeriode = periode_actuelle_debut($typeB);
        $stmtPeriodeDash->execute([$eb['id'], $debutPeriode]);
        $paye = $stmtPeriodeDash->fetch();
        $baux_apercu[] = ['nom' => $eb['nom'], 'type' => $typeB, 'paye' => (bool)$paye];
    }
}

$badge = [
    'en_attente' => ['En attente', 'bg-amber-100   text-amber-800'],
    'validee'    => ['Validée',    'bg-emerald-100 text-emerald-800'],
    'refusee'    => ['Refusée',    'bg-red-100     text-red-800'],
    'annulee'    => ['Annulée',    'bg-slate-100   text-slate-700'],
];

$pageTitle = "Tableau de bord";
require __DIR__ . '/_admin_header.php';
?>

<!-- En-tête -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Tableau de bord</h1>
    <p class="text-sm text-slate-500 mt-0.5">
      Bienvenue, <strong><?= e(explode(' ', $_SESSION['nom_complet'])[0]) ?></strong>
    </p>
  </div>
  <?php if ($role === 'superadmin'): ?>
  <a href="users.php"
     class="hidden md:flex items-center gap-2 bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
    <i class="fas fa-user-cog"></i> Gérer les rôles
  </a>
  <?php endif; ?>
</div>

<!-- ===== CARTES STATS ===== -->
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
<?php
if ($role === 'admin_comptable' || is_superadmin()) {
    $debutSemaine = date('Y-m-d', strtotime('monday this week'));
    $finSemaine   = date('Y-m-d', strtotime('sunday this week'));
    $lienRapportHebdo = "rapport.php?type=encaisse&debut=$debutSemaine&fin=$finSemaine";
    $stmtRappelExiste = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE lien = ?");
    $stmtRappelExiste->execute([$lienRapportHebdo]);
    if ((int)$stmtRappelExiste->fetchColumn() === 0) {
        notify('admin_comptable', 'rapport_hebdo', "Le rapport hebdomadaire des paiements est disponible pour la semaine du " . date('d/m', strtotime($debutSemaine)) . " au " . date('d/m', strtotime($finSemaine)) . ".", $lienRapportHebdo);
    }
}

$cards = [];
$cards[] = ['Notifications', count_notifications(), 'fa-bell', 'text-amber-500', 'bg-amber-50', 'notifications.php'];

if ($role === 'admin_comptable' || is_superadmin()) {
    $nbAcomptesEnCours = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'validee' AND statut_paiement = 'partiellement_paye'")->fetchColumn();
    $cards[] = ['Acomptes en cours', $nbAcomptesEnCours, 'fa-coins', 'text-sky-600', 'bg-sky-50', 'acomptes.php'];
}

if (isset($stats['attente']))
    $cards[] = ['Demandes en attente',  $stats['attente'],           'fa-clock',         'text-amber-500',   'bg-amber-50',    'reservations.php?statut=en_attente'];
if (isset($stats['validees']))
    $cards[] = ['Réservations validées', $stats['validees'],         'fa-check-circle',  'text-emerald-500', 'bg-emerald-50',  'reservations.php?statut=validee'];
if (isset($stats['canal_en_ligne']))
    $cards[] = ['Réservations en ligne', $stats['canal_en_ligne'],   'fa-globe',         'text-sky-600',     'bg-sky-50',      'reservations.php?canal=en_ligne'];
if (isset($stats['canal_guichet']))
    $cards[] = ['Réservations au guichet', $stats['canal_guichet'],  'fa-store',         'text-orange-600',  'bg-orange-50',   'rapport.php?type=guichet'];
if (isset($stats['espaces']))
    $cards[] = ['Espaces disponibles',   $stats['espaces'],          'fa-building',      'text-blue-600',    'bg-blue-50',     'rapport.php?type=espaces'];
if (isset($stats['activites']))
    $cards[] = ['Activités',             $stats['activites'],        'fa-star',          'text-purple-600',  'bg-purple-50',   'activites.php'];
if (isset($stats['users']))
    $cards[] = ['Utilisateurs',          $stats['users'],            'fa-users',         'text-slate-600',   'bg-slate-100',   'users.php'];
if (isset($stats['admins']))
    $cards[] = ['Administrateurs',       $stats['admins'],           'fa-user-shield',   'text-primary',     'bg-primary/5',   'users.php'];
if (isset($stats['paye']))
    $cards[] = ['Total encaissé net (FCFA)', number_format($stats['paye'],0,',',' '), 'fa-cash-register','text-teal-600','bg-teal-50','rapport.php?type=encaisse'];
if (isset($stats['en_attente_paiement']))
    $cards[] = ['Paiements en attente',  $stats['en_attente_paiement'],'fa-clock',       'text-amber-600',   'bg-amber-50',    'paiements.php#a-encaisser'];
if (!empty($nb_reductions))
    $cards[] = ['Réductions appliquées', $nb_reductions,          'fa-tags',          'text-orange-600',  'bg-orange-50',   'rapport.php?type=reductions'];
if (isset($stats['loyers_encaisses']))
    $cards[] = ['Loyers encaissés (FCFA)', number_format($stats['loyers_encaisses'],0,',',' '), 'fa-file-signature','text-indigo-600','bg-indigo-50','baux.php'];
if (isset($stats['nb_baux']))
    $cards[] = ['Espaces en bail',        $stats['nb_baux'],           'fa-handshake',     'text-purple-600',  'bg-purple-50',   'baux.php'];

$peutVoirMessages = in_array($role, ['superadmin','ministre','admin_messages','admin_espaces','admin_activites'], true);
if ($peutVoirMessages) {
    $cards[] = ['Messages non lus',  $stats['messages_non_lus'], 'fa-envelope',      'text-green-600',   'bg-green-50',    'messages.php'];
    $cards[] = ['Messages total',    $stats['messages_total'],   'fa-inbox',         'text-slate-500',   'bg-slate-100',   'messages.php'];
}

foreach ($cards as [$label, $val, $icon, $color, $bg, $link]):
?>
  <a href="<?= $link ?>"
     class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 hover:shadow-md transition-all group">
    <div class="flex items-center justify-between mb-3">
      <div class="w-10 h-10 <?= $bg ?> rounded-xl flex items-center justify-center">
        <i class="fas <?= $icon ?> <?= $color ?>"></i>
      </div>
      <i class="fas fa-arrow-right text-slate-200 text-xs group-hover:text-primary group-hover:translate-x-0.5 transition-all"></i>
    </div>
    <div class="text-2xl font-black <?= $color ?>"><?= e((string)$val) ?></div>
    <div class="text-xs font-semibold text-slate-500 mt-1 leading-tight"><?= $label ?></div>
  </a>
<?php endforeach; ?>
</div>

<div class="grid xl:grid-cols-2 gap-6">

  <!-- ===== RÉSERVATIONS RÉCENTES ===== -->
  <?php if (!empty($recent_reservations)): ?>
  <div class="xl:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-calendar-check text-accent"></i> Demandes récentes
      </h2>
      <a href="reservations.php" class="text-xs font-black text-accent hover:underline">Voir tout →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm min-w-[560px]">
        <thead class="bg-slate-50 text-[10px] uppercase tracking-widest text-slate-400 text-left">
          <tr>
            <th class="px-5 py-3">Demandeur</th>
            <th class="px-5 py-3">Espace</th>
            <th class="px-5 py-3">Date</th>
            <th class="px-5 py-3">Horaires</th>
            <th class="px-5 py-3">Statut</th>
            <th class="px-5 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <?php foreach ($recent_reservations as $r):
            [$lib, $cls] = $badge[$r['statut']] ?? ['—', 'bg-slate-100 text-slate-500'];
          ?>
          <tr class="hover:bg-slate-50 transition">
            <td class="px-5 py-3 font-bold text-slate-800 whitespace-nowrap"><?= e($r['nom_complet']) ?></td>
            <td class="px-5 py-3 text-slate-600"><?= e($r['espace_nom']) ?></td>
            <td class="px-5 py-3 text-slate-600 whitespace-nowrap"><?= date('d/m/Y', strtotime($r['date_resa'])) ?></td>
            <td class="px-5 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">
              <?= substr($r['heure_debut'],0,5) ?> → <?= substr($r['heure_fin'],0,5) ?>
            </td>
            <td class="px-5 py-3">
              <span class="px-2.5 py-1 rounded-full text-xs font-black <?= $cls ?>"><?= $lib ?></span>
            </td>
            <td class="px-5 py-3 whitespace-nowrap">
              <a href="reservations.php?id=<?= $r['id'] ?>"
                 class="text-xs font-black text-primary hover:text-accent transition">Voir →</a>
              <a href="observations.php?cible_type=reservation&cible_id=<?= $r['id'] ?>"
                 class="text-xs font-black text-slate-400 hover:text-accent transition ml-3"><i class="fas fa-eye mr-1"></i>Observer</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== RÉSERVATIONS AVEC RÉDUCTION ===== -->
  <?php if (!empty($paiements_reduction)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-tags text-accent"></i> Réservations avec réduction
      </h2>
      <a href="rapport.php?type=reductions" class="text-xs font-black text-accent hover:underline">Voir tout →</a>
    </div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($paiements_reduction as $pr): ?>
      <a href="paiements.php?resa=<?= (int)$pr['reservation_id'] ?>" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50 transition">
        <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
          <i class="fas fa-tags text-amber-500 text-sm"></i>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-slate-800 text-sm truncate"><?= e($pr['espace_nom']) ?> — <?= e($pr['nom_complet']) ?></p>
          <p class="text-xs text-slate-400 truncate"><?= e($pr['motif_reduction']) ?></p>
        </div>
        <span class="text-xs font-black text-amber-600 flex-shrink-0">− <?= number_format((float)$pr['montant_reduction'],0,',',' ') ?> FCFA</span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== APERÇU DES BAUX ===== -->
  <?php if (!empty($baux_apercu)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-file-signature text-accent"></i> Baux — période en cours
      </h2>
      <a href="baux.php" class="text-xs font-black text-accent hover:underline">Voir tout →</a>
    </div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($baux_apercu as $b): ?>
      <a href="baux.php" class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-slate-50 transition">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-9 h-9 rounded-xl <?= $b['paye'] ? 'bg-emerald-50' : 'bg-amber-50' ?> flex items-center justify-center flex-shrink-0">
            <i class="fas <?= $b['paye'] ? 'fa-check text-emerald-500' : 'fa-clock text-amber-500' ?> text-sm"></i>
          </div>
          <p class="font-bold text-slate-800 text-sm truncate"><?= e($b['nom']) ?></p>
        </div>
        <span class="text-[10px] font-black px-2.5 py-1 rounded-full flex-shrink-0 <?= $b['paye'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>"><?= $b['paye'] ? 'Payé' : 'En attente' ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== ACTIVITÉS RÉCENTES ===== -->
  <?php if (!empty($recent_activites)): ?>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-star text-accent"></i> Activités
      </h2>
      <a href="activites.php" class="text-xs font-black text-accent hover:underline">Gérer →</a>
    </div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($recent_activites as $a): ?>
      <div class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50 transition">
        <?php if ($a['image_principale']): ?>
          <img src="../uploads/activites/<?= e($a['image_principale']) ?>"
               class="w-10 h-10 rounded-xl object-cover flex-shrink-0" alt="">
        <?php else: ?>
          <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fas fa-star text-purple-400 text-sm"></i>
          </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-slate-800 text-sm truncate"><?= e($a['nom']) ?></p>
          <?php if ($a['sous_titre']): ?>
            <p class="text-xs text-slate-400 truncate mt-0.5"><?= e($a['sous_titre']) ?></p>
          <?php endif; ?>
        </div>
        <a href="activites.php" class="text-xs font-black text-primary hover:text-accent flex-shrink-0">Modifier →</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== MESSAGES RÉCENTS ===== -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden <?= empty($recent_activites) ? 'xl:col-span-2' : '' ?>">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
      <h2 class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-envelope text-accent"></i> Messages récents
        <?php if ($stats['messages_non_lus'] > 0): ?>
          <span class="bg-accent text-white text-[10px] font-black px-2 py-0.5 rounded-full">
            <?= $stats['messages_non_lus'] ?>
          </span>
        <?php endif; ?>
      </h2>
      <a href="messages.php" class="text-xs font-black text-accent hover:underline">Boîte de réception →</a>
    </div>

    <?php if (empty($recent_messages)): ?>
      <div class="py-12 text-center">
        <i class="fas fa-inbox text-3xl text-slate-200 mb-2"></i>
        <p class="text-sm text-slate-400 italic">Aucun message pour l'instant.</p>
      </div>
    <?php else: ?>
    <div class="divide-y divide-slate-50">
      <?php
      $sujetLabels = [
        'reservation_espace'   => ['Espace',       'bg-blue-100 text-blue-700'],
        'activite'             => ['Activité',      'bg-purple-100 text-purple-700'],
        'information_generale' => ['Info',          'bg-slate-100 text-slate-600'],
        'reclamation'          => ['Réclamation',   'bg-red-100 text-red-700'],
        'autre'                => ['Autre',         'bg-slate-100 text-slate-500'],
      ];
      foreach ($recent_messages as $msg):
        [$sl, $sc] = $sujetLabels[$msg['sujet']] ?? ['—', 'bg-slate-100 text-slate-400'];
      ?>
      <a href="messages.php?id=<?= $msg['id'] ?>"
         class="flex items-start gap-3 px-5 py-3 hover:bg-slate-50 transition <?= !$msg['lu'] ? 'bg-blue-50/30' : '' ?>">
        <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center font-black text-white text-xs flex-shrink-0 mt-0.5">
          <?= strtoupper(substr($msg['nom'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <p class="font-<?= !$msg['lu']?'black':'semibold' ?> text-slate-800 text-sm truncate"><?= e($msg['nom']) ?></p>
            <?php if (!$msg['lu']): ?><span class="w-2 h-2 bg-accent rounded-full flex-shrink-0"></span><?php endif; ?>
            <span class="text-[9px] font-black px-1.5 py-0.5 rounded-full ml-auto flex-shrink-0 <?= $sc ?>"><?= $sl ?></span>
          </div>
          <p class="text-xs text-slate-500 truncate mt-0.5"><?= e(substr($msg['message'], 0, 70)) ?>...</p>
          <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>


<?php if ($role === 'superadmin'): ?>
<div class="mt-5">
  <a href="activity.php"
     class="flex items-center justify-between bg-slate-900 text-white rounded-2xl px-6 py-4 hover:bg-slate-800 transition group shadow-sm">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 bg-accent/20 rounded-xl flex items-center justify-center">
        <i class="fas fa-history text-accent"></i>
      </div>
      <div>
        <p class="font-black text-sm uppercase italic tracking-tight">Journal d'activité</p>
        <p class="text-[10px] text-white/50 mt-0.5">Voir toutes les actions des administrateurs</p>
      </div>
    </div>
    <i class="fas fa-arrow-right text-white/30 group-hover:text-accent group-hover:translate-x-1 transition-all"></i>
  </a>
</div>
<?php endif; ?>
</div><!-- /grid -->

<?php require __DIR__ . '/_admin_footer.php'; ?>