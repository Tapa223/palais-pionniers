<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['superadmin','ministre','admin_comptable','admin_espaces']);

$pdo  = db();
$role = $_SESSION['role'] ?? '';

$typesAutorises = [
    'reductions' => ['superadmin','ministre','admin_comptable'],
    'guichet'    => ['superadmin','ministre','admin_comptable','admin_espaces'],
    'encaisse'   => ['superadmin','ministre','admin_comptable'],
    'espaces'    => ['superadmin','ministre','admin_espaces'],
];
$type = $_GET['type'] ?? 'encaisse';
if (!isset($typesAutorises[$type]) || !in_array($role, $typesAutorises[$type], true)) {
    header('Location: dashboard.php'); exit;
}

$debut = $_GET['debut'] ?? '';
$fin   = $_GET['fin']   ?? '';

$titres = [
    'reductions' => 'Rapport — Réductions accordées',
    'guichet'    => 'Rapport — Réservations saisies au guichet',
    'encaisse'   => 'Rapport — Tous les paiements encaissés',
    'espaces'    => 'Rapport — État des espaces',
];

$lignes = [];
$colonnes = [];
$totalGeneral = 0;
$libelleTotal = 'Total';

if ($type === 'reductions') {
    /*
     * Réductions accordées (table reductions_accordees, tous statuts) et
     * anciennes réductions saisies sur un paiement (paiements.motif_reduction).
     * Seules les réductions réellement appliquées entrent dans le total :
     * une réduction « non appliquée » ou « annulée » n'est pas un manque à gagner.
     */
    $colonnes = ['Réf.', 'Date', 'Espace', 'Client', 'Montant initial', 'Réduction', 'Statut', 'Motif', 'Saisi par'];
    $libellesStatutRed = ['appliquee' => 'Appliquée', 'non_appliquee' => 'Non utilisée (plein tarif payé)', 'annulee' => 'Annulée'];
    $params = [];
    $whereNew = [];
    if ($debut) { $whereNew[] = 'ra.created_at >= ?'; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $whereNew[] = 'ra.created_at <= ?'; $params[] = $fin . ' 23:59:59'; }
    $stmt = $pdo->prepare("
        SELECT ra.*, e.nom AS espace_nom, u.nom_complet, admin.nom_complet AS admin_nom
        FROM reductions_accordees ra
        JOIN reservations r ON r.id = ra.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        JOIN users admin ON admin.id = ra.saisi_par
        " . ($whereNew ? 'WHERE ' . implode(' AND ', $whereNew) : '') . "
        ORDER BY ra.created_at DESC
    ");
    $stmt->execute($params);
    $lignesTriees = [];
    $totalPriseEnCharge = 0;
    foreach ($stmt->fetchAll() as $r) {
        $sRed = situation_financiere_reservation($pdo, (int)$r['reservation_id']);
        // Maintien du tarif suite à réquisition : listé à part, jamais compté comme réduction commerciale
        $estPriseEnCharge = ($r['origine'] ?? '') === 'requisition';
        $lignesTriees[] = [$r['created_at'], [
            'RESA-' . (int)$r['reservation_id'], date('d/m/Y', strtotime($r['created_at'])), $r['espace_nom'], $r['nom_complet'],
            number_format((float)($sRed['montant_initial'] ?? 0), 0, ',', ' '),
            number_format((float)$r['montant_reduction'], 0, ',', ' '),
            $estPriseEnCharge ? 'Prise en charge suite à réquisition' : ($libellesStatutRed[$r['statut']] ?? $r['statut']),
            trim(($r['motif'] ?? '') . ($r['motif_statut'] ? ' — ' . $r['motif_statut'] : '')), $r['admin_nom']]];
        if ($r['statut'] === 'appliquee') {
            if ($estPriseEnCharge) {
                $totalPriseEnCharge += (float)$r['montant_reduction'];
            } else {
                $totalGeneral += (float)$r['montant_reduction'];
            }
        }
    }

    // Anciennes réductions : une ligne par réservation
    $params = [];
    $whereOld = ["p.motif_reduction IS NOT NULL", "p.motif_reduction != ''"];
    if ($debut) { $whereOld[] = 'p.created_at >= ?'; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $whereOld[] = 'p.created_at <= ?'; $params[] = $fin . ' 23:59:59'; }
    $stmt = $pdo->prepare("
        SELECT p.reservation_id, MAX(p.created_at) AS created_at, MAX(p.motif_reduction) AS motif_reduction,
               e.nom AS espace_nom, u.nom_complet, MAX(admin.nom_complet) AS admin_nom
        FROM paiements p
        JOIN reservations r ON r.id = p.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        JOIN users admin ON admin.id = p.enregistre_par
        WHERE " . implode(' AND ', $whereOld) . "
        GROUP BY p.reservation_id, e.nom, u.nom_complet
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $sRed = situation_financiere_reservation($pdo, (int)$r['reservation_id']);
        $montantRed = (float)($sRed['reduction_historique'] ?? 0);
        $lignesTriees[] = [$r['created_at'], [
            'RESA-' . (int)$r['reservation_id'], date('d/m/Y', strtotime($r['created_at'])), $r['espace_nom'], $r['nom_complet'],
            number_format((float)($sRed['montant_initial'] ?? 0), 0, ',', ' '),
            number_format($montantRed, 0, ',', ' '),
            'Ancien système (sur paiement)',
            $r['motif_reduction'], $r['admin_nom']]];
        $totalGeneral += $montantRed;
    }

    $libelleTotal = 'Total des réductions commerciales appliquées'
        . ($totalPriseEnCharge > 0 ? ' (prises en charge suite à réquisition, non comptées : ' . number_format($totalPriseEnCharge, 0, ',', ' ') . ' FCFA)' : '');
    usort($lignesTriees, fn($a, $b) => strcmp($b[0], $a[0]));
    $lignes = array_column($lignesTriees, 1);
} elseif ($type === 'guichet') {
    $colonnes = ['Date', 'Espace', 'Client', 'Statut paiement', 'Saisi par'];
    $where = ["r.canal = 'guichet'"];
    $params = [];
    if ($debut) { $where[] = 'r.created_at >= ?'; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = 'r.created_at <= ?'; $params[] = $fin . ' 23:59:59'; }
    $stmt = $pdo->prepare("
        SELECT r.*, e.nom AS espace_nom, u.nom_complet
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.created_at DESC
    ");
    $stmt->execute($params);
    $labelsPaiement = ['non_paye'=>'Non payé','attente_paiement'=>'En attente','partiellement_paye'=>'Acompte versé','paye'=>'Payé'];
    foreach ($stmt->fetchAll() as $r) {
        $lignes[] = [date('d/m/Y', strtotime($r['created_at'])), $r['espace_nom'], $r['nom_complet'],
            $labelsPaiement[$r['statut_paiement']] ?? $r['statut_paiement'], $r['nom_complet']];
    }
} elseif ($type === 'encaisse') {
    $colonnes = ['Réf.', 'Date', 'Espace', 'Client', 'Montant', 'Mode', 'Encaissé par'];
    $where = [];
    $params = [];
    if ($debut) { $where[] = 'p.created_at >= ?'; $params[] = $debut . ' 00:00:00'; }
    if ($fin)   { $where[] = 'p.created_at <= ?'; $params[] = $fin . ' 23:59:59'; }
    $modeLabels = ['especes'=>'Espèces','orange_money'=>'Orange Money','moov_money'=>'Moov Money','virement'=>'Virement','cheque'=>'Chèque'];
    $stmt = $pdo->prepare("
        SELECT p.*, e.nom AS espace_nom, u.nom_complet, admin.nom_complet AS admin_nom
        FROM paiements p
        JOIN reservations r ON r.id = p.reservation_id
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        JOIN users admin ON admin.id = p.enregistre_par
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $lignes[] = [ref_recu((int)$r['id'], $r['created_at']), date('d/m/Y', strtotime($r['created_at'])), $r['espace_nom'], $r['nom_complet'],
            number_format((float)$r['montant'], 0, ',', ' '), $modeLabels[$r['mode']] ?? $r['mode'], $r['admin_nom']];
        $totalGeneral += (float)$r['montant'];
    }
} elseif ($type === 'espaces') {
    $colonnes = ['Espace', 'Catégorie', 'Mode', 'Statut', 'Bail'];
    $stmt = $pdo->query("
        SELECT e.*, c.nom AS categorie_nom
        FROM espaces e JOIN categories c ON c.id = e.categorie_id
        ORDER BY c.nom ASC, e.nom ASC
    ");
    foreach ($stmt->fetchAll() as $r) {
        $lignes[] = [$r['nom'], $r['categorie_nom'], $r['mode_reservation'] === 'sejour' ? 'Séjour' : 'Créneau',
            $r['disponible'] ? 'Disponible' : 'Masqué',
            !empty($r['gerant_externe']) ? 'En bail' : '—'];
    }
}

// --- Génération PDF réelle (téléchargement direct, pas une impression de page) ---
if (($_GET['format'] ?? '') === 'pdf') {
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

    $pdf = new FPDF('L', 'mm', 'A4'); // Paysage, plus de place pour les colonnes
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    // En-tête
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1252', 'Palais des Pionniers'), 0, 1);
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 6, iconv('UTF-8', 'windows-1252', $titres[$type]), 0, 1);
    $pdf->SetFont('Arial', '', 8);
    $sousTitre = 'Genere le ' . date('d/m/Y a H:i');
    if ($debut || $fin) $sousTitre .= ' - Periode : ' . ($debut ?: '...') . ' -> ' . ($fin ?: '...');
    $sousTitre .= ' - ' . count($lignes) . ' ligne(s)';
    $pdf->SetTextColor(120,120,120);
    $pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252', $sousTitre), 0, 1);
    $pdf->SetTextColor(0,0,0);
    $pdf->Ln(4);

    // Largeurs de colonnes réparties sur la largeur disponible (A4 paysage ≈ 277mm utiles)
    $nbCol = count($colonnes);
    $largeurPage = 277;
    $largeurCol = $largeurPage / max(1, $nbCol);

    // En-têtes de colonnes
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(15, 23, 42);
    $pdf->SetTextColor(255,255,255);
    foreach ($colonnes as $c) {
        $pdf->Cell($largeurCol, 8, iconv('UTF-8', 'windows-1252', $c), 1, 0, 'L', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial', '', 8);

    // Lignes
    $fillRow = false;
    foreach ($lignes as $ligne) {
        $pdf->SetFillColor($fillRow ? 248 : 255, $fillRow ? 250 : 255, $fillRow ? 252 : 255);
        foreach ($ligne as $val) {
            $pdf->Cell($largeurCol, 7, iconv('UTF-8', 'windows-1252', (string)$val), 1, 0, 'L', true);
        }
        $pdf->Ln();
        $fillRow = !$fillRow;
    }

    // Total
    if ($totalGeneral > 0) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(241, 245, 249);
        $pdf->Cell($largeurCol * ($nbCol - 1), 8, iconv('UTF-8', 'windows-1252', mb_strtoupper($libelleTotal)), 1, 0, 'R', true);
        $pdf->Cell($largeurCol, 8, iconv('UTF-8', 'windows-1252', number_format($totalGeneral, 0, ',', ' ') . ' FCFA'), 1, 0, 'L', true);
        $pdf->Ln();
    }

    $nomFichier = 'rapport_' . $type . '_' . date('Y-m-d_His') . '.pdf';
    $pdf->Output('D', $nomFichier);
    exit;
}

$pageTitle = $titres[$type];
require __DIR__ . '/_admin_header.php';
?>

<style>
@media print {
    @page { size: A4 landscape; margin: 10mm; }
    .no-print { display: none !important; }
    body { background: white !important; }
}
</style>

<div class="px-4 sm:px-6 py-8 max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3 no-print">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight"><?= e($titres[$type]) ?></h1>
      <p class="text-sm text-slate-500 mt-0.5"><?= count($lignes) ?> ligne(s)</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="?type=<?= e($type) ?>&debut=<?= date('Y-m-d', strtotime('monday this week')) ?>&fin=<?= date('Y-m-d', strtotime('sunday this week')) ?>"
         class="text-xs font-black bg-primary/5 hover:bg-primary/10 text-primary px-4 py-2 rounded-xl transition flex items-center gap-2">
        <i class="fas fa-calendar-week"></i> Cette semaine
      </a>
      <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="date" name="debut" value="<?= e($debut) ?>" class="text-xs font-bold rounded-xl border border-slate-200 px-3 py-2 outline-none text-primary">
        <span class="text-xs text-slate-400">→</span>
        <input type="date" name="fin" value="<?= e($fin) ?>" class="text-xs font-bold rounded-xl border border-slate-200 px-3 py-2 outline-none text-primary">
        <button type="submit" class="text-xs font-black bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl transition">Filtrer</button>
      </form>
      <?php if (in_array($type, ['reductions','guichet','encaisse'], true)): ?>
      <a href="export.php?type=<?= $type === 'reductions' ? 'paiements' : ($type === 'guichet' ? 'reservations' : 'paiements') ?>&debut=<?= e($debut) ?>&fin=<?= e($fin) ?>" target="_blank"
         class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
        <i class="fas fa-file-excel"></i> Excel (CSV)
      </a>
      <?php endif; ?>
      <a href="rapport.php?type=<?= e($type) ?>&debut=<?= e($debut) ?>&fin=<?= e($fin) ?>&format=pdf"
         class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-accent-dark transition">
        <i class="fas fa-file-pdf"></i> Télécharger en PDF
      </a>
    </div>
  </div>

  <div class="hidden print:block mb-6">
    <h1 class="text-xl font-black uppercase"><?= e($titres[$type]) ?> — Palais des Pionniers</h1>
    <p class="text-xs text-slate-500"><?= $debut || $fin ? "Période : " . ($debut ?: '…') . " → " . ($fin ?: '…') : 'Toutes dates confondues' ?> — édité le <?= date('d/m/Y à H:i') ?></p>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <?php if (!$lignes): ?>
    <p class="text-sm text-slate-400 italic p-8 text-center">Aucune donnée pour cette période.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-100">
        <tr>
          <?php foreach ($colonnes as $c): ?>
          <th class="text-left px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-400"><?= e($c) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php foreach ($lignes as $ligne): ?>
        <tr>
          <?php foreach ($ligne as $val): ?>
          <td class="px-4 py-3 font-semibold text-slate-700"><?= e((string)$val) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($totalGeneral > 0): ?>
      <tfoot>
        <tr class="bg-slate-50 border-t-2 border-slate-200">
          <td colspan="<?= count($colonnes) - 1 ?>" class="px-4 py-3 font-black text-primary text-right uppercase text-xs"><?= e($libelleTotal) ?></td>
          <td class="px-4 py-3 font-black text-accent"><?= number_format($totalGeneral, 0, ',', ' ') ?> FCFA</td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
