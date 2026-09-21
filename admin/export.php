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
    'jeunes_engages' => ['ministre','admin_activites','superadmin'],
];

if (!isset($typesAutorises[$type]) || !in_array($role, $typesAutorises[$type], true)) {
    http_response_code(403);
    exit('Export non autorisé pour ce rôle.');
}

$where  = [];
$params = [];

if ($type === 'paiements') {
    $dateCol = 'p.created_at';
    if ($debut) { $where[] = "$dateCol >= ?"; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = "$dateCol <= ?"; $params[] = $fin   . ' 23:59:59'; }
    $sql = "
        SELECT p.id, p.created_at, u.nom_complet AS client, e.nom AS espace,
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
    $headers  = ['N° Reçu','Date encaissement','Client','Espace','Date résa','Date départ','Montant encaissé (FCFA)','Montant tarif (FCFA)','Motif réduction','Mode','Référence','Note','Enregistré par'];
    $mapRow = function($r) {
        return [
            ref_recu((int)$r['id']),
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['client'], $r['espace'],
            date('d/m/Y', strtotime($r['date_resa'])),
            $r['date_depart'] ? date('d/m/Y', strtotime($r['date_depart'])) : '',
            $r['montant'], $r['montant_reference'] ?? '', $r['motif_reduction'] ?? '',
            $r['mode'], $r['reference'] ?? '', $r['note'] ?? '', $r['enregistre_par'],
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
            ref_recu((int)$r['id']),
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
    $headers  = ['ID','Date création','Client','Téléphone','Espace','Date résa','Date départ','Heure début','Heure fin','Statut','Statut paiement','Tarif','Montant tarif (FCFA)'];
    $mapRow = function($r) {
        return [
            'RESA-' . $r['id'],
            date('d/m/Y H:i', strtotime($r['created_at'])),
            $r['client'], $r['telephone'] ?? '', $r['espace'],
            date('d/m/Y', strtotime($r['date_resa'])),
            $r['date_depart'] ? date('d/m/Y', strtotime($r['date_depart'])) : '',
            $r['heure_debut'] ? substr($r['heure_debut'],0,5) : '',
            $r['heure_fin']   ? substr($r['heure_fin'],0,5)   : '',
            $r['statut'], $r['statut_paiement'], $r['tarif'] ?? '', $r['tarif_montant'] ?? '',
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
fputcsv($out, $headers, ';');
foreach ($rows as $r) {
    fputcsv($out, $mapRow($r), ';');
}
fclose($out);
exit;
