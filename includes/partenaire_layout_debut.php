<?php
$uLayout      = current_user();
$ongletActif  = $ongletPartenaire ?? 'tableau-de-bord';
$nomPartenaire = (string)($partenaireLayout['nom'] ?? 'Partenaire');

$nbNotifsLayout = 0;
$nbAttenteLayout = 0;
try {
    $nbNotifsLayout = count_notifications();
    $stAtt = db()->prepare("SELECT COUNT(*) FROM reservations WHERE partenaire_id = ? AND statut = 'en_attente'");
    $stAtt->execute([(int)($partenaireLayout['id'] ?? 0)]);
    $nbAttenteLayout = (int)$stAtt->fetchColumn();
} catch (Exception $e) {
}

$navPartenaire = [
    'tableau-de-bord' => ['mon-compte.php?tab=tableau-de-bord', 'fas fa-chart-pie', 'Tableau de bord', 0],
    'nouvelle'        => ['reserver.php', 'fas fa-plus-circle', 'Nouvelle réservation', 0],
    'reservations'    => ['mon-compte.php?tab=reservations', 'fas fa-calendar-check', 'Mes réservations', $nbAttenteLayout],
    'bons'            => ['mon-compte.php?tab=bons', 'fas fa-file-invoice', 'Mes bons', 0],
    'services'        => ['mon-compte.php?tab=services', 'fas fa-concierge-bell', 'Services', 0],
    'messages'        => ['mon-compte.php?tab=messages', 'fas fa-envelope', 'Messages', 0],
    'notifications'   => ['mon-compte.php?tab=notifications', 'fas fa-bell', 'Notifications', $nbNotifsLayout],
    'profil'          => ['mon-compte.php?tab=profil', 'fas fa-user-cog', 'Profil et mot de passe', 0],
];
if (!empty($navPartenaireBaux)) {
    $navPartenaire['baux'] = ['mon-compte.php?tab=baux', 'fas fa-file-signature', 'Mes baux', 0];
}
$titreCourt = $navPartenaire[$ongletActif][2] ?? 'Tableau de bord';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Espace admin | Palais des Pionniers') ?></title>
<link rel="stylesheet" href="assets/css/tailwind.css<?= version_fichier('assets/css/tailwind.css') ?>">
<link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/fonts.css">
<style>
body { font-family:Inter,system-ui; background:#F8FAFC; color:#0A2558; }
h1,h2,h3 { font-family:'Plus Jakarta Sans',Inter,sans-serif; letter-spacing:-.02em; }
#sidebar { transition: transform .3s cubic-bezier(.4,0,.2,1); }
@media(max-width:767px){ #sidebar { position:fixed; top:0; left:0; height:100vh; z-index:50; transform:translateX(-100%); } #sidebar.open { transform:translateX(0); } #overlay { display:block; } }
@media(min-width:768px){
  #sidebar { position:fixed; top:0; left:0; height:100vh; z-index:20; overflow-y:auto; }
  #mainContent { margin-left:16rem; }
}
#overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:40; }
</style>
</head>
<body class="min-h-screen espace-partenaire">

<div id="overlay" onclick="closeSidebar()"></div>

<div class="flex min-h-screen">

  <aside id="sidebar" class="w-64 flex-col bg-primary text-white flex flex-shrink-0">

    <div class="px-5 py-5 border-b border-white/10 flex items-center justify-between">
      <div class="min-w-0">
        <div class="text-base font-extrabold uppercase italic tracking-tight">Palais <span class="text-accent">Pionniers</span></div>
        <span class="inline-block mt-1 text-[10px] font-black px-2 py-0.5 rounded-full bg-accent text-white">Espace admin · Partenaire</span>
      </div>
      <button onclick="closeSidebar()" class="md:hidden text-white/60 hover:text-white" aria-label="Fermer le menu">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <div class="px-5 py-4 border-b border-white/10 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-accent flex items-center justify-center flex-shrink-0">
        <i class="fas fa-handshake text-sm"></i>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold truncate"><?= e($nomPartenaire) ?></p>
        <p class="text-[10px] text-white/50 truncate"><?= e($uLayout['nom_complet']) ?> · <?= e($uLayout['email']) ?></p>
      </div>
    </div>

    <nav class="flex-1 p-3 space-y-1 text-sm overflow-y-auto" aria-label="Navigation de l'espace partenaire">
      <?php foreach ($navPartenaire as $cle => [$href, $icon, $label, $compteur]):
        $active = ($cle === $ongletActif);
      ?>
      <a href="<?= e($href) ?>" data-onglet="<?= e($cle) ?>"
         class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all <?= $active ? 'bg-accent text-white shadow-lg' : 'text-white/75 hover:bg-white/10 hover:text-white' ?>"
         <?= $active ? 'aria-current="page"' : '' ?>>
        <i class="<?= $icon ?> w-4 text-center text-sm <?= $active ? '' : 'opacity-70' ?>"></i>
        <span class="flex-1"><?= e($label) ?></span>
        <?php if ($compteur > 0): ?>
          <span class="bg-accent text-white text-[10px] font-black px-1.5 py-0.5 rounded-full <?= $active ? 'bg-white text-accent' : '' ?>"><?= min((int)$compteur, 99) ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="p-3 border-t border-white/10 space-y-1">
      <a href="logout.php" onclick="return confirm('Se déconnecter ?')"
         class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-red-400 hover:bg-red-500/10 hover:text-red-300 transition text-sm">
        <i class="fas fa-sign-out-alt w-4 text-center text-sm"></i>
        <span>Déconnexion</span>
      </a>
    </div>
  </aside>

  <div id="mainContent" class="flex-1 flex flex-col min-w-0">
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6 py-3 sticky top-0 z-30 shadow-sm">
      <div class="flex items-center gap-2">
        <a href="index.php"
           class="flex items-center gap-1.5 h-9 px-3 rounded-xl bg-slate-100 text-primary hover:bg-slate-200 transition flex-shrink-0 text-[11px] font-black uppercase tracking-tight"
           title="Quitter l'espace admin et revenir sur le site public">
          <i class="fas fa-globe text-sm"></i>
          <span class="hidden sm:inline">Retour sur le site</span>
        </a>

        <button onclick="openSidebar()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-xl bg-slate-100 text-primary hover:bg-slate-200 transition" aria-label="Ouvrir le menu">
          <i class="fas fa-bars"></i>
        </button>
      </div>

      <div class="md:hidden font-black text-primary uppercase italic text-sm tracking-tight"><?= e($titreCourt) ?></div>

      <div class="hidden md:flex items-center gap-2 text-sm text-slate-400">
        <i class="fas fa-handshake text-xs"></i>
        <span><?= e($nomPartenaire) ?></span>
        <span>/</span>
        <span class="font-semibold text-primary"><?= e($titreCourt) ?></span>
      </div>

      <div class="flex items-center gap-3">
        <a href="mon-compte.php?tab=notifications" class="relative flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-amber-500 hover:text-white transition" aria-label="Notifications">
          <i class="fas fa-bell text-sm"></i>
          <?php if ($nbNotifsLayout > 0): ?>
          <span class="absolute -top-1 -right-1 w-4 h-4 bg-accent text-white text-[9px] font-black rounded-full flex items-center justify-center"><?= min($nbNotifsLayout, 9) ?><?= $nbNotifsLayout > 9 ? '+' : '' ?></span>
          <?php endif; ?>
        </a>
        <div class="flex items-center gap-2 text-sm">
          <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center font-black text-white text-xs">
            <?= e(strtoupper(mb_substr($uLayout['nom_complet'], 0, 1))) ?>
          </div>
          <span class="hidden md:block font-semibold text-slate-700 max-w-[120px] truncate"><?= e(explode(' ', $uLayout['nom_complet'])[0]) ?></span>
        </div>
      </div>
    </header>

    <main class="flex-1 p-4 md:p-8">
      <div class="w-full max-w-[1600px] mx-auto">
        <?php if ($ongletActif !== 'tableau-de-bord'): ?>
        <a href="mon-compte.php?tab=tableau-de-bord" id="retourTableauBord"
           class="inline-flex items-center gap-1.5 mb-4 px-3 py-1.5 rounded-full bg-white border border-slate-200 text-[11px] font-black uppercase tracking-tight text-primary hover:border-primary transition">
          <i class="fas fa-arrow-left text-[10px]"></i> Retour au tableau de bord
        </a>
        <?php endif; ?>
