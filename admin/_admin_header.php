<?php
require_once __DIR__ . '/../includes/auth.php';
$u = current_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin — Palais des Pionniers') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      colors: { primary: { DEFAULT:'#0A2558', dark:'#06184A' }, accent: { DEFAULT:'#E61E2A', dark:'#B7141F' } },
      boxShadow: { card: '0 1px 2px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.06)' },
    } }
  };
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>body{font-family:Inter,system-ui;background:#F8FAFC;color:#0A2558}h1,h2,h3{font-family:'Plus Jakarta Sans',Inter,sans-serif;letter-spacing:-.02em}</style>
</head>
<body class="min-h-screen">

<div class="flex min-h-screen">
  <aside class="hidden md:flex w-64 flex-col bg-primary text-white">
    <div class="px-6 py-5 border-b border-white/10">
      <div class="text-lg font-extrabold">palais <span class="text-accent">PIONNIERS</span></div>
      <p class="text-xs text-white/60 mt-1">Espace administrateur</p>
    </div>
    <nav class="flex-1 p-3 space-y-1 text-sm">
      <?php
      $nav = [
        'dashboard.php'    => ['📊', 'Tableau de bord'],
        'reservations.php' => ['📅', 'Réservations'],
        'activites.php'    => ['🎭', 'Activités'], // Ajout de la ligne Activités
        'espaces.php'      => ['🏛️', 'Espaces'],
        'tarifs.php'       => ['💰', 'Tarifs'],
        'users.php'        => ['👥', 'Utilisateurs'],
      ];
      
      $current_file = basename($_SERVER['PHP_SELF']);
      
      foreach ($nav as $href => [$ico, $label]):
        $active = ($current_file === $href);
      ?>
        <a href="<?= $href ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 <?= $active ? 'bg-accent text-white' : 'text-white/80 hover:bg-white/10' ?>">
          <span><?= $ico ?></span><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
      
      <a href="../logout.php" onclick="return confirm('Déconnexion ?')" class="mt-4 flex items-center gap-3 rounded-lg px-3 py-2.5 text-red-300 hover:bg-red-500/10">
        <span>🚪</span><span>Déconnexion</span>
      </a>
    </nav>
  </aside>

  <div class="flex-1 flex flex-col">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
      <a href="../index.php" class="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-primary transition">
        <span>←</span> Retour au site
      </a>
      <!-- Identité utilisateur gardée à l'identique -->
      <div class="flex items-center gap-3">
        <div class="text-right text-sm">
          <div class="font-semibold"><?= e($u['nom_complet']) ?></div>
          <div class="text-xs text-slate-500">Administrateur</div>
        </div>
        <div class="grid h-9 w-9 place-items-center rounded-full bg-primary text-sm font-bold text-white shadow-sm">
          <?= e(strtoupper(substr($u['nom_complet'],0,1))) ?>
        </div>
      </div>
    </header>
    <main class="flex-1 p-6 md:p-8">