<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM formations WHERE id = ? AND actif = 1");
$stmt->execute([$id]);
$f = $stmt->fetch();

if (!$f) { header('Location: formations.php'); exit; }

$estTerminee = !empty($f['date_fin']) && strtotime($f['date_fin']) < strtotime('today');

// Carrousel : photo principale + galerie complémentaire de cette formation
$galerie = $pdo->prepare("SELECT * FROM formation_galerie WHERE formation_id = ? ORDER BY ordre ASC, id ASC");
$galerie->execute([$id]);
$galerie = $galerie->fetchAll();

$slidesFormation = [];
if ($f['photo']) $slidesFormation[] = 'assets/images/formations/' . $f['photo'];
foreach ($galerie as $g) $slidesFormation[] = 'assets/images/formations/' . $g['image_path'];
$slidesFormation = array_values(array_unique(array_filter($slidesFormation, fn($p) => file_exists(__DIR__ . '/' . $p))));

$pageTitle = $f['nom'] . " — Palais des Pionniers";
$page = 'formations.php';
require __DIR__ . '/includes/header.php';
?>

<section class="py-10 sm:py-14 bg-slate-50 border-b border-slate-100">
    <div class="container mx-auto px-4">
        <a href="formations.php" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary hover:text-accent transition bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full">
            <i class="fas fa-arrow-left"></i> Retour aux formations
        </a>
    </div>
</section>

<section class="py-12 sm:py-20 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 max-w-5xl mx-auto items-start">
            <div>
                <div class="aspect-[4/3] rounded-[2.5rem] overflow-hidden bg-slate-100 shadow-xl relative">
                    <?php if ($slidesFormation): ?>
                        <?php foreach ($slidesFormation as $index => $src): ?>
                        <img src="<?= e($src) ?>" data-slide-formation="<?= $index ?>"
                             class="slide-formation absolute inset-0 w-full h-full object-cover transition-opacity duration-700 ease-in-out <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?> <?= $estTerminee ? 'grayscale' : '' ?>">
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-8xl text-slate-300"><i class="fas fa-graduation-cap"></i></div>
                    <?php endif; ?>
                    <?php if ($estTerminee): ?>
                    <span class="absolute top-4 left-4 z-10 bg-slate-800 text-white text-xs font-black uppercase px-4 py-2 rounded-full"><i class="fas fa-flag-checkered mr-1"></i>Terminée</span>
                    <?php endif; ?>
                </div>
                <?php if (count($slidesFormation) > 1): ?>
                <div class="flex gap-2 mt-4 overflow-x-auto pb-1 scrollbar-hide">
                    <?php foreach ($slidesFormation as $index => $src): ?>
                    <button onclick="goToFormationSlide(<?= $index ?>)" data-thumb-formation="<?= $index ?>"
                            class="flex-shrink-0 w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden border-2 <?= $index === 0 ? 'border-accent' : 'border-transparent opacity-60' ?> transition-all">
                        <img src="<?= e($src) ?>" class="w-full h-full object-cover">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="text-3xl sm:text-5xl font-black text-primary uppercase italic tracking-tighter leading-none">
                    <?= e($f['nom']) ?>
                </h1>
                <div class="flex flex-wrap gap-2 mt-5">
                    <?php if ($f['duree']): ?>
                    <span class="text-xs font-black text-primary bg-slate-50 border border-slate-200 rounded-full px-4 py-2"><i class="fas fa-clock mr-1"></i><?= e($f['duree']) ?></span>
                    <?php endif; ?>
                    <?php if ($f['public_cible']): ?>
                    <span class="text-xs font-black text-primary bg-slate-50 border border-slate-200 rounded-full px-4 py-2"><i class="fas fa-users mr-1"></i><?= e($f['public_cible']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="mt-8 pt-8 border-t border-slate-100">
                    <?php if ($f['description']): ?>
                    <div class="text-slate-700 leading-loose whitespace-pre-line text-base sm:text-lg">
                        <?= nl2br(e($f['description'])) ?>
                    </div>
                    <?php else: ?>
                    <p class="text-slate-400 italic">Aucune description détaillée pour l'instant.</p>
                    <?php endif; ?>
                </div>

                <?php if ($estTerminee): ?>
                <div class="mt-8 bg-slate-50 border border-slate-200 rounded-2xl p-5">
                    <p class="text-sm text-slate-500 font-semibold"><i class="fas fa-info-circle text-slate-400 mr-2"></i>Cette session s'est déroulée au Palais des Pionniers. D'autres sessions de cette formation pourront être organisées — contactez l'administration pour être informé(e).</p>
                </div>
                <?php elseif ($f['contact']): ?>
                <div class="mt-8 bg-accent/5 border border-accent/20 rounded-2xl p-5">
                    <p class="text-xs font-black text-accent uppercase tracking-widest mb-1"><i class="fas fa-paper-plane mr-2"></i>Pour s'inscrire</p>
                    <p class="text-sm text-slate-700 font-semibold"><?= e($f['contact']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if (count($slidesFormation) > 1): ?>
<script>
function goToFormationSlide(index) {
    document.querySelectorAll('.slide-formation').forEach(s => s.classList.replace('opacity-100', 'opacity-0'));
    document.querySelector(`[data-slide-formation="${index}"]`).classList.replace('opacity-0', 'opacity-100');
    document.querySelectorAll('[data-thumb-formation]').forEach(t => {
        t.classList.remove('border-accent'); t.classList.add('border-transparent', 'opacity-60');
    });
    const activeThumb = document.querySelector(`[data-thumb-formation="${index}"]`);
    activeThumb.classList.remove('border-transparent', 'opacity-60');
    activeThumb.classList.add('border-accent');
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
