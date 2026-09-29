<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_comptable','ministre']);
expirer_reservations_non_payees();

$pdo = db();

/*
 * Suivi des acomptes : toutes les réservations validées ayant reçu au moins
 * un paiement. Les montants (initial, réduction, net, payé, remboursé,
 * solde, échéance, retard) proviennent exclusivement de
 * situation_financiere_reservation() : aucun pourcentage n'est supposé.
 */
$filtres = [
    'en_cours' => 'En cours',
    'retard'   => 'Solde en retard',
    'soldes'   => 'Soldés',
];
$filtreStatut = $_GET['statut'] ?? 'en_cours';
if (!isset($filtres[$filtreStatut])) {
    $filtreStatut = 'en_cours';
}

// Recherche simple : client / téléphone / n° de réservation, et date de réservation
$recherche = trim((string)($_GET['q'] ?? ''));
$rechercheDate = (string)($_GET['date'] ?? '');
$dObj = DateTime::createFromFormat('!Y-m-d', $rechercheDate);
if (!$dObj || $dObj->format('Y-m-d') !== $rechercheDate) {
    $rechercheDate = '';
}
$rechercheNorm = mb_strtolower(preg_replace('/^#?(?:resa-?)/i', '', $recherche));

$stmt = $pdo->query("
    SELECT r.id, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin,
           e.nom AS espace_nom, u.nom_complet, u.telephone
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    WHERE r.statut = 'validee'
      AND EXISTS (SELECT 1 FROM paiements p WHERE p.reservation_id = r.id)
    ORDER BY r.date_resa ASC, r.heure_debut ASC
");

$acomptes = [];
$compteurs = ['en_cours' => 0, 'retard' => 0, 'soldes' => 0];

foreach ($stmt->fetchAll() as $a) {
    $s = situation_financiere_reservation($pdo, (int)$a['id']);
    if (!$s) {
        continue;
    }

    if ($s['solde'] > 0) {
        $categorie = 'en_cours';
        $compteurs['en_cours']++;
        if ($s['en_retard']) {
            $compteurs['retard']++;
        }
    } elseif ($s['nb_paiements'] > 1) {
        // Réglé en plusieurs versements : traçabilité des acomptes soldés
        $categorie = 'soldes';
        $compteurs['soldes']++;
    } else {
        continue; // payé en une fois : pas un acompte
    }

    $retenu = $filtreStatut === 'retard'
        ? ($categorie === 'en_cours' && $s['en_retard'])
        : $categorie === $filtreStatut;

    if ($retenu && $rechercheDate !== '' && $a['date_resa'] !== $rechercheDate) {
        $retenu = false;
    }
    if ($retenu && $rechercheNorm !== '') {
        $texte = mb_strtolower($a['nom_complet'] . ' ' . $a['telephone'] . ' ' . $a['espace_nom']);
        $retenu = ctype_digit($rechercheNorm)
            ? ((int)$rechercheNorm === (int)$a['id'] || str_contains($texte, $rechercheNorm))
            : str_contains($texte, $rechercheNorm);
    }

    if ($retenu) {
        $a['s'] = $s;
        $acomptes[] = $a;
    }
}

// En cours : échéance la plus proche d'abord
if ($filtreStatut !== 'soldes') {
    usort($acomptes, fn($x, $y) => ($x['s']['echeance_solde'] ?? PHP_INT_MAX) <=> ($y['s']['echeance_solde'] ?? PHP_INT_MAX));
}

$fcfa = fn($m) => number_format((float)$m, 0, ',', ' ');

$pageTitle = "Suivi des acomptes";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="mb-6">
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Suivi des acomptes</h1>
    <p class="text-sm text-slate-500 mt-0.5">Toutes les réservations réglées par acompte, avec leur solde restant et leur échéance</p>
  </div>

  <div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($filtres as $cle => $libelle): ?>
    <a href="?statut=<?= $cle ?><?= $recherche !== '' ? '&q=' . urlencode($recherche) : '' ?><?= $rechercheDate ? '&date=' . $rechercheDate : '' ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtreStatut===$cle ? ($cle==='retard' ? 'bg-accent text-white' : 'bg-primary text-white') : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">
      <?= $libelle ?> <span class="opacity-70">(<?= $compteurs[$cle] ?>)</span>
    </a>
    <?php endforeach; ?>
  </div>

  <form method="GET" class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <input type="hidden" name="statut" value="<?= e($filtreStatut) ?>">
    <div class="relative col-span-2">
      <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[10px]"></i>
      <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="Client, téléphone, n° de réservation…"
             class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 outline-none focus:border-primary text-primary">
    </div>
    <input type="date" name="date" value="<?= e($rechercheDate) ?>" title="Date de la réservation"
           class="w-full text-xs font-bold rounded-xl border border-slate-200 bg-white px-3 py-2 outline-none focus:border-primary text-primary">
    <div class="flex items-center gap-2">
      <button type="submit" class="flex-1 text-xs font-black bg-primary text-white px-3 py-2 rounded-xl hover:bg-slate-800 transition">Rechercher</button>
      <?php if ($recherche !== '' || $rechercheDate): ?>
      <a href="?statut=<?= $filtreStatut ?>" class="text-xs font-bold text-slate-400 px-2 py-2 hover:text-accent transition" title="Réinitialiser"><i class="fas fa-times"></i></a>
      <?php endif; ?>
    </div>
  </form>

  <div class="space-y-3">
    <?php foreach ($acomptes as $a):
        $s = $a['s'];
        $enRetard = $s['solde'] > 0 && $s['en_retard'];
        [$etatLib, $etatCls] = libelle_etat_financier($s['etat']);
    ?>
    <a href="paiements.php?resa=<?= (int)$a['id'] ?>" class="block bg-white rounded-2xl border <?= $enRetard ? 'border-accent' : 'border-slate-100' ?> shadow-sm p-5 hover:shadow-md transition">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="font-black text-primary text-sm"><?= e($a['espace_nom']) ?> <span class="text-slate-300 font-bold">#RESA-<?= (int)$a['id'] ?></span></p>
          <p class="text-xs text-slate-400 mt-0.5">
            <?= e($a['nom_complet']) ?> · <?= e($a['telephone']) ?> ·
            <?php if (!empty($a['heure_debut'])): ?>
              le <?= date('d/m/Y', strtotime($a['date_resa'])) ?> à <?= substr($a['heure_debut'], 0, 5) ?>
            <?php else: ?>
              séjour du <?= date('d/m/Y', strtotime($a['date_resa'])) ?><?= $a['date_depart'] ? ' au ' . date('d/m/Y', strtotime($a['date_depart'])) : '' ?>
            <?php endif; ?>
          </p>
        </div>
        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full border <?= $etatCls ?>"><?= e($etatLib) ?></span>
      </div>

      <div class="grid grid-cols-3 gap-3 mt-4 text-xs">
        <div class="min-w-0">
          <p class="text-[9px] font-black uppercase text-slate-300 tracking-widest mb-1">Total attendu</p>
          <p class="font-black text-slate-700"><?= $fcfa($s['net_du']) ?> FCFA</p>
          <?php if ($s['montant_reduction'] > 0): ?>
          <p class="text-[10px] font-bold text-orange-600">après réduction</p>
          <?php endif; ?>
        </div>
        <div class="min-w-0">
          <p class="text-[9px] font-black uppercase text-slate-300 tracking-widest mb-1">Déjà versé</p>
          <p class="font-black text-sky-600"><?= $fcfa($s['paye_net']) ?> FCFA</p>
        </div>
        <div class="min-w-0">
          <p class="text-[9px] font-black uppercase text-slate-300 tracking-widest mb-1"><?= $s['solde'] > 0 ? 'Solde restant' : 'Statut' ?></p>
          <p class="font-black <?= $s['solde'] > 0 ? 'text-accent' : 'text-emerald-600' ?>"><?= $s['solde'] > 0 ? $fcfa($s['solde']) . ' FCFA' : 'Réglé' ?></p>
        </div>
      </div>

      <?php if ($s['solde'] > 0 && $s['echeance_solde']): ?>
      <p class="text-[10px] mt-3 font-bold <?= $enRetard ? 'text-accent' : 'text-slate-400' ?>">
        <i class="fas fa-clock mr-1"></i>
        <?= $enRetard ? 'Solde en retard — échéance dépassée depuis le ' : 'Solde attendu avant le ' ?><?= date('d/m/Y à H:i', $s['echeance_solde']) ?>
        <?= $enRetard ? '· à régulariser avec le client (aucune annulation automatique)' : '' ?>
      </p>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (!$acomptes): ?>
    <p class="text-sm text-slate-400 italic text-center py-10">Aucune réservation dans cette catégorie.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
