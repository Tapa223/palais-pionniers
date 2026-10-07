<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, e.nom AS espace_nom, e.slug AS espace_slug FROM personnalites p
    LEFT JOIN espaces e ON e.id = p.espace_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) { header('Location: personnalites.php'); exit; }

$nomComplet = trim(($p['prenom'] ?? '') . ' ' . $p['nom']);
$pageTitle = $nomComplet . " | Palais des Pionniers";
$page = 'personnalites.php';
require __DIR__ . '/includes/header.php';
?>

<section class="py-5 sm:py-14 bg-slate-50 border-b border-slate-100">
    <div class="container mx-auto px-4">
        <a href="personnalites.php" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary hover:text-accent transition bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full">
            <i class="fas fa-arrow-left"></i> Retour aux icônes
        </a>
    </div>
</section>

<section class="py-6 sm:py-20 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-3 gap-5 lg:gap-16 max-w-5xl mx-auto">
            <div class="lg:col-span-1">
                <div class="aspect-[4/3] sm:aspect-[3/4] rounded-[2.5rem] overflow-hidden bg-slate-100 shadow-xl sm:sticky sm:top-24">
                    <?php if ($p['photo'] && file_exists(__DIR__ . '/assets/images/personnalites/' . $p['photo'])): ?>
                    <img src="assets/images/personnalites/<?= e($p['photo']) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-8xl text-slate-300"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lg:col-span-2">
                <h1 class="text-3xl sm:text-5xl font-black text-primary uppercase italic tracking-tighter leading-none">
                    <?= e($nomComplet) ?>
                </h1>
                <?php if ($p['titre']): ?>
                <p class="text-sm font-bold text-accent uppercase tracking-widest mt-3"><?= e($p['titre']) ?></p>
                <?php endif; ?>
                <?php if ($p['espace_nom']): ?>
                <a href="espace.php?slug=<?= e($p['espace_slug']) ?>" class="inline-flex items-center gap-2 mt-5 text-xs font-black text-primary bg-slate-50 border border-slate-200 rounded-full px-4 py-2 hover:border-primary transition">
                    <i class="fas fa-building"></i> Espace : <?= e($p['espace_nom']) ?>
                </a>
                <?php endif; ?>

                <div class="mt-8 pt-8 border-t border-slate-100">
                    <?php if ($p['parcours']): ?>
                    <div class="max-w-none text-slate-700 leading-loose whitespace-pre-line text-base sm:text-lg">
                        <?= nl2br(e($p['parcours'])) ?>
                    </div>
                    <?php else: ?>
                    <p class="text-slate-400 italic">Aucune biographie renseignée pour l'instant.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
