# Palais des Pionniers de Magnambougou

Plateforme de réservation et de gestion des espaces, activités et hébergements du Palais des Pionniers de Magnambougou (Ministère de la Jeunesse et des Sports, chargé de l'Instruction Civique et de la Construction Citoyenne, Mali).

## Technique

- PHP 8.0 ou plus, sans framework, PDO avec requêtes préparées
- MariaDB 10.3 ou plus (les migrations utilisent `ADD COLUMN IF NOT EXISTS`)
- Tailwind CSS compilé, Font Awesome, FPDF et Luminous embarqués (aucun CDN)
- Apache avec `AllowOverride All` (les fichiers `.htaccess` protègent les dossiers sensibles)
- Paiements enregistrés au guichet (espèces, Orange Money, Moov Money)

## Structure

- `config/` : connexion à la base (`database.php`) et réglages
- `includes/` : fonctions communes (`auth.php`), en-tête et pied de page publics
- `admin/` : administration (réservations, paiements, réquisitions, espaces, activités, utilisateurs, exports)
- `database/` : migrations SQL à exécuter dans phpMyAdmin
- `uploads/`, `assets/images/` : photos envoyées depuis l'administration
- Racine : pages publiques, espace client (`mon-compte.php`), réservation (`reserver.php`, `traitement-reservation.php`), bons et reçus (`generer_bon.php`)

## Mise en ligne

1. Envoyer les fichiers sur l'hébergement, sans le dossier `.git` ni les sauvegardes `.sql` de données.
2. Créer la base, puis importer la sauvegarde de la base de travail depuis phpMyAdmin.
3. Exécuter les migrations qui n'ont pas encore été passées, en particulier `database/migration_securite_mise_en_ligne.sql`.
4. Copier `config/database.local.exemple.php` en `config/database.local.php` et y mettre les identifiants de la base. Ce fichier n'est jamais versionné.
5. Activer HTTPS sur le domaine.
6. Se connecter avec chaque compte d'administration et changer son mot de passe (« Mon mot de passe »). Un compte qui utilise encore un mot de passe initial est obligé de le changer à la connexion.

Ne jamais réimporter `database/palais_pionniers.sql` sur une base qui contient des données réelles.

## Circuit de réservation

1. Le client réserve en ligne (créneau horaire ou séjour selon l'espace).
2. L'administration des espaces valide ou refuse. Une fois validée, le bon de réservation est disponible dans l'espace client.
3. Le client règle au guichet, le comptable encaisse (acompte ou totalité, réduction motivée si besoin). Le bon devient un reçu.
4. Une réservation validée non payée dans le délai expire automatiquement.

Le guichet permet aussi d'enregistrer directement un client venu sur place.

## Sécurité

- Requêtes préparées partout, mots de passe en `password_hash`
- Jeton CSRF sur tous les formulaires
- Rôles vérifiés côté serveur à chaque page, rôle relu en base
- Cookies de session HttpOnly et SameSite, en-têtes de sécurité, déconnexion après 2 heures d'inactivité
- Limitation des tentatives de connexion
- Photos contrôlées sur leur contenu réel, aucun script exécutable dans les dossiers d'images
