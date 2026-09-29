<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_client('admin/dashboard.php');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: espaces.php');
    exit;
}

// Réservation faite dans le cadre d'une réquisition (nouvelle date / autre espace).
// Vide pour une réservation normale : dans ce cas rien ne change dans le circuit.
$requisition_id = max(0, (int)($_POST['requisition_id'] ?? 0));
$paramRequisition = $requisition_id ? '&requisition_id=' . $requisition_id : '';

// CSRF
if (!csrf_check($_POST['csrf_token'] ?? '')) {
    header('Location: reserver.php?error=csrf' . $paramRequisition);
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$espace_id  = (int)($_POST['espace_id'] ?? 0);
$tarif_id   = !empty($_POST['tarif_id']) ? (int)$_POST['tarif_id'] : null;
$date_resa  = trim($_POST['date_resa'] ?? '');
$date_depart = trim($_POST['date_depart'] ?? '');
$heure_debut = trim($_POST['heure_debut'] ?? '');
$heure_fin   = trim($_POST['heure_fin'] ?? '');
$petit_dej  = !empty($_POST['petit_dejeuner']) ? 1 : 0;
$vip        = !empty($_POST['vip']) ? 1 : 0;
$quantite   = max(1, (int)($_POST['quantite'] ?? 1));
$motif      = trim($_POST['motif'] ?? '');
$telephone  = trim($_POST['telephone'] ?? '');
$horaire_mode = ($_POST['horaire_mode'] ?? '') === 'meme' ? 'meme' : 'nouveau';

if (!$espace_id) {
    header('Location: ' . ($requisition_id ? 'reserver.php?requisition_id=' . $requisition_id : 'espaces.php'));
    exit;
}

$espace = $pdo->prepare("SELECT * FROM espaces WHERE id = ?");
$espace->execute([$espace_id]);
$espace = $espace->fetch();
if (!$espace) {
    header('Location: ' . ($requisition_id ? 'reserver.php?requisition_id=' . $requisition_id : 'espaces.php'));
    exit;
}

// Adresse de retour en cas d'erreur (conserve le contexte de réquisition)
$retourErreur = "reserver.php?espace_id=$espace_id" . $paramRequisition . "&error=";

if (!empty($espace['gerant_externe'])) {
    if ($requisition_id) {
        header('Location: ' . $retourErreur . urlencode('Cet espace est géré par un tiers : il ne peut pas être réservé en ligne. Merci de choisir un autre espace.'));
        exit;
    }
    header('Location: espace.php?slug=' . urlencode($espace['slug']) . '&error=' . urlencode('Cet espace est géré par un tiers, veuillez le/la contacter directement.'));
    exit;
}

$estSejour = ($espace['mode_reservation'] === 'sejour');

// Le petit-déjeuner n'est proposé que par les espaces qui l'activent réellement
// (ex: pas au Necker) — on ne fait jamais confiance à la case cochée côté client.
if (!$estSejour || !$espace['option_petit_dejeuner']) $petit_dej = 0;
if (!$espace['option_vip']) $vip = 0;

// --- Contexte de réquisition (lecture seule ici, revérifié sous verrou plus bas) ---
$requisition = null;

if ($requisition_id) {

    $contexte = requisition_contexte_nouvelle_reservation($pdo, $requisition_id, $user_id);

    if (!$contexte['ok']) {
        header('Location: mon-compte.php?requisition_erreur=' . urlencode($contexte['code']));
        exit;
    }

    $requisition = $contexte['req'];

    if ($requisition['choix_client'] === 'nouvelle_date') {

        // Même espace et même type de réservation que la réservation d'origine
        if ($espace_id !== (int)$requisition['espace_id']) {
            header('Location: reserver.php?requisition_id=' . $requisition_id . '&error=' . urlencode("Pour une nouvelle date, la réservation doit rester sur l'espace « {$requisition['espace_nom']} »."));
            exit;
        }

        if (!empty($requisition['tarif_id'])) {
            $tarif_id = (int)$requisition['tarif_id'];
        }

        if ($estSejour) {
            // Le séjour garde sa durée et son nombre de chambres : seule la date bouge.
            if ($date_resa && ($d = DateTime::createFromFormat('!Y-m-d', $date_resa)) && $d->format('Y-m-d') === $date_resa) {
                $date_depart = $d->modify('+' . (int)$requisition['nuits'] . ' days')->format('Y-m-d');
            }
            $quantite = max(1, (int)$requisition['quantite']);
        } elseif ($horaire_mode === 'meme') {
            // Option « conserver le même horaire » : l'horaire est repris côté serveur.
            $heure_debut = substr((string)$requisition['heure_debut'], 0, 5);
            $heure_fin   = substr((string)$requisition['heure_fin'], 0, 5);
        }

    } else {

        // Autre espace : un espace réellement différent de celui réquisitionné
        if ($espace_id === (int)$requisition['espace_id']) {
            header('Location: reserver.php?requisition_id=' . $requisition_id . '&error=' . urlencode("Merci de choisir un espace différent de « {$requisition['espace_nom']} », qui a été réquisitionné. Pour garder cet espace, choisissez plutôt une nouvelle date."));
            exit;
        }
    }
}

// --- Validations basiques ---
$errors = [];

$stmtActif = $pdo->prepare("SELECT actif FROM users WHERE id = ?");
$stmtActif->execute([$user_id]);
if (!$stmtActif->fetchColumn()) {
    $errors[] = "Votre compte ne peut pas effectuer de nouvelle réservation pour le moment. Merci de vous rapprocher de l'administration du Palais.";
}

// Seuls les espaces proposés sur le site (disponible = 1) peuvent être réservés
if (!(int)$espace['disponible']) {
    $errors[] = "Cet espace n'est pas disponible à la réservation pour le moment.";
}

if (!$tarif_id)  $errors[] = "Veuillez sélectionner un tarif.";
if ($tarif_id) {
    $checkTarif = $pdo->prepare("SELECT est_bail FROM tarifs WHERE id = ? AND espace_id = ?");
    $checkTarif->execute([$tarif_id, $espace_id]);
    $estBailTarif = $checkTarif->fetchColumn();
    if ($estBailTarif === false || (int)$estBailTarif === 1) {
        $errors[] = "Ce tarif n'est pas réservable en ligne — merci de faire une demande de bail si c'est ce que vous recherchez.";
    }
}

$dateResaObj = $date_resa ? DateTime::createFromFormat('!Y-m-d', $date_resa) : false;
if (!$dateResaObj || $dateResaObj->format('Y-m-d') !== $date_resa || $date_resa < date('Y-m-d')) {
    $errors[] = "Date invalide.";
}

if ($estSejour) {
    $dateDepartObj = $date_depart ? DateTime::createFromFormat('!Y-m-d', $date_depart) : false;
    if (!$dateDepartObj || $dateDepartObj->format('Y-m-d') !== $date_depart || $date_depart <= $date_resa) {
        $errors[] = "La date de départ doit être après la date d'arrivée.";
    }
} else {
    // Mêmes règles que les listes horaires de reserver.php (format, bornes, pas de 30 min)
    $erreurHoraire = horaire_reservation_erreur($heure_debut, $heure_fin);
    if ($erreurHoraire !== null) $errors[] = $erreurHoraire;
    $quantite = 1; // la quantité multiple ne concerne que les espaces en séjour (chambres)
}

if ($errors) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: " . $retourErreur . $msg);
    exit;
}

$heure_debut_fmt = $estSejour ? null : $heure_debut . ':00';
$heure_fin_fmt   = $estSejour ? null : $heure_fin   . ':00';

/* ============================================================
   RÉSERVATION NORMALE — circuit inchangé
   ============================================================ */
if (!$requisition_id) {

    try {
        // --- Anti-conflit : uniquement pour l'hébergement (inventaire réel de chambres) ---
        // Pour les créneaux (salles), toute demande est acceptée : c'est le premier
        // paiement enregistré au guichet qui départage définitivement en cas de
        // créneau partagé entre plusieurs demandes validées.
        if ($estSejour) {
            $disponible = tarif_disponible(
                $pdo, $tarif_id, $date_resa, $date_depart, null, null, null, $quantite
            );
            if (!$disponible) {
                $messageComplet = "Complet pour ces dates. Merci de choisir d'autres dates ou une autre catégorie de chambre.";
                header("Location: reserver.php?espace_id=$espace_id&error=" . urlencode($messageComplet));
                exit;
            }
        }

        // --- Insertion ---
        $stmt = $pdo->prepare("
            INSERT INTO reservations
                (user_id, espace_id, tarif_id, date_resa, date_depart, heure_debut, heure_fin, petit_dejeuner, vip, quantite, statut, motif, created_at, notification_vue)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, NOW(), 0)
        ");
        $stmt->execute([$user_id, $espace_id, $tarif_id, $date_resa, $estSejour ? $date_depart : null, $heure_debut_fmt, $heure_fin_fmt, $petit_dej, $vip, $quantite, $motif]);
        $resaId = (int)$pdo->lastInsertId();
        attribuer_partenaire_reservation($pdo, $resaId, $user_id); // compte partenaire : réservation attribuée

        // Notifier admin_espaces : une nouvelle demande attend sa validation
        $nomClient = $_SESSION['nom_complet'] ?? 'Un client';
        $periode = $estSejour
            ? ('du ' . date('d/m/Y', strtotime($date_resa)) . ' au ' . date('d/m/Y', strtotime($date_depart)) . ($quantite > 1 ? " ($quantite chambres)" : ''))
            : ('le ' . date('d/m/Y', strtotime($date_resa)));
        // Réservation partenaire : signalée comme prioritaire (même circuit de validation)
        $partResa  = partenaire_utilisateur($pdo, (int)$user_id);
        $prefixe   = $partResa ? "[Prioritaire · Partenaire {$partResa['nom']}] " : '';
        notify('admin_espaces', 'nouvelle_reservation',
            $prefixe . "Nouvelle demande de $nomClient pour « {$espace['nom']} » $periode",
            "reservations.php" . ($partResa ? "?id=$resaId" : '')
        );
        notify('superadmin', 'nouvelle_reservation',
            $prefixe . "Nouvelle demande de $nomClient pour « {$espace['nom']} » $periode",
            "reservations.php" . ($partResa ? "?id=$resaId" : '')
        );
        if ($partResa) {
            // Information comptable : le paiement suivra le circuit habituel après validation
            notify('admin_comptable', 'nouvelle_reservation',
                $prefixe . "Nouvelle demande partenaire " . ref_resa($resaId) . " pour « {$espace['nom']} » $periode (en attente de validation).",
                "reservations.php?id=$resaId"
            );
        }

        // Mise à jour du téléphone si absent
        if (!empty($telephone)) {
            $pdo->prepare("UPDATE users SET telephone = ? WHERE id = ? AND (telephone IS NULL OR telephone = '')")
                ->execute([$telephone, $user_id]);
        }

        header('Location: mon-compte.php?success=1');
        exit;

    } catch (PDOException $e) {
        header("Location: reserver.php?espace_id=$espace_id&error=" . urlencode("Erreur technique. Veuillez réessayer."));
        exit;
    }
}

/* ============================================================
   RÉSERVATION DANS LE CADRE D'UNE RÉQUISITION
   Même insertion qu'une réservation normale (statut en_attente,
   validation par admin_espaces), rattachée via requisition_id.
   Tout est revérifié sous verrou pour empêcher double clic,
   deux onglets ou deux requêtes simultanées.
   ============================================================ */
try {

    $pdo->beginTransaction();

    $contexte = requisition_contexte_nouvelle_reservation($pdo, $requisition_id, $user_id, true);

    if (!$contexte['ok']) {
        $pdo->rollBack();
        header('Location: mon-compte.php?requisition_erreur=' . urlencode($contexte['code']));
        exit;
    }

    $requisition = $contexte['req'];

    // Le choix a pu changer entre la lecture et le verrou : on revérifie l'espace
    if (
        ($requisition['choix_client'] === 'nouvelle_date' && $espace_id !== (int)$requisition['espace_id'])
        || ($requisition['choix_client'] === 'autre_espace' && $espace_id === (int)$requisition['espace_id'])
    ) {
        $pdo->rollBack();
        header('Location: reserver.php?requisition_id=' . $requisition_id . '&error=' . urlencode("L'espace choisi ne correspond pas à votre choix de réquisition."));
        exit;
    }

    // Le créneau (ou le séjour) réquisitionné reste occupé par l'institution
    if (chevauche_reservation_origine(
        $requisition,
        $espace_id,
        $date_resa,
        $estSejour ? $date_depart : null,
        $heure_debut_fmt,
        $heure_fin_fmt
    )) {
        $pdo->rollBack();
        header("Location: " . $retourErreur . urlencode("Cette période correspond à la réservation réquisitionnée : l'espace y est occupé par l'institution. Merci de choisir une autre date ou un autre horaire."));
        exit;
    }

    if ($estSejour) {
        // Même contrôle d'inventaire qu'une réservation normale
        if (!tarif_disponible($pdo, $tarif_id, $date_resa, $date_depart, null, null, null, $quantite)) {
            $pdo->rollBack();
            header("Location: " . $retourErreur . urlencode("Complet pour ces dates. Merci de choisir d'autres dates ou une autre catégorie de chambre."));
            exit;
        }
    } else {
        // Un créneau déjà définitivement réglé par un autre client ne peut pas être repris
        $occupant = creneau_occupe_par_reservation_payee($pdo, $espace_id, $date_resa, $heure_debut_fmt, $heure_fin_fmt);
        if ($occupant) {
            $pdo->rollBack();
            header("Location: " . $retourErreur . urlencode("Ce créneau est déjà réservé et réglé (une marge de 2h est prévue après chaque occupation). Merci de choisir un autre horaire ou une autre date."));
            exit;
        }
    }

    // --- Insertion : réservation normale, en attente de validation ---
    $stmt = $pdo->prepare("
        INSERT INTO reservations
            (user_id, espace_id, tarif_id, date_resa, date_depart, heure_debut, heure_fin, petit_dejeuner, vip, quantite, statut, motif, requisition_id, created_at, notification_vue)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, ?, NOW(), 0)
    ");
    $stmt->execute([$user_id, $espace_id, $tarif_id, $date_resa, $estSejour ? $date_depart : null, $heure_debut_fmt, $heure_fin_fmt, $petit_dej, $vip, $quantite, $motif, $requisition_id]);
    $resaId = (int)$pdo->lastInsertId();
    attribuer_partenaire_reservation($pdo, $resaId, $user_id); // compte partenaire : réservation attribuée

    // --- Traçabilité sur l'opération de la réquisition ---
    $periode = $estSejour
        ? ('du ' . date('d/m/Y', strtotime($date_resa)) . ' au ' . date('d/m/Y', strtotime($date_depart)) . ($quantite > 1 ? " ($quantite chambres)" : ''))
        : ('le ' . date('d/m/Y', strtotime($date_resa)) . ' de ' . $heure_debut . ' à ' . $heure_fin);

    $trace = 'Nouvelle réservation #' . $resaId . ' demandée par le client pour « ' . $espace['nom'] . ' » ' . $periode
        . ' — en attente de validation (' . date('d/m/Y à H:i') . ').';

    $stmtOp = $pdo->prepare("
        SELECT id
        FROM operations_requisition
        WHERE requisition_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmtOp->execute([$requisition_id]);
    $operationId = (int)$stmtOp->fetchColumn();

    if ($operationId > 0) {
        $pdo->prepare("
            UPDATE operations_requisition
            SET description = CONCAT(
                COALESCE(description, ''),
                CASE WHEN description IS NULL OR description = '' THEN '' ELSE '\n' END,
                ?
            )
            WHERE id = ?
        ")->execute([$trace, $operationId]);
    }

    log_activity(
        'requisition_nouvelle_reservation',
        'reservations',
        "Client #$user_id — réquisition #$requisition_id : nouvelle réservation #$resaId créée (en attente)"
    );

    // Mise à jour du téléphone si absent
    if (!empty($telephone)) {
        $pdo->prepare("UPDATE users SET telephone = ? WHERE id = ? AND (telephone IS NULL OR telephone = '')")
            ->execute([$telephone, $user_id]);
    }

    $pdo->commit();

    // --- Notifications (après commit), comme une réservation normale ---
    $nomClient = $_SESSION['nom_complet'] ?? 'Un client';
    notify('admin_espaces', 'nouvelle_reservation',
        "Nouvelle demande de $nomClient pour « {$espace['nom']} » $periode — suite à la réquisition #$requisition_id",
        "reservations.php?id=$resaId"
    );
    notify('superadmin', 'nouvelle_reservation',
        "Nouvelle demande de $nomClient pour « {$espace['nom']} » $periode — suite à la réquisition #$requisition_id",
        "reservations.php?id=$resaId"
    );
    notify('admin_comptable', 'requisition_nouvelle_reservation',
        "Réquisition #$requisition_id : le client a déposé la nouvelle réservation #$resaId (en attente de validation par l'administration des espaces).",
        "requisition-detail.php?id=$requisition_id"
    );

    header('Location: mon-compte.php?success=requisition');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('traitement-reservation (réquisition #' . $requisition_id . ') : ' . $e->getMessage());
    header("Location: " . $retourErreur . urlencode("Erreur technique. Veuillez réessayer."));
    exit;
}
