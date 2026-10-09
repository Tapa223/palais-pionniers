<?php
// Page « introuvable » : servie par Apache (ErrorDocument 404) et par les fiches dont l'élément n'existe pas
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
http_response_code(404);

$prefixeForce = rtrim((string)parse_url(url_racine_site(), PHP_URL_PATH), '/') . '/';
$pageTitle = "Page introuvable | Palais des Pionniers";
$pageDescription = "La page demandée n'existe pas ou n'est plus disponible sur le site du Palais des Pionniers.";
$pageRobots = 'noindex, follow';
$page = '404.php';
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-16 sm:py-24 max-w-2xl text-center">
    <p class="text-5xl sm:text-6xl font-black italic tracking-tighter text-accent mb-4">404</p>
    <h1 class="text-2xl sm:text-4xl font-black italic uppercase tracking-tighter text-primary mb-4">Page <span class="text-accent">introuvable</span></h1>
    <p class="text-slate-600 mb-10">La page que vous cherchez n'existe pas, a été déplacée ou n'est plus disponible.</p>
    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="<?= $prefix ?><?= lien_page('index.php') ?>" class="bg-accent text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-accent-dark transition">
            <i class="fas fa-house mr-1"></i> Retour à l'accueil
        </a>
        <a href="<?= $prefix ?><?= lien_page('espaces.php') ?>" class="border-2 border-slate-200 text-primary text-xs font-black uppercase px-6 py-3 rounded-xl hover:border-primary transition">
            Voir les espaces
        </a>
        <a href="<?= $prefix ?><?= lien_page('contact.php') ?>" class="border-2 border-slate-200 text-primary text-xs font-black uppercase px-6 py-3 rounded-xl hover:border-primary transition">
            Nous contacter
        </a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
