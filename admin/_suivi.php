<?php
/*
 * Bloc « Suivi des réservations payées » du tableau de bord (inclus par admin/dashboard.php).
 * Réservations à confirmer (terminées, non cochées) et à venir (en cours et 7 prochains jours).
 * Le cochage passe par admin/suivi.php, qui contrôle les droits côté serveur.
 */
if (suivi_disponible($pdo)):
    require_once __DIR__ . '/_suivi_ligne.php';
    $suiviConfirmer = reservations_suivi($pdo, 'a_confirmer', 1000);
    $suiviVenir     = reservations_suivi($pdo, 'a_venir', 1000);
    $msgSuivi = [
        'effectuee'  => ['ok', 'Réservation confirmée comme effectuée.'],
        'decochee'   => ['ok', 'Confirmation « Effectuée » retirée.'],
        'impossible' => ['err', 'Cette réservation ne peut pas encore être cochée : elle doit être validée, payée (au moins un acompte) et avoir commencé.'],
        'droits'     => ['err', 'Votre rôle permet de consulter le suivi, pas de cocher « Effectuée ».'],
        'requete'    => ['err', 'Requête invalide : rechargez la page et recommencez.'],
    ][$_GET['suivi'] ?? ''] ?? null;
?>
<section id="suivi" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8" aria-labelledby="titreSuivi" style="scroll-margin-top:5rem">
  <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-4 border-b border-slate-100">
    <div>
      <h2 id="titreSuivi" class="font-black text-primary uppercase italic text-sm tracking-tight flex items-center gap-2">
        <i class="fas fa-clipboard-check text-accent"></i> Suivi des réservations payées
      </h2>
      <p class="text-xs text-slate-500 mt-0.5">À cocher « Effectuée » une fois l'activité terminée</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full <?= $suiviConfirmer ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' ?>"><?= count($suiviConfirmer) ?> à confirmer</span>
      <span class="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full bg-primary/5 text-primary"><?= count($suiviVenir) ?> à venir (7 jours)</span>
      <a href="suivi.php" class="text-[11px] font-black uppercase text-primary hover:text-accent transition">Voir tout →</a>
    </div>
  </div>

  <?php if ($msgSuivi): ?>
  <p class="px-5 py-3 text-sm font-bold border-b border-slate-100 <?= $msgSuivi[0] === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-accent' ?>">
    <i class="fas <?= $msgSuivi[0] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i><?= e($msgSuivi[1]) ?>
  </p>
  <?php endif; ?>

  <?php if (!$suiviConfirmer && !$suiviVenir): ?>
  <p class="px-5 py-8 text-center text-sm text-slate-400">Aucune réservation payée à confirmer ni prévue dans les 7 prochains jours.</p>
  <?php else: ?>
    <?php if ($suiviConfirmer): ?>
    <p class="px-5 pt-4 text-[10px] font-black uppercase tracking-widest text-amber-700">À confirmer</p>
    <ul class="divide-y divide-slate-50"><?php foreach (array_slice($suiviConfirmer, 0, 5) as $r) { suivi_ligne($r, 'dashboard'); } ?></ul>
    <?php if (count($suiviConfirmer) > 5): ?><p class="px-5 pb-3 text-xs"><a href="suivi.php?vue=a_confirmer" class="font-black text-primary hover:text-accent">+ <?= count($suiviConfirmer) - 5 ?> autre(s) à confirmer →</a></p><?php endif; ?>
    <?php endif; ?>
    <?php if ($suiviVenir): ?>
    <p class="px-5 pt-4 text-[10px] font-black uppercase tracking-widest text-primary">En cours et à venir</p>
    <ul class="divide-y divide-slate-50"><?php foreach (array_slice($suiviVenir, 0, 6) as $r) { suivi_ligne($r, 'dashboard'); } ?></ul>
    <?php if (count($suiviVenir) > 6): ?><p class="px-5 pb-3 text-xs"><a href="suivi.php?vue=a_venir" class="font-black text-primary hover:text-accent">+ <?= count($suiviVenir) - 6 ?> autre(s) à venir →</a></p><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>
