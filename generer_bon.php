<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    exit('Accès refusé');
}

$pdo  = db();
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$type = $_GET['type'] ?? 'bon';
$role = $_SESSION['role'] ?? 'user';

$estAdminAutorise = in_array(
    $role,
    ['superadmin', 'admin_dg', 'ministre', 'admin_espaces', 'admin_comptable'],
    true
);

/*
|--------------------------------------------------------------------------
| OUTILS
|--------------------------------------------------------------------------
*/
if (!function_exists('nombre_en_lettres')) {
    function nombre_en_lettres(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }

        $unites = [
            '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept',
            'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze',
            'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'
        ];

        $dizaines = [
            '', '', 'vingt', 'trente', 'quarante', 'cinquante',
            'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'
        ];

        $convertirCentaines = function (
            int $n,
            bool $dernierGroupe = true
        ) use ($unites, $dizaines) {
            $texte = '';

            if ($n >= 100) {
                $c = intdiv($n, 100);

                $texte .= ($c > 1 ? $unites[$c] . ' ' : '')
                    . 'cent'
                    . ($c > 1 && $n % 100 === 0 && $dernierGroupe ? 's' : '');

                $n %= 100;

                if ($n > 0) {
                    $texte .= ' ';
                }
            }

            if ($n >= 20) {
                $d = intdiv($n, 10);
                $u = $n % 10;

                if ($d === 7 || $d === 9) {
                    $texte .= $dizaines[$d] . '-' . $unites[10 + $u];
                } else {
                    $texte .= $dizaines[$d];

                    if ($u === 1 && $d !== 8) {
                        $texte .= ' et un';
                    } elseif ($u > 0) {
                        $texte .= '-' . $unites[$u];
                    } elseif ($d === 8 && $dernierGroupe) {
                        $texte .= 's';
                    }
                }
            } elseif ($n > 0) {
                $texte .= $unites[$n];
            }

            return $texte;
        };

        $tranches = [
            [1000000000, 'milliard'],
            [1000000, 'million'],
            [1000, 'mille'],
            [1, '']
        ];

        $texte = '';

        foreach ($tranches as [$valeur, $mot]) {
            if ($n >= $valeur) {
                $q = intdiv($n, $valeur);
                $resteApres = $n % $valeur;

                if ($valeur === 1) {
                    $texte .= $convertirCentaines($q, true);
                } else {
                    $texte .= (
                        $q > 1
                            ? $convertirCentaines($q, false) . ' '
                            : ($valeur === 1000 ? '' : 'un ')
                    )
                    . $mot
                    . (
                        $q > 1 &&
                        $valeur != 1000 &&
                        $resteApres === 0
                            ? 's'
                            : ''
                    )
                    . ' ';
                }

                $n %= $valeur;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $texte));
    }
}

$modeLabels = [
    'especes'      => 'Espèces',
    'orange_money' => 'Orange Money',
    'moov_money'   => 'Moov Money',
    'virement'     => 'Virement',
    'cheque'       => 'Chèque',
];

/*
|--------------------------------------------------------------------------
| 1. BON DE REMBOURSEMENT
|--------------------------------------------------------------------------
*/
if ($type === 'remboursement') {

    /*
     * Le bon n'est disponible que lorsqu'un remboursement a réellement
     * été effectué.
     */
    $sql = "
        SELECT
            rb.*,
            r.date_resa,
            r.date_depart,
            r.heure_debut,
            r.heure_fin,
            r.espace_id,
            e.nom AS espace_nom,
            rm.id AS requisition_id,
            rm.motif AS requisition_motif,
            rm.choix_client,
            rm.date_choix,
            op.id AS operation_id,
            op.type_operation,
            op.reference AS operation_reference,
            op.date_traitement AS operation_date_traitement,
            u.nom_complet AS client_nom,
            u.telephone AS client_telephone,
            u.email AS client_email,
            comptable.nom_complet AS comptable_nom,
            comptable.telephone AS comptable_telephone
        FROM remboursements rb
        INNER JOIN reservations r
            ON r.id = rb.reservation_id
        INNER JOIN users u
            ON u.id = rb.client_id
        LEFT JOIN espaces e
            ON e.id = r.espace_id
        LEFT JOIN requisitions_ministerielles rm
            ON rm.id = rb.requisition_id
        LEFT JOIN operations_requisition op
            ON op.id = rb.operation_id
        LEFT JOIN users comptable
            ON comptable.id = rb.traite_par
        WHERE rb.id = ?
          AND rb.resultat = 'effectue'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $remboursement = $stmt->fetch();

    if (!$remboursement) {
        exit('Bon de remboursement non disponible.');
    }

    /*
     * Sécurité :
     * un client ne peut voir que son propre remboursement.
     */
    if (
        !$estAdminAutorise &&
        (int) $remboursement['client_id'] !== (int) ($_SESSION['user_id'] ?? 0)
    ) {
        exit('Accès refusé.');
    }

    /*
     * Numéro du bon.
     */
    // Année du remboursement (et non l'année en cours)
    $numeroBon = 'BR-' . date('Y', strtotime($remboursement['date_traitement'] ?? $remboursement['created_at'] ?? 'now')) . '-' . str_pad(
        (string) $remboursement['id'],
        6,
        '0',
        STR_PAD_LEFT
    );

    $dateBon = !empty($remboursement['date_traitement'])
        ? date('d/m/Y', strtotime($remboursement['date_traitement']))
        : date('d/m/Y');

    $nomClient = !empty($remboursement['client_nom'])
        ? $remboursement['client_nom']
        : 'Client';

    $telephoneClient = !empty($remboursement['client_telephone'])
        ? $remboursement['client_telephone']
        : 'Non renseigné';

    $montantPaye = (float) ($remboursement['montant_paye'] ?? 0);

    $montantARembourser = (float) (
        $remboursement['montant_a_rembourser'] ?? 0
    );

    $montantRembourse = (float) (
        $remboursement['montant_rembourse'] ?? 0
    );

    /*
     * Par sécurité, si le montant réellement remboursé n'est pas renseigné,
     * on affiche le montant à rembourser.
     */
    if ($montantRembourse <= 0 && $montantARembourser > 0) {
        $montantRembourse = $montantARembourser;
    }

    $modeRemboursement = $modeLabels[$remboursement['mode'] ?? '']
        ?? ($remboursement['mode'] ?? 'Non renseigné');

    $referenceRemboursement = !empty($remboursement['reference'])
        ? $remboursement['reference']
        : 'Non renseignée';

    $objetRemboursement = 'Remboursement relatif à la réservation';

    if (!empty($remboursement['espace_nom'])) {
        $objetRemboursement .= ' de ' . $remboursement['espace_nom'];
    }

    if (!empty($remboursement['date_resa'])) {
        $objetRemboursement .= ' du ' . date(
            'd/m/Y',
            strtotime($remboursement['date_resa'])
        );
    }

    $retourHref = 'mon-compte.php';

    if ($estAdminAutorise) {
        if (!empty($remboursement['requisition_id'])) {
            $retourHref = 'admin/requisition-detail.php?id='
                . (int) $remboursement['requisition_id'];
        } else {
            $retourHref = 'admin/requisitions.php';
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>
            Bon de remboursement <?= e($numeroBon) ?> -
            Palais des Pionniers
        </title>

        <link rel="stylesheet" href="assets/css/tailwind.css">

        <!-- PDF -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

        <style>
            :root {
                --primary: #000000;
            }

            .text-primary {
                color: var(--primary);
            }

            .bg-primary {
                background-color: var(--primary);
            }

            .border-primary {
                border-color: var(--primary);
            }

            @media print {
                @page {
                    size: A4;
                    margin: 8mm;
                }

                .no-print {
                    display: none !important;
                }

                html,
                body {
                    background: white !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    font-size: 13px !important;
                }

                .shadow-xl,
                .shadow-sm,
                .shadow-2xl {
                    box-shadow: none !important;
                }

                .max-w-2xl {
                    max-width: 100% !important;
                }

                .p-6,
                .md\:p-10 {
                    padding: 0.6rem 0.9rem !important;
                }

                .mb-8,
                .mb-10,
                .mb-16,
                .mt-10,
                .mt-16 {
                    margin-top: 0.4rem !important;
                    margin-bottom: 0.4rem !important;
                }

                .py-12,
                .py-8,
                .py-6,
                .py-5 {
                    padding-top: 0.3rem !important;
                    padding-bottom: 0.3rem !important;
                }

                .text-3xl {
                    font-size: 1.1rem !important;
                }

                .text-xl,
                .text-2xl {
                    font-size: 0.95rem !important;
                }

                table td,
                table th {
                    padding: 0.3rem 0.45rem !important;
                }

                .rounded-2xl,
                .rounded-\[2\.5rem\] {
                    border-radius: 0.5rem !important;
                }
            }
        </style>

        <script>
            async function telechargerPDF(nomFichier) {
                const documentPDF = document.getElementById('document-pdf');

                if (!documentPDF) {
                    alert('Document introuvable.');
                    return;
                }

                const boutons = documentPDF.querySelectorAll('.no-print');

                boutons.forEach(function (element) {
                    element.style.display = 'none';
                });

                try {
                    await html2pdf()
                        .set({
                            margin: 8,
                            filename: nomFichier,
                            image: {
                                type: 'jpeg',
                                quality: 0.98
                            },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                backgroundColor: '#ffffff'
                            },
                            jsPDF: {
                                unit: 'mm',
                                format: 'a4',
                                orientation: 'portrait'
                            }
                        })
                        .from(documentPDF)
                        .save();
                } catch (error) {
                    console.error(
                        'Erreur lors du téléchargement PDF :',
                        error
                    );

                    alert(
                        'Le téléchargement du document a échoué.'
                    );
                } finally {
                    boutons.forEach(function (element) {
                        element.style.display = '';
                    });
                }
            }
        </script>
    </head>

    <body class="bg-slate-100 p-4 md:p-8 font-sans">

        <div
            id="document-pdf"
            class="max-w-2xl mx-auto bg-white p-6 md:p-10 rounded-none shadow-sm border-t-8 border-primary relative overflow-hidden"
        >

            <!-- EN-TÊTE INSTITUTIONNEL -->
            <div class="flex justify-between items-start gap-4 pb-6 border-b-2 border-slate-800 mb-6">

                <div class="text-[10px] leading-relaxed">

                    <p class="font-black uppercase">
                        Ministère de la Jeunesse et des Sports,
                    </p>

                    <p class="font-black uppercase">
                        Chargé de l'Instruction Civique
                    </p>

                    <p class="font-black uppercase mb-1">
                        et de la Construction Citoyenne
                    </p>

                    <p class="tracking-[0.3em]">
                        ****************
                    </p>

                    <p class="font-black uppercase mt-1">
                        Direction Générale du Palais des Pionniers
                    </p>

                    <p class="tracking-[0.3em]">
                        ****************
                    </p>

                </div>

                <div class="text-[10px] text-right leading-relaxed">

                    <p class="font-black uppercase">
                        République du Mali
                    </p>

                    <p class="italic">
                        Un Peuple – Un But – Une Foi
                    </p>

                    <p class="tracking-[0.3em]">
                        ****************
                    </p>

                    <img
                        src="assets/images/logopalais.png"
                        alt="Palais des Pionniers"
                        class="h-8 w-auto object-contain ml-auto mt-1"
                    >

                </div>

            </div>

            <p class="text-right text-xs font-semibold mb-6">
                Bamako, le <?= e($dateBon) ?>
            </p>

            <h1 class="text-center text-lg font-black uppercase tracking-widest mb-8">
                Bon de remboursement N° <?= e($numeroBon) ?>
            </h1>

            <p class="text-sm mb-3">
                <span class="font-black underline">Doit</span> :
                <?= e($nomClient) ?>
            </p>

            <p class="text-sm mb-8">
                <span class="font-black underline">Objet</span> :
                <?= e($objetRemboursement) ?>.
            </p>

            <!-- TABLEAU DU REMBOURSEMENT -->
            <table class="w-full text-left text-sm border-collapse border border-slate-800 mb-6">

                <thead>
                    <tr class="bg-slate-300 text-slate-900">

                        <th class="py-2 px-3 font-black border border-slate-800">
                            DÉSIGNATION
                        </th>

                        <th class="py-2 px-3 font-black text-right border border-slate-800">
                            MONTANT
                        </th>

                    </tr>
                </thead>

                <tbody>

                    <tr>

                        <td class="py-3 px-3 border border-slate-800">
                            Montant initialement payé
                        </td>

                        <td class="py-3 px-3 text-right font-bold border border-slate-800">
                            <?= number_format($montantPaye, 0, ',', ' ') ?>
                            F CFA
                        </td>

                    </tr>

                    <tr>

                        <td class="py-3 px-3 border border-slate-800">
                            Montant à rembourser
                        </td>

                        <td class="py-3 px-3 text-right font-bold border border-slate-800">
                            <?= number_format($montantARembourser, 0, ',', ' ') ?>
                            F CFA
                        </td>

                    </tr>

                    <tr class="bg-slate-300">

                        <td class="py-3 px-3 font-black border border-slate-800">
                            TOTAL REMBOURSÉ
                        </td>

                        <td class="py-3 px-3 text-right font-black border border-slate-800">
                            <?= number_format($montantRembourse, 0, ',', ' ') ?>
                            F CFA
                        </td>

                    </tr>

                </tbody>

            </table>

            <!-- INFORMATIONS DU REMBOURSEMENT -->
            <div class="text-sm mb-8">

                <p class="mb-2">
                    <span class="font-black">
                        Mode de remboursement :
                    </span>
                    <?= e($modeRemboursement) ?>
                </p>

                <p class="mb-2">
                    <span class="font-black">
                        Référence :
                    </span>
                    <?= e($referenceRemboursement) ?>
                </p>

                <p class="mb-2">
                    <span class="font-black">
                        Date du remboursement :
                    </span>
                    <?= e($dateBon) ?>
                </p>

                <?php if (!empty($remboursement['motif'])): ?>

                    <p class="mb-2">
                        <span class="font-black">
                            Motif :
                        </span>

                        <?= e($remboursement['motif']) ?>
                    </p>

                <?php endif; ?>

            </div>

            <p class="text-sm mb-10">

                Arrêter le présent bon de remboursement à la somme de :

                <span class="font-black italic">

                    <?= ucfirst(nombre_en_lettres((int) $montantRembourse)) ?>

                    (<?= number_format($montantRembourse, 0, ',', ' ') ?>)

                    Francs CFA.

                </span>

            </p>

            <!-- SIGNATURES -->
            <div class="flex justify-between items-end mt-16 mb-6 text-sm">

                <div class="text-center">

                    <p class="font-black underline mb-16">
                        Le Client
                    </p>

                </div>

                <div class="text-center">

                    <p class="font-black underline mb-4">
                        Le Comptable
                    </p>

                    <?php if (!empty($remboursement['comptable_nom'])): ?>

                        <p class="text-xs text-slate-600">
                            <?= e($remboursement['comptable_nom']) ?>
                        </p>

                    <?php endif; ?>

                    <?php if (!empty($remboursement['comptable_telephone'])): ?>

                        <p class="text-xs text-slate-500">
                            <?= e($remboursement['comptable_telephone']) ?>
                        </p>

                    <?php endif; ?>

                    <div class="mt-8"></div>

                </div>

            </div>

            <!-- TRACE MINIMALE -->
            <div class="text-[9px] text-slate-400 text-center border-t border-slate-200 pt-3">

                <?php if (!empty($remboursement['requisition_id'])): ?>

                    Réservation n°<?= (int) $remboursement['reservation_id'] ?>

                    · Réquisition n°<?= (int) $remboursement['requisition_id'] ?>

                    <?php if (!empty($remboursement['operation_id'])): ?>

                        · Opération n°<?= (int) $remboursement['operation_id'] ?>

                    <?php endif; ?>

                    · Bon n°<?= e($numeroBon) ?>

                <?php else: ?>

                    Réservation n°<?= (int) $remboursement['reservation_id'] ?>

                    · Bon n°<?= e($numeroBon) ?>

                <?php endif; ?>

            </div>

            <div class="text-[9px] text-slate-400 text-center mt-2">

                Palais des Pionniers — Magnambougou / Dianéguéla, Bamako, Mali

                — NIF : 084153298A

            </div>

            <!-- ACTIONS -->
            <div class="mt-10 flex flex-col md:flex-row justify-center gap-4 no-print">

                <button
                    type="button"
                    onclick="telechargerPDF('Bon-de-remboursement-<?= e($numeroBon) ?>.pdf')"
                    class="bg-black text-white px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition shadow-xl shadow-black/20 flex items-center justify-center gap-2"
                >

                    <svg
                        class="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"
                        />
                    </svg>

                    Télécharger le bon

                </button>

                <a
                    href="<?= e($retourHref) ?>"
                    class="bg-slate-200 text-slate-700 px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-300 transition text-center flex items-center justify-center gap-2"
                >

                    <svg
                        class="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>

                    Retour

                </a>

            </div>

        </div>

    </body>

    </html>

    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. FACTURE / BON DE RÉSERVATION
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        r.*,
        e.nom AS espace_nom,
        e.prix_vip,
        t.libelle AS tarif_nom,
        t.montant,
        t.unite,
        u.nom_complet AS client_nom,
        u.telephone AS client_telephone,
        u.email AS client_email
    FROM reservations r
    JOIN espaces e
        ON e.id = r.espace_id
    JOIN users u
        ON u.id = r.user_id
    LEFT JOIN tarifs t
        ON t.id = r.tarif_id
    WHERE r.id = ?
      AND r.statut = 'validee'
";

$params = [$id];

if (!$estAdminAutorise) {
    $sql .= " AND r.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bon = $stmt->fetch();

if (!$bon) {

    $checkExpire = $pdo->prepare("
        SELECT statut
        FROM reservations
        WHERE id = ?
    ");

    $checkExpire->execute([$id]);

    $statutActuel = $checkExpire->fetchColumn();

    if ($statutActuel === 'expiree') {
        exit(
            "Cette réservation a été annulée automatiquement : "
            . "le paiement n'a pas été effectué dans le délai de 48h après validation. "
            . "Vous pouvez faire une nouvelle demande depuis le site."
        );
    }

    exit(
        "Document non disponible. "
        . "La réservation doit d'abord être validée par nos services."
    );
}

$forceBon = isset($_GET['type']) && $_GET['type'] === 'bon';

/*
 * Situation financière centrale : montant initial, réduction réellement
 * appliquée, net dû, payé, remboursé, solde et échéance. Une réduction
 * « non appliquée » ou « annulée » n'apparaît jamais sur les documents.
 */
$sf = situation_financiere_reservation($pdo, $id);

$estPaye = (
    $sf['statut_paiement_calcule'] === 'paye'
    && $sf['total_paye'] > 0
) && !$forceBon;

$estPartiel = (
    $sf['statut_paiement_calcule'] === 'partiellement_paye'
) && !$forceBon;

$from = $_GET['from'] ?? '';

/*
|--------------------------------------------------------------------------
| PAIEMENTS
|--------------------------------------------------------------------------
*/

$paiement = null;
$tousLesPaiements = [];
$totalVerseFacture = 0;

if ($estPaye || $estPartiel) {

    $stmtP = $pdo->prepare("
        SELECT
            p.id,
            p.montant,
            p.montant_reference,
            p.motif_reduction,
            p.mode,
            p.reference,
            p.created_at,
            admin.nom_complet AS comptable_nom,
            admin.telephone AS comptable_tel
        FROM paiements p
        JOIN users admin
            ON admin.id = p.enregistre_par
        WHERE p.reservation_id = ?
        ORDER BY p.created_at ASC
    ");

    $stmtP->execute([$id]);

    $tousLesPaiements = $stmtP->fetchAll();

    $paiement = $tousLesPaiements
        ? end($tousLesPaiements)
        : null;

    foreach ($tousLesPaiements as $pz) {
        $totalVerseFacture += (float) $pz['montant'];
    }
}

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$nomClient = $bon['client_nom'] ?: 'Client';

$telephoneClient = !empty($bon['client_telephone'])
    ? $bon['client_telephone']
    : 'Non renseigné';

$estSejour = empty($bon['heure_debut']);

$nuitees = $estSejour
    ? max(
        1,
        (int) (
            (strtotime($bon['date_depart']) - strtotime($bon['date_resa']))
            / 86400
        )
    )
    : 1;

$quantiteResa = max(
    1,
    (int) ($bon['quantite'] ?? 1)
);

// Montant initial (tarif normal figé), réduction appliquée et net dû
$montantReferenceComplet = $sf['montant_initial'];

$montantReductionFacture = $sf['montant_reduction'];

$motifReductionFacture = $sf['reduction_appliquee']['motif'] ?? null;

// Maintien du tarif suite à réquisition : ce n'est pas une remise commerciale
$libelleReductionFacture = !empty($sf['prise_en_charge_requisition'])
    ? 'Maintien du tarif — réquisition #' . (int) $sf['requisition_id']
    : null;
if ($libelleReductionFacture) {
    $motifReductionFacture = null; // le libellé suffit (le détail reste dans l'historique comptable)
}

if ($motifReductionFacture === null && $sf['reduction_historique'] > 0) {
    // Ancien fonctionnement : motif porté par le paiement
    foreach ($tousLesPaiements as $pz) {
        if (!empty($pz['motif_reduction'])) {
            $motifReductionFacture = $pz['motif_reduction'];
        }
    }
}

$soldeRestantFacture = $sf['solde'];

$montantFinal = $sf['net_du'];

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        <?= $estPaye
            ? 'Facture'
            : ($estPartiel
                ? "Facture d'acompte"
                : 'Bon de Réservation') ?>

        #<?= (int) $bon['id'] ?>

        - Palais des Pionniers

    </title>

    <link
        rel="stylesheet"
        href="assets/css/tailwind.css"
    >

    <!-- PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>

        :root {
            --primary: #000000;
        }

        .text-primary {
            color: var(--primary);
        }

        .bg-primary {
            background-color: var(--primary);
        }

        .border-primary {
            border-color: var(--primary);
        }

        @media print {

            @page {
                size: A4;
                margin: 8mm;
            }

            .no-print {
                display: none !important;
            }

            html,
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 13px !important;
            }

            .shadow-xl,
            .shadow-sm,
            .shadow-2xl {
                box-shadow: none !important;
            }

            .max-w-2xl {
                max-width: 100% !important;
            }

            .p-6,
            .md\:p-10 {
                padding: 0.6rem 0.9rem !important;
            }

            .mb-8,
            .mb-10,
            .mb-16,
            .mt-10,
            .mt-16 {
                margin-top: 0.4rem !important;
                margin-bottom: 0.4rem !important;
            }

            .py-12,
            .py-8,
            .py-6,
            .py-5 {
                padding-top: 0.3rem !important;
                padding-bottom: 0.3rem !important;
            }

            .text-3xl {
                font-size: 1.1rem !important;
            }

            .text-xl,
            .text-2xl {
                font-size: 0.95rem !important;
            }

            table td,
            table th {
                padding: 0.3rem 0.45rem !important;
            }

            .rounded-2xl,
            .rounded-\[2\.5rem\] {
                border-radius: 0.5rem !important;
            }
        }

    </style>

    <script>

        async function telechargerPDF(nomFichier) {

            const documentPDF =
                document.getElementById('document-pdf');

            if (!documentPDF) {
                alert('Document introuvable.');
                return;
            }

            const boutons =
                documentPDF.querySelectorAll('.no-print');

            boutons.forEach(function (element) {
                element.style.display = 'none';
            });

            try {

                await html2pdf()
                    .set({
                        margin: 8,

                        filename: nomFichier,

                        image: {
                            type: 'jpeg',
                            quality: 0.98
                        },

                        html2canvas: {
                            scale: 2,
                            useCORS: true,
                            backgroundColor: '#ffffff'
                        },

                        jsPDF: {
                            unit: 'mm',
                            format: 'a4',
                            orientation: 'portrait'
                        }
                    })
                    .from(documentPDF)
                    .save();

            } catch (error) {

                console.error(
                    'Erreur lors du téléchargement PDF :',
                    error
                );

                alert(
                    'Le téléchargement du document a échoué.'
                );

            } finally {

                boutons.forEach(function (element) {
                    element.style.display = '';
                });

            }
        }

    </script>

</head>

<body class="bg-slate-100 p-4 md:p-8 font-sans">

<div
    id="document-pdf"
    class="max-w-2xl mx-auto bg-white p-6 md:p-10 rounded-none shadow-sm border-t-8 border-primary relative overflow-hidden"
>

<?php if ($estPaye): ?>

<?php

$numeroFacture = function_exists('ref_recu')
    ? ref_recu((int) ($paiement['id'] ?? 0), $paiement['created_at'] ?? null)
    : 'FACT-' . (int) $bon['id'];

$dateFacture = $paiement
    ? date('d/m/Y', strtotime($paiement['created_at']))
    : date('d/m/Y');

$objetLibelle =
    $bon['espace_nom']
    . (
        !empty($bon['tarif_nom'])
            ? ' (' . $bon['tarif_nom'] . ')'
            : ''
    );

$objetPeriode = $estSejour
    ? 'pour le séjour du '
        . date('d/m/Y', strtotime($bon['date_resa']))
        . ' au '
        . date('d/m/Y', strtotime($bon['date_depart']))
    : "pour la journée du "
        . date('d/m/Y', strtotime($bon['date_resa']));

$sousTotal = $montantReferenceComplet;

$remiseMontant = $montantReductionFacture;

$qteLigne = $estSejour
    ? ($nuitees * $quantiteResa)
    : 1;

?>

<!-- EN-TÊTE -->

<div class="flex justify-between items-start gap-4 pb-6 border-b-2 border-slate-800 mb-6">

    <div class="text-[10px] leading-relaxed">

        <p class="font-black uppercase">
            Ministère de la Jeunesse et des Sports,
        </p>

        <p class="font-black uppercase">
            Chargé de l'Instruction Civique
        </p>

        <p class="font-black uppercase mb-1">
            et de la Construction Citoyenne
        </p>

        <p class="tracking-[0.3em]">
            ****************
        </p>

        <p class="font-black uppercase mt-1">
            Direction Générale du Palais des Pionniers
        </p>

        <p class="tracking-[0.3em]">
            ****************
        </p>

    </div>

    <div class="text-[10px] text-right leading-relaxed">

        <p class="font-black uppercase">
            République du Mali
        </p>

        <p class="italic">
            Un Peuple – Un But – Une Foi
        </p>

        <p class="tracking-[0.3em]">
            ****************
        </p>

        <img
            src="assets/images/logopalais.png"
            alt="Palais des Pionniers"
            class="h-8 w-auto object-contain ml-auto mt-1"
        >

    </div>

</div>

<p class="text-right text-xs font-semibold mb-6">
    Bamako, le <?= e($dateFacture) ?>
</p>

<h1 class="text-center text-lg font-black uppercase tracking-widest mb-8">
    Facture N° <?= e($numeroFacture) ?>
</h1>

<p class="text-sm mb-3">

    <span class="font-black underline">
        Doit
    </span> :

    <?= e($nomClient) ?>

</p>

<p class="text-sm mb-8">

    <span class="font-black underline">
        Objet
    </span> :

    Location de <?= e($objetLibelle) ?>

    <?= e($objetPeriode) ?>.

</p>

<table class="w-full text-left text-sm border-collapse border border-slate-800 mb-4">

    <thead>

        <tr class="bg-slate-300 text-slate-900">

            <th class="py-2 px-2 font-black text-center border border-slate-800 w-12">
                QTE
            </th>

            <th class="py-2 px-2 font-black border border-slate-800">
                DESIGNATION
            </th>

            <th class="py-2 px-2 font-black text-center border border-slate-800">
                NBRES/JOURS
            </th>

            <th class="py-2 px-2 font-black text-right border border-slate-800">
                P.U
            </th>

            <th class="py-2 px-2 font-black text-right border border-slate-800">
                MONTANT
            </th>

        </tr>

    </thead>

    <tbody>

        <tr>

            <td class="py-3 px-2 text-center font-bold border border-slate-800">
                01
            </td>

            <td class="py-3 px-2 border border-slate-800">

                Location de <?= e($objetLibelle) ?>

                <?= e($objetPeriode) ?>.

            </td>

            <td class="py-3 px-2 text-center font-bold border border-slate-800">
                <?= $qteLigne ?>
            </td>

            <td class="py-3 px-2 text-right font-bold border border-slate-800">

                <?= number_format(
                    (float) $bon['montant'],
                    0,
                    ',',
                    ' '
                ) ?>

            </td>

            <td class="py-3 px-2 text-right font-bold border border-slate-800">

                <?= number_format(
                    $sousTotal,
                    0,
                    ',',
                    ' '
                ) ?>

            </td>

        </tr>

        <?php if ($remiseMontant > 0): ?>

        <tr>

            <td class="py-2 px-2 border border-slate-800"></td>

            <td
                class="py-2 px-2 italic border border-slate-800"
                colspan="3"
            >

                <?= $libelleReductionFacture ? e($libelleReductionFacture) : 'Remise accordée' ?>

                <?= !empty($motifReductionFacture)
                    ? ' — ' . e($motifReductionFacture)
                    : '' ?>

            </td>

            <td class="py-2 px-2 text-right font-bold border border-slate-800">

                − <?= number_format(
                    $remiseMontant,
                    0,
                    ',',
                    ' '
                ) ?>

            </td>

        </tr>

        <?php endif; ?>

        <tr class="bg-slate-300">

            <td
                colspan="4"
                class="py-2.5 px-2 font-black text-center border border-slate-800"
            >
                TOTAL TTC
            </td>

            <td class="py-2.5 px-2 text-right font-black border border-slate-800">

                <?= number_format(
                    $montantFinal,
                    0,
                    ',',
                    ' '
                ) ?>

            </td>

        </tr>

    </tbody>

</table>

<p class="text-sm mb-10">

    Arrêter la présente facture à la somme de :

    <span class="font-black italic">

        <?= ucfirst(nombre_en_lettres((int) $montantFinal)) ?>

        (<?= number_format($montantFinal, 0, ',', ' ') ?>)

        Francs CFA

    </span>.

</p>

<?php if ($sf['nb_paiements'] > 1 || $sf['total_rembourse'] > 0 || $sf['trop_percu'] > 0): ?>

<p class="text-xs text-slate-600 -mt-6 mb-10">

    Montant encaissé :
    <strong><?= number_format($sf['total_paye'], 0, ',', ' ') ?> F CFA</strong>
    en <?= (int) $sf['nb_paiements'] ?> versement<?= $sf['nb_paiements'] > 1 ? 's' : '' ?>

    <?php if ($sf['total_rembourse'] > 0): ?>
        · remboursé : <strong><?= number_format($sf['total_rembourse'], 0, ',', ' ') ?> F CFA</strong>
    <?php endif; ?>

    <?php if ($sf['trop_percu'] > 0): ?>
        · trop-perçu à rembourser : <strong><?= number_format($sf['trop_percu'], 0, ',', ' ') ?> F CFA</strong>
    <?php endif; ?>

</p>

<?php endif; ?>

<div class="flex justify-between items-end mt-16 mb-6 text-sm">

    <div class="text-center">

        <p class="font-black underline mb-16">
            Pour acquit
        </p>

    </div>

    <div class="text-center">

        <p class="font-black underline mb-4">
            Le Comptable
        </p>

        <?php if (!empty($paiement['comptable_nom'])): ?>

            <p class="text-xs text-slate-600">
                <?= e($paiement['comptable_nom']) ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($paiement['comptable_tel'])): ?>

            <p class="text-xs text-slate-500">
                <?= e($paiement['comptable_tel']) ?>
            </p>

        <?php endif; ?>

        <div class="mt-8"></div>

    </div>

</div>

<div class="text-[9px] text-slate-400 text-center border-t border-slate-200 pt-3">

    Palais des Pionniers — Magnambougou / Dianéguéla, Bamako, Mali

    — NIF : 084153298A

</div>

<?php else: ?>

<!-- ============================================================
     BON DE RÉSERVATION
============================================================ -->

<div class="absolute top-0 right-0 opacity-5 -mr-10 -mt-10 text-9xl font-black rotate-12 select-none">
    PALAIS
</div>

<div class="flex justify-between items-start mb-10 relative z-10">

    <div>

        <h1 class="text-2xl font-black text-primary uppercase tracking-tighter italic">
            PALAIS DES PIONNIERS
        </h1>

        <p class="text-xs text-slate-500 font-bold">
            Centre de Formation et de Culture
        </p>

        <p class="text-xs text-slate-400">
            Magnambougou / Dianéguéla, Bamako, Mali
        </p>

    </div>

    <div class="text-right">

        <span class="bg-amber-500 text-white px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-widest">
            <?= $estPartiel ? 'ACOMPTE VERSÉ — SOLDE AU GUICHET' : 'À PAYER AU GUICHET' ?>
        </span>

        <p class="text-sm mt-2 text-slate-900 font-mono font-bold italic">
            ID: #RESA-<?= (int) $bon['id'] ?>
        </p>

        <?php if (!empty($sf['echeance_premier_paiement'])): ?>

                <p class="text-xs text-amber-700 font-black mt-2">

                    <i class="fas fa-clock mr-1"></i>

                    À régler avant le

                    <?= date(
                        'd/m/Y à H:i',
                        $sf['echeance_premier_paiement']
                    ) ?>

                </p>

            <?php endif; ?>

    </div>

</div>

<div class="grid grid-cols-2 gap-8 mb-8 pb-8 border-b border-slate-100">

    <div>

        <h2 class="font-bold text-slate-400 uppercase text-[10px] mb-2 tracking-widest">
            Client
        </h2>

        <p class="font-black text-slate-800 text-lg">
            <?= e($nomClient) ?>
        </p>

        <p class="text-sm text-slate-500 font-medium">
            <?= e($telephoneClient) ?>
        </p>

    </div>

    <div class="text-right">

        <h2 class="font-bold text-slate-400 uppercase text-[10px] mb-2 tracking-widest">

            <?= $estSejour
                ? 'Séjour'
                : "Date de l'événement" ?>

        </h2>

        <?php if ($estSejour): ?>

            <p class="font-black text-slate-800 text-lg">

                <?= date(
                    'd/m/Y',
                    strtotime($bon['date_resa'])
                ) ?>

                →

                <?= date(
                    'd/m/Y',
                    strtotime($bon['date_depart'])
                ) ?>

            </p>

            <p class="text-xs text-slate-500 font-bold">

                <?= $nuitees ?>

                nuitée<?= $nuitees > 1 ? 's' : '' ?>

                <?= !empty($bon['petit_dejeuner'])
                    ? ' · petit-déjeuner inclus'
                    : '' ?>

            </p>

        <?php else: ?>

            <p class="font-black text-slate-800 text-lg">

                <?= date(
                    'd/m/Y',
                    strtotime($bon['date_resa'])
                ) ?>

            </p>

        <?php endif; ?>

        <p class="text-xs text-slate-500 italic font-medium">
            Merci de vous présenter au guichet pour régler
        </p>

    </div>

</div>

<div class="mb-10">

    <h2 class="font-bold text-slate-400 uppercase text-[10px] mb-4 tracking-widest">
        Détails du service
    </h2>

    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100">

        <table class="w-full text-left">

            <tr class="border-b border-slate-200">

                <td class="py-3 text-sm text-slate-600 font-bold">
                    Espace réservé
                </td>

                <td class="py-3 font-black text-right text-slate-800">
                    <?= e($bon['espace_nom']) ?>
                </td>

            </tr>

            <tr class="border-b border-slate-200">

                <td class="py-3 text-sm text-slate-600 font-bold">
                    Option/Tarif
                </td>

                <td class="py-3 font-black text-right text-slate-800">
                    <?= e($bon['tarif_nom'] ?? 'Standard') ?>
                </td>

            </tr>

            <?php if ($estSejour): ?>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-slate-600 font-bold">
                        Durée
                    </td>

                    <td class="py-3 font-black text-right text-slate-800">

                        <?= $nuitees ?>

                        nuitée<?= $nuitees > 1 ? 's' : '' ?>

                        ×

                        <?= number_format(
                            (float) $bon['montant'],
                            0,
                            ',',
                            ' '
                        ) ?>

                        F CFA

                    </td>

                </tr>

                <?php if ($quantiteResa > 1): ?>

                    <tr class="border-b border-slate-200">

                        <td class="py-3 text-sm text-slate-600 font-bold">
                            Nombre de chambres
                        </td>

                        <td class="py-3 font-black text-right text-slate-800">
                            <?= $quantiteResa ?>
                        </td>

                    </tr>

                <?php endif; ?>

            <?php endif; ?>

            <?php if (!$estSejour && !empty($bon['vip'])): ?>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-slate-600 font-bold">
                        Accueil VIP
                    </td>

                    <td class="py-3 font-black text-right">

                        +

                        <?= number_format(
                            (float) ($bon['prix_vip'] ?? 0),
                            0,
                            ',',
                            ' '
                        ) ?>

                        F CFA

                    </td>

                </tr>

            <?php endif; ?>

            <?php if ($estPartiel): ?>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-slate-600 font-bold">
                        Montant initial
                    </td>

                    <td class="py-3 font-black text-right text-slate-800">

                        <?= number_format(
                            $montantReferenceComplet,
                            0,
                            ',',
                            ' '
                        ) ?>

                        F CFA

                    </td>

                </tr>

                <?php if ($montantReductionFacture > 0): ?>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-orange-700 font-bold">
                        <?= $libelleReductionFacture ? e($libelleReductionFacture) : 'Réduction accordée' ?>
                    </td>

                    <td class="py-3 font-black text-right text-orange-700">
                        - <?= number_format($montantReductionFacture, 0, ',', ' ') ?> F CFA
                    </td>

                </tr>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-slate-600 font-bold">
                        Net à payer
                    </td>

                    <td class="py-3 font-black text-right text-slate-800">
                        <?= number_format($sf['net_du'], 0, ',', ' ') ?> F CFA
                    </td>

                </tr>

                <?php endif; ?>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-sky-700 font-bold">

                        <i class="fas fa-coins mr-1"></i>

                        Acompte(s) versé(s)

                    </td>

                    <td class="py-3 font-black text-right text-sky-700">

                        -

                        <?= number_format(
                            $sf['paye_net'],
                            0,
                            ',',
                            ' '
                        ) ?>

                        F CFA

                    </td>

                </tr>

                <tr>

                    <td class="py-5 text-primary font-black text-xl uppercase">
                        Solde restant dû
                    </td>

                    <td class="py-5 font-black text-3xl text-right">

                        <?= number_format(
                            $soldeRestantFacture,
                            0,
                            ',',
                            ' '
                        ) ?>

                        <span class="text-sm">
                            F CFA
                        </span>

                    </td>

                </tr>

                <?php if (!empty($sf['echeance_solde'])): ?>

                    <tr>

                        <td colspan="2" class="pb-3">

                            <p class="text-xs font-bold bg-red-50 rounded-xl px-4 py-2.5 inline-block">

                                <i class="fas fa-clock mr-1.5"></i>

                                <?= $sf['en_retard'] ? 'Échéance du solde dépassée — à régler au guichet (échéance :' : 'Solde à régler avant le' ?>

                                <?= date(
                                    'd/m/Y à H:i',
                                    $sf['echeance_solde']
                                ) ?><?= $sf['en_retard'] ? ')' : '' ?>

                            </p>

                        </td>

                    </tr>

                <?php endif; ?>

            <?php else: ?>

                <?php if ($montantReductionFacture > 0): ?>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-slate-600 font-bold">
                        Montant initial
                    </td>

                    <td class="py-3 font-black text-right text-slate-800">
                        <?= number_format($montantReferenceComplet, 0, ',', ' ') ?> F CFA
                    </td>

                </tr>

                <tr class="border-b border-slate-200">

                    <td class="py-3 text-sm text-orange-700 font-bold">
                        <?= $libelleReductionFacture ? e($libelleReductionFacture) : 'Réduction accordée' ?>
                    </td>

                    <td class="py-3 font-black text-right text-orange-700">
                        - <?= number_format($montantReductionFacture, 0, ',', ' ') ?> F CFA
                    </td>

                </tr>

                <?php endif; ?>

                <tr>

                    <td class="py-5 text-primary font-black text-xl uppercase">
                        Total à payer
                    </td>

                    <td class="py-5 font-black text-3xl text-right text-primary">

                        <?= number_format(
                            $montantFinal,
                            0,
                            ',',
                            ' '
                        ) ?>

                        <span class="text-sm">
                            F CFA
                        </span>

                    </td>

                </tr>

            <?php endif; ?>

        </table>

        <?php if ($estPartiel && count($tousLesPaiements) > 1): ?>

            <div class="mt-4 pt-4 border-t border-slate-200">

                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
                    Historique des versements
                </p>

                <?php foreach ($tousLesPaiements as $vz): ?>

                    <div class="flex justify-between text-xs text-slate-600 py-1">

                        <span>

                            <?= date(
                                'd/m/Y',
                                strtotime($vz['created_at'])
                            ) ?>

                            —

                            <?= e(
                                $modeLabels[$vz['mode']]
                                ?? $vz['mode']
                            ) ?>

                        </span>

                        <span class="font-bold">

                            <?= number_format(
                                (float) $vz['montant'],
                                0,
                                ',',
                                ' '
                            ) ?>

                            F CFA

                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<div class="border-2 border-primary/20 bg-primary/5 rounded-2xl p-6 mb-8">

    <h3 class="text-primary font-black text-xs uppercase mb-3 tracking-widest">

        <span class="h-2 w-2 bg-amber-500 rounded-full inline-block mr-2"></span>

        Modalités de paiement

    </h3>

    <p class="text-sm text-slate-700 leading-relaxed font-medium">

        Le règlement s'effectue exclusivement au guichet administratif
        (espèces, Orange Money ou Moov Money).
        Présentez ce document pour finaliser votre réservation :

    </p>

    <div class="mt-4 flex items-center gap-4 bg-white p-3 rounded-xl border border-primary/10 shadow-sm w-fit">

        <div class="bg-primary text-white h-10 w-10 rounded-full flex items-center justify-center">

            <svg
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                />
            </svg>

        </div>

        <span class="text-2xl font-black text-slate-900 tracking-tighter">
            +223 66 24 90 77
        </span>

    </div>

</div>

<div class="text-[10px] text-slate-400 text-center uppercase tracking-widest font-black italic">

    *** Ce document fait office de bon de réservation —
    à présenter au guichet pour paiement ***

</div>

<?php endif; ?>

<!-- INFORMATION INSTITUTIONNELLE -->

<div class="border border-amber-200 bg-amber-50 rounded-2xl p-4 mb-6 flex items-start gap-3">

    <i class="fas fa-landmark text-amber-500 mt-0.5"></i>

    <p class="text-xs text-amber-700 leading-relaxed">

        Comme tout espace du Palais, celui-ci peut exceptionnellement
        être réquisitionné pour un besoin institutionnel prioritaire,
        même après validation ou paiement. Le cas échéant, vous seriez
        notifié et pourriez choisir un remboursement, une nouvelle date
        ou un autre espace.

    </p>

</div>

<!-- ACTIONS -->

<div class="mt-10 flex flex-col md:flex-row justify-center gap-4 no-print">

    <button
        type="button"
        onclick="telechargerPDF(
            '<?= $estPaye
                ? 'Facture'
                : ($estPartiel
                    ? 'Facture-acompte'
                    : 'Bon-de-reservation') ?>-RESA-<?= (int) $bon['id'] ?>.pdf'
        )"
        class="bg-black text-white px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-800 transition shadow-xl shadow-black/20 flex items-center justify-center gap-2"
    >

        <svg
            class="w-4 h-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"
            />
        </svg>

        Télécharger

        <?= $estPaye
            ? 'la facture'
            : ($estPartiel
                ? "la facture d'acompte"
                : 'le bon') ?>

    </button>

    <?php if ($bon['statut_paiement'] === 'paye'): ?>

        <a
            href="?id=<?= $id ?><?= $forceBon ? '' : '&type=bon' ?><?= $from ? '&from=' . urlencode($from) : '' ?>"
            class="bg-white border-2 border-slate-200 text-slate-700 px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:border-primary transition text-center flex items-center justify-center gap-2"
        >

            <?= $forceBon
                ? 'Voir la facture'
                : "Voir le bon d'origine" ?>

        </a>

    <?php endif; ?>

    <?php

    $retourHref = 'mon-compte.php';

    if ($estAdminAutorise) {

        $retourHref = match ($from) {

            'paiements' =>
                'admin/paiements.php',

            'guichet' =>
                'admin/guichet.php',

            default =>
                'admin/reservations.php?id=' . $id,

        };
    }

    ?>

    <a
        href="<?= e($retourHref) ?>"
        class="bg-slate-200 text-slate-700 px-8 py-4 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-300 transition text-center flex items-center justify-center gap-2"
    >

        <svg
            class="w-4 h-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
            />
        </svg>

        Retour

    </a>

</div>

</div>

</body>
</html>