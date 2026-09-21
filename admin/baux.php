<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','admin_comptable','admin_espaces']);

$pdo      = db();
$role     = $_SESSION['role'] ?? '';
$readonly = !in_array($role, ['admin_comptable'], true);
$msg      = null;

$dureeParType = ['mensuel' => 1, 'trimestriel' => 3, 'semestriel' => 6, 'annuel' => 12];
$labelType    = ['mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel', 'semestriel' => 'Semestriel', 'annuel' => 'Annuel'];

// periode_actuelle_debut() est maintenant définie dans includes/auth.php (partagée avec le tableau de bord)

$moisFr = ['01'=>'janvier','02'=>'février','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin','07'=>'juillet','08'=>'août','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'décembre'];

/**
 * Libellé lisible d'une période selon sa durée (en mois) et sa date de début.
 */
function periode_label(string $debut, int $dureeMois): string {
    global $moisFr;
    $d = strtotime($debut);
    if ($dureeMois >= 12) return date('Y', $d);
    if ($dureeMois >= 3) {
        $fin = strtotime("+" . ($dureeMois - 1) . " months", $d);
        return ucfirst($moisFr[date('m',$d)]) . ' – ' . ucfirst($moisFr[date('m',$fin)]) . ' ' . date('Y', $d);
    }
    return ucfirst($moisFr[date('m',$d)]) . ' ' . date('Y', $d);
}

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } elseif (($_POST['action'] ?? '') === 'relancer') {
        $espaceIdRelance = (int)($_POST['espace_id'] ?? 0);
        $esp = $pdo->prepare("SELECT nom, gerant_user_id FROM espaces WHERE id = ?");
        $esp->execute([$espaceIdRelance]); $esp = $esp->fetch();

        if ($esp && $esp['gerant_user_id']) {
            notify('', 'relance_bail', "Rappel : le paiement de votre bail pour « {$esp['nom']} » est attendu. Merci de vous rapprocher du comptable du Palais.", "mon-compte.php?tab=baux", (int)$esp['gerant_user_id']);
            log_activity('bail_relance', 'espaces', "Relance envoyée pour le bail de « {$esp['nom']} »");
            $msg = ['ok', 'Relance envoyée au client.'];
        } else {
            $msg = ['err', 'Ce bail n\'est pas relié à un compte client — impossible de le notifier sur le site.'];
        }
    } else {
        $espaceId  = (int)($_POST['espace_id'] ?? 0);
        $periode   = trim($_POST['periode_debut'] ?? '');
        $dureeMois = (int)($_POST['duree_mois'] ?? 1);
        $montant   = (float)($_POST['montant'] ?? 0);
        $montantRef= !empty($_POST['montant_reference']) ? (float)$_POST['montant_reference'] : null;
        $motifRed  = trim($_POST['motif_reduction'] ?? '');
        $mode      = $_POST['mode'] ?? '';
        $ref       = trim($_POST['reference'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $modesValides = ['especes','orange_money','moov_money','virement','cheque'];
        $estReduction = ($montantRef !== null && $montant < $montantRef);

        if (!$espaceId || !$periode || $montant <= 0 || !in_array($mode, $modesValides, true)) {
            $msg = ['err', 'Espace, période, montant ou mode de paiement invalide.'];
        } elseif ($estReduction && $motifRed === '') {
            $msg = ['err', 'Une réduction a été détectée : merci d\'indiquer le motif.'];
        } else {
            try {
                $pdo->prepare("INSERT INTO bail_paiements (espace_id, periode_debut, duree_mois, montant, montant_reference, motif_reduction, mode, reference, note, enregistre_par) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$espaceId, $periode, $dureeMois, $montant, $montantRef, $estReduction ? $motifRed : null, $mode, $ref, $note, $_SESSION['user_id']]);
                $paiementId = (int)$pdo->lastInsertId();
                $numeroRecu = ref_recu($paiementId);
                $espNom = $pdo->prepare("SELECT nom FROM espaces WHERE id = ?"); $espNom->execute([$espaceId]); $espNom = $espNom->fetchColumn();

                log_activity('bail_loyer_encaisse', 'reservations', "Loyer $numeroRecu de $espNom encaissé — " . number_format($montant,0,',',' ') . " FCFA");
                notify('superadmin', 'paiement_recu', "Loyer $numeroRecu encaissé — $espNom", "baux.php");
                notify('ministre', 'paiement_recu', "Loyer $numeroRecu encaissé — $espNom", "baux.php");

                if ($estReduction) {
                    $texteReduction = "Réduction sur le loyer $numeroRecu de $espNom : -" . number_format($montantRef - $montant,0,',',' ') . " FCFA. Motif : $motifRed";
                    notify('superadmin', 'reduction_accordee', $texteReduction, "baux.php");
                    notify('ministre', 'reduction_accordee', $texteReduction, "baux.php");
                    log_activity('reduction_accordee', 'reservations', $texteReduction);
                }
                $msg = ['ok', "Loyer $numeroRecu enregistré" . ($estReduction ? ' (avec réduction)' : '') . "."];
            } catch (PDOException $e) {
                $msg = ['err', 'Un paiement existe déjà pour cet espace sur cette période.'];
            }
        }
    }
}

// Espaces actuellement en bail
$espacesBail = $pdo->query("
    SELECT e.id, e.nom, e.type_bail, e.gerant_nom, e.gerant_prenom, e.gerant_contact, e.gerant_user_id,
    e.resiliation_demandee, e.resiliation_demandee_le, e.resiliation_note,
    (SELECT COALESCE(SUM(montant),0) FROM tarifs WHERE espace_id = e.id AND est_bail = 1) AS loyer_suggere
    FROM espaces e
    WHERE e.gerant_externe IS NOT NULL AND e.gerant_externe != ''
    ORDER BY e.nom ASC
")->fetchAll();

$stmtPeriode = $pdo->prepare("SELECT * FROM bail_paiements WHERE espace_id = ? AND periode_debut = ?");

// Notifier le comptable + admin_espaces quand une échéance de bail arrive à
// terme sans paiement encore enregistré — une seule fois par espace/période
// (on vérifie qu'aucune notification avec ce lien exact n'existe déjà).
$stmtNotifExiste = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE lien = ?");
foreach ($espacesBail as $espCheck) {
    $typeBailCheck  = $espCheck['type_bail'] ?: 'mensuel';
    $periodeCheck   = periode_actuelle_debut($typeBailCheck);
    $stmtPeriode->execute([$espCheck['id'], $periodeCheck]);
    if ($stmtPeriode->fetch()) continue; // déjà payé pour cette période

    $lienUnique = "baux.php?echeance={$espCheck['id']}-{$periodeCheck}";
    $stmtNotifExiste->execute([$lienUnique]);
    if ((int)$stmtNotifExiste->fetchColumn() > 0) continue; // déjà notifié pour cette échéance

    $texteEcheance = "Échéance de bail : le loyer de « {$espCheck['nom']} » ({$dureeParType[$typeBailCheck]} mois) est dû depuis le " . date('d/m/Y', strtotime($periodeCheck)) . " — encaissement à effectuer.";
    notify('admin_comptable', 'echeance_bail', $texteEcheance, $lienUnique);
    notify('admin_espaces', 'echeance_bail', $texteEcheance, $lienUnique);
}

$historique = $pdo->query("
    SELECT bp.*, e.nom AS espace_nom, u.nom_complet AS enregistre_par_nom
    FROM bail_paiements bp
    JOIN espaces e ON e.id = bp.espace_id
    JOIN users u ON u.id = bp.enregistre_par
    ORDER BY bp.periode_debut DESC, bp.created_at DESC
    LIMIT 50
")->fetchAll();

$modeLabels = ['especes'=>'Espèces','orange_money'=>'Orange Money','moov_money'=>'Moov Money','virement'=>'Virement','cheque'=>'Chèque'];

$pageTitle = "Suivi des Baux";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Suivi des Baux</h1>
      <p class="text-sm text-slate-500 mt-0.5">Loyers des espaces loués à un tiers, selon la périodicité de chaque contrat</p>
    </div>
    <div class="flex items-center gap-3">
      <a href="export.php?type=baux" target="_blank"
         class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
        <i class="fas fa-file-excel"></i> Exporter (Excel)
      </a>
      <?php if ($readonly): ?>
      <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <?php if (!$espacesBail): ?>
  <p class="text-sm text-slate-400 italic py-10 text-center">Aucun espace n'est actuellement géré en bail.</p>
  <?php else: ?>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
      <h2 class="font-black text-primary text-sm uppercase italic flex items-center gap-2"><i class="fas fa-file-signature text-accent"></i> Espaces en bail — période en cours</h2>
    </div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($espacesBail as $esp):
          $typeBail   = $esp['type_bail'] ?: 'mensuel';
          $dureeMois  = $dureeParType[$typeBail] ?? 1;
          $periodeDeb = periode_actuelle_debut($typeBail);
          $stmtPeriode->execute([$esp['id'], $periodeDeb]);
          $paye = $stmtPeriode->fetch();
          $montantAttendu = (float)$esp['loyer_suggere'] * $dureeMois;
      ?>
      <div class="p-5">
        <?php if ($esp['resiliation_demandee']): ?>
        <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-start gap-2.5">
          <i class="fas fa-exclamation-triangle text-accent mt-0.5"></i>
          <div>
            <p class="text-xs font-black text-accent uppercase tracking-widest">Résiliation demandée le <?= date('d/m/Y', strtotime($esp['resiliation_demandee_le'])) ?></p>
            <?php if ($esp['resiliation_note']): ?><p class="text-xs text-red-700 mt-1">« <?= e($esp['resiliation_note']) ?> »</p><?php endif; ?>
            <p class="text-[10px] text-red-600 mt-1.5">Utilisez "Terminer le bail" dans Admin &gt; Espaces une fois la résiliation finalisée avec le client.</p>
          </div>
        </div>
        <?php endif; ?>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-black text-primary text-sm"><?= e($esp['nom']) ?></p>
              <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= $labelType[$typeBail] ?></span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              <?= e(trim(($esp['gerant_prenom'] ?? '').' '.($esp['gerant_nom'] ?? '')) ?: 'Gestionnaire non renseigné') ?>
              <?php if ($esp['gerant_contact']): ?> · <?= e($esp['gerant_contact']) ?><?php endif; ?>
            </p>
            <p class="text-xs text-slate-500 mt-1"><i class="fas fa-calendar text-accent text-[10px] mr-1"></i>Période : <?= periode_label($periodeDeb, $dureeMois) ?>
              <?php if ($montantAttendu > 0): ?> · <i class="fas fa-tag text-accent text-[10px] mr-1"></i><?= number_format($montantAttendu,0,',',' ') ?> FCFA attendus<?php endif; ?>
            </p>
          </div>
          <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
            <?php if ($paye): ?>
            <span class="text-[10px] font-black px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700"><i class="fas fa-check-circle mr-1"></i>Payé (<?= number_format((float)$paye['montant'],0,',',' ') ?> FCFA)</span>
            <?php else: ?>
            <span class="text-[10px] font-black px-3 py-1.5 rounded-full bg-amber-100 text-amber-700"><i class="fas fa-clock mr-1"></i>En attente</span>
            <?php if (!$readonly && $esp['gerant_user_id']): ?>
            <form method="POST" onsubmit="return confirm('Envoyer un rappel de paiement au client ?')">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="relancer">
              <input type="hidden" name="espace_id" value="<?= $esp['id'] ?>">
              <button type="submit" class="bg-slate-100 text-slate-600 text-[10px] font-black uppercase px-3 sm:px-4 py-2 rounded-xl hover:bg-slate-200 transition">
                <i class="fas fa-bell mr-1"></i><span class="hidden sm:inline">Relancer</span>
              </button>
            </form>
            <?php endif; ?>
            <?php if (!$readonly): ?>
            <button onclick="document.getElementById('formBail-<?= $esp['id'] ?>').classList.toggle('hidden')"
                    class="bg-accent text-white text-[10px] font-black uppercase px-4 py-2 rounded-xl hover:bg-accent-dark transition">
              <i class="fas fa-plus-circle mr-1"></i>Encaisser
            </button>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$readonly && !$paye): ?>
        <form method="POST" id="formBail-<?= $esp['id'] ?>" class="hidden mt-4 bg-slate-50 rounded-2xl border border-slate-100 p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3"
              onsubmit="return checkReductionBail(<?= $esp['id'] ?>)">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="espace_id" value="<?= $esp['id'] ?>">
          <input type="hidden" name="periode_debut" value="<?= $periodeDeb ?>">
          <input type="hidden" name="duree_mois" value="<?= $dureeMois ?>">
          <input type="hidden" name="montant_reference" value="<?= $montantAttendu ?: '' ?>">
          <div>
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Montant (FCFA)</label>
            <input type="number" name="montant" id="montantBail-<?= $esp['id'] ?>" step="100" required
                   value="<?= $montantAttendu ?: '' ?>"
                   oninput="toggleReductionBail(<?= $esp['id'] ?>, <?= $montantAttendu ?: 0 ?>)"
                   class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2 font-black text-accent outline-none text-sm">
            <?php if ($montantAttendu): ?><p class="text-[9px] text-slate-400 mt-1">Attendu : <?= number_format($montantAttendu,0,',',' ') ?> FCFA</p><?php endif; ?>
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Mode</label>
            <select name="mode" required class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2 font-bold text-primary outline-none text-sm">
              <?php foreach ($modeLabels as $val=>$lab): ?><option value="<?= $val ?>"><?= $lab ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Référence</label>
            <input type="text" name="reference" class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2 font-bold text-primary outline-none text-sm">
          </div>
          <div>
            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Note</label>
            <input type="text" name="note" class="w-full rounded-xl border-2 border-slate-200 bg-white px-3 py-2 font-bold text-primary outline-none text-sm">
          </div>
          <div id="reductionBail-<?= $esp['id'] ?>" class="hidden sm:col-span-2 lg:col-span-4 bg-orange-50 border-2 border-orange-200 rounded-xl p-4">
            <label class="block text-[10px] font-black uppercase tracking-widest text-orange-700 mb-2">
              <i class="fas fa-percent mr-1"></i> Réduction détectée — motif obligatoire <span class="text-accent">*</span>
            </label>
            <input type="text" name="motif_reduction" placeholder="Ex : accord de la direction, geste commercial..."
                   class="w-full rounded-xl border-2 border-orange-200 bg-white px-3 py-2.5 font-semibold text-primary outline-none focus:border-orange-400 text-sm">
            <p class="text-[10px] text-orange-600 mt-1.5">Notifiée à l'Admin DG et au Ministre.</p>
          </div>
          <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
            <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">Confirmer l'encaissement</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
      <h2 class="font-black text-primary text-sm uppercase italic flex items-center gap-2"><i class="fas fa-history text-accent"></i> Historique des loyers encaissés</h2>
    </div>
    <?php if (!$historique): ?>
    <p class="text-sm text-slate-400 italic p-6 text-center">Aucun loyer encaissé pour l'instant.</p>
    <?php else: ?>
    <div class="divide-y divide-slate-50">
      <?php foreach ($historique as $h): ?>
      <div class="flex items-center justify-between px-5 py-3 flex-wrap gap-2">
        <div>
          <p class="font-bold text-primary text-sm">
            <?= e($h['espace_nom']) ?> — <?= periode_label($h['periode_debut'], (int)$h['duree_mois']) ?>
            <?php if (!empty($h['motif_reduction'])): ?><span class="ml-1 text-[9px] font-black uppercase bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full"><i class="fas fa-percent"></i> Réduction</span><?php endif; ?>
          </p>
          <p class="text-[10px] text-slate-400"><?= ref_recu((int)$h['id']) ?> · <?= e($modeLabels[$h['mode']] ?? $h['mode']) ?><?= $h['reference'] ? ' · '.e($h['reference']) : '' ?> · encaissé par <?= e($h['enregistre_par_nom']) ?></p>
        </div>
        <span class="font-black text-emerald-600 text-sm"><?= number_format((float)$h['montant'],0,',',' ') ?> FCFA</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div>

<script>
function toggleReductionBail(id, montantAttendu) {
    const montant = parseFloat(document.getElementById('montantBail-'+id).value) || 0;
    const field = document.getElementById('reductionBail-'+id);
    const isReduction = montantAttendu > 0 && montant < montantAttendu;
    field.classList.toggle('hidden', !isReduction);
    field.querySelector('input[name="motif_reduction"]').required = isReduction;
}
function checkReductionBail(id) {
    const field = document.getElementById('reductionBail-'+id);
    if (!field.classList.contains('hidden')) {
        const motif = field.querySelector('input[name="motif_reduction"]').value.trim();
        if (!motif) { alert('Merci d\'indiquer le motif de la réduction accordée.'); return false; }
    }
    return true;
}
</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>
