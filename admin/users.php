<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin('/index.php');

$pdo = db();
$msg = null;

// --- TRAITEMENT DES ACTIONS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $msg = ['error', 'Jeton CSRF invalide.'];
    } else {
        $id     = (int)$_POST['id'];
        $action = $_POST['action'] ?? '';
        
        // Empêcher l'auto-modification pour ne pas se bloquer soi-même
        if ($id && $id !== current_user()['id']) {
            if ($action === 'toggle_actif') {
                // On inverse la valeur de 'actif' (1 devient 0, 0 devient 1)
                $pdo->prepare("UPDATE users SET actif = 1 - actif WHERE id = :i")->execute([':i' => $id]);
                $msg = ['ok', 'Le statut du compte a été mis à jour.'];
            } elseif ($action === 'set_role') {
                $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
                $pdo->prepare("UPDATE users SET role = :r WHERE id = :i")->execute([':r' => $role, ':i' => $id]);
                $msg = ['ok', 'Le rôle de l\'utilisateur a été modifié.'];
            }
        } else {
            $msg = ['error', 'Action impossible sur votre propre compte admin.'];
        }
    }
}

// --- RÉCUPÉRATION DES UTILISATEURS ---
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = "Gestion Utilisateurs";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-6 py-8">
    <div class="mb-8">
        <h1 class="text-2xl font-black text-slate-800">Gestion des Utilisateurs</h1>
        <p class="text-sm text-slate-500 mt-1">Contrôlez les accès, les numéros et les rôles</p>
    </div>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl border <?= $msg[0] === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
            <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= e($msg[1]) ?>
        </div>
    <?php endif; ?>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-xs font-black uppercase text-slate-400 tracking-wider">
                    <th class="px-6 py-4">Utilisateur & Contact</th>
                    <th class="px-6 py-4 text-center">Rôle</th>
                    <th class="px-6 py-4 text-center">Statut</th>
                    <th class="px-6 py-4">Date d'inscription</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    
                    <!-- NOM, EMAIL & TÉLÉPHONE (Conforme image_c6bb53.png) -->
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-900"><?= e($u['nom_complet']) ?></div>
                        <div class="text-xs text-slate-400"><?= e($u['email']) ?></div>
                        <div class="text-sm text-primary font-bold mt-1">
                            <?= e($u['telephone'] ?? 'NON RENSEIGNÉ') ?>
                        </div>
                    </td>

                    <!-- RÔLE -->
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex px-2.5 py-1 rounded-md text-[10px] font-black uppercase border <?= $u['role'] === 'admin' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
                            <?= e($u['role']) ?>
                        </span>
                    </td>

                    <!-- STATUT ACTIF / BLOQUÉ -->
                    <td class="px-6 py-4 text-center">
                        <?php if ($u['actif']): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> ACTIF
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-red-50 text-red-700 border border-red-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 text-red-500"></span> BLOQUÉ
                            </span>
                        <?php endif; ?>
                    </td>

                    <!-- DATE -->
                    <td class="px-6 py-4 text-sm text-slate-500">
                        <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                    </td>

                    <!-- ACTIONS (Boutons avec texte) -->
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <!-- Toggle Bloquer / Activer -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_actif">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                
                                <?php if ($u['actif']): ?>
                                    <button class="flex items-center gap-2 bg-red-50 text-red-600 px-3 py-1.5 rounded-xl hover:bg-red-600 hover:text-white transition-all text-[10px] font-black uppercase tracking-wider border border-red-100" onclick="return confirm('Voulez-vous vraiment bloquer cet utilisateur ?')">
                                        <i class="fas fa-user-slash"></i> Bloquer
                                    </button>
                                <?php else: ?>
                                    <button class="flex items-center gap-2 bg-emerald-50 text-emerald-600 px-3 py-1.5 rounded-xl hover:bg-emerald-600 hover:text-white transition-all text-[10px] font-black uppercase tracking-wider border border-emerald-100">
                                        <i class="fas fa-user-check"></i> Débloquer
                                    </button>
                                <?php endif; ?>
                            </form>

                            <!-- Toggle Rôle -->
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                
                                <button class="flex items-center gap-2 bg-slate-50 text-slate-600 px-3 py-1.5 rounded-xl hover:bg-slate-800 hover:text-white transition-all text-[10px] font-black uppercase tracking-wider border border-slate-200">
                                    <i class="fas fa-shield-alt"></i> 
                                    <span><?= $u['role'] === 'admin' ? 'Rétrograder' : 'Promouvoir' ?></span>
                                </button>
                            </form>
                        </div>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>