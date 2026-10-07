<?php
require_once __DIR__ . '/auth.php';
$u = current_user();
$page = $page ?? basename($_SERVER['PHP_SELF']);

$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$prefix = ($current_dir === 'admin') ? '../' : '';
$admin_link = ($current_dir === 'admin') ? 'dashboard.php' : 'admin/dashboard.php';

$pageDescription = $pageDescription ?? "Le Palais des Pionniers de Magnambougou/Dianéguéla, établissement public malien dédié à la construction citoyenne, la formation et l'épanouissement de la jeunesse. Réservez nos espaces, découvrez nos activités.";
$pageImage = $pageImage ?? 'assets/images/porte.jpeg';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'palaisdespionniers.ml') . ($_SERVER['REQUEST_URI'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Palais des Pionniers') ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($currentUrl) ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle ?? 'Palais des Pionniers') ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'palaisdespionniers.ml') . '/' . ($prefix ?? '') . $pageImage) ?>">
    <meta property="og:url" content="<?= e($currentUrl) ?>">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="Palais des Pionniers">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle ?? 'Palais des Pionniers') ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "GovernmentOrganization",
        "name": "Palais des Pionniers",
        "alternateName": "Palais des Pionniers de Magnambougou/Dianéguéla",
        "url": "<?= e($currentUrl) ?>",
        "logo": "<?= e(($prefix ?? '') . 'assets/images/logopalais.png') ?>",
        "address": {
            "@type": "PostalAddress",
            "addressCountry": "ML",
            "addressLocality": "Bamako"
        }
    }
    </script>

    <link rel="stylesheet" href="<?= $prefix ?>assets/css/tailwind.css">
    <link rel="stylesheet" href="<?= $prefix ?>assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= $prefix ?>assets/css/fonts.css">
    <style>
        body{font-family:Inter,sans-serif;background:#F8FAFC;color:#0A2558}
        .scrollbar-hide::-webkit-scrollbar{display:none}
        .scrollbar-hide{-ms-overflow-style:none;scrollbar-width:none}
        @keyframes kenburns { 0% { transform: scale(1); } 100% { transform: scale(1.12); } }
        .kenburns { animation: kenburns 8s ease-out infinite alternate; }
        @keyframes marquee-scroll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .marquee { animation: marquee-scroll 40s linear infinite; }
        .marquee:hover { animation-play-state: paused; }
        .marquee-ltr { animation: marquee-scroll 30s linear infinite reverse; }
        .marquee-ltr:hover { animation-play-state: paused; }
        .marquee-bg { animation: marquee-scroll 55s linear infinite; }
        .marquee-bg-slow { animation: marquee-scroll 150s linear infinite; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

<div class="flex h-[3px] w-full">
  <div class="flex-1 bg-[#14B53A]"></div>
  <div class="flex-1 bg-[#FCD116]"></div>
  <div class="flex-1 bg-[#CE1126]"></div>
</div>

<header class="sticky top-0 z-50 border-b border-slate-200 bg-white/85 backdrop-blur">
  <div class="container mx-auto flex h-16 items-center justify-between px-4">
    
  <a href="<?= $prefix ?>index.php" class="flex items-center gap-2 sm:gap-4">
    <?php if (file_exists(__DIR__ . '/../assets/images/logominis.jpg')): ?>
    <img src="<?= $prefix ?>assets/images/logominis.jpg" alt="Sceau de la République du Mali" class="h-7 sm:h-12 md:h-14 w-auto object-contain rounded-full">
    <div class="h-6 sm:h-10 md:h-12 w-px bg-slate-200"></div>
    <?php endif; ?>

    <img src="<?= $prefix ?>assets/images/logopalais.png" alt="Logo du Palais des Pionniers" class="h-9 sm:h-16 md:h-20 w-auto object-contain">
</a>

    <nav class="hidden lg:flex items-center gap-1 text-sm">
      <?php
      $links = ['index.php'=>'Accueil', 'espaces.php'=>'Espaces', 'activites.php'=>'Activités', 'formations.php'=>'Formations', 'personnalites.php'=>'Icônes', 'a-propos.php'=>'À propos', 'faq.php'=>'FAQ', 'contact.php'=>'Contact'];
      foreach ($links as $href => $label):
        $active = ($page === $href);
      ?>
        <a href="<?= $prefix ?><?= $href ?>" class="rounded-md px-3 py-2 font-medium <?= $active ? 'text-accent' : 'text-slate-700 hover:bg-slate-100' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="flex items-center gap-2">
      <div class="hidden lg:flex items-center gap-2">
        <?php if ($u): ?>
          <?php $isAdminRole = in_array($u['role'], ['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable'], true); ?>
          <?php if ($isAdminRole): ?>
            <a href="<?= $prefix . $admin_link ?>" class="bg-primary text-white text-xs font-bold px-3 py-1.5 rounded-md hover:bg-primary-dark transition shadow-sm">
                <i class="fas fa-user-shield mr-1"></i> Admin
            </a>
          <?php else: ?>
            <?php $clientNotifs = count_notifications(); ?>
            <a href="<?= $prefix ?>mon-compte.php?tab=notifications" class="relative flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-amber-500 hover:text-white transition">
              <i class="fas fa-bell text-sm"></i>
              <?php if ($clientNotifs > 0): ?>
              <span class="absolute -top-1 -right-1 w-4 h-4 bg-accent text-white text-[9px] font-black rounded-full flex items-center justify-center"><?= min($clientNotifs,9) ?><?= $clientNotifs>9?'+':'' ?></span>
              <?php endif; ?>
            </a>
            <a href="<?= $prefix ?>mon-compte.php" class="text-sm font-medium hover:bg-slate-100 px-3 py-1.5 rounded-md"><?= $u['role'] === 'partenaire' ? 'Espace admin' : 'Mon compte' ?></a>
          <?php endif; ?>
          
          <a href="<?= $prefix ?>logout.php" onclick="return confirm('Se déconnecter ?')" class="bg-accent text-white text-sm px-3 py-1.5 rounded-md font-bold">Déconnexion</a>
        <?php else: ?>
          <a href="<?= $prefix ?>login.php" class="text-sm font-medium text-primary px-3 py-1.5 rounded-md border border-slate-200 hover:border-primary hover:bg-slate-50 transition">Connexion</a>
          <a href="<?= $prefix ?>register.php" class="bg-accent text-white text-sm px-4 py-2 rounded-md font-bold transition-transform hover:scale-105">Réserver</a>
        <?php endif; ?>
      </div>

      <button onclick="toggleMenu()" class="lg:hidden flex flex-col gap-1.5 p-2 focus:outline-none">
        <span id="line1" class="w-6 h-0.5 bg-primary transition-all"></span>
        <span id="line2" class="w-6 h-0.5 bg-primary transition-all"></span>
        <span id="line3" class="w-6 h-0.5 bg-primary transition-all"></span>
      </button>
    </div>
  </div>

  <div id="mobileMenu" class="lg:hidden bg-white shadow-2xl border-b border-slate-200 absolute w-full left-0 z-50 overflow-hidden transition-all duration-300 ease-out max-h-0 opacity-0">
    <nav class="flex flex-col px-3 max-h-[45vh] overflow-y-auto">
        <?php foreach ($links as $href => $label): $activeM = ($page === $href); ?>
            <a href="<?= $prefix ?><?= $href ?>" class="py-2 font-bold text-[11px] uppercase tracking-tight border-b border-slate-100 flex items-center justify-between <?= $activeM ? 'text-accent border-accent/20' : 'text-primary' ?>">
                <?= $label ?>
                <?php if ($activeM): ?><i class="fas fa-circle text-[5px] text-accent"></i><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-2.5 pb-3 flex flex-col gap-1.5">
        <?php if ($u): ?>
            <?php $isAdminRole = in_array($u['role'], ['superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable'], true); ?>
            <?php if ($isAdminRole): ?>
                <a href="<?= $prefix . $admin_link ?>" class="bg-primary text-white text-center py-2 rounded-lg font-bold uppercase tracking-wide text-[10px]"><i class="fas fa-user-shield mr-1"></i> Administration</a>
            <?php else: ?>
                <a href="<?= $prefix ?>mon-compte.php" class="border-2 border-slate-200 text-primary text-center py-2 rounded-lg font-bold uppercase tracking-wide text-[10px]"><?= $u['role'] === 'partenaire' ? 'Espace admin' : 'Mon compte' ?></a>
            <?php endif; ?>
            <a href="<?= $prefix ?>logout.php" class="bg-accent text-white text-center py-2 rounded-lg font-bold uppercase tracking-wide text-[10px]">Déconnexion</a>
        <?php else: ?>
            <a href="<?= $prefix ?>login.php" class="border-2 border-slate-200 text-primary text-center py-2 rounded-lg font-bold uppercase tracking-wide text-[10px] hover:border-primary hover:bg-slate-50 transition">Connexion</a>
            <a href="<?= $prefix ?>register.php" class="bg-accent text-white text-center py-2 rounded-lg font-bold uppercase tracking-wide text-[10px]">Réserver</a>
        <?php endif; ?>
    </div>
  </div>
</header>
<main class="flex-1">
<script>
function toggleMenu() {
    const menu = document.getElementById('mobileMenu');
    const l1 = document.getElementById('line1'), l2 = document.getElementById('line2'), l3 = document.getElementById('line3');
    const isOpen = menu.classList.contains('opacity-100');
    if (!isOpen) {
        menu.classList.remove('max-h-0','opacity-0');
        menu.classList.add('max-h-[80vh]','opacity-100');
        l1.style.transform = "translateY(8px) rotate(45deg)"; l2.style.opacity = "0"; l3.style.transform = "translateY(-8px) rotate(-45deg)";
    } else {
        menu.classList.remove('max-h-[80vh]','opacity-100');
        menu.classList.add('max-h-0','opacity-0');
        l1.style.transform = "none"; l2.style.opacity = "1"; l3.style.transform = "none";
    }
}
</script>