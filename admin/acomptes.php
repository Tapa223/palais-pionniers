<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_comptable','ministre']);

$pdo = db();

// Réservations avec un acompte versé (partiellement payé) OU déjà réglées
// intégralement mais qui ont eu un acompte à un moment (traçabilité complète)
$filtreStatut = $_GET['statut'] ?? 'en_cours';

$where = $filtreStatut === 'en_cours'
    ? "r.statut_paiement = 'partiellement_paye'"
    : "r.statut_paiement = 'paye' AND EXISTS (SELECT 1 FROM paiements p2 WHERE p2.reservation_id = r.id GROUP BY p2.reservation_id HAVING COUNT(*) > 1)";

$stmt = $pdo->query("
    SELECT r.id, r.date_resa, r.date_limite_solde, r.statut_paiement,
           e.nom AS espace_nom, u.nom_complet, u.telephone,
           t.montant AS tarif_montant, t.unite, r.quantite,
           (SELECT COALESCE(SUM(montant),0) FROM paiements WHERE reservation_id = r.id) AS total_verse
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    WHERE $where
    ORDER BY r.date_limite_solde ASC, r.date_resa ASC
");
$acomptes = $stmt->fetchAll();

$aujourdhui = date('Y-m-d');

$pageTitle = "Suivi des acomptes";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="mb-6">
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Suivi des acomptes</h1>
    <p class="text-sm text-slate-500 mt-0.5">Toutes les réservations réglées par acompte, avec leur solde restant et leur échéance</p>
  </div>

  <div class="flex gap-2 mb-6">
    <a href="?statut=en_cours" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtreStatut==='en_cours' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">En cours</a>
    <a href="?statut=soldes" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filtreStatut==='soldes' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>">Soldés</a>
  </div>

  <div class="space-y-3">
    <?php foreach ($acomptes as $a):
        $montantAttendu = (float)$a['tarif_montant'] * max(1, (int)$a['quantite']);
        $soldeRestant = max(0, $montantAttendu - (float)$a['total_verse']);
        $enRetard = $a['date_limite_solde'] && $a['date_limite_solde'] < $aujourdhui && $filtreStatut === 'en_cours';
    ?>
    <a href="paiements.php?id=<?= $a['id'] ?>" class="block bg-white rounded-2xl border <?= $enRetard ? 'border-accent' : 'border-slate-100' ?> shadow-sm p-5 hover:shadow-md transition">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="font-black text-primary text-sm"><?= e($a['espace_nom']) ?></p>
          <p class="text-xs text-slate-400 mt-0.5"><?= e($a['nom_complet']) ?> · <?= e($a['telephone']) ?> · réservé pour le <?= date('d/m/Y', strtotime($a['date_resa'])) ?></p>
        </div>
        <?php if ($filtreStatut === 'en_cours'): ?>
        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full <?= $enRetard ? 'bg-red-100 text-accent' : 'bg-sky-100 text-sky-700' ?>">
          <?= $enRetard ? 'Solde en retard' : 'Acompte en cours' ?>
        </span>
        <?php else: ?>
        <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700">Intégralement réglé</span>
        <?php endif; ?>
      </div>
      <div class="grid grid-cols-3 gap-3 mt-4 text-xs">
        <div>
          <p class="text-[9px] font-black uppercase text-slate-300 tracking-widest mb-1">Total attendu</p>
          <p class="font-black text-slate-700"><?= number_format($montantAttendu,0,',',' ') ?> FCFA</p>
        </div>
        <div>
          <p class="text-[9px] font-black uppercase text-slate-300 tracking-widest mb-1">Déjà versé</p>
          <p class="font-black text-sky-600"><?= number_format((float)$a['total_verse'],0,',',' ') ?> FCFA</p>
        </div>
        <div>
          <p class="text-[9px] font-black uppercase text-slate-300 tracking-widest mb-1"><?= $filtreStatut === 'en_cours' ? 'Solde restant' : 'Statut' ?></p>
          <p class="font-black <?= $filtreStatut === 'en_cours' ? 'text-accent' : 'text-emerald-600' ?>"><?= $filtreStatut === 'en_cours' ? number_format($soldeRestant,0,',',' ').' FCFA' : 'Réglé' ?></p>
        </div>
      </div>
      <?php if ($filtreStatut === 'en_cours' && $a['date_limite_solde']): ?>
      <p class="text-[10px] mt-3 font-bold <?= $enRetard ? 'text-accent' : 'text-slate-400' ?>"><i class="fas fa-clock mr-1"></i>Solde attendu avant le <?= date('d/m/Y', strtotime($a['date_limite_solde'])) ?></p>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (!$acomptes): ?>
    <p class="text-sm text-slate-400 italic text-center py-10">Aucune réservation dans cette catégorie.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
