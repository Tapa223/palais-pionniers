<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Protection : redirection si non connecté
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$u = $_SESSION; 
$pdo = db();
$user_id = $u['user_id'];

// 1. Récupération des réservations avec le nom de l'espace
$stmt = $pdo->prepare("
    SELECT r.*, e.nom AS espace_nom
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    WHERE r.user_id = ?
    ORDER BY r.date_resa ASC, r.created_at DESC
");
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll();

// 2. Calcul des stats
$stats = [
    'total' => count($reservations),
    'en_attente' => 0,
    'validee' => 0
];
foreach($reservations as $res) {
    if($res['statut'] == 'en_attente') $stats['en_attente']++;
    if($res['statut'] == 'validee') $stats['validee']++;
}

// Configuration des badges (centralisée)
$badgeConfig = [
    'en_attente' => ['label' => 'En attente', 'css' => 'bg-amber-100 text-amber-700 border-amber-200'],
    'validee'    => ['label' => 'Confirmée',  'css' => 'bg-emerald-100 text-emerald-700 border-emerald-200'],
    'refusee'    => ['label' => 'Refusée',    'css' => 'bg-red-100 text-red-700 border-red-200'],
    'annulee'    => ['label' => 'Annulée',    'css' => 'bg-slate-100 text-slate-500 border-slate-200'],
];

$pageTitle = "Mon Tableau de Bord — Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50 min-h-screen pb-20">
    <!-- HEADER -->
    <div class="bg-white border-b border-slate-200 pt-12 pb-16">
        <div class="container mx-auto px-4">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-black text-primary">Bonjour, <?= e($u['nom_complet'] ?? 'Pionnier') ?> 👋</h1>
                    <p class="text-slate-500 mt-1">Gérez vos réservations et téléchargez vos bons de paiement.</p>
                </div>
                <a href="espaces.php" class="rounded-xl bg-accent px-6 py-3 text-sm font-bold text-white shadow-lg shadow-accent/20 hover:scale-105 transition">
                    + Nouvelle réservation
                </a>
            </div>

            <!-- STATS CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-10 -mb-24">
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Demandes</p>
                    <p class="text-3xl font-black text-primary mt-1"><?= $stats['total'] ?></p>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-amber-400">
                    <p class="text-[10px] font-bold text-amber-500 uppercase tracking-widest">En étude</p>
                    <p class="text-3xl font-black text-amber-600 mt-1"><?= $stats['en_attente'] ?></p>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-emerald-500">
                    <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">À régler au guichet</p>
                    <p class="text-3xl font-black text-emerald-600 mt-1"><?= $stats['validee'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 mt-32">
        
        <?php if (isset($_GET['success'])): ?>
            <div class="mb-8 p-5 bg-emerald-600 text-white rounded-2xl shadow-xl flex items-center justify-between animate-pulse">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🎉</span>
                    <div>
                        <p class="font-bold">Demande envoyée !</p>
                        <p class="text-xs opacity-90">L'administration va vérifier la disponibilité de l'espace.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <h2 class="text-xl font-black text-slate-800 mb-6 flex items-center gap-2">
            <span class="h-8 w-1 bg-primary rounded-full"></span>
            Mes événements à venir
        </h2>

        <div class="space-y-4">
            <?php if (empty($reservations)): ?>
                <div class="text-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200">
                    <p class="text-slate-400 font-medium">Aucune réservation pour le moment.</p>
                    <a href="espaces.php" class="text-accent font-bold hover:underline mt-2 inline-block">Consulter le catalogue des espaces</a>
                </div>
            <?php else: ?>
                <?php foreach ($reservations as $res): 
                    $conf = $badgeConfig[$res['statut']] ?? $badgeConfig['annulee'];
                ?>
                    <div class="group bg-white border border-slate-200 p-6 rounded-2xl flex flex-wrap justify-between items-center gap-6 hover:border-primary hover:shadow-2xl hover:shadow-primary/5 transition-all duration-300">
                        
                        <div class="flex items-center gap-5">
                            <div class="h-14 w-14 bg-slate-50 rounded-2xl flex items-center justify-center text-primary border border-slate-100 group-hover:bg-primary group-hover:text-white transition-colors duration-500">
                                <span class="text-xl font-black"><?= strtoupper(substr($res['espace_nom'], 0, 1)) ?></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-lg leading-tight"><?= e($res['espace_nom']) ?></h3>
                                <div class="flex items-center gap-4 text-xs font-bold text-slate-500 mt-2">
                                    <span class="flex items-center gap-1 bg-slate-100 px-2 py-1 rounded">📅 <?= date('d/m/Y', strtotime($res['date_resa'])) ?></span>
                                    <span class="text-slate-300">•</span>
                                    <span class="uppercase tracking-tighter italic">ID #<?= $res['id'] ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 ml-auto md:ml-0">
                            <!-- Statut Badge -->
                            <span class="px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border <?= $conf['css'] ?>">
                                <?= $conf['label'] ?>
                            </span>

                            <!-- Action : Téléchargement du Bon -->
                            <?php if ($res['statut'] === 'validee'): ?>
                                <a href="generer_bon.php?id=<?= $res['id'] ?>" target="_blank" 
                                   class="flex items-center gap-2 bg-primary hover:bg-black text-white px-5 py-2.5 rounded-xl text-xs font-black transition-all transform hover:-translate-y-1 shadow-lg shadow-primary/20">
                                    📄 TÉLÉCHARGER LE BON
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>