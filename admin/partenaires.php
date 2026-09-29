<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre', 'admin_comptable', 'admin_espaces']);

/*
 * Partenaires (CICB, ministère, institution…).
 * Un partenaire est une fiche « partenaires » (l'organisation) et un ou
 * plusieurs comptes utilisateurs de rôle « partenaire » qui lui sont
 * rattachés (users.partenaire_id). Ces comptes réservent par le circuit
 * normal (espaces, tarifs, disponibilités, validation, paiements, bons) ;
 * leurs réservations sont attribuées au partenaire (reservations.partenaire_id).
 *
 * Gestion (fiche, comptes, mots de passe initiaux, désactivation) :
 * Direction (superadmin). Consultation et statistiques : ministre,
 * comptabilité, administration des espaces.
 */
$pdo      = db();
$role     = $_SESSION['role'] ?? '';
$peutGerer = is_superadmin();
$pageRetour = isset($_GET['id']) || isset($_GET['nouveau'])
    ? ['partenaires.php', 'Retour aux partenaires']
    : ['dashboard.php', 'Retour au tableau de bord'];
$msg      = $_SESSION['partenaires_flash'] ?? null;
unset($_SESSION['partenaires_flash']);

$installe = partenaires_disponibles($pdo);

/*
 * Compte utilisateur du partenaire (rôle « partenaire », users.partenaire_id).
 * Mêmes règles à la création du partenaire et depuis sa fiche.
 */
$validerNouveauCompte = function () use ($pdo): array {
    if (!role_partenaire_disponible($pdo)) {
        throw new RuntimeException('Exécutez d\'abord la migration « migration_role_partenaire.sql ».');
    }
    $c = [
        'nom'   => trim((string)($_POST['nom_complet'] ?? '')),
        'email' => trim((string)($_POST['email_compte'] ?? '')),
        'tel'   => trim((string)($_POST['telephone_compte'] ?? '')),
        'mdp'   => (string)($_POST['mot_de_passe'] ?? ''),
    ];
    if (mb_strlen($c['nom']) < 2 || mb_strlen($c['nom']) > 200) {
        throw new RuntimeException('Le nom du titulaire du compte est obligatoire.');
    }
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($c['email']) > 190) {
        throw new RuntimeException('Identifiant de connexion (e-mail) invalide.');
    }
    if (mb_strlen($c['tel']) > 30) {
        throw new RuntimeException('Téléphone du compte trop long.');
    }
    if (mb_strlen($c['mdp']) < 8) {
        throw new RuntimeException('Le mot de passe initial doit contenir au moins 8 caractères.');
    }
    if ($c['mdp'] !== (string)($_POST['mot_de_passe_confirmation'] ?? '')) {
        throw new RuntimeException('La confirmation du mot de passe ne correspond pas.');
    }
    $exist = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $exist->execute([$c['email']]);
    if ($exist->fetch()) {
        throw new RuntimeException('Cet identifiant (e-mail) est déjà utilisé par un autre compte. Utilisez « rattacher un compte existant » ou un autre identifiant.');
    }
    return $c;
};
$insererCompte = function (array $c, int $pid) use ($pdo): int {
    $pdo->prepare("INSERT INTO users (nom_complet, email, telephone, password_hash, role, partenaire_id, actif) VALUES (?,?,?,?, 'partenaire', ?, 1)")
        ->execute([$c['nom'], $c['email'], $c['tel'] !== '' ? $c['tel'] : null, password_hash($c['mdp'], PASSWORD_DEFAULT), $pid]);
    $uid = (int)$pdo->lastInsertId();
    log_activity('partenaire_compte_cree', 'users', "Compte partenaire #$uid ({$c['email']}) créé pour le partenaire #$pid");
    return $uid;
};
// Compte existant à rattacher : client ou partenaire sans autre rattachement
$validerCompteExistant = function (int $pid) use ($pdo): array {
    if (!role_partenaire_disponible($pdo)) {
        throw new RuntimeException('Exécutez d\'abord la migration « migration_role_partenaire.sql ».');
    }
    $email = trim((string)($_POST['email_existant'] ?? $_POST['email_compte'] ?? ''));
    $st = $pdo->prepare("SELECT id, nom_complet, role, partenaire_id FROM users WHERE email = ?");
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u) {
        throw new RuntimeException('Aucun compte ne correspond à cet identifiant. Créez plutôt un nouveau compte partenaire.');
    }
    if (!in_array($u['role'], ['user', 'partenaire'], true)) {
        throw new RuntimeException('Un compte d\'administration ne peut pas être rattaché à un partenaire.');
    }
    if ($u['partenaire_id'] && (int)$u['partenaire_id'] !== $pid) {
        throw new RuntimeException('Ce compte est déjà rattaché à un autre partenaire. Dissociez-le d\'abord.');
    }
    return $u;
};
$rattacherCompte = function (array $u, int $pid) use ($pdo): string {
    $nouveauRole = 'partenaire';
    $pdo->prepare("UPDATE users SET partenaire_id = ?, role = ? WHERE id = ?")->execute([$pid, $nouveauRole, (int)$u['id']]);
    $nomP = (string)$pdo->query("SELECT nom FROM partenaires WHERE id = " . $pid)->fetchColumn();
    notify('', 'partenaire_associe', "Votre compte est désormais un compte partenaire « $nomP » du Palais des Pionniers.", 'mon-compte.php', (int)$u['id']);
    log_activity('partenaire_compte_associe', 'users', "Compte #{$u['id']} rattaché au partenaire #$pid (rôle $nouveauRole)");
    return $nouveauRole;
};

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
                // Un partenaire est créé avec son compte de connexion : nouveau compte
                // (rôle « partenaire ») ou compte existant rattaché. Tout ou rien.
                $modeCompte = ($_POST['compte_mode'] ?? 'nouveau') === 'existant' ? 'existant' : 'nouveau';
                $compte = $modeCompte === 'nouveau' ? $validerNouveauCompte() : $validerCompteExistant(0);
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO partenaires (nom, type, contact_nom, telephone, email, notes) VALUES (?,?,?,?,?,?)")
                    ->execute(array_values($valeurs));
                $pid = (int)$pdo->lastInsertId();
                log_activity('partenaire_cree', 'users', "Partenaire #$pid créé : {$champs['nom']}");
                if ($modeCompte === 'nouveau') {
                    $insererCompte($compte, $pid);
                    $texteCompte = "compte de connexion créé (identifiant « {$compte['email']} »). Communiquez le mot de passe initial au partenaire, qui pourra le modifier dans son espace.";
                } else {
                    $rattacherCompte($compte, $pid);
                    $texteCompte = "compte « {$compte['nom_complet']} » rattaché.";
                }
                $pdo->commit();
                $_SESSION['partenaires_flash'] = ['ok', "Partenaire « {$champs['nom']} » créé, $texteCompte"];
            }
            $retour = 'partenaires.php?id=' . $pid;

        } elseif ($action === 'basculer_actif' && $pid) {
            $pdo->prepare("UPDATE partenaires SET actif = 1 - actif WHERE id = ?")->execute([$pid]);
            $actif = (int)$pdo->query("SELECT actif FROM partenaires WHERE id = " . $pid)->fetchColumn();
            log_activity('partenaire_' . ($actif ? 'active' : 'desactive'), 'users', "Partenaire #$pid " . ($actif ? 'réactivé' : 'désactivé'));
            $_SESSION['partenaires_flash'] = ['ok', $actif
                ? 'Partenaire réactivé.'
                : 'Partenaire désactivé : ses comptes ne peuvent plus se connecter ni réserver (l\'historique reste attribué).'];

        } elseif ($action === 'creer_compte' && $pid) {
            // Compte supplémentaire (ou recréé) depuis la fiche partenaire
            $compte = $validerNouveauCompte();
            if (!$pdo->query("SELECT actif FROM partenaires WHERE id = " . $pid)->fetchColumn()) {
                throw new RuntimeException('Réactivez le partenaire avant de lui créer un compte.');
            }
            $insererCompte($compte, $pid);
            $_SESSION['partenaires_flash'] = ['ok', "Compte partenaire créé : identifiant « {$compte['email']} ». Communiquez le mot de passe initial au partenaire, qui pourra le modifier dans son espace."];

        } elseif ($action === 'associer' && $pid) {
            // Rattacher un compte existant (client) : il devient un compte « partenaire »
            $u = $validerCompteExistant($pid);
            $rattacherCompte($u, $pid);
            $_SESSION['partenaires_flash'] = ['ok', "Compte « {$u['nom_complet']} » rattaché au partenaire."];

        } elseif ($action === 'dissocier' && $pid) {
            // Le compte redevient un client classique ; l'historique reste attribué
            $uid = (int)($_POST['user_id'] ?? 0);
            $st = $pdo->prepare("UPDATE users SET partenaire_id = NULL, role = IF(role = 'partenaire', 'user', role) WHERE id = ? AND partenaire_id = ?");
            $st->execute([$uid, $pid]);
            if ($st->rowCount() !== 1) {
                throw new RuntimeException('Ce compte n\'est pas rattaché à ce partenaire.');
            }
            log_activity('partenaire_compte_dissocie', 'users', "Compte #$uid dissocié du partenaire #$pid (redevient client)");
            $_SESSION['partenaires_flash'] = ['ok', 'Compte dissocié : il redevient un compte client (les réservations passées restent attribuées au partenaire).'];

        } elseif ($action === 'reinitialiser_mot_de_passe' && $pid) {
            $uid = (int)($_POST['user_id'] ?? 0);
            $mdp = (string)($_POST['mot_de_passe'] ?? '');
            if (mb_strlen($mdp) < 8) {
                throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 8 caractères.');
            }
            $st = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND partenaire_id = ? AND role IN ('partenaire','user')");
            $st->execute([password_hash($mdp, PASSWORD_DEFAULT), $uid, $pid]);
            if ($st->rowCount() !== 1) {
                throw new RuntimeException('Compte introuvable pour ce partenaire.');
            }
            log_activity('partenaire_mot_de_passe_reinitialise', 'users', "Mot de passe du compte partenaire #$uid réinitialisé par la Direction");
            $_SESSION['partenaires_flash'] = ['ok', 'Mot de passe réinitialisé. Communiquez-le au partenaire, qui pourra le modifier dans son espace.'];

        } elseif ($action === 'supprimer' && $pid) {
            // Suppression physique uniquement si aucune trace : ni compte, ni réservation
            $nbComptes = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE partenaire_id = " . $pid)->fetchColumn();
            $nbResas   = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE partenaire_id = " . $pid)->fetchColumn();
            if ($nbComptes || $nbResas) {
                throw new RuntimeException("Ce partenaire a des comptes ou des réservations ($nbComptes compte(s), $nbResas réservation(s)) : il ne peut pas être supprimé. Désactivez-le pour conserver l'historique.");
            }
            $nomP = (string)$pdo->query("SELECT nom FROM partenaires WHERE id = " . $pid)->fetchColumn();
            $pdo->prepare("DELETE FROM partenaires WHERE id = ?")->execute([$pid]);
            log_activity('partenaire_supprime', 'users', "Partenaire #$pid supprimé ($nomP) — aucun compte ni réservation");
            $_SESSION['partenaires_flash'] = ['ok', "Partenaire « $nomP » supprimé."];
            $retour = 'partenaires.php';

        } else {
            throw new RuntimeException('Action non reconnue.');
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['partenaires_flash'] = ['err', $e->getMessage()];
        if (($action ?? '') === 'enregistrer' && empty($pid)) {
            $retour = 'partenaires.php?nouveau=1';
            // Champs de la fiche conservés (jamais les mots de passe)
            $_SESSION['partenaires_saisie'] = array_intersect_key($_POST, array_flip(['nom', 'type', 'contact_nom', 'telephone', 'email', 'notes', 'compte_mode', 'nom_complet', 'email_compte', 'telephone_compte', 'email_existant']));
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
$aDesResasAnnulees = false;

if ($installe) {
    $partenaires = $pdo->query("
        SELECT p.*, (SELECT COUNT(*) FROM users u WHERE u.partenaire_id = p.id) AS nb_comptes
        FROM partenaires p
        ORDER BY p.actif DESC, p.nom ASC
    ")->fetchAll();
    foreach ($partenaires as &$p) {
        $p['nb_resas'] = 0; $p['revenus'] = 0.0; $p['reste'] = 0.0;
        $p['comptes'] = []; $p['derniere_resa'] = null;
    }
    unset($p);
    $index = array_flip(array_map(fn($p) => (int)$p['id'], $partenaires));

    // Comptes de connexion rattachés (identifiant et état), pour la liste de suivi
    foreach ($pdo->query("SELECT id, email, actif, role, partenaire_id FROM users WHERE partenaire_id IS NOT NULL ORDER BY nom_complet")->fetchAll() as $cpt) {
        $i = $index[(int)$cpt['partenaire_id']] ?? null;
        if ($i !== null) {
            $partenaires[$i]['comptes'][] = $cpt;
        }
    }
    // Dernière demande de réservation (toutes, y compris annulées)
    foreach ($pdo->query("SELECT partenaire_id, MAX(created_at) AS derniere FROM reservations WHERE partenaire_id IS NOT NULL GROUP BY partenaire_id")->fetchAll() as $d) {
        $i = $index[(int)$d['partenaire_id']] ?? null;
        if ($i !== null) {
            $partenaires[$i]['derniere_resa'] = $d['derniere'];
        }
    }

    $resas = $pdo->query("
        SELECT r.id, r.partenaire_id, r.statut, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin,
               r.espace_id, e.nom AS espace_nom, u.nom_complet
        FROM reservations r
        JOIN espaces e ON e.id = r.espace_id
        JOIN users u ON u.id = r.user_id
        WHERE r.partenaire_id IS NOT NULL
        ORDER BY r.date_resa DESC
    ")->fetchAll();

    $detailId = (int)($_GET['id'] ?? 0);
    foreach ($resas as $r) {
        $s = situation_financiere_reservation($pdo, (int)$r['id']);
        // Historique complet dans la fiche ; statistiques hors refusées / annulées / expirées
        if ((int)$r['partenaire_id'] === $detailId) {
            $r['situation'] = $s;
            $detailResas[] = $r;
        }
        if (in_array($r['statut'], ['refusee', 'annulee', 'expiree'], true)) {
            continue;
        }
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
    }
    uasort($parEspace, fn($a, $b) => $b['nb'] <=> $a['nb'] ?: $b['revenus'] <=> $a['revenus']);

    if ($detailId && isset($index[$detailId])) {
        $detail = $partenaires[$index[$detailId]];
        $st = $pdo->prepare("SELECT id, nom_complet, email, telephone, actif, role FROM users WHERE partenaire_id = ? ORDER BY nom_complet");
        $st->execute([$detailId]);
        $detailComptes = $st->fetchAll();
        $aDesResasAnnulees = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE partenaire_id = " . $detailId)->fetchColumn() > 0;
    }
}
$fcfa = fn($v) => number_format((float)$v, 0, ',', ' ');
$edition = $detail ?? (isset($_GET['nouveau']) ? [] : null);
// Saisie conservée après une erreur de création (jamais les mots de passe)
$saisie = $_SESSION['partenaires_saisie'] ?? [];
unset($_SESSION['partenaires_saisie']);
if ($edition === [] && $saisie) {
    $edition = $saisie;
}
$modeCompteSaisi = ($saisie['compte_mode'] ?? 'nouveau') === 'existant' ? 'existant' : 'nouveau';

$pageTitle = 'Partenaires';
require __DIR__ . '/_admin_header.php';
?>

<?php if (isset($_GET['id']) || isset($_GET['nouveau'])): ?>
<a href="partenaires.php" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-primary hover:text-accent transition bg-primary/5 hover:bg-accent/10 px-3.5 py-2 rounded-full mb-4">
  <i class="fas fa-arrow-left"></i> Retour aux partenaires
</a>
<?php endif; ?>

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
    <?php if (!$detail): ?>
    <!-- Compte de connexion du partenaire (créé en même temps que la fiche) -->
    <fieldset class="sm:col-span-2 rounded-xl bg-slate-50 p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
      <legend class="text-[10px] font-black uppercase tracking-widest text-slate-500 px-1">Compte de connexion du partenaire *</legend>
      <div class="sm:col-span-2 flex flex-col sm:flex-row gap-2 text-xs font-bold text-primary">
        <label class="flex items-center gap-2"><input type="radio" name="compte_mode" value="nouveau" <?= $modeCompteSaisi === 'nouveau' ? 'checked' : '' ?> onchange="modeComptePartenaire(this.value)"> Créer un nouveau compte (rôle partenaire)</label>
        <label class="flex items-center gap-2"><input type="radio" name="compte_mode" value="existant" <?= $modeCompteSaisi === 'existant' ? 'checked' : '' ?> onchange="modeComptePartenaire(this.value)"> Rattacher un compte existant</label>
      </div>
      <div id="compteNouveau" class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3 <?= $modeCompteSaisi === 'existant' ? 'hidden' : '' ?>">
        <input type="text" name="nom_complet" value="<?= e($saisie['nom_complet'] ?? '') ?>" placeholder="Nom du titulaire du compte" autocomplete="off"
               class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
        <input type="email" name="email_compte" value="<?= e($saisie['email_compte'] ?? '') ?>" placeholder="Identifiant de connexion (e-mail)" autocomplete="off"
               class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
        <input type="text" name="telephone_compte" value="<?= e($saisie['telephone_compte'] ?? '') ?>" placeholder="Téléphone du titulaire (facultatif)"
               class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
        <span class="hidden sm:block"></span>
        <input type="password" name="mot_de_passe" minlength="8" autocomplete="new-password" placeholder="Mot de passe initial (8 caractères min.)"
               class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
        <input type="password" name="mot_de_passe_confirmation" minlength="8" autocomplete="new-password" placeholder="Confirmation du mot de passe"
               class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
      </div>
      <div id="compteExistant" class="sm:col-span-2 <?= $modeCompteSaisi === 'existant' ? '' : 'hidden' ?>">
        <input type="email" name="email_existant" value="<?= e($saisie['email_existant'] ?? '') ?>" placeholder="E-mail du compte client existant" autocomplete="off"
               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
        <p class="text-[11px] text-slate-500 mt-1">Le compte deviendra un compte partenaire. Un compte d'administration ne peut pas être rattaché.</p>
      </div>
    </fieldset>
    <script>
    function modeComptePartenaire(mode) {
        document.getElementById('compteNouveau').classList.toggle('hidden', mode !== 'nouveau');
        document.getElementById('compteExistant').classList.toggle('hidden', mode !== 'existant');
    }
    </script>
    <?php endif; ?>
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
    <div class="flex flex-wrap gap-2">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="basculer_actif">
      <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
      <button type="submit" onclick="return confirm('<?= $detail['actif'] ? 'Désactiver ce partenaire ?' : 'Réactiver ce partenaire ?' ?>')"
              class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $detail['actif'] ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-emerald-500 text-white hover:bg-emerald-600' ?>">
        <?= $detail['actif'] ? 'Désactiver' : 'Réactiver' ?>
      </button>
    </form>
    <?php if (!$detailComptes && !(int)$detail['nb_resas'] && !$aDesResasAnnulees): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="supprimer">
      <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
      <button type="submit" onclick="return confirm('Supprimer définitivement ce partenaire ? (aucun compte ni réservation)')" class="text-xs font-black uppercase px-4 py-2 rounded-xl border border-rose-100 text-rose-500 hover:bg-rose-50 transition">Supprimer</button>
    </form>
    <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase text-slate-400">Réservations</p><p class="text-lg font-black text-primary"><?= (int)$detail['nb_resas'] ?></p></div>
    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase text-slate-400">Revenus</p><p class="text-lg font-black text-emerald-600"><?= $fcfa($detail['revenus']) ?> FCFA</p></div>
    <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase text-slate-400">Reste à encaisser</p><p class="text-lg font-black text-amber-600"><?= $fcfa($detail['reste']) ?> FCFA</p></div>
  </div>

  <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Comptes partenaire</h3>
  <div class="space-y-2 mb-4">
    <?php foreach ($detailComptes as $c): ?>
    <div class="rounded-xl border border-slate-100 px-3 py-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-bold text-primary"><?= e($c['nom_complet']) ?>
          <span class="text-xs font-semibold text-slate-400">identifiant : <?= e($c['email']) ?></span>
          <?php if (!(int)$c['actif']): ?><span class="text-[9px] font-black uppercase text-accent ml-1">compte bloqué</span><?php endif; ?>
        </p>
        <?php if ($peutGerer): ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="dissocier">
          <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
          <input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>">
          <button type="submit" onclick="return confirm('Dissocier ce compte ? Il redeviendra un compte client classique.')" class="text-[11px] font-black text-slate-400 hover:text-accent transition">Dissocier</button>
        </form>
        <?php endif; ?>
      </div>
      <?php if ($peutGerer): ?>
      <form method="POST" class="flex flex-col sm:flex-row gap-2 mt-2" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="reinitialiser_mot_de_passe">
        <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
        <input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>">
        <input type="password" name="mot_de_passe" required minlength="8" autocomplete="new-password" placeholder="Nouveau mot de passe (8 caractères min.)"
               class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-primary outline-none focus:border-primary">
        <button type="submit" onclick="return confirm('Réinitialiser le mot de passe de ce compte ?')" class="text-[11px] font-black uppercase px-4 py-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition">Réinitialiser le mot de passe</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$detailComptes): ?><p class="text-xs text-slate-400">Aucun compte pour le moment.</p><?php endif; ?>
  </div>

  <?php if ($peutGerer): ?>
  <!-- Créer le compte utilisateur du partenaire (rôle « partenaire ») -->
  <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3 rounded-xl bg-slate-50 p-3" autocomplete="off">
    <p class="sm:col-span-2 text-[10px] font-black uppercase tracking-widest text-slate-500">Créer un compte partenaire</p>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="creer_compte">
    <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
    <input type="text" name="nom_complet" required placeholder="Nom du titulaire"
           class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <input type="email" name="email_compte" required placeholder="Identifiant de connexion (e-mail)" autocomplete="off"
           class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <input type="text" name="telephone_compte" placeholder="Téléphone"
           class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <input type="password" name="mot_de_passe" required minlength="8" autocomplete="new-password" placeholder="Mot de passe initial (8 caractères min.)"
           class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <input type="password" name="mot_de_passe_confirmation" required minlength="8" autocomplete="new-password" placeholder="Confirmation du mot de passe"
           class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl hover:bg-slate-800 transition">Créer le compte</button>
  </form>
  <!-- Rattacher un compte existant -->
  <form method="POST" class="flex flex-col sm:flex-row gap-2 mb-5">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="associer">
    <input type="hidden" name="partenaire_id" value="<?= (int)$detail['id'] ?>">
    <input type="email" name="email_compte" required placeholder="Ou rattacher un compte existant (e-mail)"
           class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-primary outline-none focus:border-primary">
    <button type="submit" onclick="return confirm('Ce compte deviendra un compte partenaire. Continuer ?')" class="text-xs font-black uppercase px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition">Rattacher</button>
  </form>
  <?php endif; ?>

  <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Réservations du partenaire</h3>
  <div class="space-y-2">
    <?php foreach ($detailResas as $r): $s = $r['situation'];
        [$etatLib, $etatCls] = in_array($r['statut'], ['refusee', 'annulee', 'expiree'], true)
            ? [['refusee' => 'Refusée', 'annulee' => 'Annulée', 'expiree' => 'Expirée'][$r['statut']], 'bg-slate-50 text-slate-500 border-slate-200']
            : libelle_etat_financier($s['etat'] ?? ''); ?>
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
      <?php foreach ($partenaires as $p): $comptesP = $p['comptes']; ?>
      <a href="partenaires.php?id=<?= (int)$p['id'] ?>" class="flex flex-wrap items-start justify-between gap-2 px-5 py-3 hover:bg-slate-50 transition <?= $detail && (int)$detail['id'] === (int)$p['id'] ? 'bg-indigo-50' : '' ?>">
        <div class="min-w-0">
          <p class="font-black text-primary text-sm"><?= e($p['nom']) ?>
            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full <?= $p['actif'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $p['actif'] ? 'Actif' : 'Désactivé' ?></span></p>
          <p class="text-[11px] text-slate-400"><?= e(implode(' · ', array_filter([$p['type'], $p['contact_nom'], $p['telephone']])) ?: '—') ?></p>
          <p class="text-[11px] font-bold <?= $comptesP ? 'text-slate-500' : 'text-accent' ?>" style="overflow-wrap:anywhere">
            <?php if (!$comptesP): ?>
              <i class="fas fa-exclamation-triangle mr-1"></i>Aucun compte de connexion
            <?php else: ?>
              <i class="fas fa-user mr-1"></i><?php foreach ($comptesP as $k => $cpt): ?><?= $k ? ', ' : '' ?><?= e($cpt['email']) ?><?= !(int)$cpt['actif'] ? ' (bloqué)' : '' ?><?php endforeach; ?>
            <?php endif; ?>
          </p>
        </div>
        <div class="text-right text-xs font-bold text-slate-600">
          <p><?= (int)$p['nb_resas'] ?> réservation(s)</p>
          <p>Payé <span class="text-emerald-600 font-black"><?= $fcfa($p['revenus']) ?></span> · Reste dû <span class="<?= $p['reste'] > 0 ? 'text-amber-600' : 'text-slate-400' ?> font-black"><?= $fcfa($p['reste']) ?></span> FCFA</p>
          <p class="text-[11px] text-slate-400"><?= $p['derniere_resa'] ? 'Dernière demande le ' . date('d/m/Y', strtotime($p['derniere_resa'])) : 'Aucune réservation' ?></p>
        </div>
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
