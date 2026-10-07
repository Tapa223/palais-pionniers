<?php
$roleJour     = $_SESSION['role'] ?? '';
$voitContact  = in_array($roleJour, ['superadmin', 'ministre', 'admin_espaces', 'admin_comptable'], true);
$voitFinance  = in_array($roleJour, ['superadmin', 'ministre', 'admin_comptable'], true);
$voitLien     = in_array($roleJour, ['superadmin', 'ministre', 'admin_espaces', 'admin_comptable'], true);
$aujourdhui   = date('Y-m-d');
$maintenant   = date('H:i:s');

$jourRes = [];
try {
    $avecPartenaire = function_exists('partenaires_disponibles') && partenaires_disponibles($pdo);
    $stJour = $pdo->prepare("
        SELECT r.id, r.statut, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin, r.quantite,
               r.petit_dejeuner, r.vip, r.motif, r.canal,
               e.nom AS espace_nom, e.mode_reservation,
               u.nom_complet, u.telephone,
               t.libelle AS tarif_libelle
               " . ($avecPartenaire ? ", p.nom AS partenaire_nom" : ", NULL AS partenaire_nom") . "
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u   ON u.id = r.user_id
        LEFT JOIN tarifs t ON t.id = r.tarif_id
        " . ($avecPartenaire ? "LEFT JOIN partenaires p ON p.id = r.partenaire_id" : "") . "
        WHERE r.statut IN ('validee', 'en_attente')
          AND (
                (e.mode_reservation <> 'sejour' AND r.date_resa = :j1)
             OR (e.mode_reservation = 'sejour' AND r.date_resa <= :j2 AND COALESCE(r.date_depart, r.date_resa) >= :j3)
          )
        ORDER BY (r.statut = 'en_attente'), (e.mode_reservation = 'sejour'), r.heure_debut, e.nom
    ");
    $stJour->execute([':j1' => $aujourdhui, ':j2' => $aujourdhui, ':j3' => $aujourdhui]);
    $jourRes = $stJour->fetchAll();
} catch (Exception $e) {
    $jourRes = [];
}

$moisFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$joursFr = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
$dateLongue = ucfirst($joursFr[(int)date('w')] . ' ' . (int)date('j') . ' ' . $moisFr[(int)date('n') - 1] . ' ' . date('Y'));
$nbValidees = count(array_filter($jourRes, fn($r) => $r['statut'] === 'validee'));
?>
<section id="aujourdhui" data-repliable="aujourdhui" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8" aria-labelledby="titreAujourdhui">
  <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-4 border-b border-slate-100">
    <div>
      <h2 id="titreAujourdhui" class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-calendar-day text-accent"></i> Aujourd'hui au Palais
      </h2>
      <p class="text-xs text-slate-500 mt-0.5"><?= e($dateLongue) ?></p>
    </div>
    <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full bg-primary/5 text-primary">
      <?= $nbValidees ?> réservation(s) confirmée(s)<?= count($jourRes) > $nbValidees ? ' · ' . (count($jourRes) - $nbValidees) . ' à valider' : '' ?>
    </span>
  </div>

  <?php if (!$jourRes): ?>
  <p class="px-5 py-8 text-center text-sm text-slate-400"><i class="fas fa-mug-hot mr-2"></i>Aucune réservation prévue aujourd'hui.</p>
  <?php else: ?>
  <ul class="divide-y divide-slate-50">
    <?php foreach ($jourRes as $r):
      $sejour = $r['mode_reservation'] === 'sejour';
      if ($sejour) {
          if ($r['date_resa'] === $aujourdhui)        { $quand = 'Arrivée'; }
          elseif ($r['date_depart'] === $aujourdhui)  { $quand = 'Départ'; }
          else                                        { $quand = 'En séjour'; }
          $horaire = 'jusqu\'au ' . date('d/m', strtotime($r['date_depart'] ?: $r['date_resa']));
      } else {
          $quand   = substr((string)$r['heure_debut'], 0, 5);
          $horaire = '→ ' . substr((string)$r['heure_fin'], 0, 5);
      }
      $enCours = !$sejour && $r['statut'] === 'validee' && $r['heure_debut'] <= $maintenant && $maintenant < $r['heure_fin'];
      $termine = !$sejour && $r['heure_fin'] && $r['heure_fin'] <= $maintenant;
      $etat = null;
      if ($voitFinance && $r['statut'] === 'validee') {
          $s = situation_financiere_reservation($pdo, (int)$r['id']);
          if ($s) { $etat = libelle_etat_financier($s['etat']); }
      }
    ?>
    <li class="flex items-start gap-4 px-5 py-3 <?= $termine ? 'opacity-60' : '' ?>">
      <div class="w-20 flex-shrink-0 text-center">
        <div class="font-black text-primary text-sm"><?= e($quand) ?></div>
        <div class="text-[11px] font-semibold text-slate-400"><?= e($horaire) ?></div>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-black text-slate-800 text-sm"><?= e($r['motif'] ?: ($r['tarif_libelle'] ?: 'Réservation')) ?></span>
          <?php if ($enCours): ?><span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">En cours</span><?php endif; ?>
          <?php if ($r['statut'] === 'en_attente'): ?><span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">À valider</span><?php endif; ?>
          <?php if ($etat): ?><span class="text-[10px] font-black px-2 py-0.5 rounded-full border <?= $etat[1] ?>"><?= e($etat[0]) ?></span><?php endif; ?>
        </div>
        <p class="text-xs text-slate-600 mt-0.5">
          <i class="fas fa-building text-slate-400 mr-1"></i><?= e($r['espace_nom']) ?>
          <?php if ($sejour): ?> · <?= (int)($r['quantite'] ?: 1) ?> chambre(s)<?= !empty($r['petit_dejeuner']) ? ' · petit-déjeuner' : '' ?><?php endif; ?>
          <?php if (!empty($r['vip'])): ?> · accueil VIP<?php endif; ?>
        </p>
        <p class="text-xs text-slate-500 mt-0.5">
          <i class="fas fa-user text-slate-400 mr-1"></i><?= e($r['nom_complet']) ?>
          <?php if (!empty($r['partenaire_nom'])): ?><span class="text-indigo-700 font-bold"> · Partenaire <?= e($r['partenaire_nom']) ?></span><?php endif; ?>
          <?php if ($voitContact && $r['telephone']): ?> · <?= e($r['telephone']) ?><?php endif; ?>
          <?php if ($r['canal'] === 'guichet'): ?> · guichet<?php endif; ?>
        </p>
      </div>
      <?php if ($voitLien): ?>
      <a href="reservations.php?id=<?= (int)$r['id'] ?>" class="text-xs font-black text-primary hover:text-accent whitespace-nowrap"><?= e(ref_resa((int)$r['id'])) ?> →</a>
      <?php else: ?>
      <span class="text-[11px] font-bold text-slate-400 whitespace-nowrap"><?= e(ref_resa((int)$r['id'])) ?></span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
