<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','superadmin']);

$pdo = db();
$msg = null;
$readonly = is_readonly_admin();

$roleMeta = [
    'superadmin'      => ['Super Admin (Direction)', 'bg-accent text-white'],
    'ministre'        => ['Ministre / Rep.', 'bg-yellow-100 text-yellow-800'],
    'admin_espaces'   => ['Admin Espaces',   'bg-blue-100 text-blue-700'],
    'admin_activites' => ['Admin Activités', 'bg-purple-100 text-purple-700'],
    'admin_messages'  => ['Admin Messages',  'bg-green-100 text-green-700'],
    'admin_comptable' => ['Comptable',       'bg-teal-100 text-teal-700'],
    'user'            => ['Utilisateur',     'bg-slate-100 text-slate-500'],
];
$rolesDisponibles = array_keys($roleMeta);

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        $moi    = (int)current_user()['id'];

        if ($action === 'set_role' && $id && $id !== $moi) {
            $newRole = in_array($_POST['role'], $rolesDisponibles) ? $_POST['role'] : 'user';
            $old = $pdo->prepare("SELECT nom_complet, role FROM users WHERE id = ?");
            $old->execute([$id]); $old = $old->fetch();
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $id]);
            log_activity('user_role_change', 'users', "Rôle de «{$old['nom_complet']}» : {$old['role']} → $newRole");
            $msg = ['ok', 'Rôle mis à jour.'];
        }

        if ($action === 'toggle_actif' && $id && $id !== $moi) {
            $uInfo = $pdo->prepare("SELECT nom_complet, actif, role FROM users WHERE id = ?");
            $uInfo->execute([$id]); $uInfo = $uInfo->fetch();
            if ($uInfo && $uInfo['role'] === 'superadmin' && $uInfo['actif']) {
                $msg = ['err', 'Un compte Super Admin (Direction) ne peut pas être bloqué depuis cette interface.'];
            } else {
                $pdo->prepare("UPDATE users SET actif = 1 - actif WHERE id = ?")->execute([$id]);
                $label = $uInfo['actif'] ? 'bloqué' : 'débloqué';
                log_activity($uInfo['actif'] ? 'user_bloque' : 'user_debloque', 'users', "Compte «{$uInfo['nom_complet']}» $label");
                $msg = ['ok', 'Statut mis à jour.'];
            }
        }

        if ($action === 'create_user') {
            $prenom  = trim($_POST['prenom']    ?? '');
            $nom_fam = trim($_POST['nom_fam']   ?? '');
            $email   = trim($_POST['email']     ?? '');
            $tel     = trim($_POST['telephone'] ?? '');
            $role    = in_array($_POST['role'], $rolesDisponibles) ? $_POST['role'] : 'user';
            $mdp     = $_POST['password'] ?? '';

            if (!$prenom || !$nom_fam || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($mdp) < 6) {
                $msg = ['err', 'Tous les champs sont requis (mot de passe min. 6 caractères).'];
            } else {
                $exist = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $exist->execute([$email]);
                if ($exist->fetch()) {
                    $msg = ['err', 'Cet email est déjà utilisé.'];
                } else {
                    $nomComplet = $prenom . ' ' . mb_strtoupper($nom_fam);
                    $hash = password_hash($mdp, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (nom_complet, email, telephone, password_hash, role, actif) VALUES (?,?,?,?,?,1)")
                        ->execute([$nomComplet, $email, $tel, $hash, $role]);
                    $roleLabel = $roleMeta[$role][0] ?? $role;
                    log_activity('user_cree', 'users', "Compte créé : $nomComplet ($email) — Rôle : $roleLabel");
                    $msg = ['ok', "Compte «$nomComplet» créé — Rôle : $roleLabel."];
                }
            }
        }

        if ($action === 'delete' && $id && $id !== $moi) {
            $uDel = $pdo->prepare("SELECT nom_complet, role FROM users WHERE id = ?");
            $uDel->execute([$id]); $uDel = $uDel->fetch();
            if ($uDel && $uDel['role'] === 'superadmin') {
                $msg = ['err', 'Un compte Super Admin (Direction) ne peut pas être désactivé depuis cette interface.'];
            } else {
                // Désactivation réversible plutôt que suppression définitive —
                // conserve l'historique et le journal d'activité liés à ce compte.
                $pdo->prepare("UPDATE users SET actif = 0 WHERE id = ?")->execute([$id]);
                log_activity('user_bloque', 'users', "Compte désactivé (ex-suppression) : {$uDel['nom_complet']} ({$uDel['role']})");
                $msg = ['ok', "Compte «{$uDel['nom_complet']}» désactivé — il peut être réactivé à tout moment."];
            }
        }
    }
}

$search     = trim($_GET['q']    ?? '');
$filterRole = $_GET['role']      ?? '';
$where      = 'WHERE 1=1';
$params     = [];
if ($search) {
    $where   .= ' AND (nom_complet LIKE ? OR email LIKE ? OR telephone LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filterRole && in_array($filterRole, $rolesDisponibles)) {
    $where   .= ' AND role = ?';
    $params[] = $filterRole;
}
$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$countByRole = [];
foreach ($rolesDisponibles as $r) {
    $countByRole[$r] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = '$r'")->fetchColumn();
}

$pageTitle = "Utilisateurs & Rôles";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Utilisateurs & Rôles</h1>
    <p class="text-sm text-slate-500 mt-0.5"><?= count($users) ?> compte(s) affiché(s)</p>
  </div>
  <?php if (!$readonly): ?>
  <button onclick="document.getElementById('createForm').classList.toggle('hidden')"
          class="flex items-center gap-2 bg-accent text-white text-xs font-black uppercase px-5 py-3 rounded-xl hover:bg-red-700 transition shadow-lg">
    <i class="fas fa-user-plus"></i> Créer un compte
  </button>
  <?php else: ?>
  <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><i class="fas fa-eye mr-1"></i> Lecture seule</span>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<?php if (!$readonly): ?>
<div id="createForm" class="hidden mb-6 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
    <h2 class="font-black text-primary text-sm uppercase italic flex items-center gap-2">
      <i class="fas fa-user-plus text-accent"></i> Nouveau compte
    </h2>
    <button onclick="document.getElementById('createForm').classList.add('hidden')"
            class="w-7 h-7 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-accent transition">
      <i class="fas fa-times text-xs"></i>
    </button>
  </div>
  <form method="POST" class="p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action"     value="create_user">
    <?php foreach ([
      ['prenom','Prénom *','text','Amadou'],
      ['nom_fam','Nom *','text','COULIBALY'],
      ['email','Email *','email','email@exemple.ml'],
      ['telephone','Téléphone','tel','+223 XX XX XX XX'],
      ['password','Mot de passe *','password','Min. 6 caractères'],
    ] as [$name,$label,$type,$ph]): ?>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2"><?= $label ?></label>
      <input type="<?= $type ?>" name="<?= $name ?>" placeholder="<?= $ph ?>" <?= str_contains($label,'*')?'required':'' ?>
             class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
    </div>
    <?php endforeach; ?>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Rôle *</label>
      <div class="relative">
        <select name="role" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-2.5 font-bold text-primary outline-none text-sm appearance-none">
          <?php foreach ($roleMeta as $val => [$label, $_]): ?>
            <option value="<?= $val ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
      </div>
    </div>
    <div class="sm:col-span-2 lg:col-span-3 flex justify-end pt-2 border-t border-slate-100">
      <button type="submit" class="flex items-center gap-2 bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">
        <i class="fas fa-user-plus"></i> Créer le compte
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[180px]">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Rechercher</label>
      <div class="relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Nom, email, téléphone..."
               class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-primary outline-none focus:border-primary">
      </div>
    </div>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Rôle</label>
      <div class="relative">
        <select name="role" class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none pr-8 appearance-none">
          <option value="">Tous</option>
          <?php foreach ($roleMeta as $val => [$label, $_]): ?>
            <option value="<?= $val ?>" <?= $filterRole===$val?'selected':''?>><?= $label ?> (<?= $countByRole[$val] ?? 0 ?>)</option>
          <?php endforeach; ?>
        </select>
        <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
      </div>
    </div>
    <button class="bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    <?php if ($search || $filterRole): ?>
    <a href="users.php" class="text-xs font-black text-slate-400 hover:text-primary px-3 py-2.5 rounded-xl border border-slate-200 transition">
      <i class="fas fa-times mr-1"></i> Reset
    </a>
    <?php endif; ?>
  </form>
  <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-slate-100">
    <?php foreach ($roleMeta as $val => [$label, $cls]): ?>
    <a href="?role=<?= $val ?>" class="text-[9px] font-black px-3 py-1.5 rounded-full border transition <?= $filterRole===$val ? 'border-primary bg-primary text-white' : 'border-slate-200 '.$cls ?>">
      <?= $label ?> <span class="opacity-70"><?= $countByRole[$val] ?? 0 ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <?php if (empty($users)): ?>
    <div class="py-16 text-center"><i class="fas fa-users text-4xl text-slate-200 mb-3"></i><p class="text-slate-400 font-semibold">Aucun utilisateur trouvé.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[700px]">
      <thead class="bg-slate-50 text-[10px] uppercase tracking-widest text-slate-400 text-left">
        <tr>
          <th class="px-5 py-3">Utilisateur</th>
          <th class="px-5 py-3">Contact</th>
          <th class="px-5 py-3 text-center">Rôle</th>
          <th class="px-5 py-3 text-center">Statut</th>
          <th class="px-5 py-3 text-center">Inscription</th>
          <th class="px-5 py-3 text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-50">
        <?php $moi = (int)current_user()['id']; ?>
        <?php foreach ($users as $u): ?>
        <?php [$rl,$rc] = $roleMeta[$u['role']] ?? ['?','bg-slate-100 text-slate-400']; $isSelf = ((int)$u['id']===$moi); ?>
        <tr class="hover:bg-slate-50 transition <?= $isSelf?'bg-primary/5':'' ?>">
          <td class="px-5 py-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl <?= $isSelf?'bg-accent':'bg-primary/10' ?> flex items-center justify-center font-black text-sm <?= $isSelf?'text-white':'text-primary' ?> flex-shrink-0">
                <?= strtoupper(substr($u['nom_complet'],0,1)) ?>
              </div>
              <div>
                <p class="font-black text-primary text-sm"><?= e($u['nom_complet']) ?><?= $isSelf?' <span class="text-[9px] text-accent">(vous)</span>':'' ?></p>
                <p class="text-xs text-slate-400"><?= e($u['email']) ?></p>
              </div>
            </div>
          </td>
          <td class="px-5 py-4 text-sm font-bold text-slate-700"><?= e($u['telephone']?:'—') ?></td>
          <td class="px-5 py-4 text-center">
            <?php if ($isSelf || $readonly): ?>
              <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black <?= $rc ?>"><?= $rl ?></span>
            <?php else: ?>
              <form method="POST" class="inline-flex">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="set_role">
                <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                <div class="relative">
                  <select name="role" onchange="this.form.submit()"
                          class="text-[10px] font-black rounded-xl border-2 px-3 py-1.5 outline-none cursor-pointer appearance-none pr-7 <?= $rc ?> border-slate-200 hover:border-primary transition">
                    <?php foreach ($roleMeta as $val=>[$label,$_]): ?>
                      <option value="<?= $val ?>" <?= $u['role']===$val?'selected':''?>><?= $label ?></option>
                    <?php endforeach; ?>
                  </select>
                  <i class="fas fa-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-[8px] pointer-events-none opacity-60"></i>
                </div>
              </form>
            <?php endif; ?>
          </td>
          <td class="px-5 py-4 text-center">
            <span class="inline-flex items-center gap-1.5 text-[10px] font-black px-2.5 py-1 rounded-full <?= $u['actif']?'bg-green-100 text-green-700':'bg-red-100 text-red-600' ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $u['actif']?'bg-green-500':'bg-red-500' ?>"></span>
              <?= $u['actif']?'Actif':'Bloqué' ?>
            </span>
          </td>
          <td class="px-5 py-4 text-center text-xs text-slate-500 font-semibold"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td class="px-5 py-4">
            <?php if ($isSelf): ?>
              <span class="text-[10px] text-slate-300 italic">Votre compte</span>
            <?php elseif ($readonly): ?>
              <div class="text-center text-slate-300"><i class="fas fa-eye text-xs"></i></div>
            <?php else: ?>
            <div class="flex items-center justify-center gap-2">
              <form method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_actif">
                <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                <button type="submit" onclick="return confirm('<?= $u['actif']?'Bloquer':'Débloquer' ?> ce compte ?')"
                        class="w-8 h-8 flex items-center justify-center rounded-xl transition text-xs <?= $u['actif']?'bg-red-50 text-red-400 hover:bg-red-500 hover:text-white':'bg-green-50 text-green-500 hover:bg-green-500 hover:text-white' ?>">
                  <i class="fas <?= $u['actif']?'fa-user-slash':'fa-user-check' ?>"></i>
                </button>
              </form>
              <form method="POST" class="inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id"     value="<?= $u['id'] ?>">
                <button type="submit" onclick="return confirm('Désactiver ce compte ? Il pourra être réactivé plus tard depuis cette même page.')" title="Désactiver (réversible)"
                        class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-red-500 hover:text-white transition text-xs">
                  <i class="fas fa-user-slash"></i>
                </button>
              </form>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_admin_footer.php'; ?>