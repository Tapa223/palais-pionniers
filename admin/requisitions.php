<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin_comptable', 'ministre']);

$pdo = db();
$readonly = is_readonly_admin();
$msg = null;

$libellesChoix = [
    'annulation' => 'Annulation',
    'remboursement' => 'Remboursement',
    'nouvelle_date' => 'Nouvelle date',
    'changement_espace' => 'Changement d’espace',
    'autre_espace' => 'Autre espace'
];

$modesRemboursement = [
    'especes' => 'Espèces',
    'orange_money' => 'Orange Money',
    'moov_money' => 'Moov Money',
    'virement' => 'Virement bancaire',
    'cheque' => 'Chèque'
];

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {

        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($id > 0) {

            try {

                $pdo->beginTransaction();

                $stmtReq = $pdo->prepare("
                    SELECT
                        rm.*,
                        r.user_id,
                        r.date_resa,
                        r.date_depart,
                        r.heure_debut,
                        r.heure_fin,
                        r.espace_id,
                        r.tarif_id,
                        e.nom AS espace_nom,
                        u.nom_complet,
                        u.telephone,
                        u.email,
                        (
                            SELECT COALESCE(SUM(p.montant), 0)
                            FROM paiements p
                            WHERE p.reservation_id = r.id
                        ) AS montant_verse
                    FROM requisitions_ministerielles rm
                    JOIN reservations r
                        ON r.id = rm.reservation_id
                    JOIN espaces e
                        ON e.id = r.espace_id
                    JOIN users u
                        ON u.id = r.user_id
                    WHERE rm.id = ?
                    FOR UPDATE
                ");

                $stmtReq->execute([$id]);
                $requisition = $stmtReq->fetch(PDO::FETCH_ASSOC);

                if (!$requisition) {
                    throw new RuntimeException(
                        'Réquisition introuvable.'
                    );
                }

                if (empty($requisition['choix_client'])) {
                    throw new RuntimeException(
                        'Le client n’a pas encore effectué son choix.'
                    );
                }

                if (!in_array(
                    $requisition['statut'],
                    ['choix_recu', 'en_traitement'],
                    true
                )) {
                    throw new RuntimeException(
                        'Cette réquisition n’est plus disponible pour traitement.'
                    );
                }

                $stmtOperation = $pdo->prepare("
                    SELECT *
                    FROM operations_requisition
                    WHERE requisition_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmtOperation->execute([$id]);
                $operation = $stmtOperation->fetch(PDO::FETCH_ASSOC);

                if (!$operation) {
                    throw new RuntimeException(
                        'Aucune opération comptable n’est associée à cette réquisition.'
                    );
                }

                if ($operation['statut'] === 'traitee') {
                    throw new RuntimeException(
                        'Cette opération a déjà été traitée.'
                    );
                }

                if (
                    $action === 'traiter_operation'
                    && in_array($requisition['choix_client'], ['nouvelle_date', 'autre_espace'], true)
                ) {
                    $blocagesCloture = requisition_blocages_cloture($pdo, $id);

                    if ($blocagesCloture) {
                        throw new RuntimeException(
                            'La réquisition ne peut pas encore être clôturée : ' . implode(' ', $blocagesCloture)
                        );
                    }
                }

                if ($action === 'prendre_en_charge') {

                    $pdo->prepare("
                        UPDATE requisitions_ministerielles
                        SET statut = 'en_traitement'
                        WHERE id = ?
                    ")->execute([$id]);

                    $pdo->prepare("
                        UPDATE operations_requisition
                        SET
                            statut = 'en_cours',
                            agent_assigne = ?,
                            date_prise_en_charge = NOW()
                        WHERE id = ?
                    ")->execute([
                        $userId,
                        $operation['id']
                    ]);

                    $pdo->commit();

                    log_activity(
                        'requisition_prise_en_charge',
                        'reservations',
                        "Réquisition #$id prise en charge par l’utilisateur #$userId"
                    );

                    notify(
                        'admin_comptable',
                        'requisition_traitement',
                        "La réquisition #$id est maintenant en cours de traitement.",
                        'requisitions.php'
                    );

                    $msg = [
                        'ok',
                        'La réquisition a été prise en charge.'
                    ];
                }

                elseif (
                    $action === 'traiter_remboursement'
                    && $requisition['choix_client'] === 'remboursement'
                ) {

                    $rembAttendu = requisition_remboursement_attendu($pdo, $id);
                    $montantPaye = $rembAttendu['type'] === 'remboursement' ? $rembAttendu['montant'] : 0.0;
                    $montantARembourser = $montantPaye;

                    $montantRembourse = (float)(
                        $_POST['montant_rembourse'] ?? 0
                    );

                    $mode = trim($_POST['mode'] ?? '');
                    $reference = trim($_POST['reference'] ?? '');
                    $note = trim($_POST['note'] ?? '');
                    $resultat = trim($_POST['resultat'] ?? '');

                    if ($montantPaye <= 0) {
                        throw new RuntimeException(
                            'Aucun paiement enregistré pour cette réservation. Aucun remboursement ne peut être effectué.'
                        );
                    }

                    if ($montantRembourse <= 0) {
                        throw new RuntimeException(
                            'Veuillez saisir le montant réellement remboursé.'
                        );
                    }

                    if (
                        abs(
                            $montantRembourse
                            - $montantARembourser
                        ) > 0.5
                    ) {
                        throw new RuntimeException(
                            'Le remboursement doit porter sur la totalité du montant dû au client : '
                            . number_format($montantARembourser, 0, ',', ' ') . ' FCFA (montant réellement payé).'
                        );
                    }

                    if (!array_key_exists(
                        $mode,
                        $modesRemboursement
                    )) {
                        throw new RuntimeException(
                            'Veuillez sélectionner un mode de remboursement valide.'
                        );
                    }

                    if ($reference === '') {
                        throw new RuntimeException(
                            'Veuillez renseigner la référence ou le justificatif de l’opération.'
                        );
                    }

                    if ($resultat !== 'effectue') {
                        throw new RuntimeException(
                            'Le remboursement doit être marqué comme effectué pour clôturer la réquisition.'
                        );
                    }

                    $stmtRemb = $pdo->prepare("
                        SELECT *
                        FROM remboursements
                        WHERE operation_id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    $stmtRemb->execute([
                        $operation['id']
                    ]);

                    $remboursement =
                        $stmtRemb->fetch(PDO::FETCH_ASSOC);

                    if ($remboursement) {

                        if (
                            $remboursement['montant_rembourse']
                            !== null
                        ) {
                            throw new RuntimeException(
                                'Un remboursement a déjà été enregistré pour cette opération.'
                            );
                        }

                        $pdo->prepare("
                            UPDATE remboursements
                            SET
                                montant_paye = ?,
                                montant_a_rembourser = ?,
                                montant_rembourse = ?,
                                mode = ?,
                                reference = ?,
                                date_traitement = NOW(),
                                traite_par = ?,
                                resultat = ?,
                                note = ?
                            WHERE id = ?
                        ")->execute([
                            $montantPaye,
                            $montantARembourser,
                            $montantRembourse,
                            $mode,
                            $reference,
                            $userId,
                            $resultat,
                            $note ?: null,
                            $remboursement['id']
                        ]);

                    } else {

                        $motif = trim(
                            $requisition['details_choix']
                            ?: 'Remboursement demandé par le client à la suite d’une réquisition ministérielle.'
                        );

                        $pdo->prepare("
                            INSERT INTO remboursements (
                                operation_id,
                                requisition_id,
                                reservation_id,
                                client_id,
                                montant_paye,
                                montant_a_rembourser,
                                montant_rembourse,
                                motif,
                                mode,
                                reference,
                                date_demande,
                                date_traitement,
                                traite_par,
                                resultat,
                                note
                            )
                            VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?
                            )
                        ")->execute([
                            $operation['id'],
                            $id,
                            $requisition['reservation_id'],
                            $requisition['user_id'],
                            $montantPaye,
                            $montantARembourser,
                            $montantRembourse,
                            $motif,
                            $mode,
                            $reference,
                            $userId,
                            $resultat,
                            $note ?: null
                        ]);
                    }

                    $pdo->prepare("
                        UPDATE operations_requisition
                        SET
                            statut = 'traitee',
                            traite_par = ?,
                            date_traitement = NOW(),
                            resultat = ?,
                            reference = ?,
                            note = ?
                        WHERE id = ?
                    ")->execute([
                        $userId,
                        $resultat,
                        $reference,
                        $note ?: null,
                        $operation['id']
                    ]);

                    $noteTraitement =
                        'Remboursement effectué par '
                        . ($modesRemboursement[$mode] ?? $mode)
                        . ' — montant : '
                        . number_format(
                            $montantRembourse,
                            0,
                            ',',
                            ' '
                        )
                        . ' FCFA'
                        . (
                            $reference !== ''
                                ? ' — Réf. : ' . $reference
                                : ''
                        );

                    if ($note !== '') {
                        $noteTraitement .=
                            ' — ' . $note;
                    }

                    $pdo->prepare("
                        UPDATE requisitions_ministerielles
                        SET
                            statut = 'cloturee',
                            traite_par = ?,
                            date_traitement = NOW(),
                            note_traitement = ?
                        WHERE id = ?
                    ")->execute([
                        $userId,
                        $noteTraitement,
                        $id
                    ]);

                    $pdo->commit();

                    log_activity(
                        'requisition_remboursement',
                        'reservations',
                        "Remboursement de la réquisition #$id effectué pour "
                        . number_format(
                            $montantRembourse,
                            0,
                            ',',
                            ' '
                        )
                        . " FCFA par l’utilisateur #$userId"
                    );

                    notify(
                        'admin_comptable',
                        'requisition_remboursement',
                        "Le remboursement de la réquisition #$id a été enregistré : "
                        . number_format(
                            $montantRembourse,
                            0,
                            ',',
                            ' '
                        )
                        . " FCFA.",
                        'requisitions.php'
                    );

                    $msg = [
                        'ok',
                        'Le remboursement a été enregistré et la réquisition est maintenant clôturée.'
                    ];
                }

                elseif (
                    $action === 'traiter_operation'
                    && $requisition['choix_client'] !== 'remboursement'
                ) {

                    $note = trim(
                        $_POST['note_traitement'] ?? ''
                    );

                    if (
                        $requisition['choix_client']
                        === 'annulation'
                    ) {

                        $resultat =
                            'Annulation de la réservation suite à la réquisition ministérielle.';

                    } elseif (
                        $requisition['choix_client']
                        === 'nouvelle_date'
                    ) {

                        $resultat =
                            'Demande de nouvelle date enregistrée et traitée.';

                    } elseif (
                        in_array(
                            $requisition['choix_client'],
                            [
                                'changement_espace',
                                'autre_espace'
                            ],
                            true
                        )
                    ) {

                        $resultat =
                            'Demande de changement d’espace enregistrée et traitée.';

                    } else {

                        $resultat =
                            'Opération traitée.';
                    }

                    if ($note !== '') {
                        $resultat .= ' ' . $note;
                    }

                    $pdo->prepare("
                        UPDATE operations_requisition
                        SET
                            statut = 'traitee',
                            traite_par = ?,
                            date_traitement = NOW(),
                            resultat = ?,
                            note = ?
                        WHERE id = ?
                    ")->execute([
                        $userId,
                        $resultat,
                        $note ?: null,
                        $operation['id']
                    ]);

                    $pdo->prepare("
                        UPDATE requisitions_ministerielles
                        SET
                            statut = 'cloturee',
                            traite_par = ?,
                            date_traitement = NOW(),
                            note_traitement = ?
                        WHERE id = ?
                    ")->execute([
                        $userId,
                        $resultat,
                        $id
                    ]);

                    $pdo->commit();

                    log_activity(
                        'requisition_traitee',
                        'reservations',
                        "Réquisition #$id traitée par l’utilisateur #$userId"
                    );

                    $msg = [
                        'ok',
                        'La réquisition a été traitée et clôturée.'
                    ];
                }

                else {

                    throw new RuntimeException(
                        'Action non reconnue ou incompatible avec le choix du client.'
                    );
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $msg = ['err', message_erreur($e)];
            }
        }
    }
}

$filtreStatut =
    $_GET['statut'] ?? 'en_attente_choix';

$where = '1=1';

if ($filtreStatut === 'en_attente_choix') {

    $where = "
        rm.statut = 'en_attente_choix'
        AND rm.choix_client IS NULL
    ";

} elseif ($filtreStatut === 'choix_fait') {

    $where = "
        rm.statut IN ('choix_recu', 'en_traitement')
        AND rm.choix_client IS NOT NULL
    ";

} elseif ($filtreStatut === 'traite') {

    $where = "
        rm.statut IN ('cloturee', 'traite', 'annulee')
    ";
}

$stmt = $pdo->query("
    SELECT
        rm.*,

        r.date_resa,
        r.date_depart,
        r.heure_debut,
        r.heure_fin,

        r.user_id AS client_id,

        e.nom AS espace_nom,

        u.nom_complet,
        u.telephone,
        u.email,

        admin1.nom_complet AS declenche_par_nom,
        admin2.nom_complet AS traite_par_nom,

        (
            SELECT COALESCE(SUM(p.montant), 0)
            FROM paiements p
            WHERE p.reservation_id = r.id
        ) AS montant_verse,

        (
            SELECT o.id
            FROM operations_requisition o
            WHERE o.requisition_id = rm.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS operation_id,

        (
            SELECT o.statut
            FROM operations_requisition o
            WHERE o.requisition_id = rm.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS operation_statut,

        (
            SELECT o.description
            FROM operations_requisition o
            WHERE o.requisition_id = rm.id
            ORDER BY o.id DESC
            LIMIT 1
        ) AS operation_description

    FROM requisitions_ministerielles rm

    JOIN reservations r
        ON r.id = rm.reservation_id

    JOIN espaces e
        ON e.id = r.espace_id

    JOIN users u
        ON u.id = r.user_id

    LEFT JOIN users admin1
        ON admin1.id = rm.declenche_par

    LEFT JOIN users admin2
        ON admin2.id = rm.traite_par

    WHERE $where

    ORDER BY
        CASE
            WHEN rm.statut = 'choix_recu'
                 THEN 0
            WHEN rm.statut = 'en_traitement'
                 THEN 1
            ELSE 2
        END,
        rm.date_declenchee DESC
");

$requisitions =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

$compteurs = [

    'en_attente_choix' =>
        (int)$pdo->query("
            SELECT COUNT(*)
            FROM requisitions_ministerielles
            WHERE statut = 'en_attente_choix'
              AND choix_client IS NULL
        ")->fetchColumn(),

    'choix_fait' =>
        (int)$pdo->query("
            SELECT COUNT(*)
            FROM requisitions_ministerielles
            WHERE statut IN ('choix_recu', 'en_traitement')
              AND choix_client IS NOT NULL
        ")->fetchColumn(),

    'traite' =>
        (int)$pdo->query("
            SELECT COUNT(*)
            FROM requisitions_ministerielles
            WHERE statut IN ('cloturee', 'traite', 'annulee')
        ")->fetchColumn()
];

$pageTitle =
    'Réquisitions ministérielles';

require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">

    <div class="mb-6">

        <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">
            <i class="fas fa-landmark text-amber-500 mr-2"></i>
            Réquisitions ministérielles
        </h1>

        <p class="text-sm text-slate-500 mt-0.5">
            Suivi complet des espaces réquisitionnés pour un besoin institutionnel prioritaire
        </p>

        <div class="flex flex-wrap gap-2 mt-3">
            <a href="export.php?type=requisitions" target="_blank"
               class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
                <i class="fas fa-file-excel"></i> Export réquisitions
            </a>
            <a href="export.php?type=remboursements" target="_blank"
               class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
                <i class="fas fa-file-excel"></i> Export remboursements
            </a>
        </div>

    </div>

    <?php if ($msg): ?>

        <div class="mb-5 rounded-2xl p-4 flex items-center gap-3
            <?= $msg[0] === 'ok'
                ? 'bg-green-50 border border-green-200 text-green-700'
                : 'bg-red-50 border border-red-200 text-red-700'
            ?>">

            <i class="fas <?= $msg[0] === 'ok'
                ? 'fa-check-circle text-green-500'
                : 'fa-exclamation-circle text-red-500'
            ?>"></i>

            <span class="font-bold text-sm">
                <?= e($msg[1]) ?>
            </span>

        </div>

    <?php endif; ?>

    <div class="flex gap-2 mb-6 flex-wrap">

        <a
            href="?statut=en_attente_choix"
            class="text-xs font-black uppercase px-4 py-2 rounded-xl transition
            <?= $filtreStatut === 'en_attente_choix'
                ? 'bg-primary text-white'
                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
            ?>"
        >
            En attente du choix client
            (<?= $compteurs['en_attente_choix'] ?>)
        </a>

        <a
            href="?statut=choix_fait"
            class="text-xs font-black uppercase px-4 py-2 rounded-xl transition
            <?= $filtreStatut === 'choix_fait'
                ? 'bg-amber-500 text-white'
                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
            ?>"
        >
            Choix fait, à traiter
            (<?= $compteurs['choix_fait'] ?>)
        </a>

        <a
            href="?statut=traite"
            class="text-xs font-black uppercase px-4 py-2 rounded-xl transition
            <?= $filtreStatut === 'traite'
                ? 'bg-emerald-500 text-white'
                : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
            ?>"
        >
            Traitées
            (<?= $compteurs['traite'] ?>)
        </a>

    </div>

    <?php if (!$requisitions): ?>

        <div class="bg-white rounded-2xl border border-slate-100 py-14 text-center">

            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i class="fas fa-inbox text-xl text-slate-300"></i>
            </div>

            <p class="text-sm font-black text-slate-400 uppercase">
                Aucune réquisition dans cette catégorie.
            </p>

        </div>

    <?php else: ?>

        <div class="space-y-3">

            <?php foreach ($requisitions as $rq): ?>

                <?php

                $choix = $rq['choix_client'];

                $estRemboursement =
                    $choix === 'remboursement';

                $estEnTraitement =
                    in_array(
                        $rq['statut'],
                        ['choix_recu', 'en_traitement'],
                        true
                    );

                $estCloturee =
                    in_array(
                        $rq['statut'],
                        ['cloturee', 'traite', 'annulee'],
                        true
                    );

                $operationDisponible =
                    !empty($rq['operation_id']);

                ?>

                <div
                    class="bg-white rounded-2xl border
                    <?= $choix && $estEnTraitement
                        ? 'border-amber-200'
                        : 'border-slate-100'
                    ?>
                    shadow-sm overflow-hidden"
                >

                    <div class="p-4 sm:p-5">

                        <div class="flex items-center gap-3 min-w-0">

                            <div
                                class="w-11 h-11 rounded-xl
                                <?= $estRemboursement
                                    ? 'bg-amber-50 text-amber-600'
                                    : 'bg-primary/5 text-primary'
                                ?>
                                flex items-center justify-center flex-shrink-0"
                            >

                                <i class="fas
                                    <?= $estRemboursement
                                        ? 'fa-coins'
                                        : 'fa-landmark'
                                    ?>">
                                </i>

                            </div>

                            <div class="min-w-0 flex-1">

                                <div class="flex flex-wrap items-center gap-2">

                                    <p class="font-black text-primary text-sm">
                                        <?= e($rq['nom_complet']) ?>
                                    </p>

                                    <span
                                        class="text-[9px] font-black uppercase px-2 py-1 rounded-full
                                        <?= $estCloturee
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : (
                                                $rq['statut'] === 'en_traitement'
                                                    ? 'bg-blue-100 text-blue-700'
                                                    : (
                                                        $choix
                                                            ? 'bg-amber-100 text-amber-700'
                                                            : 'bg-slate-100 text-slate-500'
                                                    )
                                            )
                                        ?>"
                                    >

                                        <?php if (
                                            $rq['statut'] === 'cloturee'
                                            || $rq['statut'] === 'traite'
                                        ): ?>

                                            Traitée

                                        <?php elseif (
                                            $rq['statut'] === 'annulee'
                                        ): ?>

                                            Annulée

                                        <?php elseif (
                                            $rq['statut'] === 'en_traitement'
                                        ): ?>

                                            En traitement

                                        <?php elseif ($choix): ?>

                                            Choix fait — à traiter

                                        <?php else: ?>

                                            En attente du client

                                        <?php endif; ?>

                                    </span>

                                </div>

                                <p class="text-xs text-slate-500 mt-1 truncate">

                                    <?= e($rq['espace_nom']) ?>

                                    <span class="text-slate-300 mx-1">•</span>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime($rq['date_resa'])
                                    ) ?>

                                </p>

                                <?php if ($choix): ?>

                                    <p class="text-[10px] text-amber-600 font-bold mt-1">

                                        <i class="fas fa-hand-point-right mr-1"></i>

                                        <?= e(
                                            $libellesChoix[$choix] ?? $choix
                                        ) ?>

                                        <?php if ($estRemboursement): ?>

                                            <span class="text-slate-400 font-semibold">
                                                —
                                                <?= number_format(
                                                    (float)$rq['montant_verse'],
                                                    0,
                                                    ',',
                                                    ' '
                                                ) ?>
                                                FCFA
                                            </span>

                                        <?php endif; ?>

                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                    <div class="border-t border-slate-100 bg-slate-50/50 px-4 sm:px-5 py-3">

                        <div class="flex items-center justify-between gap-3">

                            <div class="min-w-0">

                                <?php if ($estRemboursement && $estEnTraitement): ?>

                                    <span class="inline-flex items-center text-[10px] font-black uppercase text-amber-600 bg-amber-50 border border-amber-100 px-3 py-2 rounded-xl">

                                        <i class="fas fa-coins mr-1.5"></i>

                                        <?= number_format(
                                            (float)$rq['montant_verse'],
                                            0,
                                            ',',
                                            ' '
                                        ) ?>

                                        FCFA à rembourser

                                    </span>

                                <?php elseif ($rq['statut'] === 'en_traitement'): ?>

                                    <span class="text-[10px] font-bold text-blue-600">

                                        <i class="fas fa-spinner mr-1"></i>

                                        Dossier en cours de traitement

                                    </span>

                                <?php elseif ($choix && $estEnTraitement): ?>

                                    <span class="text-[10px] font-semibold text-slate-400">
                                        Opération en attente de traitement
                                    </span>

                                <?php elseif ($estCloturee): ?>

                                    <span class="text-[10px] font-semibold text-emerald-600">

                                        <i class="fas fa-check-circle mr-1"></i>

                                        Opération clôturée

                                    </span>

                                <?php endif; ?>

                            </div>

                            <div class="flex items-center justify-end gap-2 flex-shrink-0">

                                <?php if (
                                    !$readonly
                                    && $estEnTraitement
                                    && $operationDisponible
                                ): ?>

                                    <a
                                        href="requisition-detail.php?id=<?= (int)$rq['id'] ?>"
                                        class="inline-flex items-center justify-center gap-2 bg-primary text-white text-[10px] font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition shadow-sm"
                                    >

                                        <i class="fas fa-arrow-up-right-from-square"></i>

                                        Traiter

                                    </a>

                                <?php elseif (
                                    $readonly
                                    && $estEnTraitement
                                ): ?>

                                    <a
                                        href="requisition-detail.php?id=<?= (int)$rq['id'] ?>"
                                        class="inline-flex items-center justify-center gap-2 bg-slate-100 text-primary text-[10px] font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-200 transition"
                                    >

                                        <i class="fas fa-eye"></i>

                                        Voir

                                    </a>

                                <?php endif; ?>

                                <?php if ($estCloturee): ?>

                                    <a
                                        href="requisition-detail.php?id=<?= (int)$rq['id'] ?>"
                                        class="inline-flex items-center justify-center gap-2 bg-slate-100 text-slate-600 text-[10px] font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-200 transition"
                                    >

                                        <i class="fas fa-eye"></i>

                                        Détails

                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<div
    id="choixRequisitionModal"
    class="fixed inset-0 z-[100] hidden overflow-hidden"
    aria-hidden="true"
>

    <div
        class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        onclick="fermerChoixRequisition()"
    ></div>

    <div
        class="relative z-10 flex h-full w-full items-center justify-center p-4"
    >

        <div
            class="flex h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
        >

            <div
                class="flex flex-shrink-0 items-center justify-between border-b border-slate-100 bg-white px-5 py-4"
            >

                <div>

                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                        Réquisition ministérielle
                    </p>

                    <p class="mt-0.5 text-sm font-black text-primary">
                        Que souhaitez-vous faire ?
                    </p>

                </div>

                <button
                    type="button"
                    onclick="fermerChoixRequisition()"
                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200"
                >

                    <i class="fas fa-times"></i>

                </button>

            </div>

            <div
                id="choixRequisitionModalContent"
                class="min-h-0 flex-1 overflow-y-auto p-5"
            ></div>

        </div>

    </div>

</div>


<script>

function ouvrirChoixRequisition(id) {

    const form =
        document.getElementById(
            'reqForm-' + id
        );

    const modal =
        document.getElementById(
            'choixRequisitionModal'
        );

    const content =
        document.getElementById(
            'choixRequisitionModalContent'
        );

    if (!form || !modal || !content) {
        return;
    }

    content.innerHTML = '';

    const wrapper =
        document.createElement('div');

    wrapper.innerHTML =
        form.outerHTML;

    const clonedForm =
        wrapper.firstElementChild;

    if (!clonedForm) {
        return;
    }

    content.appendChild(
        clonedForm
    );

    modal.classList.remove(
        'hidden'
    );

    modal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.classList.add(
        'overflow-hidden'
    );

    initialiserChoixRequisition(
        id
    );
}


function fermerChoixRequisition() {

    const modal =
        document.getElementById(
            'choixRequisitionModal'
        );

    const content =
        document.getElementById(
            'choixRequisitionModalContent'
        );

    if (!modal) {
        return;
    }

    modal.classList.add(
        'hidden'
    );

    modal.setAttribute(
        'aria-hidden',
        'true'
    );

    if (content) {
        content.innerHTML = '';
    }

    document.body.classList.remove(
        'overflow-hidden'
    );
}


function initialiserChoixRequisition(id) {

    const form =
        document.querySelector(
            '#choixRequisitionModalContent form'
        );

    if (!form) {
        return;
    }

    const choixInputs =
        form.querySelectorAll(
            'input[name="choix"]'
        );

    choixInputs.forEach(
        function(input) {

            input.addEventListener(
                'change',
                function() {

                    mettreAJourChoixModal(
                        form,
                        id
                    );

                }
            );

        }
    );


    const dateInput =
        form.querySelector(
            '#choixDate-' + id
        );

    if (dateInput) {

        dateInput.addEventListener(
            'change',
            function() {

                const details =
                    form.querySelector(
                        '#detailsChoix-' + id
                    );

                if (details) {
                    details.value =
                        this.value;
                }

            }
        );

    }


    const espaceSelect =
        form.querySelector(
            '#choixEspaceSelect-' + id
        );

    if (espaceSelect) {

        espaceSelect.addEventListener(
            'change',
            function() {

                const details =
                    form.querySelector(
                        '#detailsChoix-' + id
                    );

                if (details) {
                    details.value =
                        this.value;
                }

            }
        );

    }


    form.addEventListener(
        'submit',
        function(event) {

            const choix =
                form.querySelector(
                    'input[name="choix"]:checked'
                )?.value;

            const details =
                form.querySelector(
                    '#detailsChoix-' + id
                );


            if (
                choix === 'nouvelle_date'
            ) {

                const dateInput =
                    form.querySelector(
                        '#choixDate-' + id
                    );

                if (
                    !dateInput
                    || !dateInput.value
                ) {

                    event.preventDefault();

                    alert(
                        'Veuillez choisir une nouvelle date dans le calendrier.'
                    );

                    if (dateInput) {
                        dateInput.focus();
                    }

                    return;
                }

                if (details) {
                    details.value =
                        dateInput.value;
                }

            }


            if (
                choix === 'autre_espace'
            ) {

                const espaceSelect =
                    form.querySelector(
                        '#choixEspaceSelect-' + id
                    );

                if (
                    !espaceSelect
                    || !espaceSelect.value
                ) {

                    event.preventDefault();

                    alert(
                        'Veuillez choisir un autre espace.'
                    );

                    if (espaceSelect) {
                        espaceSelect.focus();
                    }

                    return;
                }

                if (details) {
                    details.value =
                        espaceSelect.value;
                }

            }


            if (
                choix === 'annulation'
                || choix === 'remboursement'
            ) {

                if (details) {
                    details.value = '';
                }

            }

        }
    );

}


function mettreAJourChoixModal(
    form,
    id
) {

    const choix =
        form.querySelector(
            'input[name="choix"]:checked'
        )?.value;

    const dateWrap =
        form.querySelector(
            '#choixDateWrap-' + id
        );

    const dateInput =
        form.querySelector(
            '#choixDate-' + id
        );

    const espaceWrap =
        form.querySelector(
            '#choixEspaceWrap-' + id
        );

    const espaceSelect =
        form.querySelector(
            '#choixEspaceSelect-' + id
        );

    const details =
        form.querySelector(
            '#detailsChoix-' + id
        );

    const nouvelleDate =
        choix === 'nouvelle_date';

    const autreEspace =
        choix === 'autre_espace';


    if (dateWrap) {

        dateWrap.classList.toggle(
            'hidden',
            !nouvelleDate
        );

    }


    if (espaceWrap) {

        espaceWrap.classList.toggle(
            'hidden',
            !autreEspace
        );

    }


    if (dateInput) {

        dateInput.disabled =
            !nouvelleDate;

        dateInput.required =
            nouvelleDate;

    }


    if (espaceSelect) {

        espaceSelect.disabled =
            !autreEspace;

        espaceSelect.required =
            autreEspace;

    }


    if (details) {

        if (nouvelleDate) {

            details.value =
                dateInput?.value || '';

        } else if (autreEspace) {

            details.value =
                espaceSelect?.value || '';

        } else {

            details.value = '';

        }

    }

}


document.addEventListener(
    'keydown',
    function(event) {

        const modal =
            document.getElementById(
                'choixRequisitionModal'
            );

        if (
            event.key === 'Escape'
            && modal
            && !modal.classList.contains('hidden')
        ) {

            fermerChoixRequisition();

        }

    }
);

</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>