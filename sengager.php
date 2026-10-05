<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$msg = null;

// Images du hero — ajoute simplement d'autres fichiers ici pour transformer
// automatiquement ce bandeau en carrousel (défilement en fondu).
$heroSengagerImages = array_values(array_filter(
    ['assets/images/groupewague.jpg'],
    fn($p) => file_exists(__DIR__ . '/' . $p)
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide, merci de réessayer.'];
    } else {
        $nom       = trim($_POST['nom'] ?? '');
        $prenom    = trim($_POST['prenom'] ?? '') ?: null;
        $age       = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $dateNaissance = !empty($_POST['date_naissance']) ? $_POST['date_naissance'] : null;
        $tel       = trim($_POST['telephone'] ?? '');
        $email     = trim($_POST['email'] ?? '') ?: null;
        $commune   = trim($_POST['commune'] ?? '') ?: null;
        $profession= trim($_POST['profession'] ?? '') ?: null;
        $domaine   = trim($_POST['domaine_interet'] ?? '') ?: null;
        $motivation= trim($_POST['motivation'] ?? '') ?: null;

        if (!$nom || !$prenom || $age === null || !$dateNaissance || !$tel || !$email || !$commune || !$profession || !$domaine || !$motivation) {
            $msg = ['err', 'Merci de renseigner tous les champs du formulaire.'];
        } else {
            $pdo->prepare("
                INSERT INTO jeunes_engages (nom, prenom, age, date_naissance, telephone, email, commune, profession, domaine_interet, motivation)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ")->execute([$nom, $prenom, $age, $dateNaissance, $tel, $email, $commune, $profession, $domaine, $motivation]);
            notify('admin_activites', 'jeune_engage', "Nouvelle inscription « S'engager » — $nom" . ($prenom ? " $prenom" : ''), "jeunes-engages.php");
            $msg = ['ok', "Merci pour votre engagement ! Nous vous recontacterons prochainement pour vous présenter nos programmes."];
        }
    }
}

$pageTitle = "S'engager — Palais des Pionniers";
$page = 'sengager.php';
require __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50 min-h-screen">
  <div class="bg-white border-b border-slate-100 sticky top-0 z-40 shadow-sm">
    <div class="container mx-auto max-w-2xl px-4 py-3 flex items-center justify-between gap-4">
      <a href="<?= ($msg && $msg[0] === 'ok') ? 'index.php' : 'javascript:history.back()' ?>" class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest hover:text-accent transition flex-shrink-0 bg-primary/5 hover:bg-accent/10 px-3 py-1.5 rounded-full">
        <i class="fas fa-arrow-left"></i>
        <span class="hidden sm:inline">Retour</span>
      </a>
      <h1 class="text-sm font-black text-primary uppercase italic tracking-tighter text-center">
        S'<span class="text-accent">engager</span>
      </h1>
      <div class="w-12 flex-shrink-0"></div>
    </div>
  </div>

<section class="relative overflow-hidden bg-slate-900 text-white">
    <?php foreach ($heroSengagerImages as $i => $img): ?>
    <img src="<?= e($img) ?>" class="sengager-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 ease-in-out <?= $i === 0 ? 'opacity-40' : 'opacity-0' ?>" alt="">
    <?php endforeach; ?>
    <div class="absolute inset-0 bg-primary/40 z-10"></div>
    <div class="container mx-auto px-4 py-8 sm:py-16 relative z-20">
        <div class="max-w-xl">
        <span class="inline-flex items-center gap-2 text-[10px] sm:text-xs font-black uppercase tracking-widest bg-accent text-white px-4 py-2 rounded-full mb-3 sm:mb-4"><i class="fas fa-hand-fist"></i> Rejoins le mouvement</span>
        <h1 class="text-2xl sm:text-4xl font-black italic uppercase tracking-tighter text-white mb-2 sm:mb-3 drop-shadow-lg">Ton engagement <span class="text-[#FCD116]">change tout</span></h1>
        <p class="text-xs sm:text-base text-slate-100 font-medium leading-relaxed mb-4 sm:mb-6 drop-shadow-md">
            Civisme, sport, formation, volontariat — des milliers de jeunes construisent déjà l'avenir du Mali avec le Palais des Pionniers.
        </p>
        <div class="flex gap-2 sm:gap-4 flex-wrap">
            <span class="flex items-center gap-2 text-[10px] sm:text-xs font-bold text-white bg-white/15 backdrop-blur-sm rounded-full px-3 py-1.5"><i class="fas fa-users text-[#14B53A]"></i> Une communauté</span>
            <span class="flex items-center gap-2 text-[10px] sm:text-xs font-bold text-white bg-white/15 backdrop-blur-sm rounded-full px-3 py-1.5"><i class="fas fa-graduation-cap text-[#FCD116]"></i> Se former</span>
            <span class="flex items-center gap-2 text-[10px] sm:text-xs font-bold text-white bg-white/15 backdrop-blur-sm rounded-full px-3 py-1.5"><i class="fas fa-futbol text-white"></i> Sport & culture</span>
        </div>
        <div class="flex gap-1.5 h-1.5 w-24 mt-3 sm:mt-6 rounded-full overflow-hidden">
            <div class="flex-1 bg-[#14B53A]"></div>
            <div class="flex-1 bg-[#FCD116]"></div>
            <div class="flex-1 bg-[#CE1126]"></div>
        </div>
        </div>
    </div>
</section>

<section class="bg-slate-50">
    <div class="container mx-auto px-4 py-6 sm:py-10 max-w-2xl">

        <?php if ($msg): ?>
        <div class="mb-6 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
            <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
            <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!($msg && $msg[0] === 'ok')): ?>
        <form method="POST" class="bg-white rounded-[2rem] p-6 sm:p-10 border-t-4 border-accent shadow-lg space-y-5">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-user text-accent mr-1"></i>Nom <span class="text-accent">*</span></label>
                    <input type="text" name="nom" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-accent text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-user text-accent mr-1"></i>Prénom <span class="text-accent">*</span></label>
                    <input type="text" name="prenom" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-accent text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-birthday-cake text-[#FCD116] mr-1"></i>Âge <span class="text-accent">*</span></label>
                    <input type="number" name="age" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-[#FCD116] text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-calendar-day text-[#FCD116] mr-1"></i>Date de naissance <span class="text-accent">*</span></label>
                    <input type="date" name="date_naissance" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-[#FCD116] text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-phone-alt text-[#14B53A] mr-1"></i>Téléphone <span class="text-accent">*</span></label>
                    <input type="text" name="telephone" required placeholder="+223 XX XX XX XX" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-[#14B53A] text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-envelope text-sky-500 mr-1"></i>Email <span class="text-accent">*</span></label>
                    <input type="email" name="email" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-sky-500 text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-map-marker-alt text-accent mr-1"></i>Commune / Quartier <span class="text-accent">*</span></label>
                    <input type="text" name="commune" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-accent text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-briefcase text-[#14B53A] mr-1"></i>Profession <span class="text-accent">*</span></label>
                    <select name="profession" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-[#14B53A] text-sm">
                        <option value="">— Choisir —</option>
                        <option value="Étudiant(e)">Étudiant(e)</option>
                        <option value="Élève">Élève</option>
                        <option value="Travailleur(euse)">Travailleur(euse)</option>
                        <option value="Sans emploi">Sans emploi</option>
                        <option value="Entrepreneur(euse)">Entrepreneur(euse)</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-star text-[#FCD116] mr-1"></i>Domaine d'intérêt <span class="text-accent">*</span></label>
                <input type="text" name="domaine_interet" list="domainesSuggestions" required placeholder="Ex : Sport, Formation professionnelle, Volontariat..."
                       class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-[#FCD116] text-sm">
                <datalist id="domainesSuggestions">
                    <option value="Civisme et construction citoyenne">
                    <option value="Sport">
                    <option value="Formation professionnelle">
                    <option value="Volontariat">
                    <option value="Culture et activités">
                </datalist>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-primary mb-2"><i class="fas fa-comment-dots text-accent mr-1"></i>Motivation <span class="text-accent">*</span></label>
                <textarea name="motivation" rows="4" required class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-medium text-primary outline-none focus:border-accent text-sm resize-none"></textarea>
            </div>

            <button type="submit" class="w-full bg-white border-4 border-accent text-accent py-4 rounded-xl font-black uppercase tracking-widest text-sm hover:bg-accent hover:text-white transition shadow-lg">
                <i class="fas fa-hand-fist mr-2"></i>Je m'engage
            </button>
        </form>
        <?php endif; ?>
    </div>
</section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sengagerSlides = document.querySelectorAll('.sengager-slide');
    if (sengagerSlides.length > 1) {
        let sengagerIdx = 0;
        setInterval(() => {
            sengagerSlides[sengagerIdx].classList.replace('opacity-40', 'opacity-0');
            sengagerIdx = (sengagerIdx + 1) % sengagerSlides.length;
            sengagerSlides[sengagerIdx].classList.replace('opacity-0', 'opacity-40');
        }, 4500);
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
