<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['ministre','admin_messages','admin_espaces','admin_activites']);

$pdo  = db();
$role = $_SESSION['role'] ?? '';
$readonly = is_readonly_admin();

$whereRole      = '';
$whereRoleAlias = '';
if ($role === 'admin_espaces') {
    $whereRole      = "AND sujet = 'reservation_espace'";
    $whereRoleAlias = "AND m.sujet = 'reservation_espace'";
}
if ($role === 'admin_activites') {
    $whereRole      = "AND sujet = 'activite'";
    $whereRoleAlias = "AND m.sujet = 'activite'";
}

if (!$readonly && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    if ($id) {
        $perimetre = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE id = ? " . $whereRole);
        $perimetre->execute([$id]);
        if (!(int)$perimetre->fetchColumn()) {
            header('Location: messages.php'); exit;
        }
    }
    if ($action === 'mark_lu' && $id) {
        $pdo->prepare("UPDATE messages SET lu=1, lu_at=NOW() WHERE id=?")->execute([$id]);
        log_activity('message_lu','messages',"Message #$id marqué comme lu");
    }
    if ($action === 'mark_non_lu' && $id) {
        $pdo->prepare("UPDATE messages SET lu=0, lu_at=NULL WHERE id=?")->execute([$id]);
    }
    if ($action === 'delete' && $id && is_superadmin()) {
        $mDel = $pdo->prepare('SELECT nom FROM messages WHERE id=?'); $mDel->execute([$id]); $mDel=$mDel->fetch();
        $pdo->prepare("DELETE FROM messages WHERE id=?")->execute([$id]);
        log_activity('message_supprime','messages',"Message de «{$mDel['nom']}» supprimé (#$id)");
    }
    if ($action === 'mark_all_lu') {
        $pdo->query("UPDATE messages SET lu=1, lu_at=NOW() WHERE lu=0 " . $whereRole . "");
    }
    if ($action === 'repondre' && $id) {
        $reponse = trim($_POST['reponse'] ?? '');
        $viaSite  = !empty($_POST['via_site']) ? 1 : 0;
        $viaEmail = !empty($_POST['via_email']) ? 1 : 0;

        if (mb_strlen($reponse) < 5) {
            header('Location: messages.php?id=' . $id . '&err=reponse_courte'); exit;
        }

        $msgOrig = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
        $msgOrig->execute([$id]);
        $msgOrig = $msgOrig->fetch();

        $pdo->prepare("UPDATE messages SET reponse=?, repondu_par=?, repondu_at=NOW(), envoye_site=?, envoye_email=? WHERE id=?")
            ->execute([$reponse, $_SESSION['user_id'], $viaSite, $viaEmail, $id]);

        if ($viaSite && $msgOrig) {
            if (array_key_exists('user_id', $msgOrig)) {
                $userId = $msgOrig['user_id'] ? (int)$msgOrig['user_id'] : 0;
            } else {
                $userMatch = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role IN ('user','partenaire')");
                $userMatch->execute([$msgOrig['email']]);
                $userId = $userMatch->fetchColumn();
            }
            if ($userId) {
                notify('', 'reponse_message', "Réponse à votre message : " . mb_substr($reponse, 0, 120) . (mb_strlen($reponse) > 120 ? '…' : ''), "mon-compte.php?tab=messages", (int)$userId);
            }
        }

        if ($viaEmail && $msgOrig) {
            $sujetMail = "Réponse à votre message - Palais des Pionniers";
            $corpsMail = "Bonjour {$msgOrig['nom']},\n\n$reponse\n\nLe Palais des Pionniers";
            $envoye = filter_var($msgOrig['email'], FILTER_VALIDATE_EMAIL)
                && @mail($msgOrig['email'], $sujetMail, $corpsMail, "From: Palais des Pionniers <ppb@mjsports.gouv.ml>\r\nContent-Type: text/plain; charset=UTF-8");
            if (!$envoye) {
                $viaEmail = 0;
                $pdo->prepare("UPDATE messages SET envoye_email = 0 WHERE id = ?")->execute([$id]);
            }
        }

        log_activity('message_repondu', 'messages', "Réponse envoyée au message #$id" . ($viaSite ? ' (site)' : '') . ($viaEmail ? ' (email)' : ''));
        header('Location: messages.php?id=' . $id . '&success=1'); exit;
    }
    header('Location: messages.php'); exit;
}

$filterLu   = $_GET['lu']    ?? 'all';
$filterSujet= $_GET['sujet'] ?? '';
$search     = trim($_GET['q'] ?? '');
$msgId      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$where = "WHERE 1=1 $whereRoleAlias";
if ($filterLu === '0') $where .= " AND m.lu=0";
if ($filterLu === '1') $where .= " AND m.lu=1";
if ($filterSujet) $where .= " AND m.sujet=".db()->quote($filterSujet);
if ($search) $where .= " AND (m.nom LIKE ".db()->quote('%'.$search.'%')." OR m.email LIKE ".db()->quote('%'.$search.'%')." OR m.message LIKE ".db()->quote('%'.$search.'%').")";

$messages = $pdo->query("
    SELECT m.*, e.nom AS espace_nom, a.nom AS activite_titre
    FROM messages m
    LEFT JOIN espaces   e ON e.id = m.espace_id
    LEFT JOIN activites a ON a.id = m.activite_id
    $where
    ORDER BY m.lu ASC, m.created_at DESC
")->fetchAll();

$openMsg = null;
if ($msgId) {
    $s = $pdo->prepare("SELECT m.*, e.nom AS espace_nom, a.nom AS activite_titre FROM messages m LEFT JOIN espaces e ON e.id=m.espace_id LEFT JOIN activites a ON a.id=m.activite_id WHERE m.id=? $whereRoleAlias");
    $s->execute([$msgId]);
    $openMsg = $s->fetch();
    if ($openMsg && !$openMsg['lu'] && !$readonly) {
        $pdo->prepare("UPDATE messages SET lu=1, lu_at=NOW() WHERE id=?")->execute([$msgId]);
        $openMsg['lu'] = 1;
    }
}

$totalNonLus = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE lu=0 " . $whereRole)->fetchColumn();

if (!$readonly && $totalNonLus > 0) {
    $pdo->query("UPDATE messages SET lu=1, lu_at=NOW() WHERE lu=0 " . $whereRole);
}

$sujetLabels = [
    'reservation_espace'   => ['Réservation espace', 'bg-blue-100 text-blue-700'],
    'activite'             => ['Activité',            'bg-purple-100 text-purple-700'],
    'information_generale' => ['Information',         'bg-slate-100 text-slate-600'],
    'reclamation'          => ['Réclamation',         'bg-red-100 text-red-700'],
    'autre'                => ['Autre',               'bg-slate-100 text-slate-500'],
];

$pageTitle = "Boîte de réception";
require __DIR__ . '/_admin_header.php';
?>

<div class="flex items-center justify-between mb-6 flex-wrap gap-4">
  <div>
    <h1 class="text-2xl font-black text-primary uppercase italic tracking-tight flex items-center gap-2">
      <i class="fas fa-envelope text-accent"></i> Boîte de réception
      <?php if ($totalNonLus): ?><span class="text-base bg-accent text-white px-2.5 py-0.5 rounded-full"><?= $totalNonLus ?></span><?php endif; ?>
    </h1>
    <p class="text-sm text-slate-500 mt-0.5"><?= count($messages) ?> message(s) affiché(s)</p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
  <a href="export.php?type=messages" target="_blank" rel="noopener" class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition"><i class="fas fa-file-excel"></i> Exporter (Excel)</a>
  <?php if ($totalNonLus && !$readonly): ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="mark_all_lu">
    <button class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
      <i class="fas fa-check-double"></i> Tout marquer comme lu
    </button>
  </form>
  <?php endif; ?>
  </div>
</div>

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[180px]">
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Rechercher</label>
      <div class="relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Nom, email, message..."
               class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-primary outline-none focus:border-primary">
      </div>
    </div>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Statut</label>
      <select name="lu" class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none">
        <option value="all" <?= $filterLu==='all'?'selected':''?>>Tous</option>
        <option value="0"   <?= $filterLu==='0'?'selected':''?>>Non lus</option>
        <option value="1"   <?= $filterLu==='1'?'selected':''?>>Lus</option>
      </select>
    </div>
    <?php if (is_superadmin() || $role === 'admin_messages'): ?>
    <div>
      <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">Sujet</label>
      <select name="sujet" class="rounded-xl border border-slate-200 text-sm font-bold text-primary px-3 py-2.5 outline-none">
        <option value="">Tous les sujets</option>
        <?php foreach ($sujetLabels as $key=>[$lbl,$_]): ?>
          <option value="<?= $key ?>" <?= $filterSujet===$key?'selected':''?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button class="bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
    <?php if ($search||$filterLu!=='all'||$filterSujet): ?>
    <a href="messages.php" class="text-xs font-black text-slate-400 hover:text-primary px-3 py-2.5 rounded-xl border border-slate-200 transition">
      <i class="fas fa-times mr-1"></i> Réinitialiser
    </a>
    <?php endif; ?>
  </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-5">

  <div class="lg:col-span-2 space-y-2 min-w-0">
    <?php if (empty($messages)): ?>
      <div class="bg-white rounded-2xl border border-slate-100 p-12 text-center">
        <i class="fas fa-inbox text-4xl text-slate-200 mb-3"></i>
        <p class="text-slate-400 font-semibold">Aucun message trouvé.</p>
      </div>
    <?php else: ?>
      <?php foreach ($messages as $msg):
        [$sujetLbl,$sujetCls] = $sujetLabels[$msg['sujet']] ?? ['—','bg-slate-100 text-slate-500'];
        $isOpen = ($openMsg && $openMsg['id'] == $msg['id']);
      ?>
      <a href="messages.php?id=<?= $msg['id'] ?><?= $search?"&q=".urlencode($search):'' ?>"
         class="block bg-white rounded-2xl border-2 p-4 hover:shadow-md transition-all <?= $isOpen ? 'border-primary shadow-md' : 'border-transparent border-slate-100' ?> <?= !$msg['lu'] ? 'bg-blue-50/40' : '' ?>">
        <div class="flex items-start gap-3">
          <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center font-black text-white text-sm flex-shrink-0 mt-0.5">
            <?= e(mb_strtoupper(mb_substr((string)$msg['nom'], 0, 1))) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <p class="font-black text-primary text-sm truncate <?= !$msg['lu']?'':'font-semibold text-slate-700' ?>"><?= e($msg['nom']) ?></p>
              <?php if (!$msg['lu']): ?><span class="w-2 h-2 bg-accent rounded-full flex-shrink-0"></span><?php endif; ?>
            </div>
            <p class="text-xs text-slate-500 truncate mt-0.5"><?= e(substr($msg['message'],0,60)) ?>...</p>
            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
              <span class="text-[9px] font-black px-2 py-0.5 rounded-full <?= $sujetCls ?>"><?= $sujetLbl ?></span>
              <span class="text-[9px] text-slate-400"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></span>
            </div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="lg:col-span-3">
    <?php if ($openMsg): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center font-black text-white text-lg flex-shrink-0">
            <?= e(mb_strtoupper(mb_substr((string)$openMsg['nom'], 0, 1))) ?>
          </div>
          <div>
            <h2 class="font-black text-primary text-base"><?= e($openMsg['nom']) ?></h2>
            <p class="text-sm text-slate-500"><?= e($openMsg['email']) ?></p>
            <?php if ($openMsg['telephone']): ?><p class="text-sm text-slate-500"><i class="fas fa-phone text-xs mr-1"></i><?= e($openMsg['telephone']) ?></p><?php endif; ?>
          </div>
        </div>
        <div class="text-right flex-shrink-0">
          <p class="text-xs text-slate-400"><?= date('d/m/Y à H:i', strtotime($openMsg['created_at'])) ?></p>
          <?php [$sujetLbl,$sujetCls] = $sujetLabels[$openMsg['sujet']] ?? ['—','bg-slate-100']; ?>
          <span class="inline-block mt-1 text-[10px] font-black px-2.5 py-0.5 rounded-full <?= $sujetCls ?>"><?= $sujetLbl ?></span>
        </div>
      </div>

      <?php if ($openMsg['espace_nom'] || $openMsg['activite_titre']): ?>
      <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex items-center gap-2 text-xs">
        <?php if ($openMsg['espace_nom']): ?>
          <i class="fas fa-building text-accent"></i>
          <span class="font-bold text-primary">Espace concerné :</span>
          <span class="text-slate-600"><?= e($openMsg['espace_nom']) ?></span>
        <?php endif; ?>
        <?php if ($openMsg['activite_titre']): ?>
          <i class="fas fa-star text-accent"></i>
          <span class="font-bold text-primary">Activité concernée :</span>
          <span class="text-slate-600"><?= e($openMsg['activite_titre']) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="px-6 py-6">
        <p class="text-slate-700 leading-relaxed text-sm whitespace-pre-line"><?= e($openMsg['message']) ?></p>
      </div>

      <?php if (!empty($openMsg['reponse'])): ?>
      <div class="mx-6 mb-6 bg-emerald-50 border border-emerald-200 rounded-2xl p-5">
        <div class="flex items-center gap-2 mb-2">
          <i class="fas fa-reply text-emerald-600"></i>
          <p class="text-xs font-black text-emerald-700 uppercase tracking-widest">Votre réponse
            <?= date('d/m/Y à H:i', strtotime($openMsg['repondu_at'])) ?>
            <?php if ($openMsg['envoye_site']): ?><span class="ml-1 bg-emerald-600 text-white px-2 py-0.5 rounded-full text-[9px]">Sur le site</span><?php endif; ?>
            <?php if ($openMsg['envoye_email']): ?><span class="ml-1 bg-emerald-600 text-white px-2 py-0.5 rounded-full text-[9px]">Par email</span><?php endif; ?>
          </p>
        </div>
        <p class="text-sm text-emerald-800 whitespace-pre-line"><?= e($openMsg['reponse']) ?></p>
      </div>
      <?php endif; ?>

      <?php if (!$readonly): ?>
      <div class="mx-6 mb-6 bg-slate-50 border border-slate-200 rounded-2xl p-5">
        <p class="text-xs font-black text-primary uppercase tracking-widest mb-3"><i class="fas fa-pen mr-1"></i><?= !empty($openMsg['reponse']) ? 'Modifier la réponse' : 'Répondre' ?></p>
        <form method="POST" class="space-y-3">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="repondre">
          <input type="hidden" name="id" value="<?= $openMsg['id'] ?>">
          <textarea name="reponse" rows="4" required minlength="5" placeholder="Votre réponse..."
                    class="w-full rounded-xl border-2 border-slate-100 bg-white px-4 py-3 text-sm font-semibold text-primary outline-none focus:border-primary resize-none"><?= e($openMsg['reponse'] ?? '') ?></textarea>
          <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
              <input type="checkbox" name="via_site" value="1" checked class="w-4 h-4 accent-accent">
              Visible sur son compte (si un compte correspond à cet email)
            </label>
            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer">
              <input type="checkbox" name="via_email" value="1" class="w-4 h-4 accent-accent">
              Envoyer aussi par email
            </label>
          </div>
          <div class="flex justify-end">
            <button type="submit" class="bg-primary text-white text-xs font-black uppercase px-6 py-2.5 rounded-xl hover:bg-slate-800 transition">
              <i class="fas fa-paper-plane mr-1"></i> Envoyer la réponse
            </button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="px-6 pb-6 flex flex-wrap gap-3">
        <button type="button" data-email="<?= e($openMsg['email']) ?>" onclick="copyEmail(this.dataset.email)"
                class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition" id="copyEmailBtn">
          <i class="fas fa-copy"></i> <span id="copyEmailLabel">Copier l'email</span>
        </button>
        <a href="mailto:<?= e($openMsg['email']) ?>?subject=<?= urlencode('Re: Palais des Pionniers — '.$sujetLbl) ?>&body=<?= urlencode("Bonjour ".$openMsg['nom'].",

") ?>"
           class="flex items-center gap-2 bg-primary text-white text-xs font-black uppercase px-4 py-2.5 rounded-xl hover:bg-slate-800 transition">
          <i class="fas fa-reply"></i> Ouvrir dans ma messagerie
        </a>

        <?php if (!$readonly): ?>
        <form method="POST" class="inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="id" value="<?= $openMsg['id'] ?>">
          <input type="hidden" name="action" value="<?= $openMsg['lu'] ? 'mark_non_lu' : 'mark_lu' ?>">
          <button class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
            <i class="fas <?= $openMsg['lu'] ? 'fa-envelope' : 'fa-envelope-open' ?>"></i>
            <?= $openMsg['lu'] ? 'Marquer non lu' : 'Marquer lu' ?>
          </button>
        </form>
        <?php endif; ?>

        <?php if (is_superadmin()): ?>
        <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce message ?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="id" value="<?= $openMsg['id'] ?>">
          <input type="hidden" name="action" value="delete">
          <button class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-black uppercase px-4 py-2.5 rounded-xl transition">
            <i class="fas fa-trash"></i> Supprimer
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl border border-slate-100 h-full flex flex-col items-center justify-center py-20 text-center">
      <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
        <i class="fas fa-envelope-open-text text-2xl text-slate-300"></i>
      </div>
      <p class="font-black text-slate-400 text-sm uppercase tracking-wide">Sélectionnez un message</p>
      <p class="text-xs text-slate-300 mt-1">Cliquez sur un message pour le lire</p>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
function copyEmail(email) {
    navigator.clipboard.writeText(email).then(() => {
        const btn = document.getElementById('copyEmailBtn');
        const lbl = document.getElementById('copyEmailLabel');
        lbl.textContent = 'Email copié !';
        btn.classList.add('bg-green-100','text-green-700');
        btn.classList.remove('bg-slate-100','text-slate-700');
        setTimeout(() => {
            lbl.textContent = "Copier l'email";
            btn.classList.remove('bg-green-100','text-green-700');
            btn.classList.add('bg-slate-100','text-slate-700');
        }, 2500);
    }).catch(() => {
        prompt("Copiez cet email :", email);
    });
}
</script>
<?php require __DIR__ . '/_admin_footer.php'; ?>