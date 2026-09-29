<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre', 'admin_comptable', 'admin_espaces']);

/*
 * Partenaires (CICB, ministère, institution…).
 * Un partenaire est une fiche « partenaires » à laquelle on associe un ou
 * plusieurs comptes clients existants : ils réservent par le circuit
 * normal (espaces, tarifs, disponibilités, paiements, bons), leurs
 * réservations étant attribuées au partenaire (reservations.partenaire_id).
 *
 * Gestion (créer, modifier, désactiver, associer des comptes) :
 * Direction (superadmin) et service comptable. Consultation et
 * statistiques : ministre et administration des espaces.
 */
$pdo      = db();
$role     = $_SESSION['role'] ?? '';
$peutGerer = is_superadmin() || $role === 'admin_comptable';
$msg      = $_SESSION['partenaires_flash'] ?? null;
unset($_SESSION['partenaires_flash']);

$installe = partenaires_disponibles($pdo);

if ($installe && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $retour = 'partenaires.php' . (!empty($_POST['partenaire_id']) ? '?id=' . (int)$_POST['partenaire_id'] : '');
    try {
        if (!csrf_check($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Requête invalide.');
        }
        if (!$peutGerer) {
            throw new RuntimeException("Vous n'êtes pas autorisé à modifier les partenaires.");
        }
        $action = $_POST['action'] ?? '';
        $pid    = (int)($_POST['partenaire_id'] ?? 0);

        if ($action === 'enregistrer') {
            $champs = [
                'nom'         => trim((string)($_POST['nom'] ?? '')),
                'type'        => trim((string)($_POST['type'] ?? '')),
                'contact_nom' => trim((string)($_POST['contact_nom'] ?? '')),
                'telephone'   => trim((string)($_POST['telephone'] ?? '')),
                'email'       => trim((string)($_POST['email'] ?? '')),
                'notes'       => trim((string)($_POST['notes'] ?? '')),
            ];
            if (mb_strlen($champs['nom']) < 2 || mb_strlen($champs['nom']) > 200) {
                throw new RuntimeException('Le nom du partenaire est obligatoire (2 à 200 caractères).');
            }
            if ($champs['email'] !== '' && !filter_var($champs['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Adresse e-mail invalide.');
            }
            foreach (['type' => 100, 'contact_nom' => 200, 'telephone' => 30, 'email' => 190] as $c => $max) {
                if (mb_strlen($champs[$c]) > $max) {
                    throw new RuntimeException('Un champ dépasse la longueur autorisée.');
                }
            }
            $valeurs = array_map(fn($v) => $v === '' ? null : $v, $champs);
            $valeurs['nom'] = $champs['nom'];

            if ($pid) {
                $pdo->prepare("UPDATE partenaires SET nom = ?, type = ?, contact_nom = ?, telephone = ?, email = ?, notes = ? WHERE id = ?")
                    ->execute([...array_values($valeurs), $pid]);
                log_activity('partenaire_modifie', 'users', "Partenaire #$pid modifié : {$champs['nom']}");
                $_SESSION['partenaires_flash'] = ['ok', 'Partenaire mis à jour.'];
            } else {
                $pdo->prepare("INSERT INTO partenaires (nom, type, contact_nom, telephone, email, notes) VALUES (?,?,?,?,?,?)")
                    ->execute(array_values($valeurs));
                $pid = (int)$pdo->lastInsertId();
                log_activity('partenaire_cree', 'users', "Partenaire #$pid créé : {$champs['nom']}");
                $_SESSION['partenaires_flash'] = ['ok', 'Partenaire créé. Associez-lui maintenant un ou plusieurs comptes.'];
            }
            $retour = 'partenaires.php?id=' . $pid;

        } elseif ($action === 'basculer_actif' && $pid) {
            $pdo->prepare("UPDATE partenaires SET actif = 1 - actif WHERE id = ?")->execute([$pid]);
            $actif = (int)$pdo->query("SELECT actif FROM partenaires WHERE id = " . $pid)->fetchColumn();
            log_activity('partenaire_' . ($actif ? 'active' : 'desactive'), 'users', "Partenaire #$pid " . ($actif ? 'réactivé' : 'désactivé'));
            $_SESSION['partenaires_flash'] = ['ok', $actif
                ? 'Partenaire réactivé.'
                : 'Partenaire désactivé : ses comptes réservent désormais comme des clients classiques (l\'historique reste attribué).'];

        } elseif ($action === 'associer' && $pid) {
            $email = trim((string)($_POST['email_compte'] ?? ''));
            $st = $pdo->prepare("SELECT id, nom_complet, role, partenaire_id FROM users WHERE email = ?");
            $st->execute([$email]);
            $u = $st->fetch();
            if (!$u) {
                throw new RuntimeException('Aucun compte ne correspond à cette adresse e-mail. Le partenaire doit d\'abord créer son compte client (inscription).');
            }
            if ($u['role'] !== 'user') {
                throw new RuntimeException('Seul un compte client peut être associé à un partenaire (pas un compte d\'administration).');
            }
            if ($u['partenaire_id'] && (int)$u['partenaire_id'] !== $pid) {
                throw new RuntimeException('Ce compte est déjà associé à un autre partenaire. Dissociez-le d\'abord.');
            }
            $pdo->prepare("UPDATE users SET partenaire_id = ? WHERE id = ?")->execute([$pid, (int)$u['id']]);
            $nomP = (string)$pdo->query("SELECT nom FROM partenaires WHERE id = " . $pid)->fetchColumn();
            notify('', 'partenaire_associe', "Votre compte est désormais associé au partenaire « $nomP » du Palais des Pionniers.", 'mon-compte.php', (int)$u['id']);
            log_activity('partenaire_compte_associe', 'users', "Compte #{$u['id']} associé au partenaire #$pid");
            $_SESSION['partenaires_flash'] = ['ok', "Compte « {$u['nom_complet']} » associé au partenaire."];

        } elseif ($action === 'dissocier' && $pid) {
            $uid = (int)($_POST['user_id'] ?? 0);
            $pdo->prepare("UPDATE users SET partenaire_id = NULL WHERE id = ? AND partenaire_id = ?")->execute([$uid, $pid]);
            log_activity('partenaire_compte_dissocie', 'users', "Compte #$uid dissocié du partenaire #$pid");
            $_SESSION['partenaires_flash'] = ['ok', 'Compte dissocié (les réservations passées restent attribuées au partenaire).'];

        } else {
            throw new RuntimeException('Action non reconnue.');
        }
    } catch (RuntimeException $e) {
        $_SESSION['partenaires_flash'] = ['err', $e->getMessage()];
    } catch (PDOException $e) {
        error_log('partenaires.php : ' . $e->getMessage());
        $_SESSION['partenaires_flash'] = ['err', 'Erreur technique : opération non enregistrée.'];
    }
    header('Location: ' . $retour);
    exit;
}

/* ------------------------------------------------------------------
 * Données et statistiques (situation financière centrale)
 * Réservations comptées : toutes sauf refusées, annulées, expirées.
 * Revenus : montants réellement payés (paiements − remboursements).
 * ------------------------------------------------------------------ */
$partenaires = [];
$stats = ['revenus' => 0.0, 'reste' => 0.0, 'nb' => 0];
$parEspace = [];
$detail = null;
$detailResas = [];
$detailComptes = [];

if ($installe) {
    $partenaires = $pdo->query("
        SELECT p.*, (SELECT COUNT(*) FROM users u WHERE u.partenaire_id = p.id) AS nb_comptes
        FROM partenaires p
        ORDER BY p.actif DESC, p.nom ASC
    ")->fetchAll();
    foreach ($partenaires as &$p) {
        $p['nb_resas'] = 0; $p['revenus'] = 0.0; $p['reste'] = 0.0;
    }
    unset($p);
    $index = array_flip(array_map(fn($p) => (int)$p['id'], $partenaires));

    $resas = $pdo->query("
        SELECT r.id, r.partenaire_id, r.statut, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin,
               r.espace_id, e.nom AS espace_nom, u.nom_complet
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE r.partenaire_id IS NOT NULL
          AND r.statut NOT IN ('refusee', 'annulee', 'expiree')
        ORDER BY r.date_resa DESC
    ")->fetchAll();

    $detailId = (int)($_GET['id'] ?? 0);
    foreach ($resas as $r) {
        $s = situation_financiere_reservation($pdo, (int)$r['id']);
        $payeNet = $s ? max(0.0, (float)$s['paye_net']) : 0.0;
        $reste   = ($s && $r['statut'] === 'validee') ? (float)$s['solde'] : 0.0;
        $i = $index[(int)$r['partenaire_id']] ?? null;
        if ($i !== null) {
            $partenaires[$i]['nb_resas']++;
            $partenaires[$i]['revenus'] += $payeNet;
            $partenaires[$i]['reste'] += $reste;
        }
        $stats['nb']++;
        $stats['revenus'] += $payeNet;
        $stats['reste'] += $reste;
        $e = (int)$r['espace_id'];
        $parEspace[$e] ??= ['nom' => $r['espace_nom'], 'nb' => 0, 'revenus' => 0.0];
        $parEspace[$e]['nb']++;
        $parEspace[$e]['revenus'] += $payeNet;
        if ((int)$r['partenaire_id'] === $detailId) {
            $r['situation'] = $s;
            $detailResas[] = $r;
        }
    }
    uasort($parEspace, fn($a, $b) => $b['nb'] <=> $a['nb'] ?: $b['revenus'] <=> $a['revenus']);

    if ($detailId && isset($index[$detailId])) {
        $detail = $partenaires[$index[$detailId]];
        $st = $pdo->prepare("SELECT id, nom_complet, email, telephone, actif FROM users WHERE partenaire_id = ? ORDER BY nom_complet");
        $st->execute([$detailId]);
        $detailComptes = $st->fetchAll();
    }
}
$fcfa = fn($v) => number_format((float)$v, 0, ',', ' ');
$edition = $detail ?? (isset($_GET['nouveau']) ? [] : null);

$pageTitle = 'Partenaires';
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Partenaires</h1>
    <p class="text-sm text-slate-500 mt-0.5">Organismes et institutions qui réservent directement les espaces du Palais</p>
  </div>
  <?php if ($installe && $peutGerer): ?>
  <a href="partenaires.php?nouveau=1" class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-accent-dark transition shadow-sm">
    <i class="fas fa-plus"></i> Nouveau partenaire
  </a>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<?php if (!$installe): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-800">
  Le module partenaires n'est pas encore installé : exécutez le fichier
  <code class="font-mono">database/migration_partenaires_suggestions_services.sql</code> dans phpMyAdmin.
</div>
<?php else: ?>

<!-- Statistiques globales -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Revenus des partenaires</p>
    <p class="text-2xl font-black text-emerald-600 mt-1"><?= $fcfa($stats['revenus']) ?> <span class="text-xs">FCFA</span></p>
    <p class="text-[10px] text-slate-400 mt-0.5">montants réellement payés</p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Réservations partenaires</p>
    <p class="text-2xl font-black text-primary mt-1"><?= (int)$stats['nb'] ?></p>
    <p class="text-[10px] text-slate-400 mt-0.5"><a href="reservations.php?partenaire=tous" class="hover:text-accent">voir dans Réservations</a></p>
  </div>
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Reste à encaisser</p>
    <p class="text-2xl font-black text-amber-600 mt-1"><?= $fcfa($stats['reste']) ?> <span class="text-xs">FCFA</span></p>
    <p class="text-[10px] text-slate-400 mt-0.5">réservations validées</p>
  </div>
</div>

<?php if ($edition !== null && $peutGerer): ?>
<!-- Création / modification -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
  <h2 class="font-black text-primary text-sm uppercase italic mb-4"><?= $detail ? 'Modifier le partenaire' : 'Nouveau partenaire' ?></h2>
  <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="enregistrer">
    <?php if ($detail): ?><input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>"><?php endif; ?>
    <?php foreach ([
        ['nom', 'Nom du partenaire *', 'text', 'Ex : CICB'],
        ['type', 'Type', 'text', 'Institution, ministère, entreprise…'],
        ['contact_nom', 'Personne de contact', 'text', ''],
        ['telephone', 'Téléphone', 'text', ''],
        ['email', 'E-mail', 'email', ''],
    ] as [$champ, $lib, $typ, $ph]): ?>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1"><?= $lib ?></label>
      <input type="<?= $typ ?>" name="<?= $champ ?>" value="<?= e($edition[$champ] ?? '') ?>" placeholder="<?= e($ph) ?>" <?= $champ === 'nom' ? 'required' : '' ?>
             class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    </div>
    <?php endforeach; ?>
    <div class="sm:col-span-2">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Notes (convention, conditions…)</label>
      <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-primary outline-none focus:border-primary"><?= e($edition['notes'] ?? '') ?></textarea>
    </div>
    <div class="sm:col-span-2 flex justify-end gap-2">
      <a href="partenaires.php" class="px-4 py-2.5 rounded-xl text-xs font-black text-slate-400 hover:bg-slate-100 transition">Fermer</a>
      <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl hover:bg-slate-800 transition">Enregistrer</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($detail): ?>
<!-- Détail d'un partenaire -->
<div class="bg-white rounded-2xl border border-indigo-100 shadow-sm p-5 mb-6">
  <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
    <div>
      <p class="font-black text-primary text-lg"><?= e($detail['nom']) ?>
        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full <?= $detail['actif'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $detail['actif'] ? 'Actif' : 'Désactivé' ?></span>
      </p>
      <p class="text-xs text-slate-500 mt-0.5">
        <?= e(implode(' · ', array_filter([$detail['type'], $detail['contact_nom'], $detail['telephone'], $detail['email']]))) ?: '—' ?>
      </p>
    </div>
    <?php if ($peutGerer): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="basculer_actif">
      <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
      <button type="submit" onclick="return confirm('<?= $detail['actif'] ? 'Désactiver ce partenaire ?' : 'Réactiver ce partenaire ?' ?>')"
              class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $detail['actif'] ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-emerald-500 text-white hover:bg-emerald-600' ?>">
        <?= $detail['actif'] ? 'Désactiver' : 'Réactiver' ?>
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase text-slate-400">Réservations</p><p class="text-lg font-black text-primary"><?= (int)$detail['nb_resas'] ?></p></div>
    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase text-slate-400">Revenus</p><p class="text-lg font-black text-emerald-600"><?= $fcfa($detail['revenus']) ?> FCFA</p></div>
    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase text-slate-400">Reste à encaisser</p><p class="text-lg font-black text-amber-600"><?= $fcfa($detail['reste']) ?> FCFA</p></div>
  </div>

  <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Comptes associés</h3>
  <div class="space-y-2 mb-4">
    <?php foreach ($detailComptes as $c): ?>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2">
      <p class="text-sm font-bold text-primary"><?= e($c['nom_complet']) ?> <span class="text-xs font-semibold text-slate-400"><?= e($c['email']) ?></span></p>
      <?php if ($peutGerer): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="dissocier">
        <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>">
        <button type="submit" onclick="return confirm('Dissocier ce compte du partenaire ?')" class="text-[11px] font-black text-slate-400 hover:text-accent transition">Dissocier</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$detailComptes): ?><p class="text-xs text-slate-400">Aucun compte associé pour le moment.</p><?php endif; ?>
  </div>
  <?php if ($peutGerer): ?>
  <form method="POST" class="flex flex-col sm:flex-row gap-2 mb-5">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="associer">
    <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
    <input type="email" name="email_compte" required placeholder="E-mail du compte client à associer"
           class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl hover:bg-slate-800 transition">Associer le compte</button>
  </form>
  <?php endif; ?>

  <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Réservations du partenaire</h3>
  <div class="space-y-2">
    <?php foreach ($detailResas as $r): $s = $r['situation']; [$etatLib, $etatCls] = libelle_etat_financier($s['etat'] ?? ''); ?>
    <a href="<?= $role === 'admin_espaces' ? 'reservations.php?q=RESA-' . (int)$r['id'] : 'paiements.php?resa=' . (int)$r['id'] ?>"
       class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2 hover:bg-slate-50 transition">
      <p class="text-sm font-bold text-primary"><span class="font-mono text-xs text-slate-400"><?= e(ref_resa((int)$r['id'])) ?></span> <?= e($r['espace_nom']) ?>
        <span class="text-xs font-semibold text-slate-400">· <?= date('d/m/Y', strtotime($r['date_resa'])) ?> · <?= e($r['nom_complet']) ?></span></p>
      <span class="text-[10px] font-black px-2 py-0.5 rounded-full border <?= $etatCls ?>"><?= e($etatLib) ?> · <?= $fcfa($s['paye_net'] ?? 0) ?> / <?= $fcfa($s['net_du'] ?? 0) ?> FCFA</span>
    </a>
    <?php endforeach; ?>
    <?php if (!$detailResas): ?><p class="text-xs text-slate-400">Aucune réservation.</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-5">
  <!-- Par partenaire -->
  <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100"><h2 class="font-black text-primary text-sm uppercase italic">Par partenaire</h2></div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($partenaires as $p): ?>
      <a href="partenaires.php?id=<?= (int)$p['id'] ?>" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50 transition <?= $detail && (int)$detail['id'] === (int)$p['id'] ? 'bg-indigo-50' : '' ?>">
        <div>
          <p class="font-black text-primary text-sm"><?= e($p['nom']) ?>
            <?php if (!$p['actif']): ?><span class="text-[9px] font-black uppercase text-slate-400 ml-1">désactivé</span><?php endif; ?></p>
          <p class="text-[11px] text-slate-400"><?= e($p['type'] ?: '—') ?> · <?= (int)$p['nb_comptes'] ?> compte(s)</p>
        </div>
        <p class="text-xs font-bold text-slate-600"><?= (int)$p['nb_resas'] ?> réservation(s) · <span class="text-emerald-600 font-black"><?= $fcfa($p['revenus']) ?> FCFA</span></p>
      </a>
      <?php endforeach; ?>
      <?php if (!$partenaires): ?><p class="px-5 py-8 text-sm text-slate-400 text-center">Aucun partenaire enregistré.</p><?php endif; ?>
    </div>
  </div>
  <!-- Espaces utilisés -->
  <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100"><h2 class="font-black text-primary text-sm uppercase italic">Espaces utilisés</h2></div>
    <div class="divide-y divide-slate-50">
      <?php foreach ($parEspace as $es): ?>
      <div class="flex items-center justify-between gap-2 px-5 py-3">
        <p class="text-sm font-bold text-primary"><?= e($es['nom']) ?></p>
        <p class="text-[11px] font-bold text-slate-500 text-right"><?= (int)$es['nb'] ?> résa · <?= $fcfa($es['revenus']) ?> FCFA</p>
      </div>
      <?php endforeach; ?>
      <?php if (!$parEspace): ?><p class="px-5 py-8 text-sm text-slate-400 text-center">Aucune réservation partenaire.</p><?php endif; ?>
    </div>
  </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/_admin_footer.php'; ?>
