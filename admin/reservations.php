<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin('../index.php');

$pdo = db();
$msg = null;

// --- TRAITEMENT DES ACTIONS ---
if (isset($_POST['action'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    
    if ($action === 'valider') {
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'validee', notification_vue = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $msg = ['ok', 'Réservation confirmée.'];
        
    } elseif ($action === 'refuser') {
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'refusee', notification_vue = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $msg = ['error', 'Réservation refusée.'];

    } elseif ($action === 'annuler') {
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'en_attente', notification_vue = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $msg = ['ok', 'La demande est de nouveau en attente.'];
    }
}

// --- RÉCUPÉRATION DES DONNÉES (Correction de u.nom -> u.nom_complet) ---
$reservations = $pdo->query("
    SELECT r.*, e.nom as espace_nom, u.nom_complet as user_nom, u.telephone as user_tel, 
           t.libelle as tarif_nom, t.montant as tarif_prix
    FROM reservations r 
    JOIN espaces e ON e.id = r.espace_id 
    JOIN users u ON u.id = r.user_id 
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    ORDER BY r.created_at DESC
")->fetchAll();

require __DIR__ . '/_admin_header.php';
?>

<div class="px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tighter">Suivi des Réservations</h1>
            <p class="text-sm text-slate-500 mt-1">Gestion des flux et paiements</p>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl border <?= $msg[0] === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200' ?>">
            <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $msg[1] ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-xl overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Client & Contact</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Détails Réservation</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Date & Heures</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest">Statut</th>
                    <th class="p-6 text-[10px] font-black uppercase text-slate-400 tracking-widest text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($reservations as $res): ?>
                <tr class="hover:bg-slate-50/50 transition-colors">
                    
                    <td class="p-6">
                        <div class="font-black text-primary uppercase text-sm italic"><?= htmlspecialchars($res['user_nom']) ?></div>
                        <div class="text-xs text-slate-500 mt-1 font-bold italic">
                             <?= htmlspecialchars($res['user_tel'] ?? 'N/A') ?>
                        </div>
                    </td>

                    <td class="p-6">
                        <div class="font-black text-primary uppercase text-sm"><?= htmlspecialchars($res['espace_nom']) ?></div>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php if($res['tarif_nom']): ?>
                                <span class="text-[9px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-black uppercase tracking-tighter">
                                    Tarif : <?= htmlspecialchars($res['tarif_nom']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if($res['tarif_prix']): ?>
                                <span class="text-[9px] bg-emerald-100 text-emerald-700 px-2 py-1 rounded font-black uppercase tracking-tighter">
                                    <?= number_format($res['tarif_prix'], 0, '.', ' ') ?> FCFA
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td class="p-6">
                        <div class="text-xs font-black text-primary italic">
                            <?= date('d/m/Y', strtotime($res['date_resa'])) ?>
                        </div>
                        <div class="text-[10px] text-indigo-600 font-black mt-1 uppercase">
                            <?= date('H:i', strtotime($res['heure_debut'])) ?> - <?= date('H:i', strtotime($res['heure_fin'])) ?>
                        </div>
                    </td>

                    <td class="p-6">
                        <?php 
                        $statusStyles = [
                            'validee' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                            'en_attente' => 'bg-amber-50 text-amber-600 border-amber-100',
                            'refusee' => 'bg-rose-50 text-rose-600 border-rose-100'
                        ];
                        $label = $res['statut'] === 'validee' ? 'Confirmé' : ($res['statut'] === 'refusee' ? 'Refusé' : 'En attente');
                        ?>
                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase border <?= $statusStyles[$res['statut']] ?? 'bg-slate-100' ?>">
                            <?= $label ?>
                        </span>
                    </td>

                    <td class="p-6 text-right">
                        <form method="post" class="flex justify-end gap-2">
                            <input type="hidden" name="id" value="<?= $res['id'] ?>">

                            <?php if ($res['statut'] === 'en_attente'): ?>
                                <button name="action" value="valider" class="bg-emerald-500 text-white px-4 py-2 rounded-xl hover:bg-emerald-600 transition text-[10px] font-black uppercase tracking-widest">
                                    Accepter
                                </button>
                                <button name="action" value="refuser" class="border border-rose-100 text-rose-500 px-4 py-2 rounded-xl hover:bg-rose-50 transition text-[10px] font-black uppercase tracking-widest" onclick="return confirm('Refuser ?')">
                                    Refuser
                                </button>

                            <?php else: ?>
                                <button name="action" value="annuler" class="bg-slate-900 text-white px-4 py-2 rounded-xl hover:bg-black transition text-[10px] font-black uppercase tracking-widest">
                                    Annuler
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>