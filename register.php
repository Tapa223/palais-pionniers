<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) { header('Location: mon-compte.php'); exit; }

$errors = [];
$old = ['prenom'=>'','nom'=>'','email'=>'','telephone'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = "Requête invalide.";
    } else {
        $old['prenom']    = trim((string)($_POST['prenom'] ?? ''));
        $old['nom']       = trim((string)($_POST['nom'] ?? ''));
        $old['email']     = trim((string)($_POST['email'] ?? ''));
        $old['telephone'] = trim((string)($_POST['telephone'] ?? ''));
        $password         = (string)($_POST['password'] ?? '');
        $confirm          = (string)($_POST['confirm']  ?? '');

        if (mb_strlen($old['prenom']) < 2)  $errors[] = "Prénom trop court (min 2 caractères).";
        if (mb_strlen($old['nom']) < 2)     $errors[] = "Nom trop court (min 2 caractères).";
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
        if (strlen($password) < 8)          $errors[] = "Mot de passe trop court (min 8 caractères).";
        if ($password !== $confirm)         $errors[] = "Les mots de passe ne correspondent pas.";

        if (!$errors) {
            $pdo = db();
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$old['email']]);
            if ($check->fetch()) {
                $errors[] = "Cet email est déjà utilisé.";
            } else {
                $nom_complet = $old['prenom'] . ' ' . mb_strtoupper($old['nom']);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO users (nom_complet, email, telephone, password_hash, role) VALUES (?, ?, ?, ?, 'user')");
                $ins->execute([$nom_complet, $old['email'], $old['telephone'], $hash]);

                session_regenerate_id(true);
                $_SESSION['user_id']     = (int)$pdo->lastInsertId();
                $_SESSION['nom_complet'] = $nom_complet;
                $_SESSION['email']       = $old['email'];
                $_SESSION['role']        = 'user';
                header('Location: login.php?registered=1');
                exit;
            }
        }
    }
}

$pageTitle = "Inscription | Palais des Pionniers";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="robots" content="noindex, follow">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/favicon/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/tailwind.css<?= version_fichier('assets/css/tailwind.css') ?>">
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
        .input-field-no-icon { padding-left: 1.25rem; }

        .strength-bar { height: 4px; border-radius: 2px; transition: all 0.3s; }
    </style>
</head>
<body class="min-h-screen flex">

    <div class="hidden lg:flex lg:w-1/2 bg-pattern flex-col justify-between p-12 relative overflow-hidden">
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-accent/10"></div>
        <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full bg-white/5"></div>

        <a href="<?= lien_page('index.php') ?>">
            <img src="assets/images/logopalais.png" alt="Palais des Pionniers" class="h-20 w-auto object-contain">
        </a>

        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 bg-green-500/20 text-green-400 text-xs font-black uppercase tracking-widest px-4 py-2 rounded-full mb-6">
                <i class="fas fa-user-plus"></i> Nouveau compte
            </div>
            <h2 class="text-4xl font-black text-white uppercase italic tracking-tighter leading-tight mb-4">
                Rejoignez le<br><span class="text-accent">Palais des</span><br>Pionniers
            </h2>
            <p class="text-slate-400 text-sm leading-relaxed max-w-sm">
                Créez votre compte gratuitement et accédez à la réservation en ligne de tous nos espaces sportifs, culturels et événementiels.
            </p>

            <div class="mt-8 space-y-3">
                <?php foreach ([
                    ['fas fa-check-circle text-green-400', 'Réservation 100% en ligne'],
                    ['fas fa-check-circle text-green-400', 'Suivi en temps réel de vos demandes'],
                    ['fas fa-check-circle text-green-400', 'Accès à tous les espaces du Palais'],
                ] as [$icon, $text]): ?>
                <div class="flex items-center gap-3">
                    <i class="<?= $icon ?> text-sm"></i>
                    <span class="text-slate-300 text-sm"><?= $text ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-map-marker-alt text-accent text-sm"></i>
            </div>
            <div>
                <p class="text-white text-xs font-bold">Palais des Pionniers</p>
                <p class="text-slate-400 text-xs">Magnambougou / Dianéguéla, Bamako, Mali</p>
            </div>
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 lg:p-12 bg-slate-50 overflow-y-auto">
        <div class="w-full max-w-md py-8">

            <div class="lg:hidden flex justify-center mb-8">
                <a href="<?= lien_page('index.php') ?>"><img src="assets/images/logopalais.png" alt="Logo" class="h-16 w-auto object-contain"></a>
            </div>

            <div class="mb-8">
                <h1 class="text-3xl font-black text-primary uppercase italic tracking-tighter">Créer un <span class="text-accent">compte</span></h1>
                <p class="text-slate-500 text-sm mt-1">Remplissez le formulaire pour commencer.</p>
            </div>

            <?php if ($errors): ?>
                <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <p class="text-sm text-accent font-semibold flex items-center gap-2">
                            <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Prénom</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="prenom" required value="<?= e($old['prenom']) ?>"
                                   placeholder="Ex: Amadou"
                                   class="input-field">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Nom</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="nom" required value="<?= e($old['nom']) ?>"
                                   placeholder="Ex: COULIBALY"
                                   class="input-field">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Adresse e-mail</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="email" name="email" required value="<?= e($old['email']) ?>"
                               placeholder="votre@email.com"
                               class="input-field">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Téléphone <span class="text-slate-300 normal-case font-normal">(optionnel)</span></label>
                    <div class="relative">
                        <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="tel" name="telephone" value="<?= e($old['telephone']) ?>"
                               placeholder="+223 XX XX XX XX"
                               class="input-field">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="password" id="pwInput" required
                               placeholder="Min. 8 caractères"
                               class="input-field pr-12"
                               oninput="checkStrength(this.value)">
                        <button type="button" onclick="togglePw('pwInput','eye1')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition">
                            <i class="fas fa-eye" id="eye1"></i>
                        </button>
                    </div>
                    <div class="mt-2 flex gap-1">
                        <div class="strength-bar flex-1 bg-slate-200" id="s1"></div>
                        <div class="strength-bar flex-1 bg-slate-200" id="s2"></div>
                        <div class="strength-bar flex-1 bg-slate-200" id="s3"></div>
                        <div class="strength-bar flex-1 bg-slate-200" id="s4"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1" id="strengthLabel"></p>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Confirmer le mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="password" name="confirm" id="pwConfirm" required
                               placeholder="Répétez le mot de passe"
                               class="input-field pr-12">
                        <button type="button" onclick="togglePw('pwConfirm','eye2')" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary transition">
                            <i class="fas fa-eye" id="eye2"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-accent hover:bg-accent-dark text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm transition-all shadow-lg hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2">
                    <i class="fas fa-user-plus"></i> Créer mon compte
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-sm text-slate-500">Déjà inscrit ?
                    <a href="login.php" class="text-primary font-black hover:underline ml-1">Se connecter</a>
                </p>
            </div>
            <div class="mt-4 text-center">
                <a href="<?= lien_page('index.php') ?>" class="text-xs text-slate-400 hover:text-primary transition">
                    <i class="fas fa-arrow-left mr-1"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

<script>
function togglePw(id, iconId) {
    const i = document.getElementById(id), ic = document.getElementById(iconId);
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash');
}

function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) score++;

    const colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-green-500'];
    const labels = ['Très faible','Faible','Moyen','Fort'];
    const bars = ['s1','s2','s3','s4'];

    bars.forEach((b, i) => {
        const el = document.getElementById(b);
        el.className = 'strength-bar flex-1';
        if (i < score) el.classList.add(colors[score-1]);
        else el.classList.add('bg-slate-200');
    });

    document.getElementById('strengthLabel').textContent = pw.length > 0 ? labels[score-1] || '' : '';
}
</script>
</body>
</html>