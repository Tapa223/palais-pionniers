<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo     = db();
$success = false;
$errors  = [];
$old     = ['nom'=>'','email'=>'','telephone'=>'','sujet'=>'','message'=>'','espace_id'=>'','activite_id'=>''];

$sujetsValides = ['reservation_espace','activite','information_generale','reclamation','autre'];
if (isset($_GET['sujet']) && in_array($_GET['sujet'], $sujetsValides, true)) {
    $old['sujet'] = $_GET['sujet'];
}
if (!empty($_GET['espace'])) {
    $old['espace_id'] = (int)$_GET['espace'];
}
if (!empty($_GET['service']) && is_string($_GET['service'])) {
    $old['message'] = "Je souhaite bénéficier du service « " . mb_substr(trim($_GET['service']), 0, 120) . " ». Merci de me contacter pour la mise en place.";
}

if (is_logged_in()) {
    $me = $pdo->prepare("SELECT nom_complet, email, telephone FROM users WHERE id = ?");
    $me->execute([$_SESSION['user_id']]);
    $me = $me->fetch();
    $old['nom']       = $me['nom_complet'] ?? '';
    $old['email']     = $me['email']       ?? '';
    $old['telephone'] = $me['telephone']   ?? '';
}

$espaces   = $pdo->query("SELECT id, nom FROM espaces WHERE disponible = 1 ORDER BY nom ASC")->fetchAll();
$activites = $pdo->query("SELECT id, nom FROM activites ORDER BY nom ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = "Requête invalide. Rechargez la page.";
    } else {
        $old['nom']         = trim($_POST['nom']       ?? '');
        $old['email']       = trim($_POST['email']     ?? '');
        $old['telephone']   = trim($_POST['telephone'] ?? '');
        $old['sujet']       = trim($_POST['sujet']     ?? '');
        $old['message']     = trim($_POST['message']   ?? '');
        $old['espace_id']   = !empty($_POST['espace_id'])   ? (int)$_POST['espace_id']   : null;
        $old['activite_id'] = !empty($_POST['activite_id']) ? (int)$_POST['activite_id'] : null;

        $sujetsValides = ['reservation_espace','activite','information_generale','reclamation','autre'];

        if (mb_strlen($old['nom']) < 2)                              $errors[] = "Nom trop court (min. 2 caractères).";
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))       $errors[] = "Adresse email invalide.";
        if (!in_array($old['sujet'], $sujetsValides, true))          $errors[] = "Veuillez choisir un sujet.";
        if (mb_strlen($old['message']) < 10)                         $errors[] = "Message trop court (min. 10 caractères).";
        if (mb_strlen($old['message']) > 5000)                       $errors[] = "Message trop long (5 000 caractères maximum).";
        if (!$errors && !envoi_formulaire_autorise('contact'))       $errors[] = "Vous avez déjà envoyé plusieurs messages. Merci de patienter avant d'en envoyer un nouveau.";

        if (!$errors) {
            $auteurId = (is_logged_in() && in_array($_SESSION['role'] ?? '', ['user', 'partenaire'], true)) ? (int)$_SESSION['user_id'] : null;
            if (colonne_existe($pdo, 'messages', 'user_id')) {
                $stmt = $pdo->prepare("
                    INSERT INTO messages
                        (user_id, nom, email, telephone, sujet, espace_id, activite_id, message, lu, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([
                    $auteurId, $old['nom'], $old['email'], $old['telephone'],
                    $old['sujet'], $old['espace_id'], $old['activite_id'],
                    $old['message']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO messages
                        (nom, email, telephone, sujet, espace_id, activite_id, message, lu, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
                ");
                $stmt->execute([
                    $old['nom'], $old['email'], $old['telephone'],
                    $old['sujet'], $old['espace_id'], $old['activite_id'],
                    $old['message']
                ]);
            }
            $nouveauMsgId = (int)$pdo->lastInsertId();
            $roleNotifie = ['reservation_espace' => 'admin_espaces', 'activite' => 'admin_activites'][$old['sujet']] ?? 'admin_messages';
            notify($roleNotifie, 'nouveau_message', "Nouveau message de « {$old['nom']} » — sujet : {$old['sujet']}.", "messages.php?id=$nouveauMsgId");
            envoi_formulaire_enregistre('contact');
            $success = true;
            $old = array_merge($old, ['telephone'=>'','sujet'=>'','message'=>'','espace_id'=>'','activite_id'=>'']);
        }
    }
}

$pageTitle = "Contact | Palais des Pionniers";
require __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50 min-h-screen py-10 md:py-16">
<div class="container mx-auto max-w-5xl px-4">

  <div class="text-center mb-10 md:mb-14">
    <span class="inline-flex items-center gap-2 bg-accent/10 text-accent text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-full mb-4">
      <i class="fas fa-envelope"></i> Nous contacter
    </span>
    <h1 class="text-3xl md:text-5xl font-black text-primary uppercase italic tracking-tighter">
      Écrivez-<span class="text-accent">nous</span>
    </h1>
    <p class="text-slate-500 mt-3 text-sm max-w-md mx-auto leading-relaxed">
      Une question sur une réservation, une activité ou autre chose ?<br>
      Notre équipe vous répond sous 24h.
    </p>
  </div>

  <div class="flex flex-col md:grid md:grid-cols-3 gap-6 md:gap-10">

    <div class="space-y-4 order-2 md:order-1">
      <?php foreach ([
        ['fa-map-marker-alt', 'Adresse',   'Magnambougou / Dianéguéla, Bamako, Mali'],
        ['fa-phone',          'Téléphone', '+223 76 45 42 59'],
        ['fa-envelope',       'Email',     'ppb@mjsports.gouv.ml'],
        ['fa-clock',          'Horaires',  'Lun – Sam : 08h00 – 18h00'],
      ] as [$icon, $label, $val]): ?>
      <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex items-start gap-4 hover:shadow-md transition">
        <div class="w-10 h-10 bg-primary/5 rounded-xl flex items-center justify-center flex-shrink-0">
          <i class="fas <?= $icon ?> text-accent text-sm"></i>
        </div>
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400"><?= $label ?></p>
          <p class="text-sm font-bold text-primary mt-0.5"><?= $val ?></p>
        </div>
      </div>
      <?php endforeach; ?>

      <a href="reserver.php"
         class="flex items-center gap-3 bg-primary text-white rounded-2xl p-5 hover:bg-slate-800 transition shadow-sm group">
        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center flex-shrink-0">
          <i class="fas fa-calendar-plus text-accent text-sm"></i>
        </div>
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-white/60">Réserver directement</p>
          <p class="text-sm font-black text-white mt-0.5 group-hover:text-accent transition">Faire une demande →</p>
        </div>
      </a>

      <?php
      $reseaux = array_filter(require __DIR__ . '/config/reseaux_sociaux.php', 'url_web_valide');
      $boutonsReseaux = [
          'facebook' => ['fa-facebook-f', 'Facebook', 'bg-blue-600 hover:bg-blue-700'],
      ];
      ?>
      <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Suivez-nous</p>
        <div class="grid grid-cols-1 gap-2">
          <?php foreach ($boutonsReseaux as $cle => [$icone, $nom, $couleur]): ?>
          <?php if (!empty($reseaux[$cle])): ?>
          <a href="<?= e($reseaux[$cle]) ?>" target="_blank" rel="noopener" aria-label="<?= $nom ?> du Palais des Pionniers"
             class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl <?= $couleur ?> text-white transition justify-center">
            <i class="fab <?= $icone ?> text-sm w-4 text-center"></i>
            <span class="text-xs font-black"><?= $nom ?></span>
          </a>
          <?php else: ?>
          <span class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl <?= $couleur ?> text-white justify-center">
            <i class="fab <?= $icone ?> text-sm w-4 text-center"></i>
            <span class="text-xs font-black"><?= $nom ?></span>
          </span>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="md:col-span-2 order-1 md:order-2">
      <div class="bg-white rounded-[2rem] shadow-xl p-6 md:p-10">

        <?php if ($success): ?>
        <div class="rounded-2xl bg-green-50 border border-green-200 p-5 flex items-start gap-4 mb-6">
          <i class="fas fa-check-circle text-green-500 text-xl mt-0.5 flex-shrink-0"></i>
          <div>
            <p class="font-black text-green-700 text-base">Message envoyé avec succès !</p>
            <p class="text-sm text-green-600 mt-1">Notre équipe vous répondra dans les plus brefs délais.</p>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="rounded-2xl bg-red-50 border border-red-200 p-4 mb-6 space-y-1.5">
          <?php foreach ($errors as $err): ?>
            <p class="text-sm text-accent font-bold flex items-center gap-2">
              <i class="fas fa-exclamation-circle flex-shrink-0"></i> <?= e($err) ?>
            </p>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
                Nom complet <span class="text-accent">*</span>
              </label>
              <div class="relative">
                <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="nom" required value="<?= e($old['nom']) ?>"
                       placeholder="Votre nom complet"
                       class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3.5 font-bold text-primary outline-none focus:border-primary focus:bg-white transition text-sm">
              </div>
            </div>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
                Email <span class="text-accent">*</span>
              </label>
              <div class="relative">
                <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="email" name="email" required value="<?= e($old['email']) ?>"
                       placeholder="votre@email.com"
                       class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3.5 font-bold text-primary outline-none focus:border-primary focus:bg-white transition text-sm">
              </div>
            </div>
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Téléphone</label>
            <div class="relative">
              <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
              <input type="tel" name="telephone" value="<?= e($old['telephone']) ?>"
                     placeholder="+223 XX XX XX XX"
                     class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3.5 font-bold text-primary outline-none focus:border-primary focus:bg-white transition text-sm">
            </div>
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
              Sujet <span class="text-accent">*</span>
            </label>
            <div class="relative">
              <i class="fas fa-tag absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
              <select name="sujet" required onchange="onSujetChange(this.value)"
                      class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-10 py-3.5 font-bold text-primary outline-none focus:border-primary focus:bg-white transition text-sm appearance-none cursor-pointer">
                <option value="">— Choisir un sujet —</option>
                <option value="reservation_espace"   <?= $old['sujet']==='reservation_espace'   ?'selected':''?>>Réservation d'espace</option>
                <option value="activite"             <?= $old['sujet']==='activite'             ?'selected':''?>>Activité</option>
                <option value="information_generale" <?= $old['sujet']==='information_generale' ?'selected':''?>>Information générale</option>
                <option value="reclamation"          <?= $old['sujet']==='reclamation'          ?'selected':''?>>Réclamation</option>
                <option value="autre"                <?= $old['sujet']==='autre'                ?'selected':''?>>Autre</option>
              </select>
              <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            </div>
          </div>

          <div id="espaceField" class="hidden transition-all">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Espace concerné</label>
            <div class="relative">
              <i class="fas fa-building absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
              <select name="espace_id"
                      class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-10 py-3.5 font-bold text-primary outline-none focus:border-primary focus:bg-white transition text-sm appearance-none cursor-pointer">
                <option value="">— Sélectionner (optionnel) —</option>
                <?php foreach ($espaces as $esp): ?>
                  <option value="<?= $esp['id'] ?>" <?= $old['espace_id']==$esp['id']?'selected':''?>>
                    <?= e($esp['nom']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            </div>
          </div>

          <div id="activiteField" class="hidden transition-all">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Activité concernée</label>
            <div class="relative">
              <i class="fas fa-star absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
              <select name="activite_id"
                      class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-10 py-3.5 font-bold text-primary outline-none focus:border-primary focus:bg-white transition text-sm appearance-none cursor-pointer">
                <option value="">— Sélectionner (optionnel) —</option>
                <?php foreach ($activites as $act): ?>
                  <option value="<?= $act['id'] ?>" <?= $old['activite_id']==$act['id']?'selected':''?>>
                    <?= e($act['nom']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            </div>
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
              Message <span class="text-accent">*</span>
            </label>
            <div class="relative">
              <i class="fas fa-align-left absolute left-4 top-4 text-slate-400 text-sm"></i>
              <textarea name="message" rows="5" required minlength="10"
                        placeholder="Décrivez votre demande en détail..."
                        oninput="countMsg(this)"
                        class="w-full rounded-2xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3.5 font-semibold text-primary outline-none focus:border-primary focus:bg-white transition text-sm resize-none"><?= e($old['message']) ?></textarea>
            </div>
            <p class="text-right text-[10px] text-slate-400 mt-1" id="msgCount">0 / 10 min.</p>
          </div>

          <button type="submit"
                  class="w-full bg-primary hover:bg-slate-900 text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm transition-all shadow-lg hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2">
            <i class="fas fa-paper-plane"></i> Envoyer le message
          </button>

          <p class="text-center text-xs text-slate-400">
            <i class="fas fa-shield-alt mr-1 text-green-500"></i>
            Vos données restent confidentielles et ne sont utilisées qu'pour vous répondre.
          </p>
        </form>
      </div>
    </div>
  </div>
</div>
</div>

<script>
function onSujetChange(val) {
    document.getElementById('espaceField').classList.toggle('hidden',  val !== 'reservation_espace');
    document.getElementById('activiteField').classList.toggle('hidden', val !== 'activite');
}
function countMsg(el) {
    const n = el.value.trim().length;
    const c = document.getElementById('msgCount');
    c.textContent = n + (n < 10 ? ' / 10 min.' : ' caractères');
    c.className = 'text-right text-[10px] mt-1 ' + (n >= 10 ? 'text-green-600' : 'text-slate-400');
}
onSujetChange(document.querySelector('select[name="sujet"]').value);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>