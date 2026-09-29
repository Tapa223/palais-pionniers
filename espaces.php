<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

$msgService = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'demander_service') {
    if (!is_logged_in()) {
        header('Location: login.php?redirect=' . urlencode('espaces.php#services'));
        exit;
    }
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msgService = ['err', 'Requête invalide, merci de réessayer.'];
    } else {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $messageSrv = trim($_POST['message'] ?? '');
        $existeService = $pdo->prepare("SELECT nom FROM services_annexes WHERE id = ? AND actif = 1");
        $existeService->execute([$serviceId]);
        $nomService = $existeService->fetchColumn();

        if (!$nomService) {
            $msgService = ['err', 'Service introuvable.'];
        } elseif (mb_strlen($messageSrv) < 5) {
            $msgService = ['err', 'Merci de préciser votre besoin (date souhaitée, quantité, contexte...).'];
        } else {
            $pdo->prepare("INSERT INTO demandes_services (user_id, service_id, message) VALUES (?,?,?)")
                ->execute([$_SESSION['user_id'], $serviceId, $messageSrv]);
            notify('admin_espaces', 'demande_service', "Nouvelle demande de service « $nomService » — {$_SESSION['nom_complet']}.", "demandes-services.php");
            notify('admin_comptable', 'demande_service', "Nouvelle demande de service « $nomService » — {$_SESSION['nom_complet']}. Tarif à négocier avec le client.", "demandes-services.php");
            log_activity('demande_service', 'espaces', "Demande de service « $nomService » par {$_SESSION['nom_complet']}");
            $msgService = ['ok', "Votre demande pour « $nomService » a bien été enregistrée. L'administration vous recontactera ; vous pouvez suivre son état dans Mon compte, onglet « Mes services »."];
        }
    }
}

$cat = $_GET['cat'] ?? 'all';

// 1. Modification SQL : On utilise GROUP_CONCAT pour récupérer toutes les images séparées par une virgule
$sql = "
  SELECT e.id, e.slug, e.nom, e.capacite, e.description, e.mode_reservation, e.gerant_externe, c.nom AS categorie, c.slug AS cat_slug,
  (SELECT GROUP_CONCAT(chemin ORDER BY id ASC) FROM espace_images WHERE espace_id = e.id) as toutes_photos,
  (SELECT COUNT(*) FROM tarifs WHERE espace_id = e.id AND est_bail = 1) as nb_tarifs_bail
  FROM espaces e
  JOIN categories c ON c.id = e.categorie_id
  WHERE 1=1
";

$params = [];
if ($cat !== 'all') {
    $sql .= " AND c.slug = :cat";
    $params[':cat'] = $cat;
}
$sql .= " ORDER BY e.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$espaces = $stmt->fetchAll();

$cats = $pdo->query("SELECT slug, nom FROM categories ORDER BY id")->fetchAll();

$pageTitle = "Nos espaces — Palais des Pionniers";
$page = 'espaces.php';
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-6 md:px-6 md:py-8">
  <header class="max-w-2xl">
    <h1 class="text-2xl sm:text-4xl font-black italic tracking-tighter uppercase">Nos <span class="text-accent">espaces</span></h1>
    <p class="mt-2 text-slate-500 font-medium text-sm sm:text-base">Découvrez nos infrastructures et réservez le créneau qui vous convient.</p>
  </header>

  <!-- Filtres -->
  <div class="mt-5 sm:mt-8">
    <span class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Filtrer par :</span>
    <div class="flex flex-nowrap sm:flex-wrap items-center gap-3 overflow-x-auto sm:overflow-visible -mx-4 px-4 sm:mx-0 sm:px-0 pb-2 sm:pb-0 scrollbar-hide">
      <a href="?cat=all" class="flex-shrink-0 rounded-full border px-5 py-2 text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap <?= $cat === 'all' ? 'border-primary bg-primary text-white shadow-lg shadow-primary/20' : 'border-slate-200 bg-white text-slate-600 hover:border-primary' ?>">Tous</a>
      <?php foreach ($cats as $c): ?>
        <a href="?cat=<?= e($c['slug']) ?>" class="flex-shrink-0 rounded-full border px-5 py-2 text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap <?= $cat === $c['slug'] ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white' ?>"><?= e($c['nom']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$espaces): ?>
    <div class="mt-20 text-center"><p class="text-slate-400 font-medium italic">Aucun espace disponible.</p></div>
  <?php else: ?>
    <div class="mt-12 grid grid-cols-2 gap-3 sm:gap-8 lg:grid-cols-3 xl:grid-cols-4">
      <?php foreach ($espaces as $e): 
          $urlDetails = "espace.php?slug=" . urlencode($e['slug']);
          // On transforme la chaîne d'images en tableau PHP
          $photos = $e['toutes_photos'] ? explode(',', $e['toutes_photos']) : [];
      ?>
        <!-- On retire le 'a' global car on va mettre des boutons à l'intérieur -->
        <div onclick="window.location='<?= $urlDetails ?>'" class="cursor-pointer group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-2 hover:shadow-2xl relative">
          
          <!-- ZONE SLIDER -->
          <div class="relative aspect-[16/11] overflow-hidden bg-slate-100 js-slider">
            <?php if (!empty($photos)): ?>
                <?php foreach ($photos as $index => $img): ?>
                    <img src="uploads/<?= e($img) ?>" 
                         class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?> js-slide"
                         data-index="<?= $index ?>">
                <?php endforeach; ?>
                
                <!-- Contrôles du slider (uniquement si plusieurs photos) -->
                <?php if (count($photos) > 1): ?>
                    <button onclick="event.stopPropagation(); changeSlide(this, -1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center bg-white/80 rounded-full text-xs hover:bg-white z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button onclick="event.stopPropagation(); changeSlide(this, 1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center bg-white/80 rounded-full text-xs hover:bg-white z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <!-- Indicateur (petit point en bas) -->
                    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1 z-10">
                        <?php foreach ($photos as $index => $img): ?>
                            <div class="w-1.5 h-1.5 rounded-full <?= $index === 0 ? 'bg-white' : 'bg-white/40' ?> js-dot"></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="absolute inset-0 flex items-center justify-center text-5xl text-slate-400 bg-slate-200 opacity-50"><i class="fas fa-landmark"></i></div>
            <?php endif; ?>
            
            <div class="absolute right-1.5 sm:right-4 top-1.5 sm:top-4 z-20 flex flex-col items-end gap-1 sm:gap-1.5">
                <span class="rounded-full bg-white/95 px-1.5 sm:px-3 py-0.5 sm:py-1.5 text-[7px] sm:text-[10px] font-black uppercase tracking-widest text-primary shadow-sm">
                    <?= e($e['categorie']) ?>
                </span>
                <?php if (!empty($e['gerant_externe'])): ?>
                <span title="Cet espace est loué sur une longue durée à un tiers — pas de réservation ponctuelle possible" class="rounded-full bg-amber-500/95 px-1.5 sm:px-3 py-0.5 sm:py-1.5 text-[7px] sm:text-[10px] font-black uppercase tracking-widest text-white shadow-sm">
                    <i class="fas fa-user-tie mr-1"></i>En bail
                </span>
                <?php elseif ($e['mode_reservation'] === 'sejour'): ?>
                <span class="rounded-full bg-primary/95 px-1.5 sm:px-3 py-0.5 sm:py-1.5 text-[7px] sm:text-[10px] font-black uppercase tracking-widest text-white shadow-sm">
                    <i class="fas fa-bed mr-1"></i>Séjour
                </span>
                <?php elseif ((int)$e['nb_tarifs_bail'] > 0): ?>
                <span title="Cet espace peut être loué sur une longue durée, en plus de la réservation ponctuelle" class="rounded-full bg-indigo-600/95 px-1.5 sm:px-3 py-0.5 sm:py-1.5 text-[7px] sm:text-[10px] font-black uppercase tracking-widest text-white shadow-sm">
                    <i class="fas fa-file-signature mr-1"></i>Bail possible
                </span>
                <?php endif; ?>
            </div>
          </div>

          <!-- ZONE INFOS -->
          <div class="flex flex-1 flex-col p-3 sm:p-6">
            <h3 class="text-sm sm:text-xl font-black text-slate-900 uppercase italic tracking-tighter">
                <?= e($e['nom']) ?>
            </h3>
            <p class="mt-1 sm:mt-2 text-[9px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest italic">
                <?= e($e['capacite']) ?>
            </p>
            <?php if (!empty($e['description'])): ?>
            <p class="mt-1 sm:mt-2 text-xs sm:text-sm text-slate-500 leading-snug line-clamp-2 hidden sm:block">
                <?= e($e['description']) ?>
            </p>
            <?php endif; ?>

            <a href="<?= $urlDetails ?>" class="mt-auto pt-3 sm:pt-6 inline-flex w-full items-center justify-center rounded-lg border border-slate-300 px-2 sm:px-4 py-1.5 sm:py-2 text-[9px] sm:text-[10px] font-black uppercase tracking-[0.1em] sm:tracking-[0.15em] transition-all hover:bg-black hover:border-black hover:text-white">
                Voir détails
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- SERVICES & PRESTATIONS -->
  <?php
  $services = $pdo->query("SELECT * FROM services_annexes WHERE actif = 1 ORDER BY nom ASC")->fetchAll();
  if ($services):
  ?>
  <section id="services" class="mt-20 -mx-4 px-4 sm:mx-0 sm:px-10 py-12 sm:py-14 sm:rounded-[3rem] bg-slate-900">
    <header class="max-w-2xl">
      <span class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-accent bg-accent/10 px-3 py-1.5 rounded-full mb-3"><i class="fas fa-concierge-bell"></i> Sur simple demande</span>
      <h2 class="text-2xl sm:text-3xl font-black italic tracking-tighter uppercase text-white">Services & <span class="text-accent">Prestations</span></h2>
      <p class="mt-2 text-slate-400 font-medium text-sm">Prestations annexes du Palais, disponibles sur simple demande — connectez-vous pour envoyer votre demande, elle sera suivie par l'administration.</p>
    </header>
    <?php if ($msgService): ?>
    <div class="mt-6 rounded-2xl p-4 flex items-center gap-3 <?= $msgService[0]==='ok' ? 'bg-green-500/10 border border-green-500/30 text-green-400' : 'bg-red-500/10 border border-red-500/30 text-accent' ?>">
      <i class="fas <?= $msgService[0]==='ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <span class="font-bold text-sm"><?= e($msgService[1]) ?></span>
    </div>
    <?php endif; ?>
    <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-3">
      <?php foreach ($services as $srv): ?>
      <div class="flex flex-col rounded-2xl border-2 border-white/10 hover:border-accent/50 bg-white/5 p-6 hover:bg-white/10 transition-all">
        <h3 class="font-black text-white uppercase italic tracking-tight text-sm"><?= e($srv['nom']) ?></h3>
        <p class="mt-2 text-sm text-slate-400 leading-snug flex-1"><?= e($srv['description']) ?></p>
        <p class="mt-4 font-black text-accent text-lg"><?= number_format((float)$srv['montant'],0,',',' ') ?> <span class="text-xs">FCFA/<?= e($srv['unite']) ?></span></p>

        <button type="button" onclick="document.getElementById('srvForm-<?= $srv['id'] ?>').classList.toggle('hidden')"
                class="mt-4 w-full flex items-center justify-center gap-2 bg-accent text-white text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl hover:bg-white hover:text-primary transition">
          <i class="fas fa-paper-plane"></i> Faire la demande
        </button>

        <form method="POST" action="espaces.php#services" id="srvForm-<?= $srv['id'] ?>" class="hidden mt-3 space-y-2.5">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="demander_service">
          <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
          <textarea name="message" rows="3" required minlength="5" placeholder="Précisez votre besoin (date souhaitée, quantité, contexte...)"
                    class="w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-xs font-medium text-white placeholder-slate-500 outline-none focus:border-accent resize-none"></textarea>
          <div class="flex gap-2">
            <button type="button" onclick="document.getElementById('srvForm-<?= $srv['id'] ?>').classList.add('hidden')"
                    class="flex-shrink-0 bg-white/10 text-white/70 text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl hover:bg-white/20 transition">
              Annuler
            </button>
            <button type="submit" class="flex-1 bg-white text-primary text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl hover:bg-accent hover:text-white transition">
              Envoyer ma demande
            </button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<!-- SCRIPT SLIDER -->
<script>
function changeSlide(btn, direction) {
    const container = btn.closest('.js-slider');
    const slides = container.querySelectorAll('.js-slide');
    const dots = container.querySelectorAll('.js-dot');
    let currentIndex = 0;

    // Trouver l'index actuel
    slides.forEach((s, i) => {
        if(s.classList.contains('opacity-100')) currentIndex = i;
    });

    // Calculer le nouvel index
    let newIndex = currentIndex + direction;
    if(newIndex >= slides.length) newIndex = 0;
    if(newIndex < 0) newIndex = slides.length - 1;

    // Appliquer le changement
    slides.forEach((s, i) => {
        s.classList.replace(i === newIndex ? 'opacity-0' : 'opacity-100', i === newIndex ? 'opacity-100' : 'opacity-0');
    });
    
    dots.forEach((d, i) => {
        d.classList.replace(i === newIndex ? 'bg-white/40' : 'bg-white', i === newIndex ? 'bg-white' : 'bg-white/40');
    });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>