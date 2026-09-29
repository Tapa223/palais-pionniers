<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','admin_comptable','admin_espaces','admin_activites','admin_messages']);

$pdo   = db();
$role  = $_SESSION['role'] ?? '';
$type  = $_GET['type'] ?? '';
$debut = $_GET['debut'] ?? '';
$fin   = $_GET['fin']   ?? '';

// Dates de filtre : format AAAA-MM-JJ uniquement
$dateValide = function (string $d): string {
    $o = DateTime::createFromFormat('!Y-m-d', $d);
    return ($o && $o->format('Y-m-d') === $d) ? $d : '';
};
$debut = $dateValide((string)$debut);
$fin   = $dateValide((string)$fin);

/*
 * Droits d'accès, vérifiés côté serveur.
 * Les exports financiers sont réservés à la comptabilité et à la
 * Direction (ministre, superadmin). L'administration des espaces
 * n'obtient que l'export des réservations, sans colonne financière.
 */
$rolesComptables = ['ministre', 'admin_comptable', 'superadmin'];
$typesAutorises = [
    'paiements'      => $rolesComptables,
    'remboursements' => $rolesComptables,
    'reductions'     => $rolesComptables,
    'requisitions'   => $rolesComptables,
    'baux'           => $rolesComptables,
    'reservations'   => ['ministre', 'admin_espaces', 'admin_comptable', 'superadmin'],
    'jeunes_engages' => ['ministre', 'admin_activites', 'superadmin'],
    // Boîte à suggestions anonyme : aucune donnée d'identification de l'auteur
    'suggestions'    => ['ministre', 'admin_espaces', 'admin_activites', 'admin_messages', 'admin_comptable', 'superadmin'],
];

if (!isset($typesAutorises[$type]) || !in_array($role, $typesAutorises[$type], true)) {
    http_response_code(403);
    exit('Export non autorisé pour ce rôle.');
}

$accesFinancier = in_array($role, $rolesComptables, true);

$where  = [];
$params = [];

/*
 * Situation financière par réservation (fonction centrale), mise en cache
 * pour ne la calculer qu'une fois par réservation dans un export.
 */
$situations = [];
$situation = function (?int $reservationId) use ($pdo, &$situations): array {
    if (!$reservationId) {
        return [];
    }
    if (!isset($situations[$reservationId])) {
        $situations[$reservationId] = situation_financiere_reservation($pdo, $reservationId) ?? [];
    }
    return $situations[$reservationId];
};

// Montants : entiers sans séparateur (tri et calculs dans Excel)
$fin2 = fn($v) => $v === null || $v === '' ? '' : number_format((float)$v, 0, '', '');
$dateH = fn($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '';
$dateJ = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '';

$libStatutResa = [
    'en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée',
    'annulee' => 'Annulée', 'expiree' => 'Expirée', 'requisitionnee' => 'Réquisitionnée',
];
$libMode = [
    'especes' => 'Espèces', 'orange_money' => 'Orange Money', 'moov_money' => 'Moov Money',
    'virement' => 'Virement', 'cheque' => 'Chèque',
];
$libChoix = [
    'annulation' => 'Annulation', 'remboursement' => 'Remboursement',
    'nouvelle_date' => 'Nouvelle date', 'autre_espace' => 'Autre espace',
];
$libStatutReq = [
    'en_attente_choix' => 'En attente du choix', 'choix_recu' => 'Choix reçu',
    'en_traitement' => 'En traitement', 'cloturee' => 'Clôturée',
    'annulee' => 'Annulée', 'traite' => 'Traitée',
];
$etatFinancier = function (array $s): string {
    return $s ? libelle_etat_financier($s['etat'])[0] : '';
};
$natureReduction = function (array $s): string {
    if (($s['montant_reduction'] ?? 0) <= 0) {
        return '';
    }
    return !empty($s['prise_en_charge_requisition']) ? 'Prise en charge réquisition' : 'Commerciale';
};

// Références de dossier chargées après la requête principale
$refs = [];
$ref = function (?int $reservationId) use (&$refs, $pdo): array {
    if (!$reservationId) {
        return ['resa' => '', 'req' => '', 'origine' => '', 'nouvelle' => '', 'requisition_id' => null, 'origine_id' => null, 'nouvelle_id' => null];
    }
    if (!isset($refs[$reservationId])) {
        $refs[$reservationId] = references_dossier($pdo, $reservationId);
    }
    return $refs[$reservationId];
};
$colonneRefs = [];

if ($type === 'paiements') {
    $dateCol = 'p.created_at';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT p.id, p.created_at, p.reservation_id, u.nom_complet AS client, u.telephone, e.nom AS espace,
               r.date_resa, r.date_depart, r.statut AS statut_resa, p.montant, p.montant_reference, p.motif_reduction,
               p.mode, p.reference, p.note, admin.nom_complet AS enregistre_par
        FROM paiements p
        JOIN reservations r ON r.id = p.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN users admin ON admin.id = p.enregistre_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY p.created_at DESC, p.id DESC
    ";
    $colonneRefs = ['reservation_id'];
    $filename = 'paiements_' . date('Y-m-d_His') . '.csv';
    $headers  = [
        'N° reçu', 'Date encaissement', 'Réservation', 'Réquisition', 'Réservation initiale',
        'Client', 'Téléphone', 'Espace', 'Date réservation', 'Date départ',
        'Montant encaissé (FCFA)', 'Mode de paiement', 'Référence transaction', 'Paiement transféré (réquisition)',
        'Enregistré par', 'Note',
        'Statut réservation', 'État financier réservation', 'Montant initial réservation (FCFA)',
        'Réduction appliquée (FCFA)', 'Nature de la réduction', 'Net dû réservation (FCFA)',
        'Payé net réservation (FCFA)', 'Remboursé réservation (FCFA)', 'Solde réservation (FCFA)',
        'Motif réduction (ancien système)', 'Montant de référence (ancien système)',
    ];
    $mapRow = function ($r) use ($situation, $ref, $fin2, $dateH, $dateJ, $libMode, $libStatutResa, $etatFinancier, $natureReduction) {
        $s = $situation((int)$r['reservation_id']);
        $rf = $ref((int)$r['reservation_id']);
        return [
            ref_recu((int)$r['id'], $r['created_at']),
            $dateH($r['created_at']),
            $rf['resa'], $rf['req'], $rf['origine'],
            $r['client'], $r['telephone'] ?? '', $r['espace'],
            $dateJ($r['date_resa']), $dateJ($r['date_depart']),
            $fin2($r['montant']), $libMode[$r['mode']] ?? $r['mode'], $r['reference'] ?? '',
            str_contains((string)$r['note'], 'Paiement transféré') ? 'Oui' : 'Non',
            $r['enregistre_par'] ?? '', $r['note'] ?? '',
            $libStatutResa[$r['statut_resa']] ?? $r['statut_resa'],
            $etatFinancier($s),
            $fin2($s['montant_initial'] ?? null), $fin2($s['montant_reduction'] ?? null), $natureReduction($s),
            $fin2($s['net_du'] ?? null), $fin2($s['paye_net'] ?? null), $fin2($s['total_rembourse'] ?? null), $fin2($s['solde'] ?? null),
            $r['motif_reduction'] ?? '', $r['motif_reduction'] ? $fin2($r['montant_reference']) : '',
        ];
    };

} elseif ($type === 'remboursements') {
    $dateCol = 'COALESCE(rb.date_traitement, rb.created_at)';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT rb.id, rb.reservation_id, rb.requisition_id, rb.montant_paye, rb.montant_a_rembourser,
               rb.montant_rembourse, rb.mode, rb.reference, rb.resultat, rb.motif,
               rb.date_demande, rb.date_traitement, rb.created_at,
               rm.choix_client, u.nom_complet AS client, u.telephone, admin.nom_complet AS traite_par,
               (
                   SELECT n.id FROM reservations n
                   WHERE n.requisition_id = rb.requisition_id AND n.statut IN ('validee', 'requisitionnee')
                   ORDER BY n.id DESC LIMIT 1
               ) AS nouvelle_id
        FROM remboursements rb
        JOIN users u ON u.id = rb.client_id
        LEFT JOIN requisitions_ministerielles rm ON rm.id = rb.requisition_id
        LEFT JOIN users admin ON admin.id = rb.traite_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY $dateCol DESC
    ";
    $filename = 'remboursements_' . date('Y-m-d_His') . '.csv';
    $headers  = [
        'N° bon', 'Nature', 'Statut', 'Réservation concernée', 'Réquisition', 'Réservation initiale', 'Nouvelle réservation',
        'Client', 'Téléphone', 'Montant encaissé (FCFA)', 'Montant à rembourser (FCFA)', 'Montant remboursé (FCFA)',
        'Date de demande', 'Date d\'exécution', 'Mode', 'Référence transaction', 'Motif', 'Traité par',
    ];
    $mapRow = function ($r) use ($fin2, $dateH, $libMode) {
        $tropPercu = in_array($r['choix_client'], ['nouvelle_date', 'autre_espace'], true);
        $effectue = ($r['resultat'] ?? '') === 'effectue';
        $nouvelle = $tropPercu && $r['nouvelle_id'] ? ref_resa((int)$r['nouvelle_id']) : '';
        return [
            'BR-' . date('Y', strtotime($r['date_traitement'] ?? $r['created_at'])) . '-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT),
            $tropPercu ? 'Trop-perçu (réquisition)' : 'Remboursement (réquisition)',
            $effectue ? 'Effectué' : 'Non effectué',
            $nouvelle !== '' ? $nouvelle : ref_resa((int)$r['reservation_id']),
            ref_req($r['requisition_id'] ? (int)$r['requisition_id'] : null),
            ref_resa((int)$r['reservation_id']),
            $nouvelle,
            $r['client'], $r['telephone'] ?? '',
            $fin2($r['montant_paye']), $fin2($r['montant_a_rembourser']),
            $effectue ? $fin2($r['montant_rembourse'] ?? $r['montant_a_rembourser']) : '',
            $dateH($r['date_demande'] ?? $r['created_at']), $effectue ? $dateH($r['date_traitement']) : '',
            $libMode[$r['mode'] ?? ''] ?? ($r['mode'] ?? ''), $r['reference'] ?? '', $r['motif'] ?? '', $r['traite_par'] ?? '',
        ];
    };

} elseif ($type === 'reductions') {
    $dateCol = 'ra.created_at';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $origineDispo = reductions_origine_disponible($pdo);
    $sql = "
        SELECT ra.*, " . ($origineDispo ? '' : "'commerciale' AS origine, ") . "
               r.date_resa, r.statut AS statut_resa, u.nom_complet AS client, e.nom AS espace,
               us.nom_complet AS saisi_nom, um.nom_complet AS modifie_nom
        FROM reductions_accordees ra
        JOIN reservations r ON r.id = ra.reservation_id
        JOIN users u ON u.id = r.user_id
        JOIN espaces e ON e.id = r.espace_id
        LEFT JOIN users us ON us.id = ra.saisi_par
        LEFT JOIN users um ON um.id = ra.statut_modifie_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY ra.created_at DESC, ra.id DESC
    ";
    $colonneRefs = ['reservation_id'];
    $filename = 'reductions_' . date('Y-m-d_His') . '.csv';
    $headers  = [
        'N° réduction', 'Date de saisie', 'Nature', 'Statut', 'Montant (FCFA)', 'Pourcentage',
        'Réservation', 'Réquisition', 'Réservation initiale', 'Client', 'Espace', 'Date réservation', 'Statut réservation',
        'Motif', 'Autorisée par', 'Référence de l\'accord', 'Saisie par',
        'Statut modifié par', 'Date du changement de statut', 'Motif du changement', 'Reportée de la réduction n°',
    ];
    $mapRow = function ($r) use ($ref, $fin2, $dateH, $dateJ, $libStatutResa) {
        $rf = $ref((int)$r['reservation_id']);
        return [
            (int)$r['id'], $dateH($r['created_at']),
            ($r['origine'] ?? '') === 'requisition' ? 'Maintien du tarif (réquisition)' : 'Réduction commerciale',
            ['appliquee' => 'Appliquée', 'non_appliquee' => 'Non utilisée', 'annulee' => 'Annulée'][$r['statut']] ?? $r['statut'],
            $fin2($r['montant_reduction']), $r['pourcentage'] !== null ? rtrim(rtrim((string)$r['pourcentage'], '0'), '.') : '',
            $rf['resa'], $rf['req'], $rf['origine'], $r['client'], $r['espace'], $dateJ($r['date_resa']),
            $libStatutResa[$r['statut_resa']] ?? $r['statut_resa'],
            $r['motif'] ?? '', $r['autorise_par'] ?? '', $r['reference_accord'] ?? '', $r['saisi_nom'] ?? '',
            $r['modifie_nom'] ?? '', $dateH($r['statut_modifie_le'] ?? null), $r['motif_statut'] ?? '',
            $r['reportee_de'] ? (int)$r['reportee_de'] : '',
        ];
    };

} elseif ($type === 'requisitions') {
    $dateCol = 'rm.date_declenchee';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT rm.*, a.date_resa AS date_a, ea.nom AS espace_a, u.nom_complet AS client, u.telephone,
               ud.nom_complet AS declenche_nom, ut.nom_complet AS traite_nom,
               (
                   SELECT n.id FROM reservations n
                   WHERE n.requisition_id = rm.id
                   ORDER BY (n.statut IN ('validee', 'requisitionnee')) DESC, (n.statut = 'en_attente') DESC, n.id DESC
                   LIMIT 1
               ) AS nouvelle_id,
               (
                   SELECT COALESCE(SUM(COALESCE(rb.montant_rembourse, rb.montant_a_rembourser)), 0)
                   FROM remboursements rb WHERE rb.requisition_id = rm.id AND rb.resultat = 'effectue'
               ) AS rembourse
        FROM requisitions_ministerielles rm
        JOIN reservations a ON a.id = rm.reservation_id
        JOIN espaces ea ON ea.id = a.espace_id
        JOIN users u ON u.id = a.user_id
        LEFT JOIN users ud ON ud.id = rm.declenche_par
        LEFT JOIN users ut ON ut.id = rm.traite_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY rm.date_declenchee DESC, rm.id DESC
    ";
    $filename = 'requisitions_' . date('Y-m-d_His') . '.csv';
    $headers  = [
        'Réquisition', 'Date de déclenchement', 'Déclenchée par', 'Statut', 'Choix du client', 'Date du choix',
        'Réservation initiale', 'Client', 'Téléphone', 'Espace initial', 'Date initiale',
        'Net dû réservation initiale (FCFA)', 'Payé net restant sur la réservation initiale (FCFA)',
        'Nouvelle réservation', 'Statut nouvelle réservation', 'Espace nouvelle réservation', 'Date nouvelle réservation',
        'Montant initial nouvelle réservation (FCFA)', 'Prise en charge réquisition (FCFA)', 'Réduction commerciale reportée (FCFA)',
        'Net dû nouvelle réservation (FCFA)', 'Payé net nouvelle réservation (FCFA)', 'Solde (FCFA)', 'Trop-perçu restant (FCFA)',
        'Remboursé (FCFA)', 'Traité par', 'Date de clôture', 'Motif de la réquisition',
    ];
    $mapRow = function ($r) use ($pdo, $situation, $fin2, $dateH, $dateJ, $libStatutReq, $libChoix, $libStatutResa) {
        $sa = $situation((int)$r['reservation_id']);
        $nid = $r['nouvelle_id'] ? (int)$r['nouvelle_id'] : null;
        $sb = $situation($nid);
        $nouvelle = null;
        if ($nid) {
            $st = $pdo->prepare("SELECT n.statut, n.date_resa, e.nom FROM reservations n JOIN espaces e ON e.id = n.espace_id WHERE n.id = ?");
            $st->execute([$nid]);
            $nouvelle = $st->fetch();
        }
        $priseEnCharge = ($sb && !empty($sb['prise_en_charge_requisition'])) ? $sb['montant_reduction'] : 0;
        $reductionReportee = ($sb && empty($sb['prise_en_charge_requisition'])) ? ($sb['montant_reduction'] ?? 0) : 0;
        return [
            ref_req((int)$r['id']), $dateH($r['date_declenchee']), $r['declenche_nom'] ?? '',
            $libStatutReq[$r['statut']] ?? $r['statut'],
            $libChoix[$r['choix_client'] ?? ''] ?? '', $dateH($r['date_choix']),
            ref_resa((int)$r['reservation_id']), $r['client'], $r['telephone'] ?? '', $r['espace_a'], $dateJ($r['date_a']),
            $fin2($sa['net_du'] ?? null), $fin2($sa['paye_net'] ?? null),
            ref_resa($nid), $nouvelle ? ($libStatutResa[$nouvelle['statut']] ?? $nouvelle['statut']) : '',
            $nouvelle['nom'] ?? '', $nouvelle ? $dateJ($nouvelle['date_resa']) : '',
            $sb ? $fin2($sb['montant_initial']) : '', $sb ? $fin2($priseEnCharge) : '', $sb ? $fin2($reductionReportee) : '',
            $sb ? $fin2($sb['net_du']) : '', $sb ? $fin2($sb['paye_net']) : '',
            ($sb && ($nouvelle['statut'] ?? '') === 'validee') ? $fin2($sb['solde']) : '',
            $sb ? $fin2($sb['trop_percu']) : '',
            $fin2($r['rembourse']), $r['traite_nom'] ?? '',
            $r['statut'] === 'cloturee' ? $dateH($r['date_traitement']) : '',
            $r['motif'] ?? '',
        ];
    };

} elseif ($type === 'baux') {
    $dateCol = 'bp.periode_debut';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin; }
    $sql = "
        SELECT bp.id, bp.periode_debut, bp.duree_mois, bp.montant, bp.mode, bp.reference, bp.created_at,
               e.nom AS espace, e.gerant_nom, e.gerant_prenom, admin.nom_complet AS enregistre_par
        FROM bail_paiements bp
        JOIN espaces e ON e.id = bp.espace_id
        JOIN users admin ON admin.id = bp.enregistre_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY bp.periode_debut DESC
    ";
    $filename = 'baux_' . date('Y-m-d_His') . '.csv';
    $headers  = ['N° Reçu','Espace','Gestionnaire','Début période','Durée (mois)','Montant (FCFA)','Mode','Référence transaction','Date encaissement','Enregistré par'];
    $mapRow = function($r) use ($fin2, $libMode) {
        return [
            ref_recu((int)$r['id'], $r['created_at']),
            $r['espace'],
            trim(($r['gerant_prenom'] ?? '').' '.($r['gerant_nom'] ?? '')),
            date('d/m/Y', strtotime($r['periode_debut'])),
            $r['duree_mois'],
            $fin2($r['montant']), $libMode[$r['mode']] ?? $r['mode'], $r['reference'] ?? '',
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['enregistre_par'],
        ];
    };

} elseif ($type === 'jeunes_engages') {
    $sql = "SELECT nom, prenom, age, telephone, email, commune, domaine_interet, motivation, statut, created_at FROM jeunes_engages ORDER BY created_at DESC";
    $filename = 'jeunes_engages_' . date('Y-m-d_His') . '.csv';
    $headers  = ['Nom','Prénom','Âge','Téléphone','Email','Commune','Domaine d\'intérêt','Motivation','Statut','Date d\'inscription'];
    $mapRow = function($r) {
        return [$r['nom'], $r['prenom'], $r['age'], $r['telephone'], $r['email'], $r['commune'], $r['domaine_interet'], $r['motivation'], $r['statut'], date('d/m/Y H:i', strtotime($r['created_at']))];
    };

} elseif ($type === 'suggestions') {
    if (!suggestions_disponibles($pdo)) {
        http_response_code(404);
        exit('Module suggestions non installé.');
    }
    if ($debut) { $where[] = "sg.created_at >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "sg.created_at <= ?"; $params[] = $fin   . ' 23:59:59'; }
    // Seules les colonnes de la suggestion elle-même (aucune IP, aucun compte auteur)
    $sql = "
        SELECT sg.id, sg.created_at, sg.statut, sg.contenu, sg.statut_modifie_le, u.nom_complet AS traite_par
        FROM suggestions sg
        LEFT JOIN users u ON u.id = sg.statut_modifie_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY sg.created_at DESC
    ";
    $filename = 'suggestions_' . date('Y-m-d_His') . '.csv';
    $headers  = ['Référence', 'Date', 'Statut', 'Contenu', 'Date de traitement', 'Traité par'];
    $libStatutSug = ['nouvelle' => 'Nouvelle', 'lue' => 'Lue', 'traitee' => 'Traitée'];
    $mapRow = function ($r) use ($dateH, $libStatutSug) {
        // Texte saisi publiquement : neutralise les formules à l'ouverture dans un tableur
        $contenu = (string)$r['contenu'];
        if ($contenu !== '' && strpbrk($contenu[0], "=+-@\t\r") !== false) {
            $contenu = "'" . $contenu;
        }
        return [
            'SUG-' . (int)$r['id'], $dateH($r['created_at']),
            $libStatutSug[$r['statut']] ?? $r['statut'], $contenu,
            $r['statut'] !== 'nouvelle' ? $dateH($r['statut_modifie_le']) : '',
            $r['statut'] !== 'nouvelle' ? ($r['traite_par'] ?? '') : '',
        ];
    };

} else { // reservations
    $dateCol = 'r.created_at';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT r.id, r.created_at, r.canal, r.quantite, u.nom_complet AS client, u.telephone, e.nom AS espace,
               " . (partenaires_disponibles($pdo) ? "(SELECT p.nom FROM partenaires p WHERE p.id = r.partenaire_id)" : "NULL") . " AS partenaire,
               r.date_resa, r.date_depart, r.heure_debut, r.heure_fin, r.statut,
               t.libelle AS tarif
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN tarifs t ON t.id = r.tarif_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY r.created_at DESC
    ";
    $colonneRefs = ['id'];
    $filename = 'reservations_' . date('Y-m-d_His') . '.csv';

    // Colonnes communes (gestion des espaces et des réservations)
    $headers  = ['Réservation','Réquisition','Réservation initiale','Remplacée par','Canal','Date création','Client','Partenaire','Téléphone','Espace','Date résa','Date départ','Heure début','Heure fin','Quantité','Statut','Tarif'];
    // Colonnes financières : uniquement pour la comptabilité et la Direction
    if ($accesFinancier) {
        $headers = array_merge($headers, [
            'État financier', 'Montant initial (FCFA)', 'Réduction appliquée (FCFA)', 'Nature de la réduction', 'Net dû (FCFA)',
            'Encaissé (FCFA)', 'Remboursé (FCFA)', 'Payé net (FCFA)', 'Solde (FCFA)', 'Trop-perçu (FCFA)', 'Réduction accordée non utilisée (FCFA)',
        ]);
    }
    $mapRow = function($r) use ($situation, $ref, $fin2, $dateH, $dateJ, $libStatutResa, $accesFinancier, $etatFinancier, $natureReduction) {
        $rf = $ref((int)$r['id']);
        $ligne = [
            $rf['resa'], $rf['req'], $rf['origine'], $rf['nouvelle'],
            $r['canal'] === 'guichet' ? 'Guichet' : 'En ligne',
            $dateH($r['created_at']),
            $r['client'], $r['partenaire'] ?? '', $r['telephone'] ?? '', $r['espace'],
            $dateJ($r['date_resa']), $dateJ($r['date_depart']),
            $r['heure_debut'] ? substr($r['heure_debut'],0,5) : '',
            $r['heure_fin']   ? substr($r['heure_fin'],0,5)   : '',
            (int)($r['quantite'] ?? 1),
            $libStatutResa[$r['statut']] ?? $r['statut'], $r['tarif'] ?? '',
        ];
        if (!$accesFinancier) {
            return $ligne;
        }
        $s = $situation((int)$r['id']);
        $nonUtilisee = 0.0;
        foreach ($s['reductions_non_appliquees'] ?? [] as $red) {
            $nonUtilisee += (float)$red['montant_reduction'];
        }
        return array_merge($ligne, [
            $etatFinancier($s),
            $fin2($s['montant_initial'] ?? null), $fin2($s['montant_reduction'] ?? null), $natureReduction($s),
            $fin2($s['net_du'] ?? null),
            $fin2($s['total_paye'] ?? null), $fin2($s['total_rembourse'] ?? null), $fin2($s['paye_net'] ?? null),
            ($r['statut'] === 'validee') ? $fin2($s['solde'] ?? null) : '',
            $fin2($s['trop_percu'] ?? null),
            $nonUtilisee > 0 ? $fin2($nonUtilisee) : '',
        ]);
    };
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Références de dossier préchargées en une requête (listes longues)
if ($colonneRefs) {
    $ids = [];
    foreach ($rows as $r) {
        foreach ($colonneRefs as $c) {
            $ids[] = (int)$r[$c];
        }
    }
    $refs = references_dossiers($pdo, $ids) + $refs;
}

log_activity('export_' . $type, $type === 'suggestions' ? 'messages' : 'reservations', count($rows) . ' ligne(s) exportée(s) (' . $type . ')'
    . ($debut || $fin ? ' — période ' . ($debut ?: '…') . ' → ' . ($fin ?: '…') : ''));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
fputcsv($out, $headers, ';', '"', '\\');
foreach ($rows as $r) {
    fputcsv($out, $mapRow($r), ';', '"', '\\');
}
fclose($out);
exit;
