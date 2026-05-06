<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Vérification des droits admin
require_admin('/index.php');

$pdo = db();
$msg = null;

// TRAITEMENT DES FORMULAIRES (SANS JETONS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // AJOUT D'UN NOUVEAU TARIF
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO tarifs (espace_id, libelle, montant, unite) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            (int)$_POST['espace_id'], 
            trim($_POST['libelle']), 
            (int)$_POST['montant'], 
            $_POST['unite'] ?: 'jour'
        ]);
        $msg = ['ok', 'Nouveau tarif enregistré avec succès.'];
    } 
    // MISE À JOUR D'UN TARIF EXISTANT
    elseif ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE tarifs SET libelle = ?, montant = ?, unite = ? WHERE id = ?");
        $stmt->execute([
            $_POST['libelle'], 
            $_POST['montant'], 
            $_POST['unite'], 
            (int)$_POST['id']
        ]);
        $msg = ['ok', 'Le tarif a été mis à jour.'];
    } 
    // SUPPRESSION D'UN TARIF
    elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM tarifs WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $msg = ['ok', 'Le tarif a été supprimé.'];
    }
}

// RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
$espaces = $pdo->query("SELECT id, nom FROM espaces ORDER BY nom")->fetchAll();
$tarifs  = $pdo->query("SELECT t.*, e.nom AS espace_nom FROM tarifs t JOIN espaces e ON e.id = t.espace_id ORDER BY e.nom, t.id")->fetchAll();

require __DIR__ . '/_admin_header.php'; 
?>

<div class="space-y-8">
    <!-- Entête de page -->
    <div>
        <h1 class="text-3xl font-black text-slate-800 uppercase italic">Configuration des Tarifs</h1>
        <p class="text-slate-500">Définissez les prix de location pour chaque espace du Palais.</p>
    </div>

    <!-- Message de confirmation -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 animate-fade-in">
            <i class="fas fa-check-circle mr-2"></i> <?= e($msg[1]) ?>
        </div>
    <?php endif; ?>

    <!-- Formulaire d'ajout rapide -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <form method="POST" class="grid gap-4 md:grid-cols-5 items-end">
            <input type="hidden" name="action" value="add">
            
            <div class="md:col-span-1">
                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Choisir l'Espace</label>
                <select name="espace_id" required class="w-full rounded-xl border-slate-200 bg-slate-50 p-3 text-sm font-bold focus:border-primary outline-none transition">
                    <?php foreach ($espaces as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= e($e['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Libellé (ex: Mariage)</label>
                <input name="libelle" required class="w-full rounded-xl border-slate-200 bg-slate-50 p-3 text-sm focus:border-primary outline-none transition" placeholder="Type d'événement">
            </div>

            <div>
                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Montant (FCFA)</label>
                <input type="number" name="montant" required class="w-full rounded-xl border-slate-200 bg-slate-50 p-3 text-sm font-black text-[#E61E2A] focus:border-[#E61E2A] outline-none transition">
            </div>

            <div>
                <label class="text-[10px] font-black uppercase text-slate-400 mb-2 block">Unité</label>
                <input name="unite" value="jour" class="w-full rounded-xl border-slate-200 bg-slate-50 p-3 text-sm focus:border-primary outline-none transition">
            </div>

            <button class="bg-[#0A2558] text-white h-[46px] rounded-xl font-bold uppercase text-xs hover:bg-black transition shadow-lg shadow-blue-900/20">
                <i class="fas fa-plus-circle mr-2"></i> Ajouter
            </button>
        </form>
    </div>

    <!-- Tableau de gestion -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-[10px] font-black uppercase text-slate-400 italic">
                    <th class="px-6 py-4">Espace concerné</th>
                    <th class="px-6 py-4">Nature du tarif</th>
                    <th class="px-6 py-4">Prix (FCFA)</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($tarifs as $t): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        
                        <td class="px-6 py-4">
                            <span class="font-bold text-[#0A2558] text-sm uppercase italic"><?= e($t['espace_nom']) ?></span>
                        </td>
                        
                        <td class="px-6 py-4">
                            <input name="libelle" value="<?= e($t['libelle']) ?>" class="w-full bg-transparent border-none p-1 text-sm focus:ring-0">
                        </td>
                        
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <input name="montant" value="<?= $t['montant'] ?>" class="w-32 bg-transparent border-none p-1 text-sm font-black text-[#E61E2A] focus:ring-0">
                                <span class="text-[10px] text-slate-300">/</span>
                                <input name="unite" value="<?= e($t['unite']) ?>" class="w-16 bg-transparent border-none p-1 text-[10px] uppercase font-bold text-slate-400 focus:ring-0">
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 text-right space-x-3">
                            <button name="action" value="edit" title="Sauvegarder" class="text-emerald-500 hover:scale-125 transition">
                                <i class="fas fa-save"></i>
                            </button>
                            <button name="action" value="delete" onclick="return confirm('Voulez-vous vraiment supprimer ce prix ?')" title="Supprimer" class="text-slate-300 hover:text-red-500 hover:scale-125 transition">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if(empty($tarifs)): ?>
            <div class="p-12 text-center text-slate-300">
                <i class="fas fa-tags text-4xl mb-4 opacity-20"></i>
                <p class="text-sm">Aucun tarif spécifique n'est défini pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>