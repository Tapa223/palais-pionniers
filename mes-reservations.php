<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$pdo = db();
$user_id = $_SESSION['user_id'];

// On récupère les réservations de cet utilisateur uniquement
$stmt = $pdo->prepare("
    SELECT r.*, e.nom as espace_nom 
    FROM reservations r 
    JOIN espaces e ON e.id = r.espace_id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll();

$pageTitle = "Mes Réservations — Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto max-w-4xl px-4 py-12">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-2xl font-black text-primary">Mes Réservations</h1>
        <a href="espaces.php" class="text-sm font-bold text-accent">+ Nouvelle demande</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="mb-8 p-6 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4 shadow-sm">
            <div class="h-12 w-12 bg-emerald-500 text-white rounded-full flex items-center justify-center text-xl shrink-0">
                <i class="fas fa-check"></i>
            </div>
            <div>
                <h3 class="font-bold text-emerald-900">Demande envoyée avec succès !</h3>
                <p class="text-sm text-emerald-700">Votre demande est en attente. Un agent va l'étudier et vous contactera.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php if (empty($reservations)): ?>
            <div class="text-center py-20 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                <p class="text-slate-400">Vous n'avez pas encore de réservation.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reservations as $res): ?>
                <div class="bg-white border border-slate-200 p-5 rounded-2xl flex flex-wrap justify-between items-center gap-4 hover:shadow-md transition">
                    <div>
                        <h3 class="font-bold text-slate-800"><?= e($res['espace_nom']) ?></h3>
                        <p class="text-xs text-slate-500">
                            <i class="far fa-calendar-alt mr-1"></i> <?= date('d/m/Y', strtotime($res['date_resa'])) ?>
                        </p>
                    </div>

                    <div>
                        <?php 
                        $statusStyles = [
                            'validee' => 'bg-emerald-100 text-emerald-700',
                            'en_attente' => 'bg-amber-100 text-amber-700',
                            'refusee' => 'bg-red-100 text-red-700'
                        ];
                        $label = $res['statut'] === 'validee' ? 'Confirmé' : ($res['statut'] === 'refusee' ? 'Refusé' : 'En attente');
                        ?>
                        <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest <?= $statusStyles[$res['statut']] ?? 'bg-slate-100' ?>">
                            <?= $label ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>