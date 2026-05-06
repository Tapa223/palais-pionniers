<?php
require_once __DIR__ . '/auth.php';
$u = current_user();
$page = $page ?? basename($_SERVER['PHP_SELF']);

// Détection de l'emplacement pour corriger les liens relatifs
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$prefix = ($current_dir === 'admin') ? '../' : '';
$admin_link = ($current_dir === 'admin') ? 'dashboard.php' : 'admin/dashboard.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Palais des Pionniers') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: { DEFAULT: '#0A2558', dark: '#06184A' },
              accent:  { DEFAULT: '#E61E2A', dark: '#B7141F' },
            },
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:Inter,sans-serif;background:#F8FAFC;color:#0A2558}
    </style>
</head>
<body class="min-h-screen flex flex-col">

<header class="sticky top-0 z-50 border-b border-slate-200 bg-white/85 backdrop-blur">
  <div class="container mx-auto flex h-16 items-center justify-between px-4">
    
    <a href="<?= $prefix ?>index.php" class="flex items-center">
      <img src="<?= $prefix ?>assets/images/logopalais.png" alt="Logo" class="h-16 md:h-20 w-auto object-contain">
    </a>

    <nav class="hidden md:flex items-center gap-1 text-sm">
      <?php
      $links = ['index.php'=>'Accueil', 'espaces.php'=>'Espaces', 'activites.php'=>'Activités', 'a-propos.php'=>'À propos', 'contact.php'=>'Contact'];
      foreach ($links as $href => $label):
        $active = ($page === $href);
      ?>
        <a href="<?= $prefix ?><?= $href ?>" class="rounded-md px-3 py-2 font-medium <?= $active ? 'text-accent' : 'text-slate-700 hover:bg-slate-100' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="flex items-center gap-2">
      <div class="hidden md:flex items-center gap-2">
        <?php if ($u): ?>
          <?php if (in_array($u['role'], ['admin', 'superadmin', 'admin_activites', 'admin_espaces'])): ?>
            <a href=" admin/dashboard.php" class="bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-md hover:bg-primary-dark transition shadow-sm">
                <i class="fas fa-user-shield mr-1"></i> Admin
            </a>
          <?php endif; ?>
          <a href="<?= $prefix ?>mon-compte.php" class="text-sm font-medium hover:bg-slate-100 px-3 py-1.5 rounded-md">Mon compte</a>
          
          <a href="<?= $prefix ?>logout.php" onclick="return confirm('Se déconnecter ?')" class="bg-accent text-white text-sm px-3 py-1.5 rounded-md font-bold">Déconnexion</a>
        <?php else: ?>
          <a href="<?= $prefix ?>login.php" class="text-sm font-medium px-3 py-1.5">Connexion</a>
          <a href="<?= $prefix ?>register.php" class="bg-accent text-white text-sm px-4 py-2 rounded-md font-bold transition-transform hover:scale-105">Réserver</a>
        <?php endif; ?>
      </div>

      <button onclick="toggleMenu()" class="md:hidden flex flex-col gap-1.5 p-2 focus:outline-none">
        <span id="line1" class="w-6 h-0.5 bg-primary transition-all"></span>
        <span id="line2" class="w-6 h-0.5 bg-primary transition-all"></span>
        <span id="line3" class="w-6 h-0.5 bg-primary transition-all"></span>
      </button>
    </div>
  </div>

  <div id="mobileMenu" class="hidden md:hidden bg-white border-b border-slate-200 shadow-xl absolute w-full left-0">
    <nav class="flex flex-col p-4 gap-1">
        <?php foreach ($links as $href => $label): ?>
            <a href="<?= $prefix ?><?= $href ?>" class="px-4 py-3 font-bold text-primary uppercase tracking-widest text-xs border-b border-slate-50"><?= $label ?></a>
        <?php endforeach; ?>
        <div class="mt-4 pt-4 flex flex-col gap-3">
            <?php if ($u): ?>
                <?php if (in_array($u['role'], ['admin', 'superadmin', 'admin_activites', 'admin_espaces'])): ?>
                    <a href="<?= $admin_link ?>" class="bg-primary text-white text-center py-3 rounded-lg font-bold mx-4"><i class="fas fa-user-shield mr-2"></i> Administration</a>
                <?php endif; ?>
                <a href="<?= $prefix ?>logout.php" class="bg-accent text-white text-center py-3 rounded-lg font-bold mx-4">Déconnexion</a>
            <?php endif; ?>
        </div>
    </nav>
  </div>
</header>
<main class="flex-1">
<script>
function toggleMenu() {
    const menu = document.getElementById('mobileMenu');
    const l1 = document.getElementById('line1'), l2 = document.getElementById('line2'), l3 = document.getElementById('line3');
    menu.classList.toggle('hidden');
    if(!menu.classList.contains('hidden')) {
        l1.style.transform = "translateY(8px) rotate(45deg)"; l2.style.opacity = "0"; l3.style.transform = "translateY(-8px) rotate(-45deg)";
    } else {
        l1.style.transform = "none"; l2.style.opacity = "1"; l3.style.transform = "none";
    }
}
</script>