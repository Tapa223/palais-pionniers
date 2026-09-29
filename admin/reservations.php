<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_espaces','admin_comptable','ministre']);
expirer_reservations_non_payees();

// Seul admin_espaces (+ superadmin) peut valider/refuser/annuler.
// Comptable et ministre ont un accès en lecture seule à cette page.
$role     = $_SESSION['role'] ?? '';
$readonly = is_readonly_admin();

$pdo = db();
$msg = null;

// --- TRAITEMENT DES ACTIONS (admin_espaces / superadmin uniquement) ---
if (!$readonly && isset($_POST['action'])) {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['error', 'Requête invalide.'];
    } else {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($action === 'valider') {
        $resa = $pdo->prepare("SELECT r.*, e.nom AS espace_nom, u.nom_complet FROM reservations r JOIN espaces e ON e.id=r.espace_id JOIN users u ON u.id=r.user_id WHERE r.id=?");
        $resa->execute([$id]);
        $resa = $resa->fetch();

        if (!$resa) {
            $msg = ['error', 'Réservation introuvable.'];
            goto finValider;
        }

        /*
         * Réservation issue d'une réquisition (nouvelle date / autre espace) :
         * même validation par admin_espaces, mais faite dans une transaction
         * qui vérifie la réquisition et rattache les paiements déjà encaissés
         * sur la réservation d'origine (aucun nouvel encaissement n'est créé).
         */
        if (!empty($resa['requisition_id'])) {
            $reqIdVal = (int)$resa['requisition_id'];
            try {
                $pdo->beginTransaction();

                $lock = $pdo->prepare("SELECT statut FROM reservations WHERE id = ? FOR UPDATE");
                $lock->execute([$id]);
                if ($lock->fetchColumn() !== 'en_attente') {
                    throw new RuntimeException("Cette demande n'est plus en attente de validation.");
                }

                $reqLock = $pdo->prepare("SELECT statut FROM requisitions_ministerielles WHERE id = ? FOR UPDATE");
                $reqLock->execute([$reqIdVal]);
                $reqStatut = $reqLock->fetchColumn();
                if (!in_array($reqStatut, ['choix_recu', 'en_traitement'], true)) {
                    throw new RuntimeException("La réquisition #$reqIdVal n'est plus ouverte : cette demande ne peut pas être validée.");
                }

                $autre = $pdo->prepare("SELECT id FROM reservations WHERE requisition_id = ? AND id != ? AND statut IN ('validee','requisitionnee') LIMIT 1");
                $autre->execute([$reqIdVal, $id]);
                if ($autreId = $autre->fetchColumn()) {
                    throw new RuntimeException("La réservation #$autreId est déjà validée pour la réquisition #$reqIdVal.");
                }

                if (!empty($resa['date_depart'])) {
                    if (!tarif_disponible($pdo, (int)$resa['tarif_id'], $resa['date_resa'], $resa['date_depart'], null, null, $id, (int)$resa['quantite'])) {
                        throw new RuntimeException("Plus assez de chambres disponibles sur cette période pour valider cette demande.");
                    }
                } elseif ($occupant = creneau_occupe_par_reservation_payee($pdo, (int)$resa['espace_id'], $resa['date_resa'], $resa['heure_debut'], $resa['heure_fin'], $id)) {
                    throw new RuntimeException("Le créneau est déjà occupé par la réservation #$occupant, validée et réglée.");
                }

                $pdo->prepare("UPDATE reservations SET statut = 'validee', date_validation = NOW(), notification_vue = 0 WHERE id = ?")
                    ->execute([$id]);

                // Tarif normal figé avant le rattachement des paiements
                figer_montant_initial($pdo, $id);

                $transfert = transferer_paiements_requisition($pdo, $id);

                // Comme après un encaissement : les autres demandes validées non payées
                // sur ce créneau sont départagées (mode créneau uniquement).
                $annulees = ($transfert['transfere'] > 0 && empty($resa['date_depart']))
                    ? annuler_reservations_concurrentes($pdo, $id)
                    : [];

                log_activity('reservation_validee', 'reservations', "Réservation #$id validée (réquisition #$reqIdVal)");

                $pdo->commit();

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = ['error', $e instanceof RuntimeException ? e($e->getMessage()) : 'Validation impossible : erreur technique.'];
                if (!$e instanceof RuntimeException) error_log('Validation réservation réquisition #' . $id . ' : ' . $e->getMessage());
                goto finValider;
            }

            $dateTxt = date('d/m/Y', strtotime($resa['date_resa']));
            $fmt = fn($m) => number_format((float)$m, 0, ',', ' ');

            if ($transfert['transfere'] > 0) {
                $suiteClient = " Le paiement déjà versé ({$fmt($transfert['transfere'])} FCFA) a été rattaché à cette nouvelle réservation";
                if ($transfert['statut'] === 'paye') {
                    $suiteClient .= ' : aucun nouveau paiement n\'est nécessaire.';
                    if ($transfert['trop_percu'] > 0) {
                        $suiteClient .= " Le trop-perçu de {$fmt($transfert['trop_percu'])} FCFA vous sera remboursé par le service comptable.";
                    }
                } else {
                    $suiteClient .= " : il reste {$fmt($transfert['solde'])} FCFA à régler au guichet.";
                }
                notify('admin_comptable', 'reservation_validee',
                    "Réquisition #$reqIdVal : réservation #$id validée pour «{$resa['espace_nom']}» le $dateTxt — {$fmt($transfert['transfere'])} FCFA transférés"
                    . ($transfert['solde'] > 0 ? ", solde à encaisser : {$fmt($transfert['solde'])} FCFA" : '')
                    . ($transfert['trop_percu'] > 0 ? ", trop-perçu à rembourser : {$fmt($transfert['trop_percu'])} FCFA" : '') . '.',
                    "requisition-detail.php?id=$reqIdVal"
                );
            } elseif (($sfB = situation_financiere_reservation($pdo, $id)) && $sfB['total_paye'] > 0) {
                // Revalidation : les paiements avaient déjà été rattachés à B lors
                // d'une validation précédente (aucun nouveau transfert).
                $montantAPayer = $sfB['solde'];
                $suiteClient = " Les paiements déjà rattachés ({$fmt($sfB['paye_net'])} FCFA) restent acquis"
                    . ($sfB['solde'] > 0 ? " : il reste {$fmt($sfB['solde'])} FCFA à régler au guichet." : ' : aucun nouveau paiement n\'est nécessaire.');
                notify('admin_comptable', 'reservation_validee',
                    "Réservation " . ref_resa($id) . " de nouveau validée (" . ref_req($reqIdVal) . ") — {$fmt($sfB['paye_net'])} FCFA déjà rattachés"
                    . ($sfB['solde'] > 0 ? ", solde à encaisser : {$fmt($sfB['solde'])} FCFA" : '') . '.',
                    "paiements.php?resa=$id"
                );
            } else {
                // Réservation initiale non payée : aucun transfert, aucun
                // remboursement ; paiement attendu dans le délai habituel de 48 h.
                $montantAPayer = $sfB ? $sfB['solde'] : 0.0;
                $mentionTarif = ($sfB && $sfB['prise_en_charge_requisition'])
                    ? " (tarif de votre réservation initiale maintenu)"
                    : '';
                $suiteClient = " Montant à régler : {$fmt($montantAPayer)} FCFA$mentionTarif, au guichet avant le "
                    . date('d/m/Y à H:i', limite_paiement(date('Y-m-d H:i:s')))
                    . " (48 h) — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.";
                notify('admin_comptable', 'reservation_validee',
                    "Réservation " . ref_resa($id) . " validée pour «{$resa['espace_nom']}» le $dateTxt — {$resa['nom_complet']} (" . ref_req($reqIdVal) . ", réservation initiale non payée) : {$fmt($montantAPayer)} FCFA à encaisser sous 48 h",
                    "paiements.php?resa=$id"
                );
            }

            notify('superadmin', 'reservation_validee',
                "Réservation validée pour «{$resa['espace_nom']}» le $dateTxt — {$resa['nom_complet']} (réquisition #$reqIdVal)",
                "reservations.php"
            );
            notify('', 'reservation_validee',
                "Votre nouvelle réservation pour « {$resa['espace_nom']} » (suite à la réquisition) est validée !" . $suiteClient,
                "mon-compte.php", (int)$resa['user_id']
            );

            $msg = ['ok', 'Réservation confirmée (réquisition #' . $reqIdVal . ').'
                . ($transfert['transfere'] > 0
                    ? ' ' . $fmt($transfert['transfere']) . ' FCFA déjà encaissés y ont été rattachés.'
                    : ($sfB['total_paye'] > 0
                        ? ' Paiements déjà rattachés : ' . $fmt($sfB['paye_net']) . ' FCFA' . ($sfB['solde'] > 0 ? ', solde ' . $fmt($sfB['solde']) . ' FCFA.' : ', réservation réglée.')
                        : ' Aucun paiement sur la réservation initiale : ' . $fmt($montantAPayer) . ' FCFA à régler sous 48 h. Le comptable a été notifié pour l\'encaissement.'))
                . ($annulees ? ' ' . count($annulees) . ' demande(s) concurrente(s) annulée(s).' : '')];
            goto finValider;
        }

        $estSejourResaVal = !empty($resa['date_depart']);

        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'validee', date_validation = NOW(), notification_vue = 0 WHERE id = ? AND statut = 'en_attente'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            $msg = ['error', 'Cette demande n\'est plus en attente de validation.'];
            goto finValider;
        }
        // Tarif normal (avant toute réduction) figé à la validation
        figer_montant_initial($pdo, $id);
        log_activity('reservation_validee','reservations','Réservation #'.($id??0).' validée');

        if ($resa) {
            notify('admin_comptable', 'reservation_validee',
                "Réservation validée pour «{$resa['espace_nom']}» le ".date('d/m/Y',strtotime($resa['date_resa']))." — {$resa['nom_complet']} : paiement à encaisser",
                "paiements.php"
            );
            notify('superadmin', 'reservation_validee',
                "Réservation validée pour «{$resa['espace_nom']}» le ".date('d/m/Y',strtotime($resa['date_resa']))." — {$resa['nom_complet']}",
                "reservations.php"
            );
            notify('', 'reservation_validee',
                "Votre demande pour « {$resa['espace_nom']} » est validée ! Merci de régler au guichet avant le " . date('d/m/Y à H:i', limite_paiement($resa['date_validation'] ?? date('Y-m-d H:i:s'))) . " — en cas de créneau partagé avec une autre demande, la salle revient au premier qui règle le paiement.",
                "mon-compte.php", (int)$resa['user_id']
            );
        }
        $msg = ['ok', 'Réservation confirmée. Le comptable a été notifié pour l\'encaissement.'];
        finValider:

    } elseif ($action === 'refuser') {
        // Réservation B issue d'une réquisition : pas de refus si elle porte des
        // paiements (transférés ou encaissés) ni si la réquisition est clôturée.
        $chkReq = $pdo->prepare("SELECT r.requisition_id FROM reservations r WHERE r.id = ?");
        $chkReq->execute([$id]);
        $chkReq = $chkReq->fetch();
        if ($blocageB = reservation_requisition_blocage($pdo, $id, 'refuser')) {
            $msg = ['error', $blocageB];
            goto finRefus;
        }
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'refusee', notification_vue = 0 WHERE id = ?");
        $stmt->execute([$id]); log_activity('reservation_refusee','reservations','Réservation #'.($id??0).' refusée — Note: '.(trim($_POST['note_admin']??'')?:'-'));

        $resaRefus = $pdo->prepare("SELECT r.user_id, r.tarif_id, r.date_resa, r.heure_debut, e.nom AS espace_nom FROM reservations r JOIN espaces e ON e.id=r.espace_id WHERE r.id=?");
        $resaRefus->execute([$id]);
        if ($resaRefus = $resaRefus->fetch()) {
            $noteClient = trim($_POST['note_admin'] ?? '');
            $suggestion = '';
            if (!empty($resaRefus['heure_debut'])) {
                $creneaux = creneaux_libres_du_jour($pdo, $resaRefus['tarif_id'], $resaRefus['date_resa']);
                if ($creneaux) $suggestion = ' Créneaux encore libres ce jour-là : ' . implode(', ', $creneaux) . '.';
            }
            notify('', 'reservation_refusee',
                "Votre demande pour « {$resaRefus['espace_nom']} » n'a pas pu être acceptée." . ($noteClient ? " Motif : $noteClient" : '') . $suggestion,
                "mon-compte.php", (int)$resaRefus['user_id']
            );
        }
        if ($chkReq && !empty($chkReq['requisition_id'])) {
            $reqIdRefus = (int)$chkReq['requisition_id'];
            if ($resaRefus) {
                notify('', 'reservation_refusee',
                    "Votre réquisition reste ouverte : vous pouvez déposer une nouvelle demande depuis votre espace (bouton « Finaliser ma nouvelle réservation »).",
                    "mon-compte.php", (int)$resaRefus['user_id']
                );
            }
            notify('admin_comptable', 'reservation_refusee',
                "Réquisition #$reqIdRefus : la nouvelle réservation #$id du client a été refusée. Le client peut en déposer une autre.",
                "requisition-detail.php?id=$reqIdRefus"
            );
        }
        $msg = ['error', 'Réservation refusée.'];
        finRefus:

    } elseif ($action === 'annuler') {
        // Une réservation réquisitionnée ne revient jamais « en attente » :
        // son devenir est géré par la réquisition.
        $chkStatut = $pdo->prepare("SELECT statut FROM reservations WHERE id = ?");
        $chkStatut->execute([$id]);
        if ($chkStatut->fetchColumn() === 'requisitionnee') {
            $msg = ['error', 'Une réservation réquisitionnée ne peut pas être remise en attente : son traitement se fait depuis la réquisition.'];
            goto finAnnuler;
        }
        // Réservation B issue d'une réquisition : pas de remise en attente si
        // elle porte des paiements, si la réquisition est clôturée, ou si elle
        // a été refusée / a expiré (le client dépose une nouvelle demande).
        if ($blocageB = reservation_requisition_blocage($pdo, $id, 'annuler')) {
            $msg = ['error', $blocageB];
            goto finAnnuler;
        }
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'en_attente', notification_vue = 0 WHERE id = ?");
        $stmt->execute([$id]); log_activity('reservation_en_attente','reservations','Réservation #'.($id??0).' remise en attente');
        $msg = ['ok', 'La demande est de nouveau en attente.'];
        finAnnuler:
    } elseif ($action === 'requisitionner') {
        if (!is_superadmin() && $role !== 'admin_comptable') {
            $msg = ['error', 'Seuls la Direction ou le Comptable peuvent déclencher une réquisition institutionnelle.'];
            goto finRequisition;
        }
        $motifsAutorises = [
            "Cet espace a été réquisitionné dans le cadre d'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers.",
            "Cet espace a été réquisitionné dans le cadre d'une activité officielle du Gouvernement de la République du Mali.",
            "Cet espace a été réquisitionné en raison d'une urgence nationale.",
        ];
        $motifReq = trim($_POST['motif_requisition'] ?? '');
        if (!in_array($motifReq, $motifsAutorises, true)) {
            $msg = ['error', 'Merci de sélectionner un motif de réquisition.'];
            goto finRequisition;
        }
        $resaReq = $pdo->prepare("SELECT r.*, e.nom AS espace_nom FROM reservations r JOIN espaces e ON e.id = r.espace_id WHERE r.id = ? AND r.statut = 'validee'");
        $resaReq->execute([$id]);
        $resaReq = $resaReq->fetch();
        if (!$resaReq) {
            $msg = ['error', 'Seule une réservation déjà validée peut être réquisitionnée.'];
            goto finRequisition;
        }

        $pdo->prepare("UPDATE reservations SET statut = 'requisitionnee', notification_vue = 0 WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO requisitions_ministerielles (reservation_id, motif, declenche_par) VALUES (?,?,?)")
            ->execute([$id, $motifReq, $_SESSION['user_id']]);

        notify('', 'requisition_ministerielle',
            "Votre réservation pour « {$resaReq['espace_nom']} » a été réquisitionnée pour un besoin institutionnel prioritaire. Merci de choisir une option de dédommagement dans votre espace.",
            "mon-compte.php", (int)$resaReq['user_id']
        );
        log_activity('reservation_requisitionnee', 'reservations', "Réservation #$id réquisitionnée — motif : $motifReq");
        $msg = ['ok', 'Réservation réquisitionnée. Le client a été notifié et doit choisir une option de dédommagement.'];
        finRequisition:
    }
    }
}

// --- RÉCUPÉRATION DES DONNÉES (Correction de u.nom -> u.nom_complet) ---
// Filtres simples : canal (existant), statut, client / téléphone / n°, salle, date
$statutsFiltre = ['en_attente' => 'En attente', 'validee' => 'Validées', 'refusee' => 'Refusées',
                  'annulee' => 'Annulées', 'expiree' => 'Expirées', 'requisitionnee' => 'Réquisitionnées'];
$filterCanal  = in_array($_GET['canal'] ?? '', ['en_ligne','guichet'], true) ? $_GET['canal'] : '';
$filterStatut = isset($statutsFiltre[$_GET['statut'] ?? '']) ? $_GET['statut'] : '';
$filterQ      = trim((string)($_GET['q'] ?? ''));
$filterEspace = (int)($_GET['espace'] ?? 0);
$filterDate   = (string)($_GET['date'] ?? '');
$dObj = DateTime::createFromFormat('!Y-m-d', $filterDate);
if (!$dObj || $dObj->format('Y-m-d') !== $filterDate) {
    $filterDate = '';
}

$whereResa  = [];
$paramsResa = [];
if ($filterCanal)  { $whereResa[] = 'r.canal = ?';     $paramsResa[] = $filterCanal; }
if ($filterStatut) { $whereResa[] = 'r.statut = ?';    $paramsResa[] = $filterStatut; }
if ($filterEspace) { $whereResa[] = 'r.espace_id = ?'; $paramsResa[] = $filterEspace; }
if ($filterDate) {
    // Date couverte par la réservation (créneau : le jour ; séjour : de l'arrivée à la veille du départ)
    $whereResa[] = '(r.date_resa = ? OR (r.date_depart IS NOT NULL AND r.date_resa <= ? AND r.date_depart > ?))';
    array_push($paramsResa, $filterDate, $filterDate, $filterDate);
}
if ($filterQ !== '') {
    $condQ = 'u.nom_complet LIKE ? OR u.telephone LIKE ? OR u.email LIKE ?';
    array_push($paramsResa, "%$filterQ%", "%$filterQ%", "%$filterQ%");
    if (preg_match('/^#?(?:RESA-?)?(\d+)$/i', $filterQ, $mQ)) {
        $condQ .= ' OR r.id = ?';
        $paramsResa[] = (int)$mQ[1];
    }
    $whereResa[] = "($condQ)";
}
$filtreResaActif = $filterCanal || $filterStatut || $filterEspace || $filterDate || $filterQ !== '';

$stmtResa = $pdo->prepare("
    SELECT r.*, e.nom as espace_nom, u.nom_complet as user_nom, u.telephone as user_tel,
           t.libelle as tarif_nom, t.montant as tarif_prix
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    " . ($whereResa ? 'WHERE ' . implode(' AND ', $whereResa) : '') . "
    ORDER BY r.created_at DESC
");
$stmtResa->execute($paramsResa);
$reservations = $stmtResa->fetchAll();
// Références de dossier (RESA-B · REQ-X · issue de RESA-A) en une requête
$refsListe = references_dossiers($pdo, array_column($reservations, 'id'));

$espacesFiltre = $pdo->query("SELECT id, nom FROM espaces ORDER BY nom")->fetchAll();

require __DIR__ . '/_admin_header.php';
?>

<div class="px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tighter">Suivi des Réservations</h1>
            <p class="text-sm text-slate-500 mt-1">Gestion des flux et paiements</p>
        </div>
        <a href="export.php?type=reservations" target="_blank"
           class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
            <i class="fas fa-file-excel"></i> Exporter en Excel
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl border <?= $msg[0] === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200' ?>">
            <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $msg[1] ?>
        </div>
    <?php endif; ?>

    <!-- Recherche -->
    <form method="GET" class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-5">
        <?php if ($filterCanal): ?><input type="hidden" name="canal" value="<?= e($filterCanal) ?>"><?php endif; ?>
        <div class="relative col-span-2">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
            <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Client, téléphone, n° de réservation…"
                   class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 outline-none focus:border-primary text-primary">
        </div>
        <select name="espace" class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none text-primary">
            <option value="0">Toutes les salles</option>
            <?php foreach ($espacesFiltre as $espF): ?>
            <option value="<?= (int)$espF['id'] ?>" <?= $filterEspace === (int)$espF['id'] ? 'selected' : '' ?>><?= e($espF['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?= e($filterDate) ?>" title="Date de la réservation"
               class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none focus:border-primary text-primary">
        <select name="statut" class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none text-primary">
            <option value="">Tous les statuts</option>
            <?php foreach ($statutsFiltre as $cleS => $libS): ?>
            <option value="<?= $cleS ?>" <?= $filterStatut === $cleS ? 'selected' : '' ?>><?= $libS ?></option>
            <?php endforeach; ?>
        </select>
        <div class="flex flex-wrap items-center gap-2 col-span-2">
            <button type="submit" class="text-xs font-black bg-primary text-white px-4 py-2 rounded-xl hover:bg-slate-800 transition">Rechercher</button>
            <?php if ($filtreResaActif): ?>
            <a href="reservations.php" class="text-xs font-bold text-slate-400 px-2 py-2 hover:text-accent transition">Réinitialiser</a>
            <span class="text-[11px] font-bold text-slate-500"><?= count($reservations) ?> résultat(s)<?= $filterCanal ? ' — ' . ($filterCanal === 'guichet' ? 'guichet' : 'en ligne') : '' ?></span>
            <?php endif; ?>
        </div>
    </form>

    <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-xl overflow-hidden">
        <?php if (!$reservations): ?>
        <p class="p-10 text-center text-sm text-slate-400 italic">Aucune réservation ne correspond à la recherche.</p>
        <?php endif; ?>
        <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Client & Contact</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Détails Réservation</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Date & Heures</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Statut</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($reservations as $res): $maintienTarif = null; ?>
                <tr id="resa-<?= $res['id'] ?>" class="hover:bg-slate-50/50 transition-colors">
                    
                    <td class="p-6">
                        <div class="font-black text-primary uppercase text-sm italic"><?= htmlspecialchars($res['user_nom']) ?></div>
                        <div class="text-xs text-slate-500 mt-1 font-bold italic">
                             <?= htmlspecialchars($res['user_tel'] ?? 'N/A') ?>
                        </div>
                    </td>

                    <td class="p-6">
                        <div class="font-black text-primary uppercase text-sm"><?= htmlspecialchars($res['espace_nom']) ?></div>
                        <?php $refsRes = $refsListe[(int)$res['id']] ?? references_dossier($pdo, (int)$res['id']); ?>
                        <div class="mt-1 text-[10px] font-mono font-black text-slate-400">
                            <?php if ($refsRes['req'] !== ''): ?><i class="fas fa-landmark text-amber-600 mr-1"></i><?php endif; ?>
                            <?= e(libelle_references_dossier($refsRes)) ?>
                        </div>
                        <?php if (!empty($res['requisition_id'])): ?>
                            <?php
                            // Tarif garanti : ce que le client paiera réellement si cette demande est acceptée
                            $maintienTarif = $res['statut'] === 'en_attente' ? estimation_maintien_tarif($pdo, (int)$res['id']) : null;
                            if ($maintienTarif && $maintienTarif['prise_en_charge'] > 0):
                            ?>
                            <div class="mt-1 text-[10px] font-bold text-orange-700">
                                Tarif garanti : <?= number_format($maintienTarif['net_du_origine'], 0, ',', ' ') ?> FCFA au lieu de
                                <?= number_format($maintienTarif['montant_initial'], 0, ',', ' ') ?> FCFA
                                (<?= number_format($maintienTarif['prise_en_charge'], 0, ',', ' ') ?> FCFA pris en charge)
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php if($res['tarif_nom']): ?>
                                <span class="text-[9px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-black uppercase tracking-tighter">
                                    Tarif : <?= htmlspecialchars($res['tarif_nom']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if($res['tarif_prix']): ?>
                                <span class="text-[9px] bg-emerald-100 text-emerald-700 px-2 py-1 rounded font-black uppercase tracking-tighter">
                                    <?= number_format($res['tarif_prix'], 0, '.', ' ') ?> FCFA
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td class="p-6">
                        <?php if ($res['heure_debut']): ?>
                        <div class="text-xs font-black text-primary italic">
                            <?= date('d/m/Y', strtotime($res['date_resa'])) ?>
                        </div>
                        <div class="text-[10px] text-indigo-600 font-black mt-1 uppercase">
                            <?= date('H:i', strtotime($res['heure_debut'])) ?> - <?= date('H:i', strtotime($res['heure_fin'])) ?>
                        </div>
                        <?php else: ?>
                        <div class="text-xs font-black text-primary italic">
                            <?= date('d/m/Y', strtotime($res['date_resa'])) ?> → <?= date('d/m/Y', strtotime($res['date_depart'])) ?>
                        </div>
                        <div class="text-[10px] text-indigo-600 font-black mt-1 uppercase">
                            <?= (int)((strtotime($res['date_depart']) - strtotime($res['date_resa'])) / 86400) ?> nuitée(s)
                            <?php if ((int)($res['quantite'] ?? 1) > 1): ?><span class="text-indigo-600">· <?= (int)$res['quantite'] ?> ch.</span><?php endif; ?>
                            <?php if ($res['petit_dejeuner']): ?><span class="text-amber-600">· petit-déj</span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <td class="p-6">
                        <?php 
                        $statusStyles = [
                            'validee'    => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                            'en_attente' => 'bg-amber-50 text-amber-600 border-amber-100',
                            'refusee'    => 'bg-rose-50 text-rose-600 border-rose-100',
                            'annulee'    => 'bg-slate-100 text-slate-500 border-slate-200',
                            'expiree'    => 'bg-slate-100 text-slate-500 border-slate-200',
                            'requisitionnee' => 'bg-amber-50 text-amber-700 border-amber-100',
                        ];
                        $labels = [
                            'validee' => 'Confirmé', 'refusee' => 'Refusé', 'en_attente' => 'En attente',
                            'annulee' => 'Annulée', 'expiree' => 'Expirée (48h)',
                            'requisitionnee' => 'Réquisitionnée',
                        ];
                        $label = $labels[$res['statut']] ?? $res['statut'];
                        ?>
                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase border <?= $statusStyles[$res['statut']] ?? 'bg-slate-100' ?>">
                            <?= $label ?>
                        </span>
                        <?php if ($res['statut'] === 'validee' && in_array($res['statut_paiement'], ['non_paye', 'attente_paiement'], true) && !empty($res['date_validation'])): ?>
                        <p class="text-[9px] text-amber-600 font-bold mt-1">
                            <i class="fas fa-clock"></i> 1er paiement avant <?= date('d/m H:i', limite_paiement($res['date_validation'])) ?>
                        </p>
                        <?php elseif ($res['statut'] === 'validee' && $res['statut_paiement'] === 'partiellement_paye'): ?>
                        <p class="text-[9px] text-sky-600 font-bold mt-1">
                            <i class="fas fa-coins"></i> Acompte versé — solde au comptable
                        </p>
                        <?php endif; ?>
                    </td>

                    <td class="p-6 text-right">
                        <?php if ($readonly): ?>
                            <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">
                                <i class="fas fa-eye mr-1"></i> Lecture seule
                            </span>
                        <?php else: ?>
                        <form method="post" class="flex justify-end gap-2">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="id" value="<?= $res['id'] ?>">

                            <?php
                                // Réservation B : refus / remise en attente bloqués côté serveur
                                // (paiements, réquisition clôturée) — boutons masqués en conséquence.
                                $blocageRefus = !empty($res['requisition_id']) ? reservation_requisition_blocage($pdo, (int)$res['id'], 'refuser') : null;
                                $blocageAnnul = !empty($res['requisition_id']) ? reservation_requisition_blocage($pdo, (int)$res['id'], 'annuler') : null;
                            ?>
                            <?php if ($res['statut'] === 'en_attente'): ?>
                                <button name="action" value="valider"
                                        <?php if (!empty($maintienTarif) && $maintienTarif['prise_en_charge'] > 0 && !empty($res['requisition_id'])): ?>
                                        onclick="return confirm(<?= e(json_encode('Réquisition : le client paiera ' . number_format($maintienTarif['net_du_origine'], 0, ',', ' ') . ' FCFA (tarif de sa réservation initiale). ' . number_format($maintienTarif['prise_en_charge'], 0, ',', ' ') . ' FCFA seront pris en charge au titre de la réquisition. Accepter cette demande ?', JSON_UNESCAPED_UNICODE)) ?>)"
                                        <?php endif; ?>
                                        class="bg-emerald-500 text-white px-4 py-2 rounded-xl hover:bg-emerald-600 transition text-[10px] font-black uppercase tracking-widest">
                                    Accepter
                                </button>
                                <?php if (!$blocageRefus): ?>
                                <button name="action" value="refuser" class="border border-rose-100 text-rose-500 px-4 py-2 rounded-xl hover:bg-rose-50 transition text-[10px] font-black uppercase tracking-widest" onclick="return confirm('Refuser ?')">
                                    Refuser
                                </button>
                                <?php endif; ?>

                            <?php elseif ($res['statut'] !== 'requisitionnee'): ?>
                                <?php if (!$blocageAnnul): ?>
                                <button name="action" value="annuler" class="bg-slate-900 text-white px-4 py-2 rounded-xl hover:bg-black transition text-[10px] font-black uppercase tracking-widest">
                                    Annuler
                                </button>
                                <?php else: ?>
                                <span class="text-[9px] font-black uppercase text-slate-400 py-2" title="<?= e($blocageAnnul) ?>">
                                    <i class="fas fa-lock mr-1"></i>Suivi par la réquisition
                                </span>
                                <?php endif; ?>
                                <?php if ($res['statut'] === 'validee' && (is_superadmin() || $role === 'admin_comptable')): ?>
                                <button type="button" onclick="document.getElementById('reqModal-<?= $res['id'] ?>').classList.remove('hidden')"
                                        class="bg-amber-500 text-white px-4 py-2 rounded-xl hover:bg-amber-600 transition text-[10px] font-black uppercase tracking-widest">
                                    <i class="fas fa-landmark mr-1"></i>Réquisitionner
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>

                        <?php if ($res['statut'] === 'validee' && (is_superadmin() || $role === 'admin_comptable')): ?>
                        <div id="reqModal-<?= $res['id'] ?>" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                          <div class="bg-white rounded-2xl p-6 max-w-md w-full">
                            <h3 class="font-black text-primary uppercase italic text-sm mb-1"><i class="fas fa-landmark text-amber-500 mr-1"></i>Réquisition institutionnelle</h3>
                            <p class="text-xs text-slate-500 mb-4">Cette réservation sera immédiatement réquisitionnée pour un besoin prioritaire. Le client sera notifié et devra choisir lui-même une option de dédommagement.</p>
                            <form method="POST">
                              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                              <input type="hidden" name="id" value="<?= $res['id'] ?>">
                              <input type="hidden" name="action" value="requisitionner">
                              <div class="space-y-2 mb-4">
                                <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 border-slate-100 cursor-pointer hover:border-amber-300 transition">
                                  <input type="radio" name="motif_requisition" required value="Cet espace a été réquisitionné dans le cadre d'une activité officielle du Ministère de la Jeunesse et des Sports ou du Palais des Pionniers." class="mt-0.5 w-4 h-4 accent-amber-600">
                                  <span class="text-xs font-semibold text-slate-700">Activité du Ministère ou du Palais</span>
                                </label>
                                <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 border-slate-100 cursor-pointer hover:border-amber-300 transition">
                                  <input type="radio" name="motif_requisition" required value="Cet espace a été réquisitionné dans le cadre d'une activité officielle du Gouvernement de la République du Mali." class="mt-0.5 w-4 h-4 accent-amber-600">
                                  <span class="text-xs font-semibold text-slate-700">Activité du Gouvernement</span>
                                </label>
                                <label class="flex items-start gap-2.5 p-3 rounded-xl border-2 border-slate-100 cursor-pointer hover:border-amber-300 transition">
                                  <input type="radio" name="motif_requisition" required value="Cet espace a été réquisitionné en raison d'une urgence nationale." class="mt-0.5 w-4 h-4 accent-amber-600">
                                  <span class="text-xs font-semibold text-slate-700">Urgence nationale</span>
                                </label>
                              </div>
                              <div class="flex gap-2 justify-end">
                                <button type="button" onclick="document.getElementById('reqModal-<?= $res['id'] ?>').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-black text-slate-400 hover:bg-slate-100 transition">Annuler</button>
                                <button type="submit" onclick="return confirm('Confirmer la réquisition de cette réservation ?')" class="bg-amber-500 text-white px-4 py-2 rounded-xl text-xs font-black uppercase hover:bg-amber-600 transition">Réquisitionner</button>
                              </div>
                            </form>
                          </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <div class="text-right mt-1.5">
                            <a href="observations.php?cible_type=reservation&cible_id=<?= $res['id'] ?>"
                               class="text-[10px] font-black text-slate-400 hover:text-accent transition"><i class="fas fa-eye mr-1"></i>Observer</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php if (isset($_GET['id'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cible = document.getElementById('resa-<?= (int)$_GET['id'] ?>');
    if (cible) {
        cible.scrollIntoView({ behavior: 'smooth', block: 'center' });
        cible.classList.add('bg-primary/5');
        setTimeout(() => cible.classList.remove('bg-primary/5'), 3000);
    }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/_admin_footer.php'; ?>