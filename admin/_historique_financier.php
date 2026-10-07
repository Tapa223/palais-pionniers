<?php
if (!isset($histoEntrees) || !is_array($histoEntrees)) {
    return;
}
?>
<div class="historique-financier">
  <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
    <i class="fas fa-clock-rotate-left mr-1"></i> Historique financier du dossier
  </p>
  <?php if (!$histoEntrees): ?>
    <p class="text-xs text-slate-400">Aucune opération financière enregistrée.</p>
  <?php else: ?>
    <ol class="space-y-2">
      <?php foreach ($histoEntrees as $h): ?>
        <li class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
          <div class="flex items-start justify-between gap-2 flex-wrap">
            <p class="text-xs font-black text-primary"><?= e($h['action']) ?></p>
            <?php if ($h['montant'] !== null): ?>
              <p class="text-xs font-black text-slate-700 whitespace-nowrap"><?= number_format((float)$h['montant'], 0, ',', ' ') ?> FCFA</p>
            <?php endif; ?>
          </div>
          <p class="text-[10px] text-slate-500 mt-0.5">
            <?= date('d/m/Y H:i', strtotime($h['date'])) ?> · <?= e($h['auteur']) ?>
            · <span class="font-mono font-bold"><?= e($h['objet']) ?></span>
          </p>
          <p class="text-[10px] font-bold text-slate-600 mt-0.5">
            <?= e($h['resultat']) ?>
            <?php if ($h['transaction'] !== ''): ?>
              · Transaction : <span class="font-mono"><?= e($h['transaction']) ?></span>
            <?php endif; ?>
          </p>
          <?php if ($h['detail'] !== ''): ?>
            <p class="text-[10px] text-slate-500 mt-0.5" style="overflow-wrap:anywhere"><?= e($h['detail']) ?></p>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</div>
