<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$uid = (int)$_SESSION['user_id'];
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $actuel  = (string)($_POST['mdp_actuel'] ?? '');
        $nouveau = (string)($_POST['mdp_nouveau'] ?? '');
        $confirm = (string)($_POST['mdp_confirmation'] ?? '');
        $hash = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $hash->execute([$uid]);
        $hash = (string)$hash->fetchColumn();

        if (!password_verify($actuel, $hash)) {
            $msg = ['err', 'Le mot de passe actuel est incorrect.'];
        } elseif (strlen($nouveau) < 8) {
            $msg = ['err', 'Le nouveau mot de passe doit contenir au moins 8 caractères.'];
        } elseif ($nouveau !== $confirm) {
            $msg = ['err', 'La confirmation ne correspond pas au nouveau mot de passe.'];
        } elseif (mot_de_passe_par_defaut($nouveau)) {
            $msg = ['err', 'Ce mot de passe est trop connu : choisissez-en un autre.'];
        } elseif (password_verify($nouveau, $hash)) {
            $msg = ['err', 'Le nouveau mot de passe doit être différent de l\'actuel.'];
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($nouveau, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            unset($_SESSION['mdp_a_changer']);
            log_activity('mdp_modifie', 'users', 'Mot de passe personnel modifié');
            $msg = ['ok', 'Votre mot de passe a été modifié.'];
        }
    }
}

$pageTitle = 'Mon mot de passe';
require __DIR__ . '/_admin_header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Mon mot de passe</h1>
  <p class="text-sm text-slate-500 mt-0.5">Modifiez le mot de passe de votre compte d'administration.</p>
</div>

<?php if (!empty($_SESSION['mdp_a_changer']) && !$msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800">
  <i class="fas fa-key"></i>
  <span class="font-bold text-sm">Votre compte utilise encore le mot de passe initial. Choisissez un nouveau mot de passe personnel pour accéder à l'administration.</span>
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0] === 'ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0] === 'ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<div class="max-w-2xl bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
  <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
    <h2 class="font-black text-primary text-sm uppercase italic flex items-center gap-2"><i class="fas fa-key text-accent"></i> Changer mon mot de passe</h2>
  </div>
  <form method="POST" class="p-6 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <?php foreach ([
      ['mdp_actuel', 'Mot de passe actuel', 'current-password'],
      ['mdp_nouveau', 'Nouveau mot de passe (8 caractères min.)', 'new-password'],
      ['mdp_confirmation', 'Confirmation', 'new-password'],
    ] as [$name, $label, $auto]): ?>
    <div>
      <label for="<?= $name ?>" class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2"><?= $label ?></label>
      <input type="password" id="<?= $name ?>" name="<?= $name ?>" required autocomplete="<?= $auto ?>" <?= $name !== 'mdp_actuel' ? 'minlength="8"' : '' ?>
             class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
    </div>
    <?php endforeach; ?>
    <div class="flex justify-end pt-2 border-t border-slate-100">
      <button type="submit" class="flex items-center gap-2 bg-primary text-white text-xs font-black uppercase px-6 py-3 rounded-xl hover:bg-slate-800 transition">
        <i class="fas fa-key"></i> Changer le mot de passe
      </button>
    </div>
  </form>
</div>
<p class="max-w-2xl text-xs text-slate-500 mt-4">Mot de passe oublié ? La Direction peut le réinitialiser depuis « Utilisateurs ».</p>

<?php require __DIR__ . '/_admin_footer.php'; ?>
