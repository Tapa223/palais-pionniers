<?php
if (!function_exists('suivi_ligne')) {
    function suivi_ligne(array $r, string $retour): void
    {
        $role        = $_SESSION['role'] ?? '';
        $peutCocher  = peut_cocher_effectuee();
        $voitContact = in_array($role, ['superadmin', 'ministre', 'admin_espaces', 'admin_comptable'], true);
        $voitFinance = in_array($role, ['superadmin', 'ministre', 'admin_comptable'], true);
        $voitLien    = in_array($role, ['superadmin', 'ministre', 'admin_espaces', 'admin_comptable'], true);
        $sejour      = $r['mode_reservation'] === 'sejour';
        $commencee   = strtotime($r['debut_dt']) <= time();
        $terminee    = strtotime($r['fin_dt']) <= time();
        $faite       = !empty($r['effectuee_le']);

        if ($sejour) {
            $quand   = date('d/m', strtotime($r['date_resa'])) . ' → ' . date('d/m', strtotime($r['date_depart'] ?: $r['date_resa']));
            $detail  = (int)($r['quantite'] ?: 1) . ' chambre(s)';
        } else {
            $quand   = date('d/m', strtotime($r['date_resa']));
            $detail  = substr((string)$r['heure_debut'], 0, 5) . ' → ' . substr((string)$r['heure_fin'], 0, 5);
        }
        $jour = date('Y-m-d', strtotime($r['date_resa']));
        $etiquetteJour = $jour === date('Y-m-d') ? "Aujourd'hui" : ($jour === date('Y-m-d', strtotime('+1 day')) ? 'Demain' : null);
        ?>
        <li class="flex items-start gap-4 px-5 py-3">
          <div class="w-20 flex-shrink-0 text-center">
            <div class="font-black text-primary text-sm"><?= e($quand) ?></div>
            <div class="text-[11px] font-semibold text-slate-400"><?= e($detail) ?></div>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-black text-slate-800 text-sm"><?= e($r['motif'] ?: 'Réservation') ?></span>
              <?php if ($etiquetteJour && !$faite): ?><span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-primary/10 text-primary"><?= e($etiquetteJour) ?></span><?php endif; ?>
              <?php if ($faite): ?>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700"><i class="fas fa-check mr-1"></i>Effectuée</span>
              <?php elseif ($terminee): ?>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">À confirmer</span>
              <?php elseif ($commencee): ?>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">En cours</span>
              <?php endif; ?>
              <?php if ($voitFinance): ?>
                <span class="text-[10px] font-black px-2 py-0.5 rounded-full border <?= $r['statut_paiement'] === 'paye' ? 'border-emerald-200 text-emerald-700' : 'border-sky-200 text-sky-700' ?>"><?= $r['statut_paiement'] === 'paye' ? 'Payée' : 'Acompte versé' ?></span>
              <?php endif; ?>
            </div>
            <p class="text-xs text-slate-600 mt-0.5"><i class="fas fa-building text-slate-400 mr-1"></i><?= e($r['espace_nom']) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">
              <i class="fas fa-user text-slate-400 mr-1"></i><?= e($r['nom_complet']) ?>
              <?php if (!empty($r['partenaire_nom'])): ?><span class="text-indigo-700 font-bold"> · Partenaire <?= e($r['partenaire_nom']) ?></span><?php endif; ?>
              <?php if ($voitContact && !empty($r['telephone'])): ?> · <?= e($r['telephone']) ?><?php endif; ?>
            </p>
            <?php if ($faite): ?>
            <p class="text-[11px] text-emerald-700 mt-0.5">Confirmée le <?= date('d/m/Y à H:i', strtotime($r['effectuee_le'])) ?><?= !empty($r['effectuee_par_nom']) ? ' par ' . e($r['effectuee_par_nom']) : '' ?></p>
            <?php endif; ?>
          </div>
          <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
            <?php if ($voitLien): ?>
            <a href="reservations.php?id=<?= (int)$r['id'] ?>" class="text-xs font-black text-primary hover:text-accent whitespace-nowrap"><?= e(ref_resa((int)$r['id'])) ?> →</a>
            <?php else: ?>
            <span class="text-[11px] font-bold text-slate-400 whitespace-nowrap"><?= e(ref_resa((int)$r['id'])) ?></span>
            <?php endif; ?>
            <?php if ($peutCocher && ($faite || $commencee)): ?>
            <form method="POST" action="suivi.php">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="retour" value="<?= e($retour) ?>">
              <?php if ($faite): ?>
              <button type="submit" name="action" value="annuler_effectuee" onclick="return confirm('Retirer la confirmation « Effectuée » ?')"
                      class="text-[10px] font-black uppercase text-slate-400 hover:text-accent transition whitespace-nowrap"><i class="fas fa-rotate-left mr-1"></i>Décocher</button>
              <?php else: ?>
              <button type="submit" name="action" value="effectuer"
                      class="flex items-center gap-1.5 bg-emerald-500 text-white text-[10px] font-black uppercase px-3 py-1.5 rounded-xl hover:bg-emerald-600 transition whitespace-nowrap"><i class="far fa-square-check"></i> Effectuée</button>
              <?php endif; ?>
            </form>
            <?php elseif (!$faite && !$commencee): ?>
            <span class="text-[10px] font-semibold text-slate-400 whitespace-nowrap">À venir</span>
            <?php endif; ?>
          </div>
        </li>
        <?php
    }
}
