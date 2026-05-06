<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Sécurité : Vérification du rôle admin
require_admin('/index.php');

$pdo = db();

// --- GESTION DE LA SUPPRESSION ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // 1. Récupérer le nom de l'image pour la supprimer du dossier physique
        $stmtImg = $pdo->prepare("SELECT image_principale FROM activites WHERE id = ?");
        $stmtImg->execute([$id]);
        $act = $stmtImg->fetch();
        
        if ($act && $act['image_principale'] != 'default-hero.jpg') {
            $path = "../assets/images/activites/" . $act['image_principale'];
            if (file_exists($path)) unlink($path);
        }

        // 2. Suppression en base de données
        $stmt = $pdo->prepare("DELETE FROM activites WHERE id = ?");
        $stmt->execute([$id]);
        
        header('Location: activites.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$activites = $pdo->query("SELECT * FROM activites ORDER BY id DESC")->fetchAll();

$pageTitle = "Gestion des Activités — Admin";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between px-4 sm:px-0">
    <h1 class="text-2xl font-black text-[#0a214a] tracking-tight">Gestion des activités</h1>
    <a href="admin-ajout-activite.php" class="inline-flex items-center rounded-xl bg-[#0a214a] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-900/20 hover:scale-105 transition-all">
        <i class="fas fa-plus mr-2"></i> Ajouter une activité
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="mt-6 rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-sm font-bold text-emerald-800 animate-in fade-in slide-in-from-top-2">
        ✨ L'opération a été effectuée avec succès.
    </div>
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
                                <img src="../assets/images/activites/<?= htmlspecialchars($a['image_principale']) ?>" 
                                     class="h-full w-full object-cover transform group-hover:scale-110 transition-transform duration-500" 
                                     alt="Aperçu">
                            </div>
                        </td>
                        <td class="px-6 py-4 font-bold text-[#0a214a]">
                            <?= htmlspecialchars($a['nom']) ?>
                        </td>
                        <td class="px-6 py-4 text-slate-500 italic text-xs">
                            <?= htmlspecialchars(mb_strimwidth($a['sous_titre'], 0, 60, "...")) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <!-- Galerie -->
                                <a href="admin-galerie-activite.php?id=<?= $a['id'] ?>" 
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:border-blue-500 hover:text-blue-600 hover:shadow-md transition-all" 
                                   title="Galerie photos">
                                    <i class="fas fa-images text-sm"></i>
                                </a>
                                
                                <!-- Modifier -->
                                <a href="admin-ajout-activite.php?id=<?= $a['id'] ?>"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:border-amber-500 hover:text-amber-600 hover:shadow-md transition-all" 
                                   title="Modifier">
                                    <i class="fas fa-pen text-sm"></i>
                                </>

                                <!-- Supprimer -->
                                <a href="activites.php?delete=<?= $a['id'] ?>" 
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette activité ?')"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:border-red-500 hover:text-red-600 hover:shadow-md transition-all" 
                                   title="Supprimer">
                                    <i class="fas fa-trash text-sm"></i>
                                </a>
                            </div>
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