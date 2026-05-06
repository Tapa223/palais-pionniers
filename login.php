<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = "Identifiants invalides.";
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($password, $u['password_hash'])) {
            $errors[] = "Email ou mot de passe incorrect.";
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']     = (int)$u['id'];
            $_SESSION['nom_complet'] = $u['nom_complet'];
            $_SESSION['email']       = $u['email'];
            $_SESSION['role']        = $u['role'];

            $dest = ($u['role'] === 'admin') ? 'admin/dashboard.php' : 'mon-compte.php';
            header('Location: ' . $dest);
            exit;
        }
    }
}

$pageTitle = "Connexion — Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto max-w-md px-4 py-14">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-card">
        <h1 class="text-2xl font-bold">Se connecter</h1>
        <?php foreach ($errors as $err): ?>
            <div class="mt-4 rounded-md bg-accent/10 p-3 text-sm text-accent"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="mt-6 space-y-4">
            <div>
                <label class="text-sm font-medium">Email</label>
                <input type="email" name="email" required value="<?= e($email) ?>" class="mt-1 w-full rounded-md border p-2 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Mot de passe</label>
                <input type="password" name="password" required class="mt-1 w-full rounded-md border p-2 text-sm">
            </div>
            <button class="w-full rounded-md bg-accent py-2.5 text-white font-bold">Se connecter</button>
        </form>
        <p class="mt-4 text-center text-sm text-slate-500">
            Pas de compte ? <a href="register.php" class="text-primary font-bold">Créer un compte</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>