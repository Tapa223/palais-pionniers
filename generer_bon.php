<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) exit('Accès refusé');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = db();

/**
 * 1. RÉCUPÉRATION DES DONNÉES
 * On utilise t.montant pour le prix et on s'assure que r.user_id correspond
 * pour éviter qu'un utilisateur ne voie le bon d'un autre.
 */
$stmt = $pdo->prepare("
    SELECT r.*, e.nom as espace_nom, t.libelle as tarif_nom, t.montant, t.unite
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    LEFT JOIN tarifs t ON t.id = r.tarif_id
    WHERE r.id = ? AND r.user_id = ? AND r.statut = 'validee'
");
$stmt->execute([$id, $_SESSION['user_id']]);
$bon = $stmt->fetch();

if (!$bon) exit('Bon non disponible ou réservation non encore validée par l\'administration.');

/**
 * 2. PRÉPARATION DES VARIABLES
 * Sécurité pour éviter les "Undefined index"
 */
$nomClient = $_SESSION['nom_complet'] ?? $_SESSION['user_nom'] ?? 'Client';
$telephoneClient = !empty($bon['telephone']) ? $bon['telephone'] : 'Non renseigné';
$montantFinal = isset($bon['montant']) ? $bon['montant'] : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de Réservation #<?= $bon['id'] ?> - Palais des Pionniers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Configuration de la couleur principale du Palais */
        :root { --primary: #000000; } /* Ajuste ici si ton primary est différent (ex: #e11d48) */
        .text-primary { color: var(--primary); }
        .bg-primary { background-color: var(--primary); }
        .border-primary { border-color: var(--primary); }

        @media print { 
            .no-print { display: none !important; } 
            body { background: white; padding: 0; }
            .shadow-xl { shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 md:p-8 font-sans">
    <div class="max-w-2xl mx-auto bg-white p-6 md:p-10 rounded-none shadow-sm border-t-8 border-primary relative overflow-hidden">
        
        <!-- Filigrane de fond -->
        <div class="absolute top-0 right-0 opacity-5 -mr-10 -mt-10 text-9xl font-black rotate-12 select-none">PALAIS</div>

        <div class="flex justify-between items-start mb-10 relative z-10">
            <div>
                <h1 class="text-2xl font-black text-primary uppercase tracking-tighter italic">PALAIS DES PIONNIERS</h1>
                <p class="text-xs text-slate-500 font-bold">Centre de Formation et de Culture</p>
                <p class="text-xs text-slate-400">Magnambougou, Bamako, Mali</p>
            </div>
            <div class="text-right">
                <span class="bg-emerald-500 text-white px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest">CONFIRMÉ</span>
                <p class="text-sm mt-2 text-slate-900 font-mono font-bold italic">ID: #RESA-<?= $bon['id'] ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-8 pb-8 border-b border-slate-100">
            <div>
                <h2 class="font-bold text-slate-400 uppercase text-[10px] mb-2 tracking-widest">Client</h2>
                <p class="font-black text-slate-800 text-lg"><?= htmlspecialchars($nomClient) ?></p>
                <p class="text-sm text-slate-500 font-medium"><?= htmlspecialchars($telephoneClient) ?></p>
            </div>
            <div class="text-right">
                <h2 class="font-bold text-slate-400 uppercase text-[10px] mb-2 tracking-widest">Date de l'événement</h2>
                <p class="font-black text-slate-800 text-lg"><?= date('d/m/Y', strtotime($bon['date_resa'])) ?></p>
                <p class="text-xs text-slate-500 italic font-medium">Validité : 48h pour le paiement</p>
            </div>
        </div>

        <div class="mb-10">
            <h2 class="font-bold text-slate-400 uppercase text-[10px] mb-4 tracking-widest">Détails du service</h2>
            <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">
                <table class="w-full text-left">
                    <tr class="border-b border-slate-200">
                        <td class="py-3 text-sm text-slate-600 font-bold">Espace réservé</td>
                        <td class="py-3 font-black text-right text-slate-800"><?= htmlspecialchars($bon['espace_nom']) ?></td>
                    </tr>
                    <tr class="border-b border-slate-200">
                        <td class="py-3 text-sm text-slate-600 font-bold">Option/Tarif</td>
                        <td class="py-3 font-black text-right text-slate-800"><?= htmlspecialchars($bon['tarif_nom'] ?? 'Standard') ?></td>
                    </tr>
                    <tr>
                        <td class="py-5 text-primary font-black text-xl uppercase">Total à payer</td>
                        <td class="py-5 font-black text-3xl text-right text-primary">
                            <?= number_format($montantFinal, 0, ',', ' ') ?> <span class="text-sm">F CFA</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- INFOS DE CONTACT ET PAIEMENT -->
        <div class="border-2 border-primary/20 bg-primary/5 rounded-2xl p-6 mb-8">
            <h3 class="text-primary font-black text-xs uppercase mb-3 tracking-widest flex items-center gap-2">
                <span class="h-2 w-2 bg-primary rounded-full animate-pulse"></span> Modalités de paiement
            </h3>
            <p class="text-sm text-slate-700 leading-relaxed font-medium">
                Le règlement s'effectue au guichet administratif. Pour valider définitivement votre créneau, veuillez contacter le service comptabilité pour un dépôt ou transfert :
            </p>
            <div class="mt-4 flex items-center gap-4 bg-white p-3 rounded-xl border border-primary/10 shadow-sm w-fit">
                <div class="bg-primary text-white h-10 w-10 rounded-full flex items-center justify-center shadow-lg shadow-primary/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                </div>
                <span class="text-2xl font-black text-slate-900 tracking-tighter">+223 66 24 90 77</span>
            </div>
        </div>

        <div class="text-[10px] text-slate-400 text-center uppercase tracking-widest font-black italic">
            *** Ce document fait office de pré-réservation officielle au Palais des Pionniers ***
        </div>

        <!-- ACTIONS (Boutons fixés en blanc sur noir pour la visibilité) -->
        <div class="mt-10 flex flex-col md:flex-row justify-center gap-4 no-print">
            <button onclick="window.print()" class="bg-black text-white px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition shadow-xl shadow-black/20 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Imprimer mon bon
            </button>
            <a href="mon-compte.php" class="bg-slate-200 text-slate-700 px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-300 transition text-center flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Retour au compte
            </a>
        </div>
    </div>
</body>
</html>