<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$questions = [];
if (faq_disponible($pdo)) {
    $questions = $pdo->query("SELECT question, reponse, categorie FROM faq WHERE actif = 1 ORDER BY (SELECT MIN(f2.ordre) FROM faq f2 WHERE f2.categorie <=> faq.categorie AND f2.actif = 1), COALESCE(categorie, 'zzz'), ordre, id")->fetchAll();
}
$parCategorie = [];
foreach ($questions as $q) {
    $parCategorie[$q['categorie'] ?: 'Autres questions'][] = $q;
}

$pageTitle = "Questions fréquentes | Palais des Pionniers";
$pageDescription = "Réponses aux questions fréquentes sur la réservation des espaces, le paiement, l'annulation, la location longue durée et le compte client du Palais des Pionniers.";
$page = 'faq.php';
require __DIR__ . '/includes/header.php';
?>

<section class="relative overflow-hidden bg-primary text-white">
    <div class="absolute inset-0 opacity-20" style="background: url('assets/images/porte.jpeg') center/cover no-repeat;"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-primary/90 via-primary/30 to-transparent"></div>
    <div class="container relative mx-auto px-4 py-10 sm:py-16">
        <h2 class="text-accent font-black tracking-[0.2em] sm:tracking-[0.3em] uppercase text-[9px] sm:text-xs mb-2 sm:mb-4">Vous avez une question ?</h2>
        <h1 class="text-2xl sm:text-4xl md:text-5xl font-black italic tracking-tighter uppercase mb-2 sm:mb-4">Questions <span class="text-accent">fréquentes</span></h1>
        <p class="max-w-2xl text-xs sm:text-base text-slate-200 font-medium leading-relaxed">
            Réservation, paiement, annulation, location longue durée : retrouvez ici l'essentiel sur le fonctionnement du Palais des Pionniers.
        </p>
    </div>
</section>

<div class="container mx-auto px-4 py-10 sm:py-16 max-w-5xl space-y-10">

    <?php require __DIR__ . '/includes/tutoriel-video.php'; ?>

    <section aria-labelledby="titreFaq">
        <h2 id="titreFaq" class="text-xl sm:text-3xl font-black italic uppercase tracking-tighter text-primary mb-6">Vos questions, nos réponses</h2>

        <?php if (!$parCategorie): ?>
        <p class="text-sm text-slate-500">Les questions fréquentes seront bientôt disponibles. En attendant, n'hésitez pas à <a href="<?= lien_page('contact.php') ?>" class="font-bold text-accent hover:underline">nous écrire</a>.</p>
        <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($parCategorie as $categorie => $liste): ?>
            <div>
                <h3 class="text-[11px] font-black uppercase tracking-widest text-accent mb-3"><?= e($categorie) ?></h3>
                <div class="space-y-3">
                    <?php foreach ($liste as $q): ?>
                    <details class="faq-item bg-white rounded-2xl border border-slate-100 shadow-sm transition">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer px-5 py-4 font-black text-primary text-sm sm:text-base">
                            <span><?= e($q['question']) ?></span>
                            <i class="faq-icone fas fa-plus text-accent text-xs transition flex-shrink-0"></i>
                        </summary>
                        <div class="px-5 pb-5 text-sm text-slate-600 leading-relaxed"><?= nl2br(e($q['reponse'])) ?></div>
                    </details>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl bg-slate-900 text-white p-6 sm:p-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
        <div>
            <p class="font-black uppercase italic text-lg sm:text-2xl tracking-tight">Vous ne trouvez pas votre réponse ?</p>
            <p class="text-sm text-white/70 mt-1">Notre équipe vous répond sous 24 heures, du lundi au samedi.</p>
        </div>
        <a href="<?= lien_page('contact.php') ?>" class="bg-accent text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-accent-dark transition whitespace-nowrap">Contactez-nous</a>
    </section>
</div>

<style>
.faq-item > summary { list-style: none; }
.faq-item > summary::-webkit-details-marker { display: none; }
.faq-item[open] { box-shadow: 0 10px 25px -10px rgba(10, 37, 88, .18); }
.faq-item[open] .faq-icone { transform: rotate(45deg); }
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
