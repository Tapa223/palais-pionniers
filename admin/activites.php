<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['ministre','admin_activites']);

$pdo = db();
$readonly = is_readonly_admin();

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $error = "Requête invalide.";
    } else {
    $id = (int)($_POST['id'] ?? 0);

    try {
        $stmtImg = $pdo->prepare("SELECT image_principale FROM activites WHERE id = ?");
        $stmtImg->execute([$id]);
        $act = $stmtImg->fetch();
        
        if ($act && $act['image_principale'] !== '' && $act['image_principale'] != 'default-hero.jpg') {
            $path = __DIR__ . "/../assets/images/activites/" . basename($act['image_principale']);
            if (file_exists($path)) unlink($path);
        }

        $stmt = $pdo->prepare("DELETE FROM activites WHERE id = ?"); log_activity('activite_supprimee','activites','Activité ID '.($id??0).' supprimée');
        $stmt->execute([$id]);
        
        header('Location: activites.php?success=1');
        exit;
    } catch (Exception $e) {
        error_log('activites suppression : ' . $e->getMessage());
        $error = "La suppression a échoué.";
    }
    }
}

$activites = $pdo->query("SELECT * FROM activites ORDER BY id DESC")->fetchAll();

$pageTitle = "Gestion des Activités — Admin";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between px-4 sm:px-0">
    <h1 class="text-2xl font-black text-[#0a214a] tracking-tight">Gestion des activités</h1>
    <?php if (!$readonly): ?>
    <a href="admin-ajout-activite.php" class="inline-flex items-center rounded-xl bg-[#0a214a] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-900/20 hover:scale-105 transition-all">
        <i class="fas fa-plus mr-2"></i> Ajouter une activité
    </a>
    <?php else: ?>
    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</span>
    <?php endif; ?>
</div>

<?php if (($_GET['success'] ?? '') === 'partiel'): ?>
    <div class="mt-6 rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm font-bold text-amber-800">
        L'activité est enregistrée, mais certaines photos ou certains liens ont été refusés (images JPG, PNG ou WebP de 5 Mo maximum, liens commençant par http:// ou https://).
    </div>
<?php elseif (isset($_GET['success'])): ?>
    <div class="mt-6 rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-sm font-bold text-emerald-800 animate-in fade-in slide-in-from-top-2">
        L'opération a été effectuée avec succès.
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="mt-6 rounded-xl bg-red-50 border border-red-100 p-4 text-sm font-bold text-red-800"><?= e($error) ?></div>
<?php endif; ?>

<div class="mt-8 rounded-[2rem] border border-slate-100 bg-white shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100">
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Aperçu</th>
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Nom de l'activité</th>
                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-400">Sous-titre</th>
                    <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($activites as $a): ?>
                    <tr class="group hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="h-14 w-24 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm">
                                <?php if ($a['image_principale'] !== '' && $a['image_principale'] !== 'default-hero.jpg'): ?>
                                <img src="../assets/images/activites/<?= e($a['image_principale']) ?>"
                                     class="h-full w-full object-cover transform group-hover:scale-110 transition-transform duration-500"
                                     alt="Aperçu">
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-bold text-[#0a214a]">
                            <?= htmlspecialchars($a['nom']) ?>
                        </td>
                        <td class="px-6 py-4 text-slate-500 italic text-xs">
                            <?= htmlspecialchars(mb_strimwidth($a['sous_titre'], 0, 60, "...")) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php if (!$readonly): ?>
                            <div class="flex justify-end gap-2">
                                <a href="admin-galerie-activite.php?id=<?= $a['id'] ?>" 
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:border-blue-500 hover:text-blue-600 hover:shadow-md transition-all" 
                                   title="Galerie photos">
                                    <i class="fas fa-images text-sm"></i>
                                </a>
                                
                                <a href="admin-ajout-activite.php?id=<?= $a['id'] ?>"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:border-amber-500 hover:text-amber-600 hover:shadow-md transition-all" 
                                   title="Modifier">
                                    <i class="fas fa-pen text-sm"></i>
                                </a>

                                <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette activité ?')">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <button type="submit"
                                       class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:border-red-500 hover:text-red-600 hover:shadow-md transition-all" 
                                       title="Supprimer">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div class="text-slate-300"><i class="fas fa-eye text-sm"></i></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($activites)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="h-16 w-16 rounded-full bg-slate-50 flex items-center justify-center mb-4">
                                    <i class="fas fa-folder-open text-slate-300 text-2xl"></i>
                                </div>
                                <p class="text-slate-400 font-medium">Aucune activité enregistrée pour le moment.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>