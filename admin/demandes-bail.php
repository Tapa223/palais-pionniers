<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_espaces','ministre','admin_comptable']);

$pdo      = db();
$readonly = is_readonly_admin();
$msg      = null;

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'supprimer') {
            // Suppression d'une erreur ou d'un test : Direction uniquement
            $id = (int)($_POST['id'] ?? 0);
            if (!is_superadmin()) {
                $msg = ['err', 'Seule la Direction peut supprimer une demande.'];
            } else {
                $dSupp = $pdo->prepare("SELECT nom, prenom FROM demandes_bail WHERE id = ?");
                $dSupp->execute([$id]);
                if ($dSupp = $dSupp->fetch()) {
                    $pdo->beginTransaction();
                    try {
                        if (colonne_existe($pdo, 'demande_bail_espaces', 'demande_id')) {
                            $pdo->prepare("DELETE FROM demande_bail_espaces WHERE demande_id = ?")->execute([$id]);
                        }
                        $pdo->prepare("DELETE FROM demandes_bail WHERE id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM notifications WHERE lien IN (?, ?)")->execute(["demandes-bail.php?id=$id", "admin/demandes-bail.php?id=$id"]);
                        $pdo->commit();
                        log_activity('demande_bail_supprimee', 'espaces', "Demande de bail #$id supprimée (erreur ou test) — " . trim(($dSupp['prenom'] ?? '') . ' ' . $dSupp['nom']));
                        $msg = ['ok', 'Demande supprimée définitivement.'];
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $msg = ['err', 'La suppression n\'a pas pu être effectuée.'];
                    }
                } else {
                    $msg = ['err', 'Demande introuvable.'];
                }
            }
        }

        if ($action === 'traiter') {
            $id      = (int)($_POST['id'] ?? 0);
            $statut  = in_array($_POST['statut'] ?? '', ['acceptee','refusee'], true) ? $_POST['statut'] : null;
            $note    = trim($_POST['note_traitement'] ?? '') ?: null;

            if ($id && $statut) {
                $d = $pdo->prepare("SELECT * FROM demandes_bail WHERE id = ?");
                $d->execute([$id]); $d = $d->fetch();

                $clientUserId = null;
                $motDePasseGenere = null;

                if ($statut === 'acceptee' && $d && !empty($d['email'])) {
                    $existant = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $existant->execute([$d['email']]);
                    $clientUserId = $existant->fetchColumn();

                    if (!$clientUserId) {
                        // Génère un mot de passe temporaire simple, à communiquer au client
                        $motDePasseGenere = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                        $nomComplet = trim(($d['prenom'] ?? '') . ' ' . $d['nom']);
                        $pdo->prepare("INSERT INTO users (nom_complet, email, telephone, password_hash, role, actif) VALUES (?,?,?,?, 'user', 1)")
                            ->execute([$nomComplet, $d['email'], $d['telephone'], password_hash($motDePasseGenere, PASSWORD_DEFAULT)]);
                        $clientUserId = (int)$pdo->lastInsertId();
                        log_activity('user_cree', 'users', "Compte client créé automatiquement depuis une demande de bail : $nomComplet ({$d['email']})");
                    }
                }

                $pdo->prepare("UPDATE demandes_bail SET statut = ?, traite_par = ?, date_traitement = NOW(), note_traitement = ?, client_user_id = ? WHERE id = ?")
                    ->execute([$statut, $_SESSION['user_id'], $note, $clientUserId, $id]);

                log_activity('demande_bail_traitee', 'espaces', "Demande de bail #$id " . ($statut === 'acceptee' ? 'acceptée' : 'refusée'));

                $espNom = $pdo->prepare("SELECT nom FROM espaces WHERE id = ?"); $espNom->execute([$d['espace_id']]); $espNom = $espNom->fetchColumn();
                if ($statut === 'acceptee' && $clientUserId) {
                    notify('', 'demande_bail_acceptee', "Votre demande de bail pour « $espNom » a été acceptée. Nous vous contacterons pour finaliser les modalités.", "mon-compte.php?tab=baux", (int)$clientUserId);
                }

                $espacesSuppNoms = $pdo->prepare("SELECT e.nom FROM demande_bail_espaces dbe JOIN espaces e ON e.id = dbe.espace_id WHERE dbe.demande_id = ?");
                $espacesSuppNoms->execute([$id]);
                $espacesSuppNoms = $espacesSuppNoms->fetchAll(PDO::FETCH_COLUMN);
                $rappelEspaces = $espacesSuppNoms ? " N'oublie pas de configurer aussi le bail sur : " . implode(', ', $espacesSuppNoms) . " (même compte client)." : '';

                $msgOk = 'Demande mise à jour.';
                if ($statut === 'acceptee') {
                    $msgOk .= $motDePasseGenere
                        ? " Compte client créé ({$d['email']}) — mot de passe temporaire : $motDePasseGenere (à communiquer au client). Pense à créer le bail dans Admin > Espaces et à le lier à ce compte." . $rappelEspaces
                        : ' Compte client existant lié. Pense à créer le bail dans Admin > Espaces si ce n\'est pas déjà fait.' . $rappelEspaces;
                }
                $msg = ['ok', $msgOk];
            }
        }

        if ($action === 'saisie_guichet') {
            $nom      = trim($_POST['nom'] ?? '');
            $prenom   = trim($_POST['prenom'] ?? '') ?: null;
            $tel      = trim($_POST['telephone'] ?? '');
            $email    = trim($_POST['email'] ?? '') ?: null;
            $espaceId = (int)($_POST['espace_id'] ?? 0);
            $usage    = trim($_POST['usage_prevu'] ?? '') ?: null;
            $duree    = in_array($_POST['duree_souhaitee'] ?? '', ['mensuel','trimestriel','semestriel','annuel'], true) ? $_POST['duree_souhaitee'] : 'mensuel';
            $dateDeb  = trim($_POST['date_debut_souhaitee'] ?? '') ?: null;
            $message  = trim($_POST['message'] ?? '') ?: null;

            if (!$nom || !$tel || !$espaceId || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = ['err', 'Nom, téléphone, un email valide et espace sont obligatoires.'];
            } else {
                $pdo->prepare("
                    INSERT INTO demandes_bail (nom, prenom, telephone, email, espace_id, usage_prevu, duree_souhaitee, date_debut_souhaitee, message, canal, enregistre_par)
                    VALUES (?,?,?,?,?,?,?,?,?,'guichet',?)
                ")->execute([$nom, $prenom, $tel, $email, $espaceId, $usage, $duree, $dateDeb, $message, $_SESSION['user_id']]);
                log_activity('demande_bail_guichet', 'espaces', "Demande de bail saisie au guichet pour $nom");
                header('Location: demandes-bail.php?success=1'); exit;
            }
        }
    }
}

if (isset($_GET['success'])) $msg = ['ok', 'Demande enregistrée.'];

$filterStatut = $_GET['statut'] ?? 'en_attente';
$where = [];
$params = [];
if (in_array($filterStatut, ['en_attente','acceptee','refusee'], true)) {
    $where[] = 'db.statut = ?';
    $params[] = $filterStatut;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$demandes = $pdo->prepare("
    SELECT db.*, e.nom AS espace_nom, admin.nom_complet AS traite_par_nom
    FROM demandes_bail db
    JOIN espaces e ON e.id = db.espace_id
    LEFT JOIN users admin ON admin.id = db.traite_par
    $whereSQL
    ORDER BY db.created_at DESC
");
$demandes->execute($params);
$demandes = $demandes->fetchAll();

// Espaces supplémentaires par demande (bail groupé)
$espacesSuppParDemande = [];
if ($demandes) {
    $idsDemandes = array_column($demandes, 'id');
    $in = implode(',', array_fill(0, count($idsDemandes), '?'));
    $stmtSupp = $pdo->prepare("SELECT dbe.demande_id, e.nom FROM demande_bail_espaces dbe JOIN espaces e ON e.id = dbe.espace_id WHERE dbe.demande_id IN ($in)");
    $stmtSupp->execute($idsDemandes);
    foreach ($stmtSupp->fetchAll() as $row) { $espacesSuppParDemande[$row['demande_id']][] = $row['nom']; }
}

$espacesBailPossible = $pdo->query("
    SELECT e.id, e.nom FROM espaces e
    WHERE (e.gerant_externe IS NULL OR e.gerant_externe = '')
      AND EXISTS (SELECT 1 FROM tarifs t WHERE t.espace_id = e.id AND t.est_bail = 1)
    ORDER BY e.nom ASC
")->fetchAll();

$nbEnAttente = (int)$pdo->query("SELECT COUNT(*) FROM demandes_bail WHERE statut = 'en_attente'")->fetchColumn();

$pageTitle = "Demandes de bail";
require __DIR__ . '/_admin_header.php';
?>

<div class="px-4 sm:px-6 py-8">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Demandes de bail</h1>
      <p class="text-sm text-slate-500 mt-0.5"><?= $nbEnAttente ?> demande(s) en attente de traitement</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
    <a href="export.php?type=demandes_bail" target="_blank" rel="noopener" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition"><i class="fas fa-file-excel"></i> Exporter (Excel)</a>
    <?php if (!$readonly): ?>
    <button onclick="document.getElementById('formGuichet').classList.toggle('hidden')"
            class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-accent-dark transition shadow-lg">
      <i class="fas fa-store"></i> Saisir une demande au guichet
    </button>
    <?php endif; ?>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok'?'bg-green-50 border border-green-200 text-green-700':'bg-red-50 border border-red-200 text-accent' ?>">
    <i class="fas <?= $msg[0]==='ok'?'fa-check-circle text-green-500':'fa-exclamation-circle text-accent' ?>"></i>
    <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
  </div>
  <?php endif; ?>

  <?php if (!$readonly): ?>
  <div id="formGuichet" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-8">
    <h2 class="font-black text-primary uppercase italic text-sm mb-5">Nouvelle demande saisie au guichet</h2>
    <form method="POST" class="grid sm:grid-cols-2 gap-5">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="saisie_guichet">
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom <span class="text-accent">*</span></label>
        <input type="text" name="nom" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Prénom</label>
        <input type="text" name="prenom" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Téléphone <span class="text-accent">*</span></label>
        <input type="text" name="telephone" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Email <span class="text-accent">*</span></label>
        <input type="email" name="email" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Espace <span class="text-accent">*</span></label>
        <select name="espace_id" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          <option value="">— Choisir —</option>
          <?php foreach ($espacesBailPossible as $esp): ?>
          <option value="<?= $esp['id'] ?>"><?= e($esp['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Durée souhaitée</label>
        <select name="duree_souhaitee" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          <option value="mensuel">Mensuel</option>
          <option value="trimestriel">Trimestriel</option>
          <option value="semestriel">Semestriel</option>
          <option value="annuel">Annuel</option>
        </select>
      </div>
      <div>
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Date de début souhaitée</label>
        <input type="date" name="date_debut_souhaitee" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Usage prévu</label>
        <input type="text" name="usage_prevu" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
      </div>
      <div class="sm:col-span-2">
        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Note / message</label>
        <textarea name="message" rows="3" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-medium text-primary outline-none focus:border-primary text-sm resize-none"></textarea>
      </div>
      <div class="sm:col-span-2 flex justify-end">
        <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">Enregistrer la demande</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Filtres -->
  <div class="flex gap-2 mb-5">
    <?php foreach (['en_attente'=>'En attente','acceptee'=>'Acceptées','refusee'=>'Refusées'] as $val=>$lab): ?>
    <a href="?statut=<?= $val ?>" class="text-xs font-black uppercase px-4 py-2 rounded-xl transition <?= $filterStatut===$val ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' ?>"><?= $lab ?></a>
    <?php endforeach; ?>
  </div>

  <div class="space-y-4">
    <?php foreach ($demandes as $d): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
          <p class="font-black text-primary text-sm"><?= e(trim(($d['prenom'] ?? '').' '.$d['nom'])) ?>
            <span class="ml-2 text-[9px] font-black uppercase px-2 py-0.5 rounded-full <?= $d['canal']==='guichet' ? 'bg-orange-100 text-orange-700' : 'bg-sky-100 text-sky-700' ?>"><?= $d['canal']==='guichet' ? 'Guichet' : 'En ligne' ?></span>
          </p>
          <p class="text-xs text-slate-400 mt-0.5"><?= e($d['telephone']) ?><?= $d['email'] ? ' · '.e($d['email']) : '' ?></p>
        </div>
        <span class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full <?= ['en_attente'=>'bg-amber-100 text-amber-700','acceptee'=>'bg-emerald-100 text-emerald-700','refusee'=>'bg-red-100 text-red-700'][$d['statut']] ?>">
          <?= ['en_attente'=>'En attente','acceptee'=>'Acceptée','refusee'=>'Refusée'][$d['statut']] ?>
        </span>
      </div>
      <div class="grid sm:grid-cols-2 gap-3 text-xs text-slate-600 mb-3">
        <p><i class="fas fa-building w-4 text-slate-300"></i> <?= e($d['espace_nom']) ?>
          <?php if (!empty($espacesSuppParDemande[$d['id']])): ?>
          <span class="ml-1 text-[9px] font-black uppercase bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">+ <?= implode(', ', $espacesSuppParDemande[$d['id']]) ?></span>
          <?php endif; ?>
        </p>
        <p><i class="fas fa-calendar w-4 text-slate-300"></i> <?= ucfirst($d['duree_souhaitee']) ?><?= $d['date_debut_souhaitee'] ? ' — dès le '.date('d/m/Y', strtotime($d['date_debut_souhaitee'])) : '' ?></p>
        <?php if ($d['usage_prevu']): ?><p class="sm:col-span-2"><i class="fas fa-info-circle w-4 text-slate-300"></i> <?= e($d['usage_prevu']) ?></p><?php endif; ?>
      </div>
      <?php if ($d['message']): ?>
      <p class="text-xs text-slate-500 bg-slate-50 rounded-xl p-3 italic mb-3">« <?= e($d['message']) ?> »</p>
      <?php endif; ?>

      <?php if ($d['statut'] !== 'en_attente'): ?>
      <p class="text-[10px] text-slate-400">Traitée par <?= e($d['traite_par_nom'] ?? '—') ?> le <?= date('d/m/Y à H:i', strtotime($d['date_traitement'])) ?><?= $d['note_traitement'] ? ' — '.e($d['note_traitement']) : '' ?></p>
      <?php elseif (!$readonly): ?>
      <form method="POST" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="traiter">
        <input type="hidden" name="id" value="<?= $d['id'] ?>">
        <input type="text" name="note_traitement" placeholder="Note (optionnel)" class="flex-1 min-w-[160px] rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-primary outline-none focus:border-primary">
        <button type="submit" name="statut" value="acceptee" class="bg-emerald-500 text-white text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-emerald-600 transition">Accepter</button>
        <button type="submit" name="statut" value="refusee" class="bg-red-50 text-accent text-[11px] font-black uppercase px-4 py-2 rounded-xl hover:bg-red-500 hover:text-white transition">Refuser</button>
      </form>
      <?php endif; ?>
      <?php if (is_superadmin()): ?>
      <form method="POST" class="mt-3 text-right" onsubmit="return confirm('Supprimer définitivement cette demande de bail ? (erreur ou test)')">
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
