<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$cat = $_GET['cat'] ?? 'all';

// 1. Modification SQL : On utilise GROUP_CONCAT pour récupérer toutes les images séparées par une virgule
$sql = "
  SELECT e.id, e.slug, e.nom, e.capacite, e.description, c.nom AS categorie, c.slug AS cat_slug,
  (SELECT GROUP_CONCAT(chemin ORDER BY id ASC) FROM espace_images WHERE espace_id = e.id) as toutes_photos
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

<div class="container mx-auto px-4 py-12 md:px-6 md:py-16">
  <header class="max-w-2xl">
    <h1 class="text-4xl font-black italic tracking-tighter uppercase">Nos <span class="text-accent">espaces</span></h1>
    <p class="mt-2 text-slate-500 font-medium">Découvrez nos infrastructures et réservez le créneau qui vous convient.</p>
  </header>

  <!-- Filtres -->
  <div class="mt-10 flex flex-wrap items-center gap-3">
    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mr-2">Filtrer par :</span>
    <a href="?cat=all" class="rounded-full border px-5 py-2 text-xs font-black uppercase tracking-widest transition-all <?= $cat === 'all' ? 'border-primary bg-primary text-white shadow-lg shadow-primary/20' : 'border-slate-200 bg-white text-slate-600 hover:border-primary' ?>">Tous</a>
    <?php foreach ($cats as $c): ?>
      <a href="?cat=<?= e($c['slug']) ?>" class="rounded-full border px-5 py-2 text-xs font-black uppercase tracking-widest transition-all <?= $cat === $c['slug'] ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white' ?>"><?= e($c['nom']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$espaces): ?>
    <div class="mt-20 text-center"><p class="text-slate-400 font-medium italic">Aucun espace disponible.</p></div>
  <?php else: ?>
    <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      <?php foreach ($espaces as $e): 
          $urlDetails = "espace.php?slug=" . urlencode($e['slug']);
          // On transforme la chaîne d'images en tableau PHP
          $photos = $e['toutes_photos'] ? explode(',', $e['toutes_photos']) : [];
      ?>
        <!-- On retire le 'a' global car on va mettre des boutons à l'intérieur -->
        <div class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-2 hover:shadow-2xl relative">
          
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
                    <button onclick="changeSlide(this, -1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center bg-white/80 rounded-full text-xs hover:bg-white z-10 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button onclick="changeSlide(this, 1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center bg-white/80 rounded-full text-xs hover:bg-white z-10 opacity-0 group-hover:opacity-100 transition-opacity">
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
                <div class="absolute inset-0 flex items-center justify-center text-5xl bg-slate-200 opacity-50">🏛️</div>
            <?php endif; ?>
            
            <span class="absolute right-4 top-4 rounded-full bg-white/95 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-primary shadow-sm z-20">
                <?= e($e['categorie']) ?>
            </span>
          </div>

          <!-- ZONE INFOS -->
          <div class="flex flex-1 flex-col p-6">
            <h3 class="text-xl font-black text-slate-900 uppercase italic tracking-tighter">
                <?= e($e['nom']) ?>
            </h3>
            <p class="mt-2 text-xs font-bold text-slate-400 uppercase tracking-widest italic">
                <?= e($e['capacite']) ?> places disponibles
            </p>
            
            <a href="<?= $urlDetails ?>" class="mt-6 inline-flex w-full items-center justify-center rounded-xl border-2 border-slate-900 px-4 py-3 text-[10px] font-black uppercase tracking-[0.2em] transition-all hover:bg-black hover:text-white">
                Voir détails
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
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