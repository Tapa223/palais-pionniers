<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_comptable','ministre']);
expirer_reservations_non_payees();

$pdo      = db();
$role     = $_SESSION['role'] ?? '';
$readonly = is_readonly_admin() || is_superadmin();
$msg      = null;

$modeLabels = [
    'especes'      => ['Espèces',       'fa-money-bill-wave', 'bg-green-100 text-green-700'],
    'orange_money' => ['Orange Money',  'fa-mobile-alt',      'bg-orange-100 text-orange-700'],
    'moov_money'   => ['Moov Money',    'fa-mobile-alt',      'bg-blue-100 text-blue-700'],
    'virement'     => ['Virement',      'fa-university',      'bg-slate-100 text-slate-600'],
    'cheque'       => ['Chèque',        'fa-file-invoice',    'bg-purple-100 text-purple-700'],
];

$fcfa = fn($m) => number_format((float)$m, 0, ',', ' ');

// Message de l'opération précédente (après redirection)
if (!empty($_SESSION['paiements_flash'])) {
    $msg = $_SESSION['paiements_flash'];
    unset($_SESSION['paiements_flash']);
}

/*
 * Jeton à usage unique par formulaire : un double clic, un rechargement
 * ou un second onglet ne peuvent pas rejouer la même action.
 */
function paiement_jeton(): string
{
    $jeton = bin2hex(random_bytes(16));
    $_SESSION['jetons_compta'][$jeton] = time();
    // on ne garde que les jetons récents
    $_SESSION['jetons_compta'] = array_filter(
        $_SESSION['jetons_compta'],
        fn($t) => $t > time() - 6 * 3600
    );
    return $jeton;
}

function paiement_consommer_jeton(?string $jeton): bool
{
    if (!$jeton || empty($_SESSION['jetons_compta'][$jeton])) {
        return false;
    }
    unset($_SESSION['jetons_compta'][$jeton]);
    return true;
}

// ============================================================
// ACTIONS (comptable uniquement) — tout est recalculé côté serveur
// ============================================================
if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $resaId = (int)($_POST['reservation_id'] ?? 0);

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } elseif (!paiement_consommer_jeton($_POST['jeton'] ?? null)) {
        $msg = ['err', 'Ce formulaire a déjà été envoyé (double clic, rechargement ou autre onglet). Vérifiez l\'historique avant de recommencer.'];
    } elseif (!$resaId) {
        $msg = ['err', 'Réservation introuvable.'];
    } else {

        $notifications = [];

        try {
            $pdo->beginTransaction();

            // Verrou : un seul traitement comptable à la fois sur cette réservation
            $s = situation_financiere_reservation($pdo, $resaId, true);

            if (!$s) {
                throw new RuntimeException('Réservation introuvable.');
            }

            if ($s['statut_reservation'] !== 'validee') {
                $libelles = ['en_attente' => 'en attente de validation', 'refusee' => 'refusée', 'annulee' => 'annulée',
                             'expiree' => 'expirée', 'requisitionnee' => 'réquisitionnée'];
                throw new RuntimeException('Opération impossible : cette réservation est '
                    . ($libelles[$s['statut_reservation']] ?? $s['statut_reservation']) . '.');
            }

            // Anciennes réservations : le montant initial est figé à la première opération
            figer_montant_initial($pdo, $resaId);

            $infos = $pdo->prepare("SELECT r.*, e.nom AS espace_nom, u.nom_complet FROM reservations r JOIN espaces e ON e.id=r.espace_id JOIN users u ON u.id=r.user_id WHERE r.id=?");
            $infos->execute([$resaId]);
            $resa = $infos->fetch();

            // ------------------------------------------------------------
            // A. RÉDUCTION ACCORDÉE
            // ------------------------------------------------------------
            if ($action === 'accorder_reduction') {

                $s = situation_financiere_reservation($pdo, $resaId);

                if ($s['reduction_appliquee']) {
                    throw new RuntimeException('Une réduction est déjà appliquée sur cette réservation. Annulez-la ou marquez-la comme non utilisée avant d\'en accorder une autre.');
                }

                $pourcentage = trim((string)($_POST['pourcentage'] ?? ''));
                $montantSaisi = trim((string)($_POST['montant_reduction'] ?? ''));

                if ($pourcentage !== '') {
                    if (!is_numeric($pourcentage) || (float)$pourcentage <= 0 || (float)$pourcentage >= 100) {
                        throw new RuntimeException('Le pourcentage doit être compris entre 0 et 100 (exclus).');
                    }
                    $montantReduction = round($s['montant_initial'] * (float)$pourcentage / 100);
                } else {
                    if (!is_numeric($montantSaisi)) {
                        throw new RuntimeException('Indiquez le montant ou le pourcentage de la réduction.');
                    }
                    $montantReduction = round((float)$montantSaisi);
                    $pourcentage = '';
                }

                $plafond = $s['montant_initial'] - $s['paye_net'];

                if ($montantReduction <= 0) {
                    throw new RuntimeException('Le montant de la réduction doit être supérieur à 0.');
                }
                if ($montantReduction >= $s['montant_initial']) {
                    throw new RuntimeException('La réduction ne peut pas couvrir la totalité du montant initial.');
                }
                if ($montantReduction > $plafond) {
                    throw new RuntimeException('La réduction ne peut pas dépasser le montant restant dû (' . $fcfa($plafond) . ' FCFA) : sinon une partie déjà encaissée devrait être remboursée.');
                }

                $motif = trim((string)($_POST['motif'] ?? ''));
                $autorisePar = trim((string)($_POST['autorise_par'] ?? ''));
                $reference = trim((string)($_POST['reference_accord'] ?? ''));

                $pdo->prepare("
                    INSERT INTO reductions_accordees
                        (reservation_id, montant_reduction, pourcentage, motif, autorise_par, reference_accord, statut, saisi_par)
                    VALUES (?, ?, ?, ?, ?, ?, 'appliquee', ?)
                ")->execute([
                    $resaId,
                    $montantReduction,
                    $pourcentage !== '' ? (float)$pourcentage : null,
                    $motif !== '' ? mb_substr($motif, 0, 2000) : null,
                    $autorisePar !== '' ? mb_substr($autorisePar, 0, 150) : null,
                    $reference !== '' ? mb_substr($reference, 0, 150) : null,
                    $_SESSION['user_id'],
                ]);

                $apres = synchroniser_statut_paiement($pdo, $resaId);

                $texte = "Réduction de " . $fcfa($montantReduction) . " FCFA accordée sur la réservation #$resaId («{$resa['espace_nom']}», {$resa['nom_complet']}) : "
                    . $fcfa($apres['montant_initial']) . " → " . $fcfa($apres['net_du']) . " FCFA"
                    . ($motif !== '' ? ". Motif : $motif" : '')
                    . ($autorisePar !== '' ? ". Accord : $autorisePar" : '');

                log_activity('reduction_accordee', 'reservations', $texte);
                $notifications[] = ['superadmin', 'reduction_accordee', $texte, "paiements.php?resa=$resaId"];
                $notifications[] = ['ministre', 'reduction_accordee', $texte, "paiements.php?resa=$resaId"];

                $msgOk = 'Réduction de ' . $fcfa($montantReduction) . ' FCFA enregistrée. Net à payer : ' . $fcfa($apres['net_du']) . ' FCFA — reste à payer : ' . $fcfa($apres['solde']) . ' FCFA.';

            // ------------------------------------------------------------
            // A bis. RÉDUCTION ANNULÉE OU NON UTILISÉE
            // ------------------------------------------------------------
            } elseif ($action === 'statut_reduction') {

                $reductionId = (int)($_POST['reduction_id'] ?? 0);
                $nouveau = $_POST['nouveau_statut'] ?? '';
                $motifStatut = trim((string)($_POST['motif_statut'] ?? ''));

                if (!in_array($nouveau, ['annulee', 'non_appliquee'], true)) {
                    throw new RuntimeException('Statut de réduction invalide.');
                }
                if (mb_strlen($motifStatut) < 3) {
                    throw new RuntimeException('Indiquez la raison du changement.');
                }
                // Le maintien du tarif suite à réquisition n'est pas une réduction commerciale :
                // il ne peut être ni annulé ni marqué « non utilisé ».
                if ($s['prise_en_charge_requisition'] && (int)$s['reduction_appliquee']['id'] === $reductionId) {
                    throw new RuntimeException('Le maintien du tarif suite à réquisition ne peut pas être annulé ni marqué non utilisé.');
                }

                $upd = $pdo->prepare("
                    UPDATE reductions_accordees
                    SET statut = ?, motif_statut = ?, statut_modifie_par = ?, statut_modifie_le = NOW()
                    WHERE id = ? AND reservation_id = ? AND statut = 'appliquee'
                ");
                $upd->execute([$nouveau, mb_substr($motifStatut, 0, 2000), $_SESSION['user_id'], $reductionId, $resaId]);

                if ($upd->rowCount() !== 1) {
                    throw new RuntimeException('Cette réduction n\'est plus appliquée.');
                }

                $apres = synchroniser_statut_paiement($pdo, $resaId);

                $texte = 'Réduction #' . $reductionId . ' de la réservation #' . $resaId . ' '
                    . ($nouveau === 'annulee' ? 'annulée' : 'non utilisée (plein tarif)')
                    . ' — motif : ' . $motifStatut . '. Net à payer : ' . $fcfa($apres['net_du']) . ' FCFA.';

                log_activity('reduction_' . $nouveau, 'reservations', $texte);
                $notifications[] = ['superadmin', 'reduction_modifiee', $texte, "paiements.php?resa=$resaId"];

                $msgOk = $texte;

            // ------------------------------------------------------------
            // B. PAIEMENT RÉELLEMENT ENCAISSÉ
            // ------------------------------------------------------------
            } elseif ($action === 'enregistrer_paiement') {

                $mode = $_POST['mode'] ?? '';
                if (!isset($modeLabels[$mode])) {
                    throw new RuntimeException('Mode de paiement invalide.');
                }

                $s = situation_financiere_reservation($pdo, $resaId);

                // Le client règle finalement le plein tarif : la réduction accordée
                // reste tracée mais n'est pas utilisée (aucune réduction encaissée).
                // (sans effet sur une prise en charge suite à réquisition)
                if (!empty($_POST['plein_tarif']) && $s['reduction_appliquee'] && !$s['prise_en_charge_requisition']) {
                    $pdo->prepare("
                        UPDATE reductions_accordees
                        SET statut = 'non_appliquee',
                            motif_statut = 'Le client a réglé le plein tarif',
                            statut_modifie_par = ?, statut_modifie_le = NOW()
                        WHERE id = ? AND statut = 'appliquee'
                    ")->execute([$_SESSION['user_id'], (int)$s['reduction_appliquee']['id']]);

                    log_activity('reduction_non_appliquee', 'reservations',
                        'Réduction #' . (int)$s['reduction_appliquee']['id'] . ' de la réservation #' . $resaId . ' non utilisée : le client règle le plein tarif');

                    $s = situation_financiere_reservation($pdo, $resaId);
                }

                if ($s['solde'] <= 0) {
                    throw new RuntimeException('Cette réservation est déjà intégralement réglée.');
                }

                $type = $_POST['type_paiement'] ?? '';
                switch ($type) {
                    case 'complet':
                        $montant = $s['solde'];
                        break;
                    case 'acompte_25':
                        $montant = round($s['net_du'] * 0.25);
                        break;
                    case 'acompte_50':
                        $montant = round($s['net_du'] * 0.50);
                        break;
                    case 'personnalise':
                        $brut = str_replace([' ', "\u{00A0}"], '', (string)($_POST['montant'] ?? ''));
                        if (!is_numeric($brut)) {
                            throw new RuntimeException('Montant personnalisé invalide.');
                        }
                        $montant = round((float)$brut, 2);
                        break;
                    default:
                        throw new RuntimeException('Choisissez le type de paiement (complet, acompte 25 %, 50 % ou montant personnalisé).');
                }

                if ($montant <= 0) {
                    throw new RuntimeException('Le montant encaissé doit être supérieur à 0.');
                }
                if ($montant > $s['solde'] + 0.001) {
                    throw new RuntimeException('Le montant (' . $fcfa($montant) . ' FCFA) dépasse le reste à payer (' . $fcfa($s['solde']) . ' FCFA).'
                        . ($s['reduction_appliquee'] ? ' Si le client règle le plein tarif malgré la réduction, cochez « Le client paie le plein tarif ».' : ''));
                }

                // Un créneau déjà réglé (même partiellement) par une autre réservation ne peut pas être encaissé
                if ($s['total_paye'] <= 0 && !empty($resa['heure_debut'])) {
                    $occupant = creneau_occupe_par_reservation_payee($pdo, (int)$resa['espace_id'], $resa['date_resa'], $resa['heure_debut'], $resa['heure_fin'], $resaId);
                    if ($occupant) {
                        throw new RuntimeException("Le créneau est déjà réglé par la réservation #$occupant : cet encaissement créerait une double occupation.");
                    }
                }

                // Garde-fou : même montant encaissé il y a moins de 2 minutes sur cette réservation
                $recent = $pdo->prepare("SELECT id FROM paiements WHERE reservation_id = ? AND montant = ? AND created_at >= NOW() - INTERVAL 2 MINUTE LIMIT 1");
                $recent->execute([$resaId, $montant]);
                if ($recent->fetchColumn() && empty($_POST['confirmer_doublon'])) {
                    throw new RuntimeException('Un paiement identique vient d\'être enregistré sur cette réservation. S\'il s\'agit bien d\'un second versement distinct, cochez « Second versement distinct » et validez à nouveau.');
                }

                $ref  = mb_substr(trim((string)($_POST['reference'] ?? '')), 0, 100);
                $note = trim((string)($_POST['note'] ?? ''));

                $pdo->prepare("
                    INSERT INTO paiements (reservation_id, montant, montant_reference, motif_reduction, mode, reference, note, enregistre_par)
                    VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
                ")->execute([$resaId, $montant, $s['montant_initial'], $mode, $ref, $note, $_SESSION['user_id']]);

                $paiementId = (int)$pdo->lastInsertId();
                $crePaiement = $pdo->prepare("SELECT created_at FROM paiements WHERE id = ?");
                $crePaiement->execute([$paiementId]);
                $numeroRecu = ref_recu($paiementId, $crePaiement->fetchColumn());

                $apres = synchroniser_statut_paiement($pdo, $resaId);

                // Ce paiement départage les éventuelles demandes concurrentes sur le même créneau
                $annulees = annuler_reservations_concurrentes($pdo, $resaId);

                $dateResa = date('d/m/Y', strtotime($resa['date_resa']));
                $suffixe = $apres['statut_paiement_calcule'] === 'partiellement_paye'
                    ? ' — ACOMPTE, reste à payer : ' . $fcfa($apres['solde']) . ' FCFA avant le ' . date('d/m/Y à H:i', $apres['echeance_solde'])
                    : '';

                $notifications[] = ['admin_espaces', 'paiement_recu', "Paiement $numeroRecu reçu pour «{$resa['espace_nom']}» le $dateResa — {$resa['nom_complet']}$suffixe", "reservations.php?id=$resaId"];
                $notifications[] = ['superadmin', 'paiement_recu', "Paiement $numeroRecu de " . $fcfa($montant) . " FCFA enregistré — Réservation #$resaId$suffixe", "paiements.php?id=$paiementId"];
                $notifications[] = ['', 'paiement_confirme',
                    "Paiement confirmé ($numeroRecu) de " . $fcfa($montant) . " FCFA pour « {$resa['espace_nom']} »"
                    . ($apres['statut_paiement_calcule'] === 'partiellement_paye'
                        ? " — reste à payer : " . $fcfa($apres['solde']) . " FCFA avant le " . date('d/m/Y à H:i', $apres['echeance_solde']) . '.'
                        : ' — réservation entièrement réglée.')
                    . ' Votre document est disponible.',
                    "generer_bon.php?id=$resaId", (int)$resa['user_id']];

                log_activity('paiement_enregistre', 'reservations',
                    "Paiement $numeroRecu — " . $fcfa($montant) . " FCFA pour réservation #$resaId ($mode, $type) — payé " . $fcfa($apres['paye_net']) . ' / ' . $fcfa($apres['net_du']) . ' FCFA');

                $msgOk = "Paiement $numeroRecu de " . $fcfa($montant) . ' FCFA enregistré. '
                    . ($apres['statut_paiement_calcule'] === 'paye'
                        ? 'Réservation entièrement réglée.'
                        : 'Reste à payer : ' . $fcfa($apres['solde']) . ' FCFA avant le ' . date('d/m/Y à H:i', $apres['echeance_solde']) . '.')
                    . ($annulees ? ' ' . count($annulees) . ' demande(s) concurrente(s) sur ce créneau ont été annulées.' : '');

            } else {
                throw new RuntimeException('Action inconnue.');
            }

            $pdo->commit();

            foreach ($notifications as $n) {
                notify(...$n);
            }

            /*
             * Redirection après succès (Post/Redirect/Get) : actualiser la page
             * affiche l'état à jour du dossier au lieu de renvoyer le formulaire
             * (qui serait refusé par le jeton à usage unique et laisserait
             * croire que le paiement n'est pas passé).
             */
            $_SESSION['paiements_flash'] = ['ok', $msgOk];
            header('Location: paiements.php?resa=' . $resaId);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                $msg = ['err', $e->getMessage()];
            } else {
                error_log('paiements.php : ' . $e->getMessage());
                $msg = ['err', 'Erreur technique : l\'opération n\'a pas été enregistrée.'];
            }
        }
    }
}

// ============================================================
// FILTRES
// ============================================================
$filterMode   = $_GET['mode']   ?? '';
$search       = trim($_GET['q'] ?? '');
$filterDate   = (string)($_GET['date'] ?? '');
$filterEspace = (int)($_GET['espace'] ?? 0);
$openId       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$openResa     = isset($_GET['resa']) ? (int)$_GET['resa'] : (int)($_POST['reservation_id'] ?? 0);

$dObj = DateTime::createFromFormat('!Y-m-d', $filterDate);
if (!$dObj || $dObj->format('Y-m-d') !== $filterDate) {
    $filterDate = '';
}

$where  = [];
$params = [];

if ($filterMode && isset($modeLabels[$filterMode])) { $where[]='p.mode=?'; $params[]=$filterMode; }
if ($search) {
    // Client, espace, référence, n° de reçu, ou n° de réservation (12, #12, RESA-12)
    $cond = 'u.nom_complet LIKE ? OR e.nom LIKE ? OR p.reference LIKE ? OR CONCAT(RIGHT(YEAR(p.created_at),2), "-", LPAD(p.id,3,"0"), "/DGPP-C") LIKE ?';
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
    if (preg_match('/^#?(?:RESA-?)?(\d+)$/i', $search, $mResa)) {
        $cond .= ' OR p.reservation_id = ?';
        $params[] = (int)$mResa[1];
    }
    // Référence de réquisition (REQ-27) : réservation initiale et nouvelle réservation
    if (preg_match('/^#?REQ-?(\d+)$/i', $search, $mReq)) {
        $cond .= ' OR r.requisition_id = ? OR p.reservation_id = (SELECT rmq.reservation_id FROM requisitions_ministerielles rmq WHERE rmq.id = ?)';
        $params[] = (int)$mReq[1];
        $params[] = (int)$mReq[1];
    }
    $where[] = "($cond)";
}
if ($filterDate)   { $where[] = 'DATE(p.created_at) = ?'; $params[] = $filterDate; }
if ($filterEspace) { $where[] = 'r.espace_id = ?';        $params[] = $filterEspace; }
$filtreActif = $search || $filterMode || $filterDate || $filterEspace;

$espacesListe = $pdo->query("SELECT id, nom FROM espaces ORDER BY nom")->fetchAll();

// Historique des paiements
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

// Réservations validées restant à encaisser (totalement ou en partie)
$partenairesPaie = partenaires_disponibles($pdo);
$enAttente = $pdo->query("
    SELECT r.*, e.nom AS espace_nom, u.nom_complet, u.telephone, u.email,
           t.libelle AS tarif_libelle, t.unite
           " . ($partenairesPaie ? ", pa.nom AS partenaire_nom" : ", NULL AS partenaire_nom") . "
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    " . ($partenairesPaie ? "LEFT JOIN partenaires pa ON pa.id = r.partenaire_id" : "") . "
    WHERE r.statut = 'validee' AND r.statut_paiement IN ('non_paye','attente_paiement','partiellement_paye')
    ORDER BY " . ($partenairesPaie ? "(r.partenaire_id IS NOT NULL) DESC, " : "") . "r.date_resa ASC
")->fetchAll();

foreach ($enAttente as &$ea) {
    $ea['situation'] = situation_financiere_reservation($pdo, (int)$ea['id']);
}
unset($ea);

// Références de dossier (RESA / REQ / réservation initiale), en une requête par liste
$refsAttente   = references_dossiers($pdo, array_column($enAttente, 'id'));
$refsPaiements = references_dossiers($pdo, array_column($paiements, 'reservation_id'));

// Totaux : brut encaissé, remboursements effectués, net
$totalPaye     = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements")->fetchColumn();
$totalRembourse = (float)$pdo->query("SELECT COALESCE(SUM(COALESCE(montant_rembourse, montant_a_rembourser)),0) FROM remboursements WHERE resultat = 'effectue'")->fetchColumn();
$nbPaiements   = (int)$pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn();
$nbEnAttente   = count($enAttente);
$nbRetard      = count(array_filter($enAttente, fn($x) => $x['situation']['en_retard'] && $x['situation']['total_paye'] > 0));

// Lien ?resa=ID vers une réservation déjà réglée : on ouvre son dernier paiement
if ($openResa && !$openId && !in_array($openResa, array_map(fn($x) => (int)$x['id'], $enAttente), true)) {
    $dernier = $pdo->prepare("SELECT MAX(id) FROM paiements WHERE reservation_id = ?");
    $dernier->execute([$openResa]);
    $openId = (int)$dernier->fetchColumn();
}

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
    if ($openPaiement) {
        $openPaiement['situation'] = situation_financiere_reservation($pdo, (int)$openPaiement['reservation_id']);
        $openPaiement['refs'] = references_dossier($pdo, (int)$openPaiement['reservation_id']);
        $openPaiement['historique'] = historique_financier_reservation($pdo, (int)$openPaiement['reservation_id']);
    }
}

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

<?php
  // Paiement refusé car identique à un paiement récent : on propose alors la case « second versement »
  $doublonSignale = $msg && $msg[0] === 'err' && str_contains($msg[1], 'paiement identique');
?>

<!-- Stats : chaque carte mène aux données correspondantes -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  <?php foreach ([
      ['rapport.php?type=encaisse', 'fa-check-circle text-green-500', 'bg-green-50', $fcfa($totalPaye - $totalRembourse) . ' <span class="text-xs">FCFA</span>', 'text-green-600', 'Total encaissé', 'Brut ' . $fcfa($totalPaye) . ' FCFA — remboursé ' . $fcfa($totalRembourse) . ' FCFA'],
      ['#historique', 'fa-receipt text-blue-500', 'bg-blue-50', (string)$nbPaiements, 'text-primary', 'Paiements enregistrés', 'Voir l\'historique'],
      ['#a-encaisser', 'fa-clock text-amber-500', 'bg-amber-50', (string)$nbEnAttente, 'text-amber-500', 'En attente de paiement', 'Voir les réservations à encaisser'],
      ['acomptes.php?statut=retard', 'fa-exclamation-triangle text-accent', 'bg-red-50', (string)$nbRetard, 'text-accent', 'Soldes en retard', 'Voir les soldes en retard'],
  ] as [$lien, $ico, $bg, $val, $cls, $lib, $titre]): ?>
  <a href="<?= $lien ?>" title="<?= e($titre) ?>"
     class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 hover:shadow-md transition group min-w-0">
    <div class="flex items-center justify-between mb-2">
      <div class="w-9 h-9 <?= $bg ?> rounded-xl flex items-center justify-center">
        <i class="fas <?= $ico ?> text-sm"></i>
      </div>
      <i class="fas fa-arrow-right text-slate-200 text-xs group-hover:text-primary transition"></i>
    </div>
    <p class="text-base sm:text-2xl font-black <?= $cls ?>"><?= $val ?></p>
    <p class="text-[11px] sm:text-xs font-bold text-slate-500 mt-0.5"><?= $lib ?></p>
  </a>
  <?php endforeach; ?>
</div>

<!-- Réservations en attente de paiement -->
<?php if (!empty($enAttente)): ?>
<div id="a-encaisser" class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden mb-6">
  <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-5 py-4 border-b border-amber-100 bg-amber-50">
    <h2 class="flex-1 font-black text-amber-800 text-sm uppercase italic tracking-tight flex items-center gap-2">
      <i class="fas fa-clock text-amber-500"></i>
      <?= count($enAttente) ?> réservation(s) validée(s) — paiement en attente
    </h2>
    <div class="relative">
      <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
      <input type="search" id="rechercheAttente" oninput="filtrerAttente(this.value)" placeholder="Client, espace, n°, date…"
             class="w-full text-xs font-bold rounded-xl border border-amber-200 bg-white pl-8 pr-3 py-2 outline-none focus:border-primary text-primary">
    </div>
  </div>
  <p id="attenteVide" class="hidden px-5 py-6 text-sm text-slate-400 italic text-center">Aucune réservation ne correspond à la recherche.</p>
  <div class="divide-y divide-slate-50">
    <?php foreach ($enAttente as $r):
        $s = $r['situation'];
        $rid = (int)$r['id'];
        $estSejourResa = empty($r['heure_debut']);
        $nuitees = $estSejourResa ? max(1, (int)((strtotime($r['date_depart']) - strtotime($r['date_resa'])) / 86400)) : 1;
        $quantiteResa = max(1, (int)($r['quantite'] ?? 1));
        $ouvert = ($openResa === $rid);
        $acompte25 = round($s['net_du'] * 0.25);
        $acompte50 = round($s['net_du'] * 0.50);
        $proposer25 = $s['total_paye'] <= 0 && $acompte25 > 0 && $acompte25 < $s['solde'];
        $proposer50 = $s['total_paye'] <= 0 && $acompte50 > 0 && $acompte50 < $s['solde'];
        // Avertissement d'expiration : uniquement tant qu'aucun paiement n'a été reçu (délai de 48 h)
        $heuresRestantes = $s['echeance_premier_paiement'] ? ($s['echeance_premier_paiement'] - time()) / 3600 : null;
        $red = $s['reduction_appliquee'];
        // Maintien du tarif suite à réquisition : pas une réduction commerciale, non modifiable
        $priseEnCharge = $s['prise_en_charge_requisition'];
        $libelleMaintien = 'Maintien du tarif — réquisition #' . (int)($s['requisition_id'] ?? 0);
        $refsR = $refsAttente[$rid] ?? references_dossier($pdo, $rid);
        $rechercheTexte = mb_strtolower($r['nom_complet'] . ' ' . $r['espace_nom'] . ' #resa-' . $rid . ' ' . $rid . ' ' . $refsR['req'] . ' ' . $refsR['origine'] . ' ' . ($r['telephone'] ?? '') . ' ' . date('d/m/Y', strtotime($r['date_resa'])));
    ?>
    <div id="resa-<?= $rid ?>" data-recherche="<?= e($rechercheTexte) ?>" class="ligne-attente">
    <div onclick="<?= !$readonly ? "togglePayForm($rid)" : '' ?>" class="flex flex-col md:flex-row md:items-center gap-3 px-5 py-4 hover:bg-slate-50 transition <?= !$readonly ? 'cursor-pointer' : '' ?>">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="font-black text-primary text-sm"><?= e($r['nom_complet']) ?></p>
          <span class="text-xs text-slate-400">·</span>
          <p class="text-sm text-slate-600 font-semibold"><?= e($r['espace_nom']) ?></p>
          <span class="text-[10px] font-mono font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= e(libelle_references_dossier($refsR)) ?></span>
          <?php if (!empty($r['partenaire_nom'])): ?>
          <span class="text-[10px] font-black uppercase bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full"><i class="fas fa-handshake mr-1"></i>Partenaire · <?= e($r['partenaire_nom']) ?></span>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 flex-wrap">
          <?php if ($estSejourResa): ?>
          <span><i class="fas fa-calendar text-accent text-[10px] mr-1"></i><?= date('d/m/Y',strtotime($r['date_resa'])) ?> → <?= date('d/m/Y',strtotime($r['date_depart'])) ?> (<?= $nuitees ?> nuitée<?= $nuitees>1?'s':'' ?>)</span>
          <?php if ($quantiteResa > 1): ?><span class="text-indigo-600 font-bold"><i class="fas fa-door-open text-[10px] mr-1"></i><?= $quantiteResa ?> chambres</span><?php endif; ?>
          <?php if (!empty($r['petit_dejeuner'])): ?><span class="text-amber-600 font-bold"><i class="fas fa-coffee text-[10px] mr-1"></i>Petit-déj inclus</span><?php endif; ?>
          <?php else: ?>
          <span><i class="fas fa-calendar text-accent text-[10px] mr-1"></i><?= date('d/m/Y',strtotime($r['date_resa'])) ?></span>
          <span><i class="fas fa-clock text-accent text-[10px] mr-1"></i><?= substr($r['heure_debut'],0,5) ?> → <?= substr($r['heure_fin'],0,5) ?></span>
          <?php endif; ?>
          <?php if ($r['telephone']): ?>
          <span><i class="fas fa-phone text-accent text-[10px] mr-1"></i><?= e($r['telephone']) ?></span>
          <?php endif; ?>
          <span class="font-black text-primary"><i class="fas fa-tag text-accent text-[10px] mr-1"></i><?= $fcfa($s['net_du']) ?> FCFA<?= $red ? ' <span class="font-bold text-orange-600">(' . ($priseEnCharge ? e($libelleMaintien) . ' : ' : 'réduction ') . $fcfa($s['montant_reduction']) . ')</span>' : '' ?></span>
          <?php if ($s['total_paye'] > 0): ?>
          <span class="font-black <?= $s['en_retard'] ? 'text-red-600 bg-red-50' : 'text-sky-700 bg-sky-50' ?> px-2 py-0.5 rounded-full">
            <i class="fas fa-coins text-[10px] mr-1"></i>Acompte versé : <?= $fcfa($s['paye_net']) ?> FCFA — <?= $s['en_retard'] ? 'solde en retard' : 'solde' ?> : <?= $fcfa($s['solde']) ?> FCFA<?= $s['echeance_solde'] ? ($s['en_retard'] ? ' — échéance dépassée (' . date('d/m/Y H:i', $s['echeance_solde']) . ')' : ' — à régler avant le ' . date('d/m/Y H:i', $s['echeance_solde'])) : '' ?>
          </span>
          <?php endif; ?>
          <?php if ($heuresRestantes !== null && $heuresRestantes <= 6): ?>
          <span class="font-black text-red-600 bg-red-50 px-2 py-0.5 rounded-full"><i class="fas fa-clock text-[10px] mr-1"></i><?= $heuresRestantes > 0 ? 'Expire dans ' . max(1, round($heuresRestantes)) . 'h' : 'Expiration imminente' ?></span>
          <?php elseif ($heuresRestantes !== null && $heuresRestantes <= 24): ?>
          <span class="font-black text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full"><i class="fas fa-clock text-[10px] mr-1"></i>Expire dans <?= round($heuresRestantes) ?>h</span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$readonly): ?>
      <div class="flex-shrink-0 grid grid-cols-2 md:flex items-center gap-2">
        <a href="../generer_bon.php?id=<?= $rid ?>&from=paiements" target="_blank" onclick="event.stopPropagation()"
           class="flex items-center justify-center gap-2 bg-slate-100 text-slate-600 text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-200 transition">
          <i class="fas fa-file-invoice"></i> Voir le bon
        </a>
        <button type="button" onclick="event.stopPropagation(); togglePayForm(<?= $rid ?>)"
                class="flex items-center justify-center gap-2 bg-accent text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-accent-dark transition shadow-sm">
          <i class="fas fa-plus-circle"></i> Enregistrer le paiement
        </button>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$readonly): ?>
    <!-- Formulaire de paiement (déplié au clic) -->
    <div id="payForm-<?= $rid ?>" class="<?= $ouvert ? '' : 'hidden' ?> px-5 pb-5">
      <!-- Formulaires : les champs affichés plus bas y sont rattachés par l'attribut form -->
      <form id="fPay-<?= $rid ?>" method="POST" class="hidden" onsubmit="return confirmerPaiement(<?= $rid ?>)" data-solde="<?= (int)round($s['solde']) ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="jeton" value="<?= paiement_jeton() ?>">
        <input type="hidden" name="action" value="enregistrer_paiement">
        <input type="hidden" name="reservation_id" value="<?= $rid ?>">
        <input type="hidden" name="type_paiement" id="typePaiement-<?= $rid ?>" value="complet">
      </form>
      <?php if (!$red): ?>
      <form id="fRed-<?= $rid ?>" method="POST" class="hidden" onsubmit="return confirm('Enregistrer cette réduction ?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="jeton" value="<?= paiement_jeton() ?>">
        <input type="hidden" name="action" value="accorder_reduction">
        <input type="hidden" name="reservation_id" value="<?= $rid ?>">
      </form>
      <?php elseif (!$priseEnCharge): ?>
      <form id="fRedM-<?= $rid ?>" method="POST" class="hidden" onsubmit="return confirm('Confirmer le changement de cette réduction ?')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="jeton" value="<?= paiement_jeton() ?>">
        <input type="hidden" name="action" value="statut_reduction">
        <input type="hidden" name="reservation_id" value="<?= $rid ?>">
        <input type="hidden" name="reduction_id" value="<?= (int)$red['id'] ?>">
      </form>
      <?php endif; ?>

      <div class="bg-slate-50 rounded-2xl border border-slate-100 p-4 sm:p-5 space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Montant encaissé</p>
            <p id="montantAffiche-<?= $rid ?>" class="text-lg font-black text-accent"><?= $fcfa($s['solde']) ?> FCFA</p>
          </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" onclick="basculerAcompte(<?= $rid ?>)" class="text-xs font-black px-3 py-2 rounded-xl border-2 border-sky-200 bg-white text-sky-700 hover:bg-slate-50 transition">
                <i class="fas fa-coins mr-1"></i>Acompte
              </button>
              <?php if (!$red): ?>
              <button type="button" onclick="basculer('reduction-<?= $rid ?>')" class="text-xs font-black px-3 py-2 rounded-xl border-2 border-orange-200 bg-white text-orange-700 hover:bg-slate-50 transition">
                <i class="fas fa-percent mr-1"></i>Appliquer une réduction
              </button>
              <?php elseif ($priseEnCharge): ?>
              <span class="text-xs font-bold px-3 py-2 rounded-xl border-2 border-orange-200 bg-orange-50 text-orange-700" title="Prise en charge suite à réquisition : le client ne paie pas plus que pour sa réservation initiale">
                <?= e($libelleMaintien) ?> : <?= $fcfa($s['montant_reduction']) ?> pris en charge
              </span>
              <?php else: ?>
              <button type="button" onclick="basculer('reductionModif-<?= $rid ?>')" class="text-xs font-bold px-3 py-2 rounded-xl border-2 border-orange-200 bg-white text-orange-700 hover:bg-slate-50 transition">
                Réduction de <?= $fcfa($s['montant_reduction']) ?> appliquée · <span class="underline">modifier</span>
              </button>
              <?php endif; ?>
            </div>
        </div>

        <?php if (!$red): ?>
        <!-- Réduction : visible seulement après clic sur « Appliquer une réduction » -->
        <div id="reduction-<?= $rid ?>" class="hidden bg-orange-50 border-2 border-orange-200 rounded-xl p-4 space-y-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-orange-700"><i class="fas fa-percent mr-1"></i>Réduction</p>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Montant (FCFA)</label>
            <input form="fRed-<?= $rid ?>" type="number" name="montant_reduction" min="1" step="1" placeholder="Ex : 20000"
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-bold text-primary text-sm outline-none focus:border-orange-400">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">ou %</label>
            <input form="fRed-<?= $rid ?>" type="number" name="pourcentage" min="0.01" max="99.99" step="0.01" placeholder="Ex : 20"
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-bold text-primary text-sm outline-none focus:border-orange-400">
          </div>
          <div class="col-span-2">
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Motif <span class="text-accent">*</span></label>
            <input form="fRed-<?= $rid ?>" type="text" name="motif" list="motifsReductionSuggestions" required placeholder="Ex : accord de la Direction"
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-semibold text-primary text-sm outline-none focus:border-orange-400">
          </div>
          <div class="col-span-2">
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Accordée par</label>
            <input form="fRed-<?= $rid ?>" type="text" name="autorise_par" placeholder="Ex : Directeur Général"
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-semibold text-primary text-sm outline-none focus:border-orange-400">
          </div>
          <div class="col-span-2">
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Référence de l'accord</label>
            <input form="fRed-<?= $rid ?>" type="text" name="reference_accord" placeholder="Facultatif"
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-semibold text-primary text-sm outline-none focus:border-orange-400">
          </div>
        </div>
        <div class="flex items-center justify-between gap-3">
          <button type="button" onclick="basculer('reduction-<?= $rid ?>')" class="text-xs font-bold text-slate-400 hover:text-primary transition px-2 py-2">Annuler</button>
          <button type="submit" form="fRed-<?= $rid ?>" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl transition">Appliquer la réduction</button>
        </div>
        </div>
        <?php elseif (!$priseEnCharge): ?>
        <!-- Modification de la réduction : visible seulement après clic sur « modifier » -->
        <div id="reductionModif-<?= $rid ?>" class="hidden bg-orange-50 border-2 border-orange-200 rounded-xl p-4 space-y-3">
        <p class="text-xs text-orange-800">
          Réduction de <strong><?= $fcfa($red['montant_reduction']) ?> FCFA</strong><?= $red['motif'] ? ' — ' . e($red['motif']) : '' ?><?= $red['autorise_par'] ? ' (accord : ' . e($red['autorise_par']) . ')' : '' ?>
        </p>
        <div class="grid sm:grid-cols-2 gap-3">
          <select form="fRedM-<?= $rid ?>" name="nouveau_statut" class="w-full text-xs font-bold rounded-xl border-2 border-orange-200 bg-white px-3 py-2.5">
            <option value="annulee">Annuler la réduction</option>
            <option value="non_appliquee">Réduction non utilisée</option>
          </select>
          <input form="fRedM-<?= $rid ?>" type="text" name="motif_statut" required minlength="3" placeholder="Raison"
                 class="w-full text-xs font-semibold rounded-xl border-2 border-orange-200 bg-white px-3 py-2.5">
        </div>
        <div class="flex items-center justify-between gap-3">
          <button type="button" onclick="basculer('reductionModif-<?= $rid ?>')" class="text-xs font-bold text-slate-400 hover:text-primary transition px-2 py-2">Fermer</button>
          <button type="submit" form="fRedM-<?= $rid ?>" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl transition">Valider</button>
        </div>
        </div>
        <?php endif; ?>

        <!-- Acompte : visible seulement après clic sur « Acompte » -->
        <div id="acompte-<?= $rid ?>" class="hidden bg-sky-50 border-2 border-sky-200 rounded-xl p-4 space-y-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-sky-700"><i class="fas fa-coins mr-1"></i>Acompte</p>
          <div class="flex flex-wrap gap-2">
            <?php if ($proposer25): ?>
            <button type="button" data-type="acompte_25" data-montant="<?= (int)$acompte25 ?>" onclick="choisirAcompte(<?= $rid ?>, this)"
                    class="choix-acompte-<?= $rid ?> text-xs font-black px-3 py-2 rounded-xl border-2 border-sky-200 bg-white text-sky-700">25 % — <?= $fcfa($acompte25) ?> FCFA</button>
            <?php endif; ?>
            <?php if ($proposer50): ?>
            <button type="button" data-type="acompte_50" data-montant="<?= (int)$acompte50 ?>" onclick="choisirAcompte(<?= $rid ?>, this)"
                    class="choix-acompte-<?= $rid ?> text-xs font-black px-3 py-2 rounded-xl border-2 border-sky-200 bg-white text-sky-700">50 % — <?= $fcfa($acompte50) ?> FCFA</button>
            <?php endif; ?>
            <button type="button" data-type="personnalise" onclick="choisirAcompte(<?= $rid ?>, this)"
                    class="choix-acompte-<?= $rid ?> text-xs font-black px-3 py-2 rounded-xl border-2 border-sky-200 bg-white text-sky-700">Autre montant</button>
          </div>
          <div id="montantPerso-<?= $rid ?>" class="hidden">
            <label class="block text-[10px] font-black uppercase tracking-widest text-sky-700 mb-1">Montant de l'acompte (FCFA)</label>
            <input form="fPay-<?= $rid ?>" type="number" name="montant" min="1" max="<?= max(1, (int)floor($s['solde'])) ?>" step="1" placeholder="Maximum <?= $fcfa($s['solde']) ?>"
                   oninput="majMontantPerso(<?= $rid ?>)"
                   class="w-full sm:max-w-xs rounded-xl border-2 border-sky-200 bg-white px-3 py-2.5 font-black text-accent outline-none focus:border-primary text-sm">
          </div>
          <p class="text-[10px] text-sky-600">Le solde sera à régler au plus tard 24 h avant le début de la réservation.</p>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Mode de paiement <span class="text-accent">*</span></label>
            <select form="fPay-<?= $rid ?>" name="mode" required class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none text-sm">
              <?php foreach ($modeLabels as $val=>[$lab,$ico,$cls]): ?>
                <option value="<?= $val ?>"><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Référence de la transaction</label>
            <input form="fPay-<?= $rid ?>" type="text" name="reference" maxlength="100" placeholder="Ex : OM-123456789, VIR-2026-…, n° de reçu"
                   title="Référence réelle du paiement (Orange Money, virement, reçu…) — ne pas saisir la référence du dossier"
                   class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Note</label>
            <input form="fPay-<?= $rid ?>" type="text" name="note" placeholder="Observation..."
                   class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
        </div>

        <?php if ($red && !$priseEnCharge): ?>
        <label class="flex items-start gap-2 text-xs text-orange-800 bg-orange-50 border border-orange-200 rounded-xl p-3 cursor-pointer">
          <input form="fPay-<?= $rid ?>" type="checkbox" name="plein_tarif" value="1" onchange="majAffichage(<?= $rid ?>)" class="mt-0.5 w-4 h-4 accent-amber-600" id="pleinTarif-<?= $rid ?>"
                 data-solde-plein="<?= (int)round($s['montant_initial'] - $s['paye_net']) ?>" data-net-plein="<?= (int)round($s['montant_initial']) ?>">
          <span>Le client paie le plein tarif (la réduction ne sera pas utilisée).</span>
        </label>
        <?php endif; ?>

        <?php if ($doublonSignale && $ouvert): ?>
        <label class="flex items-start gap-2 text-xs text-slate-600 bg-white border border-slate-200 rounded-xl p-3 cursor-pointer">
          <input form="fPay-<?= $rid ?>" type="checkbox" name="confirmer_doublon" value="1" class="mt-0.5 w-4 h-4 accent-primary">
          <span>Second versement distinct : il s'agit bien d'un nouveau paiement.</span>
        </label>
        <?php endif; ?>

        <div class="flex items-center justify-between gap-3">
          <button type="button" onclick="togglePayForm(<?= $rid ?>)" class="text-xs font-bold text-slate-400 hover:text-primary transition px-2 py-2">Annuler</button>
          <button type="submit" form="fPay-<?= $rid ?>" id="confirmer-<?= $rid ?>" class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl transition shadow-sm">
            <i class="fas fa-check"></i> Confirmer le paiement
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($ouvert): ?>
    <div class="px-5 pb-5">
      <?php $histoEntrees = historique_financier_reservation($pdo, $rid); require __DIR__ . '/_historique_financier.php'; ?>
      <div class="flex flex-wrap gap-3 text-[11px] font-black mt-3">
        <a href="observations.php?cible_type=reservation&cible_id=<?= $rid ?>" class="text-slate-400 hover:text-accent transition">
          <i class="fas fa-eye mr-1"></i>Observer la réservation<?= ($nbObs = nb_observations($pdo, 'reservation', $rid)) ? ' (' . $nbObs . ')' : '' ?>
        </a>
        <?php if ($refsR['requisition_id']): ?>
        <a href="requisition-detail.php?id=<?= (int)$refsR['requisition_id'] ?>" class="text-slate-400 hover:text-accent transition">
          <i class="fas fa-landmark mr-1"></i>Dossier <?= e($refsR['req']) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="px-5 pb-3">
      <a href="paiements.php?resa=<?= $rid ?>#resa-<?= $rid ?>" class="text-[10px] font-black text-slate-400 hover:text-accent transition">
        <i class="fas fa-clock-rotate-left mr-1"></i>Historique du dossier
      </a>
    </div>
    <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Historique paiements -->
<div id="historique" class="grid lg:grid-cols-5 gap-5">
  <!-- Liste -->
  <div class="lg:col-span-3 min-w-0">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 bg-slate-50 space-y-3">
        <h2 class="font-black text-[10px] uppercase tracking-widest text-slate-400 flex items-center gap-2">
          <i class="fas fa-history text-accent"></i> Historique des paiements
        </h2>
        <!-- Recherche -->
        <form method="GET" action="paiements.php#historique" class="grid grid-cols-2 md:grid-cols-4 gap-2">
          <div class="relative col-span-2 md:col-span-2">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Client, n° réservation, n° reçu, référence…"
                   class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 outline-none focus:border-primary text-primary">
          </div>
          <input type="date" name="date" value="<?= e($filterDate) ?>" title="Date d'encaissement"
                 class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none focus:border-primary text-primary">
          <select name="espace" class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none text-primary">
            <option value="0">Toutes les salles</option>
            <?php foreach ($espacesListe as $esp): ?>
              <option value="<?= (int)$esp['id'] ?>" <?= $filterEspace === (int)$esp['id'] ? 'selected' : '' ?>><?= e($esp['nom']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="mode" class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none text-primary">
            <option value="">Tous modes</option>
            <?php foreach ($modeLabels as $val=>[$lab,$ico,$cls]): ?>
              <option value="<?= $val ?>" <?= $filterMode===$val?'selected':''?>><?= $lab ?></option>
            <?php endforeach; ?>
          </select>
          <div class="flex items-center gap-2">
            <button type="submit" class="flex-1 text-xs font-black bg-primary text-white px-3 py-2 rounded-xl hover:bg-slate-800 transition">Rechercher</button>
            <?php if ($filtreActif): ?>
            <a href="paiements.php#historique" class="text-xs font-bold text-slate-400 px-2 py-2 hover:text-accent transition" title="Réinitialiser"><i class="fas fa-times"></i></a>
            <?php endif; ?>
          </div>
        </form>
        <?php if ($filtreActif): ?>
        <p class="text-[11px] font-bold text-slate-500"><?= count($paiements) ?> résultat(s)</p>
        <?php endif; ?>
      </div>

      <?php if (empty($paiements)): ?>
        <div class="py-16 text-center">
          <i class="fas fa-receipt text-4xl text-slate-200 mb-3"></i>
          <p class="text-slate-400 font-semibold"><?= $filtreActif ? 'Aucun paiement ne correspond à la recherche.' : 'Aucun paiement enregistré.' ?></p>
        </div>
      <?php else: ?>
      <div class="divide-y divide-slate-50">
        <?php foreach ($paiements as $p):
          [$mlab,$mico,$mcls] = $modeLabels[$p['mode']] ?? ['—','fa-circle','bg-slate-100 text-slate-400'];
          $isOpen = ($openPaiement && $openPaiement['id']==$p['id']);
        ?>
        <a href="paiements.php?id=<?= $p['id'] ?>"
           class="flex items-start gap-3 px-4 sm:px-5 py-3.5 hover:bg-slate-50 transition <?= $isOpen?'bg-primary/5 border-l-4 border-primary':'' ?>">
          <div class="w-9 h-9 <?= $mcls ?> rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
            <i class="fas <?= $mico ?> text-xs"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-black text-primary text-sm truncate"><?= e($p['nom_complet']) ?></p>
              <span class="text-xs font-black text-green-600">+<?= $fcfa($p['montant']) ?> FCFA</span>
            </div>
            <p class="text-[9px] font-mono font-bold text-slate-400 mt-0.5">
              <?= ref_recu((int)$p['id'], $p['created_at']) ?> · <?= e(libelle_references_dossier($refsPaiements[(int)$p['reservation_id']] ?? references_dossier($pdo, (int)$p['reservation_id']))) ?>
              <?php if (!empty($p['motif_reduction'])): ?>
                <span class="ml-1 inline-block bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full"><i class="fas fa-percent"></i> Réduction</span>
              <?php endif; ?>
            </p>
            <p class="text-xs text-slate-500 truncate mt-0.5"><?= e($p['espace_nom']) ?> · <?= date('d/m/Y',strtotime($p['date_resa'])) ?></p>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
              <span class="text-[9px] font-black px-2 py-0.5 rounded-full <?= $mcls ?>"><?= $mlab ?></span>
              <?php if ($p['reference']): ?>
                <span class="text-[9px] text-slate-400 font-mono truncate"><?= e($p['reference']) ?></span>
              <?php endif; ?>
              <span class="text-[10px] text-slate-400 ml-auto whitespace-nowrap"><?= date('d/m/Y H:i',strtotime($p['created_at'])) ?></span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Détail -->
  <div class="lg:col-span-2 min-w-0">
    <?php if ($openPaiement):
      [$mlab,$mico,$mcls] = $modeLabels[$openPaiement['mode']] ?? ['—','fa-circle','bg-slate-100'];
      $so = $openPaiement['situation'];
    ?>
    <div id="detail-paiement" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden sticky top-24">
      <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between gap-3">
        <h2 class="font-black text-primary text-sm uppercase italic">Paiement <?= e(ref_recu((int)$openPaiement['id'], $openPaiement['created_at'])) ?></h2>
        <a href="paiements.php" class="w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent transition">
          <i class="fas fa-times text-xs"></i>
        </a>
      </div>
      <div class="p-6 space-y-5">
        <div class="text-center py-4 bg-green-50 rounded-2xl border border-green-100">
          <p class="text-3xl font-black text-green-600"><?= $fcfa($openPaiement['montant']) ?></p>
          <p class="text-sm font-black text-green-700 mt-0.5">FCFA</p>
          <span class="inline-block mt-2 text-[10px] font-black px-3 py-1 rounded-full <?= $mcls ?>">
            <i class="fas <?= $mico ?> mr-1"></i><?= $mlab ?>
          </span>
        </div>

        <a href="../generer_bon.php?id=<?= $openPaiement['reservation_id'] ?>&from=paiements" target="_blank"
           class="flex items-center justify-center gap-2 bg-primary text-white text-xs font-black uppercase px-4 py-3 rounded-xl hover:bg-slate-800 transition">
          <i class="fas fa-file-invoice"></i> Voir / Imprimer la facture
        </a>

        <?php if ($so && $so['montant_reduction'] > 0): ?>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1"><i class="fas fa-percent mr-1"></i> <?= $so['prise_en_charge_requisition'] ? 'Maintien du tarif — réquisition #' . (int)$so['requisition_id'] : 'Réduction accordée' ?></p>
          <p class="text-sm font-black text-orange-800">-<?= $fcfa($so['montant_reduction']) ?> FCFA</p>
          <?php $motifRed = $so['reduction_appliquee']['motif'] ?? $openPaiement['motif_reduction'] ?? ''; ?>
          <?php if ($motifRed): ?><p class="text-xs text-orange-700 mt-1"><?= e($motifRed) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($so && $so['solde'] > 0 && $so['statut_reservation'] === 'validee'): ?>
        <p class="text-xs font-bold text-sky-700 bg-sky-50 rounded-xl px-3 py-2">
          <i class="fas fa-coins mr-1"></i>Solde restant : <?= $fcfa($so['solde']) ?> FCFA<?= $so['echeance_solde'] ? ' — avant le ' . date('d/m/Y H:i', $so['echeance_solde']) : '' ?>
        </p>
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
          <div class="min-w-0">
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?= $label ?></p>
            <p class="text-sm font-bold text-primary mt-0.5"><?= e($val) ?></p>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Dossier</p>
          <p class="text-sm font-mono font-bold text-primary"><?= e(libelle_references_dossier($openPaiement['refs'])) ?></p>
        </div>

        <?php if ($openPaiement['reference']): ?>
        <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Référence de la transaction</p>
          <code class="text-sm font-mono text-primary"><?= e($openPaiement['reference']) ?></code>
        </div>
        <?php endif; ?>

        <?php if ($openPaiement['note']): ?>
        <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Note</p>
          <p class="text-sm text-slate-700 whitespace-pre-line"><?= e($openPaiement['note']) ?></p>
        </div>
        <?php endif; ?>

        <?php
          $nbObsPaiement = nb_observations($pdo, 'paiement', (int)$openPaiement['id']);
          $nbObsResa = nb_observations($pdo, 'reservation', (int)$openPaiement['reservation_id']);
        ?>
        <div class="flex flex-wrap gap-3 text-[11px] font-black">
          <a href="observations.php?cible_type=paiement&cible_id=<?= (int)$openPaiement['id'] ?>" class="text-slate-400 hover:text-accent transition">
            <i class="fas fa-eye mr-1"></i>Observer ce paiement<?= $nbObsPaiement ? ' (' . $nbObsPaiement . ')' : '' ?>
          </a>
          <a href="observations.php?cible_type=reservation&cible_id=<?= (int)$openPaiement['reservation_id'] ?>" class="text-slate-400 hover:text-accent transition">
            <i class="fas fa-eye mr-1"></i>Observer la réservation<?= $nbObsResa ? ' (' . $nbObsResa . ')' : '' ?>
          </a>
          <?php if (!empty($openPaiement['refs']['requisition_id'])): ?>
          <a href="requisition-detail.php?id=<?= (int)$openPaiement['refs']['requisition_id'] ?>" class="text-slate-400 hover:text-accent transition">
            <i class="fas fa-landmark mr-1"></i>Dossier <?= e($openPaiement['refs']['req']) ?>
          </a>
          <?php endif; ?>
        </div>

        <?php $histoEntrees = $openPaiement['historique']; require __DIR__ . '/_historique_financier.php'; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="hidden lg:block bg-white rounded-2xl border border-slate-100 py-20 text-center">
      <i class="fas fa-hand-pointer text-3xl text-slate-200 mb-3"></i>
      <p class="font-black text-slate-400 text-sm uppercase">Sélectionnez un paiement</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const fmtFcfa = n => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0)) + ' FCFA';
function togglePayForm(id) {
    const f = document.getElementById('payForm-'+id);
    if (!f) return;
    f.classList.toggle('hidden');
    if (!f.classList.contains('hidden')) f.scrollIntoView({behavior:'smooth',block:'center'});
}
function basculer(idBloc) {
    const b = document.getElementById(idBloc);
    if (b) b.classList.toggle('hidden');
}
// Montant qui sera réellement encaissé (même calcul que le serveur)
function montantChoisi(id) {
    const form = document.getElementById('typePaiement-'+id).form;
    const type = document.getElementById('typePaiement-'+id).value;
    const plein = document.getElementById('pleinTarif-'+id);
    const pleinCoche = plein && plein.checked;
    if (type === 'personnalise') return parseFloat(document.querySelector(`#montantPerso-${id} input`).value) || 0;  // 0 = à saisir
    if (type === 'acompte_25' || type === 'acompte_50') {
        const taux = type === 'acompte_25' ? 0.25 : 0.50;
        if (pleinCoche) return Math.round(plein.dataset.netPlein * taux);
        return parseFloat(document.querySelector(`#acompte-${id} [data-type="${type}"]`).dataset.montant) || 0;
    }
    return pleinCoche ? parseFloat(plein.dataset.soldePlein) : parseFloat(form.dataset.solde);
}
function majAffichage(id) {
    const m = montantChoisi(id);
    document.getElementById('montantAffiche-'+id).textContent = m > 0 ? fmtFcfa(m) : 'Montant à saisir';
}
function selectionnerType(id, type) {
    document.getElementById('typePaiement-'+id).value = type;
    document.querySelectorAll('.choix-acompte-'+id).forEach(b => {
        const actif = b.dataset.type === type;
        b.classList.toggle('bg-sky-100', actif);
        b.classList.toggle('ring-1', actif);
        b.classList.toggle('ring-accent/50', actif);
        b.classList.toggle('bg-white', !actif);
    });
    const perso = document.getElementById('montantPerso-'+id);
    const estPerso = type === 'personnalise';
    perso.classList.toggle('hidden', !estPerso);
    perso.querySelector('input').required = estPerso;
    majAffichage(id);
}
function basculerAcompte(id) {
    const bloc = document.getElementById('acompte-'+id);
    bloc.classList.toggle('hidden');
    if (bloc.classList.contains('hidden')) {
        selectionnerType(id, 'complet');           // fermeture : retour au paiement complet
    } else {
        const premier = bloc.querySelector('[data-type]');
        if (premier) selectionnerType(id, premier.dataset.type);
    }
}
function choisirAcompte(id, bouton) { selectionnerType(id, bouton.dataset.type); }
function majMontantPerso(id) { majAffichage(id); }
function confirmerPaiement(id) {
    const montant = montantChoisi(id);
    if (!montant || montant <= 0) { alert('Indiquez le montant encaissé.'); return false; }
    return confirm('Confirmer l\'encaissement de ' + fmtFcfa(montant) + ' ?');
}
function filtrerAttente(texte) {
    const t = texte.trim().toLowerCase();
    let visibles = 0;
    document.querySelectorAll('.ligne-attente').forEach(l => {
        const ok = !t || l.dataset.recherche.includes(t);
        l.classList.toggle('hidden', !ok);
        if (ok) visibles++;
    });
    document.getElementById('attenteVide').classList.toggle('hidden', visibles > 0);
}
document.addEventListener('DOMContentLoaded', function() {
<?php if ($openPaiement): ?>
    document.getElementById('detail-paiement')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
<?php elseif ($openResa): ?>
    document.getElementById('resa-<?= (int)$openResa ?>')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
<?php endif; ?>
});
</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>
