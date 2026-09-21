<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_comptable','ministre']);
expirer_reservations_non_payees();

$pdo      = db();
$role     = $_SESSION['role'] ?? '';
$readonly = is_readonly_admin() || is_superadmin();
$msg      = null;

// ============================================================
// ACTIONS (comptable uniquement)
// ============================================================
if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err','Requête invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $resaId = (int)($_POST['reservation_id'] ?? 0);

        // ---- Enregistrer un paiement ----
        if ($action === 'enregistrer_paiement' && $resaId) {
            $montant          = (float)($_POST['montant'] ?? 0);
            $montantReference = !empty($_POST['montant_reference']) ? (float)$_POST['montant_reference'] : null;
            $motifReduction   = trim($_POST['motif_reduction'] ?? '');
            $estAcompte       = !empty($_POST['est_acompte']);
            $mode    = $_POST['mode']      ?? '';
            $ref     = trim($_POST['reference'] ?? '');
            $note    = trim($_POST['note']      ?? '');

            $modesValides = ['especes','orange_money','moov_money','virement','cheque'];
            $estReduction = (!$estAcompte && $montantReference !== null && $montant < $montantReference);

            if ($montant <= 0 || !in_array($mode, $modesValides)) {
                $msg = ['err','Montant ou mode de paiement invalide.'];
            } elseif ($estReduction && $motifReduction === '') {
                $msg = ['err','Une réduction a été détectée : merci d\'indiquer le motif de la réduction accordée, ou cochez "Acompte" si le solde sera réglé plus tard.'];
            } else {
                // Enregistrer le paiement
                $pdo->prepare("
                    INSERT INTO paiements (reservation_id, montant, montant_reference, motif_reduction, mode, reference, note, enregistre_par)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([$resaId, $montant, $montantReference, $estReduction ? $motifReduction : null, $mode, $ref, $note, $_SESSION['user_id']]);
                $paiementId = (int)$pdo->lastInsertId();
                $numeroRecu = ref_recu($paiementId);

                // Total déjà versé sur cette réservation (tous paiements confondus)
                $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE reservation_id = ?");
                $stmtTotal->execute([$resaId]);
                $totalVerse = (float)$stmtTotal->fetchColumn();

                // Statut : payé seulement si le total versé couvre le montant attendu (ou pas de référence connue)
                $nouveauStatut = ($montantReference !== null && $totalVerse < $montantReference) ? 'partiellement_paye' : 'paye';

                // Date limite du solde : enregistrée seulement si c'est un acompte,
                // effacée dès que le solde est intégralement réglé.
                $dateLimiteSolde = ($nouveauStatut === 'partiellement_paye' && $estAcompte && !empty($_POST['date_limite_solde']))
                    ? $_POST['date_limite_solde'] : null;

                $pdo->prepare("
                    UPDATE reservations SET statut_paiement=?, date_limite_solde=?, paiement_notifie=0 WHERE id=?
                ")->execute([$nouveauStatut, $dateLimiteSolde, $resaId]);

                // Récupérer infos réservation pour notification
                $resa = $pdo->prepare("SELECT r.*, e.nom AS espace_nom, u.nom_complet FROM reservations r JOIN espaces e ON e.id=r.espace_id JOIN users u ON u.id=r.user_id WHERE r.id=?");
                $resa->execute([$resaId]);
                $resa = $resa->fetch();

                $soldeRestant = $montantReference !== null ? max(0, $montantReference - $totalVerse) : 0;
                $suffixeAcompte = $nouveauStatut === 'partiellement_paye'
                    ? ' — ACOMPTE reçu, solde restant : ' . number_format($soldeRestant,0,',',' ') . ' FCFA'
                    : '';

                // Notifier admin_espaces
                notify('admin_espaces','paiement_recu',
                    "Paiement $numeroRecu reçu pour «{$resa['espace_nom']}» le ".date('d/m/Y',strtotime($resa['date_resa'])).' — '.$resa['nom_complet'].$suffixeAcompte,
                    "reservations.php?id=$resaId"
                );

                // Notifier superadmin
                notify('superadmin','paiement_recu',
                    "Paiement $numeroRecu de ".number_format($montant,0,',',' ')." FCFA enregistré — Réservation #{$resaId}".$suffixeAcompte,
                    "paiements.php?id=$resaId"
                );

                // Notifier le client : paiement confirmé, reçu disponible
                notify('', 'paiement_confirme',
                    "Paiement confirmé ($numeroRecu) pour « {$resa['espace_nom']} » — votre reçu est disponible.",
                    "generer_bon.php?id=$resaId", (int)$resa['user_id']
                );

                // Réduction accordée : traçabilité renforcée — visible pour la supervision (Admin DG + Ministre)
                if ($estReduction) {
                    $ecart = $montantReference - $montant;
                    $texteReduction = "Réduction accordée sur le paiement $numeroRecu : -".number_format($ecart,0,',',' ')." FCFA (tarif ".number_format($montantReference,0,',',' ')." → ".number_format($montant,0,',',' ')." FCFA). Motif : $motifReduction";
                    notify('superadmin', 'reduction_accordee', $texteReduction, "paiements.php?id=$resaId");
                    notify('ministre', 'reduction_accordee', $texteReduction, "paiements.php?id=$resaId");
                    log_activity('reduction_accordee', 'reservations', $texteReduction);
                }

                log_activity('paiement_enregistre','reservations',"Paiement $numeroRecu — ".number_format($montant,0,',',' ')." FCFA pour réservation #$resaId ($mode)");

                // Ce paiement départage les éventuelles demandes concurrentes sur le même créneau
                $annulees = annuler_reservations_concurrentes($pdo, $resaId);

                $msg = ['ok',"Paiement $numeroRecu enregistré".($estReduction ? ' (avec réduction — Admin DG et Ministre notifiés)' : '').
                    ($annulees ? ' — '.count($annulees).' demande(s) concurrente(s) sur ce créneau ont été annulées et leurs clients notifiés.' : ' et admin_espaces notifié.')];
            }
        }
    }
}

// ============================================================
// FILTRES
// ============================================================
$filterMode   = $_GET['mode']   ?? '';
$filterStatut = $_GET['statut'] ?? '';
$search       = trim($_GET['q'] ?? '');
$openId       = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$where  = [];
$params = [];

if ($filterMode) { $where[]='p.mode=?'; $params[]=$filterMode; }
if ($search) {
    $where[]='(u.nom_complet LIKE ? OR e.nom LIKE ? OR p.reference LIKE ? OR CONCAT(RIGHT(YEAR(p.created_at),2), "-", LPAD(p.id,3,"0"), "/DGPP-C") LIKE ?)';
    $params=array_merge($params,["%$search%","%$search%","%$search%","%$search%"]);
}

// Réservations avec paiements
$paiementsQuery = $pdo->prepare("
    SELECT p.*, r.date_resa, r.heure_debut, r.heure_fin, r.statut, r.statut_paiement,
           e.nom AS espace_nom, u.nom_complet, u.email, u.telephone,
           admin.nom_complet AS comptable_nom
    FROM paiements p
    JOIN reservations r ON r.id = p.reservation_id
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    JOIN users admin ON admin.id = p.enregistre_par
    " . ($where ? 'WHERE '.implode(' AND ',$where) : '') . "
    ORDER BY p.created_at DESC
");
$paiementsQuery->execute($params);
$paiements = $paiementsQuery->fetchAll();

// Réservations validées EN ATTENTE DE PAIEMENT
$enAttenteQuery = $pdo->query("
    SELECT r.*, e.nom AS espace_nom, e.prix_vip, u.nom_complet, u.telephone, u.email,
           t.montant AS tarif_montant, t.libelle AS tarif_libelle, t.unite
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    WHERE r.statut = 'validee' AND r.statut_paiement IN ('non_paye','attente_paiement','partiellement_paye')
    ORDER BY r.date_resa ASC
");
$enAttente = $enAttenteQuery->fetchAll();

// Stats
$totalPaye    = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements")->fetchColumn();
$nbPaiements  = (int)$pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn();
$nbEnAttente  = count($enAttente);

// Détail paiement ouvert
$openPaiement = null;
if ($openId) {
    $s = $pdo->prepare("
        SELECT p.*, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin, r.statut, r.statut_paiement, r.motif,
               e.nom AS espace_nom, u.nom_complet, u.email, u.telephone,
               admin.nom_complet AS comptable_nom
        FROM paiements p
        JOIN reservations r ON r.id=p.reservation_id
        JOIN espaces e ON e.id=r.espace_id
        JOIN users u ON u.id=r.user_id
        JOIN users admin ON admin.id=p.enregistre_par
        WHERE p.id=?
    ");
    $s->execute([$openId]);
    $openPaiement = $s->fetch();
}

$modeLabels = [
    'especes'      => ['Espèces',       'fa-money-bill-wave', 'bg-green-100 text-green-700'],
    'orange_money' => ['Orange Money',  'fa-mobile-alt',      'bg-orange-100 text-orange-700'],
    'moov_money'   => ['Moov Money',    'fa-mobile-alt',      'bg-blue-100 text-blue-700'],
    'virement'     => ['Virement',      'fa-university',      'bg-slate-100 text-slate-600'],
    'cheque'       => ['Chèque',        'fa-file-invoice',    'bg-purple-100 text-purple-700'],
];

$pageTitle = "Gestion des Paiements";
require __DIR__ . '/_admin_header.php';
?>

<datalist id="motifsReductionSuggestions">
  <option value="Geste commercial">
  <option value="Partenaire institutionnel">
  <option value="Événement caritatif / solidarité">
  <option value="Accord de la Direction">
  <option value="Étudiants / jeunes en difficulté">
  <option value="Association reconnue d'utilité publique">
</datalist>

<!-- En-tête -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight flex items-center gap-2">
      <i class="fas fa-cash-register text-accent"></i> Paiements
    </h1>
    <p class="text-sm text-slate-500 mt-0.5"><?= $nbPaiements ?> paiement(s) enregistré(s)</p>
  </div>
  <form method="GET" action="export.php" target="_blank" class="flex flex-wrap items-center gap-2">
    <input type="hidden" name="type" value="paiements">
    <input type="date" name="debut" value="<?= date('Y-m-d') ?>" class="text-xs font-bold rounded-xl border border-slate-200 px-3 py-2 outline-none text-primary">
    <span class="text-xs text-slate-400">→</span>
    <input type="date" name="fin" value="<?= date('Y-m-d') ?>" class="text-xs font-bold rounded-xl border border-slate-200 px-3 py-2 outline-none text-primary">
    <button type="submit" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-file-excel"></i> Rapport (Excel)
    </button>
  </form>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center mb-3">
      <i class="fas fa-check-circle text-green-500"></i>
    </div>
    <p class="text-2xl font-black text-green-600"><?= number_format($totalPaye,0,',',' ') ?> <span class="text-sm">FCFA</span></p>
    <p class="text-xs font-bold text-slate-500 mt-1">Total encaissé</p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mb-3">
      <i class="fas fa-receipt text-blue-500"></i>
    </div>
    <p class="text-2xl font-black text-primary"><?= $nbPaiements ?></p>
    <p class="text-xs font-bold text-slate-500 mt-1">Paiements enregistrés</p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 <?= $nbEnAttente>0?'border-amber-200':'' ?>">
    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center mb-3">
      <i class="fas fa-clock text-amber-500"></i>
    </div>
    <p class="text-2xl font-black text-amber-500"><?= $nbEnAttente ?></p>
    <p class="text-xs font-bold text-slate-500 mt-1">En attente de paiement</p>
  </div>
</div>

<!-- Réservations en attente de paiement -->
<?php if (!empty($enAttente)): ?>
<div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden mb-6">
  <div class="flex items-center gap-3 px-5 py-4 border-b border-amber-100 bg-amber-50">
    <i class="fas fa-clock text-amber-500"></i>
    <h2 class="font-black text-amber-800 text-sm uppercase italic tracking-tight">
      <?= count($enAttente) ?> réservation(s) validée(s) — paiement en attente
    </h2>
  </div>
  <div class="divide-y divide-slate-50">
    <?php foreach ($enAttente as $r):
        $estSejourResa = empty($r['heure_debut']);
        $nuitees = $estSejourResa ? max(1, (int)((strtotime($r['date_depart']) - strtotime($r['date_resa'])) / 86400)) : 1;
        $quantiteResa   = max(1, (int)($r['quantite'] ?? 1));
        $montantAttendu = $r['tarif_montant'] ? ((float)$r['tarif_montant'] * $nuitees * $quantiteResa) : 0;
        if ($estSejourResa && $r['petit_dejeuner']) $montantAttendu += 5000 * $nuitees * $quantiteResa;
        if (!$estSejourResa && !empty($r['vip'])) $montantAttendu += (float)($r['prix_vip'] ?? 0);

        // Si un acompte a déjà été versé, le solde restant à encaisser est ce qui compte réellement
        $dejaVerse = 0;
        if ($r['statut_paiement'] === 'partiellement_paye') {
            $stmtVerse = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE reservation_id = ?");
            $stmtVerse->execute([$r['id']]);
            $dejaVerse = (float)$stmtVerse->fetchColumn();
        }
        $soldeRestantAffiche = $montantAttendu > 0 ? max(0, $montantAttendu - $dejaVerse) : 0;

        // Délai de paiement : 48h après validation (date_validation), ou date de l'événement si plus tôt
        $limite48h  = $r['date_validation'] ? limite_paiement($r['date_validation']) : limite_paiement($r['created_at']);
        $limiteEvt  = strtotime($r['date_resa'] . ' 23:59:59');
        $limite     = min($limite48h, $limiteEvt);
        $heuresRestantes = ($limite - time()) / 3600;
    ?>
    <div onclick="<?= !$readonly ? "togglePayForm({$r['id']})" : '' ?>" class="flex flex-col md:flex-row md:items-center gap-4 px-5 py-4 hover:bg-slate-50 transition <?= !$readonly ? 'cursor-pointer' : '' ?>">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="font-black text-primary text-sm"><?= e($r['nom_complet']) ?></p>
          <span class="text-xs text-slate-400">·</span>
          <p class="text-sm text-slate-600 font-semibold"><?= e($r['espace_nom']) ?></p>
          <span class="text-[10px] font-mono font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">#RESA-<?= $r['id'] ?></span>
        </div>
        <div class="flex items-center gap-3 mt-1 text-xs text-slate-500 flex-wrap">
          <?php if ($estSejourResa): ?>
          <span><i class="fas fa-calendar text-accent text-[10px] mr-1"></i><?= date('d/m/Y',strtotime($r['date_resa'])) ?> → <?= date('d/m/Y',strtotime($r['date_depart'])) ?> (<?= $nuitees ?> nuitée<?= $nuitees>1?'s':'' ?>)</span>
          <?php if ($quantiteResa > 1): ?><span class="text-indigo-600 font-bold"><i class="fas fa-door-open text-[10px] mr-1"></i><?= $quantiteResa ?> chambres</span><?php endif; ?>
          <?php if ($r['petit_dejeuner']): ?><span class="text-amber-600 font-bold"><i class="fas fa-coffee text-[10px] mr-1"></i>Petit-déj inclus</span><?php endif; ?>
          <?php else: ?>
          <span><i class="fas fa-calendar text-accent text-[10px] mr-1"></i><?= date('d/m/Y',strtotime($r['date_resa'])) ?></span>
          <span><i class="fas fa-clock text-accent text-[10px] mr-1"></i><?= substr($r['heure_debut'],0,5) ?> → <?= substr($r['heure_fin'],0,5) ?></span>
          <?php endif; ?>
          <?php if ($r['telephone']): ?>
          <span><i class="fas fa-phone text-accent text-[10px] mr-1"></i><?= e($r['telephone']) ?></span>
          <?php endif; ?>
          <?php if ($montantAttendu): ?>
          <span class="font-black text-primary"><i class="fas fa-tag text-accent text-[10px] mr-1"></i><?= number_format($montantAttendu,0,',',' ') ?> FCFA<?= $estSejourResa ? " ($nuitees × ".number_format((float)$r['tarif_montant'],0,',',' ').")" : '/'.e($r['unite']) ?></span>
          <?php endif; ?>
          <?php if ($dejaVerse > 0): ?>
          <span class="font-black text-sky-700 bg-sky-50 px-2 py-0.5 rounded-full"><i class="fas fa-coins text-[10px] mr-1"></i>Acompte versé : <?= number_format($dejaVerse,0,',',' ') ?> FCFA — solde : <?= number_format($soldeRestantAffiche,0,',',' ') ?> FCFA<?= !empty($r['date_limite_solde']) ? ' — à régler avant le '.date('d/m/Y', strtotime($r['date_limite_solde'])) : '' ?></span>
          <?php endif; ?>
          <?php if ($heuresRestantes <= 6): ?>
          <span class="font-black text-red-600 bg-red-50 px-2 py-0.5 rounded-full"><i class="fas fa-clock text-[10px] mr-1"></i><?= $heuresRestantes > 0 ? 'Expire dans ' . round($heuresRestantes) . 'h' : 'Expiration imminente' ?></span>
          <?php elseif ($heuresRestantes <= 24): ?>
          <span class="font-black text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full"><i class="fas fa-clock text-[10px] mr-1"></i>Expire dans <?= round($heuresRestantes) ?>h</span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$readonly): ?>
      <!-- Formulaire paiement rapide -->
      <div class="flex-shrink-0 flex items-center gap-2">
        <a href="../generer_bon.php?id=<?= $r['id'] ?>&from=paiements" target="_blank" onclick="event.stopPropagation()"
           class="flex items-center gap-2 bg-slate-100 text-slate-600 text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-200 transition">
          <i class="fas fa-file-invoice"></i> Voir le bon
        </a>
        <button onclick="event.stopPropagation(); togglePayForm(<?= $r['id'] ?>)"
                class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-accent-dark transition shadow-sm">
          <i class="fas fa-plus-circle"></i> Enregistrer le paiement
        </button>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$readonly): ?>
    <!-- Formulaire paiement inline -->
    <div id="payForm-<?= $r['id'] ?>" class="hidden px-5 pb-5">
      <form method="POST" class="bg-slate-50 rounded-2xl border border-slate-100 p-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-4" onsubmit="return checkReduction(<?= $r['id'] ?>)">
        <input type="hidden" name="csrf_token"    value="<?= csrf_token() ?>">
        <input type="hidden" name="action"         value="enregistrer_paiement">
        <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
        <input type="hidden" name="montant_reference" value="<?= $montantAttendu ?: '' ?>">

        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Montant encaissé FCFA <span class="text-accent">*</span></label>
          <input type="number" name="montant" id="montant-<?= $r['id'] ?>" step="100" required
                 value="<?= $dejaVerse > 0 ? $soldeRestantAffiche : ($montantAttendu ?: '') ?>"
                 oninput="toggleReductionField(<?= $r['id'] ?>, <?= $montantAttendu ?: 0 ?>)"
                 placeholder="Ex: 400000"
                 class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-black text-accent outline-none focus:border-accent text-sm">
          <?php if ($montantAttendu): ?>
          <p class="text-[10px] text-slate-400 mt-1">
            Tarif attendu : <?= number_format($montantAttendu,0,',',' ') ?> FCFA
            · <button type="button" onclick="showReductionField(<?= $r['id'] ?>)" class="text-accent font-black underline hover:no-underline">Montant différent (acompte ou réduction)</button>
          </p>
          <?php endif; ?>
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-widests text-slate-400 mb-2">Mode de paiement <span class="text-accent">*</span></label>
          <div class="relative">
            <select name="mode" required class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none text-sm appearance-none">
              <?php foreach ($modeLabels as $val=>[$lab,$ico,$cls]): ?>
                <option value="<?= $val ?>"><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Référence / N° reçu</label>
          <input type="text" name="reference" placeholder="Ex: OM-123456"
                 class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>

        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Note</label>
          <input type="text" name="note" placeholder="Observation..."
                 class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>

        <div id="reductionField-<?= $r['id'] ?>" class="hidden sm:col-span-2 lg:col-span-4 space-y-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Le montant saisi est inférieur au tarif attendu — précisez pourquoi :</p>

          <div class="grid sm:grid-cols-2 gap-3">
            <label class="montant-type-option flex items-start gap-2.5 p-4 rounded-xl border-2 border-sky-200 bg-sky-50 cursor-pointer transition hover:border-sky-400">
              <input type="radio" name="type_montant_reduit" value="acompte" onchange="toggleAcompteMode(<?= $r['id'] ?>)" class="mt-0.5 w-4 h-4 accent-sky-600">
              <span>
                <span class="block text-xs font-black uppercase tracking-widest text-sky-700"><i class="fas fa-coins mr-1"></i>Acompte</span>
                <span class="block text-[10px] text-sky-600 mt-1">Le solde sera réglé plus tard, en un second versement.</span>
              </span>
            </label>
            <label class="montant-type-option flex items-start gap-2.5 p-4 rounded-xl border-2 border-orange-200 bg-orange-50 cursor-pointer transition hover:border-orange-400">
              <input type="radio" name="type_montant_reduit" value="reduction" onchange="toggleAcompteMode(<?= $r['id'] ?>)" class="mt-0.5 w-4 h-4 accent-orange-600">
              <span>
                <span class="block text-xs font-black uppercase tracking-widest text-orange-700"><i class="fas fa-percent mr-1"></i>Réduction</span>
                <span class="block text-[10px] text-orange-600 mt-1">Le montant total dû est définitivement réduit.</span>
              </span>
            </label>
          </div>

          <!-- Case cachée pour le traitement serveur, synchronisée avec le choix ci-dessus -->
          <input type="checkbox" name="est_acompte" value="1" class="hidden">

          <div id="motifReductionWrap-<?= $r['id'] ?>" class="hidden bg-orange-50 border-2 border-orange-200 rounded-xl p-4">
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-2">
              Motif de la réduction — obligatoire <span class="text-accent">*</span>
            </label>
            <input type="text" name="motif_reduction" list="motifsReductionSuggestions" placeholder="Ex : geste commercial, événement caritatif, accord de la direction..."
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2.5 font-semibold text-primary outline-none focus:border-orange-400 text-sm">
            <p class="text-[10px] text-orange-600 mt-1.5">Cette réduction sera visible dans l'historique et notifiée à l'Admin DG et au Ministre.</p>
          </div>
          <div id="acompteInfo-<?= $r['id'] ?>" class="hidden bg-sky-50 border-2 border-sky-200 rounded-xl p-4 space-y-3">
            <p class="text-xs text-sky-700">Le solde restant sera automatiquement calculé et affiché — vous pourrez enregistrer un second versement plus tard sur cette même réservation.</p>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-sky-700 mb-2">Date limite pour régler le solde</label>
              <input type="date" name="date_limite_solde" value="<?= date('Y-m-d', strtotime($r['date_resa'] . ' -2 days')) ?>"
                     class="w-full rounded-xl border-2 border-sky-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-sky-400 text-sm">
              <p class="text-[10px] text-sky-600 mt-1.5">Par défaut, 48h avant l'activité — modifiable d'un commun accord avec le client.</p>
            </div>
          </div>
        </div>

        <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-between">
          <button type="button" onclick="togglePayForm(<?= $r['id'] ?>)"
                  class="text-xs font-bold text-slate-400 hover:text-primary transition">Annuler</button>
          <button type="submit"
                  class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-xs font-black uppercase px-6 py-2.5 rounded-xl transition shadow-sm">
            <i class="fas fa-check"></i> Confirmer le paiement
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Historique paiements -->
<div class="grid lg:grid-cols-5 gap-5">
  <!-- Liste -->
  <div class="lg:col-span-3">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50">
        <h2 class="font-black text-[10px] uppercase tracking-widest text-slate-400 flex items-center gap-2">
          <i class="fas fa-history text-accent"></i> Historique des paiements
        </h2>
        <!-- Filtre mode + recherche -->
        <form method="GET" class="flex flex-wrap gap-2">
          <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="N° facture, client, espace..."
                   class="text-xs font-bold rounded-xl border border-slate-200 pl-8 pr-3 py-1.5 outline-none focus:border-primary text-primary w-48">
          </div>
          <div class="relative">
            <select name="mode" onchange="this.form.submit()" class="text-xs font-bold rounded-xl border border-slate-200 px-3 py-1.5 outline-none appearance-none pr-7 text-primary">
              <option value="">Tous modes</option>
              <?php foreach ($modeLabels as $val=>[$lab,$ico,$cls]): ?>
                <option value="<?= $val ?>" <?= $filterMode===$val?'selected':''?>><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[9px] pointer-events-none"></i>
          </div>
          <button type="submit" class="text-xs font-black bg-primary text-white px-3 py-1.5 rounded-xl hover:bg-slate-800 transition">OK</button>
          <?php if ($search || $filterMode): ?>
          <a href="paiements.php" class="text-xs font-bold text-slate-400 px-2 py-1.5 hover:text-accent transition">Réinitialiser</a>
          <?php endif; ?>
        </form>
      </div>

      <?php if (empty($paiements)): ?>
        <div class="py-16 text-center">
          <i class="fas fa-receipt text-4xl text-slate-200 mb-3"></i>
          <p class="text-slate-400 font-semibold">Aucun paiement enregistré.</p>
        </div>
      <?php else: ?>
      <div class="divide-y divide-slate-50">
        <?php foreach ($paiements as $p):
          [$mlab,$mico,$mcls] = $modeLabels[$p['mode']] ?? ['—','fa-circle','bg-slate-100 text-slate-400'];
          $isOpen = ($openPaiement && $openPaiement['id']==$p['id']);
        ?>
        <a href="paiements.php?id=<?= $p['id'] ?>"
           class="flex items-start gap-4 px-5 py-3.5 hover:bg-slate-50 transition <?= $isOpen?'bg-primary/5 border-l-4 border-primary':'' ?>">
          <div class="w-9 h-9 <?= $mcls ?> rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
            <i class="fas <?= $mico ?> text-xs"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-black text-primary text-sm"><?= e($p['nom_complet']) ?></p>
              <span class="text-xs font-black text-green-600">+<?= number_format((float)$p['montant'],0,',',' ') ?> FCFA</span>
            </div>
            <p class="text-[9px] font-mono font-bold text-slate-400 mt-0.5">
              <?= ref_recu((int)$p['id']) ?>
              <?php if (!empty($p['motif_reduction'])): ?>
                <span class="ml-1 inline-block bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full"><i class="fas fa-percent"></i> Réduction</span>
              <?php endif; ?>
            </p>
            <p class="text-xs text-slate-500 truncate mt-0.5"><?= e($p['espace_nom']) ?> · <?= date('d/m/Y',strtotime($p['date_resa'])) ?></p>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[9px] font-black px-2 py-0.5 rounded-full <?= $mcls ?>"><?= $mlab ?></span>
              <?php if ($p['reference']): ?>
                <span class="text-[9px] text-slate-400 font-mono"><?= e($p['reference']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <p class="text-[10px] text-slate-400 flex-shrink-0 whitespace-nowrap"><?= date('d/m H:i',strtotime($p['created_at'])) ?></p>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Détail -->
  <div class="lg:col-span-2">
    <?php if ($openPaiement):
      [$mlab,$mico,$mcls] = $modeLabels[$openPaiement['mode']] ?? ['—','fa-circle','bg-slate-100'];
    ?>
    <div id="detail-paiement" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden sticky top-24">
      <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <h2 class="font-black text-primary text-sm uppercase italic">Détail paiement #<?= $openPaiement['id'] ?></h2>
        <a href="paiements.php" class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent transition">
          <i class="fas fa-times text-xs"></i>
        </a>
      </div>
      <div class="p-6 space-y-5">
        <!-- Montant -->
        <div class="text-center py-4 bg-green-50 rounded-2xl border border-green-100">
          <p class="text-3xl font-black text-green-600"><?= number_format((float)$openPaiement['montant'],0,',',' ') ?></p>
          <p class="text-sm font-black text-green-700 mt-0.5">FCFA</p>
          <span class="inline-block mt-2 text-[10px] font-black px-3 py-1 rounded-full <?= $mcls ?>">
            <i class="fas <?= $mico ?> mr-1"></i><?= $mlab ?>
          </span>
        </div>

        <a href="../generer_bon.php?id=<?= $openPaiement['reservation_id'] ?>&from=paiements" target="_blank"
           class="flex items-center justify-center gap-2 bg-primary text-white text-xs font-black uppercase px-4 py-3 rounded-xl hover:bg-slate-800 transition">
          <i class="fas fa-file-invoice"></i> Voir / Imprimer la facture
        </a>

        <?php if (!empty($openPaiement['motif_reduction'])): ?>
        <div class="bg-orange-50 border-2 border-orange-200 rounded-xl p-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">
            <i class="fas fa-percent mr-1"></i> Réduction accordée
          </p>
          <p class="text-sm font-black text-orange-800">
            -<?= number_format((float)$openPaiement['montant_reference'] - (float)$openPaiement['montant'],0,',',' ') ?> FCFA
            <span class="font-normal text-xs text-orange-600">(tarif <?= number_format((float)$openPaiement['montant_reference'],0,',',' ') ?> FCFA)</span>
          </p>
          <p class="text-xs text-orange-700 mt-1"><?= e($openPaiement['motif_reduction']) ?></p>
        </div>
        <?php endif; ?>

        <?php foreach ([
          ['fa-user','Client',        $openPaiement['nom_complet']],
          ['fa-building','Espace',    $openPaiement['espace_nom']],
          ['fa-calendar','Date resa', $openPaiement['heure_debut']
              ? date('d/m/Y',strtotime($openPaiement['date_resa']))
              : date('d/m/Y',strtotime($openPaiement['date_resa'])).' → '.date('d/m/Y',strtotime($openPaiement['date_depart']))],
          ['fa-clock','Horaires',     $openPaiement['heure_debut'] ? (substr($openPaiement['heure_debut'],0,5).' → '.substr($openPaiement['heure_fin'],0,5)) : '—'],
          ['fa-user-tie','Enregistré par', $openPaiement['comptable_nom']],
          ['fa-calendar-plus','Date paiement', date('d/m/Y H:i',strtotime($openPaiement['created_at']))],
        ] as [$ico,$label,$val]): ?>
        <div class="flex items-start gap-3">
          <div class="w-7 h-7 bg-slate-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 border border-slate-100">
            <i class="fas <?= $ico ?> text-accent text-[10px]"></i>
          </div>
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?= $label ?></p>
            <p class="text-sm font-bold text-primary mt-0.5"><?= e($val) ?></p>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if ($openPaiement['reference']): ?>
        <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Référence</p>
          <code class="text-sm font-mono text-primary"><?= e($openPaiement['reference']) ?></code>
        </div>
        <?php endif; ?>

        <?php if ($openPaiement['note']): ?>
        <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Note</p>
          <p class="text-sm text-slate-700"><?= e($openPaiement['note']) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 py-20 text-center">
      <i class="fas fa-hand-pointer text-3xl text-slate-200 mb-3"></i>
      <p class="font-black text-slate-400 text-sm uppercase">Sélectionnez un paiement</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function togglePayForm(id) {
    const f = document.getElementById('payForm-'+id);
    f.classList.toggle('hidden');
    if (!f.classList.contains('hidden')) f.scrollIntoView({behavior:'smooth',block:'center'});
}

function toggleReductionField(id, montantAttendu) {
    const montant = parseFloat(document.getElementById('montant-'+id).value) || 0;
    const field = document.getElementById('reductionField-'+id);
    const isReduction = montantAttendu > 0 && montant < montantAttendu;
    field.classList.toggle('hidden', !isReduction);
    if (!isReduction) {
        // On réinitialise le choix si le montant redevient normal
        field.querySelectorAll('input[name="type_montant_reduit"]').forEach(r => r.checked = false);
        document.getElementById('motifReductionWrap-'+id).classList.add('hidden');
        document.getElementById('acompteInfo-'+id).classList.add('hidden');
        field.querySelector('input[name="motif_reduction"]').required = false;
    }
}

function showReductionField(id) {
    document.getElementById('reductionField-'+id).classList.remove('hidden');
    document.getElementById('montant-'+id).select();
}

function toggleAcompteMode(id) {
    const choix = document.querySelector(`#reductionField-${id} input[name="type_montant_reduit"]:checked`)?.value;
    const hiddenCheckbox = document.querySelector(`#reductionField-${id} input[name="est_acompte"]`);
    const motifWrap = document.getElementById('motifReductionWrap-'+id);
    const acompteInfo = document.getElementById('acompteInfo-'+id);
    const motifInput = motifWrap.querySelector('input[name="motif_reduction"]');

    hiddenCheckbox.checked = (choix === 'acompte');
    motifWrap.classList.toggle('hidden', choix !== 'reduction');
    acompteInfo.classList.toggle('hidden', choix !== 'acompte');
    motifInput.required = (choix === 'reduction');
}

function checkReduction(id) {
    const field = document.getElementById('reductionField-'+id);
    if (!field.classList.contains('hidden')) {
        const choix = field.querySelector('input[name="type_montant_reduit"]:checked');
        if (!choix) { alert('Merci de préciser s\'il s\'agit d\'un acompte ou d\'une réduction.'); return false; }
        if (choix.value === 'reduction') {
            const motif = field.querySelector('input[name="motif_reduction"]').value.trim();
            if (!motif) { alert('Merci d\'indiquer le motif de la réduction accordée.'); return false; }
        }
    }
    return true;
}

<?php if ($openPaiement): ?>
document.addEventListener('DOMContentLoaded', function() {
    const detail = document.getElementById('detail-paiement');
    if (detail) detail.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>