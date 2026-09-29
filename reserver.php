<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_client('admin/dashboard.php');

$pdo = db();
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$idPreselectionne = isset($_GET['espace_id']) ? (int)$_GET['espace_id'] : 0;
$errorMsg = isset($_GET['error']) ? urldecode($_GET['error']) : '';

$espacesData = $pdo->query("
    SELECT e.id, e.nom, e.capacite, e.description, e.mode_reservation, e.option_petit_dejeuner, e.option_vip, e.prix_vip,
    e.gerant_externe, e.gerant_nom, e.gerant_prenom, e.gerant_email, e.gerant_contact,
    (SELECT COUNT(*) FROM tarifs t WHERE t.espace_id = e.id AND t.est_bail = 1) as nb_tarifs_bail,
    (SELECT chemin FROM espace_images WHERE espace_id = e.id LIMIT 1) as photo
    FROM espaces e WHERE e.disponible = 1 ORDER BY e.nom ASC
")->fetchAll(PDO::FETCH_UNIQUE);

$tarifsData = $pdo->query("SELECT espace_id, id, libelle, montant, unite, quantite_disponible, est_bail FROM tarifs ORDER BY est_bail ASC, montant ASC")
    ->fetchAll(PDO::FETCH_GROUP);

$occupationsRaw = $pdo->query("
    SELECT espace_id, date_resa, heure_debut, heure_fin
    FROM reservations
    WHERE statut IN ('validee','en_attente') AND date_resa >= CURRENT_DATE
")->fetchAll(PDO::FETCH_ASSOC);

$creneaux = [];
foreach ($occupationsRaw as $r) {
    $creneaux[$r['espace_id']][$r['date_resa']][] = [
        'debut' => substr((string)$r['heure_debut'], 0, 5),
        'fin'   => substr((string)$r['heure_fin'], 0, 5),
    ];
}

/* ============================================================
   MODE RÉQUISITION (nouvelle date / autre espace)
   Le formulaire reste le formulaire normal de réservation ; on se
   contente de restreindre les espaces/tarifs proposés et de
   préremplir les informations de la réservation réquisitionnée.
   Tous les contrôles sont refaits côté serveur au traitement.
   ============================================================ */
$requisitionId     = isset($_GET['requisition_id']) ? max(0, (int)$_GET['requisition_id']) : 0;
$requisition       = null;
$requisitionErreur = '';
$requisitionJs     = null;

if ($requisitionId > 0) {

    $contexte = requisition_contexte_nouvelle_reservation($pdo, $requisitionId, (int)$_SESSION['user_id']);

    if (!$contexte['ok']) {
        $requisitionErreur = $contexte['erreur'];
    } else {
        $requisition = $contexte['req'];
        $espaceOrigine = (int)$requisition['espace_id'];

        // En mode réquisition, les tarifs « bail » (demande de location longue durée)
        // ne sont pas proposés : ils ne correspondent pas à une réservation.
        foreach ($tarifsData as $eid => $liste) {
            $tarifsData[$eid] = array_values(array_filter($liste, fn($t) => (int)$t['est_bail'] !== 1));
        }

        if ($requisition['choix_client'] === 'nouvelle_date') {

            if (!isset($espacesData[$espaceOrigine])) {
                $requisitionErreur = "L'espace « {$requisition['espace_nom']} » n'est plus proposé à la réservation pour le moment. Merci de contacter l'administration du Palais.";
            } else {
                // Même espace, même type de réservation (même tarif)
                $espacesData = [$espaceOrigine => $espacesData[$espaceOrigine]];
                $idPreselectionne = $espaceOrigine;

                if (!empty($requisition['tarif_id'])) {
                    $tarifOrigine = array_values(array_filter(
                        $tarifsData[$espaceOrigine] ?? [],
                        fn($t) => (int)$t['id'] === (int)$requisition['tarif_id']
                    ));
                    if ($tarifOrigine) {
                        $tarifsData[$espaceOrigine] = $tarifOrigine;
                    }
                }
            }

        } else {

            // Autre espace : tous les espaces réservables en ligne, sauf celui réquisitionné
            $espacesData = array_filter(
                $espacesData,
                fn($esp, $eid) => (int)$eid !== $espaceOrigine && empty($esp['gerant_externe']),
                ARRAY_FILTER_USE_BOTH
            );

            if (!$espacesData) {
                $requisitionErreur = "Aucun autre espace n'est actuellement réservable en ligne. Merci de contacter l'administration du Palais.";
            } elseif (!isset($espacesData[$idPreselectionne])) {
                // Présélection à partir de l'espace indiqué lors du choix (nom), si retrouvé
                $idPreselectionne = 0;
                foreach ($espacesData as $eid => $esp) {
                    if ($esp['nom'] === (string)$requisition['details_choix']) {
                        $idPreselectionne = (int)$eid;
                        break;
                    }
                }
            }
        }

        // Le créneau réquisitionné reste occupé par l'institution : on l'affiche
        // comme occupé pour que le calendrier et les contrôles JS le prennent en compte.
        if (!$requisitionErreur && !empty($requisition['heure_debut']) && $requisition['date_resa'] >= date('Y-m-d')) {
            $creneaux[$espaceOrigine][$requisition['date_resa']][] = [
                'debut' => substr($requisition['heure_debut'], 0, 5),
                'fin'   => substr($requisition['heure_fin'], 0, 5),
            ];
        }

        // Date proposée : celle choisie par le client lors du choix (nouvelle date),
        // sinon la date d'origine (autre espace), si elle n'est pas passée.
        $datePropose = '';
        $candidate = $requisition['choix_client'] === 'nouvelle_date'
            ? (string)$requisition['details_choix']
            : (string)$requisition['date_resa'];
        $dc = DateTime::createFromFormat('!Y-m-d', $candidate);
        if ($dc && $dc->format('Y-m-d') === $candidate && $candidate >= date('Y-m-d')) {
            $datePropose = $candidate;
        }

        $requisitionJs = [
            'id'             => (int)$requisition['requisition_id'],
            'choix'          => $requisition['choix_client'],
            'espace_origine' => $espaceOrigine,
            'espace_nom'     => $requisition['espace_nom'],
            'mode_origine'   => $requisition['mode_reservation'],
            'tarif_origine'  => $requisition['tarif_id'] ? (int)$requisition['tarif_id'] : null,
            'date_origine'   => $requisition['date_resa'],
            'date_proposee'  => $datePropose,
            'heure_debut'    => $requisition['heure_debut'] ? substr($requisition['heure_debut'], 0, 5) : '',
            'heure_fin'      => $requisition['heure_fin'] ? substr($requisition['heure_fin'], 0, 5) : '',
            'nuits'          => (int)$requisition['nuits'],
            'quantite'       => max(1, (int)$requisition['quantite']),
            'petit_dej'      => (int)$requisition['petit_dejeuner'],
            'vip'            => (int)$requisition['vip'],
            'motif'          => (string)($requisition['motif'] ?? ''),
        ];
    }
}

$pageTitle = "Réserver un espace — Palais des Pionniers";
require __DIR__ . '/includes/header.php';

if ($requisitionErreur):
?>
<div class="bg-slate-50 min-h-[60vh]">
  <div class="container mx-auto max-w-2xl px-4 py-12">
    <div class="bg-white rounded-2xl shadow-lg border border-amber-200 p-6 sm:p-8">
      <p class="text-sm font-black text-amber-700 mb-2 flex items-center gap-2">
        <i class="fas fa-landmark"></i> Nouvelle réservation suite à une réquisition
      </p>
      <p class="text-sm text-slate-700 leading-relaxed"><?= e($requisitionErreur) ?></p>
      <a href="mon-compte.php"
         class="mt-6 inline-flex items-center gap-2 bg-primary text-white text-xs font-black uppercase tracking-widest px-5 py-3 rounded-xl hover:bg-slate-800 transition">
        <i class="fas fa-arrow-left"></i> Retour à mon compte
      </a>
    </div>
  </div>
</div>
<?php
require __DIR__ . '/includes/footer.php';
exit;
endif;
?>

<style>
  .field-label { display:block; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; margin-bottom:.6rem; }
  .field-label .required { color:#E61E2A; margin-left:2px; }
  .input-base { width:100%; border-radius:1rem; border:2px solid #f1f5f9; background:#f8fafc; padding:.9rem 1.25rem; font-weight:700; color:#0A2558; outline:none; transition:border-color .2s,box-shadow .2s; font-size:.875rem; }
  .input-base:focus { border-color:#0A2558; box-shadow:0 0 0 4px rgba(10,37,88,.07); background:#fff; }
  .input-base.error { border-color:#E61E2A; background:#fff5f5; }
  .input-base.valid { border-color:#22c55e; }
  .input-icon { padding-left:3rem; }
  select.input-base { appearance:none; cursor:pointer; }
  textarea.input-base { resize:none; }
  .step-badge { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#E61E2A; color:#fff; font-size:9px; font-weight:900; margin-right:5px; flex-shrink:0; }
  /* Progress */
  .progress-step { flex:1; text-align:center; position:relative; }
  .progress-step::after { content:''; position:absolute; top:14px; left:60%; width:80%; height:2px; background:#e2e8f0; z-index:0; }
  .progress-step:last-child::after { display:none; }
  .progress-dot { width:28px; height:28px; border-radius:50%; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:900; border:2px solid #e2e8f0; background:#fff; color:#94a3b8; position:relative; z-index:1; transition:all .3s; }
  .progress-dot.active { background:#0A2558; border-color:#0A2558; color:#fff; }
  .progress-dot.done   { background:#22c55e; border-color:#22c55e; color:#fff; }
  .progress-label { font-size:8px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; }
  /* Toast */
  #toast { position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%) translateY(100px); background:#0A2558; color:#fff; padding:.75rem 1.5rem; border-radius:2rem; font-size:.8rem; font-weight:700; z-index:9999; transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s; opacity:0; pointer-events:none; white-space:nowrap; box-shadow:0 8px 32px rgba(10,37,88,.25); }
  #toast.show { transform:translateX(-50%) translateY(0); opacity:1; }
  #toast.success { background:#16a34a; }
  #toast.error { background:#E61E2A; }
  /* Accordéon calendrier mobile */
  #calAccordion { overflow:hidden; transition:max-height .4s cubic-bezier(.4,0,.2,1); max-height:0; }
  #calAccordion.open { max-height:500px; }
  #calToggleIcon { transition:transform .3s; }
  #calToggleIcon.open { transform:rotate(180deg); }
  /* Sticky bottom bar mobile */
  @media (max-width:1023px) {
    .mobile-sticky-bar { position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid #e2e8f0; padding:.75rem 1rem; z-index:50; box-shadow:0 -4px 20px rgba(0,0,0,.08); }
    .has-sticky-bar { padding-bottom:5rem; }
    #submitBtnDesktop { display:none; }
  }
  @media (min-width:1024px) {
    .mobile-sticky-bar { display:none; }
    #submitBtnMobile { display:none; }
  }
</style>

<div class="bg-slate-50 min-h-screen">

  <!-- Header sticky -->
  <div class="bg-white border-b border-slate-100 sticky top-0 z-40 shadow-sm">
    <div class="container mx-auto max-w-5xl px-4 py-3 flex items-center justify-between gap-4">
      <a href="espaces.php" class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-widest hover:text-primary transition flex-shrink-0">
        <i class="fas fa-arrow-left"></i>
        <span class="hidden sm:inline">Espaces</span>
      </a>
      <h1 class="text-sm font-black text-primary uppercase italic tracking-tighter text-center">
        Réserver un <span class="text-accent">espace</span>
      </h1>
      <div class="flex items-center gap-2 text-xs text-slate-500 flex-shrink-0">
        <i class="fas fa-user-circle text-primary"></i>
        <span class="hidden sm:inline font-semibold max-w-[120px] truncate"><?= e($user['nom_complet']) ?></span>
      </div>
    </div>
  </div>

  <!-- Barre de progression -->
  <div class="bg-white border-b border-slate-100 py-3">
    <div class="container mx-auto max-w-lg px-4">
      <div class="flex items-start justify-center" id="progressBar">
        <?php foreach([['1','fa-building','Espace'],['2','fa-calendar','Date'],['3','fa-clock','Horaires'],['4','fa-file-alt','Détails'],['5','fa-check','Confirmer']] as $i=>[$num,$icon,$lbl]): ?>
        <div class="progress-step">
          <div class="progress-dot <?= $i===0?'active':'' ?>" id="dot<?= $num ?>"><i class="fas <?= $icon ?> text-[9px]"></i></div>
          <div class="progress-label"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if ($errorMsg): ?>
  <div class="container mx-auto max-w-5xl px-4 pt-4">
    <div class="rounded-2xl bg-red-50 border border-red-200 p-4 flex items-start gap-3">
      <i class="fas fa-exclamation-triangle text-accent mt-0.5 flex-shrink-0"></i>
      <div>
        <p class="font-black text-accent text-sm">Créneau non disponible</p>
        <p class="text-red-700 text-sm mt-0.5"><?= e($errorMsg) ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Layout principal -->
  <div class="container mx-auto max-w-5xl px-4 py-5 md:py-8 has-sticky-bar lg:pb-8">
    <div class="flex flex-col lg:grid lg:grid-cols-3 lg:gap-8 gap-5">

      <!-- ===== FORMULAIRE (toujours en premier sur mobile) ===== -->
      <div class="order-1 lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
          <form action="traitement-reservation.php" method="POST" id="resaForm" novalidate class="p-5 sm:p-8 space-y-6">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <?php if ($requisition): ?>
            <input type="hidden" name="requisition_id" value="<?= (int)$requisition['requisition_id'] ?>">
            <input type="hidden" name="horaire_mode" id="horaireModeInput" value="nouveau">

            <div class="rounded-2xl bg-amber-50 border-2 border-amber-200 p-4">
              <p class="text-sm font-black text-amber-700 mb-1 flex items-center gap-2">
                <i class="fas fa-landmark"></i>
                <?= $requisition['choix_client'] === 'nouvelle_date' ? 'Nouvelle date' : 'Autre espace' ?> — réquisition n°<?= (int)$requisition['requisition_id'] ?>
              </p>
              <p class="text-xs text-amber-700 leading-relaxed">
                Réservation réquisitionnée : <strong><?= e($requisition['espace_nom']) ?></strong>,
                <?php if (!empty($requisition['heure_debut'])): ?>
                  le <?= date('d/m/Y', strtotime($requisition['date_resa'])) ?>
                  de <?= e(substr($requisition['heure_debut'], 0, 5)) ?> à <?= e(substr($requisition['heure_fin'], 0, 5)) ?>.
                <?php else: ?>
                  du <?= date('d/m/Y', strtotime($requisition['date_resa'])) ?>
                  au <?= date('d/m/Y', strtotime($requisition['date_depart'])) ?>
                  (<?= (int)$requisition['nuits'] ?> <?= (int)$requisition['nuits'] > 1 ? 'nuitées' : 'nuitée' ?>).
                <?php endif; ?>
              </p>
              <p class="text-xs text-amber-700 leading-relaxed mt-2">
                <?php if ($requisition['choix_client'] === 'nouvelle_date'): ?>
                  L'espace et le tarif restent ceux de votre réservation initiale : choisissez simplement la nouvelle date<?= empty($requisition['heure_debut']) ? ' d\'arrivée (la durée du séjour est conservée)' : ' et l\'horaire' ?>.
                <?php else: ?>
                  Choisissez l'espace qui vous convient : son propre tarif s'appliquera.
                <?php endif; ?>
                Votre demande suit le circuit habituel et sera examinée par l'administration.
                Si vous aviez déjà payé, le montant versé sera rattaché à cette nouvelle réservation lors de sa validation ;
                un éventuel écart de tarif sera régularisé (solde à régler ou remboursement du trop-perçu).
              </p>
            </div>
            <?php endif; ?>

            <!-- 1. Espace -->
            <div>
              <label class="field-label"><span class="step-badge">1</span>Espace souhaité <span class="required">*</span></label>
              <div class="relative" id="espaceDropdownWrap">
                <select name="espace_id" id="espaceSelect" required class="hidden">
                  <option value="">— Sélectionner un espace —</option>
                  <?php foreach ($espacesData as $eid => $esp): ?>
                    <option value="<?= $eid ?>" <?= ($idPreselectionne == $eid) ? 'selected' : '' ?>>
                      <?= e($esp['nom']) ?> · <?= e($esp['capacite']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <button type="button" id="espaceDropdownBtn" onclick="toggleEspaceDropdown()"
                        class="input-base input-icon text-left flex items-center justify-between bg-white">
                  <i class="fas fa-building absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm z-10"></i>
                  <span id="espaceDropdownLabel" class="truncate pr-6 <?= $idPreselectionne ? 'text-primary' : 'text-slate-400' ?> font-semibold">
                    <?= $idPreselectionne && isset($espacesData[$idPreselectionne]) ? e($espacesData[$idPreselectionne]['nom']) . ' · ' . e($espacesData[$idPreselectionne]['capacite']) : '— Sélectionner un espace —' ?>
                  </span>
                  <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none transition-transform" id="espaceDropdownChevron"></i>
                </button>

                <div id="espaceDropdownList"
                     class="hidden absolute z-30 left-0 right-0 mt-2 max-h-72 overflow-y-auto bg-white border-2 border-slate-100 rounded-2xl shadow-2xl py-2">
                  <button type="button" onclick="selectEspaceOption('', '— Sélectionner un espace —')"
                          class="w-full text-left px-4 py-2.5 text-sm font-semibold text-slate-400 hover:bg-slate-50 transition">
                    — Sélectionner un espace —
                  </button>
                  <?php foreach ($espacesData as $eid => $esp): ?>
                  <button type="button" onclick="selectEspaceOption('<?= $eid ?>', '<?= e(addslashes($esp['nom'])) ?> · <?= e(addslashes($esp['capacite'])) ?>')"
                          data-espace-option="<?= $eid ?>"
                          class="w-full text-left px-4 py-2.5 text-sm font-bold text-primary hover:bg-primary/5 transition flex items-center justify-between gap-2">
                    <span class="truncate"><?= e($esp['nom']) ?> <span class="text-slate-400 font-medium">· <?= e($esp['capacite']) ?></span></span>
                    <i class="fas fa-check text-accent text-xs opacity-0" data-espace-check></i>
                  </button>
                  <?php endforeach; ?>
                </div>
              </div>
              <p id="espaceErr" class="hidden text-xs text-accent font-bold mt-1.5 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> Veuillez sélectionner un espace.</p>
            </div>

            <!-- Preview espace (mobile inline, desktop dans sidebar) -->
            <div id="espacePreviewMobile" class="hidden lg:hidden rounded-2xl overflow-hidden border border-slate-100 bg-slate-50">
              <div class="flex items-center gap-3 p-3">
                <img id="previewImgMobile" src="" alt="" class="w-16 h-16 object-cover rounded-xl flex-shrink-0">
                <div>
                  <h3 id="previewNomMobile" class="font-black text-primary uppercase italic text-sm tracking-tight"></h3>
                  <p id="previewCapMobile" class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                    <i class="fas fa-users text-accent text-[10px]"></i><span></span>
                  </p>
                </div>
              </div>
            </div>

            <!-- Tarifs -->
            <!-- Message si l'espace choisi est déjà en bail (géré par un tiers) -->
            <div id="espaceEnBailMessage" class="hidden bg-amber-50 border-2 border-amber-200 rounded-2xl p-5">
              <div class="flex items-start gap-3">
                <i class="fas fa-user-tie text-amber-500 mt-0.5 text-lg"></i>
                <div>
                  <p class="text-sm font-black text-amber-700 mb-1">Cet espace est géré par un tiers</p>
                  <p class="text-xs text-amber-600 mb-3">Il est loué sur une longue durée à une association, un club ou une entreprise, qui en gère l'usage au quotidien — il n'est donc pas réservable ponctuellement en ligne.</p>
                  <p id="espaceEnBailTexte" class="text-xs text-amber-700 leading-relaxed"></p>
                  <p class="text-xs font-black uppercase tracking-widest text-amber-700 mt-3 mb-1.5"><i class="fas fa-address-card mr-1.5"></i>Contacter le gestionnaire</p>
                  <div id="espaceEnBailFiche" class="space-y-1.5"></div>
                </div>
              </div>
            </div>

              <div id="tarifSection" class="hidden">
              <label class="field-label"><span class="step-badge">↳</span>Options tarifaires <span class="required">*</span></label>
              <div id="tarifGrid" class="grid gap-2.5"></div>
              <p id="tarifErr" class="hidden text-xs text-accent font-bold mt-1.5 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> Veuillez sélectionner un tarif.</p>
            </div>

            <!-- 2. Date -->
            <div id="dateSection">
              <label class="field-label"><span class="step-badge">2</span><span id="dateLabel">Date de réservation</span> <span class="required">*</span></label>
              <div class="relative">
                <i class="fas fa-calendar-alt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="date" name="date_resa" id="dateInput" required min="<?= date('Y-m-d') ?>"
                       onchange="onDateChange()" class="input-base input-icon">
              </div>
              <p id="dateErr" class="hidden text-xs text-accent font-bold mt-1.5 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> Veuillez choisir une date.</p>
            </div>

            <!-- 2bis. Date de départ + quantité + petit-déjeuner (mode séjour uniquement) -->
            <div id="sejourSection" class="hidden space-y-4">
              <div>
                <label class="field-label"><span class="step-badge">↳</span>Date de départ <span class="required">*</span></label>
                <div class="relative">
                  <i class="fas fa-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input type="date" name="date_depart" id="dateDepartInput" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                         onchange="onDateDepartChange()" class="input-base input-icon">
                </div>
                <p id="dateDepartErr" class="hidden text-xs text-accent font-bold mt-1.5 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i> La date de départ doit être après la date d'arrivée.</p>
                <p id="nuiteesLabel" class="hidden text-xs text-primary font-black mt-1.5"></p>
              </div>
              <div>
                <label class="field-label">Nombre de chambres <span class="required">*</span></label>
                <div class="flex items-center gap-3">
                  <button type="button" onclick="changeQuantite(-1)" class="w-11 h-11 flex items-center justify-center rounded-xl border-2 border-slate-100 bg-white text-primary font-black hover:border-primary transition">−</button>
                  <input type="number" name="quantite" id="quantiteInput" value="1" min="1" max="1" readonly
                         onchange="checkAllValid()" class="w-16 text-center rounded-xl border-2 border-slate-100 bg-slate-50 py-2.5 font-black text-primary outline-none">
                  <button type="button" onclick="changeQuantite(1)" class="w-11 h-11 flex items-center justify-center rounded-xl border-2 border-slate-100 bg-white text-primary font-black hover:border-primary transition">+</button>
                  <span id="quantiteMaxLabel" class="text-xs text-slate-400 font-semibold"></span>
                </div>
              </div>
              <label id="petitDejLabel" class="hidden flex items-center gap-3 p-3.5 rounded-xl border-2 border-slate-100 bg-white cursor-pointer hover:border-primary transition-all w-fit">
                <input type="checkbox" name="petit_dejeuner" id="petitDejCheck" value="1" onchange="checkAllValid()" class="w-4 h-4 accent-primary">
                <span class="text-xs font-black text-slate-700">Petit-déjeuner inclus <span class="text-slate-400 font-normal">(+5 000 FCFA/nuit/chambre)</span></span>
              </label>
            </div>

            <!-- Disponibilité inline mobile (après date) -->
            <div id="creneauxMobile" class="hidden lg:hidden rounded-2xl bg-slate-50 border border-slate-100 p-4">
              <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-calendar-day text-accent text-xs"></i>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Occupation du <span id="creneauxDateLabelMobile" class="text-primary"></span></p>
              </div>
              <div class="relative h-5 bg-slate-200 rounded-xl overflow-hidden mb-2" id="friseHoraireMobile"></div>
              <div class="flex justify-between text-[8px] text-slate-400 mb-3 px-0.5">
                <span>7h</span><span>10h</span><span>13h</span><span>16h</span><span>19h</span><span>22h</span>
              </div>
              <div id="creneauxListMobile" class="space-y-1.5 text-xs"></div>
              <div id="creneauxLibresMobile" class="mt-3 hidden">
                <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Créneaux libres — appuyez pour sélectionner</p>
                <div id="creneauxLibresListMobile" class="flex flex-wrap gap-1.5"></div>
              </div>
            </div>

            <!-- 3. Horaires -->
            <div id="horairesSection">
              <label class="field-label"><span class="step-badge">3</span>Horaires <span class="required">*</span></label>
              <?php if ($requisition && $requisition['choix_client'] === 'nouvelle_date' && !empty($requisition['heure_debut'])): ?>
              <div id="horaireModeChoix" class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                <label class="flex items-center gap-2 p-3 rounded-xl border-2 border-slate-100 bg-white cursor-pointer text-xs font-black text-slate-700 hover:border-primary transition">
                  <input type="radio" name="horaire_choix" value="meme" checked onchange="onHoraireModeChange()" class="w-4 h-4 accent-primary">
                  Conserver le même horaire
                  <span class="text-slate-400 font-semibold">(<?= e(substr($requisition['heure_debut'], 0, 5)) ?> → <?= e(substr($requisition['heure_fin'], 0, 5)) ?>)</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-xl border-2 border-slate-100 bg-white cursor-pointer text-xs font-black text-slate-700 hover:border-primary transition">
                  <input type="radio" name="horaire_choix" value="nouveau" onchange="onHoraireModeChange()" class="w-4 h-4 accent-primary">
                  Choisir un nouvel horaire
                </label>
              </div>
              <?php endif; ?>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <p class="text-[9px] font-black text-slate-400 uppercase mb-1.5 flex items-center gap-1"><i class="fas fa-play text-green-500 text-[8px]"></i>Début</p>
                  <div class="relative">
                    <select name="heure_debut" id="heureDebut" required onchange="onHeureChange()" class="input-base text-sm" style="padding-left:1rem">
                      <?php for($h=7; $h<=21; $h++): ?>
                        <option value="<?= sprintf('%02d',$h) ?>:00"><?= sprintf('%02d',$h) ?>:00</option>
                        <option value="<?= sprintf('%02d',$h) ?>:30"><?= sprintf('%02d',$h) ?>:30</option>
                      <?php endfor; ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                  </div>
                </div>
                <div>
                  <p class="text-[9px] font-black text-slate-400 uppercase mb-1.5 flex items-center gap-1"><i class="fas fa-stop text-accent text-[8px]"></i>Fin</p>
                  <div class="relative">
                    <select name="heure_fin" id="heureFin" required onchange="onHeureChange()" class="input-base text-sm" style="padding-left:1rem">
                      <?php for($h=8; $h<=22; $h++): ?>
                        <option value="<?= sprintf('%02d',$h) ?>:00"><?= sprintf('%02d',$h) ?>:00</option>
                        <option value="<?= sprintf('%02d',$h) ?>:30"><?= sprintf('%02d',$h) ?>:30</option>
                      <?php endfor; ?>
                    </select>
                    <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                  </div>
                </div>
              </div>
              <p id="heureErr" class="hidden text-xs text-accent font-bold mt-1.5 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i></p>
            </div>

            <!-- Alertes dispo -->
            <div id="conflictAlert" class="hidden rounded-2xl bg-red-50 border border-red-200 p-4 flex items-start gap-3">
              <i class="fas fa-times-circle text-accent text-base mt-0.5 flex-shrink-0"></i>
              <p class="text-sm font-bold text-red-700" id="conflictMsg"></p>
            </div>
            <div id="disponibleAlert" class="hidden rounded-2xl bg-green-50 border border-green-200 p-4 flex items-center gap-3">
              <i class="fas fa-check-circle text-green-500 text-base flex-shrink-0"></i>
              <div>
                <p class="text-sm font-black text-green-700">Créneau disponible !</p>
                <p class="text-xs text-green-600 mt-0.5" id="dureeLabel"></p>
              </div>
            </div>

            <!-- Option VIP (supplément, espaces qui le proposent) -->
            <label id="vipLabel" class="hidden flex items-center gap-3 p-3.5 rounded-xl border-2 border-slate-100 bg-white cursor-pointer hover:border-primary transition-all w-fit">
              <input type="checkbox" name="vip" id="vipCheck" value="1" onchange="checkAllValid()" class="w-4 h-4 accent-accent">
              <span class="text-xs font-black text-slate-700">Accueil VIP <span id="vipPrixLabel" class="text-slate-400 font-normal"></span></span>
            </label>

            <!-- 4. Motif -->
            <div id="motifSection">
              <label class="field-label"><span class="step-badge">4</span>Motif de la réservation <span class="required">*</span></label>
              <div class="relative">
                <i class="fas fa-align-left absolute left-4 top-4 text-slate-400 text-sm"></i>
                <textarea name="motif" id="motifInput" rows="3" required minlength="10"
                          placeholder="Ex : Réunion, conférence, tournoi, fête..."
                          oninput="onMotifChange()" class="input-base input-icon"></textarea>
              </div>
              <div class="flex justify-between mt-1.5">
                <p id="motifErr" class="hidden text-xs text-accent font-bold flex items-center gap-1"><i class="fas fa-exclamation-circle"></i>Min. 10 caractères.</p>
                <p class="text-xs text-slate-400 ml-auto" id="motifCount">0 / 10 min.</p>
              </div>
            </div>

            <!-- 5. Téléphone -->
            <div id="telSection">
              <label class="field-label"><span class="step-badge">5</span>Téléphone de contact <span class="required">*</span></label>
              <div class="relative">
                <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="tel" name="telephone" id="telInput" required
                       value="<?= e($user['telephone'] ?? '') ?>"
                       placeholder="+223 XX XX XX XX"
                       oninput="onTelChange()" class="input-base input-icon">
              </div>
              <p id="telErr" class="hidden text-xs text-accent font-bold mt-1.5 flex items-center gap-1"><i class="fas fa-exclamation-circle"></i>Numéro requis (min. 8 chiffres).</p>
            </div>

            <!-- Récap -->
            <div id="recapBox" class="hidden rounded-2xl bg-primary/5 border border-primary/10 p-4">
              <p class="text-[10px] font-black uppercase tracking-widest text-primary mb-3 flex items-center gap-2">
                <i class="fas fa-receipt text-accent"></i>Récapitulatif
              </p>
              <div class="space-y-2 text-sm" id="recapContent"></div>
            </div>

            <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3.5 flex items-start gap-2.5">
              <i class="fas fa-landmark text-amber-500 mt-0.5 flex-shrink-0"></i>
              <p class="text-[11px] text-amber-700 leading-relaxed">Comme tout espace du Palais, celui-ci peut exceptionnellement être réquisitionné pour un besoin institutionnel prioritaire (activité ministérielle, gouvernementale ou urgence nationale), même après validation. Vous seriez alors notifié et pourriez choisir un remboursement, une nouvelle date ou un autre espace.</p>
            </div>

            <!-- Bouton desktop uniquement -->
            <div id="submitBtnDesktop">
              <button type="submit" id="submitBtn" disabled
                      class="w-full bg-primary text-white py-5 rounded-2xl font-black uppercase tracking-widest text-sm transition-all flex items-center justify-center gap-3 opacity-40 cursor-not-allowed">
                <i class="fas fa-paper-plane"></i>
                <span>Envoyer ma demande</span>
              </button>
              <p class="text-center text-xs text-slate-400 mt-3">
                <i class="fas fa-shield-alt mr-1 text-green-500"></i>Demande examinée sous 24h.
              </p>
            </div>
          </form>
        </div>

        <!-- Accordéon calendrier MOBILE uniquement -->
        <div class="lg:hidden mt-4">
          <button type="button" onclick="toggleCal()"
                  class="w-full bg-slate-900 text-white rounded-2xl px-5 py-4 flex items-center justify-between font-black text-sm uppercase tracking-wide">
            <span class="flex items-center gap-2">
              <i class="fas fa-calendar-alt text-accent"></i>
              Voir le calendrier de disponibilité
            </span>
            <i class="fas fa-chevron-down text-slate-400" id="calToggleIcon"></i>
          </button>
          <div id="calAccordion">
            <div class="bg-slate-900 rounded-b-2xl p-5 text-white">
              <!-- Légende -->
              <div class="flex items-center gap-3 mb-4 flex-wrap">
                <span class="flex items-center gap-1 text-[9px] text-slate-400"><span class="w-2.5 h-2.5 rounded bg-green-500/40 inline-block"></span>Libre</span>
                <span class="flex items-center gap-1 text-[9px] text-slate-400"><span class="w-2.5 h-2.5 rounded bg-yellow-400/50 inline-block"></span>Partiel</span>
                <span class="flex items-center gap-1 text-[9px] text-slate-400"><span class="w-2.5 h-2.5 rounded bg-red-500/50 inline-block"></span>Complet</span>
              </div>
              <div class="flex items-center justify-between mb-4">
                <button type="button" onclick="changeMonth(-1)" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/15 transition">
                  <i class="fas fa-chevron-left text-sm"></i>
                </button>
                <div id="monthLabelMobile" class="text-xs font-black uppercase italic text-accent tracking-widest"></div>
                <button type="button" onclick="changeMonth(1)" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/15 transition">
                  <i class="fas fa-chevron-right text-sm"></i>
                </button>
              </div>
              <div class="grid grid-cols-7 gap-0.5 mb-1.5">
                <?php foreach(['L','M','M','J','V','S','D'] as $j): ?>
                  <div class="text-center text-[8px] font-black text-slate-600 uppercase py-1"><?= $j ?></div>
                <?php endforeach; ?>
              </div>
              <div id="calGridMobile" class="grid grid-cols-7 gap-0.5"></div>
              <p id="calHintMobile" class="text-center text-[9px] text-slate-500 mt-3 italic">Sélectionnez un espace pour voir la disponibilité</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== SIDEBAR DESKTOP ===== -->
      <div class="order-2 lg:col-span-1 hidden lg:block space-y-5">

        <!-- Preview espace desktop -->
        <div id="espacePreview" class="hidden bg-white rounded-[2rem] overflow-hidden shadow-sm border border-slate-100">
          <img id="previewImg" src="" alt="" class="w-full h-40 object-cover">
          <div class="p-4">
            <h3 id="previewNom" class="font-black text-primary uppercase italic tracking-tighter text-base"></h3>
            <p id="previewCap" class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
              <i class="fas fa-users text-accent text-[10px]"></i><span></span>
            </p>
          </div>
        </div>

        <!-- Dispo desktop -->
        <div id="creneauxDuJour" class="hidden bg-white rounded-[2rem] p-5 border border-slate-100 shadow-sm">
          <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-calendar-day text-accent text-sm"></i>
            <div>
              <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Occupation</p>
              <p class="text-xs font-black text-primary" id="creneauxDateLabel"></p>
            </div>
          </div>
          <div class="mb-3">
            <div class="relative h-6 bg-slate-100 rounded-xl overflow-hidden" id="friseHoraire"></div>
            <div class="flex justify-between text-[8px] text-slate-400 mt-1 px-0.5">
              <span>7h</span><span>10h</span><span>13h</span><span>16h</span><span>19h</span><span>22h</span>
            </div>
          </div>
          <div id="creneauxList" class="space-y-1.5 text-xs"></div>
          <div id="creneauxLibres" class="mt-4 hidden">
            <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-2">Disponibles</p>
            <div id="creneauxLibresList" class="flex flex-wrap gap-1.5"></div>
          </div>
        </div>

        <!-- Calendrier desktop -->
        <div class="bg-slate-900 rounded-[2rem] p-6 text-white shadow-xl">
          <div class="flex items-center justify-between mb-5">
            <button type="button" onclick="changeMonth(-1)" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/15 transition"><i class="fas fa-chevron-left text-sm"></i></button>
            <div id="monthLabel" class="text-xs font-black uppercase italic text-accent tracking-widest"></div>
            <button type="button" onclick="changeMonth(1)" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/15 transition"><i class="fas fa-chevron-right text-sm"></i></button>
          </div>
          <div class="flex items-center gap-3 mb-4 flex-wrap">
            <span class="flex items-center gap-1 text-[9px] text-slate-400"><span class="w-2.5 h-2.5 rounded bg-green-500/40 inline-block"></span>Libre</span>
            <span class="flex items-center gap-1 text-[9px] text-slate-400"><span class="w-2.5 h-2.5 rounded bg-yellow-400/50 inline-block"></span>Partiel</span>
            <span class="flex items-center gap-1 text-[9px] text-slate-400"><span class="w-2.5 h-2.5 rounded bg-red-500/50 inline-block"></span>Complet</span>
          </div>
          <div class="grid grid-cols-7 gap-0.5 mb-1.5">
            <?php foreach(['L','M','M','J','V','S','D'] as $j): ?>
              <div class="text-center text-[8px] font-black text-slate-600 uppercase py-1"><?= $j ?></div>
            <?php endforeach; ?>
          </div>
          <div id="calGrid" class="grid grid-cols-7 gap-0.5"></div>
          <p id="calHint" class="text-center text-[9px] text-slate-500 mt-4 italic">Sélectionnez un espace pour voir la disponibilité</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bouton sticky mobile -->
<div id="mobileStickyBar" class="mobile-sticky-bar lg:hidden">
  <button type="button" id="submitBtnMobile" disabled onclick="submitMobile()"
          class="w-full bg-primary text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm flex items-center justify-center gap-2 opacity-40 cursor-not-allowed transition-all">
    <i class="fas fa-paper-plane"></i>
    <span id="submitMobileLabel">Remplissez tous les champs</span>
  </button>
</div>

<div id="toast"></div>

<script>
const espaces  = <?= json_encode($espacesData) ?>;
const tarifs   = <?= json_encode($tarifsData) ?>;
const creneaux = <?= json_encode($creneaux) ?>;
// Contexte de réquisition (null pour une réservation normale)
const REQ = <?= json_encode($requisitionJs) ?>;
let currentViewDate = new Date();

// ---- Helpers ----
function toMin(hhmm) { const [h,m]=hhmm.split(':').map(Number); return h*60+m; }
function fromMin(m)  { return String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0'); }
function formatDuree(m) { const h=Math.floor(m/60),r=m%60; return h>0?(h+'h'+(r?r+'min':'')):(r+'min'); }
function isMobile()  { return window.innerWidth < 1024; }

function showToast(msg, type='info') {
    const t = document.getElementById('toast');
    t.textContent = msg; t.className = 'show '+type;
    setTimeout(()=>t.className='', 3000);
}
function setValid(el, ok) {
    if(!el) return;
    el.classList.toggle('valid', ok);
    el.classList.toggle('error', !ok);
}
function el(id) { return document.getElementById(id); }

const JOUR_DEBUT=7*60, JOUR_FIN=22*60, JOUR_DUREE=JOUR_FIN-JOUR_DEBUT;
function getEspaceId(){ return el('espaceSelect').value; }

function toggleEspaceDropdown() {
    if (reqNouvelleDate()) return; // nouvelle date : l'espace réquisitionné est conservé
    const list = el('espaceDropdownList');
    const chevron = el('espaceDropdownChevron');
    const isOpen = !list.classList.contains('hidden');
    if (isOpen) {
        list.classList.add('hidden');
        chevron.style.transform = '';
        return;
    }
    list.classList.remove('hidden');
    chevron.style.transform = 'rotate(180deg)';

    // Positionnement intelligent : si pas assez de place en dessous, ouvrir vers le haut
    const wrap = el('espaceDropdownWrap');
    const rect = wrap.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const listHeight = Math.min(list.scrollHeight, 288);
    list.classList.remove('top-full', 'bottom-full', 'mt-2', 'mb-2');
    if (spaceBelow < listHeight + 20 && rect.top > listHeight + 20) {
        list.classList.add('bottom-full', 'mb-2');
    } else {
        list.classList.add('top-full', 'mt-2');
    }
}

function selectEspaceOption(value, label) {
    el('espaceSelect').value = value;
    el('espaceDropdownLabel').textContent = label;
    el('espaceDropdownLabel').classList.toggle('text-slate-400', value === '');
    el('espaceDropdownLabel').classList.toggle('text-primary', value !== '');
    document.querySelectorAll('[data-espace-check]').forEach(c => c.classList.add('opacity-0'));
    const btn = document.querySelector(`[data-espace-option="${value}"] [data-espace-check]`);
    if (btn) btn.classList.remove('opacity-0');
    el('espaceDropdownList').classList.add('hidden');
    el('espaceDropdownChevron').style.transform = '';
    onEspaceChange();
}

document.addEventListener('click', function(e) {
    const wrap = document.getElementById('espaceDropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('espaceDropdownList')?.classList.add('hidden');
        const chevron = document.getElementById('espaceDropdownChevron');
        if (chevron) chevron.style.transform = '';
    }
});
function getDate()    { return el('dateInput').value; }
function getDebut()   { return el('heureDebut').value; }
function getFin()     { return el('heureFin').value; }
function getDateDepart() { return el('dateDepartInput').value; }
function isSejour(id) { return id && espaces[id] && espaces[id].mode_reservation === 'sejour'; }

function changeQuantite(delta) {
    if (reqNouvelleDate() && isSejour(getEspaceId())) return; // nombre de chambres conservé
    const input = el('quantiteInput');
    const max = parseInt(input.max || '1', 10);
    let val = parseInt(input.value || '1', 10) + delta;
    val = Math.max(1, Math.min(val, max));
    input.value = val;
    checkAllValid();
}

function updateQuantiteMax() {
    const id = getEspaceId();
    if (!isSejour(id)) return;
    const tarifEl = document.querySelector('input[name="tarif_id"]:checked');
    if (!tarifEl || !tarifs[id]) return;
    const tarif = tarifs[id].find(t => String(t.id) === tarifEl.value);
    const max = (tarif && tarif.quantite_disponible) ? tarif.quantite_disponible : 1;
    const input = el('quantiteInput');
    input.max = max;
    if (reqNouvelleDate()) { input.max = Math.max(max, REQ.quantite); input.value = REQ.quantite; }
    if (parseInt(input.value,10) > parseInt(input.max,10)) input.value = input.max;
    el('quantiteMaxLabel').textContent = max > 1 ? `(max ${max} disponibles)` : '';
}

// ---- Toggle accordéon calendrier mobile ----
function toggleCal() {
    const acc  = el('calAccordion');
    const icon = el('calToggleIcon');
    acc.classList.toggle('open');
    icon.classList.toggle('open');
    renderCal();
}

// ---- Validation globale + bouton ----
function checkAllValid() {
    const id    = getEspaceId();
    const date  = getDate();
    const motif = el('motifInput').value.trim();
    const tel   = el('telInput').value.trim();
    const sejour = isSejour(id);
    if (sejour) updateQuantiteMax();

    const hasTarif = el('tarifSection').classList.contains('hidden')
        ? true
        : document.querySelector('input[name="tarif_id"]:checked') !== null;

    let debut = '', fin = '', finOk = false, noConf = true;

    if (sejour) {
        const depart = getDateDepart();
        finOk  = !!(depart && depart > date);
        noConf = true; // la disponibilité réelle (inventaire) est vérifiée côté serveur
    } else {
        debut = getDebut();
        fin   = getFin();
        finOk = fin > debut;
        const taken = (creneaux[id] && creneaux[id][date]) ? creneaux[id][date] : [];
        noConf = finOk && !taken.some(c => debut<c.fin && fin>c.debut);
    }

    const allOk  = !!(id && date && finOk && noConf && motif.length>=10 && tel.length>=8 && hasTarif);

    // Bouton desktop
    const btnD = el('submitBtn');
    if (btnD) {
        btnD.disabled = !allOk;
        btnD.classList.toggle('opacity-40', !allOk);
        btnD.classList.toggle('cursor-not-allowed', !allOk);
        btnD.classList.toggle('hover:bg-slate-800', allOk);
    }
    // Bouton mobile sticky
    const btnM = el('submitBtnMobile');
    if (btnM) {
        btnM.disabled = !allOk;
        btnM.classList.toggle('opacity-40', !allOk);
        btnM.classList.toggle('cursor-not-allowed', !allOk);
        btnM.classList.toggle('bg-primary', true);
        el('submitMobileLabel').textContent = allOk ? 'Envoyer ma demande' : 'Remplissez tous les champs';
    }

    updateProgress(id, date, finOk, motif, tel);
    el('recapBox').classList.toggle('hidden', !allOk);
    if (allOk) updateRecap(id, date, debut, fin, motif, tel, sejour);
    return allOk;
}

function updateProgress(id, date, finOk, motif, tel) {
    const steps = [!!id, !!date, finOk, motif.length>=10, tel.length>=8];
    steps.forEach((ok,i)=>{
        const d = el('dot'+(i+1));
        if(!d) return;
        d.classList.toggle('done', ok);
        d.classList.toggle('active', !ok && (i===0||steps[i-1]));
        if(ok&&!d.classList.contains('done')) d.classList.remove('active');
    });
}

function updateRecap(id, date, debut, fin, motif, tel, sejour) {
    const esp = espaces[id];
    const tarifEl = document.querySelector('input[name="tarif_id"]:checked');
    const tarifLabel = tarifEl ? tarifEl.closest('label').querySelector('.tarif-label')?.textContent : '';
    const d = new Date(date+'T12:00:00');
    const dateStr = new Intl.DateTimeFormat('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(d);

    let ligneDatesHTML;
    if (sejour) {
        const depart = getDateDepart();
        const dDepart = new Date(depart+'T12:00:00');
        const depStr = new Intl.DateTimeFormat('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(dDepart);
        const nuits = Math.round((dDepart - d) / 86400000);
        const qte = parseInt(el('quantiteInput').value, 10) || 1;
        const tarifObj = tarifEl && tarifs[id] ? tarifs[id].find(t => String(t.id) === tarifEl.value) : null;
        const prixNuit = tarifObj ? tarifObj.montant : 0;
        const petitDej = el('petitDejCheck').checked;
        const total = (prixNuit * nuits * qte) + (petitDej ? 5000 * nuits * qte : 0);
        ligneDatesHTML = `
            <span class="text-slate-500">Arrivée</span><span class="font-bold text-primary text-right capitalize text-xs">${dateStr}</span>
            <span class="text-slate-500">Départ</span><span class="font-bold text-primary text-right capitalize text-xs">${depStr}</span>
            <span class="text-slate-500">Durée</span><span class="font-black text-primary text-right">${nuits} ${nuits>1?'nuitées':'nuitée'}</span>
            <span class="text-slate-500">Chambres</span><span class="font-black text-primary text-right">${qte}</span>
            ${petitDej?'<span class="text-slate-500">Petit-déjeuner</span><span class="font-bold text-primary text-right text-xs">Inclus</span>':''}
            <span class="text-slate-500">Total estimé</span><span class="font-black text-accent text-right">${new Intl.NumberFormat('fr-FR').format(total)} FCFA</span>`;
    } else {
        const dur = toMin(fin)-toMin(debut);
        const vipCoche = el('vipCheck') && el('vipCheck').checked;
        const vipLigne = vipCoche ? `<span class="text-slate-500">Accueil VIP</span><span class="font-bold text-accent text-right text-xs">+${new Intl.NumberFormat('fr-FR').format(esp.prix_vip || 0)} FCFA</span>` : '';
        ligneDatesHTML = `
            <span class="text-slate-500">Date</span><span class="font-bold text-primary text-right capitalize text-xs">${dateStr}</span>
            <span class="text-slate-500">Horaires</span><span class="font-black text-primary text-right">${debut}→${fin} <span class="text-accent text-xs">(${formatDuree(dur)})</span></span>
            ${vipLigne}`;
    }

    el('recapContent').innerHTML = `
        <div class="grid grid-cols-2 gap-y-2 text-sm">
            <span class="text-slate-500">Espace</span><span class="font-black text-primary text-right">${esp.nom}</span>
            ${ligneDatesHTML}
            ${tarifLabel?`<span class="text-slate-500">Tarif</span><span class="font-bold text-primary text-right text-xs">${tarifLabel}</span>`:''}
            <span class="text-slate-500">Contact</span><span class="font-bold text-primary text-right">${tel}</span>
        </div>`;
}

// ---- Espace ----
function onEspaceChange() {
    const id = getEspaceId();
    el('espaceErr').classList.add('hidden');
    setValid(el('espaceSelect'), !!id);

    const calHint  = el('calHint');
    const calHintM = el('calHintMobile');

    if (!id) {
        el('tarifSection').classList.add('hidden');
        el('espacePreview').classList.add('hidden');
        el('espacePreviewMobile').classList.add('hidden');
        if(calHint) calHint.classList.remove('hidden');
        if(calHintM) calHintM.classList.remove('hidden');
        checkAllValid(); return;
    }
    if(calHint) calHint.classList.add('hidden');
    if(calHintM) calHintM.classList.add('hidden');

    const esp = espaces[id];

    // Espace déjà géré par un tiers (en bail) — pas de réservation en ligne possible
    const sectionsAReservation = ['dateSection','horairesSection','sejourSection','motifSection','telSection','recapBox','submitBtnDesktop'];
    if (esp.gerant_externe) {
        el('espaceEnBailMessage').classList.remove('hidden');
        sectionsAReservation.forEach(s => el(s)?.classList.add('hidden'));
        el('mobileStickyBar')?.classList.add('hidden');
        el('petitDejLabel')?.classList.add('hidden');
        el('vipLabel')?.classList.add('hidden');

        el('espaceEnBailTexte').textContent = esp.gerant_externe;
        const ficheDiv = el('espaceEnBailFiche');
        let ficheHtml = '';
        const nomGerant = [esp.gerant_prenom, esp.gerant_nom].filter(Boolean).join(' ');
        if (nomGerant) ficheHtml += `<p class="text-xs text-amber-700"><i class="fas fa-user w-4"></i> ${nomGerant}</p>`;
        if (esp.gerant_email) ficheHtml += `<p class="text-xs text-amber-700"><i class="fas fa-envelope w-4"></i> <a href="mailto:${esp.gerant_email}" class="underline font-bold">${esp.gerant_email}</a></p>`;
        if (esp.gerant_contact) ficheHtml += `<p class="text-xs text-amber-700"><i class="fas fa-phone-alt w-4"></i> <a href="tel:${esp.gerant_contact.replace(/\s+/g,'')}" class="underline font-bold">${esp.gerant_contact}</a></p>`;
        if (!nomGerant && !esp.gerant_email && !esp.gerant_contact) {
            ficheHtml = `<p class="text-xs text-amber-700 italic"><i class="fas fa-exclamation-circle w-4"></i> Coordonnées du gestionnaire non encore renseignées — <a href="contact.php" class="underline font-bold">contactez l'administration du Palais</a>.</p>`;
        }
        ficheDiv.innerHTML = ficheHtml;

        el('tarifSection').classList.add('hidden');
        return;
    } else {
        el('espaceEnBailMessage').classList.add('hidden');
        el('mobileStickyBar')?.classList.remove('hidden');
        sectionsAReservation.forEach(s => { if (s !== 'recapBox') el(s)?.classList.remove('hidden'); });
    }

    const sejour = esp.mode_reservation === 'sejour';

    // Bascule créneau <-> séjour
    el('horairesSection').classList.toggle('hidden', sejour);
    el('sejourSection').classList.toggle('hidden', !sejour);
    el('petitDejLabel').classList.toggle('hidden', !(sejour && esp.option_petit_dejeuner == 1));
    if (!(sejour && esp.option_petit_dejeuner == 1)) el('petitDejCheck').checked = false;
    const avecVip = esp.option_vip == 1;
    el('vipLabel').classList.toggle('hidden', !avecVip);
    if (!avecVip) { el('vipCheck').checked = false; }
    else { el('vipPrixLabel').textContent = '(+' + new Intl.NumberFormat('fr-FR').format(esp.prix_vip || 0) + ' FCFA)'; }
    el('quantiteInput').value = 1;
    el('creneauxMobile').classList.toggle('hidden', sejour);
    el('dateLabel').textContent = sejour ? "Date d'arrivée" : 'Date de réservation';
    el('heureDebut').required = !sejour;
    el('heureFin').required   = !sejour;
    el('dateDepartInput').required = sejour;
    if (!sejour) { el('dateDepartInput').value = ''; el('petitDejCheck').checked = false; }

    // Preview desktop
    el('previewNom').textContent = esp.nom;
    el('previewCap').querySelector('span').textContent = esp.capacite+' personnes';
    el('previewImg').src = esp.photo ? 'uploads/'+esp.photo : 'https://placehold.co/600x300/0A2558/ffffff?text='+encodeURIComponent(esp.nom);
    el('espacePreview').classList.remove('hidden');

    // Preview mobile inline
    el('previewNomMobile').textContent = esp.nom;
    el('previewCapMobile').querySelector('span').textContent = esp.capacite+' personnes';
    el('previewImgMobile').src = esp.photo ? 'uploads/'+esp.photo : 'https://placehold.co/200x200/0A2558/ffffff?text='+encodeURIComponent(esp.nom);
    el('espacePreviewMobile').classList.remove('hidden');

    // Tarifs
    const grid = el('tarifGrid');
    grid.innerHTML = '';
    if (tarifs[id] && tarifs[id].length) {
        const normaux = tarifs[id].filter(t => t.est_bail != 1);
        const bail = tarifs[id].filter(t => t.est_bail == 1);

        normaux.forEach((t,i) => {
            const lbl = document.createElement('label');
            lbl.className = "flex items-center justify-between p-3.5 rounded-xl border-2 border-slate-100 bg-white cursor-pointer hover:border-primary transition-all";
            lbl.innerHTML = `
                <div class="flex items-center gap-3">
                    <input type="radio" name="tarif_id" value="${t.id}" ${i===0?'checked':''} onchange="checkAllValid()" class="w-4 h-4 accent-primary flex-shrink-0">
                    <span class="text-xs font-black text-slate-700 tarif-label">${t.libelle}</span>
                </div>
                <span class="text-sm font-black text-primary whitespace-nowrap ml-2">${new Intl.NumberFormat('fr-FR').format(t.montant)} <span class="text-xs text-slate-400 font-normal">FCFA/${t.unite}</span></span>`;
            grid.appendChild(lbl);
        });

        bail.forEach(t => {
            const carte = document.createElement('div');
            carte.id = 'bailCarte-' + t.id;
            carte.className = "p-3.5 rounded-xl border-2 border-indigo-100 bg-indigo-50 cursor-pointer transition-all";
            carte.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="bail-radio w-4 h-4 rounded-full border-2 border-indigo-300 flex-shrink-0 flex items-center justify-center">
                            <span class="bail-radio-dot hidden w-2 h-2 rounded-full bg-indigo-600"></span>
                        </span>
                        <span class="text-xs font-black text-indigo-700">${t.libelle}</span>
                    </div>
                    <span class="text-sm font-black text-indigo-700 whitespace-nowrap ml-2">${new Intl.NumberFormat('fr-FR').format(t.montant)} <span class="text-xs text-indigo-400 font-normal">FCFA/${t.unite}</span></span>
                </div>
                <div id="bailDetail-${t.id}" class="hidden mt-3 pt-3 border-t border-indigo-100">
                    <p class="text-xs text-indigo-700 mb-3 leading-relaxed">Cette option n'est pas une réservation classique : c'est une location longue durée. En cliquant ci-dessous, vous ouvrez un formulaire pour demander à louer cet espace sur plusieurs mois — le tarif exact sera confirmé avec vous par l'administration.</p>
                    <a href="demande-bail.php?espace_id=${id}" class="flex items-center justify-center gap-2 p-3 rounded-xl bg-indigo-600 text-white text-xs font-black uppercase tracking-widest hover:bg-indigo-700 transition-all">
                        <i class="fas fa-arrow-right"></i> Demander un bail sur cet espace
                    </a>
                </div>`;
            carte.onclick = (e) => {
                if (e.target.closest('a')) return;
                const detail = document.getElementById('bailDetail-' + t.id);
                const dot = carte.querySelector('.bail-radio-dot');
                const estOuvert = !detail.classList.contains('hidden');
                detail.classList.toggle('hidden');
                dot.classList.toggle('hidden', estOuvert);
                carte.classList.toggle('border-indigo-500', !estOuvert);

                // Sécurité : tant que la carte bail est ouverte, aucun tarif classique
                // ne doit rester sélectionné — sinon le formulaire pourrait être envoyé
                // par erreur avec le premier tarif normal au lieu de passer par la
                // vraie demande de bail.
                if (!estOuvert) {
                    document.querySelectorAll('input[name="tarif_id"]:checked').forEach(r => r.checked = false);
                }
                checkAllValid();
            };
            grid.appendChild(carte);
        });

        el('tarifSection').classList.remove('hidden');
    } else {
        el('tarifSection').classList.add('hidden');
    }

    renderCal();
    updateDisponibilite();
    checkAllValid();
}

// ---- Date ----
function onDateChange() {
    const date = getDate();
    el('dateErr').classList.add('hidden');
    setValid(el('dateInput'), !!date);
    currentViewDate = date ? new Date(date+'T12:00:00') : new Date();
    if (reqNouvelleDate() && isSejour(getEspaceId())) majDepartRequisition();
    if (isSejour(getEspaceId())) { onDateDepartChange(); }
    renderCal();
    updateDisponibilite();
    checkAllValid();
}

// ---- Heure ----
function onHeureChange() { updateDisponibilite(); checkAllValid(); }

// ---- Date de départ (séjour) ----
function onDateDepartChange() {
    const arrivee = getDate();
    const depart  = getDateDepart();
    el('dateDepartErr').classList.add('hidden');
    const ok = arrivee && depart && depart > arrivee;
    setValid(el('dateDepartInput'), ok);
    if (ok) {
        const nuits = Math.round((new Date(depart) - new Date(arrivee)) / 86400000);
        el('nuiteesLabel').textContent = nuits + (nuits > 1 ? ' nuitées' : ' nuitée');
        el('nuiteesLabel').classList.remove('hidden');
    } else {
        el('nuiteesLabel').classList.add('hidden');
    }
    checkAllValid();
}

// ---- Motif ----
function onMotifChange() {
    const val = el('motifInput').value.trim();
    el('motifCount').textContent = val.length+' / 10 min.';
    el('motifCount').classList.toggle('text-green-600', val.length>=10);
    el('motifCount').classList.toggle('text-slate-400', val.length<10);
    setValid(el('motifInput'), val.length>=10);
    el('motifErr').classList.toggle('hidden', val.length>=10);
    checkAllValid();
}

// ---- Téléphone ----
function onTelChange() {
    const val = el('telInput').value.trim();
    setValid(el('telInput'), val.length>=8);
    el('telErr').classList.toggle('hidden', val.length>=8);
    checkAllValid();
}

// ---- Disponibilité (desktop + mobile) ----
function buildFrise(containerId, taken, debut, fin) {
    const friseEl = el(containerId);
    if (!friseEl) return;
    friseEl.innerHTML = '';
    taken.forEach(c => {
        const pst = ((toMin(c.debut)-JOUR_DEBUT)/JOUR_DUREE)*100;
        const pw  = ((toMin(c.fin)-toMin(c.debut))/JOUR_DUREE)*100;
        const bloc = document.createElement('div');
        bloc.className = 'absolute top-0 h-full bg-accent/60 flex items-center justify-center';
        bloc.style.cssText = `left:${pst}%;width:${pw}%;`;
        if(pw>8) bloc.innerHTML=`<span class="text-white text-[7px] font-black px-1 truncate">${c.debut}–${c.fin}</span>`;
        friseEl.appendChild(bloc);
    });
    if (debut && fin && fin>debut) {
        const pst = ((toMin(debut)-JOUR_DEBUT)/JOUR_DUREE)*100;
        const pw  = ((toMin(fin)-toMin(debut))/JOUR_DUREE)*100;
        const sel = document.createElement('div');
        sel.className = 'absolute top-0 h-full bg-primary/40 border-2 border-primary rounded';
        sel.style.cssText = `left:${pst}%;width:${pw}%;`;
        friseEl.appendChild(sel);
    }
}

function buildCreneauxLibres(listId, sectionId, taken, selD, selF) {
    const sorted = [...taken].sort((a,b)=>toMin(a.debut)-toMin(b.debut));
    const free=[]; let cur=JOUR_DEBUT;
    sorted.forEach(c=>{
        if(cur<toMin(c.debut)) free.push({debut:fromMin(cur),fin:fromMin(toMin(c.debut))});
        cur=Math.max(cur,toMin(c.fin));
    });
    if(cur<JOUR_FIN) free.push({debut:fromMin(cur),fin:fromMin(JOUR_FIN)});
    const listEl=el(listId), sectEl=el(sectionId);
    if(!listEl||!sectEl) return;
    listEl.innerHTML='';
    const valid=free.filter(s=>(toMin(s.fin)-toMin(s.debut))>=30);
    if(valid.length) {
        valid.forEach(s=>{
            const dur=toMin(s.fin)-toMin(s.debut);
            const btn=document.createElement('button');
            btn.type='button';
            btn.className="text-[10px] font-black bg-green-50 border border-green-200 text-green-700 rounded-xl px-2.5 py-1.5 hover:bg-green-500 hover:text-white active:scale-95 transition";
            btn.innerHTML=`${s.debut}–${s.fin} <span class="opacity-60 text-[9px]">(${formatDuree(dur)})</span>`;
            btn.onclick=()=>{ selD.value=s.debut; selF.value=s.fin; showToast('Créneau sélectionné : '+s.debut+' – '+s.fin,'success'); updateDisponibilite(); checkAllValid(); };
            listEl.appendChild(btn);
        });
        sectEl.classList.remove('hidden');
    } else { sectEl.classList.add('hidden'); }
}

function buildCreneauxList(listId, taken) {
    const listEl=el(listId); if(!listEl) return;
    listEl.innerHTML='';
    if(taken.length===0) {
        listEl.innerHTML='<p class="text-xs font-bold text-green-600 flex items-center gap-1.5"><i class="fas fa-check-circle"></i>Journée entièrement libre !</p>';
    } else {
        taken.forEach(c=>{
            const e2=document.createElement('div');
            e2.className="flex items-center justify-between bg-red-50 border border-red-100 rounded-xl px-3 py-2";
            e2.innerHTML=`<span class="flex items-center gap-1.5 text-xs font-bold text-red-700"><i class="fas fa-ban text-accent text-[9px]"></i>Occupé</span><span class="text-xs font-black text-slate-700">${c.debut}→${c.fin}</span>`;
            listEl.appendChild(e2);
        });
    }
}

function updateDisponibilite() {
    if (reqMemeHoraire()) appliquerMemeHoraire();
    const id=getEspaceId(), date=getDate(), debut=getDebut(), fin=getFin();

    if (isSejour(id)) {
        el('conflictAlert').classList.add('hidden');
        el('disponibleAlert').classList.add('hidden');
        el('heureErr').classList.add('hidden');
        if (el('creneauxDuJour')) el('creneauxDuJour').classList.add('hidden');
        el('creneauxMobile').classList.add('hidden');
        return;
    }

    const taken=(creneaux[id]&&creneaux[id][date])?creneaux[id][date]:[];

    el('conflictAlert').classList.add('hidden');
    el('disponibleAlert').classList.add('hidden');
    el('heureErr').classList.add('hidden');

    // Griser heures occupées
    const selD=el('heureDebut'), selF=el('heureFin');
    Array.from(selD.options).forEach(o=>{
        const bl=taken.some(c=>o.value>=c.debut&&o.value<c.fin);
        o.disabled=bl; o.style.color=bl?'#ccc':''; o.style.background=bl?'#fee2e2':'';
    });
    Array.from(selF.options).forEach(o=>{
        const bl=taken.some(c=>o.value>c.debut&&o.value<=c.fin);
        o.disabled=bl; o.style.color=bl?'#ccc':''; o.style.background=bl?'#fee2e2':'';
    });
    if (reqMemeHoraire()) {
        // Horaire d'origine imposé : même s'il est occupé, on le garde pour afficher le conflit
        selD.value = REQ.heure_debut; selF.value = REQ.heure_fin;
    } else {
    if(selD.options[selD.selectedIndex]?.disabled){ const nf=Array.from(selD.options).find(o=>!o.disabled); if(nf)selD.value=nf.value; }
    if(selF.options[selF.selectedIndex]?.disabled){ const nf=Array.from(selF.options).find(o=>!o.disabled); if(nf)selF.value=nf.value; }
    }

    if (!id||!date) {
        el('creneauxDuJour').classList.add('hidden');
        el('creneauxMobile').classList.add('hidden');
        return;
    }

    // Label date
    const d=new Date(date+'T12:00:00');
    const dateStr=new Intl.DateTimeFormat('fr-FR',{weekday:'short',day:'numeric',month:'long'}).format(d);
    if(el('creneauxDateLabel'))    el('creneauxDateLabel').textContent=dateStr;
    if(el('creneauxDateLabelMobile')) el('creneauxDateLabelMobile').textContent=dateStr;

    // Frises
    buildFrise('friseHoraire', taken, debut, fin);
    buildFrise('friseHoraireMobile', taken, debut, fin);

    // Listes
    buildCreneauxList('creneauxList', taken);
    buildCreneauxList('creneauxListMobile', taken);

    // Créneaux libres
    if(taken.length>0) {
        buildCreneauxLibres('creneauxLibresList','creneauxLibres', taken, selD, selF);
        buildCreneauxLibres('creneauxLibresListMobile','creneauxLibresMobile', taken, selD, selF);
    } else {
        el('creneauxLibres').classList.add('hidden');
        el('creneauxLibresMobile').classList.add('hidden');
    }

    el('creneauxDuJour').classList.remove('hidden');
    el('creneauxMobile').classList.remove('hidden');

    // Alertes conflit
    if(debut && fin) {
        if(fin<=debut) {
            el('heureErr').innerHTML='<i class="fas fa-exclamation-circle"></i> L\'heure de fin doit être après l\'heure de début.';
            el('heureErr').classList.remove('hidden');
        } else {
            const conf=taken.find(c=>debut<c.fin&&fin>c.debut);
            if(conf) {
                el('conflictMsg').textContent=`Ce créneau chevauche une réservation (${conf.debut}–${conf.fin}). Choisissez un créneau libre ci-dessus.`;
                el('conflictAlert').classList.remove('hidden');
            } else {
                const dur=toMin(fin)-toMin(debut);
                el('dureeLabel').textContent='Durée : '+formatDuree(dur);
                el('disponibleAlert').classList.remove('hidden');
            }
        }
    }
}

// ---- Calendrier (dessine dans 2 grilles: desktop + mobile) ----
function changeMonth(dir) {
    currentViewDate=new Date(currentViewDate.getFullYear(),currentViewDate.getMonth()+dir,1);
    renderCal();
}
function selectDate(y,m,d) {
    const ds=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    el('dateInput').value=ds;
    currentViewDate=new Date(y,m,d);
    renderCal(); onDateChange();
    // Le calendrier reste ouvert : la date choisie est visible en surbrillance
    // dans la grille, pas besoin de refermer ni de sauter vers le formulaire.
}

function renderCal() {
    const id=getEspaceId(), selVal=getDate();
    const y=currentViewDate.getFullYear(), m=currentViewDate.getMonth();
    const today=new Date(); today.setHours(0,0,0,0);
    const monthStr=new Intl.DateTimeFormat('fr-FR',{month:'long',year:'numeric'}).format(new Date(y,m,1)).toUpperCase();

    ['monthLabel','monthLabelMobile'].forEach(id2=>{ if(el(id2)) el(id2).textContent=monthStr; });

    let first=new Date(y,m,1).getDay(); first=first===0?6:first-1;
    const days=new Date(y,m+1,0).getDate();

    ['calGrid','calGridMobile'].forEach(gridId=>{
        const grid=el(gridId); if(!grid) return;
        grid.innerHTML='';
        for(let i=0;i<first;i++) grid.appendChild(document.createElement('div'));
        for(let d=1;d<=days;d++) {
            const dStr=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const dDate=new Date(y,m,d);
            const isPast=dDate<today, isSel=selVal===dStr, isTod=dDate.toDateString()===today.toDateString();
            let state='free';
            if(id&&creneaux[id]&&creneaux[id][dStr]) {
                let mins=0; creneaux[id][dStr].forEach(c=>{mins+=toMin(c.fin)-toMin(c.debut);});
                state=mins>=JOUR_DUREE*0.85?'full':'partial';
            }
            const cel=document.createElement('div');
            cel.className='aspect-square flex items-center justify-center rounded-lg text-[10px] font-black transition-all ';
            if(isSel) cel.className+='bg-white text-primary scale-110 shadow-md';
            else if(isPast) cel.className+='text-slate-600 opacity-20 cursor-default';
            else if(state==='full') { cel.className+='bg-red-500/30 text-red-400 cursor-not-allowed'; cel.title='Journée complète'; }
            else if(state==='partial') { cel.className+='bg-yellow-400/30 text-yellow-300 hover:bg-white/15 hover:text-white cursor-pointer'; cel.title='Partiellement disponible'; }
            else { cel.className+='bg-green-500/20 text-green-400 hover:bg-white/15 hover:text-white cursor-pointer'; cel.title='Disponible'; }
            if(isTod&&!isSel) cel.className+=' ring-1 ring-accent/50';
            cel.textContent=d;
            if(!isPast&&state!=='full') cel.onclick=()=>selectDate(y,m,d);
            grid.appendChild(cel);
        }
    });
}

// ---- Submit ----
function submitMobile() {
    if(!checkAllValid()) { showToast('Veuillez compléter tous les champs.','error'); return; }
    el('resaForm').dispatchEvent(new Event('submit'));
}
let envoiEnCours = false;
el('resaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (envoiEnCours) return; // protection double clic
    if(!checkAllValid()) { showToast('Veuillez compléter tous les champs.','error'); return; }
    const debut=getDebut(), fin=getFin(), date=getDate(), esp=espaces[getEspaceId()];
    const d=new Date(date+'T12:00:00');
    const dateStr=new Intl.DateTimeFormat('fr-FR',{weekday:'long',day:'numeric',month:'long'}).format(d);
    if(confirm(`Confirmer la réservation ?\n\nEspace : ${esp.nom}\nDate : ${dateStr}\nHoraires : ${debut} → ${fin}\n\nVotre demande sera examinée sous 24h.`)) {
        envoiEnCours = true;
        ['submitBtn','submitBtnMobile'].forEach(b => { if (el(b)) { el(b).disabled = true; el(b).classList.add('opacity-40','cursor-not-allowed'); } });
        showToast('Envoi en cours...','info');
        this.submit();
    }
});

// ---- Mode réquisition (nouvelle date / autre espace) ----
function reqNouvelleDate() { return !!(REQ && REQ.choix === 'nouvelle_date'); }
function reqMemeHoraire() {
    if (!reqNouvelleDate() || !REQ.heure_debut || isSejour(getEspaceId())) return false;
    const r = document.querySelector('input[name="horaire_choix"]:checked');
    return !!(r && r.value === 'meme');
}
function appliquerMemeHoraire() {
    el('heureDebut').value = REQ.heure_debut;
    el('heureFin').value   = REQ.heure_fin;
}
function onHoraireModeChange() {
    const meme = reqMemeHoraire();
    if (el('horaireModeInput')) el('horaireModeInput').value = meme ? 'meme' : 'nouveau';
    ['heureDebut','heureFin'].forEach(s => {
        el(s).classList.toggle('pointer-events-none', meme);
        el(s).classList.toggle('opacity-60', meme);
        el(s).tabIndex = meme ? -1 : 0;
    });
    if (meme) appliquerMemeHoraire();
    updateDisponibilite();
    checkAllValid();
}
function ajouterJours(dateStr, n) {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + n);
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
function majDepartRequisition() {
    const date = getDate();
    el('dateDepartInput').value = (date && REQ.nuits > 0) ? ajouterJours(date, REQ.nuits) : '';
}
function initRequisition() {
    if (!REQ) return;
    const id = getEspaceId();
    if (!id || !espaces[id]) { checkAllValid(); return; }
    const sejour = isSejour(id);
    const memeMode = espaces[id].mode_reservation === REQ.mode_origine;

    if (reqNouvelleDate()) {
        const btn = el('espaceDropdownBtn');
        if (btn) { btn.classList.add('cursor-not-allowed'); btn.title = "L'espace réquisitionné est conservé pour une nouvelle date"; }
        const radio = document.querySelector(`input[name="tarif_id"][value="${REQ.tarif_origine}"]`);
        if (radio) radio.checked = true;
    }

    if (REQ.date_proposee) {
        el('dateInput').value = REQ.date_proposee;
        currentViewDate = new Date(REQ.date_proposee + 'T12:00:00');
    }

    if (sejour) {
        if (reqNouvelleDate()) {
            el('dateDepartInput').readOnly = true;
            el('dateDepartInput').classList.add('bg-slate-100');
            majDepartRequisition();
            el('quantiteInput').value = REQ.quantite;
        } else if (memeMode && REQ.date_proposee) {
            el('dateDepartInput').value = ajouterJours(REQ.date_proposee, REQ.nuits);
        }
        if (memeMode && REQ.petit_dej && !el('petitDejLabel').classList.contains('hidden')) el('petitDejCheck').checked = true;
    } else if (memeMode && REQ.heure_debut) {
        appliquerMemeHoraire();
    }

    if (REQ.vip && !el('vipLabel').classList.contains('hidden')) el('vipCheck').checked = true;
    if (!el('motifInput').value.trim() && REQ.motif) { el('motifInput').value = REQ.motif; }

    onMotifChange();
    onHoraireModeChange();
    onDateChange();
}

// ---- Init ----
onEspaceChange();
renderCal();
if(el('telInput').value.trim().length>=8) onTelChange();
initRequisition();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>