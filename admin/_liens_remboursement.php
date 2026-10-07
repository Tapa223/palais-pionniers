<?php
if (empty($remboursement) || !isset($pdo)) {
    return;
}
$nbObsRb = nb_observations($pdo, 'remboursement', (int)$remboursement['id']);
?>
<p class="text-xs mt-2 flex flex-wrap gap-3 font-black">
  <?php if (($remboursement['resultat'] ?? '') === 'effectue'): ?>
    <a href="../generer_bon.php?id=<?= (int)$remboursement['id'] ?>&type=remboursement" target="_blank"
       class="text-slate-500 hover:text-accent transition">
      <i class="fas fa-file-invoice mr-1"></i>Bon de remboursement
    </a>
  <?php endif; ?>
  <?php if (in_array('remboursement', observations_types_disponibles($pdo), true)): ?>
    <a href="observations.php?cible_type=remboursement&cible_id=<?= (int)$remboursement['id'] ?>"
       class="text-slate-500 hover:text-accent transition">
      <i class="fas fa-eye mr-1"></i>Observer ce remboursement<?= $nbObsRb ? ' (' . $nbObsRb . ')' : '' ?>
    </a>
  <?php endif; ?>
</p>
