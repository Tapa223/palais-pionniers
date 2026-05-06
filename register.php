<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$old = ['nom_complet'=>'','email'=>'','telephone'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['nom_complet'] = trim((string)($_POST['nom_complet'] ?? ''));
    $old['email']       = trim((string)($_POST['email'] ?? ''));
    $old['telephone']   = trim((string)($_POST['telephone'] ?? ''));
    $password           = (string)($_POST['password'] ?? '');
    $confirm            = (string)($_POST['confirm']  ?? '');

    if (mb_strlen($old['nom_complet']) < 2) $errors[] = "Nom trop court.";
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
    if (strlen($password) < 8) $errors[] = "Mot de passe trop court (min 8).";
    if ($password !== $confirm) $errors[] = "Les mots de passe ne correspondent pas.";

    if (!$errors) {
        $pdo = db();
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $errors[] = "Cet email est déjà utilisé.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (nom_complet, email, telephone, password_hash, role) VALUES (?, ?, ?, ?, 'user')");
            $ins->execute([$old['nom_complet'], $old['email'], $old['telephone'], $hash]);
            
            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['nom_complet'] = $old['nom_complet'];
            $_SESSION['role'] = 'user';
            header('Location: mon-compte.php');
            exit;
        }
    }
}

$pageTitle = "Inscription — Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>
<div class="container mx-auto max-w-md px-4 py-14">
    <div class="rounded-2xl border bg-white p-8 shadow-card">
        <h1 class="text-2xl font-bold">Créer un compte</h1>
        <?php foreach ($errors as $err): ?>
            <div class="mt-4 text-accent text-sm"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" class="mt-6 space-y-4">
            <input type="text" name="nom_complet" placeholder="Nom complet" required value="<?= e($old['nom_complet']) ?>" class="w-full border p-2 rounded">
            <input type="email" name="email" placeholder="Email" required value="<?= e($old['email']) ?>" class="w-full border p-2 rounded">
            <input type="tel" name="telephone" placeholder="Téléphone" value="<?= e($old['telephone']) ?>" class="w-full border p-2 rounded">
            <input type="password" name="password" placeholder="Mot de passe" required class="w-full border p-2 rounded">
            <input type="password" name="confirm" placeholder="Confirmer mot de passe" required class="w-full border p-2 rounded">
            <button class="w-full bg-accent text-white py-2 rounded font-bold">S'inscrire</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>