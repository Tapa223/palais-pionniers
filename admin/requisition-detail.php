<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['admin_comptable', 'ministre']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;

/* =========================================================
   LIBELLÉS
========================================================= */

$libellesChoix = [
    'annulation'         => 'Annulation',
    'remboursement'      => 'Remboursement',
    'nouvelle_date'      => 'Nouvelle date',
    'changement_espace'  => 'Changement d’espace',
    'autre_espace'       => 'Autre espace',
];

$libellesStatut = [
    'en_attente_choix' => 'En attente du choix client',
    'choix_recu'       => 'Choix reçu — à traiter',
    'en_traitement'    => 'En traitement',
    'cloturee'         => 'Clôturée',
    'traitee'          => 'Traitée',
    'annulee'          => 'Annulée',
];

$libellesStatutOperation = [
    'a_traiter' => 'À traiter',
    'en_cours'  => 'En cours',
    'traitee'   => 'Traitée',
    'annulee'   => 'Annulée',
];

$modesRemboursement = [
    'especes'       => 'Espèces',
    'orange_money'  => 'Orange Money',
    'moov_money'    => 'Moov Money',
    'virement'      => 'Virement bancaire',
    // Mêmes valeurs que l'ENUM remboursements.mode (et paiements.mode) :
    // l'ancienne valeur « autre » n'existe pas en base.
    'cheque'        => 'Chèque',
];

/* =========================================================
   ID
========================================================= */

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: requisitions.php');
    exit;
}

/* =========================================================
   TRAITEMENT DES ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readonly) {

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {

        $action = $_POST['action'] ?? '';

        /* -------------------------------------------------
           RÉCUPÉRER LA RÉQUISITION
        ------------------------------------------------- */

        $stmt = $pdo->prepare("
            SELECT
                r.*,
                res.date_resa,
                res.date_depart,
                res.heure_debut,
                res.heure_fin,
                res.espace_id,
                res.tarif_id,
                res.user_id,
                u.nom_complet,
                u.telephone,
                u.email,
                e.nom AS espace_nom
            FROM requisitions_ministerielles r
            JOIN reservations res ON res.id = r.reservation_id
            JOIN users u ON u.id = res.user_id
            LEFT JOIN espaces e ON e.id = res.espace_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $rqAction = $stmt->fetch();

        if (!$rqAction) {
            $msg = ['err', 'Réquisition introuvable.'];
        } else {

            /* =============================================
               COMMENCER LE TRAITEMENT
            ============================================= */

            if ($action === 'prendre_en_charge') {

                if (
                    !in_array(
                        $rqAction['statut'],
                        ['choix_recu'],
                        true
                    )
                    || empty($rqAction['choix_client'])
                ) {
                    $msg = [
                        'err',
                        'Cette réquisition ne peut plus être démarrée dans cet état.'
                    ];
                } else {

                    $pdo->beginTransaction();

                    try {

                        /*
                         * On conserve le nom technique de l'action
                         * pour ne pas casser le fonctionnement existant.
                         * Le libellé visible côté interface est :
                         * "Commencer le traitement".
                         */

                        $stmt = $pdo->prepare("
                            UPDATE requisitions_ministerielles
                            SET
                                statut = 'en_traitement',
                                date_traitement = NULL,
                                traite_par = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $id
                        ]);

                        /*
                         * Mettre à jour l'opération active.
                         */

                        $stmtOp = $pdo->prepare("
                            SELECT id
                            FROM operations_requisition
                            WHERE requisition_id = ?
                            ORDER BY id DESC
                            LIMIT 1
                        ");
                        $stmtOp->execute([$id]);
                        $operationId = (int)$stmtOp->fetchColumn();

                        if ($operationId > 0) {

                            $stmt = $pdo->prepare("
                                UPDATE operations_requisition
                                SET
                                    statut = 'en_cours',
                                    agent_assigne = ?,
                                    date_prise_en_charge = NOW()
                                WHERE id = ?
                            ");

                            $stmt->execute([
                                $_SESSION['user_id'],
                                $operationId
                            ]);

                        } else {

                            $stmt = $pdo->prepare("
                                INSERT INTO operations_requisition
                                (
                                    requisition_id,
                                    reservation_id,
                                    type_operation,
                                    statut,
                                    agent_assigne,
                                    date_prise_en_charge
                                )
                                VALUES
                                (?, ?, ?, 'en_cours', ?, NOW())
                            ");

                            /*
                             * Si type_operation n'est pas disponible
                             * dans certaines versions du schéma,
                             * cette branche ne sera normalement jamais
                             * utilisée car une opération existe déjà
                             * depuis le choix client.
                             */

                            /*
                             * Même correspondance que lors du choix client
                             * (mon-compte.php) : « autre_espace » est enregistré
                             * comme « changement_espace » dans l'ENUM
                             * operations_requisition.type_operation.
                             */
                            $typeOperationSecours = [
                                'annulation'    => 'annulation',
                                'remboursement' => 'remboursement',
                                'nouvelle_date' => 'nouvelle_date',
                                'autre_espace'  => 'changement_espace',
                            ][$rqAction['choix_client']] ?? 'autre';

                            $stmt->execute([
                                $id,
                                $rqAction['reservation_id'],
                                $typeOperationSecours,
                                $_SESSION['user_id']
                            ]);
                        }

                        log_activity(
                            'requisition_traitement_commence',
                            'reservations', // module existant de activity_log (ENUM)
                            'Traitement commencé pour la réquisition #' . $id
                        );

                        notify(
                            'admin_comptable',
                            'requisition_en_traitement',
                            'La réquisition #' . $id . ' est maintenant en traitement.',
                            'requisition-detail.php?id=' . $id
                        );

                        $pdo->commit();

                        header(
                            'Location: requisition-detail.php?id='
                            . $id
                            . '&success=traitement'
                        );
                        exit;

                    } catch (Throwable $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $msg = [
                            'err',
                            'Impossible de commencer le traitement : '
                            . $e->getMessage()
                        ];
                    }
                }
            }

            /* =============================================
               TRAITER UN REMBOURSEMENT
            ============================================= */

            elseif ($action === 'traiter_remboursement') {

                /*
                 * Deux cas utilisent ce remboursement existant :
                 * - le choix « remboursement » du client ;
                 * - le trop-perçu constaté lorsqu'une nouvelle réservation
                 *   (nouvelle date / autre espace) moins chère a été validée
                 *   et que les paiements y ont été rattachés. Dans ce cas les
                 *   montants sont fixés côté serveur.
                 */
                $suiviTrop = in_array($rqAction['choix_client'], ['nouvelle_date', 'autre_espace'], true)
                    ? requisition_suivi_nouvelle_reservation($pdo, $id)
                    : null;

                $estTropPercu = $suiviTrop !== null && $suiviTrop['a_rembourser'];

                // Règle commune (requisitions.php utilise la même) : montant réel dû au client
                $rembAttendu = requisition_remboursement_attendu($pdo, (int)$id);

                if ($rembAttendu['type'] === null) {

                    $msg = [
                        'err',
                        'Cette opération ne correspond pas à un remboursement.'
                    ];

                } elseif (abs((float)($_POST['montant_a_rembourser'] ?? 0) - $rembAttendu['montant']) > 0.5) {

                    $msg = [
                        'err',
                        'Le remboursement doit porter sur la totalité du montant dû au client : '
                        . number_format($rembAttendu['montant'], 0, ',', ' ')
                        . ' FCFA' . ($rembAttendu['type'] === 'trop_percu' ? ' (trop-perçu constaté).' : ' (montant réellement payé).')
                    ];

                } elseif ($rqAction['statut'] === 'cloturee') {

                    $msg = [
                        'err',
                        'Cette réquisition est déjà clôturée.'
                    ];

                } else {

                    /*
                     * Montant payé : toujours déterminé côté serveur à partir
                     * de la situation financière (jamais depuis le formulaire).
                     */
                    if ($estTropPercu) {
                        $montantVerse = (float)$suiviTrop['validee']['total_paye'];
                    } else {
                        $situationOrigine = situation_financiere_reservation($pdo, (int)$rqAction['reservation_id']);
                        $montantVerse = $situationOrigine ? (float)$situationOrigine['paye_net'] : 0.0;
                    }
                    $montantARembourser = (float)($_POST['montant_a_rembourser'] ?? 0);
                    $motifRemboursement = $estTropPercu
                        ? 'Remboursement du trop-perçu suite à réquisition ministérielle (nouvelle réservation #'
                            . (int)$suiviTrop['validee']['id'] . ')'
                        : 'Remboursement suite à réquisition ministérielle';
                    $mode = trim($_POST['mode'] ?? '');
                    $reference = trim($_POST['reference'] ?? '');
                    $resultat = trim($_POST['resultat'] ?? '');
                    $note = trim($_POST['note'] ?? '');

                    if ($montantVerse < 0 || $montantARembourser <= 0) {

                        $msg = [
                            'err',
                            'Les montants indiqués sont invalides.'
                        ];

                    } elseif ($montantARembourser > $montantVerse) {

                        $msg = [
                            'err',
                            'Le montant à rembourser ne peut pas dépasser le montant payé.'
                        ];

                    } elseif (!array_key_exists($mode, $modesRemboursement)) {

                        $msg = [
                            'err',
                            'Veuillez sélectionner un mode de remboursement.'
                        ];

                    } elseif (!in_array(
                        $resultat,
                        ['effectue'],
                        true
                    )) {

                        /*
                         * Une réquisition de remboursement ne peut être
                         * clôturée que lorsque le remboursement a réellement
                         * été effectué.
                         */

                        $msg = [
                            'err',
                            'Le remboursement doit être marqué comme effectué pour clôturer la réquisition.'
                        ];

                    } else {

                        $pdo->beginTransaction();

                        try {

                            // Verrou : empêche un double traitement simultané
                            $verrou = $pdo->prepare("SELECT statut FROM requisitions_ministerielles WHERE id = ? FOR UPDATE");
                            $verrou->execute([$id]);
                            if ($verrou->fetchColumn() === 'cloturee') {
                                throw new RuntimeException('Cette réquisition est déjà clôturée.');
                            }

                            /*
                             * Récupérer l'opération active.
                             */

                            $stmtOp = $pdo->prepare("
                                SELECT *
                                FROM operations_requisition
                                WHERE requisition_id = ?
                                ORDER BY id DESC
                                LIMIT 1
                            ");
                            $stmtOp->execute([$id]);
                            $operation = $stmtOp->fetch();

                            $operationId = $operation
                                ? (int)$operation['id']
                                : null;

                            /*
                             * Vérifier s'il existe déjà un remboursement
                             * pour cette réquisition.
                             */

                            $stmtRb = $pdo->prepare("
                                SELECT id
                                FROM remboursements
                                WHERE requisition_id = ?
                                ORDER BY id DESC
                                LIMIT 1
                            ");
                            $stmtRb->execute([$id]);
                            $remboursementId = (int)$stmtRb->fetchColumn();

                            if ($remboursementId > 0) {

                                $stmt = $pdo->prepare("
                                    UPDATE remboursements
                                    SET
                                        operation_id = ?,
                                        reservation_id = ?,
                                        client_id = ?,
                                        montant_paye = ?,
                                        montant_a_rembourser = ?,
                                        montant_rembourse = ?,
                                        motif = ?,
                                        mode = ?,
                                        reference = ?,
                                        date_traitement = NOW(),
                                        traite_par = ?,
                                        resultat = ?,
                                        note = ?
                                    WHERE id = ?
                                ");

                                $stmt->execute([
                                    $operationId,
                                    $rqAction['reservation_id'],
                                    $rqAction['user_id'],
                                    $montantVerse,
                                    $montantARembourser,
                                    $montantARembourser,
                                    $motifRemboursement,
                                    $mode,
                                    $reference,
                                    $_SESSION['user_id'],
                                    $resultat,
                                    $note,
                                    $remboursementId
                                ]);

                            } else {

                                $stmt = $pdo->prepare("
                                    INSERT INTO remboursements
                                    (
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
                                    VALUES
                                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)
                                ");

                                $stmt->execute([
                                    $operationId,
                                    $id,
                                    $rqAction['reservation_id'],
                                    $rqAction['user_id'],
                                    $montantVerse,
                                    $montantARembourser,
                                    $montantARembourser,
                                    $motifRemboursement,
                                    $mode,
                                    $reference,
                                    $_SESSION['user_id'],
                                    $resultat,
                                    $note
                                ]);
                            }

                            /*
                             * Fermer l'opération.
                             */

                            if ($operationId) {

                                $stmt = $pdo->prepare("
                                    UPDATE operations_requisition
                                    SET
                                        statut = 'traitee',
                                        traite_par = ?,
                                        date_traitement = NOW(),
                                        resultat = ?,
                                        reference = ?,
                                        note = ?
                                    WHERE id = ?
                                ");

                                $stmt->execute([
                                    $_SESSION['user_id'],
                                    $resultat,
                                    $reference,
                                    $note,
                                    $operationId
                                ]);
                            }

                            /*
                             * Une fois le remboursement réellement effectué,
                             * la réquisition peut être clôturée.
                             */

                            $stmt = $pdo->prepare("
                                UPDATE requisitions_ministerielles
                                SET
                                    statut = 'cloturee',
                                    date_traitement = NOW(),
                                    traite_par = ?,
                                    note_traitement = ?
                                WHERE id = ?
                            ");

                            $stmt->execute([
                                $_SESSION['user_id'],
                                $note,
                                $id
                            ]);

                            log_activity(
                                'remboursement_requisition',
                                'reservations', // module existant de activity_log (ENUM)
                                'Remboursement effectué pour la réquisition #' . $id
                            );

                            notify(
                                'ministre',
                                'requisition_traitee',
                                'Le remboursement de la réquisition #' . $id . ' a été effectué.',
                                'requisition-detail.php?id=' . $id
                            );

                            $pdo->commit();

                            header(
                                'Location: requisition-detail.php?id='
                                . $id
                                . '&success=remboursement'
                            );
                            exit;

                        } catch (Throwable $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            $msg = [
                                'err',
                                'Impossible d’enregistrer le remboursement : '
                                . $e->getMessage()
                            ];
                        }
                    }
                }
            }

            /* =============================================
               TRAITEMENT D'UNE AUTRE OPÉRATION
            ============================================= */

            elseif ($action === 'traiter_operation') {

                if (empty($rqAction['choix_client'])) {

                    $msg = [
                        'err',
                        'Le client n’a pas encore effectué son choix.'
                    ];

                } elseif ($rqAction['statut'] === 'cloturee') {

                    $msg = [
                        'err',
                        'Cette réquisition est déjà clôturée.'
                    ];

                } elseif ($rqAction['choix_client'] === 'remboursement') {

                    // Un choix « remboursement » ne se clôture que par un
                    // remboursement réellement effectué (action dédiée).
                    $msg = [
                        'err',
                        'Cette réquisition ne peut être clôturée que par l’enregistrement du remboursement effectué.'
                    ];

                } else {

                    $resultat = trim($_POST['resultat'] ?? '');
                    $reference = trim($_POST['reference'] ?? '');
                    $note = trim($_POST['note'] ?? '');

                    /*
                     * Nouvelle date / autre espace : la réquisition n'est
                     * traitée que si la nouvelle réservation existe réellement
                     * et a été validée, et qu'aucun trop-perçu n'attend
                     * d'être remboursé.
                     */
                    $suiviOperation = in_array($rqAction['choix_client'], ['nouvelle_date', 'autre_espace'], true)
                        ? requisition_suivi_nouvelle_reservation($pdo, $id)
                        : null;

                    $blocagesCloture = $suiviOperation !== null
                        ? requisition_blocages_cloture($pdo, $id)
                        : [];

                    if ($blocagesCloture) {

                        $msg = [
                            'err',
                            'La réquisition ne peut pas encore être clôturée : ' . implode(' ', $blocagesCloture)
                        ];

                    } elseif ($resultat === '') {

                        $msg = [
                            'err',
                            'Veuillez renseigner le résultat du traitement.'
                        ];

                    } else {

                        $pdo->beginTransaction();

                        try {

                            // Verrou + contrôles refaits sous verrou (double clic, deux onglets)
                            $verrou = $pdo->prepare("SELECT statut FROM requisitions_ministerielles WHERE id = ? FOR UPDATE");
                            $verrou->execute([$id]);
                            if ($verrou->fetchColumn() === 'cloturee') {
                                throw new RuntimeException('Cette réquisition est déjà clôturée.');
                            }
                            if ($suiviOperation !== null && ($blocagesVerrou = requisition_blocages_cloture($pdo, $id))) {
                                throw new RuntimeException(implode(' ', $blocagesVerrou));
                            }

                            $stmtOp = $pdo->prepare("
                                SELECT id
                                FROM operations_requisition
                                WHERE requisition_id = ?
                                ORDER BY id DESC
                                LIMIT 1
                            ");
                            $stmtOp->execute([$id]);
                            $operationId = (int)$stmtOp->fetchColumn();

                            if ($operationId > 0) {

                                $stmt = $pdo->prepare("
                                    UPDATE operations_requisition
                                    SET
                                        statut = 'traitee',
                                        traite_par = ?,
                                        date_traitement = NOW(),
                                        resultat = ?,
                                        reference = ?,
                                        note = ?
                                    WHERE id = ?
                                ");

                                $stmt->execute([
                                    $_SESSION['user_id'],
                                    $resultat,
                                    $reference,
                                    $note,
                                    $operationId
                                ]);
                            }

                            $stmt = $pdo->prepare("
                                UPDATE requisitions_ministerielles
                                SET
                                    statut = 'cloturee',
                                    date_traitement = NOW(),
                                    traite_par = ?,
                                    note_traitement = ?
                                WHERE id = ?
                            ");

                            $stmt->execute([
                                $_SESSION['user_id'],
                                $note,
                                $id
                            ]);

                            log_activity(
                                'requisition_traitee',
                                'reservations', // module existant de activity_log (ENUM)
                                'Réquisition #' . $id . ' traitée'
                            );

                            $pdo->commit();

                            header(
                                'Location: requisition-detail.php?id='
                                . $id
                                . '&success=traitement_termine'
                            );
                            exit;

                        } catch (Throwable $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            $msg = [
                                'err',
                                'Impossible de terminer le traitement : '
                                . $e->getMessage()
                            ];
                        }
                    }
                }
            }
        }
    }
}

/* =========================================================
   MESSAGES DE SUCCÈS
========================================================= */

if (isset($_GET['success'])) {

    $success = $_GET['success'];

    if ($success === 'traitement') {
        $msg = [
            'ok',
            'Le traitement de la réquisition a commencé.'
        ];
    }

    elseif ($success === 'remboursement') {
        $msg = [
            'ok',
            'Le remboursement a été enregistré et la réquisition est clôturée.'
        ];
    }

    elseif ($success === 'traitement_termine') {
        $msg = [
            'ok',
            'Le traitement de la réquisition est terminé.'
        ];
    }
}

/* =========================================================
   CHARGEMENT DE LA RÉQUISITION
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        r.*,
        res.date_resa,
        res.date_depart,
        res.heure_debut,
        res.heure_fin,
        res.espace_id,
        res.tarif_id,
        res.user_id,
        u.nom_complet,
        u.telephone,
        u.email,
        e.nom AS espace_nom
    FROM requisitions_ministerielles r
    JOIN reservations res ON res.id = r.reservation_id
    JOIN users u ON u.id = res.user_id
    LEFT JOIN espaces e ON e.id = res.espace_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$rq = $stmt->fetch();

if (!$rq) {
    header('Location: requisitions.php');
    exit;
}

/* =========================================================
   MONTANT PAYÉ
========================================================= */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(montant), 0)
    FROM paiements
    WHERE reservation_id = ?
");
$stmt->execute([$rq['reservation_id']]);

$montantPaye = (float)$stmt->fetchColumn();

/* =========================================================
   REMBOURSEMENT EXISTANT
========================================================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM remboursements
    WHERE requisition_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$id]);
$remboursement = $stmt->fetch();

/* =========================================================
   DERNIÈRE OPÉRATION
========================================================= */

$stmt = $pdo->prepare("
    SELECT *
    FROM operations_requisition
    WHERE requisition_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$id]);
$operation = $stmt->fetch();

/* =========================================================
   HISTORIQUE DES OPÉRATIONS
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        o.*,
        u.nom_complet AS agent_nom
    FROM operations_requisition o
    LEFT JOIN users u
        ON u.id = COALESCE(o.traite_par, o.agent_assigne)
    WHERE o.requisition_id = ?
    ORDER BY o.id DESC
");
$stmt->execute([$id]);
$historique = $stmt->fetchAll();

/* =========================================================
   AGENT ACTUEL
========================================================= */

$agent = null;

if (!empty($operation['agent_assigne'])) {

    $stmt = $pdo->prepare("
        SELECT id, nom_complet, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$operation['agent_assigne']]);
    $agent = $stmt->fetch();
}

/* =========================================================
   ÉTAT DE LA PAGE
========================================================= */

$choixClient = $rq['choix_client'] ?? '';
$libelleChoix = $libellesChoix[$choixClient] ?? '—';

$libelleStatut =
    $libellesStatut[$rq['statut'] ?? '']
    ?? ($rq['statut'] ?? '—');

$estRemboursement = ($choixClient === 'remboursement');

$estNouvelleReservation = in_array($choixClient, ['nouvelle_date', 'autre_espace'], true);

$suivi = $estNouvelleReservation
    ? requisition_suivi_nouvelle_reservation($pdo, $id)
    : null;

$tropPercuARembourser = $suivi !== null && $suivi['a_rembourser'];
// Ce qui empêche encore la clôture (même règle que le traitement serveur)
$blocagesCloture = $estNouvelleReservation ? requisition_blocages_cloture($pdo, (int)$id) : [];

$libellesStatutResa = [
    'en_attente'     => 'En attente de validation',
    'validee'        => 'Validée',
    'refusee'        => 'Refusée',
    'annulee'        => 'Annulée',
    'expiree'        => 'Expirée',
    'requisitionnee' => 'Réquisitionnée',
];

$libellesPaiementResa = [
    'non_paye'           => 'Non payée',
    'attente_paiement'   => 'En attente de paiement',
    'partiellement_paye' => 'Partiellement payée',
    'paye'               => 'Payée',
];

$estCloturee = in_array(
    $rq['statut'],
    ['cloturee', 'traitee', 'annulee'],
    true
);

$estEnTraitement = ($rq['statut'] === 'en_traitement');

$peutCommencerTraitement =
    !$readonly
    && !$estCloturee
    && !$estEnTraitement
    && $rq['statut'] === 'choix_recu'
    && !empty($rq['choix_client']);

/* =========================================================
   STYLE DU STATUT
========================================================= */

$statutClasses = [
    'en_attente_choix' => 'bg-slate-100 text-slate-600 border-slate-200',
    'choix_recu'       => 'bg-amber-50 text-amber-700 border-amber-200',
    'en_traitement'    => 'bg-blue-50 text-blue-700 border-blue-200',
    'cloturee'         => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'traitee'          => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'annulee'           => 'bg-red-50 text-red-700 border-red-200',
];

$statutClass =
    $statutClasses[$rq['statut'] ?? '']
    ?? 'bg-slate-100 text-slate-600 border-slate-200';

/* =========================================================
   PAGE
========================================================= */

$pageTitle = 'Détail de la réquisition #' . $id;

require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8 max-w-7xl mx-auto">

    <!-- =====================================================
         EN-TÊTE
    ====================================================== -->

    <div class="flex items-start justify-between gap-4 flex-wrap mb-7">

        <div>

            <a
                href="requisitions.php"
                class="inline-flex items-center gap-2 text-xs font-bold text-slate-400 hover:text-primary transition mb-3"
            >
                <i class="fas fa-arrow-left"></i>
                Retour aux réquisitions
            </a>

            <h1 class="text-2xl sm:text-3xl font-black text-primary uppercase italic tracking-tight flex items-center gap-3">
                <i class="fas fa-file-circle-exclamation text-accent"></i>
                Dossier de réquisition
            </h1>

            <?php
                $nouvelleRef = !empty($suivi['validee']) ? (int)$suivi['validee']['id']
                    : (!empty($suivi['liste']) ? (int)$suivi['liste'][0]['id'] : 0);
                $nbObsReq = nb_observations($pdo, 'requisition', (int)$rq['id']);
            ?>
            <p class="text-sm text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                <span class="font-mono font-black text-slate-600"><?= e(ref_req((int)$rq['id'])) ?></span>
                <span>· réservation initiale</span>
                <span class="font-mono font-black text-slate-600"><?= e(ref_resa((int)$rq['reservation_id'])) ?></span>
                <?php if ($nouvelleRef): ?>
                    <span>→ nouvelle réservation</span>
                    <span class="font-mono font-black text-slate-600"><?= e(ref_resa($nouvelleRef)) ?></span>
                <?php endif; ?>
                <?php if (in_array('requisition', observations_types_disponibles($pdo), true)): ?>
                    <a href="observations.php?cible_type=requisition&cible_id=<?= (int)$rq['id'] ?>"
                       class="text-[11px] font-black text-slate-400 hover:text-accent transition ml-1">
                        <i class="fas fa-eye mr-1"></i>Observer<?= $nbObsReq ? ' (' . $nbObsReq . ')' : '' ?>
                    </a>
                <?php endif; ?>
            </p>

        </div>

        <div class="flex items-center gap-2">

            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-[11px] font-black uppercase <?= $statutClass ?>">
                <i class="fas fa-circle text-[7px]"></i>
                <?= e($libelleStatut) ?>
            </span>

        </div>

    </div>

    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($msg): ?>

        <div class="mb-6 rounded-2xl p-4 flex items-center gap-3
            <?= $msg[0] === 'ok'
                ? 'bg-emerald-50 border border-emerald-200 text-emerald-700'
                : 'bg-red-50 border border-red-200 text-red-700'
            ?>"
        >

            <i class="fas <?= $msg[0] === 'ok'
                ? 'fa-circle-check text-emerald-500'
                : 'fa-circle-exclamation text-red-500'
            ?>"></i>

            <span class="font-bold text-sm">
                <?= e($msg[1]) ?>
            </span>

        </div>

    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- =================================================
             COLONNE PRINCIPALE
        ================================================== -->

        <div class="lg:col-span-2 space-y-6">

            <!-- =============================================
                 INFORMATIONS GÉNÉRALES
            ============================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-primary flex items-center justify-center">
                        <i class="fas fa-file-lines"></i>
                    </div>

                    <div>
                        <h2 class="text-sm font-black text-primary uppercase tracking-wide">
                            Informations générales
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Données de la réquisition
                        </p>
                    </div>

                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">

                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Numéro
                        </p>
                        <p class="text-sm font-black text-slate-800">
                            Réquisition #<?= (int)$rq['id'] ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Statut
                        </p>

                        <span class="inline-flex px-3 py-1.5 rounded-lg border text-[10px] font-black uppercase <?= $statutClass ?>">
                            <?= e($libelleStatut) ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Date de déclenchement
                        </p>

                        <p class="text-sm font-bold text-slate-700">
                            <?= !empty($rq['date_declenchee'])
                                ? date('d/m/Y à H:i', strtotime($rq['date_declenchee']))
                                : '—'
                            ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Choix reçu le
                        </p>

                        <p class="text-sm font-bold text-slate-700">
                            <?= !empty($rq['date_choix'])
                                ? date('d/m/Y à H:i', strtotime($rq['date_choix']))
                                : '—'
                            ?>
                        </p>
                    </div>

                </div>

            </section>

            <!-- =============================================
                 CLIENT
            ============================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fas fa-user"></i>
                    </div>

                    <div>
                        <h2 class="text-sm font-black text-indigo-700 uppercase tracking-wide">
                            Client concerné
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Coordonnées du titulaire de la réservation
                        </p>
                    </div>

                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">

                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Nom complet
                        </p>

                        <p class="text-sm font-black text-slate-800">
                            <?= e($rq['nom_complet'] ?? '—') ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Téléphone
                        </p>

                        <p class="text-sm font-bold text-slate-700">
                            <?= e($rq['telephone'] ?? '—') ?>
                        </p>
                    </div>

                    <div class="sm:col-span-2">

                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            E-mail
                        </p>

                        <p class="text-sm font-bold text-slate-700">
                            <?= e($rq['email'] ?? '—') ?>
                        </p>

                    </div>

                </div>

            </section>

            <!-- =============================================
                 RÉSERVATION INITIALE
            ============================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <i class="fas fa-calendar-check"></i>
                    </div>

                    <div>
                        <h2 class="text-sm font-black text-purple-700 uppercase tracking-wide">
                            Réservation concernée
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Réservation à l’origine de la réquisition
                        </p>
                    </div>

                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">

                    <div>

                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Réservation
                        </p>

                        <p class="text-sm font-black text-slate-800">
                            #<?= (int)$rq['reservation_id'] ?>
                        </p>

                    </div>

                    <div>

                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Espace
                        </p>

                        <p class="text-sm font-black text-primary">
                            <?= e($rq['espace_nom'] ?? '—') ?>
                        </p>

                    </div>

                    <div>

                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Date
                        </p>

                        <p class="text-sm font-bold text-slate-700">
                            <?= !empty($rq['date_resa'])
                                ? date('d/m/Y', strtotime($rq['date_resa']))
                                : '—'
                            ?>
                        </p>

                    </div>

                    <div>

                        <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                            Horaires
                        </p>

                        <p class="text-sm font-bold text-slate-700">
                            <?php if (!empty($rq['heure_debut'])): ?>

                                <?= e(substr($rq['heure_debut'], 0, 5)) ?>

                                <?php if (!empty($rq['heure_fin'])): ?>
                                    — <?= e(substr($rq['heure_fin'], 0, 5)) ?>
                                <?php endif; ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>
                        </p>

                    </div>

                </div>

            </section>

            <!-- =============================================
                 MOTIF
            ============================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i class="fas fa-landmark"></i>
                    </div>

                    <div>
                        <h2 class="text-sm font-black text-amber-700 uppercase tracking-wide">
                            Motif de la réquisition
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Motif transmis par l’administration
                        </p>
                    </div>

                </div>

                <div class="p-5">

                    <p class="text-sm leading-6 text-slate-700 whitespace-pre-line">
                        <?= e($rq['motif'] ?? 'Aucun motif renseigné.') ?>
                    </p>

                </div>

            </section>

            <!-- =============================================
                 CHOIX DU CLIENT
            ============================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-hand-pointer"></i>
                    </div>

                    <div>
                        <h2 class="text-sm font-black text-blue-700 uppercase tracking-wide">
                            Décision du client
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Choix enregistré après la réquisition
                        </p>
                    </div>

                </div>

                <div class="p-5">

                    <?php if ($choixClient): ?>

                        <div class="rounded-2xl bg-blue-50 border border-blue-100 p-4">

                            <div class="flex items-start gap-3">

                                <div class="w-10 h-10 rounded-xl bg-white text-blue-600 flex items-center justify-center shrink-0 shadow-sm">
                                    <i class="fas fa-check"></i>
                                </div>

                                <div class="min-w-0">

                                    <p class="text-[10px] font-black uppercase text-blue-500 mb-1">
                                        Choix enregistré
                                    </p>

                                    <p class="text-base font-black text-blue-800">
                                        <?= e($libelleChoix) ?>
                                    </p>

                                    <?php if (!empty($rq['details_choix'])): ?>

                                        <div class="mt-3 pt-3 border-t border-blue-100">

                                            <p class="text-[10px] font-black uppercase text-blue-500 mb-1">
                                                Détails fournis
                                            </p>

                                            <p class="text-sm text-slate-700 whitespace-pre-line leading-6">
                                                <?= e($rq['details_choix']) ?>
                                            </p>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5 text-center">

                            <i class="fas fa-hourglass-half text-slate-300 text-2xl mb-2"></i>

                            <p class="text-sm font-bold text-slate-500">
                                Le client n’a pas encore effectué son choix.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- =============================================
                 NOUVELLE RÉSERVATION (nouvelle date / autre espace)
            ============================================== -->

            <?php if ($estNouvelleReservation): ?>

                <section class="bg-white rounded-2xl border border-indigo-200 shadow-sm overflow-hidden">

                    <div class="px-5 py-4 border-b border-indigo-100 flex items-center gap-3 bg-indigo-50/50">

                        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center">
                            <i class="fas fa-calendar-plus"></i>
                        </div>

                        <div>
                            <h2 class="text-sm font-black text-indigo-700 uppercase tracking-wide">
                                Nouvelle réservation du client
                            </h2>

                            <p class="text-[10px] text-indigo-600/70">
                                Réservation normale rattachée à cette réquisition
                            </p>
                        </div>

                    </div>

                    <div class="p-5 space-y-3">

                        <?php if (!$suivi['liste']): ?>

                            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                <p class="text-sm font-bold text-slate-600">
                                    Le client n’a pas encore déposé sa nouvelle réservation.
                                </p>
                                <p class="text-xs text-slate-500 mt-1">
                                    Il la finalise depuis son espace client ; elle suivra ensuite
                                    la validation habituelle par l’administration des espaces.
                                </p>
                            </div>

                        <?php else: ?>

                            <?php foreach ($suivi['liste'] as $n): ?>

                                <div class="rounded-2xl border p-4 <?= in_array($n['statut'], ['validee', 'requisitionnee'], true)
                                    ? 'bg-emerald-50 border-emerald-200'
                                    : ($n['statut'] === 'en_attente' ? 'bg-amber-50 border-amber-200' : 'bg-slate-50 border-slate-200') ?>">

                                    <div class="flex items-start justify-between gap-3 flex-wrap">

                                        <div>
                                            <p class="text-sm font-black text-slate-800">
                                                <?= e(ref_resa((int)$n['id'])) ?> — <?= e($n['espace_nom']) ?>
                                            </p>

                                            <p class="text-xs text-slate-600 mt-1">
                                                <?php if (!empty($n['heure_debut'])): ?>
                                                    Le <?= date('d/m/Y', strtotime($n['date_resa'])) ?>
                                                    de <?= e(substr($n['heure_debut'], 0, 5)) ?> à <?= e(substr($n['heure_fin'], 0, 5)) ?>
                                                <?php else: ?>
                                                    Du <?= date('d/m/Y', strtotime($n['date_resa'])) ?>
                                                    au <?= date('d/m/Y', strtotime($n['date_depart'])) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-[10px] font-black uppercase text-slate-500">
                                                <?= e($libellesStatutResa[$n['statut']] ?? $n['statut']) ?>
                                            </p>
                                            <?php
                                                // État financier calculé (paiements − remboursements effectués)
                                                $sfN = situation_financiere_reservation($pdo, (int)$n['id']);
                                                [$libEtatN] = libelle_etat_financier($sfN['etat'] ?? '');
                                            ?>
                                            <p class="text-[10px] font-bold text-slate-400 mt-0.5">
                                                <?php if (in_array($n['statut'], ['validee'], true)): ?>
                                                    <?= e($libEtatN) ?>
                                                    · <?= number_format((float)$sfN['paye_net'], 0, ',', ' ') ?> FCFA payés
                                                    <?php if ($sfN['solde'] > 0): ?>
                                                        · reste <?= number_format((float)$sfN['solde'], 0, ',', ' ') ?> FCFA
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?= e($libellesPaiementResa[$n['statut_paiement']] ?? $n['statut_paiement']) ?>
                                                    · <?= number_format((float)$sfN['paye_net'], 0, ',', ' ') ?> FCFA payés
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        <?php
                            // Uniquement sur base financière réelle : trop-perçu calculé
                            // restant, ou remboursement réellement effectué.
                            $tropAffiche = $suivi['trop_percu'] > 0 && $suivi['situation'] && $suivi['situation']['total_paye'] > 0;
                            $rembourseAffiche = $suivi['rembourse'] && $suivi['montant_rembourse'] > 0;
                        ?>
                        <?php if ($tropAffiche || $rembourseAffiche): ?>

                            <div class="rounded-2xl p-4 border <?= !$tropAffiche ? 'bg-emerald-50 border-emerald-200' : 'bg-orange-50 border-orange-200' ?>">
                                <p class="text-sm font-black <?= !$tropAffiche ? 'text-emerald-800' : 'text-orange-800' ?>">
                                    <?php if ($tropAffiche): ?>
                                        Trop-perçu : <?= number_format($suivi['trop_percu'], 0, ',', ' ') ?> FCFA — à rembourser
                                    <?php else: ?>
                                        Trop-perçu : <?= number_format($suivi['montant_rembourse'], 0, ',', ' ') ?> FCFA — remboursé
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-slate-600 mt-1">
                                    La nouvelle réservation coûte moins cher que le montant déjà encaissé.
                                </p>
                                <?php if ($rembourseAffiche && $remboursement): ?>
                                    <?php include __DIR__ . '/_liens_remboursement.php'; ?>
                                <?php endif; ?>
                            </div>

                        <?php endif; ?>

                        <?php
                            // Historique financier (lecture seule) du dossier RESA-A → REQ-X → RESA-B
                            $histoResaId = $nouvelleRef ?: (int)$rq['reservation_id'];
                            $histoEntrees = historique_financier_reservation($pdo, $histoResaId);
                        ?>
                        <div class="pt-2">
                            <?php require __DIR__ . '/_historique_financier.php'; ?>
                        </div>

                    </div>

                </section>

            <?php endif; ?>

            <!-- =============================================
                 REMBOURSEMENT
            ============================================== -->

            <?php if ($estRemboursement): ?>

                <section class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">

                    <div class="px-5 py-4 border-b border-amber-100 flex items-center gap-3 bg-amber-50/50">

                        <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                            <i class="fas fa-money-bill-transfer"></i>
                        </div>

                        <div>
                            <h2 class="text-sm font-black text-amber-700 uppercase tracking-wide">
                                Remboursement
                            </h2>

                            <p class="text-[10px] text-amber-600/70">
                                Suivi financier de l’opération
                            </p>
                        </div>

                    </div>

                    <div class="p-5">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">

                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">

                                <p class="text-[10px] font-black uppercase text-slate-400 mb-1">
                                    Total payé
                                </p>

                                <p class="text-xl font-black text-slate-800">
                                    <?= number_format($montantPaye, 0, ',', ' ') ?> FCFA
                                </p>

                            </div>

                            <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">

                                <p class="text-[10px] font-black uppercase text-emerald-600 mb-1">
                                    Remboursement enregistré
                                </p>

                                <p class="text-xl font-black text-emerald-700">
                                    <?= number_format(
                                        (float)($remboursement['montant_rembourse'] ?? 0),
                                        0,
                                        ',',
                                        ' '
                                    ) ?> FCFA
                                </p>

                            </div>

                        </div>

                        <?php if ($remboursement): ?>

                            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">

                                <div class="flex items-start gap-3">

                                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                        <i class="fas fa-circle-check"></i>
                                    </div>

                                    <div>

                                        <p class="text-sm font-black text-emerald-800">
                                            Remboursement enregistré
                                        </p>

                                        <p class="text-xs text-emerald-700 mt-1">
                                            Mode :
                                            <?= e(
                                                $modesRemboursement[$remboursement['mode'] ?? '']
                                                ?? ($remboursement['mode'] ?? '—')
                                            ) ?>
                                        </p>

                                        <?php if (!empty($remboursement['reference'])): ?>

                                            <p class="text-xs text-emerald-700 mt-1">
                                                Référence :
                                                <?= e($remboursement['reference']) ?>
                                            </p>

                                        <?php endif; ?>

                                        <?php if (!empty($remboursement['date_traitement'])): ?>

                                            <p class="text-xs text-emerald-700 mt-1">
                                                Traité le :
                                                <?= date(
                                                    'd/m/Y à H:i',
                                                    strtotime($remboursement['date_traitement'])
                                                ) ?>
                                            </p>

                                        <?php endif; ?>

                                        <?php include __DIR__ . '/_liens_remboursement.php'; ?>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>

                    </div>

                </section>

            <?php endif; ?>

            <!-- =============================================
                 HISTORIQUE
            ============================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>

                    <div>
                        <h2 class="text-sm font-black text-slate-700 uppercase tracking-wide">
                            Historique du traitement
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Traçabilité des opérations effectuées
                        </p>
                    </div>

                </div>

                <div class="p-5">

                    <?php if ($historique): ?>

                        <div class="space-y-4">

                            <?php foreach ($historique as $op): ?>

                                <?php
                                $statutOp =
                                    $libellesStatutOperation[$op['statut'] ?? '']
                                    ?? ($op['statut'] ?? '—');

                                $opClass = match ($op['statut'] ?? '') {
                                    'traitee'  => 'bg-emerald-50 border-emerald-200',
                                    'en_cours' => 'bg-blue-50 border-blue-200',
                                    'annulee'  => 'bg-red-50 border-red-200',
                                    default    => 'bg-amber-50 border-amber-200',
                                };
                                ?>

                                <div class="rounded-2xl border p-4 <?= $opClass ?>">

                                    <div class="flex items-start justify-between gap-4">

                                        <div>

                                            <p class="text-sm font-black text-slate-800">
                                                <?= e(
                                                    $libellesChoix[$op['type_operation'] ?? '']
                                                    ?? ($op['type_operation'] ?? 'Opération')
                                                ) ?>
                                            </p>

                                            <p class="text-[10px] font-black uppercase text-slate-500 mt-1">
                                                <?= e($statutOp) ?>
                                            </p>

                                        </div>

                                        <?php if (!empty($op['date_traitement'])): ?>

                                            <p class="text-[10px] font-bold text-slate-400 whitespace-nowrap">
                                                <?= date(
                                                    'd/m/Y H:i',
                                                    strtotime($op['date_traitement'])
                                                ) ?>
                                            </p>

                                        <?php elseif (!empty($op['date_prise_en_charge'])): ?>

                                            <p class="text-[10px] font-bold text-slate-400 whitespace-nowrap">
                                                <?= date(
                                                    'd/m/Y H:i',
                                                    strtotime($op['date_prise_en_charge'])
                                                ) ?>
                                            </p>

                                        <?php endif; ?>

                                    </div>

                                    <?php if (!empty($op['agent_nom'])): ?>

                                        <p class="text-xs text-slate-500 mt-3">
                                            <i class="fas fa-user-check mr-1"></i>
                                            <?= e($op['agent_nom']) ?>
                                        </p>

                                    <?php endif; ?>

                                    <?php if (!empty($op['resultat'])): ?>

                                        <p class="text-xs text-slate-600 mt-2">
                                            <span class="font-black">Résultat :</span>
                                            <?= e($op['resultat']) ?>
                                        </p>

                                    <?php endif; ?>

                                    <?php if (!empty($op['reference'])): ?>

                                        <p class="text-xs text-slate-600 mt-1">
                                            <span class="font-black">Référence :</span>
                                            <?= e($op['reference']) ?>
                                        </p>

                                    <?php endif; ?>

                                    <?php if (!empty($op['note'])): ?>

                                        <p class="text-xs text-slate-600 mt-2 whitespace-pre-line">
                                            <span class="font-black">Note :</span>
                                            <?= e($op['note']) ?>
                                        </p>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-8">

                            <i class="fas fa-clock text-slate-200 text-3xl mb-2"></i>

                            <p class="text-sm text-slate-400">
                                Aucun historique disponible.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>

        <!-- =================================================
             COLONNE TRAITEMENT
        ================================================== -->

        <aside class="lg:col-span-1">

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden sticky top-6">

                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

                    <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <i class="fas fa-gears"></i>
                    </div>

                    <div>

                        <h2 class="text-sm font-black text-primary uppercase tracking-wide">
                            Traitement
                        </h2>

                        <p class="text-[10px] text-slate-400">
                            Actions disponibles
                        </p>

                    </div>

                </div>

                <div class="p-5 space-y-5">

                    <!-- ÉTAT -->

                    <div>

                        <p class="text-[10px] font-black uppercase text-slate-400 mb-2">
                            État actuel
                        </p>

                        <div class="rounded-xl border p-3 <?= $statutClass ?>">

                            <div class="flex items-center gap-2">

                                <i class="fas fa-circle text-[7px]"></i>

                                <span class="text-xs font-black uppercase">
                                    <?= e($libelleStatut) ?>
                                </span>

                            </div>

                        </div>

                    </div>

                    <!-- AGENT -->

                    <?php if ($agent): ?>

                        <div>

                            <p class="text-[10px] font-black uppercase text-slate-400 mb-2">
                                Agent en charge du traitement
                            </p>

                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">

                                <p class="text-sm font-black text-slate-700">
                                    <?= e($agent['nom_complet']) ?>
                                </p>

                                <?php if (!empty($agent['role'])): ?>

                                    <p class="text-[10px] text-slate-400 mt-1">
                                        <?= e($agent['role']) ?>
                                    </p>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endif; ?>

                    <!-- =====================================
                         COMMENCER LE TRAITEMENT
                    ====================================== -->

                    <?php if ($peutCommencerTraitement): ?>

                        <div class="pt-1">

                            <form method="post">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$id ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="prendre_en_charge"
                                >

                                <button
                                    type="submit"
                                    onclick="return confirm('Commencer le traitement de cette réquisition ?')"
                                    class="w-full flex items-center justify-center gap-2 bg-primary text-white text-xs font-black uppercase px-4 py-3.5 rounded-xl hover:opacity-90 transition shadow-lg shadow-primary/20"
                                >
                                    <i class="fas fa-play"></i>
                                    Commencer le traitement
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                    <!-- =====================================
                         TRAITEMENT REMBOURSEMENT
                    ====================================== -->

                    <?php if (
                        !$readonly
                        && $estEnTraitement
                        && (($estRemboursement && !$remboursement) || $tropPercuARembourser)
                        && ($rembAttenduVue = requisition_remboursement_attendu($pdo, (int)$id))['type'] !== null
                    ):
                        // Montants fixés par le serveur (règle commune) : totalité du montant dû
                        $montantPayeFormulaire = $tropPercuARembourser
                            ? (float)$suivi['validee']['total_paye']
                            : $rembAttenduVue['montant'];
                        $montantARembourserFormulaire = $rembAttenduVue['montant'];
                    ?>

                        <div class="pt-1">

                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 mb-4">

                                <div class="flex items-start gap-2">

                                    <i class="fas fa-circle-info text-amber-600 mt-0.5"></i>

                                    <p class="text-[11px] text-amber-800 leading-5">
                                        <?php if ($tropPercuARembourser): ?>
                                        <strong>Trop-perçu de <?= number_format($suivi['trop_percu'], 0, ',', ' ') ?> FCFA</strong>
                                        sur la nouvelle réservation #<?= (int)$suivi['validee']['id'] ?>.
                                        <?php endif; ?>
                                        Le remboursement doit être enregistré avec son montant,
                                        son mode et sa référence. La réquisition ne sera clôturée
                                        que lorsque le remboursement est marqué comme effectué.
                                    </p>

                                </div>

                            </div>

                            <form method="post" class="space-y-3">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$id ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="traiter_remboursement"
                                >

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Montant payé
                                    </label>

                                    <input
                                        type="number"
                                        name="montant_paye"
                                        min="0"
                                        step="1"
                                        value="<?= e((string)$montantPayeFormulaire) ?>"
                                        readonly
                                        title="Montant calculé par le système à partir des paiements enregistrés"
                                        required
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-primary"
                                    >

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Montant à rembourser
                                    </label>

                                    <input
                                        type="number"
                                        name="montant_a_rembourser"
                                        min="0"
                                        step="1"
                                        value="<?= e((string)$montantARembourserFormulaire) ?>"
                                        readonly
                                        title="Totalité du montant dû au client, calculée par le système"
                                        required
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-primary"
                                    >

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Mode de remboursement
                                    </label>

                                    <select
                                        name="mode"
                                        required
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-primary"
                                    >

                                        <option value="">
                                            Sélectionner
                                        </option>

                                        <?php foreach ($modesRemboursement as $value => $label): ?>

                                            <option value="<?= e($value) ?>">
                                                <?= e($label) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Référence
                                    </label>

                                    <input
                                        type="text"
                                        name="reference"
                                        placeholder="Référence de transaction"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-primary"
                                    >

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Résultat
                                    </label>

                                    <select
                                        name="resultat"
                                        required
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-primary"
                                    >

                                        <option value="">
                                            Sélectionner
                                        </option>

                                        <option value="effectue">
                                            Remboursement effectué
                                        </option>

                                    </select>

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Note
                                    </label>

                                    <textarea
                                        name="note"
                                        rows="3"
                                        placeholder="Observation ou précision sur le remboursement..."
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:border-primary resize-none"
                                    ></textarea>

                                </div>

                                <button
                                    type="submit"
                                    onclick="return confirm('Confirmer l’enregistrement du remboursement ?')"
                                    class="w-full flex items-center justify-center gap-2 bg-emerald-600 text-white text-xs font-black uppercase px-4 py-3.5 rounded-xl hover:bg-emerald-700 transition shadow-lg shadow-emerald-600/20"
                                >
                                    <i class="fas fa-money-bill-transfer"></i>
                                    Enregistrer le remboursement
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                    <!-- =====================================
                         AUTRE OPÉRATION
                    ====================================== -->

                    <?php if (
                        !$readonly
                        && $estEnTraitement
                        && $estNouvelleReservation
                        && !$estCloturee
                        && $blocagesCloture
                    ): ?>

                        <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-3">
                            <p class="text-[11px] text-indigo-800 leading-5">
                                <i class="fas fa-circle-info mr-1"></i>
                                Clôture possible lorsque le dossier sera réellement terminé :
                                <?php foreach ($blocagesCloture as $blocage): ?>
                                    <br>— <?= e($blocage) ?>
                                <?php endforeach; ?>
                                <?php if ($suivi['validee'] !== null && ($suivi['situation']['solde'] ?? 0) > 0): ?>
                                    <br><a href="paiements.php?resa=<?= (int)$suivi['validee']['id'] ?>" class="font-black underline">Encaisser le solde</a>
                                <?php endif; ?>
                            </p>
                        </div>

                    <?php endif; ?>

                    <?php if (
                        !$readonly
                        && $estEnTraitement
                        && !$estRemboursement
                        && !$estCloturee
                        && (!$estNouvelleReservation || !$blocagesCloture)
                    ): ?>

                        <div class="pt-1">

                            <form method="post" class="space-y-3">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$id ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="traiter_operation"
                                >

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Résultat du traitement
                                    </label>

                                    <textarea
                                        name="resultat"
                                        rows="4"
                                        required
                                        placeholder="Décrire le résultat du traitement..."
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:border-primary resize-none"
                                    ></textarea>

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Référence
                                    </label>

                                    <input
                                        type="text"
                                        name="reference"
                                        placeholder="Référence éventuelle"
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 outline-none focus:border-primary"
                                    >

                                </div>

                                <div>

                                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">
                                        Note
                                    </label>

                                    <textarea
                                        name="note"
                                        rows="3"
                                        placeholder="Note de traitement..."
                                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 outline-none focus:border-primary resize-none"
                                    ></textarea>

                                </div>

                                <button
                                    type="submit"
                                    onclick="return confirm('Valider le traitement de cette réquisition ?')"
                                    class="w-full flex items-center justify-center gap-2 bg-primary text-white text-xs font-black uppercase px-4 py-3.5 rounded-xl hover:opacity-90 transition shadow-lg shadow-primary/20"
                                >
                                    <i class="fas fa-check"></i>
                                    Valider le traitement
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                    <!-- =====================================
                         DOSSIER CLÔTURÉ
                    ====================================== -->

                    <?php if ($estCloturee): ?>

                        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">

                            <div class="flex items-start gap-3">

                                <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                    <i class="fas fa-circle-check"></i>
                                </div>

                                <div>

                                    <p class="text-sm font-black text-emerald-800">
                                        Traitement terminé
                                    </p>

                                    <p class="text-xs text-emerald-700 mt-1 leading-5">
                                        Cette réquisition est clôturée.
                                        Les informations restent disponibles
                                        dans l’historique pour assurer la traçabilité.
                                    </p>

                                </div>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </aside>

    </div>

</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>