<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = db();
$pageTitle = "Politique de confidentialité — Palais des Pionniers";
$pageDescription = "Politique de confidentialité et de protection des données personnelles du Palais des Pionniers.";
$page = 'politique-confidentialite.php';
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-10 sm:py-16 max-w-3xl">
    <a href="javascript:history.back()" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary hover:text-accent transition bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full mb-6">
        <i class="fas fa-arrow-left"></i> Retour
    </a>

    <h1 class="text-2xl sm:text-4xl font-black italic uppercase tracking-tighter text-primary mb-8">Politique de <span class="text-accent">confidentialité</span></h1>

    <div class="prose prose-sm sm:prose-base max-w-none space-y-6 text-slate-600">
        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Données collectées</h2>
            <p>Dans le cadre de l'utilisation de ce site (création de compte, réservation d'un espace, demande de bail, inscription "S'engager", formulaire de contact), nous collectons les données suivantes : nom, prénom, téléphone, email, et selon le formulaire, commune, profession ou motivation.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Utilisation des données</h2>
            <p>Ces données sont utilisées exclusivement pour :</p>
            <ul class="list-disc pl-5 space-y-1">
                <li>Gérer votre compte et vos réservations</li>
                <li>Vous recontacter dans le cadre d'une demande (bail, service, engagement)</li>
                <li>Assurer le suivi administratif et comptable des prestations du Palais</li>
            </ul>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Conservation des données</h2>
            <p>Vos données sont conservées le temps nécessaire à la gestion de votre compte et de vos demandes, conformément aux obligations légales applicables aux établissements publics maliens.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Partage des données</h2>
            <p>Vos données ne sont jamais vendues ni transmises à des tiers à des fins commerciales. Elles sont accessibles uniquement au personnel habilité du Palais des Pionniers, dans le cadre de leurs fonctions.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Vos droits</h2>
            <p>Vous pouvez à tout moment demander la consultation, la correction ou la suppression de vos données personnelles en nous contactant via notre <a href="contact.php" class="text-accent font-bold hover:underline">formulaire de contact</a>.</p>
        </section>

        <section>
            <h2 class="text-sm font-black uppercase text-primary mb-2">Cookies</h2>
            <p>Ce site utilise uniquement des cookies techniques nécessaires au bon fonctionnement de la connexion à votre compte (session). Aucun cookie publicitaire ou de suivi tiers n'est utilisé.</p>
        </section>

        <p class="text-xs text-slate-400 pt-4">Voir aussi nos <a href="mentions-legales.php" class="text-accent font-bold hover:underline">Mentions légales</a>.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
