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
                if (!empty($_POST['plein_tarif']) && $s['reduction_appliquee']) {
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

            $msg = ['ok', $msgOk];

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
$openId       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$openResa     = isset($_GET['resa']) ? (int)$_GET['resa'] : (int)($_POST['reservation_id'] ?? 0);

$where  = [];
$params = [];

if ($filterMode && isset($modeLabels[$filterMode])) { $where[]='p.mode=?'; $params[]=$filterMode; }
if ($search) {
    $where[]='(u.nom_complet LIKE ? OR e.nom LIKE ? OR p.reference LIKE ? OR CONCAT(RIGHT(YEAR(p.created_at),2), "-", LPAD(p.id,3,"0"), "/DGPP-C") LIKE ?)';
    $params=array_merge($params,["%$search%","%$search%","%$search%","%$search%"]);
}

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
$enAttente = $pdo->query("
    SELECT r.*, e.nom AS espace_nom, u.nom_complet, u.telephone, u.email,
           t.libelle AS tarif_libelle, t.unite
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    WHERE r.statut = 'validee' AND r.statut_paiement IN ('non_paye','attente_paiement','partiellement_paye')
    ORDER BY r.date_resa ASC
")->fetchAll();

foreach ($enAttente as &$ea) {
    $ea['situation'] = situation_financiere_reservation($pdo, (int)$ea['id']);
}
unset($ea);

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

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center mb-3">
      <i class="fas fa-check-circle text-green-500"></i>
    </div>
    <p class="text-2xl font-black text-green-600"><?= $fcfa($totalPaye - $totalRembourse) ?> <span class="text-sm">FCFA</span></p>
    <p class="text-xs font-bold text-slate-500 mt-1">Encaissé net</p>
    <p class="text-[10px] text-slate-400 mt-0.5">Brut <?= $fcfa($totalPaye) ?> — remboursé <?= $fcfa($totalRembourse) ?></p>
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
    <p class="text-xs font-bold text-slate-500 mt-1">À encaisser (total ou solde)</p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 <?= $nbRetard>0?'border-red-200':'' ?>">
    <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center mb-3">
      <i class="fas fa-exclamation-triangle text-accent"></i>
    </div>
    <p class="text-2xl font-black text-accent"><?= $nbRetard ?></p>
    <p class="text-xs font-bold text-slate-500 mt-1">Soldes en retard</p>
  </div>
</div>

<!-- Réservations à encaisser -->
<?php if (!empty($enAttente)): ?>
<div class="bg-white rounded-2xl border-2 border-amber-200 shadow-sm overflow-hidden mb-6">
  <div class="flex items-center gap-3 px-5 py-4 border-b border-amber-100 bg-amber-50">
    <i class="fas fa-clock text-amber-500"></i>
    <h2 class="font-black text-amber-800 text-sm uppercase italic tracking-tight">
      <?= count($enAttente) ?> réservation(s) validée(s) — paiement ou solde à encaisser
    </h2>
  </div>
  <div class="divide-y divide-slate-50">
    <?php foreach ($enAttente as $r):
        $s = $r['situation'];
        $estSejourResa = empty($r['heure_debut']);
        $nuitees = $estSejourResa ? max(1, (int)((strtotime($r['date_depart']) - strtotime($r['date_resa'])) / 86400)) : 1;
        [$etatLib, $etatCls] = libelle_etat_financier($s['etat']);
        $echeance = $s['echeance_solde'] ?? $s['echeance_premier_paiement'];
        $heuresRestantes = $echeance ? ($echeance - time()) / 3600 : null;
        $ouvert = ($openResa === (int)$r['id']);
        $acompte25 = round($s['net_du'] * 0.25);
        $acompte50 = round($s['net_du'] * 0.50);
    ?>
    <div id="resa-<?= $r['id'] ?>">
    <div onclick="<?= !$readonly ? "togglePayForm({$r['id']})" : '' ?>" class="flex flex-col md:flex-row md:items-center gap-4 px-5 py-4 hover:bg-slate-50 transition <?= !$readonly ? 'cursor-pointer' : '' ?>">
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <p class="font-black text-primary text-sm"><?= e($r['nom_complet']) ?></p>
          <span class="text-xs text-slate-400">·</span>
          <p class="text-sm text-slate-600 font-semibold"><?= e($r['espace_nom']) ?></p>
          <span class="text-[10px] font-mono font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">#RESA-<?= $r['id'] ?></span>
          <span class="text-[10px] font-black px-2 py-0.5 rounded-full border <?= $etatCls ?>"><?= e($etatLib) ?></span>
        </div>
        <div class="flex items-center gap-3 mt-1 text-xs text-slate-500 flex-wrap">
          <?php if ($estSejourResa): ?>
          <span><i class="fas fa-calendar text-accent text-[10px] mr-1"></i><?= date('d/m/Y',strtotime($r['date_resa'])) ?> → <?= date('d/m/Y',strtotime($r['date_depart'])) ?> (<?= $nuitees ?> nuitée<?= $nuitees>1?'s':'' ?>)</span>
          <?php else: ?>
          <span><i class="fas fa-calendar text-accent text-[10px] mr-1"></i><?= date('d/m/Y',strtotime($r['date_resa'])) ?></span>
          <span><i class="fas fa-clock text-accent text-[10px] mr-1"></i><?= substr($r['heure_debut'],0,5) ?> → <?= substr($r['heure_fin'],0,5) ?></span>
          <?php endif; ?>
          <?php if ($r['telephone']): ?>
          <span><i class="fas fa-phone text-accent text-[10px] mr-1"></i><?= e($r['telephone']) ?></span>
          <?php endif; ?>
        </div>
        <!-- Situation financière -->
        <div class="flex items-center gap-2 mt-2 flex-wrap text-[11px]">
          <span class="font-bold text-slate-500">Initial <span class="font-black text-primary"><?= $fcfa($s['montant_initial']) ?></span></span>
          <?php if ($s['montant_reduction'] > 0): ?>
          <span class="font-bold text-orange-600">− réduction <?= $fcfa($s['montant_reduction']) ?></span>
          <span class="font-bold text-slate-500">= net <span class="font-black text-primary"><?= $fcfa($s['net_du']) ?></span></span>
          <?php endif; ?>
          <span class="font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-full">Payé <?= $fcfa($s['paye_net']) ?></span>
          <span class="font-black text-accent bg-red-50 px-2 py-0.5 rounded-full">Reste <?= $fcfa($s['solde']) ?> FCFA</span>
          <?php if ($echeance): ?>
          <span class="font-bold <?= $s['en_retard'] ? 'text-red-600 bg-red-50' : ($heuresRestantes <= 24 ? 'text-amber-600 bg-amber-50' : 'text-slate-500 bg-slate-50') ?> px-2 py-0.5 rounded-full">
            <i class="fas fa-clock text-[10px] mr-1"></i>
            <?= $s['echeance_solde'] ? 'Solde avant le ' : 'Premier paiement avant le ' ?><?= date('d/m/Y H:i', $echeance) ?>
            <?= $s['en_retard'] ? '— en retard' : '' ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!$readonly): ?>
      <div class="flex-shrink-0 flex items-center gap-2">
        <a href="../generer_bon.php?id=<?= $r['id'] ?>&from=paiements" target="_blank" onclick="event.stopPropagation()"
           class="flex items-center gap-2 bg-slate-100 text-slate-600 text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-200 transition">
          <i class="fas fa-file-invoice"></i> Document
        </a>
        <button onclick="event.stopPropagation(); togglePayForm(<?= $r['id'] ?>)"
                class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-accent-dark transition shadow-sm">
          <i class="fas fa-plus-circle"></i> Encaisser
        </button>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$readonly): ?>
    <div id="payForm-<?= $r['id'] ?>" class="<?= $ouvert ? '' : 'hidden' ?> px-5 pb-5 space-y-4">

      <!-- Récapitulatif -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center">
        <?php foreach ([
            ['Montant initial', $s['montant_initial'], 'text-primary'],
            ['Réduction appliquée', $s['montant_reduction'], 'text-orange-600'],
            ['Net à payer', $s['net_du'], 'text-primary'],
            ['Déjà payé', $s['paye_net'], 'text-sky-700'],
            ['Reste à payer', $s['solde'], 'text-accent'],
        ] as [$lib, $val, $cls]): ?>
        <div class="bg-slate-50 rounded-xl border border-slate-100 p-3">
          <p class="text-[9px] font-black uppercase tracking-widest text-slate-400"><?= $lib ?></p>
          <p class="text-sm font-black <?= $cls ?> mt-1"><?= $fcfa($val) ?> <span class="text-[9px]">FCFA</span></p>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- A. Réduction éventuelle -->
      <div class="bg-orange-50/60 rounded-2xl border border-orange-100 p-4">
        <p class="text-[10px] font-black uppercase tracking-widest text-orange-700 mb-3"><i class="fas fa-percent mr-1"></i>A. Réduction éventuelle</p>

        <?php if ($s['reduction_appliquee']): $red = $s['reduction_appliquee']; ?>
          <div class="flex flex-col md:flex-row md:items-start gap-3">
            <div class="flex-1 text-xs text-orange-800">
              <p class="font-black">Réduction appliquée : <?= $fcfa($red['montant_reduction']) ?> FCFA<?= $red['pourcentage'] !== null ? ' (' . rtrim(rtrim($red['pourcentage'], '0'), '.') . ' %)' : '' ?></p>
              <?php if ($red['motif']): ?><p class="mt-0.5">Motif : <?= e($red['motif']) ?></p><?php endif; ?>
              <?php if ($red['autorise_par']): ?><p class="mt-0.5">Accord : <?= e($red['autorise_par']) ?><?= $red['reference_accord'] ? ' — réf. ' . e($red['reference_accord']) : '' ?></p><?php endif; ?>
              <p class="mt-0.5 text-orange-600">Enregistrée par <?= e($red['saisi_par_nom'] ?? '—') ?> le <?= date('d/m/Y H:i', strtotime($red['created_at'])) ?></p>
            </div>
            <form method="POST" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Confirmer le changement de cette réduction ?')">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="jeton" value="<?= paiement_jeton() ?>">
              <input type="hidden" name="action" value="statut_reduction">
              <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="reduction_id" value="<?= (int)$red['id'] ?>">
              <select name="nouveau_statut" class="text-xs font-bold rounded-xl border-2 border-orange-200 bg-white px-3 py-2">
                <option value="annulee">Annuler la réduction</option>
                <option value="non_appliquee">Réduction non utilisée</option>
              </select>
              <input type="text" name="motif_statut" required minlength="3" placeholder="Raison"
                     class="text-xs font-semibold rounded-xl border-2 border-orange-200 bg-white px-3 py-2 w-44">
              <button class="text-xs font-black uppercase bg-white border-2 border-orange-300 text-orange-700 px-3 py-2 rounded-xl hover:bg-orange-100 transition">Valider</button>
            </form>
          </div>
        <?php else: ?>
          <form method="POST" class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end" onsubmit="return confirm('Enregistrer cette réduction ?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="jeton" value="<?= paiement_jeton() ?>">
            <input type="hidden" name="action" value="accorder_reduction">
            <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Montant (FCFA)</label>
              <input type="number" name="montant_reduction" min="1" step="1" placeholder="Ex : 20000"
                     class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-bold text-primary text-sm">
            </div>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">ou %</label>
              <input type="number" name="pourcentage" min="0.01" max="99.99" step="0.01" placeholder="Ex : 20"
                     class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-bold text-primary text-sm">
            </div>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Motif</label>
              <input type="text" name="motif" list="motifsReductionSuggestions" placeholder="Motif"
                     class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-semibold text-primary text-sm">
            </div>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">Accordée par / réf.</label>
              <input type="text" name="autorise_par" placeholder="Ex : Directeur Général"
                     class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-semibold text-primary text-sm mb-1">
              <input type="text" name="reference_accord" placeholder="Réf. (facultatif)"
                     class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2 font-semibold text-primary text-sm">
            </div>
            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
              Enregistrer la réduction
            </button>
          </form>
          <p class="text-[10px] text-orange-600 mt-2">Facultatif. La réduction diminue le montant net à payer ; elle est tracée et notifiée à la Direction et au Ministre. Elle ne peut pas dépasser le reste à payer (<?= $fcfa($s['montant_initial'] - $s['paye_net']) ?> FCFA).</p>
        <?php endif; ?>

        <?php foreach (array_merge($s['reductions_non_appliquees'], $s['reductions_annulees']) as $old): ?>
          <p class="text-[10px] text-slate-500 mt-2">
            <i class="fas fa-history mr-1"></i>Réduction de <?= $fcfa($old['montant_reduction']) ?> FCFA —
            <?= $old['statut'] === 'annulee' ? 'annulée' : 'non utilisée' ?><?= $old['motif_statut'] ? ' (' . e($old['motif_statut']) . ')' : '' ?>
          </p>
        <?php endforeach; ?>
      </div>

      <!-- B. Paiement -->
      <form method="POST" class="bg-slate-50 rounded-2xl border border-slate-100 p-4 space-y-4" onsubmit="return confirmerPaiement(<?= $r['id'] ?>)">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="jeton" value="<?= paiement_jeton() ?>">
        <input type="hidden" name="action" value="enregistrer_paiement">
        <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500"><i class="fas fa-money-bill-wave mr-1"></i>B. Paiement réellement encaissé</p>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2" id="typesPaiement-<?= $r['id'] ?>">
          <?php
            $choix = [['complet', 'Paiement complet', $s['solde']]];
            if ($s['total_paye'] <= 0 && $acompte25 < $s['solde']) $choix[] = ['acompte_25', 'Acompte 25 %', $acompte25];
            if ($s['total_paye'] <= 0 && $acompte50 < $s['solde']) $choix[] = ['acompte_50', 'Acompte 50 %', $acompte50];
            $choix[] = ['personnalise', 'Montant personnalisé', null];
            foreach ($choix as $i => [$val, $lib, $mt]):
          ?>
          <label class="flex items-start gap-2 p-3 rounded-xl border-2 border-slate-200 bg-white cursor-pointer hover:border-primary transition">
            <input type="radio" name="type_paiement" value="<?= $val ?>" <?= $i === 0 ? 'checked' : '' ?> onchange="majTypePaiement(<?= $r['id'] ?>)" class="mt-0.5 w-4 h-4 accent-primary"
                   data-montant="<?= $mt !== null ? (int)$mt : '' ?>">
            <span>
              <span class="block text-xs font-black text-slate-700"><?= $lib ?></span>
              <span class="block text-[11px] font-bold text-accent"><?= $mt !== null ? $fcfa($mt) . ' FCFA' : 'saisi ci-dessous' ?></span>
            </span>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div id="montantPerso-<?= $r['id'] ?>" class="hidden">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Montant encaissé FCFA <span class="text-accent">*</span></label>
            <input type="number" name="montant" min="1" max="<?= (int)ceil($s['solde']) ?>" step="1" placeholder="Max <?= $fcfa($s['solde']) ?>"
                   class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-black text-accent outline-none focus:border-accent text-sm">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Mode de paiement <span class="text-accent">*</span></label>
            <select name="mode" required class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none text-sm">
              <?php foreach ($modeLabels as $val=>[$lab,$ico,$cls]): ?>
                <option value="<?= $val ?>"><?= $lab ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Référence / N° reçu</label>
            <input type="text" name="reference" maxlength="100" placeholder="Ex: OM-123456"
                   class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Note</label>
            <input type="text" name="note" placeholder="Observation..."
                   class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
        </div>

        <?php if ($s['reduction_appliquee']): ?>
        <label class="flex items-start gap-2 text-xs text-orange-800 bg-orange-50 border border-orange-200 rounded-xl p-3 cursor-pointer">
          <input type="checkbox" name="plein_tarif" value="1" onchange="majTypePaiement(<?= $r['id'] ?>)" class="mt-0.5 w-4 h-4 accent-orange-600" id="pleinTarif-<?= $r['id'] ?>"
                 data-solde-plein="<?= (int)round($s['montant_initial'] - $s['paye_net']) ?>" data-net-plein="<?= (int)round($s['montant_initial']) ?>">
          <span><strong>Le client paie le plein tarif</strong> : la réduction accordée reste tracée mais ne sera pas utilisée
          (reste à payer au plein tarif : <?= $fcfa($s['montant_initial'] - $s['paye_net']) ?> FCFA).</span>
        </label>
        <?php endif; ?>

        <div class="flex items-center justify-between flex-wrap gap-3">
          <label class="flex items-center gap-2 text-[11px] text-slate-500">
            <input type="checkbox" name="confirmer_doublon" value="1" class="w-3.5 h-3.5">
            Second versement distinct (si un paiement identique vient d'être enregistré)
          </label>
          <div class="flex items-center gap-3">
            <button type="button" onclick="togglePayForm(<?= $r['id'] ?>)" class="text-xs font-bold text-slate-400 hover:text-primary transition">Fermer</button>
            <button type="submit" class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-xs font-black uppercase px-6 py-2.5 rounded-xl transition shadow-sm">
              <i class="fas fa-check"></i> Confirmer le paiement
            </button>
          </div>
        </div>
      </form>
    </div>
    <?php endif; ?>
    </div>
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
              <span class="text-xs font-black text-green-600">+<?= $fcfa($p['montant']) ?> FCFA</span>
            </div>
            <p class="text-[9px] font-mono font-bold text-slate-400 mt-0.5">
              <?= ref_recu((int)$p['id'], $p['created_at']) ?>
              <?php if (!empty($p['motif_reduction'])): ?>
                <span class="ml-1 inline-block bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full"><i class="fas fa-percent"></i> Réduction (ancien format)</span>
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
      $so = $openPaiement['situation'];
    ?>
    <div id="detail-paiement" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden sticky top-24">
      <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
        <h2 class="font-black text-primary text-sm uppercase italic">Paiement <?= e(ref_recu((int)$openPaiement['id'], $openPaiement['created_at'])) ?></h2>
        <a href="paiements.php" class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent transition">
          <i class="fas fa-times text-xs"></i>
        </a>
      </div>
      <div class="p-6 space-y-5">
        <!-- Montant -->
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

        <?php if ($so): ?>
        <div class="bg-slate-50 rounded-xl border border-slate-100 p-3 text-xs space-y-1">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Situation de la réservation #<?= (int)$openPaiement['reservation_id'] ?></p>
          <p class="flex justify-between"><span>Montant initial</span><strong><?= $fcfa($so['montant_initial']) ?> FCFA</strong></p>
          <?php if ($so['montant_reduction'] > 0): ?>
          <p class="flex justify-between text-orange-700"><span>Réduction appliquée</span><strong>− <?= $fcfa($so['montant_reduction']) ?> FCFA</strong></p>
          <?php endif; ?>
          <p class="flex justify-between"><span>Net dû</span><strong><?= $fcfa($so['net_du']) ?> FCFA</strong></p>
          <p class="flex justify-between text-sky-700"><span>Total payé</span><strong><?= $fcfa($so['total_paye']) ?> FCFA</strong></p>
          <?php if ($so['total_rembourse'] > 0): ?>
          <p class="flex justify-between text-emerald-700"><span>Remboursé</span><strong>− <?= $fcfa($so['total_rembourse']) ?> FCFA</strong></p>
          <?php endif; ?>
          <p class="flex justify-between text-accent"><span>Reste à payer</span><strong><?= $fcfa($so['solde']) ?> FCFA</strong></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($openPaiement['motif_reduction'])): ?>
        <div class="bg-orange-50 border-2 border-orange-200 rounded-xl p-3">
          <p class="text-[10px] font-black uppercase tracking-widest text-orange-700 mb-1">
            <i class="fas fa-percent mr-1"></i> Réduction (ancien format, saisie sur le paiement)
          </p>
          <p class="text-sm font-black text-orange-800">
            -<?= $fcfa((float)$openPaiement['montant_reference'] - (float)$openPaiement['montant']) ?> FCFA
            <span class="font-normal text-xs text-orange-600">(tarif <?= $fcfa($openPaiement['montant_reference']) ?> FCFA)</span>
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
          <p class="text-sm text-slate-700 whitespace-pre-line"><?= e($openPaiement['note']) ?></p>
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
    if (!f) return;
    f.classList.toggle('hidden');
    if (!f.classList.contains('hidden')) f.scrollIntoView({behavior:'smooth',block:'center'});
}
function majTypePaiement(id) {
    const choix = document.querySelector(`#typesPaiement-${id} input[name="type_paiement"]:checked`);
    const perso = document.getElementById('montantPerso-'+id);
    const estPerso = choix && choix.value === 'personnalise';
    perso.classList.toggle('hidden', !estPerso);
    perso.querySelector('input').required = estPerso;
}
function confirmerPaiement(id) {
    const choix = document.querySelector(`#typesPaiement-${id} input[name="type_paiement"]:checked`);
    if (!choix) { alert('Choisissez le type de paiement.'); return false; }
    const plein = document.getElementById('pleinTarif-'+id);
    let montant = choix.value === 'personnalise'
        ? document.querySelector(`#montantPerso-${id} input`).value
        : choix.dataset.montant;
    if (plein && plein.checked) {
        // Montants recalculés au plein tarif (comme côté serveur)
        if (choix.value === 'complet') montant = plein.dataset.soldePlein;
        if (choix.value === 'acompte_25') montant = Math.round(plein.dataset.netPlein * 0.25);
        if (choix.value === 'acompte_50') montant = Math.round(plein.dataset.netPlein * 0.50);
    }
    return confirm('Confirmer l\'encaissement de ' + new Intl.NumberFormat('fr-FR').format(montant || 0) + ' FCFA ?');
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
