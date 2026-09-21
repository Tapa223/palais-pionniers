<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$msg = null;

// Liste des espaces qui proposent réellement une option bail (tarif est_bail),
// et qui ne sont pas déjà loués intégralement (gerant_externe vide)
$espacesBailPossible = $pdo->query("
    SELECT e.id, e.nom, e.groupe_batiment
    FROM espaces e
    WHERE (e.gerant_externe IS NULL OR e.gerant_externe = '')
      AND EXISTS (SELECT 1 FROM tarifs t WHERE t.espace_id = e.id AND t.est_bail = 1)
    ORDER BY e.nom ASC
")->fetchAll();

$espacePreselectionne = isset($_GET['espace_id']) ? (int)$_GET['espace_id'] : 0;

// Pré-remplissage si le visiteur est déjà connecté à son compte
$moiConnecte = null;
if (is_logged_in()) {
    $stmtMoi = $pdo->prepare("SELECT nom_complet, telephone, email FROM users WHERE id = ?");
    $stmtMoi->execute([$_SESSION['user_id']]);
    $moiConnecte = $stmtMoi->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide, merci de réessayer.'];
    } else {
        $nom      = trim($_POST['nom'] ?? '');
        $prenom   = trim($_POST['prenom'] ?? '') ?: null;
        $tel      = trim($_POST['telephone'] ?? '');
        $email    = trim($_POST['email'] ?? '') ?: null;
        $espaceId = (int)($_POST['espace_id'] ?? 0);
        $usage    = trim($_POST['usage_prevu'] ?? '') ?: null;
        $duree    = in_array($_POST['duree_souhaitee'] ?? '', ['mensuel','trimestriel','semestriel','annuel'], true) ? $_POST['duree_souhaitee'] : 'mensuel';
        $dateDeb  = trim($_POST['date_debut_souhaitee'] ?? '') ?: null;
        $message  = trim($_POST['message'] ?? '') ?: null;

        $espaceValide = in_array($espaceId, array_column($espacesBailPossible, 'id'), true);

        // Espaces supplémentaires du même bâtiment, si le client a coché "prendre aussi..."
        $espacesSupp = array_map('intval', $_POST['espaces_supplementaires'] ?? []);
        $groupeDemande = null;
        foreach ($espacesBailPossible as $eb) { if ($eb['id'] == $espaceId) $groupeDemande = $eb['groupe_batiment']; }
        // Sécurité : ne garder que des espaces valides, du même groupe, et différents de l'espace principal
        $espacesSupp = array_values(array_filter($espacesSupp, function($id) use ($espacesBailPossible, $groupeDemande, $espaceId) {
            if ($id === $espaceId || !$groupeDemande) return false;
            foreach ($espacesBailPossible as $eb) { if ($eb['id'] == $id && $eb['groupe_batiment'] === $groupeDemande) return true; }
            return false;
        }));

        if (!$nom || !$tel || !$espaceValide || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = ['err', 'Merci de renseigner votre nom, votre téléphone, un email valide et l\'espace souhaité.'];
        } else {
            $pdo->prepare("
                INSERT INTO demandes_bail (nom, prenom, telephone, email, espace_id, usage_prevu, duree_souhaitee, date_debut_souhaitee, message, canal)
                VALUES (?,?,?,?,?,?,?,?,?,'en_ligne')
            ")->execute([$nom, $prenom, $tel, $email, $espaceId, $usage, $duree, $dateDeb, $message]);
            $demandeId = (int)$pdo->lastInsertId();

            foreach ($espacesSupp as $espSuppId) {
                $pdo->prepare("INSERT INTO demande_bail_espaces (demande_id, espace_id) VALUES (?,?)")->execute([$demandeId, $espSuppId]);
            }

            $espNom = $pdo->prepare("SELECT nom FROM espaces WHERE id = ?"); $espNom->execute([$espaceId]); $espNom = $espNom->fetchColumn();
            $suffixeGroupe = $espacesSupp ? ' (+ ' . count($espacesSupp) . ' autre(s) espace(s) du même bâtiment)' : '';
            notify('admin_espaces', 'demande_bail', "Nouvelle demande de bail pour « $espNom »$suffixeGroupe — $nom" . ($prenom ? " $prenom" : ''), "demandes-bail.php?id=$demandeId");
            notify('admin_comptable', 'demande_bail', "Nouvelle demande de bail pour « $espNom »$suffixeGroupe — $nom" . ($prenom ? " $prenom" : '') . ". À traiter avant tout encaissement.", "demandes-bail.php?id=$demandeId");

            $msg = ['ok', 'Votre demande a bien été enregistrée. Nous vous recontacterons prochainement.'];
        }
    }
}

$pageTitle = "Demande de bail — Palais des Pionniers";
$page = 'demande-bail.php';
require __DIR__ . '/includes/header.php';
?>

<div class="bg-slate-50 min-h-screen">

  <!-- Header sticky, compact -->
  <div class="bg-white border-b border-slate-100 sticky top-0 z-40 shadow-sm">
    <div class="container mx-auto max-w-2xl px-4 py-3 flex items-center justify-between gap-4">
      <a href="javascript:history.back()" class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest hover:text-accent transition flex-shrink-0 bg-primary/5 hover:bg-accent/10 px-3 py-1.5 rounded-full">
        <i class="fas fa-arrow-left"></i>
        <span class="hidden sm:inline">Retour</span>
      </a>
      <h1 class="text-sm font-black text-primary uppercase italic tracking-tighter text-center">
        Demande de <span class="text-accent">bail</span>
      </h1>
      <div class="w-12 flex-shrink-0"></div>
    </div>
  </div>

<section class="py-6 sm:py-12 bg-slate-50">
    <div class="container mx-auto px-4 max-w-2xl">
        <p class="text-xs sm:text-sm text-slate-500 mb-5 sm:mb-6 text-center">
            Vous souhaitez louer un espace du Palais sur une longue durée (association, club, entreprise...) ? Remplissez ce formulaire, notre équipe vous recontactera.
        </p>

        <?php if ($msg): ?>
        <div class="mb-6 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
            <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
            <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($moiConnecte): ?>
        <div class="mb-5 rounded-2xl p-3.5 flex items-center gap-2.5 bg-sky-50 border border-sky-100">
            <i class="fas fa-user-check text-sky-500"></i>
            <span class="text-xs font-bold text-sky-700">Vous êtes connecté — vos coordonnées ont été pré-remplies, vous pouvez les corriger si besoin.</span>
        </div>
        <?php endif; ?>

        <?php if (!$espacesBailPossible): ?>
        <p class="text-center text-slate-400 italic py-10">Aucun espace ne propose actuellement d'option de location en bail.</p>
        <?php elseif (!($msg && $msg[0] === 'ok')): ?>
        <form method="POST" class="bg-white rounded-[2rem] p-6 sm:p-10 border border-slate-100 shadow-sm space-y-5">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nom <span class="text-accent">*</span></label>
                    <input type="text" name="nom" required value="<?= e($moiConnecte['nom_complet'] ?? '') ?>" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Prénom</label>
                    <input type="text" name="prenom" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Téléphone <span class="text-accent">*</span></label>
                    <input type="text" name="telephone" required value="<?= e($moiConnecte['telephone'] ?? '') ?>" placeholder="+223 XX XX XX XX" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Email <span class="text-accent">*</span></label>
                    <input type="email" name="email" required value="<?= e($moiConnecte['email'] ?? '') ?>" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Espace souhaité <span class="text-accent">*</span></label>
                <select name="espace_id" id="espaceSelect" required onchange="onEspaceBailChange()" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
                    <option value="">— Choisir —</option>
                    <?php foreach ($espacesBailPossible as $esp): ?>
                    <option value="<?= $esp['id'] ?>" <?= $espacePreselectionne === (int)$esp['id'] ? 'selected' : '' ?>><?= e($esp['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Bail groupé : proposé uniquement si l'espace choisi fait partie d'un bâtiment à plusieurs salles -->
            <div id="groupeBatimentBloc" class="hidden bg-indigo-50 border-2 border-indigo-100 rounded-2xl p-4">
                <p class="text-xs font-black text-indigo-700 mb-2"><i class="fas fa-building mr-1.5"></i>Souhaitez-vous prendre aussi les autres salles du même bâtiment (<span id="nomBatiment"></span>) ?</p>
                <div id="autresEspacesListe" class="space-y-1.5"></div>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
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
                    <input type="date" name="date_debut_souhaitee" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Usage prévu de l'espace</label>
                <input type="text" name="usage_prevu" placeholder="Ex : entraînements de club, activités associatives..." class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Message (optionnel)</label>
                <textarea name="message" rows="4" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-medium text-primary outline-none focus:border-primary text-sm resize-none"></textarea>
            </div>

            <button type="submit" class="w-full bg-primary text-white py-4 rounded-xl font-black uppercase tracking-widest text-sm hover:bg-slate-800 transition">
                Envoyer ma demande
            </button>
        </form>
        <?php endif; ?>
    </div>
</section>

</div>

<script>
const espacesBailData = <?= json_encode($espacesBailPossible) ?>;

function onEspaceBailChange() {
    const select = document.getElementById('espaceSelect');
    const espaceId = parseInt(select.value, 10);
    const espace = espacesBailData.find(e => e.id === espaceId);
    const bloc = document.getElementById('groupeBatimentBloc');
    const liste = document.getElementById('autresEspacesListe');
    liste.innerHTML = '';

    if (!espace || !espace.groupe_batiment) {
        bloc.classList.add('hidden');
        return;
    }

    const autres = espacesBailData.filter(e => e.groupe_batiment === espace.groupe_batiment && e.id !== espaceId);
    if (!autres.length) {
        bloc.classList.add('hidden');
        return;
    }

    document.getElementById('nomBatiment').textContent = espace.groupe_batiment;
    autres.forEach(e => {
        const label = document.createElement('label');
        label.className = 'flex items-center gap-2.5 text-sm font-semibold text-indigo-700 cursor-pointer';
        label.innerHTML = `<input type="checkbox" name="espaces_supplementaires[]" value="${e.id}" class="w-4 h-4 accent-indigo-600"> ${e.nom}`;
        liste.appendChild(label);
    });
    bloc.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', onEspaceBailChange);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
