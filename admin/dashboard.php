<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// LA CORRECTION EST ICI : on utilise ../ au lieu de /
require_admin('../index.php');

$pdo = db();

// Statistiques
$stats = [
  'attente'   => (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='en_attente'")->fetchColumn(),
  'validees'  => (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='validee'")->fetchColumn(),
  'espaces'   => (int)$pdo->query("SELECT COUNT(*) FROM espaces WHERE disponible=1")->fetchColumn(),
  'activites' => (int)$pdo->query("SELECT COUNT(*) FROM activites")->fetchColumn(),
  'users'     => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

// Réservations récentes
$recent = $pdo->query("
  SELECT r.*, u.nom_complet, e.nom AS espace_nom
  FROM reservations r
  JOIN users u   ON u.id = r.user_id
  JOIN espaces e ON e.id = r.espace_id
  ORDER BY r.created_at DESC
  LIMIT 8
")->fetchAll();

$badge = [
  'en_attente' => ['En attente', 'bg-amber-100 text-amber-800'],
  'validee'    => ['Validée',    'bg-emerald-100 text-emerald-800'],
  'refusee'    => ['Refusée',    'bg-red-100 text-red-800'],
  'annulee'    => ['Annulée',    'bg-slate-100 text-slate-700'],
];

$pageTitle = "Dashboard — Admin";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">Tableau de bord</h1>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
  <?php
  $cards = [
    ['Demandes en attente',   $stats['attente'],   'text-amber-600'],
    ['Réservations validées', $stats['validees'],  'text-emerald-600'],
    ['Activités',             $stats['activites'], 'text-accent'],
    ['Espaces disponibles',   $stats['espaces'],   'text-primary'],
    ['Utilisateurs',          $stats['users'],     'text-slate-600'],
  ];
  foreach ($cards as [$l,$v,$c]): ?>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="text-xs font-medium uppercase tracking-wider text-slate-500"><?= e($l) ?></div>
      <div class="mt-2 text-3xl font-bold <?= $c ?>"><?= (int)$v ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-8 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
  <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
    <h2 class="font-semibold text-slate-800">Demandes récentes</h2>
    <a href="reservations.php" class="text-sm font-medium text-primary hover:underline">Voir tout →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
          <tr>
            <th class="px-5 py-3">Nom</th>
            <th class="px-5 py-3">Espace</th>
            <th class="px-5 py-3">Date</th>
            <th class="px-5 py-3">Statut</th>
            <th class="px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($recent as $r): [$lib,$cls] = $badge[$r['statut']]; ?>
            <tr class="hover:bg-slate-50 transition-colors">
              <td class="px-5 py-3 font-medium text-slate-700 uppercase italic"><?= e($r['nom_complet']) ?></td>
              <td class="px-5 py-3"><?= e($r['espace_nom']) ?></td>
              <td class="px-5 py-3 font-bold"><?= e(date('d/m/Y', strtotime($r['date_resa']))) ?></td>
              <td class="px-5 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $cls ?>"><?= e($lib) ?></span></td>
              <td class="px-5 py-3">
                <a href="reservations.php#r-<?= (int)$r['id'] ?>" class="text-primary font-bold hover:underline">Gérer</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>