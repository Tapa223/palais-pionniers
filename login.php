<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: mon-compte.php');
    exit;
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = "Requête invalide. Veuillez réessayer.";
    } else {
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
            } elseif ($u['role'] === 'partenaire' && (!(int)$u['actif'] || !partenaire_utilisateur(db(), (int)$u['id']))) {
                // Compte partenaire désactivé (compte ou organisation) : accès refusé
                $errors[] = "Ce compte partenaire est désactivé. Contactez la Direction du Palais.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']     = (int)$u['id'];
                $_SESSION['nom_complet'] = $u['nom_complet'];
                $_SESSION['email']       = $u['email'];
                $_SESSION['role']        = $u['role'];

                switch ($u['role']) {
                    case 'superadmin':
                    case 'admin_espaces':
                    case 'admin_activites':
                    case 'admin_messages':
                    case 'admin_comptable':
                    case 'ministre':
                        $dest = 'admin/dashboard.php';
                        break;
                    default:
                        $dest = $_GET['redirect'] ?? 'mon-compte.php';
                }
                header('Location: ' . $dest);
                exit;
            }
        }
    }
}

$pageTitle = "Connexion — Palais des Pionniers";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/fonts.css">
    <style>
        body { font-family: Inter, sans-serif; }
        .bg-pattern {
            background-color: #0A2558;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .input-field {
            width: 100%; border-radius: 1rem; border: 2px solid #e2e8f0;
            background: #f8fafc; padding: 0.875rem 1.25rem 0.875rem 3rem;
            font-weight: 600; color: #0A2558; outline: none; transition: all 0.2s; font-size: 0.9rem;
        }
        .input-field:focus { border-color: #0A2558; background: #fff; box-shadow: 0 0 0 4px rgba(10,37,88,0.08); }
        .input-field::placeholder { color: #94a3b8; font-weight: 500; }
    </style>
</head>
<body class="min-h-screen flex">

    <div class="hidden lg:flex lg:w-1/2 bg-pattern flex-col justify-between p-12 relative overflow-hidden">
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-accent/10"></div>
        <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-white/5"></div>
        <a href="index.php">
            <img src="assets/images/logopalais.png" alt="Palais des Pionniers" class="h-20 w-auto object-contain">
        </a>
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 bg-accent/20 text-accent text-xs font-black uppercase tracking-widest px-4 py-2 rounded-full mb-6">
                <i class="fas fa-shield-alt"></i> Espace sécurisé
            </div>
            <h2 class="text-4xl font-black text-white uppercase italic tracking-tighter leading-tight mb-4">
                Gérez vos<br><span class="text-accent">réservations</span><br>en ligne
            </h2>
            <p class="text-slate-400 text-sm leading-relaxed max-w-sm">
                Accédez à votre espace personnel pour soumettre et suivre vos demandes de réservation au Palais des Pionniers de Magnambougou.
            </p>
        </div>
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-map-marker-alt text-accent text-sm"></i>
            </div>
            <div>
                <p class="text-white text-xs font-bold">Palais des Pionniers</p>
                <p class="text-slate-400 text-xs">Magnambougou / Dianéguéla, Bamako — Mali</p>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 lg:p-12 bg-slate-50">
        <div class="w-full max-w-md">
            <div class="lg:hidden flex justify-center mb-8">
                <a href="index.php"><img src="assets/images/logopalais.png" alt="Logo" class="h-16 w-auto object-contain"></a>
            </div>
            <div class="mb-8">
                <h1 class="text-3xl font-black text-primary uppercase italic tracking-tighter">Bon retour <span class="text-accent">!</span></h1>
                <p class="text-slate-500 text-sm mt-1">Connectez-vous à votre espace personnel.</p>
            </div>

            <?php if ($errors): ?>
                <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 flex items-start gap-3">
                    <i class="fas fa-exclamation-circle text-accent mt-0.5"></i>
                    <div><?php foreach ($errors as $err): ?><p class="text-sm text-accent font-semibold"><?= e($err) ?></p><?php endforeach; ?></div>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
                <div class="mb-6 rounded-2xl bg-green-50 border border-green-200 p-4 flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
                    <p class="text-sm text-green-700 font-semibold">Compte créé avec succès ! Connectez-vous.</p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Adresse e-mail</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" name="email" required value="<?= e($email) ?>" placeholder="votre@email.com" class="input-field">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password" id="pwInput" required placeholder="••••••••" class="input-field pr-12">
                        <button type="button" onclick="togglePw()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm transition-all shadow-lg hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm text-slate-500">Pas encore de compte ?
                    <a href="register.php" class="text-accent font-black hover:underline ml-1">Créer un compte</a>
                </p>
            </div>
            <div class="mt-4 text-center">
                <a href="index.php" class="text-xs text-slate-400 hover:text-primary transition">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

<script>
function togglePw() {
    const i = document.getElementById('pwInput'), ic = document.getElementById('eyeIcon');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash');
}
</script>
</body>
</html>