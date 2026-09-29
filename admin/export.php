<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','admin_comptable','admin_espaces','admin_activites']);

$pdo   = db();
$role  = $_SESSION['role'] ?? '';
$type  = $_GET['type'] ?? '';
$debut = $_GET['debut'] ?? '';
$fin   = $_GET['fin']   ?? '';

$typesAutorises = [
    'paiements'    => ['ministre','admin_comptable','superadmin'],
    'reservations' => ['ministre','admin_espaces','admin_comptable','superadmin'],
    'baux'         => ['ministre','admin_comptable','superadmin'],
    'remboursements' => ['ministre','admin_comptable','superadmin'],
    'jeunes_engages' => ['ministre','admin_activites','superadmin'],
];

if (!isset($typesAutorises[$type]) || !in_array($role, $typesAutorises[$type], true)) {
    http_response_code(403);
    exit('Export non autorisé pour ce rôle.');
}

$where  = [];

/*
 * Situation financière par réservation (fonction centrale), mise en cache
 * pour ne la calculer qu'une fois par réservation dans un export.
 */
$situations = [];
$situation = function (int $reservationId) use ($pdo, &$situations): array {
    if (!isset($situations[$reservationId])) {
        $situations[$reservationId] = situation_financiere_reservation($pdo, $reservationId) ?? [];
    }
    return $situations[$reservationId];
};
$fin2 = fn($v) => $v === null || $v === '' ? '' : number_format((float)$v, 0, '', '');
$params = [];

if ($type === 'paiements') {
    $dateCol = 'p.created_at';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT p.id, p.created_at, p.reservation_id, u.nom_complet AS client, e.nom AS espace,
               r.date_resa, r.date_depart, p.montant, p.montant_reference, p.motif_reduction,
               p.mode, p.reference, p.note, admin.nom_complet AS enregistre_par
        FROM paiements p
        JOIN reservations r ON r.id = p.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        JOIN users admin ON admin.id = p.enregistre_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY p.created_at DESC
    ";
    $filename = 'paiements_' . date('Y-m-d_His') . '.csv';
    $headers  = ['N° Reçu','Date encaissement','Client','Espace','Date résa','Date départ','Montant encaissé (FCFA)','Montant de référence saisi (FCFA)','Motif réduction (ancien système)','Mode','Référence','Note','Enregistré par',
                 'Réservation','Montant initial résa (FCFA)','Réduction appliquée résa (FCFA)','Net dû résa (FCFA)','Total encaissé résa (FCFA)','Remboursé résa (FCFA)','Solde résa (FCFA)'];
    $mapRow = function($r) use ($situation, $fin2) {
        $s = $situation((int)$r['reservation_id']);
        return [
            ref_recu((int)$r['id'], $r['created_at']),
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['client'], $r['espace'],
            date('d/m/Y', strtotime($r['date_resa'])),
            $r['date_depart'] ? date('d/m/Y', strtotime($r['date_depart'])) : '',
            $r['montant'], $r['montant_reference'] ?? '', $r['motif_reduction'] ?? '',
            $r['mode'], $r['reference'] ?? '', $r['note'] ?? '', $r['enregistre_par'],
            'RESA-' . $r['reservation_id'],
            $fin2($s['montant_initial'] ?? null), $fin2($s['montant_reduction'] ?? null), $fin2($s['net_du'] ?? null),
            $fin2($s['total_paye'] ?? null), $fin2($s['total_rembourse'] ?? null), $fin2($s['solde'] ?? null),
        ];
    };
} elseif ($type === 'remboursements') {
    $dateCol = 'COALESCE(rb.date_traitement, rb.created_at)';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT rb.id, rb.reservation_id, rb.requisition_id, rb.montant_paye, rb.montant_a_rembourser,
               rb.montant_rembourse, rb.mode, rb.reference, rb.resultat, rb.date_traitement, rb.created_at,
               rm.choix_client, u.nom_complet AS client, e.nom AS espace, admin.nom_complet AS traite_par
        FROM remboursements rb
        JOIN users u ON u.id = rb.client_id
        LEFT JOIN reservations r ON r.id = rb.reservation_id
        LEFT JOIN espaces e ON e.id = r.espace_id
        LEFT JOIN requisitions_ministerielles rm ON rm.id = rb.requisition_id
        LEFT JOIN users admin ON admin.id = rb.traite_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY $dateCol DESC
    ";
    $filename = 'remboursements_' . date('Y-m-d_His') . '.csv';
    $headers  = ['N° Bon','Date traitement','Client','Espace','Réservation','Réquisition','Nature','Montant versé (FCFA)','Montant à rembourser (FCFA)','Montant remboursé (FCFA)','Mode','Référence','Résultat','Traité par'];
    $mapRow = function($r) {
        return [
            'BR-' . date('Y', strtotime($r['date_traitement'] ?? $r['created_at'])) . '-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT),
            $r['date_traitement'] ? date('d/m/Y H:i', strtotime($r['date_traitement'])) : '',
            $r['client'], $r['espace'] ?? '',
            $r['reservation_id'] ? 'RESA-' . $r['reservation_id'] : '',
            $r['requisition_id'] ? '#' . $r['requisition_id'] : '',
            in_array($r['choix_client'], ['nouvelle_date', 'autre_espace'], true) ? 'Trop-perçu' : 'Remboursement',
            $r['montant_paye'], $r['montant_a_rembourser'], $r['montant_rembourse'] ?? '',
            $r['mode'] ?? '', $r['reference'] ?? '', $r['resultat'] ?? '', $r['traite_par'] ?? '',
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
    $headers  = ['N° Reçu','Espace','Gestionnaire','Début période','Durée (mois)','Montant (FCFA)','Mode','Référence','Date encaissement','Enregistré par'];
    $mapRow = function($r) {
        return [
            ref_recu((int)$r['id'], $r['created_at']),
            $r['espace'],
            trim(($r['gerant_prenom'] ?? '').' '.($r['gerant_nom'] ?? '')),
            date('d/m/Y', strtotime($r['periode_debut'])),
            $r['duree_mois'],
            $r['montant'], $r['mode'], $r['reference'] ?? '',
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
} else { // reservations
    $dateCol = 'r.created_at';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT r.id, r.created_at, u.nom_complet AS client, u.telephone, e.nom AS espace,
               r.date_resa, r.date_depart, r.heure_debut, r.heure_fin, r.statut, r.statut_paiement,
               t.libelle AS tarif, t.montant AS tarif_montant
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN tarifs t ON t.id = r.tarif_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY r.created_at DESC
    ";
    $filename = 'reservations_' . date('Y-m-d_His') . '.csv';
    $headers  = ['ID','Date création','Client','Téléphone','Espace','Date résa','Date départ','Heure début','Heure fin','Statut','Statut paiement','Tarif','Montant tarif (FCFA)',
                 'Montant initial (FCFA)','Réduction appliquée (FCFA)','Net dû (FCFA)','Encaissé (FCFA)','Remboursé (FCFA)','Solde (FCFA)','Réduction accordée non utilisée (FCFA)'];
    $mapRow = function($r) use ($situation, $fin2) {
        $s = $situation((int)$r['id']);
        $nonUtilisee = 0.0;
        foreach ($s['reductions_non_appliquees'] ?? [] as $red) {
            $nonUtilisee += (float)$red['montant_reduction'];
        }
        return [
            'RESA-' . $r['id'],
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['client'], $r['telephone'] ?? '', $r['espace'],
            date('d/m/Y', strtotime($r['date_resa'])),
            $r['date_depart'] ? date('d/m/Y', strtotime($r['date_depart'])) : '',
            $r['heure_debut'] ? substr($r['heure_debut'],0,5) : '',
            $r['heure_fin']   ? substr($r['heure_fin'],0,5)   : '',
            $r['statut'], $r['statut_paiement'], $r['tarif'] ?? '', $r['tarif_montant'] ?? '',
            $fin2($s['montant_initial'] ?? null), $fin2($s['montant_reduction'] ?? null), $fin2($s['net_du'] ?? null),
            $fin2($s['total_paye'] ?? null), $fin2($s['total_rembourse'] ?? null),
            in_array($r['statut'], ['validee'], true) ? $fin2($s['solde'] ?? null) : '',
            $nonUtilisee > 0 ? $fin2($nonUtilisee) : '',
        ];
    };
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

log_activity('export_' . $type, $type === 'paiements' ? 'reservations' : 'reservations', count($rows) . ' ligne(s) exportée(s) (' . $type . ')');

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
