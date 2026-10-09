<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = db();
$pageTitle = "Mentions légales | Palais des Pionniers";
$pageDescription = "Mentions légales du site officiel du Palais des Pionniers de Magnambougou/Dianéguéla.";
$page = 'mentions-legales.php';
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-10 sm:py-16 max-w-3xl">
    <a href="javascript:history.back()" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary hover:text-accent transition bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full mb-6">
        <i class="fas fa-arrow-left"></i> Retour
    </a>

    <h1 class="text-2xl sm:text-4xl font-black italic uppercase tracking-tighter text-primary mb-8">Mentions <span class="text-accent">légales</span></h1>

    <div class="prose prose-sm sm:prose-base max-w-none space-y-6 text-slate-600">
        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Éditeur du site</h2>
            <p>Le présent site est édité par le <strong>Palais des Pionniers</strong>, établissement public à caractère scientifique et technologique (EPST), créé par la Loi n°2022-022 du 28 juin 2022, placé sous la tutelle du Ministère de la Jeunesse et des Sports de la République du Mali.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Hébergement</h2>
            <p>Les informations relatives à l'hébergement du site sont disponibles sur demande auprès de l'administration du Palais des Pionniers.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Propriété intellectuelle</h2>
            <p>L'ensemble des contenus présents sur ce site (textes, images, logos, mise en page) est la propriété du Palais des Pionniers, sauf mention contraire. Toute reproduction sans autorisation préalable est interdite.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Responsabilité</h2>
            <p>Le Palais des Pionniers s'efforce d'assurer l'exactitude des informations diffusées sur ce site, sans toutefois garantir qu'elles soient exemptes d'erreurs. L'institution ne saurait être tenue responsable des dommages directs ou indirects résultant de l'utilisation de ce site.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Contact</h2>
            <p>Pour toute question relative à ces mentions légales, vous pouvez nous contacter via notre <a href="<?= lien_page('contact.php') ?>" class="text-accent font-bold hover:underline">formulaire de contact</a>.</p>
        </section>

        <p class="text-xs text-slate-400 pt-4">Voir aussi notre <a href="<?= lien_page('politique-confidentialite.php') ?>" class="text-accent font-bold hover:underline">Politique de confidentialité</a>.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
