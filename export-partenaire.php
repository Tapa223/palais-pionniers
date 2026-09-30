<?php
/*
 * Export CSV de l'Espace admin partenaire (consultation uniquement).
 *
 * Périmètre : les réservations de l'organisation du partenaire CONNECTÉ
 * (reservations.partenaire_id = sa fiche partenaire), déterminé côté serveur
 * à partir de la session. Aucun identifiant n'est lu dans l'URL : seul le
 * type d'export (?type=reservations|paiements) est accepté.
 *
 * Même format que les exports de l'administration (admin/export.php) :
 * UTF-8 avec BOM, séparateur « ; », montants entiers.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_client('admin/dashboard.php');

$pdo = db();
$partenaire = partenaire_utilisateur($pdo, (int)$_SESSION['user_id']);
if (!$partenaire) {
    http_response_code(403);
    exit('Export réservé aux comptes partenaires.');
}
$pid = (int)$partenaire['id'];

$type = (string)($_GET['type'] ?? 'reservations');
if (!in_array($type, ['reservations', 'paiements'], true)) {
    http_response_code(400);
    exit('Type d\'export inconnu.');
}

$fin2  = fn($v) => number_format((float)$v, 0, '', '');
$dateH = fn($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '';
$dateJ = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '';
// Texte saisi : neutralise les formules à l'ouverture dans un tableur
$texte = function ($v): string {
    $v = (string)$v;
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
};
$libStatut = [
    'en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée',
    'annulee' => 'Annulée', 'expiree' => 'Expirée', 'requisitionnee' => 'Réquisitionnée',
];
$libMode = [
    'especes' => 'Espèces', 'orange_money' => 'Orange Money', 'moov_money' => 'Moov Money',
    'virement' => 'Virement', 'cheque' => 'Chèque',
];

$lignes = [];
if ($type === 'reservations') {
    $st = $pdo->prepare("
        SELECT r.id, r.created_at, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin, r.quantite,
               r.statut, e.nom AS espace, u.nom_complet AS compte
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE r.partenaire_id = ?
        ORDER BY r.created_at DESC
    ");
    $st->execute([$pid]);
    $entetes = ['Réservation', 'Date de la demande', 'Compte', 'Espace', 'Date', 'Date de départ', 'Horaire',
                'Quantité', 'Statut de la réservation', 'Statut du paiement', 'Montant total (FCFA)',
                'Payé (FCFA)', 'Remboursé (FCFA)', 'Reste à payer (FCFA)', 'Bon disponible'];
    foreach ($st->fetchAll() as $r) {
        $s = situation_financiere_reservation($pdo, (int)$r['id']) ?? [];
        $lignes[] = [
            ref_resa((int)$r['id']), $dateH($r['created_at']), $texte($r['compte']), $texte($r['espace']),
            $dateJ($r['date_resa']), $dateJ($r['date_depart']),
            $r['heure_debut'] ? substr($r['heure_debut'], 0, 5) . ' - ' . substr((string)$r['heure_fin'], 0, 5) : '',
            (int)($r['quantite'] ?? 1),
            $libStatut[$r['statut']] ?? $r['statut'],
            $s ? libelle_etat_financier($s['etat'])[0] : '',
            $fin2($s['net_du'] ?? 0), $fin2(max(0, $s['paye_net'] ?? 0)), $fin2($s['total_rembourse'] ?? 0),
            $r['statut'] === 'validee' ? $fin2($s['solde'] ?? 0) : '',
            $r['statut'] === 'validee' ? 'Oui' : 'Non',
        ];
    }
} else {
    $st = $pdo->prepare("
        SELECT p.id, p.created_at, p.montant, p.mode, p.reference, p.reservation_id,
               e.nom AS espace, r.date_resa, u.nom_complet AS compte
        FROM paiements p
        JOIN reservations r ON r.id = p.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE r.partenaire_id = ?
        ORDER BY p.created_at DESC, p.id DESC
    ");
    $st->execute([$pid]);
    $entetes = ['Reçu', 'Date du paiement', 'Réservation', 'Compte', 'Espace', 'Date de la réservation',
                'Montant (FCFA)', 'Mode', 'Référence de la transaction'];
    foreach ($st->fetchAll() as $p) {
        $lignes[] = [
            ref_recu((int)$p['id'], $p['created_at']), $dateH($p['created_at']), ref_resa((int)$p['reservation_id']),
            $texte($p['compte']), $texte($p['espace']), $dateJ($p['date_resa']),
            $fin2($p['montant']), $libMode[$p['mode']] ?? $p['mode'], $texte($p['reference'] ?? ''),
        ];
    }
}

log_activity('export_partenaire_' . $type, 'reservations',
    count($lignes) . " ligne(s) exportée(s) par le partenaire #$pid ({$partenaire['nom']})");

$nomFichier = 'partenaire_' . preg_replace('/[^a-z0-9]+/i', '-', $partenaire['nom']) . '_' . $type . '_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomFichier . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $entetes, ';', '"', '\\');
foreach ($lignes as $l) {
    fputcsv($out, $l, ';', '"', '\\');
}
fclose($out);
exit;
