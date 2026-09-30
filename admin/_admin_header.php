<?php
require_once __DIR__ . '/../includes/auth.php';
$u    = current_user();
$role = $_SESSION['role'] ?? 'user';
$nav  = admin_nav();

$roleLabels = [
    'superadmin'      => ['Super Admin (Direction)', 'bg-accent text-white'],
    'ministre'        => ['Ministre / Rep.',   'bg-yellow-500 text-white'],
    'admin_espaces'   => ['Admin Espaces',     'bg-blue-600 text-white'],
    'admin_activites' => ['Admin Activités',   'bg-purple-600 text-white'],
    'admin_messages'  => ['Admin Messages',    'bg-green-600 text-white'],
    'admin_comptable' => ['Comptable',         'bg-teal-600 text-white'],
];
[$roleLabel, $roleBadge] = $roleLabels[$role] ?? ['Utilisateur','bg-slate-400 text-white'];

// Marquer une notification comme lue quand on arrive dessus via son lien "Voir"
if (!empty($_GET['read_notif'])) {
    $uidNotif = (int)($_SESSION['user_id'] ?? 0);
    db()->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND (destinataire_role = ? OR destinataire_id = ?)")
        ->execute([(int)$_GET['read_notif'], $role, $uidNotif]);
}

// Compter messages non lus (pour badge) — même périmètre par rôle que messages.php
$unread = 0;
$notifCount = 0;
try {
    $whereMsgRole = '';
    if ($role === 'admin_espaces')   $whereMsgRole = "AND sujet = 'reservation_espace'";
    if ($role === 'admin_activites') $whereMsgRole = "AND sujet = 'activite'";
    $unread = (int)db()->query("SELECT COUNT(*) FROM messages WHERE lu = 0 $whereMsgRole")->fetchColumn();
    $notifCount = count_notifications();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin — Palais des Pionniers') ?></title>
<link rel="stylesheet" href="../assets/css/tailwind.css">
<link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../assets/css/fonts.css">
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
<body class="min-h-screen">

<!-- Overlay mobile -->
<div id="overlay" onclick="closeSidebar()"></div>

<div class="flex min-h-screen">
  <!-- SIDEBAR -->
  <aside id="sidebar" class="w-64 flex-col bg-primary text-white flex flex-shrink-0">
    <!-- Logo -->
    <div class="px-5 py-5 border-b border-white/10 flex items-center justify-between">
      <div>
        <div class="text-base font-extrabold uppercase italic tracking-tight">Palais <span class="text-accent">Pionniers</span></div>
        <span class="inline-block mt-1 text-[10px] font-black px-2 py-0.5 rounded-full <?= $roleBadge ?>"><?= $roleLabel ?></span>
      </div>
      <button onclick="closeSidebar()" class="md:hidden text-white/60 hover:text-white">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Profil -->
    <div class="px-5 py-4 border-b border-white/10 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-accent flex items-center justify-center font-black text-sm flex-shrink-0">
        <?= strtoupper(substr($u['nom_complet'],0,1)) ?>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold truncate"><?= e($u['nom_complet']) ?></p>
        <p class="text-[10px] text-white/50 truncate"><?= e($u['email']) ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-3 space-y-1 text-sm overflow-y-auto">
      <?php
      $current = basename($_SERVER['PHP_SELF']);
      $navBadges = admin_nav_badges();
      foreach ($nav as $href => [$icon, $label]):
        $active = ($current === $href);
        $isMsgs = ($href === 'messages.php');
        $badgeCount = $isMsgs ? $unread : ($navBadges[$href] ?? 0);
      ?>
        <a href="<?= $href ?>"
           class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition-all <?= $active ? 'bg-accent text-white shadow-lg' : 'text-white/75 hover:bg-white/10 hover:text-white' ?>">
          <i class="fas <?= $icon ?> w-4 text-center text-sm <?= $active ? '' : 'opacity-70' ?>"></i>
          <span class="flex-1"><?= $label ?></span>
          <?php if ($badgeCount > 0): ?>
            <span class="bg-accent text-white text-[10px] font-black px-1.5 py-0.5 rounded-full <?= $active ? 'bg-white text-accent' : '' ?>"><?= min($badgeCount,99) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Bas sidebar -->
    <div class="p-3 border-t border-white/10 space-y-1">
      <a href="../logout.php" onclick="return confirm('Se déconnecter ?')"
         class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-red-400 hover:bg-red-500/10 hover:text-red-300 transition text-sm">
        <i class="fas fa-sign-out-alt w-4 text-center text-sm"></i>
        <span>Déconnexion</span>
      </a>
    </div>
  </aside>

  <!-- CONTENU PRINCIPAL -->
  <div id="mainContent" class="flex-1 flex flex-col min-w-0">
    <!-- Header top -->
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6 py-3 sticky top-0 z-30 shadow-sm">
      <div class="flex items-center gap-2">
        <!-- Bouton Retour au site public (texte explicite pour ne pas confondre les deux) -->
        <a href="../index.php"
           class="flex items-center gap-1.5 h-9 px-3 rounded-xl bg-slate-100 text-primary hover:bg-slate-200 transition flex-shrink-0 text-[11px] font-black uppercase tracking-tight"
           title="Quitter l'administration et revenir sur le site public">
          <i class="fas fa-globe text-sm"></i>
          <span class="hidden sm:inline">Retour sur le site</span>
        </a>

        <!-- Burger mobile -->
        <button onclick="openSidebar()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-xl bg-slate-100 text-primary hover:bg-slate-200 transition">
          <i class="fas fa-bars"></i>
        </button>

      </div>
        <!-- Titre page (mobile) -->
        <div class="md:hidden font-black text-primary uppercase italic text-sm tracking-tight"><?= e($pageTitle ?? '') ?></div>

        <!-- Breadcrumb desktop -->
        <div class="hidden md:flex items-center gap-2 text-sm text-slate-400">
          <i class="fas fa-home text-xs"></i>
          <span>/</span>
          <span class="font-semibold text-primary"><?= e($pageTitle ?? 'Dashboard') ?></span>
        </div>

      <!-- Droite header : notifications + profil -->
      <div class="flex items-center gap-3">
        <a href="notifications.php" class="relative flex items-center justify-center w-9 h-9 rounded-xl bg-slate-100 text-slate-600 hover:bg-amber-500 hover:text-white transition">
          <i class="fas fa-bell text-sm"></i>
          <?php if ($notifCount > 0): ?>
          <span class="absolute -top-1 -right-1 w-4 h-4 bg-accent text-white text-[9px] font-black rounded-full flex items-center justify-center"><?= min($notifCount,9) ?><?= $notifCount>9?'+':'' ?></span>
          <?php endif; ?>
        </a>
        <div class="flex items-center gap-2 text-sm">
          <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center font-black text-white text-xs">
            <?= strtoupper(substr($u['nom_complet'],0,1)) ?>
          </div>
          <span class="hidden md:block font-semibold text-slate-700 max-w-[120px] truncate"><?= e(explode(' ',$u['nom_complet'])[0]) ?></span>
        </div>
      </div>
    </header>

    <main class="flex-1 p-4 md:p-8">
      <div class="w-full max-w-[1600px] mx-auto">
        <?php
          /*
           * Flèche « Retour » : l'ancienne flèche de la barre du haut, déplacée ici
           * pour ne pas être confondue avec « Retour sur le site ».
           * Affichée sur toutes les pages sauf le tableau de bord.
           * - $pageRetour = [href, libellé] : retour vers la page parente indiquée ;
           * - $pageRetour = false : la page a déjà son propre lien retour ;
           * - sinon : comportement d'origine (page précédente), repli sur le tableau de bord.
           */
          $pageCourante = basename($_SERVER['PHP_SELF']);
          $estSecondaire = true;
          $retourCls = 'inline-flex items-center gap-1.5 mb-4 px-3 py-1.5 rounded-full bg-white border border-slate-200 text-[11px] font-black uppercase tracking-tight text-primary hover:border-primary transition';
        ?>
        <?php if (isset($pageRetour) && is_array($pageRetour)): ?>
        <a href="<?= e($pageRetour[0]) ?>" id="retourPage" class="<?= $retourCls ?>">
          <i class="fas fa-arrow-left text-[10px]"></i> <?= e($pageRetour[1] ?? 'Retour') ?>
        </a>
        <?php elseif (($pageRetour ?? null) !== false && $estSecondaire && $pageCourante !== 'dashboard.php'): ?>
        <button type="button" id="retourPage" class="<?= $retourCls ?>" title="Revenir à la page précédente"
                onclick="if (history.length > 1) { history.back(); } else { location.href = 'dashboard.php'; }">
          <i class="fas fa-arrow-left text-[10px]"></i> Retour
        </button>
        <?php endif; ?>