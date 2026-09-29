<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin_espaces', 'admin_comptable', 'superadmin']);
expirer_reservations_non_payees();

$pdo = db();
$msg = null;

/*
 * Vérification de disponibilité (lecture seule, JSON) : réutilise exactement
 * les fonctions utilisées à l'enregistrement d'une réservation
 * (tarif_disponible() et creneaux_libres_du_jour()).
 */
if (($_GET['verifier_dispo'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $dateOk = fn($d) => ($o = DateTime::createFromFormat('!Y-m-d', (string)$d)) && $o->format('Y-m-d') === $d;
    $heureOk = fn($h) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$h);

    $tarifId  = (int)($_GET['tarif_id'] ?? 0);
    $date     = (string)($_GET['date'] ?? '');
    $depart   = (string)($_GET['date_depart'] ?? '');
    $hd       = (string)($_GET['heure_debut'] ?? '');
    $hf       = (string)($_GET['heure_fin'] ?? '');
    $quantite = max(1, (int)($_GET['quantite'] ?? 1));

    $t = $pdo->prepare("SELECT t.id, e.mode_reservation FROM tarifs t JOIN espaces e ON e.id = t.espace_id WHERE t.id = ?");
    $t->execute([$tarifId]);
    $tarif = $t->fetch();

    if (!$tarif || !$dateOk($date)) {
        echo json_encode(['ok' => false, 'message' => 'Choisissez un espace, un tarif et une date.']);
        exit;
    }

    if ($tarif['mode_reservation'] === 'sejour') {
        if (!$dateOk($depart) || $depart <= $date) {
            echo json_encode(['ok' => false, 'message' => 'Indiquez une date de départ postérieure à l\'arrivée.']);
            exit;
        }
        $dispo = tarif_disponible($pdo, $tarifId, $date, $depart, null, null, null, $quantite);
        echo json_encode(['ok' => true, 'disponible' => $dispo,
            'message' => $dispo ? 'Disponible pour ces dates.' : 'Complet pour ces dates.']);
        exit;
    }

    $libres = creneaux_libres_du_jour($pdo, $tarifId, $date);
    $dispo = null;
    if ($heureOk($hd) && $heureOk($hf) && $hf > $hd) {
        $dispo = tarif_disponible($pdo, $tarifId, $date, null, $hd . ':00', $hf . ':00', null, 1);
    }
    echo json_encode(['ok' => true, 'disponible' => $dispo, 'libres' => $libres,
        'message' => $dispo === null ? 'Horaire incomplet : l\'heure de fin doit suivre l\'heure de début.' : ($dispo ? 'Créneau disponible.' : 'Ce créneau est déjà occupé.')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $msg = ['err', 'Requête invalide.'];
    } else {
        $action    = $_POST['action'] ?? '';

        if ($action === 'create_guichet') {
            $espaceId    = (int)($_POST['espace_id']  ?? 0);
            $tarifId     = !empty($_POST['tarif_id']) ? (int)$_POST['tarif_id'] : null;
            $dateResa    = trim($_POST['date_resa']    ?? '');
            $dateDepart  = trim($_POST['date_depart']  ?? '');
            $heureD      = trim($_POST['heure_debut']  ?? '');
            $heureF      = trim($_POST['heure_fin']    ?? '');
            $petitDej    = !empty($_POST['petit_dejeuner']) ? 1 : 0;
            $vip         = !empty($_POST['vip']) ? 1 : 0;
            $motif       = trim($_POST['motif']        ?? '');
            $telephone   = trim($_POST['telephone']    ?? '');
            $userId      = (int)($_POST['user_id']     ?? 0);

            if (!$userId) {
                $prenom  = trim($_POST['prenom']  ?? '');
                $nom_fam = trim($_POST['nom_fam'] ?? '');
                $email   = trim($_POST['email']   ?? '');

                if (!$prenom || !$nom_fam || !$email) {
                    $msg = ['err', 'Remplissez prénom, nom et email du client.'];
                    goto end;
                }

                $exist = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $exist->execute([$email]);
                $exist = $exist->fetch();

                if ($exist) {
                    $userId = (int)$exist['id'];
                } else {
                    $nomComplet = $prenom . ' ' . mb_strtoupper($nom_fam);
                    $tmpPass    = password_hash(uniqid(), PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (nom_complet, email, telephone, password_hash, role) VALUES (?,?,?,?,'user')")
                        ->execute([$nomComplet, $email, $telephone, $tmpPass]);
                    $userId = (int)$pdo->lastInsertId();
                }
            }

            if (!$espaceId || !$dateResa || !$tarifId) {
                $msg = ['err', 'Espace, date et tarif sont obligatoires.'];
                goto end;
            }

            $espaceInfo = $pdo->prepare("SELECT mode_reservation, option_petit_dejeuner, option_vip, gerant_externe FROM espaces WHERE id = ?");
            $espaceInfo->execute([$espaceId]);
            $espaceRow = $espaceInfo->fetch();

            if ($espaceRow && !empty($espaceRow['gerant_externe'])) {
                $msg = ['err', 'Cet espace est géré par un tiers — la réservation ne peut pas être enregistrée ici.'];
                goto end;
            }
            $estSejour = ($espaceRow && $espaceRow['mode_reservation'] === 'sejour');
            if (!$estSejour || !$espaceRow['option_petit_dejeuner']) $petitDej = 0;
            if (!$espaceRow['option_vip']) $vip = 0;
            $quantite = $estSejour ? max(1, (int)($_POST['quantite'] ?? 1)) : 1;

            if ($estSejour) {
                if (!$dateDepart || $dateDepart <= $dateResa) {
                    $msg = ['err', 'La date de départ doit être après la date d\'arrivée.'];
                    goto end;
                }
            } else {
                if (!$heureD || !$heureF) {
                    $msg = ['err', 'Espace, date et horaires sont obligatoires.'];
                    goto end;
                }
            }

            $heureDFmt = $estSejour ? null : $heureD . ':00';
            $heureFFmt = $estSejour ? null : $heureF . ':00';

            $disponible = tarif_disponible(
                $pdo, $tarifId, $dateResa,
                $estSejour ? $dateDepart : null,
                $estSejour ? null : $heureDFmt,
                $estSejour ? null : $heureFFmt,
                null, $quantite
            );

            if (!$disponible) {
                $msg = ['err', $estSejour ? 'Complet pour ces dates.' : 'Ce créneau est déjà occupé.'];
                goto end;
            }

            $pdo->prepare("
                INSERT INTO reservations
                    (user_id, espace_id, tarif_id, date_resa, date_depart, heure_debut, heure_fin, petit_dejeuner, vip, canal, quantite,
                     statut, date_validation, statut_paiement, motif, created_at, notification_vue)
                VALUES (?,?,?,?,?,?,?,?,?,'guichet',?,'validee',NOW(),'attente_paiement',?,NOW(),0)
            ")->execute([$userId, $espaceId, $tarifId, $dateResa, $estSejour ? $dateDepart : null, $heureDFmt, $heureFFmt, $petitDej, $vip, $quantite, $motif]);


            $resaId = (int)$pdo->lastInsertId();
            // Tarif normal (avant toute réduction) figé dès la saisie guichet
            figer_montant_initial($pdo, $resaId);
            $estComptable = (($_SESSION['role'] ?? '') === 'admin_comptable');
            if (!$estComptable) {
                notify('admin_comptable', 'guichet_resa', "Réservation guichet #$resaId — paiement à encaisser", "paiements.php?resa=$resaId");
            }
            notify('superadmin', 'guichet_resa', "Réservation guichet #$resaId créée — paiement à encaisser", "reservations.php");
            log_activity('resa_guichet', 'reservations', "Réservation guichet #$resaId créée pour user #$userId");
            $msg = ['ok', $estComptable
                ? "Réservation #$resaId créée. Vous pouvez encaisser dès maintenant (acompte ou paiement complet)."
                : "Réservation #$resaId créée et transmise au comptable."];
        }
        end:;
    }
}

$espaces    = $pdo->query("SELECT id, nom, capacite, mode_reservation, option_petit_dejeuner, option_vip, prix_vip FROM espaces WHERE disponible = 1 ORDER BY nom")->fetchAll();
$tarifsData = $pdo->query("SELECT espace_id, id, libelle, montant, unite, quantite_disponible FROM tarifs WHERE est_bail = 0 ORDER BY montant ASC")
                  ->fetchAll(PDO::FETCH_GROUP);
$recentGuichet = $pdo->query("
    SELECT r.id, r.date_resa, r.date_depart, r.heure_debut, r.heure_fin, r.statut_paiement,
           e.nom AS espace_nom, u.nom_complet
    FROM reservations r
    JOIN espaces e ON e.id = r.espace_id
    JOIN users   u ON u.id = r.user_id
    ORDER BY r.created_at DESC LIMIT 12
")->fetchAll();

$pageTitle = "Guichet — Réservation présentielle";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight">Guichet</h1>
    <p class="text-sm text-slate-500 mt-0.5">Enregistrement direct pour un client présent physiquement</p>
  </div>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-2xl p-4 flex items-center gap-3 <?= $msg[0]==='ok' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-accent' ?>">
  <i class="fas <?= $msg[0]==='ok' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-accent' ?>"></i>
  <span class="font-bold text-sm"><?= e($msg[1]) ?></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Formulaire -->
  <div class="lg:col-span-2 min-w-0">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
        <h2 class="font-black text-primary text-sm uppercase italic flex items-center gap-2">
          <i class="fas fa-user-plus text-accent"></i> Client & réservation
        </h2>
      </div>
      <form method="POST" class="p-6 space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action"     value="create_guichet">
        <input type="hidden" name="user_id"    id="selectedUserId" value="0">

        <!-- Recherche client -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Rechercher un client existant</label>
          <div class="relative">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" id="clientSearch" placeholder="Nom, email ou téléphone..."
                   oninput="searchClient(this.value)"
                   class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
          <div id="clientResults" class="hidden bg-white rounded-xl border border-slate-200 shadow-lg max-h-48 overflow-y-auto mt-1"></div>
          <div id="clientSelected" class="hidden mt-3 bg-primary/5 rounded-xl border border-primary/10 p-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-primary rounded-xl flex items-center justify-center font-black text-white text-sm" id="clientInitiale">?</div>
              <div>
                <p class="font-black text-primary text-sm" id="clientNom">—</p>
                <p class="text-xs text-slate-500"         id="clientEmail">—</p>
              </div>
            </div>
            <button type="button" onclick="clearClient()" class="text-xs text-slate-400 hover:text-accent"><i class="fas fa-times"></i></button>
          </div>
        </div>

        <!-- Nouveau client -->
        <div id="newClientFields">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Ou créer un nouveau client</label>
          <div class="grid sm:grid-cols-3 gap-4">
            <?php foreach ([['prenom','Prénom','text','Amadou'],['nom_fam','Nom','text','COULIBALY'],['email','Email','email','email@exemple.ml']] as [$n,$l,$t,$ph]): ?>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5"><?= $l ?></label>
              <input type="<?= $t ?>" name="<?= $n ?>" placeholder="<?= $ph ?>"
                     class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-3 py-2.5 font-bold text-primary outline-none focus:border-primary text-sm">
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Téléphone -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Téléphone</label>
          <div class="relative">
            <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="tel" name="telephone" placeholder="+223 XX XX XX XX"
                   class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
        </div>

        <!-- Espace -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Espace <span class="text-accent">*</span></label>
          <div class="relative">
            <i class="fas fa-building absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <select name="espace_id" required onchange="onEspaceChangeGuichet(this.value)"
                    class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 pl-11 pr-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm appearance-none">
              <option value="">— Sélectionner —</option>
              <?php foreach ($espaces as $e): ?>
                <option value="<?= $e['id'] ?>"><?= e($e['nom']) ?> (<?= e($e['capacite']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
          </div>
        </div>

        <!-- Tarifs -->
        <div id="tarifSection" class="hidden">
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Tarif</label>
          <div id="tarifGrid" class="grid gap-2"></div>
        </div>

        <!-- Date + Horaires (mode créneau) -->
        <div id="creneauFields" class="grid sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Date <span class="text-accent">*</span></label>
            <input type="date" name="date_resa" id="dateResaGuichet" required min="<?= date('Y-m-d') ?>"
                   class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
          <?php foreach ([['heure_debut','Début',7,21],['heure_fin','Fin',8,22]] as [$name,$label,$from,$to]): ?>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2"><?= $label ?> <span class="text-accent">*</span></label>
            <div class="relative">
              <select name="<?= $name ?>" id="<?= $name ?>Guichet" class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-3 py-3 font-bold text-primary outline-none text-sm appearance-none">
                <?php for($h=$from;$h<=$to;$h++): ?>
                  <option value="<?= sprintf('%02d',$h) ?>:00"><?= sprintf('%02d',$h) ?>:00</option>
                  <option value="<?= sprintf('%02d',$h) ?>:30"><?= sprintf('%02d',$h) ?>:30</option>
                <?php endfor; ?>
              </select>
              <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <label id="vipLabelGuichet" class="hidden flex items-center gap-3 p-3 rounded-xl border-2 border-slate-100 cursor-pointer hover:border-primary transition w-fit">
          <input type="checkbox" name="vip" value="1" class="w-4 h-4 accent-accent">
          <span class="text-xs font-black text-slate-700">Accueil VIP <span id="vipPrixGuichet" class="text-slate-400 font-normal"></span></span>
        </label>

        <!-- Séjour (arrivée/départ + petit-déjeuner) -->
        <div id="sejourFields" class="hidden grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Arrivée <span class="text-accent">*</span></label>
            <input type="date" name="date_resa" id="dateResaSejourGuichet" min="<?= date('Y-m-d') ?>" disabled
                   class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Départ <span class="text-accent">*</span></label>
            <input type="date" name="date_depart" id="dateDepartGuichet" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                   class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-bold text-primary outline-none focus:border-primary text-sm">
          </div>
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Nombre de chambres <span class="text-accent">*</span></label>
            <div class="flex items-center gap-2">
              <button type="button" onclick="changeQuantiteGuichet(-1)" class="w-10 h-10 flex items-center justify-center rounded-xl border-2 border-slate-100 bg-white text-primary font-black hover:border-primary transition">−</button>
              <input type="number" name="quantite" id="quantiteGuichet" value="1" min="1" max="1" readonly
                     class="w-14 text-center rounded-xl border-2 border-slate-100 bg-slate-50 py-2 font-black text-primary outline-none">
              <button type="button" onclick="changeQuantiteGuichet(1)" class="w-10 h-10 flex items-center justify-center rounded-xl border-2 border-slate-100 bg-white text-primary font-black hover:border-primary transition">+</button>
              <span id="quantiteMaxGuichet" class="text-[10px] text-slate-400 font-semibold"></span>
            </div>
          </div>
          <label id="petitDejLabelGuichet" class="hidden sm:col-span-2 flex items-center gap-3 p-3 rounded-xl border-2 border-slate-100 cursor-pointer hover:border-primary transition w-fit">
            <input type="checkbox" name="petit_dejeuner" value="1" class="w-4 h-4 accent-primary">
            <span class="text-xs font-black text-slate-700">Petit-déjeuner inclus <span class="text-slate-400 font-normal">(+5 000 FCFA/nuit/chambre)</span></span>
          </label>
        </div>

        <!-- Vérification de disponibilité (même règle que l'enregistrement) -->
        <div>
          <button type="button" onclick="verifierDisponibilite()"
                  class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
            <i class="fas fa-calendar-check"></i> Vérifier la disponibilité
          </button>
          <div id="resultatDispo" class="hidden mt-3 rounded-xl border p-3 text-xs font-bold"></div>
        </div>

        <!-- Motif -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Motif</label>
          <textarea name="motif" rows="2" placeholder="Objet de la réservation..."
                    class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-4 py-3 font-semibold text-primary outline-none focus:border-primary text-sm resize-none"></textarea>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
          <i class="fas fa-info-circle text-amber-500 mt-0.5 flex-shrink-0"></i>
          <?php if (($_SESSION['role'] ?? '') === 'admin_comptable'): ?>
          <p class="text-xs text-amber-700">La réservation sera <strong>directement validée</strong>. Vous pouvez procéder à l'encaissement dès maintenant depuis <a href="paiements.php" class="underline font-bold">Paiements</a>.</p>
          <?php else: ?>
          <p class="text-xs text-amber-700">La réservation sera <strong>directement validée</strong> et transmise au comptable pour encaissement.</p>
          <?php endif; ?>
        </div>

        <button type="submit"
                class="w-full bg-primary hover:bg-slate-800 text-white py-4 rounded-2xl font-black uppercase tracking-widest text-sm transition shadow-lg flex items-center justify-center gap-2">
          <i class="fas fa-check-circle"></i> Créer la réservation
        </button>
      </form>
    </div>
  </div>

  <!-- Réservations récentes -->
  <div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden sticky top-24">
      <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
        <h2 class="font-black text-[10px] uppercase tracking-widest text-slate-400 flex items-center gap-2">
          <i class="fas fa-history text-accent"></i> Réservations récentes
        </h2>
      </div>
      <div class="divide-y divide-slate-50 max-h-[600px] overflow-y-auto">
        <?php if (empty($recentGuichet)): ?>
          <p class="text-center text-sm text-slate-400 py-8 italic">Aucune réservation.</p>
        <?php else: ?>
          <?php foreach ($recentGuichet as $r): ?>
          <div class="px-4 py-3 hover:bg-slate-50 transition">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 bg-primary/10 rounded-xl flex items-center justify-center font-black text-primary text-xs flex-shrink-0">
                <?= strtoupper(substr($r['nom_complet'],0,1)) ?>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-black text-primary text-xs truncate"><?= e($r['nom_complet']) ?></p>
                <p class="text-[10px] text-slate-500 truncate"><?= e($r['espace_nom']) ?></p>
                <p class="text-[10px] text-slate-400">
                  <?php if ($r['heure_debut']): ?>
                    <?= date('d/m/Y', strtotime($r['date_resa'])) ?> · <?= substr($r['heure_debut'],0,5) ?>→<?= substr($r['heure_fin'],0,5) ?>
                  <?php else: ?>
                    <?= date('d/m/Y', strtotime($r['date_resa'])) ?> → <?= date('d/m/Y', strtotime($r['date_depart'])) ?>
                  <?php endif; ?>
                </p>
              </div>
              <?php
                // État financier calculé (même libellé que la comptabilité)
                $sfG = situation_financiere_reservation($pdo, (int)$r['id']);
                [$etatG, $clsG] = libelle_etat_financier($sfG['etat'] ?? '');
              ?>
              <span class="text-[9px] font-black px-2 py-0.5 rounded-full border flex-shrink-0 <?= $clsG ?>">
                <?= e($etatG) ?>
              </span>
              <a href="../generer_bon.php?id=<?= $r['id'] ?>&from=guichet" target="_blank" title="Voir la facture"
                 class="w-7 h-7 flex-shrink-0 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-primary hover:text-white transition">
                <i class="fas fa-file-invoice text-[10px]"></i>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
const tarifsData  = <?= json_encode($tarifsData) ?>;
const espacesModes = <?= json_encode(array_column($espaces, 'mode_reservation', 'id')) ?>;
const espacesPetitDej = <?= json_encode(array_column($espaces, 'option_petit_dejeuner', 'id')) ?>;
const espacesVip = <?= json_encode(array_column($espaces, 'option_vip', 'id')) ?>;
const espacesPrixVip = <?= json_encode(array_column($espaces, 'prix_vip', 'id')) ?>;

function onEspaceChangeGuichet(espaceId) {
    loadTarifs(espaceId);
    const sejour = espacesModes[espaceId] === 'sejour';

    document.getElementById('creneauFields').classList.toggle('hidden', sejour);
    document.getElementById('sejourFields').classList.toggle('hidden', !sejour);

    document.getElementById('dateResaGuichet').disabled = sejour;
    document.getElementById('heure_debutGuichet').disabled = sejour;
    document.getElementById('heure_finGuichet').disabled = sejour;
    document.getElementById('dateResaSejourGuichet').disabled = !sejour;
    document.getElementById('dateDepartGuichet').disabled = !sejour;

    const avecVip = espacesVip[espaceId] == 1;
    document.getElementById('vipLabelGuichet').classList.toggle('hidden', !avecVip);
    if (!avecVip) { document.querySelector('input[name="vip"]').checked = false; }
    else { document.getElementById('vipPrixGuichet').textContent = '(+' + new Intl.NumberFormat('fr-FR').format(espacesPrixVip[espaceId] || 0) + ' FCFA)'; }

    const avecPetitDej = sejour && espacesPetitDej[espaceId] == 1;
    document.getElementById('petitDejLabelGuichet').classList.toggle('hidden', !avecPetitDej);
    if (!avecPetitDej) document.querySelector('#sejourFields input[name="petit_dejeuner"]').checked = false;
    document.getElementById('quantiteGuichet').value = 1;
}

function verifierDisponibilite() {
    const box = document.getElementById('resultatDispo');
    const espaceId = document.querySelector('select[name="espace_id"]').value;
    const tarifEl = document.querySelector('input[name="tarif_id"]:checked');
    const sejour = espacesModes[espaceId] === 'sejour';
    const params = new URLSearchParams({ verifier_dispo: '1', tarif_id: tarifEl ? tarifEl.value : '' });
    if (sejour) {
        params.set('date', document.getElementById('dateResaSejourGuichet').value);
        params.set('date_depart', document.getElementById('dateDepartGuichet').value);
        params.set('quantite', document.getElementById('quantiteGuichet').value || '1');
    } else {
        params.set('date', document.getElementById('dateResaGuichet').value);
        params.set('heure_debut', document.getElementById('heure_debutGuichet').value);
        params.set('heure_fin', document.getElementById('heure_finGuichet').value);
    }
    const afficher = (classes, html) => {
        box.className = 'mt-3 rounded-xl border p-3 text-xs font-bold ' + classes;
        box.innerHTML = html;
    };
    fetch('guichet.php?' + params.toString(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { afficher('bg-slate-50 border-slate-200 text-slate-600', d.message); return; }
            const cls = d.disponible === false ? 'bg-red-50 border-red-200 text-red-700'
                      : (d.disponible === true ? 'bg-green-50 border-green-200 text-green-700' : 'bg-slate-50 border-slate-200 text-slate-600');
            let html = '';
            const span = document.createElement('span'); span.textContent = d.message; html += span.outerHTML;
            if (d.libres) {
                html += '<p class="mt-1 font-semibold text-slate-600">Plages libres ce jour : '
                     + (d.libres.length ? d.libres.map(l => l.replace(/[<>&]/g, '')).join(', ') : 'aucune') + '</p>';
            }
            afficher(cls, html);
        })
        .catch(() => afficher('bg-red-50 border-red-200 text-red-700', 'Vérification impossible pour le moment.'));
}

function changeQuantiteGuichet(delta) {
    const input = document.getElementById('quantiteGuichet');
    const max = parseInt(input.max || '1', 10);
    let val = parseInt(input.value || '1', 10) + delta;
    val = Math.max(1, Math.min(val, max));
    input.value = val;
}

function updateQuantiteMaxGuichet(espaceId) {
    const tarifEl = document.querySelector('input[name="tarif_id"]:checked');
    if (!tarifEl || !tarifsData[espaceId]) return;
    const tarif = tarifsData[espaceId].find(t => String(t.id) === tarifEl.value);
    const max = (tarif && tarif.quantite_disponible) ? tarif.quantite_disponible : 1;
    const input = document.getElementById('quantiteGuichet');
    input.max = max;
    if (parseInt(input.value, 10) > max) input.value = max;
    document.getElementById('quantiteMaxGuichet').textContent = max > 1 ? `(max ${max} disponibles)` : '';
}

function loadTarifs(espaceId) {
    const section = document.getElementById('tarifSection');
    const grid    = document.getElementById('tarifGrid');
    grid.innerHTML = '';
    if (!espaceId || !tarifsData[espaceId] || !tarifsData[espaceId].length) { section.classList.add('hidden'); return; }
    tarifsData[espaceId].forEach((t, i) => {
        const lbl = document.createElement('label');
        lbl.className = 'flex items-center justify-between p-3 rounded-xl border-2 border-slate-100 cursor-pointer hover:border-primary transition';
        lbl.innerHTML = `
            <div class="flex items-center gap-3">
                <input type="radio" name="tarif_id" value="${t.id}" ${i===0?'checked':''} onchange="updateQuantiteMaxGuichet(${espaceId})" class="w-4 h-4 accent-primary">
                <span class="text-xs font-black text-slate-700">${t.libelle}</span>
            </div>
            <span class="text-sm font-black text-primary">${new Intl.NumberFormat('fr-FR').format(t.montant)} <span class="text-[10px] text-slate-400 font-normal">FCFA/${t.unite}</span></span>`;
        grid.appendChild(lbl);
    });
    section.classList.remove('hidden');
    updateQuantiteMaxGuichet(espaceId);
}

let searchTimer;
function searchClient(q) {
    clearTimeout(searchTimer);
    const results = document.getElementById('clientResults');
    if (q.length < 2) { results.classList.add('hidden'); return; }
    searchTimer = setTimeout(() => {
        fetch(`../api/search_user.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                results.innerHTML = '';
                if (!data.length) {
                    results.innerHTML = '<p class="text-xs text-slate-400 p-3 italic">Aucun client trouvé.</p>';
                } else {
                    data.forEach(u => {
                        const div = document.createElement('div');
                        div.className = 'flex items-center gap-3 px-4 py-3 hover:bg-primary/5 cursor-pointer border-b border-slate-50 transition';
                        div.innerHTML = `<div class="w-8 h-8 bg-primary rounded-xl flex items-center justify-center font-black text-white text-xs">${u.nom_complet[0].toUpperCase()}</div><div><p class="font-black text-primary text-sm">${u.nom_complet}</p><p class="text-xs text-slate-500">${u.email}</p></div>`;
                        div.onclick = () => selectClient(u);
                        results.appendChild(div);
                    });
                }
                results.classList.remove('hidden');
            }).catch(() => {});
    }, 300);
}

function selectClient(u) {
    document.getElementById('selectedUserId').value    = u.id;
    document.getElementById('clientNom').textContent   = u.nom_complet;
    document.getElementById('clientEmail').textContent = u.email;
    document.getElementById('clientInitiale').textContent = u.nom_complet[0].toUpperCase();
    document.getElementById('clientSelected').classList.remove('hidden');
    document.getElementById('newClientFields').classList.add('hidden');
    document.getElementById('clientResults').classList.add('hidden');
    document.getElementById('clientSearch').value = '';
}

function clearClient() {
    document.getElementById('selectedUserId').value = '0';
    document.getElementById('clientSelected').classList.add('hidden');
    document.getElementById('newClientFields').classList.remove('hidden');
}
</script>

<?php require __DIR__ . '/_admin_footer.php'; ?>