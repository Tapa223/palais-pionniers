<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_espaces','ministre','admin_comptable']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;
$cycle    = services_cycle_disponible($pdo);

$transitions = [
    'prendre_en_charge' => ['en_attente', 'en_cours', false, 'prise en charge', 'est prise en charge par nos services'],
    'realiser'          => ['en_cours', 'realisee', false, 'réalisée', 'a été réalisée'],
    'refuser'           => ['en_attente', 'refusee', true, 'refusée', "n'a pas pu être acceptée"],
    'annuler'           => ['en_cours', 'annulee', true, 'annulée', 'a été annulée'],
];

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $id     = (int)($_POST['id'] ?? 0);
        $note   = trim($_POST['note_traitement'] ?? '');
        $action = $_POST['action'] ?? ($cycle ? '' : 'marquer_traitee');

        if ($action === 'supprimer') {
            if (!is_superadmin()) {
                $msg = ['err', 'Seule la Direction peut supprimer une demande.'];
            } else {
                $dSupp = $pdo->prepare("SELECT d.id, s.nom, u.nom_complet FROM demandes_services d JOIN services_annexes s ON s.id = d.service_id JOIN users u ON u.id = d.user_id WHERE d.id = ?");
                $dSupp->execute([$id]);
                if ($dSupp = $dSupp->fetch()) {
                    $pdo->prepare("DELETE FROM demandes_services WHERE id = ?")->execute([$id]);
                    log_activity('demande_service_supprimee', 'espaces', "Demande de service #$id supprimée (erreur ou test) — {$dSupp['nom']}, {$dSupp['nom_complet']}");
                    $msg = ['ok', 'Demande supprimée définitivement.'];
                } else {
                    $msg = ['err', 'Demande introuvable.'];
                }
            }
        } elseif (!$cycle) {
            if ($id) {
                $pdo->prepare("UPDATE demandes_services SET statut = 'traitee', traite_par = ?, date_traitement = NOW(), note_traitement = ? WHERE id = ? AND statut = 'en_attente'")
                    ->execute([$_SESSION['user_id'], $note ?: null, $id]);
                log_activity('demande_service_traitee', 'espaces', "Demande de service #$id marquée traitée");
                $msg = ['ok', 'Demande marquée comme traitée.'];
            }
        } elseif ($id && isset($transitions[$action])) {
            [$avant, $apres, $motifRequis, $libelle, $texteClient] = $transitions[$action];

            if ($motifRequis && mb_strlen($note) < 3) {
                $msg = ['err', 'Merci d\'indiquer le motif (il sera communiqué au client).'];
            } else {
                if ($action === 'prendre_en_charge') {
                    $st = $pdo->prepare("UPDATE demandes_services SET statut = 'en_cours', pris_en_charge_par = ?, date_prise_en_charge = NOW() WHERE id = ? AND statut = 'en_attente'");
                    $st->execute([$_SESSION['user_id'], $id]);
                } else {
                    $st = $pdo->prepare("UPDATE demandes_services SET statut = ?, traite_par = ?, date_traitement = NOW(), note_traitement = ? WHERE id = ? AND statut = ?");
                    $st->execute([$apres, $_SESSION['user_id'], $note ?: null, $id, $avant]);
                }

                if ($st->rowCount() !== 1) {
                    $msg = ['err', 'Cette demande a déjà changé de statut : actualisez la page.'];
                } else {
                    $info = $pdo->prepare("SELECT ds.user_id, s.nom FROM demandes_services ds JOIN services_annexes s ON s.id = ds.service_id WHERE ds.id = ?");
                    $info->execute([$id]);
                    $info = $info->fetch();
                    if ($info) {
                        notify('', 'demande_service_' . $apres,
                            "Votre demande de service « {$info['nom']} » $texteClient." . ($note !== '' && $motifRequis ? " Motif : $note" : ($note !== '' ? " $note" : '')),
                            'mon-compte.php?tab=services', (int)$info['user_id']);
                    }
                    log_activity('demande_service_' . $apres, 'espaces', "Demande de service #$id $libelle" . ($note !== '' ? " — $note" : ''));
                    $msg = ['ok', 'Demande ' . $libelle . '.'];
                }
            }
        } else {
            $msg = ['err', 'Action non reconnue.'];
        }
    }
}

$filtres = $cycle
    ? ['en_attente' => 'En attente', 'en_cours' => 'En cours', 'realisee' => 'Réalisées', 'fermees' => 'Refusées / annulées', 'toutes' => 'Toutes']
    : ['en_attente' => 'En attente', 'traitee' => 'Traitées'];
$filterStatut = isset($filtres[$_GET['statut'] ?? '']) ? $_GET['statut'] : 'en_attente';
$conditions = [
    'en_attente' => "ds.statut = 'en_attente'",
    'en_cours'   => "ds.statut = 'en_cours'",
    'realisee'   => "ds.statut IN ('realisee','traitee')",
    'traitee'    => "ds.statut = 'traitee'",
    'fermees'    => "ds.statut IN ('refusee','annulee')",
    'toutes'     => '1=1',
];
$compteurs = [];
foreach (array_keys($filtres) as $f) {
    $compteurs[$f] = (int)$pdo->query("SELECT COUNT(*) FROM demandes_services ds WHERE " . $conditions[$f])->fetchColumn();
}

$demandes = $pdo->query("
    SELECT ds.*, s.nom AS service_nom, s.montant, s.unite, u.nom_complet, u.telephone, u.email,
           admin.nom_complet AS traite_par_nom
           " . ($cycle ? ", pec.nom_complet AS pris_en_charge_nom" : "") . "
    FROM demandes_services ds
    JOIN services_annexes s ON s.id = ds.service_id
    JOIN users u ON u.id = ds.user_id
    LEFT JOIN users admin ON admin.id = ds.traite_par
    " . ($cycle ? "LEFT JOIN users pec ON pec.id = ds.pris_en_charge_par" : "") . "
    WHERE " . $conditions[$filterStatut] . "
    ORDER BY ds.created_at DESC
")->fetchAll();

$pageTitle = "Demandes de services";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Demandes de services</h1>
      <p class="text-sm text-slate-500 mt-0.5">Lavage automobile, support publicitaire et autres prestations demandées depuis les comptes clients</p>
    </div>
    <a href="export.php?type=demandes_services" target="_blank" rel="noopener" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition"><i class="fas fa-file-excel"></i> Exporter (Excel)</a>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="flex flex-wrap gap-2 mb-5">
    <?php foreach ($filtres as $val => $lab): ?>
    <a href="?statut=<?= $val ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filterStatut===$val ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>"><?= $lab ?> (<?= $compteurs[$val] ?>)</a>
    <?php endforeach; ?>
  </div>

  <div class="space-y-4">
    <?php foreach ($demandes as $d): [$libSt, $clsSt] = libelle_statut_service($d['statut']); ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
          <p class="font-black text-primary text-sm"><?= e($d['service_nom']) ?> <span class="text-[10px] font-mono text-slate-400">#<?= (int)$d['id'] ?></span></p>
          <p class="text-xs text-slate-400 mt-0.5"><?= e($d['nom_complet']) ?> · <?= e($d['telephone']) ?><?= $d['email'] ? ' · '.e($d['email']) : '' ?></p>
        </div>
        <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full <?= $clsSt ?>"><?= e($libSt) ?></span>
      </div>
      <p class="text-xs text-slate-500 mb-3">Tarif indicatif : <?= number_format((float)$d['montant'],0,',',' ') ?> FCFA/<?= e($d['unite']) ?> · demandé le <?= date('d/m/Y à H:i', strtotime($d['created_at'])) ?></p>
      <?php if (!empty($d['message'])): ?>
      <p class="text-xs text-slate-600 bg-slate-50 rounded-xl p-3 italic mb-3">« <?= e($d['message']) ?> »</p>
      <?php endif; ?>

      <?php if (!empty($d['pris_en_charge_nom'])): ?>
      <p class="text-[10px] text-slate-400">Prise en charge par <?= e($d['pris_en_charge_nom']) ?> le <?= date('d/m/Y à H:i', strtotime($d['date_prise_en_charge'])) ?></p>
      <?php endif; ?>
      <?php if (in_array($d['statut'], ['realisee', 'traitee', 'refusee', 'annulee'], true)): ?>
      <p class="text-[10px] text-slate-400"><?= e($libSt) ?> par <?= e($d['traite_par_nom'] ?? '—') ?><?= $d['date_traitement'] ? ' le ' . date('d/m/Y à H:i', strtotime($d['date_traitement'])) : '' ?><?= $d['note_traitement'] ? ' — ' . e($d['note_traitement']) : '' ?></p>
      <?php endif; ?>

      <?php if (!$readonly && $cycle && in_array($d['statut'], ['en_attente', 'en_cours'], true)): ?>
      <form method="POST" class="flex flex-wrap items-center gap-2 mt-3">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <input type="text" name="note_traitement" maxlength="500" placeholder="Note ou motif (obligatoire pour refuser / annuler)"
               class="flex-1 min-w-[160px] rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-primary outline-none focus:border-primary">
        <?php if ($d['statut'] === 'en_attente'): ?>
        <button type="submit" name="action" value="prendre_en_charge" class="bg-primary text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-slate-800 transition">Prendre en charge</button>
        <button type="submit" name="action" value="refuser" onclick="return confirm('Refuser cette demande ?')" class="border border-rose-100 text-rose-500 text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-rose-50 transition">Refuser</button>
        <?php else: ?>
        <button type="submit" name="action" value="realiser" class="bg-emerald-500 text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-emerald-600 transition">Marquer réalisée</button>
        <button type="submit" name="action" value="annuler" onclick="return confirm('Annuler cette demande en cours ?')" class="border border-slate-200 text-slate-500 text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-slate-50 transition">Annuler</button>
        <?php endif; ?>
      </form>
      <?php elseif (!$readonly && !$cycle && $d['statut'] === 'en_attente'): ?>
      <form method="POST" class="flex flex-wrap items-center gap-2 mt-3">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <input type="text" name="note_traitement" placeholder="Note (optionnel)" class="flex-1 min-w-[160px] rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-primary outline-none focus:border-primary">
        <button type="submit" class="bg-emerald-500 text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-emerald-600 transition">Marquer traitée</button>
      </form>
      <?php endif; ?>
      <?php if (is_superadmin()): ?>
      <form method="POST" class="mt-3 text-right" onsubmit="return confirm('Supprimer définitivement cette demande de service ? (erreur ou test)')">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <button type="submit" class="text-[10px] font-black uppercase text-slate-400 hover:text-red-600 transition" title="Supprimer définitivement (erreur ou test)"><i class="fas fa-trash-alt mr-1"></i>Supprimer</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$demandes): ?>
    <p class="text-sm text-slate-400 italic text-center py-10">Aucune demande dans cette catégorie.</p>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>
