<<?php
require_once __DIR__ . '/../includes/auth.php';
$u = current_user();

// 1. CONFIGURATION DE LA CONNEXION
$host = 'localhost';
$db   = 'palais_pionniers'; 
$user = 'root'; 
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$msg = null;
if (isset($_GET['success'])) $msg = ['ok', 'Opération réussie !'];

// 2. TRAITEMENT DES ACTIONS (POST)
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    
    // Suppression d'image : on redirige vers le mode edit de l'espace parent
    if ($action === 'delete_image') {
        $imgId = (int)$_POST['image_id'];
        $stmt = $pdo->prepare("SELECT chemin, espace_id FROM espace_images WHERE id = ?");
        $stmt->execute([$imgId]);
        $img = $stmt->fetch();
        if ($img) {
            @unlink(__DIR__ . '/../uploads/' . $img['chemin']);
            $pdo->prepare("DELETE FROM espace_images WHERE id = ?")->execute([$imgId]);
            // Retour immédiat à la modif de cet espace précis
            header("Location: espaces.php?edit=" . $img['espace_id'] . "&success=1"); exit;
        }
    }

    if ($action === 'delete_espace') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT chemin FROM espace_images WHERE espace_id = ?");
        $stmt->execute([$id]);
        foreach($stmt->fetchAll() as $img) { @unlink(__DIR__ . '/../uploads/' . $img['chemin']); }
        $pdo->prepare("DELETE FROM espaces WHERE id = ?")->execute([$id]);
        header("Location: espaces.php?success=1"); exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom']);
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $nom)));
        $cat = (int)$_POST['categorie_id'];
        $cap = trim($_POST['capacite']);
        $prix = (float)$_POST['tarif'];
        $desc = trim($_POST['description'] ?? '');
        $equip = trim($_POST['equipements'] ?? '');

        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO espaces (nom, slug, categorie_id, capacite, tarif, description, equipements) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$nom, $slug, $cat, $cap, $prix, $desc, $equip]);
            $id = $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("UPDATE espaces SET nom=?, slug=?, categorie_id=?, capacite=?, tarif=?, description=?, equipements=? WHERE id=?");
            $stmt->execute([$nom, $slug, $cat, $cap, $prix, $desc, $equip, $id]);
        }

        if (!empty($_FILES['galerie']['name'][0])) {
            foreach ($_FILES['galerie']['tmp_name'] as $k => $tmp_name) {
                if($_FILES['galerie']['error'][$k] == 0){
                    $ext = strtolower(pathinfo($_FILES['galerie']['name'][$k], PATHINFO_EXTENSION));
                    $filename = "esp_" . $id . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($tmp_name, __DIR__ . '/../uploads/' . $filename)) {
                        $pdo->prepare("INSERT INTO espace_images (espace_id, chemin) VALUES (?, ?)")->execute([$id, $filename]);
                    }
                }
            }
        }
        
        // REDIRECTION INTELLIGENTE : on reste sur la fiche qu'on vient de modifier
        header("Location: espaces.php?edit=" . $id . "&success=1"); exit;
    }
}

// 3. CHARGEMENT DES DONNÉES (Reste identique)
$editId = $_GET['edit'] ?? null;
$showForm = isset($_GET['add']) || $editId;
$espaceToEdit = null;
$imagesToEdit = [];

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM espaces WHERE id = ?");
    $stmt->execute([$editId]);
    $espaceToEdit = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM espace_images WHERE espace_id = ?");
    $stmt->execute([$editId]);
    $imagesToEdit = $stmt->fetchAll();
}

$allEspaces = $pdo->query("SELECT e.*, c.nom as cat_nom FROM espaces e JOIN categories c ON e.categorie_id = c.id ORDER BY e.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= ($pageTitle ?? 'Admin — Palais des Pionniers') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      colors: { 
        primary: { DEFAULT:'#0A2558', dark:'#06184A' }, 
        accent: { DEFAULT:'#E61E2A', dark:'#B7141F' } 
      },
      boxShadow: { card: '0 1px 2px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.06)' },
    } }
  };
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body{font-family:Inter,system-ui;background:#F8FAFC;color:#0A2558}
    h1,h2,h3{font-family:'Plus Jakarta Sans',Inter,sans-serif;letter-spacing:-.02em}
    .animate-fade-in { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body class="min-h-screen">

<div class="flex min-h-screen">
  <!-- SIDEBAR GAUCHE -->
  <aside class="hidden md:flex w-64 flex-col bg-primary text-white sticky top-0 h-screen">
    <div class="px-6 py-5 border-b border-white/10">
      <div class="text-lg font-extrabold text-white uppercase italic">palais <span class="text-accent">PIONNIERS</span></div>
      <p class="text-xs text-white/60 mt-1 uppercase tracking-widest">Espace administrateur</p>
    </div>
    <nav class="flex-1 p-3 space-y-1 text-sm">
      <?php
      $nav = [
        'dashboard.php'    => ['📊', 'Tableau de bord'],
        'reservations.php' => ['📅', 'Réservations'],
        'activites.php'    => ['🎭', 'Activités'],
        'espaces.php'      => ['🏛️', 'Espaces'],
        'tarifs.php'       => ['💰', 'Tarifs'],
        'users.php'        => ['👥', 'Utilisateurs'],
      ];
      $current_file = basename($_SERVER['PHP_SELF']);
      foreach ($nav as $href => [$ico, $label]):
        $active = ($current_file === $href);
      ?>
        <a href="<?= $href ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition-all <?= $active ? 'bg-accent text-white shadow-lg shadow-accent/20' : 'text-white/80 hover:bg-white/10' ?>">
          <span><?= $ico ?></span><span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
      <a href="../logout.php" onclick="return confirm('Déconnexion ?')" class="mt-4 flex items-center gap-3 rounded-lg px-3 py-2.5 text-red-300 hover:bg-red-500/10">
        <span>🚪</span><span>Déconnexion</span>
      </a>
    </nav>
  </aside>

  <div class="flex-1 flex flex-col">
    <!-- HEADER -->
    <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 sticky top-0 z-40">
      <a href="../index.php" class="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-primary transition">
        <span>←</span> Retour au site
      </a>
      <div class="flex items-center gap-3">
        <div class="text-right text-sm">
          <div class="font-bold text-primary"><?= ($u['nom_complet']) ?></div>
          <div class="text-xs text-slate-500">Administrateur</div>
        </div>
        <div class="grid h-10 w-10 place-items-center rounded-full bg-primary text-sm font-black text-white shadow-md border-2 border-white">
          <?= strtoupper(substr($u['nom_complet'],0,1)) ?>
        </div>
      </div>
    </header>

    <main class="flex-1 p-6 md:p-8">
      
      <!-- TITRE ET BOUTON AJOUTER -->
      <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl font-black text-primary uppercase tracking-tight">Gestion des Espaces</h2>
            <p class="text-slate-500 text-sm">Contrôle des infrastructures et galeries photos</p>
        </div>
        <a href="?add=1" class="bg-primary hover:bg-primary-dark text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 transition shadow-xl shadow-primary/20">
            <i class="fas fa-plus-circle text-lg"></i> Ajouter un espace
        </a>
      </div>

      <?php if ($msg): ?>
          <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r-xl flex items-center gap-3 animate-fade-in">
              <i class="fas fa-check-circle text-xl"></i> <?= $msg[1] ?>
          </div>
      <?php endif; ?>

      <!-- FORMULAIRE (MODIFICATION OU AJOUT) -->
      <?php if ($showForm): ?>
      <div class="bg-white rounded-3xl shadow-card border border-slate-100 mb-10 overflow-hidden animate-fade-in">
          <div class="bg-slate-50 px-8 py-5 border-b border-slate-100 flex justify-between items-center">
              <h3 class="font-bold text-primary uppercase italic tracking-wider"><?= $editId ? 'Modifier l\'espace' : 'Configuration Nouvel Espace' ?></h3>
              <a href="espaces.php" class="h-8 w-8 grid place-items-center rounded-full bg-white text-slate-400 hover:text-accent shadow-sm transition"><i class="fas fa-times"></i></a>
          </div>
          <form action="espaces.php" method="POST" enctype="multipart/form-data" class="p-8 space-y-8">
              <input type="hidden" name="id" value="<?= $espaceToEdit['id'] ?? '' ?>">
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                  <div class="md:col-span-2">
                      <label class="block text-xs font-black uppercase text-slate-400 mb-2">Nom de l'espace / Salle</label>
                      <input type="text" name="nom" value="<?= $espaceToEdit['nom'] ?? '' ?>" required class="w-full border-slate-200 border-2 p-3.5 rounded-2xl focus:ring-4 focus:ring-accent/10 focus:border-accent outline-none transition-all">
                  </div>
                  <div>
                      <label class="block text-xs font-black uppercase text-slate-400 mb-2">Type d'espace (Catégorie)</label>
                      <select name="categorie_id" class="w-full border-slate-200 border-2 p-3.5 rounded-2xl outline-none focus:border-primary transition-all">
                          <?php foreach ($categories as $cat): ?>
                              <option value="<?= $cat['id'] ?>" <?= (isset($espaceToEdit['categorie_id']) && $espaceToEdit['categorie_id'] == $cat['id']) ? 'selected' : '' ?>><?= $cat['nom'] ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <!-- Dans la grille des tarifs (ligne ~140 du code précédent) -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 italic">Tarif / Jour (FCFA)</label>
                        <input type="number" 
                            name="tarif" 
                            value="<?= htmlspecialchars($espaceToEdit['tarif'] ?? '0') ?>" 
                            class="w-full bg-slate-50 border-2 border-slate-200 p-4 rounded-2xl font-black text-[#E61E2A]" 
                            placeholder="0">
                    </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div>
                      <label class="block text-xs font-black uppercase text-slate-400 mb-2">Capacité d'accueil</label>
                      <input type="text" name="capacite" value="<?= $espaceToEdit['capacite'] ?? '' ?>" placeholder="ex: 400 places" class="w-full border-slate-200 border-2 p-3.5 rounded-2xl">
                  </div>
                  <div>
                      <label class="block text-xs font-black uppercase text-slate-400 mb-2">Équipements disponibles</label>
                      <input type="text" name="equipements" value="<?= $espaceToEdit['equipements'] ?? '' ?>" placeholder="Wifi, Sonorisation, Climatisation..." class="w-full border-slate-200 border-2 p-3.5 rounded-2xl">
                  </div>

              </div>

              <div>
                  <label class="block text-xs font-black uppercase text-slate-400 mb-2">Présentation détaillée</label>
                  <textarea name="description" rows="4" class="w-full border-slate-200 border-2 p-3.5 rounded-2xl outline-none focus:border-primary"><?= $espaceToEdit['description'] ?? '' ?></textarea>
              </div>

              <!-- GALERIE PHOTO INTERACTIVE -->
              <div class="p-8 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                  <label class="block text-xs font-black uppercase text-slate-400 mb-4">Galerie Photos & Médias</label>
                  <input type="file" name="galerie[]" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-6 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-primary file:text-white hover:file:bg-primary-dark cursor-pointer transition">

                  <?php if ($editId && !empty($imagesToEdit)): ?>
                  <div class="mt-8 pt-8 border-t border-slate-200">
                      <p class="text-xs font-bold text-primary uppercase mb-5 tracking-widest">Images en ligne (<?= count($imagesToEdit) ?>)</p>
                      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
                          <?php foreach ($imagesToEdit as $img): ?>
                              <div class="group relative aspect-square bg-white rounded-2xl overflow-hidden border-2 border-white shadow-sm hover:shadow-xl transition-all duration-300">
                                  <img src="../uploads/<?= htmlspecialchars($img['chemin']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                                  <div class="absolute inset-0 bg-primary/40 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center backdrop-blur-sm">
                                      <button type="submit" name="action" value="delete_image" onclick="return confirm('Retirer cette photo de la galerie ?')" class="bg-accent text-white w-12 h-12 rounded-2xl flex items-center justify-center hover:scale-110 transition active:scale-95 shadow-2xl">
                                          <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                          <i class="fas fa-trash-alt text-lg"></i>
                                      </button>
                                  </div>
                              </div>
                          <?php endforeach; ?>
                      </div>
                  </div>
                  <?php endif; ?>
              </div>

              <div class="flex justify-end pt-4">
                  <button type="submit" name="action" value="<?= $editId ? 'update' : 'create' ?>" class="bg-accent hover:bg-accent-dark text-white px-10 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl shadow-accent/30 active:scale-95">
                      <i class="fas fa-save mr-3"></i> <?= $editId ? 'Mettre à jour les données' : 'Confirmer la création' ?>
                  </button>
              </div>
          </form>
      </div>
      <?php endif; ?>

      <!-- TABLEAU DES ESPACES EXISTANTS -->
      <div class="bg-white rounded-3xl shadow-card border border-slate-100 overflow-hidden">
          <div class="px-8 py-5 border-b border-slate-50 bg-slate-50/50">
                <h3 class="text-xs font-black uppercase text-slate-400 tracking-widest italic">Infrastructures répertoriées</h3>
          </div>
          <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                  <thead class="bg-slate-50/30 text-slate-400 text-xs font-black uppercase">
                      <tr>
                          <th class="px-8 py-5">Espace</th>
                          <th class="px-8 py-5">Catégorie</th>
                          <th class="px-8 py-5 text-center">Capacité</th>
                          <th class="px-8 py-5 text-center">Actions</th>
                      </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 text-sm">
                      <?php foreach ($allEspaces as $esp): ?>
                      <tr class="hover:bg-slate-50/80 transition-colors">
                          <td class="px-8 py-5">
                              <div class="font-bold text-primary text-base"><?= htmlspecialchars($esp['nom']) ?></div>
                              <div class="text-[10px] text-slate-400 uppercase font-bold tracking-tighter"><?= htmlspecialchars($esp['slug']) ?></div>
                          </td>
                          <td class="px-8 py-5">
                              <span class="bg-primary/5 text-primary px-4 py-1.5 rounded-full text-[11px] font-black uppercase tracking-wider border border-primary/10">
                                  <?= htmlspecialchars($esp['cat_nom']) ?>
                              </span>
                          </td>
                          <td class="px-8 py-5 text-center font-bold text-slate-600"><?= htmlspecialchars($esp['capacite']) ?></td>
                          <td class="px-8 py-5 flex justify-center gap-3">
                              <a href="?edit=<?= $esp['id'] ?>" class="h-10 w-10 grid place-items-center rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-sm" title="Modifier">
                                  <i class="fas fa-edit"></i>
                              </a>
                              <form action="espaces.php" method="POST" onsubmit="return confirm('Action irréversible : Supprimer cet espace et ses photos ?');">
                                  <input type="hidden" name="id" value="<?= $esp['id'] ?>">
                                  <button type="submit" name="action" value="delete_espace" class="h-10 w-10 grid place-items-center rounded-xl bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition shadow-sm" title="Supprimer">
                                      <i class="fas fa-trash-alt"></i>
                                  </button>
                              </form>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
          <?php if(empty($allEspaces)): ?>
            <div class="p-20 text-center text-slate-400">
                <i class="fas fa-box-open text-4xl mb-4 opacity-20"></i>
                <p>Aucun espace enregistré pour le moment.</p>
            </div>
          <?php endif; ?>
      </div>

    </main>
  </div>
</div>

</body>
</html><?php
require_once __DIR__ . '/../includes/auth.php';
$u = current_user();

// 1. CONFIGURATION DE LA CONNEXION
$host = 'localhost';
$db   = 'palais_pionniers'; 
$user = 'root'; 
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$msg = null;
if (isset($_GET['success'])) $msg = ['ok', 'Opération réussie !'];

// 2. TRAITEMENT DES ACTIONS (POST)
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    
    if ($action === 'delete_image') {
        $imgId = (int)$_POST['image_id'];
        $stmt = $pdo->prepare("SELECT chemin FROM espace_images WHERE id = ?");
        $stmt->execute([$imgId]);
        $img = $stmt->fetch();
        if ($img) {
            @unlink(__DIR__ . '/../uploads/' . $img['chemin']);
            $pdo->prepare("DELETE FROM espace_images WHERE id = ?")->execute([$imgId]);
            $msg = ['ok', 'Image supprimée de la galerie'];
        }
    }

    if ($action === 'delete_espace') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT chemin FROM espace_images WHERE espace_id = ?");
        $stmt->execute([$id]);
        foreach($stmt->fetchAll() as $img) { @unlink(__DIR__ . '/../uploads/' . $img['chemin']); }
        $pdo->prepare("DELETE FROM espaces WHERE id = ?")->execute([$id]);
        header("Location: espaces.php?success=1"); exit;
    }

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = trim($_POST['nom']);
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $nom)));
        $cat = (int)$_POST['categorie_id'];
        $cap = trim($_POST['capacite']);
        $prix = (float)$_POST['tarif']; // Nouveau champ Tarif
        $desc = trim($_POST['description'] ?? '');
        $equip = trim($_POST['equipements'] ?? '');

        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO espaces (nom, slug, categorie_id, capacite, tarif, description, equipements) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$nom, $slug, $cat, $cap, $prix, $desc, $equip]);
            $id = $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare("UPDATE espaces SET nom=?, slug=?, categorie_id=?, capacite=?, tarif=?, description=?, equipements=? WHERE id=?");
            $stmt->execute([$nom, $slug, $cat, $cap, $prix, $desc, $equip, $id]);
        }

        if (!empty($_FILES['galerie']['name'][0])) {
            foreach ($_FILES['galerie']['tmp_name'] as $k => $tmp_name) {
                if($_FILES['galerie']['error'][$k] == 0){
                    $ext = strtolower(pathinfo($_FILES['galerie']['name'][$k], PATHINFO_EXTENSION));
                    $filename = "esp_" . $id . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($tmp_name, __DIR__ . '/../uploads/' . $filename)) {
                        $pdo->prepare("INSERT INTO espace_images (espace_id, chemin) VALUES (?, ?)")->execute([$id, $filename]);
                    }
                }
            }
        }
        header("Location: espaces.php?success=1"); exit;
    }
}

// 3. CHARGEMENT DES DONNÉES
$editId = $_GET['edit'] ?? null;
$showForm = isset($_GET['add']) || $editId;
$espaceToEdit = null;
$imagesToEdit = [];

if ($editId) {
    $stmt = $pdo->prepare("SELECT * FROM espaces WHERE id = ?");
    $stmt->execute([$editId]);
    $espaceToEdit = $stmt->fetch();
    $stmt = $pdo->prepare("SELECT * FROM espace_images WHERE espace_id = ?");
    $stmt->execute([$editId]);
    $imagesToEdit = $stmt->fetchAll();
}

$allEspaces = $pdo->query("SELECT e.*, c.nom as cat_nom FROM espaces e JOIN categories c ON e.categorie_id = c.id ORDER BY e.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom ASC")->fetchAll();
?>

