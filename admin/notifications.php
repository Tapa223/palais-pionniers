<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo  = db();
$role = $_SESSION['role'] ?? '';
$uid  = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'mark_lu' && $id) {
        $pdo->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND (destinataire_role = ? OR destinataire_id = ?)")->execute([$id, $role, $uid]);
    }
    if ($action === 'mark_all_lu') {
        $pdo->prepare("UPDATE notifications SET lu = 1 WHERE lu = 0 AND (destinataire_role = ? OR destinataire_id = ?)")
            ->execute([$role, $uid]);
    }
    header('Location: notifications.php'); exit;
}

$notifs = $pdo->prepare("
    SELECT * FROM notifications
    WHERE destinataire_role = ? OR destinataire_id = ?
    ORDER BY lu ASC, created_at DESC
    LIMIT 50
");
$notifs->execute([$role, $uid]);
$notifs = $notifs->fetchAll();

$nonLus = count(array_filter($notifs, fn($n) => !$n['lu']));

if ($nonLus > 0) {
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE lu = 0 AND (destinataire_role = ? OR destinataire_id = ?)")
        ->execute([$role, $uid]);
}

$typeIcons = [
    'nouvelle_reservation' => ['fa-calendar-plus',  'bg-indigo-50 text-indigo-600', 'Nouvelle demande'],
    'paiement_recu'        => ['fa-cash-register',  'bg-green-50 text-green-600',   'Paiement reçu'],
    'guichet_resa'         => ['fa-store',           'bg-blue-50 text-blue-600',     'Réservation guichet'],
    'nouvelle_observation' => ['fa-eye',             'bg-yellow-50 text-yellow-600', 'Observation / Note'],
    'reservation_validee'  => ['fa-calendar-check',  'bg-emerald-50 text-emerald-600','Réservation validée'],
    'reservation_refusee'  => ['fa-calendar-times',  'bg-red-50 text-red-600',       'Réservation refusée'],
    'reduction_accordee'   => ['fa-percent',         'bg-orange-50 text-orange-600', 'Réduction accordée'],
    'reservation_expiree'  => ['fa-clock',           'bg-slate-100 text-slate-500',  'Réservation expirée'],
    'paiement_confirme'    => ['fa-check-double',    'bg-green-50 text-green-600',   'Paiement confirmé'],
];

$pageTitle = "Notifications";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight flex items-center gap-2">
      <i class="fas fa-bell text-accent"></i> Notifications
      <?php if ($nonLus): ?>
        <span class="text-base bg-accent text-white px-2.5 py-0.5 rounded-full"><?= $nonLus ?></span>
      <?php endif; ?>
    </h1>
    <p class="text-sm text-slate-500 mt-0.5"><?= count($notifs) ?> notification(s)</p>
  </div>
  <?php if ($nonLus): ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action"     value="mark_all_lu">
    <button class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-check-double"></i> Tout marquer comme lu
    </button>
  </form>
  <?php endif; ?>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <?php if (empty($notifs)): ?>
  <div class="py-16 text-center">
    <i class="fas fa-bell-slash text-4xl text-slate-200 mb-3"></i>
    <p class="text-slate-400 font-semibold">Aucune notification.</p>
  </div>
  <?php else: ?>
  <div class="divide-y divide-slate-50">
    <?php foreach ($notifs as $n):
      [$icon, $cls, $label] = $typeIcons[$n['type']] ?? ['fa-bell', 'bg-slate-100 text-slate-500', $n['type']];
    ?>
    <div class="flex items-start gap-4 px-5 py-4 hover:bg-slate-50 transition <?= !$n['lu'] ? 'bg-blue-50/30' : '' ?>">
      <div class="w-10 h-10 <?= $cls ?> rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
        <i class="fas <?= $icon ?> text-sm"></i>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="text-[10px] font-black px-2 py-0.5 rounded-full <?= $cls ?>"><?= $label ?></span>
          <?php if (!$n['lu']): ?><span class="w-2 h-2 bg-accent rounded-full flex-shrink-0"></span><?php endif; ?>
        </div>
        <p class="text-sm font-semibold text-slate-800 mt-1"><?= e($n['message']) ?></p>
        <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?></p>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <?php if ($n['lien']):
          $lienPropre = preg_replace('#^admin/#', '', $n['lien']);
          $sep = (strpos($lienPropre, '?') !== false) ? '&' : '?';
          $lienLu = e($lienPropre) . $sep . 'read_notif=' . $n['id'];
        ?>
        <a href="<?= $lienLu ?>"
           class="text-xs font-black text-primary hover:text-accent transition">
          Voir →
        </a>
        <?php endif; ?>
        <?php if (!$n['lu']): ?>
        <form method="POST" class="inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action"     value="mark_lu">
          <input type="hidden" name="id"         value="<?= $n['id'] ?>">
          <button class="text-xs text-slate-400 hover:text-primary transition" title="Marquer comme lu">
            <i class="fas fa-check"></i>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>