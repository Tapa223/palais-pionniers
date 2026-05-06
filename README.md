# Palais des Pionniers — Plateforme PHP/MySQL

Plateforme officielle de gestion des espaces et réservations du Palais des Pionniers de Magnambougou.

## ⚙️ Stack technique
- **Backend** : PHP 8.0+ avec PDO
- **Base de données** : MySQL 5.7+ / MariaDB 10.3+
- **Frontend** : HTML5 + Tailwind CSS (via CDN)
- **Serveur cible** : Linux + Apache (XAMPP / WAMP en local)

## 📁 Structure
```
palais-php/
├── config/
│   └── database.php         # Connexion PDO
├── includes/
│   ├── auth.php             # Sessions, CSRF, helpers
│   ├── header.php           # Header public
│   └── footer.php           # Footer public
├── admin/
│   ├── _admin_header.php    # Layout admin (sidebar)
│   ├── _admin_footer.php
│   ├── dashboard.php        # Stats + dernières demandes
│   ├── reservations.php     # Validation/refus
│   ├── espaces.php          # CRUD espaces
│   ├── tarifs.php           # CRUD tarifs
│   └── users.php            # Modération comptes
├── index.php                # Accueil
├── espaces.php              # Catalogue + filtres
├── espace.php               # Détail d'un espace
├── activites.php
├── a-propos.php
├── contact.php
├── register.php             # Inscription
├── login.php                # Connexion
├── logout.php
├── reserver.php             # Formulaire de réservation
├── mon-compte.php           # Suivi des demandes
└── database.sql             # Script complet d'initialisation
```

## 🚀 Installation (XAMPP / WAMP)

1. **Copier les fichiers** dans `htdocs/palais-pionniers/` (XAMPP) ou `www/palais-pionniers/` (WAMP).

2. **Créer la base** : ouvrez phpMyAdmin → onglet *Importer* → sélectionnez `database.sql` → *Exécuter*.
   La base `palais_pionniers` est créée avec toutes les tables, données officielles et le compte admin.

3. **Adapter la config** dans `config/database.php` si besoin :
   ```php
   const DB_HOST = '127.0.0.1';
   const DB_USER = 'root';
   const DB_PASS = '';        // XAMPP par défaut : vide
   ```

4. **Démarrer Apache + MySQL** dans le panneau XAMPP/WAMP.

5. **Accéder** : http://localhost/palais-pionniers/

> ⚠️ Les chemins absolus utilisés dans le HTML (ex: `/login.php`) supposent que l'application est servie à la racine.
> Si vous l'installez dans un sous-dossier, configurez un VirtualHost ou adaptez les liens.

## 🔐 Compte administrateur par défaut
- **Email**    : `admin@palaisdespionniers.ml`
- **Mot de passe** : `Admin@2026`

> ⚠️ **Changez ce mot de passe immédiatement en production** depuis la base ou via une page dédiée.

## ✅ Fonctionnalités
### Côté utilisateur
- Inscription / connexion sécurisée (mots de passe `password_hash` bcrypt)
- Catalogue d'espaces avec filtres par catégorie + recherche
- Page détail avec tarifs officiels et équipements
- Formulaire de réservation (date, horaires, motif)
- **Anti-conflit** : impossible de réserver un créneau qui chevauche une réservation `en_attente` ou `validée`
- Espace **Mon compte** pour suivre les statuts (En attente / Validée / Refusée / Annulée)

### Côté administration
- Dashboard avec statistiques (en attente, validées, espaces dispo, utilisateurs)
- Validation / refus des demandes avec note administrateur
- CRUD complet sur les espaces
- CRUD complet sur les tarifs (multi-tarifs par espace)
- Modération des comptes : blocage/déblocage, promotion admin

## 🛡️ Sécurité
- Requêtes préparées **PDO** systématiques (anti-injection SQL)
- Hachage `password_hash` (bcrypt) pour les mots de passe
- Jetons **CSRF** sur tous les formulaires POST
- `session_regenerate_id()` à la connexion
- Cookies de session `HttpOnly` + `SameSite=Lax`
- Échappement HTML systématique via `e()`
- Contrôle d'accès par rôle (`require_login`, `require_admin`)

## 💳 Évolution Mobile Money (préparation)
La table `reservations` peut être étendue avec :
```sql
ALTER TABLE reservations
  ADD COLUMN paiement_statut ENUM('non_paye','en_cours','paye') DEFAULT 'non_paye',
  ADD COLUMN paiement_ref    VARCHAR(100) NULL,
  ADD COLUMN paiement_method ENUM('orange_money','moov_money','especes') NULL;
```
L'intégration des API Orange Money / Moov Money pourra alors se faire dans un script `api/payment_callback.php`.

## 📝 Données officielles intégrées
| Espace | Tarif |
|---|---|
| Salle Seydou BADIAN (400 places) | 400 000 F CFA / jour |
| Salle Sony Bounian KOITA (420 places) | 400 000 F CFA / jour |
| Salle Pr Amadou Seydou TRAORÉ (80 places) | 150 000 F CFA / jour |
| Terrain de Maracana | 10 000 F CFA / heure entr. — 20 000 F CFA / match gala |
| Centre Tidiani COULIBALY | 5 000 à 10 000 F CFA / nuitée |
| Piscine | 250 000 F CFA / jour événement |

---
© Palais des Pionniers — Magnambougou, Bamako, Mali
