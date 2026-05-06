<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// 1. SÉCURITÉ : Vérifier la connexion
if (!is_logged_in()) {
    header('Location: login.php?msg=auth_required');
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $user_id   = $_SESSION['user_id'];
    $espace_id = (int)$_POST['espace_id'];
    
    // CORRECTION : Vérification sécurisée du tarif_id
    // Si la clé n'existe pas ou est vide, on met null (ou une valeur par défaut)
    $tarif_id  = isset($_POST['tarif_id']) && !empty($_POST['tarif_id']) ? (int)$_POST['tarif_id'] : null;
    
    $date_resa = $_POST['date_resa'];
    $telephone = $_POST['telephone'] ?? ''; 
    $motif     = $_POST['motif'] ?? '';

    // Horaires par défaut (à adapter selon ton besoin)
    $heure_debut = '08:00:00';
    $heure_fin   = '18:00:00';

    // Sécurité supplémentaire : si tarif_id est NULL, on vérifie si la DB l'autorise
    // Sinon, on redirige avec une erreur.
    if ($tarif_id === null) {
        die("Erreur : Veuillez sélectionner un tarif valide.");
    }

    try {
        // 2. INSERTION avec la nouvelle colonne tarif_id
        $stmt = $pdo->prepare("
            INSERT INTO reservations (
                user_id, 
                espace_id, 
                tarif_id, 
                date_resa, 
                heure_debut, 
                heure_fin, 
                statut, 
                motif, 
                created_at, 
                notification_vue
            ) 
            VALUES (?, ?, ?, ?, ?, ?, 'en_attente', ?, NOW(), 0)
        ");
        
        $stmt->execute([
            $user_id, 
            $espace_id, 
            $tarif_id, 
            $date_resa, 
            $heure_debut, 
            $heure_fin, 
            $motif
        ]);

        // 3. MISE À JOUR DU TÉLÉPHONE (Si non renseigné dans le profil)
        if (!empty($telephone)) {
            $updateTel = $pdo->prepare("
                UPDATE users 
                SET telephone = ? 
                WHERE id = ? AND (telephone IS NULL OR telephone = '')
            ");
            $updateTel->execute([$telephone, $user_id]);
        }

        // 4. REDIRECTION vers le tableau de bord
        header('Location: mon-compte.php?success=1');
        exit;

    } catch (PDOException $e) {
        // En cas d'erreur de contrainte SQL (ID de tarif inexistant par exemple)
        die("Erreur technique lors de l'enregistrement : " . $e->getMessage());
    }
} else {
    // Si on tente d'accéder au fichier sans POST
    header('Location: espaces.php');
    exit;
}