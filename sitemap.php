<?php
// Plan du site pour les moteurs de recherche, servi aussi à l'adresse /sitemap.xml (.htaccess)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

header('Content-Type: application/xml; charset=utf-8');

$racine = url_racine_site();
$adresse = function (string $fichier, array $params = []) use ($racine): string {
    $nom = basename($fichier, '.php');
    if ($nom === 'index') {
        return $racine . '/';
    }
    $chemin = url_propres_actives() ? $nom : $nom . '.php';
    return $racine . '/' . $chemin . ($params ? '?' . http_build_query($params) : '');
};

$urls = [
    [$adresse('index.php'), '1.0'],
    [$adresse('espaces.php'), '0.9'],
    [$adresse('activites.php'), '0.9'],
    [$adresse('formations.php'), '0.8'],
    [$adresse('sengager.php'), '0.8'],
    [$adresse('personnalites.php'), '0.7'],
    [$adresse('a-propos.php'), '0.7'],
    [$adresse('faq.php'), '0.6'],
    [$adresse('contact.php'), '0.6'],
    [$adresse('demande-bail.php'), '0.5'],
    [$adresse('mentions-legales.php'), '0.2'],
    [$adresse('politique-confidentialite.php'), '0.2'],
];
foreach ($pdo->query("SELECT slug FROM espaces WHERE disponible = 1 AND slug <> '' ORDER BY id") as $row) {
    $urls[] = [$adresse('espace.php', ['slug' => $row['slug']]), '0.7'];
}
foreach ($pdo->query("SELECT slug FROM activites WHERE slug <> '' ORDER BY id") as $row) {
    $urls[] = [$adresse('detail-activite.php', ['slug' => $row['slug']]), '0.6'];
}
foreach ($pdo->query("SELECT id FROM formations WHERE actif = 1 ORDER BY id") as $row) {
    $urls[] = [$adresse('formation.php', ['id' => (int)$row['id']]), '0.6'];
}
foreach ($pdo->query("SELECT id FROM personnalites ORDER BY id") as $row) {
    $urls[] = [$adresse('personnalite.php', ['id' => (int)$row['id']]), '0.5'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $priorite]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><priority>' . $priorite . "</priority></url>\n";
}
echo "</urlset>\n";
