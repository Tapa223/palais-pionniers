<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_client('admin/dashboard.php');
expirer_reservations_non_payees();

$pdo     = db();
$user_id = (int)$_SESSION['user_id'];
$msg     = null;

// --- Modification du profil (nom, téléphone, email) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profil') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $nomComplet = trim($_POST['nom_complet'] ?? '');
        $telephone  = trim($_POST['telephone'] ?? '');
        $email      = trim($_POST['email'] ?? '');

        if (!$nomComplet || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = ['err', 'Nom et email valide sont obligatoires.'];
        } else {
            $emailPris = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailPris->execute([$email, $user_id]);
            if ($emailPris->fetch()) {
                $msg = ['err', 'Cet email est déjà utilisé par un autre compte.'];
            } else {
                $pdo->prepare("UPDATE users SET nom_complet = ?, telephone = ?, email = ? WHERE id = ?")
                    ->execute([$nomComplet, $telephone, $email, $user_id]);
                $_SESSION['nom_complet'] = $nomComplet;
                log_activity('profil_modifie', 'users', "Client #$user_id a mis à jour son profil");
                $msg = ['ok', 'Profil mis à jour avec succès.'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'choix_requisition') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $requisitionId = (int)($_POST['requisition_id'] ?? 0);
        $choix         = in_array($_POST['choix'] ?? '', ['remboursement','nouvelle_date','autre_espace'], true) ? $_POST['choix'] : null;
        $details       = trim($_POST['details_choix'] ?? '');

        // Sécurité : cette réquisition doit concerner une réservation de ce client
        $checkReq = $pdo->prepare("
            SELECT rm.id, r.espace_id, e.nom AS espace_nom,
                   (SELECT COALESCE(SUM(montant),0) FROM paiements WHERE reservation_id = r.id) AS montant_verse
            FROM requisitions_ministerielles rm
            JOIN reservations r ON r.id = rm.reservation_id
            JOIN espaces e ON e.id = r.espace_id
            WHERE rm.id = ? AND r.user_id = ? AND rm.choix_client IS NULL
        ");
        $checkReq->execute([$requisitionId, $user_id]);
        $reqInfo = $checkReq->fetch();

        if (!$reqInfo || !$choix) {
            $msg = ['err', 'Cette demande ne vous appartient pas ou a déjà été traitée.'];
        } elseif ($choix === 'remboursement' && (float)$reqInfo['montant_verse'] <= 0) {
            $msg = ['err', 'Le remboursement n\'est pas disponible : aucun paiement n\'a encore été effectué pour cette réservation.'];
        } else {
            $pdo->prepare("UPDATE requisitions_ministerielles SET choix_client = ?, details_choix = ?, date_choix = NOW() WHERE id = ?")
                ->execute([$choix, $details ?: null, $requisitionId]);
            $libellesChoix = ['remboursement' => 'Remboursement', 'nouvelle_date' => 'Nouvelle date', 'autre_espace' => 'Autre espace'];
            $suffixeMontant = $choix === 'remboursement' ? ' — Montant à rembourser : ' . number_format((float)$reqInfo['montant_verse'],0,',',' ') . ' FCFA' : '';
            notify('admin_comptable', 'requisition_choix', "Le client a choisi « {$libellesChoix[$choix]} » suite à la réquisition de « {$reqInfo['espace_nom']} ».$suffixeMontant", "requisitions.php");
            log_activity('requisition_choix', 'reservations', "Client #$user_id a choisi « $choix » pour la réquisition #$requisitionId");
            $msg = ['ok', 'Votre choix a bien été enregistré. Le comptable va le traiter et vous recontacter.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer_message') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $msgId = (int)($_POST['message_id'] ?? 0);
        // Sécurité : ce message doit vraiment appartenir à ce client (même email)
        $checkMsg = $pdo->prepare("SELECT id FROM messages WHERE id = ? AND email = (SELECT email FROM users WHERE id = ?)");
        $checkMsg->execute([$msgId, $user_id]);
        if ($checkMsg->fetchColumn()) {
            $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$msgId]);
            $msg = ['ok', 'Message supprimé.'];
        } else {
            $msg = ['err', 'Ce message ne vous appartient pas.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'demander_resiliation') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $espaceIdResi = (int)($_POST['espace_id'] ?? 0);
        $noteResi     = trim($_POST['resiliation_note'] ?? '');

        // Sécurité : ce bail doit vraiment appartenir à ce client
        $checkResi = $pdo->prepare("SELECT nom FROM espaces WHERE id = ? AND gerant_user_id = ?");
        $checkResi->execute([$espaceIdResi, $user_id]);
        $espNomResi = $checkResi->fetchColumn();

        if (!$espNomResi) {
            $msg = ['err', 'Ce bail ne vous appartient pas.'];
        } else {
            $pdo->prepare("UPDATE espaces SET resiliation_demandee = 1, resiliation_demandee_le = NOW(), resiliation_note = ? WHERE id = ?")
                ->execute([$noteResi ?: null, $espaceIdResi]);
            notify('admin_espaces', 'resiliation_demandee', "Demande de résiliation du bail « $espNomResi » par {$_SESSION['nom_complet']}.", "baux.php");
            log_activity('resiliation_demandee', 'espaces', "Résiliation demandée pour « $espNomResi » par le client #$user_id");
            $msg = ['ok', 'Votre demande de résiliation a été transmise à l\'administration. Elle vous recontactera pour finaliser.'];
        }
    }
}

// --- Notifications du client (marquées lues automatiquement à la consultation) ---
$notifsClient = $pdo->prepare("SELECT * FROM notifications WHERE destinataire_id = ? ORDER BY lu ASC, created_at DESC LIMIT 20");
$notifsClient->execute([$user_id]);
$notifsClient = $notifsClient->fetchAll();
$notifsNonLues = count(array_filter($notifsClient, fn($n) => !$n['lu']));
if ($notifsNonLues > 0) {
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE destinataire_id = ? AND lu = 0")->execute([$user_id]);
}

// Infos utilisateur complètes
$me = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$me->execute([$user_id]);
$me = $me->fetch();

// Messages envoyés par ce client (via le formulaire de contact), avec réponse si disponible
$mesMessages = $pdo->prepare("SELECT * FROM messages WHERE email = ? ORDER BY created_at DESC");
$mesMessages->execute([$me['email']]);
$mesMessages = $mesMessages->fetchAll();

// Mes Baux — espaces loués par ce compte client
$mesBaux = $pdo->prepare("SELECT * FROM espaces WHERE gerant_user_id = ?");
$mesBaux->execute([$user_id]);
$mesBaux = $mesBaux->fetchAll();

$dureeParTypeBail = ['mensuel' => 1, 'trimestriel' => 3, 'semestriel' => 6, 'annuel' => 12];
foreach ($mesBaux as &$bailInfo) {
    $periodeDeb = periode_actuelle_debut($bailInfo['type_bail'] ?: 'mensuel');
    $paye = $pdo->prepare("SELECT * FROM bail_paiements WHERE espace_id = ? AND periode_debut = ?");
    $paye->execute([$bailInfo['id'], $periodeDeb]);
    $bailInfo['periode_payee'] = (bool)$paye->fetch();
    $bailInfo['periode_debut_actuelle'] = $periodeDeb;
    $bailInfo['loyer_suggere'] = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM tarifs WHERE espace_id = " . (int)$bailInfo['id'] . " AND est_bail = 1")->fetchColumn();
    $histoPaiements = $pdo->prepare("SELECT * FROM bail_paiements WHERE espace_id = ? ORDER BY periode_debut DESC LIMIT 12");
    $histoPaiements->execute([$bailInfo['id']]);
    $bailInfo['historique'] = $histoPaiements->fetchAll();
}
unset($bailInfo);

// Réservations
// Liste des espaces disponibles, pour le choix "Autre espace" en cas de réquisition
$espacesDisponiblesChoix = $pdo->query("SELECT id, nom FROM espaces WHERE disponible = 1 AND (gerant_externe IS NULL OR gerant_externe = '') ORDER BY nom ASC")->fetchAll();

$reservations = $pdo->prepare("
    SELECT r.*, e.nom AS espace_nom, t.libelle AS tarif_libelle, t.montant, t.unite,
           rm.id AS requisition_id, rm.motif AS requisition_motif, rm.choix_client, rm.statut AS requisition_statut,
           (SELECT COALESCE(SUM(montant),0) FROM paiements WHERE reservation_id = r.id) AS montant_verse
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    LEFT JOIN requisitions_ministerielles rm ON rm.reservation_id = r.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$reservations->execute([$user_id]);
$reservations = $reservations->fetchAll();

// Une réquisition en attente de choix doit toujours apparaître en premier,
// même sur une réservation ancienne — le client ne doit pas avoir à
// défiler pour la voir.
usort($reservations, function($a, $b) {
    $prioriteA = ($a['requisition_id'] && !$a['choix_client']) ? 0 : 1;
    $prioriteB = ($b['requisition_id'] && !$b['choix_client']) ? 0 : 1;
    if ($prioriteA !== $prioriteB) return $prioriteA <=> $prioriteB;
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

// Stats
$stats = ['total'=>count($reservations),'en_attente'=>0,'validee'=>0,'refusee'=>0];
foreach ($reservations as $r) {
    if (isset($stats[$r['statut']])) $stats[$r['statut']]++;
}

$badgeConfig = [
    'en_attente' => ['En attente',       'bg-amber-100 text-amber-700 border-amber-200'],
    'validee'    => ['Validée',          'bg-blue-100 text-blue-700 border-blue-200'],
    'refusee'    => ['Refusée',          'bg-red-100 text-red-700 border-red-200'],
    'annulee'    => ['Annulée',          'bg-slate-100 text-slate-500 border-slate-200'],
    'expiree'    => ['Expirée (délai dépassé)', 'bg-slate-100 text-slate-500 border-slate-200'],
    'requisitionnee' => ['Réquisitionnée', 'bg-amber-100 text-amber-700 border-amber-200'],
];

// Onglet actif
$tab = in_array($_GET['tab'] ?? '', ['reservations','notifications','messages','baux','profil']) ? $_GET['tab'] : 'reservations';

$pageTitle = "Mon espace — Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-16">

  <!-- Header profil -->
  <div class="bg-primary text-white">
    <div class="container mx-auto max-w-4xl px-4 py-6 sm:py-8 md:py-12">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-accent flex items-center justify-center font-black text-xl flex-shrink-0">
            <?= strtoupper(substr($me['nom_complet'],0,1)) ?>
          </div>
          <div>
            <h1 class="text-xl md:text-2xl font-black uppercase italic tracking-tight">
              <?php
                $heureActuelle = (int)date('G');
                $salutation = $heureActuelle < 5 ? 'Bonsoir' : ($heureActuelle < 18 ? 'Bonjour' : 'Bonsoir');
              ?>
              <?= $salutation ?>, <?= e(explode(' ',$me['nom_complet'])[0]) ?>
            </h1>
            <p class="text-white/60 text-sm mt-0.5"><?= e($me['email']) ?></p>
          </div>
        </div>
        <a href="reserver.php"
           class="flex items-center gap-2 bg-accent hover:bg-accent-dark text-white px-5 py-3 rounded-xl font-black text-sm uppercase tracking-wide transition shadow-lg flex-shrink-0">
          <i class="fas fa-plus-circle"></i> Nouvelle réservation
        </a>
      </div>

      <!-- Stats rapides -->
      <div class="grid grid-cols-3 gap-3 mt-6">
        <?php foreach ([
          ['Total',       $stats['total'],      'fa-calendar', 'text-white'],
          ['En attente',  $stats['en_attente'], 'fa-clock',    'text-amber-400'],
          ['Confirmées',  $stats['validee'],    'fa-check',    'text-emerald-400'],
        ] as [$label,$val,$icon,$color]): ?>
        <div class="bg-white/10 rounded-2xl p-4 text-center">
          <i class="fas <?= $icon ?> <?= $color ?> text-sm mb-1"></i>
          <p class="text-2xl font-black text-white"><?= $val ?></p>
          <p class="text-[10px] text-white/50 uppercase tracking-wide font-bold"><?= $label ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Onglets -->
    <div class="container mx-auto max-w-4xl px-4">
      <div class="flex gap-0.5 sm:gap-1 border-b border-white/10 overflow-x-auto scrollbar-hide">
        <?php foreach (array_merge([
          ['reservations', 'fa-calendar-check', 'Réservations', count($reservations)],
          ['messages',     'fa-envelope',        'Messages',    count(array_filter($mesMessages, fn($m) => !empty($m['reponse'])))],
        ], $mesBaux ? [['baux', 'fa-file-signature', 'Mes Baux', count(array_filter($mesBaux, fn($b) => !$b['periode_payee']))]] : [], [
          ['notifications','fa-bell',           'Notifications', $notifsNonLues > 0 ? $notifsNonLues : null],
          ['profil',       'fa-user',           'Profil',       null],
        ]) as [$key,$icon,$label,$count]): ?>
        <a href="?tab=<?= $key ?>"
           class="flex-shrink-0 flex items-center justify-center sm:justify-start gap-1 sm:gap-2 px-3.5 sm:px-4 py-3 sm:py-3 text-[11px] sm:text-sm font-black transition border-b-2 <?= $tab===$key ? 'border-accent text-white' : 'border-transparent text-white/50 hover:text-white/80' ?>"
           title="<?= e($label) ?>">
          <i class="fas <?= $icon ?> text-sm sm:text-xs"></i>
          <span class="hidden sm:inline"><?= $label ?></span>
          <?php if ($count !== null): ?>
            <span class="text-[8px] sm:text-[9px] bg-white/20 px-1.5 py-0.5 rounded-full"><?= $count ?></span>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="container mx-auto max-w-4xl px-4 py-7">

    <?php if (isset($_GET['success'])): ?>
    <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3">
      <i class="fas fa-check-circle text-emerald-500 text-lg"></i>
      <p class="font-bold text-emerald-700">Demande de réservation envoyée ! L'administration vous répondra sous 24h.</p>
    </div>
    <?php endif; ?>

    <!-- ===== ONGLET RÉSERVATIONS ===== -->
    <?php if ($tab === 'reservations'): ?>

    <?php if (empty($reservations)): ?>
      <div class="bg-white rounded-2xl border border-slate-100 py-16 text-center shadow-sm">
        <i class="fas fa-calendar-xmark text-4xl text-slate-200 mb-3"></i>
        <p class="text-slate-500 font-semibold">Aucune réservation pour le moment.</p>
        <a href="espaces.php" class="inline-flex items-center gap-2 mt-4 text-xs font-black text-accent hover:underline">
          <i class="fas fa-building"></i> Découvrir nos espaces
        </a>
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($reservations as $r):
          [$blib, $bcls] = $badgeConfig[$r['statut']] ?? $badgeConfig['annulee'];
        ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition overflow-hidden">
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 p-5">
            <!-- Initiale espace -->
            <div class="w-12 h-12 rounded-xl bg-primary/5 border border-primary/10 flex items-center justify-center font-black text-primary text-lg flex-shrink-0">
              <?= strtoupper(substr($r['espace_nom'],0,1)) ?>
            </div>

            <!-- Infos -->
            <div class="flex-1 min-w-0">
              <h3 class="font-black text-primary text-base truncate"><?= e($r['espace_nom']) ?></h3>
              <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-slate-500 font-semibold">
                <?php if ($r['heure_debut']): ?>
                <span class="flex items-center gap-1">
                  <i class="fas fa-calendar text-accent text-[10px]"></i>
                  <?= date('d/m/Y', strtotime($r['date_resa'])) ?>
                </span>
                <span class="flex items-center gap-1">
                  <i class="fas fa-clock text-accent text-[10px]"></i>
                  <?= substr($r['heure_debut'],0,5) ?> → <?= substr($r['heure_fin'],0,5) ?>
                </span>
                <?php else:
                  $nuiteesClient = max(1, (int)((strtotime($r['date_depart']) - strtotime($r['date_resa'])) / 86400));
                ?>
                <span class="flex items-center gap-1">
                  <i class="fas fa-calendar text-accent text-[10px]"></i>
                  <?= date('d/m/Y', strtotime($r['date_resa'])) ?> → <?= date('d/m/Y', strtotime($r['date_depart'])) ?>
                </span>
                <span class="flex items-center gap-1">
                  <i class="fas fa-moon text-accent text-[10px]"></i>
                  <?= $nuiteesClient ?> nuitée<?= $nuiteesClient > 1 ? 's' : '' ?>
                  <?php if ((int)($r['quantite'] ?? 1) > 1): ?> · <?= (int)$r['quantite'] ?> chambres<?php endif; ?>
                  <?php if ($r['petit_dejeuner']): ?> · petit-déj inclus<?php endif; ?>
                </span>
                <?php endif; ?>
                <?php if ($r['tarif_libelle']): ?>
                <span class="flex items-center gap-1">
                  <i class="fas fa-tag text-accent text-[10px]"></i>
                  <?= e($r['tarif_libelle']) ?> — <?= number_format((float)$r['montant'],0,',',' ') ?> FCFA/<?= e($r['unite']) ?>
                </span>
                <?php endif; ?>
              </div>
              <?php if ($r['motif']): ?>
                <p class="text-xs text-slate-400 mt-1 truncate italic">"<?= e($r['motif']) ?>"</p>
              <?php endif; ?>
            </div>

            <!-- Droite : badge + action -->
            <div class="flex flex-col sm:items-end gap-2 flex-shrink-0">
              <div class="flex flex-wrap sm:justify-end gap-1.5">
                <?php if ($r['statut'] === 'validee' && $r['statut_paiement'] === 'paye'): ?>
                <span class="text-[10px] font-black px-3 py-1.5 rounded-full border bg-emerald-100 text-emerald-700 border-emerald-200">
                  <i class="fas fa-check-circle mr-0.5"></i> Payée
                </span>
                <?php elseif ($r['statut'] === 'validee' && $r['statut_paiement'] === 'partiellement_paye'): ?>
                <span class="text-[10px] font-black px-3 py-1.5 rounded-full border bg-sky-100 text-sky-700 border-sky-200">
                  <i class="fas fa-coins mr-0.5"></i> Acompte versé
                </span>
                <?php if (!empty($r['date_limite_solde'])): ?>
                <span class="text-[9px] font-bold text-sky-600 sm:text-right">Solde à régler avant le <?= date('d/m/Y', strtotime($r['date_limite_solde'])) ?></span>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-[10px] font-black px-3 py-1.5 rounded-full border <?= $bcls ?>"><?= $blib ?></span>
                <?php endif; ?>
              </div>
              <?php if ($r['statut'] === 'validee'): ?>
                <?php if ($r['statut_paiement'] === 'paye'): ?>
                <div class="flex flex-col sm:flex-row gap-1.5">
                  <a href="generer_bon.php?id=<?= $r['id'] ?>&type=bon" target="_blank"
                     class="flex items-center gap-1.5 bg-primary text-white text-[10px] font-black uppercase px-3 py-1.5 rounded-xl hover:bg-slate-800 transition">
                    <i class="fas fa-download"></i> Bon de réservation
                  </a>
                  <a href="generer_bon.php?id=<?= $r['id'] ?>" target="_blank"
                     class="flex items-center gap-1.5 bg-emerald-600 text-white text-[10px] font-black uppercase px-3 py-1.5 rounded-xl hover:bg-emerald-700 transition">
                    <i class="fas fa-file-invoice"></i> Voir ma facture
                  </a>
                </div>
                <?php else: ?>
                <a href="generer_bon.php?id=<?= $r['id'] ?>" target="_blank"
                   class="flex items-center gap-1.5 bg-primary text-white text-[10px] font-black uppercase px-3 py-1.5 rounded-xl hover:bg-slate-800 transition">
                  <i class="fas fa-download"></i> Bon de réservation
                </a>
                <?php endif; ?>
                <?php if ($r['statut_paiement'] !== 'paye' && !empty($r['date_validation'])): ?>
                <span class="text-[10px] text-amber-600 font-bold text-right">
                  <i class="fas fa-clock"></i> À régler avant le <?= date('d/m/Y à H:i', strtotime($r['date_validation'] . ' +48 hours')) ?>
                </span>
                <?php endif; ?>
              <?php elseif ($r['statut'] === 'en_attente'): ?>
                <span class="text-[10px] text-amber-500 font-bold italic">Examen en cours...</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Note admin si refusée -->
          <?php if ($r['statut'] === 'refusee' && $r['note_admin']): ?>
          <div class="px-5 pb-4">
            <div class="rounded-xl bg-red-50 border border-red-100 p-3 flex items-start gap-2 text-xs">
              <i class="fas fa-info-circle text-accent flex-shrink-0 mt-0.5"></i>
              <div>
                <span class="font-black text-accent">Motif du refus : </span>
                <span class="text-red-700"><?= e($r['note_admin']) ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Réquisition ministérielle : le client doit choisir -->
          <?php if ($r['statut'] === 'requisitionnee' && $r['requisition_id']): ?>
          <div class="px-5 pb-5">
            <div class="rounded-2xl bg-amber-50 border-2 border-amber-200 p-4">
              <p class="text-sm font-black text-amber-700 mb-1"><i class="fas fa-landmark mr-1.5"></i>Espace réquisitionné pour un besoin prioritaire</p>
              <p class="text-xs text-amber-700 mb-3">Motif : <?= e($r['requisition_motif']) ?></p>
              <?php if ($r['choix_client']): ?>
              <p class="text-xs font-bold text-amber-700"><i class="fas fa-check-circle mr-1"></i>Votre choix a été enregistré, l'administration va vous recontacter.</p>
              <?php else: ?>
              <p class="text-xs text-amber-700 mb-3">Merci de choisir comment vous souhaitez être dédommagé :</p>
              <form method="POST" id="reqForm-<?= $r['requisition_id'] ?>" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="choix_requisition">
                <input type="hidden" name="requisition_id" value="<?= $r['requisition_id'] ?>">
                <?php $aPaye = (float)$r['montant_verse'] > 0; ?>
                <div class="grid grid-cols-1 <?= $aPaye ? 'sm:grid-cols-3' : 'sm:grid-cols-2' ?> gap-2">
                  <?php if ($aPaye): ?>
                  <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-amber-200 bg-white cursor-pointer text-xs font-bold text-amber-700">
                    <input type="radio" name="choix" value="remboursement" required onchange="toggleChoixEspace(<?= $r['requisition_id'] ?>)" class="w-4 h-4 accent-amber-600"> Remboursement <span class="text-[10px] font-normal">(<?= number_format((float)$r['montant_verse'],0,',',' ') ?> FCFA)</span>
                  </label>
                  <?php endif; ?>
                  <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-amber-200 bg-white cursor-pointer text-xs font-bold text-amber-700">
                    <input type="radio" name="choix" value="nouvelle_date" required onchange="toggleChoixEspace(<?= $r['requisition_id'] ?>)" class="w-4 h-4 accent-amber-600"> Nouvelle date
                  </label>
                  <label class="flex items-center gap-2 p-2.5 rounded-xl border-2 border-amber-200 bg-white cursor-pointer text-xs font-bold text-amber-700">
                    <input type="radio" name="choix" value="autre_espace" required onchange="toggleChoixEspace(<?= $r['requisition_id'] ?>)" class="w-4 h-4 accent-amber-600"> Autre espace
                  </label>
                </div>
                <?php if (!$aPaye): ?>
                <p class="text-[10px] text-amber-600 italic"><i class="fas fa-info-circle mr-1"></i>Le remboursement n'est pas proposé ici car aucun paiement n'a encore été effectué pour cette réservation.</p>
                <?php endif; ?>
                <div id="choixEspaceWrap-<?= $r['requisition_id'] ?>" class="hidden">
                  <select id="choixEspaceSelect-<?= $r['requisition_id'] ?>" onchange="syncDetailsChoix(<?= $r['requisition_id'] ?>)" class="w-full rounded-xl border-2 border-amber-200 bg-white px-3 py-2 text-xs font-bold text-amber-700 outline-none focus:border-amber-400">
                    <option value="">— Choisir un espace —</option>
                    <?php foreach ($espacesDisponiblesChoix as $espChoix): ?>
                    <option value="<?= e($espChoix['nom']) ?>"><?= e($espChoix['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <input type="text" name="details_choix" id="detailsChoix-<?= $r['requisition_id'] ?>" placeholder="Précisions (optionnel — ex : date souhaitée)"
                       class="w-full rounded-xl border-2 border-amber-200 bg-white px-3 py-2 text-xs font-semibold text-amber-700 outline-none focus:border-amber-400">
                <button type="submit" class="w-full bg-amber-600 text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-amber-700 transition">Envoyer mon choix</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- ===== ONGLET PROFIL ===== -->
    <?php elseif ($tab === 'messages'): ?>

    <?php if (empty($mesMessages)): ?>
      <div class="bg-white rounded-2xl border border-slate-100 py-16 text-center shadow-sm">
        <i class="fas fa-envelope-open text-4xl text-slate-200 mb-3"></i>
        <p class="text-slate-400 font-semibold">Vous n'avez envoyé aucun message pour l'instant.</p>
        <a href="contact.php" class="inline-block mt-4 text-accent font-black text-sm hover:underline">Contactez-nous →</a>
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($mesMessages as $m): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
          <div class="flex items-center justify-between gap-3 mb-2">
            <p class="text-xs font-black text-slate-400 uppercase tracking-widest"><?= date('d/m/Y à H:i', strtotime($m['created_at'])) ?></p>
            <?php if (!empty($m['reponse'])): ?>
            <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700"><i class="fas fa-check mr-1"></i>Répondu</span>
            <?php else: ?>
            <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-amber-100 text-amber-700"><i class="fas fa-clock mr-1"></i>En attente de réponse</span>
            <?php endif; ?>
          </div>
          <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line mb-3"><?= e($m['message']) ?></p>
          <?php if (!empty($m['reponse']) && !empty($m['envoye_site'])): ?>
          <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 mt-2">
            <p class="text-[10px] font-black text-primary uppercase tracking-widest mb-1.5"><i class="fas fa-reply mr-1"></i>Réponse du Palais</p>
            <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line"><?= e($m['reponse']) ?></p>
          </div>
          <?php endif; ?>
          <form method="POST" onsubmit="return confirm('Supprimer ce message ?')" class="mt-3 text-right">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="supprimer_message">
            <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
            <button type="submit" class="text-[10px] font-black text-slate-300 hover:text-accent uppercase tracking-widest transition"><i class="fas fa-trash mr-1"></i>Supprimer</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php elseif ($tab === 'baux'): ?>

    <?php if (!$mesBaux): ?>
    <div class="bg-white rounded-2xl border border-slate-100 py-12 sm:py-16 text-center shadow-sm">
      <i class="fas fa-file-signature text-3xl sm:text-4xl text-slate-200 mb-3"></i>
      <p class="text-slate-400 text-sm">Vous n'avez aucun bail actif pour le moment.</p>
    </div>
    <?php else: ?>
    <p class="text-xs text-slate-400 mb-4 sm:mb-6">Vous louez le ou les espaces ci-dessous sur une longue durée. Le loyer est à régler directement auprès du comptable du Palais, selon la périodicité de votre contrat.</p>
    <div class="space-y-5 sm:space-y-6">
      <?php foreach ($mesBaux as $bail): ?>
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
          <div>
            <p class="font-black text-primary text-sm sm:text-lg uppercase italic"><?= e($bail['nom']) ?></p>
            <p class="text-[10px] sm:text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5"><?= ucfirst($bail['type_bail']) ?></p>
          </div>
          <span class="text-[9px] sm:text-[10px] font-black uppercase px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full <?= $bail['periode_payee'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
            <?= $bail['periode_payee'] ? 'Période à jour' : 'Paiement attendu' ?>
          </span>
        </div>

        <div class="px-4 sm:px-6 py-4 grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 text-xs sm:text-sm">
          <div>
            <p class="text-[9px] sm:text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Loyer</p>
            <p class="font-black text-primary"><?= number_format($bail['loyer_suggere'], 0, ',', ' ') ?> FCFA</p>
          </div>
          <div>
            <p class="text-[9px] sm:text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Période en cours</p>
            <p class="font-bold text-slate-600"><?= date('d/m/Y', strtotime($bail['periode_debut_actuelle'])) ?></p>
          </div>
          <div class="col-span-2 sm:col-span-1">
            <p class="text-[9px] sm:text-[10px] font-black uppercase text-slate-300 tracking-widest mb-1">Statut</p>
            <p class="font-bold <?= $bail['periode_payee'] ? 'text-emerald-600' : 'text-amber-600' ?>"><?= $bail['periode_payee'] ? 'Réglé' : 'En attente de paiement' ?></p>
          </div>
        </div>

        <?php if ($bail['historique']): ?>
        <div class="px-4 sm:px-6 pb-4 sm:pb-5">
          <p class="text-[9px] sm:text-[10px] font-black uppercase text-slate-300 tracking-widest mb-2">Historique des paiements</p>
          <div class="space-y-1.5 sm:space-y-2">
            <?php foreach ($bail['historique'] as $h): ?>
            <div class="flex items-center justify-between text-xs sm:text-sm bg-slate-50 rounded-xl px-3 py-2">
              <span class="text-slate-500 font-semibold"><?= date('d/m/Y', strtotime($h['periode_debut'])) ?> <span class="hidden sm:inline">(<?= $h['duree_mois'] ?> mois)</span></span>
              <span class="font-black text-primary"><?= number_format($h['montant'], 0, ',', ' ') ?> FCFA</span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="px-4 sm:px-6 pb-4 sm:pb-5 border-t border-slate-50 pt-4">
          <?php if ($bail['resiliation_demandee']): ?>
          <div class="bg-slate-50 rounded-xl px-4 py-3 flex items-center gap-2.5">
            <i class="fas fa-clock text-slate-400 text-sm"></i>
            <p class="text-xs text-slate-500 font-semibold">Résiliation demandée le <?= date('d/m/Y', strtotime($bail['resiliation_demandee_le'])) ?> — l'administration va vous recontacter.</p>
          </div>
          <?php else: ?>
          <button onclick="document.getElementById('resiForm-<?= $bail['id'] ?>').classList.toggle('hidden')"
                  class="text-[11px] font-black text-accent uppercase tracking-widest hover:underline">
            <i class="fas fa-file-signature mr-1"></i>Demander la résiliation de ce bail
          </button>
          <form method="POST" id="resiForm-<?= $bail['id'] ?>" class="hidden mt-3 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="demander_resiliation">
            <input type="hidden" name="espace_id" value="<?= $bail['id'] ?>">
            <textarea name="resiliation_note" rows="2" placeholder="Motif (optionnel)"
                      class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-3 py-2.5 text-xs sm:text-sm font-medium text-primary outline-none focus:border-primary resize-none"></textarea>
            <button type="submit" onclick="return confirm('Confirmer votre demande de résiliation ? L\'administration vous recontactera pour finaliser.')"
                    class="bg-primary text-white text-[11px] font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
              Envoyer la demande
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'notifications'): ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
        <h2 class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
          <i class="fas fa-bell text-accent"></i> Notifications
        </h2>
      </div>
      <?php if (!$notifsClient): ?>
      <div class="p-10 text-center text-slate-400 italic text-sm">Aucune notification pour le moment.</div>
      <?php else: ?>
      <div class="divide-y divide-slate-50">
        <?php foreach ($notifsClient as $n):
            $icones = [
                'reservation_validee'  => ['fa-calendar-check', 'bg-emerald-50 text-emerald-600'],
                'reservation_refusee'  => ['fa-calendar-times',  'bg-red-50 text-red-600'],
                'reservation_expiree'  => ['fa-clock',           'bg-slate-100 text-slate-500'],
                'paiement_confirme'    => ['fa-check-double',    'bg-green-50 text-green-600'],
            ];
            [$ic, $cls] = $icones[$n['type']] ?? ['fa-bell', 'bg-slate-100 text-slate-500'];
        ?>
        <a href="<?= e($n['lien'] ?: '#') ?>" class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 transition <?= !$n['lu'] ? 'bg-blue-50/30' : '' ?>">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 <?= $cls ?>"><i class="fas <?= $ic ?> text-sm"></i></div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-700"><?= e($n['message']) ?></p>
            <p class="text-[10px] text-slate-400 mt-1"><?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?></p>
          </div>
          <?php if (!$n['lu']): ?><span class="w-2 h-2 bg-accent rounded-full flex-shrink-0 mt-2"></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($tab === 'profil'): ?>

    <?php if ($msg): ?>
    <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
      <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
      <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 md:p-8">
      <h2 class="font-black text-primary uppercase italic text-sm tracking-tight mb-6 flex items-center gap-2">
        <i class="fas fa-user-circle text-accent"></i> Informations du compte
      </h2>
      <form method="POST" class="grid sm:grid-cols-2 gap-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_profil">
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom complet <span class="text-accent">*</span></label>
          <input type="text" name="nom_complet" required value="<?= e($me['nom_complet']) ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Email <span class="text-accent">*</span></label>
          <input type="email" name="email" required value="<?= e($me['email']) ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Téléphone</label>
          <input type="text" name="telephone" value="<?= e($me['telephone'] ?? '') ?>"
                 class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Membre depuis</label>
          <div class="w-full rounded-xl bg-slate-100 px-4 py-3 font-bold text-slate-400 text-sm"><?= date('d/m/Y', strtotime($me['created_at'] ?? 'now')) ?></div>
        </div>
        <div class="sm:col-span-2">
          <button type="submit" class="flex items-center gap-2 bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">
            <i class="fas fa-save"></i> Enregistrer les modifications
          </button>
        </div>
      </form>
      <div class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap gap-3">
        <a href="contact.php" class="flex items-center gap-2 text-xs font-black text-primary border border-slate-200 hover:border-primary px-4 py-2.5 rounded-xl transition">
          <i class="fas fa-envelope text-accent"></i> Nous contacter
        </a>
        <a href="logout.php" onclick="return confirm('Se déconnecter ?')"
           class="flex items-center gap-2 text-xs font-black text-red-500 border border-red-100 hover:border-red-300 hover:bg-red-50 px-4 py-2.5 rounded-xl transition">
          <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
      </div>
    </div>

    <?php endif; ?>
  </div>
</div>

<script>
function toggleChoixEspace(id) {
    const choix = document.querySelector(`#reqForm-${id} input[name="choix"]:checked`)?.value;
    const wrap = document.getElementById('choixEspaceWrap-' + id);
    const details = document.getElementById('detailsChoix-' + id);
    wrap.classList.toggle('hidden', choix !== 'autre_espace');
    if (choix === 'autre_espace') {
        details.placeholder = 'Précisions supplémentaires (optionnel)';
        syncDetailsChoix(id);
    } else if (choix === 'nouvelle_date') {
        details.placeholder = 'Date souhaitée (optionnel)';
        details.value = '';
    } else {
        details.placeholder = 'Précisions (optionnel)';
        details.value = '';
    }
}
function syncDetailsChoix(id) {
    const select = document.getElementById('choixEspaceSelect-' + id);
    const details = document.getElementById('detailsChoix-' + id);
    details.value = select.value;
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>