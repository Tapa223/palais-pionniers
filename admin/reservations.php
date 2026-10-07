<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_espaces','admin_comptable','ministre']);
expirer_reservations_non_payees();

$role     = $_SESSION['role'] ?? '';
$readonly = is_readonly_admin();

$pdo = db();
$msg = null;

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

                figer_montant_initial($pdo, $id);

                $transfert = transferer_paiements_requisition($pdo, $id);

                $annulees = ($transfert['transfere'] > 0 && empty($resa['date_depart']))
                    ? annuler_reservations_concurrentes($pdo, $id)
                    : [];

                log_activity('reservation_validee', 'reservations', "Réservation #$id validée (réquisition #$reqIdVal)");

                $pdo->commit();

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = ['error', e(message_erreur($e, 'Validation impossible : erreur technique.'))];
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
                $montantAPayer = $sfB['solde'];
                $suiteClient = " Les paiements déjà rattachés ({$fmt($sfB['paye_net'])} FCFA) restent acquis"
                    . ($sfB['solde'] > 0 ? " : il reste {$fmt($sfB['solde'])} FCFA à régler au guichet." : ' : aucun nouveau paiement n\'est nécessaire.');
                notify('admin_comptable', 'reservation_validee',
                    "Réservation " . ref_resa($id) . " de nouveau validée (" . ref_req($reqIdVal) . ") — {$fmt($sfB['paye_net'])} FCFA déjà rattachés"
                    . ($sfB['solde'] > 0 ? ", solde à encaisser : {$fmt($sfB['solde'])} FCFA" : '') . '.',
                    "paiements.php?resa=$id"
                );
            } else {
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
        $chkStatut = $pdo->prepare("SELECT statut FROM reservations WHERE id = ?");
        $chkStatut->execute([$id]);
        if ($chkStatut->fetchColumn() === 'requisitionnee') {
            $msg = ['error', 'Une réservation réquisitionnée ne peut pas être remise en attente : son traitement se fait depuis la réquisition.'];
            goto finAnnuler;
        }
        if ($blocageB = reservation_requisition_blocage($pdo, $id, 'annuler')) {
            $msg = ['error', $blocageB];
            goto finAnnuler;
        }
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'en_attente', notification_vue = 0 WHERE id = ?");
        $stmt->execute([$id]); log_activity('reservation_en_attente','reservations','Réservation #'.($id??0).' remise en attente');
        if (partenaires_disponibles($pdo) && $stmt->rowCount() === 1) {
            $resaAtt = $pdo->prepare("SELECT r.user_id, r.partenaire_id, r.date_resa, e.nom AS espace_nom FROM reservations r JOIN espaces e ON e.id = r.espace_id WHERE r.id = ?");
            $resaAtt->execute([$id]);
            $resaAtt = $resaAtt->fetch();
            if ($resaAtt && !empty($resaAtt['partenaire_id'])) {
                notify('', 'reservation_en_attente',
                    "Votre réservation " . ref_resa((int)$id) . " pour « {$resaAtt['espace_nom']} » du " . date('d/m/Y', strtotime($resaAtt['date_resa'])) . " est de nouveau en attente de validation par l'administration du Palais.",
                    "mon-compte.php?tab=reservations", (int)$resaAtt['user_id']
                );
            }
        }
        $msg = ['ok', 'La demande est de nouveau en attente.'];
        finAnnuler:
    } elseif ($action === 'supprimer') {
        if (!is_superadmin()) {
            $msg = ['error', 'Seule la Direction peut supprimer une réservation.'];
        } elseif (!dossier_reservation($pdo, $id)) {
            $msg = ['error', 'Réservation introuvable : elle a peut-être déjà été supprimée.'];
        } elseif (strtoupper(trim((string)($_POST['confirmation'] ?? ''))) !== ref_resa($id)) {
            $msg = ['error', 'Suppression non effectuée : pour confirmer, saisissez exactement la référence ' . e(ref_resa($id)) . '.'];
            $_GET['supprimer'] = $id;
        } else {
            try {
                $dSupp = supprimer_dossier_reservation($pdo, $id);
                $msg = ['ok', 'Dossier supprimé définitivement : ' . e(implode(', ', $dSupp['refs']))
                    . ' (' . $dSupp['paiements'] . ' paiement(s), ' . number_format($dSupp['encaisse'], 0, ',', ' ') . ' FCFA). '
                    . 'Il n\'apparaît plus dans les compteurs, les rapports ni les exports ; la suppression reste tracée dans le journal.'];
            } catch (Throwable $e) {
                error_log('Suppression réservation #' . $id . ' : ' . $e->getMessage());
                $msg = ['error', 'La suppression n\'a pas pu être effectuée : rien n\'a été modifié.'];
            }
        }

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

$partenairesActifs = partenaires_disponibles($pdo);
$listePartenaires  = $partenairesActifs ? $pdo->query("SELECT id, nom FROM partenaires ORDER BY nom")->fetchAll() : [];
$filterPartenaire  = (string)($_GET['partenaire'] ?? '');
if ($filterPartenaire !== 'tous' && $filterPartenaire !== 'aucun' && !in_array((int)$filterPartenaire, array_map('intval', array_column($listePartenaires, 'id')), true)) {
    $filterPartenaire = '';
}

$whereResa  = [];
$paramsResa = [];
if ($partenairesActifs && $filterPartenaire === 'tous')  { $whereResa[] = 'r.partenaire_id IS NOT NULL'; }
if ($partenairesActifs && $filterPartenaire === 'aucun') { $whereResa[] = 'r.partenaire_id IS NULL'; }
if ($partenairesActifs && ctype_digit($filterPartenaire)) { $whereResa[] = 'r.partenaire_id = ?'; $paramsResa[] = (int)$filterPartenaire; }
if ($filterCanal)  { $whereResa[] = 'r.canal = ?';     $paramsResa[] = $filterCanal; }
if ($filterStatut) { $whereResa[] = 'r.statut = ?';    $paramsResa[] = $filterStatut; }
if ($filterEspace) { $whereResa[] = 'r.espace_id = ?'; $paramsResa[] = $filterEspace; }
if ($filterDate) {
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
$filtreResaActif = $filterCanal || $filterStatut || $filterEspace || $filterDate || $filterQ !== '' || $filterPartenaire !== '';

$stmtResa = $pdo->prepare("
    SELECT r.*, e.nom as espace_nom, u.nom_complet as user_nom, u.telephone as user_tel,
           t.libelle as tarif_nom, t.montant as tarif_prix
           " . ($partenairesActifs ? ", pa.nom AS partenaire_nom" : ", NULL AS partenaire_nom") . "
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    " . ($partenairesActifs ? "LEFT JOIN partenaires pa ON pa.id = r.partenaire_id" : "") . "
    " . ($whereResa ? 'WHERE ' . implode(' AND ', $whereResa) : '') . "
    ORDER BY " . ($partenairesActifs
        ? "(r.statut = 'en_attente' AND r.partenaire_id IS NOT NULL) DESC, "
        : "") . "r.created_at DESC
");
$stmtResa->execute($paramsResa);
$reservations = $stmtResa->fetchAll();
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

    <?php
    $dossierSupp = (is_superadmin() && isset($_GET['supprimer']) && (!$msg || $msg[0] !== 'ok'))
        ? dossier_reservation($pdo, (int)$_GET['supprimer']) : null;
    ?>
    <?php if ($dossierSupp): $refSupp = ref_resa((int)$_GET['supprimer']); ?>
        <div id="confirmationSuppression" style="scroll-margin-top:5rem" class="mb-6 rounded-2xl border-2 border-red-200 bg-red-50 p-6">
            <h2 class="font-black text-red-700 uppercase italic text-sm flex items-center gap-2"><i class="fas fa-trash-alt"></i> Supprimer définitivement <?= e($refSupp) ?></h2>
            <p class="text-sm text-slate-700 mt-2">À utiliser pour une erreur de saisie, un test ou une formation. La réservation et tout ce qui y est rattaché seront effacés, et n'apparaîtront plus dans les compteurs, les rapports ni les exports. La suppression reste tracée dans le journal d'activité.</p>
            <dl class="grid sm:grid-cols-2 gap-3 text-sm mt-4">
                <div><dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">Client · espace · date</dt><dd class="font-bold text-slate-800"><?= e($dossierSupp['client']) ?> · <?= e($dossierSupp['espace']) ?> · <?= date('d/m/Y', strtotime($dossierSupp['date'])) ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">Réservations supprimées</dt><dd class="font-bold text-slate-800"><?= e(implode(', ', $dossierSupp['refs'])) ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">Paiements effacés</dt><dd class="font-bold text-slate-800"><?= $dossierSupp['paiements'] ?> · <?= number_format($dossierSupp['encaisse'], 0, ',', ' ') ?> FCFA</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-widest text-slate-400">Réquisition · remboursements</dt><dd class="font-bold text-slate-800"><?= $dossierSupp['requisitions'] ? e(implode(', ', array_map('ref_req', $dossierSupp['requisitions']))) : 'Aucune' ?><?= $dossierSupp['rembourse'] > 0 ? ' · ' . number_format($dossierSupp['rembourse'], 0, ',', ' ') . ' FCFA remboursés' : '' ?></dd></div>
            </dl>
            <?php if (count($dossierSupp['reservations']) > 1): ?>
            <p class="text-xs font-bold text-red-700 mt-3"><i class="fas fa-link mr-1"></i>Ces réservations forment un même dossier de réquisition : elles sont supprimées ensemble.</p>
            <?php endif; ?>
            <?php if ($dossierSupp['encaisse'] > 0): ?>
            <p class="text-xs font-bold text-red-700 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Des paiements sont enregistrés : ne supprimez ce dossier que s'il s'agit bien d'une erreur ou d'un test.</p>
            <?php endif; ?>
            <form method="post" class="mt-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= (int)$_GET['supprimer'] ?>">
                <input type="hidden" name="action" value="supprimer">
                <label class="block">
                    <span class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Pour confirmer, saisissez <?= e($refSupp) ?></span>
                    <input type="text" name="confirmation" required autocomplete="off" placeholder="<?= e($refSupp) ?>"
                           class="rounded-xl border-2 border-red-200 bg-white px-4 py-2.5 font-black text-primary outline-none focus:border-accent text-sm uppercase">
                </label>
                <button type="submit" class="bg-red-600 text-white px-5 py-3 rounded-xl hover:bg-red-700 transition text-[10px] font-black uppercase tracking-widest"><i class="fas fa-trash-alt mr-1"></i>Supprimer définitivement</button>
                <a href="reservations.php" class="px-4 py-3 rounded-xl text-[10px] font-black uppercase text-slate-500 hover:bg-white transition">Annuler</a>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl border <?= $msg[0] === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200' ?>">
            <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $msg[1] ?>
        </div>
    <?php endif; ?>

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
        <?php if ($partenairesActifs): ?>
        <select name="partenaire" class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none text-primary">
            <option value="">Tous les clients</option>
            <option value="tous" <?= $filterPartenaire === 'tous' ? 'selected' : '' ?>>Partenaires uniquement</option>
            <option value="aucun" <?= $filterPartenaire === 'aucun' ? 'selected' : '' ?>>Clients classiques</option>
            <?php foreach ($listePartenaires as $pf): ?>
            <option value="<?= (int)$pf['id'] ?>" <?= $filterPartenaire === (string)$pf['id'] ? 'selected' : '' ?>>Partenaire : <?= e($pf['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
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
                        <?php if (!empty($res['partenaire_nom'])): ?>
                        <span class="inline-flex items-center gap-1 mt-1 text-[9px] font-black uppercase tracking-widest text-indigo-700 bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-full">
                            <i class="fas fa-handshake"></i> Partenaire · <?= e($res['partenaire_nom']) ?>
                        </span>
                        <?php endif; ?>
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
                        <?php if (!empty($res['effectuee_le'])): ?>
                        <p class="text-[9px] text-emerald-700 font-bold mt-1">
                            <i class="fas fa-clipboard-check"></i> Effectuée le <?= date('d/m/Y', strtotime($res['effectuee_le'])) ?>
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
                            <?php if (is_superadmin()): ?>
                            <a href="reservations.php?supprimer=<?= (int)$res['id'] ?>#confirmationSuppression"
                               class="ml-3 text-[10px] font-black text-slate-400 hover:text-red-600 transition" title="Supprimer cette réservation (erreur ou test)"><i class="fas fa-trash-alt mr-1"></i>Supprimer</a>
                            <?php endif; ?>
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