<?php
require_once 'config/db.php'; // Votre connexion PDO

$message = "";

// 1. Récupérer la liste des activités pour le menu déroulant
$activites = $pdo->query("SELECT id, nom FROM activites ORDER BY nom ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['photos'])) {
    $activite_id = $_POST['activite_id'];
    $categorie = $_POST['categorie'];
    $target_dir = "assets/images/activites/galerie/";

    // Créer le dossier s'il n'existe pas
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $upload_errors = 0;
    $uploaded_count = 0;

    // 2. Boucle sur les fichiers envoyés
    foreach ($_FILES['photos']['name'] as $key => $name) {
        if ($_FILES['photos']['error'][$key] == 0) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            // Nom unique pour éviter les doublons : ID_TIMESTAMP_RANDOM
            $new_name = $activite_id . "_" . time() . "_" . rand(100, 999) . "." . $extension;
            $target_path = $target_dir . $new_name;

            if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $target_path)) {
                // 3. Insertion en base de données pour chaque image
                $stmt = $pdo->prepare("INSERT INTO activite_galerie (activite_id, image_path, categorie) VALUES (?, ?, ?)");
                $stmt->execute([$activite_id, $new_name, $categorie]);
                $uploaded_count++;
            } else {
                $upload_errors++;
            }
        }
    }

    if ($uploaded_count > 0) {
        $message = "<div class='alert alert-success'>$uploaded_count image(s) ajoutée(s) avec succès dans la catégorie '$categorie' !</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Admin - Gestion des Galeries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow border-0 rounded-4">
                    <div class="card-body p-5">
                        <h2 class="fw-black text-uppercase italic mb-4">Ajouter des <span class="text-danger">Photos</span></h2>
                        <?= $message ?>

                        <form action="" method="POST" enctype="multipart/form-data">
                            <!-- Sélection de l'activité -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Sélectionner l'activité</label>
                                <select name="activite_id" class="form-select" required>
                                    <option value="">-- Choisir une activité --</option>
                                    <?php foreach ($activites as $act): ?>
                                        <option value="<?= $act['id'] ?>"><?= htmlspecialchars($act['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Catégorie -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Catégorie des photos</label>
                                <input type="text" name="categorie" class="form-control" placeholder="Ex: Immersion, Cérémonie, Formation" required>
                                <div class="form-text">Les photos seront regroupées par ce nom sur la page.</div>
                            </div>

                            <!-- Upload Multiple -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Choisir les images</label>
                                <input type="file" name="photos[]" class="form-control" accept="image/*" multiple required>
                                <div class="form-text">Vous pouvez sélectionner plusieurs fichiers à la fois.</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger btn-lg text-uppercase fw-bold">Lancer l'upload</button>
                                <a href="detail-activite.php?slug=eci" class="btn btn-outline-secondary">Voir le résultat (ECI)</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>