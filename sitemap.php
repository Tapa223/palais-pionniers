<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

header('Content-Type: application/xml; charset=utf-8');

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'palaisdespionniers.ml');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <url><loc><?= e($base) ?>/index.php</loc><priority>1.0</priority></url>
  <url><loc><?= e($base) ?>/espaces.php</loc><priority>0.9</priority></url>
  <url><loc><?= e($base) ?>/activites.php</loc><priority>0.9</priority></url>
  <url><loc><?= e($base) ?>/formations.php</loc><priority>0.8</priority></url>
  <url><loc><?= e($base) ?>/personnalites.php</loc><priority>0.7</priority></url>
  <url><loc><?= e($base) ?>/a-propos.php</loc><priority>0.7</priority></url>
  <url><loc><?= e($base) ?>/faq.php</loc><priority>0.6</priority></url>
  <url><loc><?= e($base) ?>/contact.php</loc><priority>0.6</priority></url>
  <url><loc><?= e($base) ?>/sengager.php</loc><priority>0.8</priority></url>
  <url><loc><?= e($base) ?>/demande-bail.php</loc><priority>0.5</priority></url>

  <?php foreach ($pdo->query("SELECT slug FROM espaces WHERE disponible = 1") as $row): ?>
  <url><loc><?= e($base) ?>/espace.php?slug=<?= urlencode($row['slug']) ?></loc><priority>0.7</priority></url>
  <?php endforeach; ?>

  <?php foreach ($pdo->query("SELECT slug FROM activites") as $row): ?>
  <url><loc><?= e($base) ?>/detail-activite.php?slug=<?= urlencode($row['slug']) ?></loc><priority>0.6</priority></url>
  <?php endforeach; ?>

  <?php foreach ($pdo->query("SELECT id FROM formations WHERE actif = 1") as $row): ?>
  <url><loc><?= e($base) ?>/formation.php?id=<?= (int)$row['id'] ?></loc><priority>0.6</priority></url>
  <?php endforeach; ?>

  <?php foreach ($pdo->query("SELECT id FROM personnalites") as $row): ?>
  <url><loc><?= e($base) ?>/personnalite.php?id=<?= (int)$row['id'] ?></loc><priority>0.5</priority></url>
  <?php endforeach; ?>

</urlset>
