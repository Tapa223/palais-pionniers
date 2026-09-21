# Palais des Pionniers de Magnambougou — Plateforme de gestion

Plateforme officielle de réservation et de gestion des espaces, activités et
hébergements du Palais des Pionniers de Magnambougou.
Ministère de la Jeunesse et des Sports, chargé de l'Instruction Civique et de
la Construction Citoyenne — République du Mali.

---

## ⚙️ Stack technique

| Composant | Choix |
|---|---|
| Langage serveur | PHP 8.0+ |
| Accès base de données | PDO (requêtes préparées systématiques) |
| Base de données | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | HTML5 + Tailwind CSS (CDN) + JavaScript natif (aucun framework) |
| Typographie | Inter (texte courant) + Plus Jakarta Sans (titres) |
| Serveur cible | Linux + Apache — XAMPP/WAMP en local |
| Paiement | 100 % physique au guichet (espèces, Orange Money, Moov Money) — aucune API de paiement en ligne actuellement |

Choix assumé : pas de framework PHP (Laravel, Symfony...) — code procédural
structuré par page, avec des fonctions utilitaires partagées dans
`includes/auth.php`. Décision maintenue volontairement pour rester simple à
maintenir et héberger sur une infrastructure institutionnelle standard.

---

## 📁 Structure du projet

```
palais-pionniers/
├── config/
│   └── database.php          # Connexion PDO (singleton db())
├── includes/
│   ├── auth.php               # Sessions, rôles, CSRF, notify(), tarif_disponible()...
│   ├── header.php             # Header + nav du site public
│   └── footer.php
├── admin/
│   ├── _admin_header.php      # Layout back-office (sidebar fixe, tous rôles)
│   ├── _admin_footer.php
│   ├── dashboard.php          # Statistiques, filtrées par rôle
│   ├── espaces.php            # CRUD espaces + tarifs (créneau/séjour, inventaire, bail)
│   ├── activites.php / admin-ajout-activite.php / admin-galerie-activite.php
│   ├── reservations.php       # Validation / refus des demandes
│   ├── paiements.php          # Encaissement, réductions tracées
│   ├── guichet.php            # Enregistrement d'un client physique
│   ├── messages.php           # Boîte de contact
│   ├── observations.php       # Notes de suivi (tous rôles admin)
│   ├── notifications.php      # Centre de notifications internes
│   ├── users.php              # Gestion des comptes (superadmin uniquement)
│   ├── activity.php           # Journal d'audit
│   └── export.php             # Export CSV (paiements, réservations)
├── database/
│   ├── palais_pionniers.sql          # ⭐ Script d'installation complet (base unique, à jour)
│   ├── migration_admin_dg.sql        # Migration pour une base déjà en prod (rôle admin_dg)
│   └── migration_phase2_hebergement.sql # Migration hébergement/inventaire/réductions
├── index.php, espaces.php, espace.php, activites.php, detail-activite.php,
│   a-propos.php, contact.php         # Site public
├── login.php, register.php, logout.php, mon-compte.php
├── reserver.php, traitement-reservation.php  # Parcours de réservation client
└── generer_bon.php            # Bon de réservation → reçu de paiement (même document, 2 états)
```

---

## 🚀 Installation

### Nouvelle installation (base vierge)

1. Copier les fichiers dans `htdocs/palais-pionniers/` (XAMPP) ou équivalent.
2. phpMyAdmin → **Importer** → sélectionner **`database/palais_pionniers.sql`** → Exécuter.
   C'est le **script unique et à jour** : il crée toutes les tables, les 16 espaces
   officiels avec leur grille tarifaire réelle, les rôles, et les comptes admin.
3. Adapter `config/database.php` si besoin (identifiants MySQL).
4. Démarrer Apache + MySQL, accéder à `http://localhost/palais-pionniers/`.

### Base déjà en production (mise à jour)

Ne **jamais** réimporter `palais_pionniers.sql` sur une base contenant déjà des
données réelles (ça écraserait tout). Exécuter dans l'ordre, une seule fois :
1. `database/migration_admin_dg.sql`
2. `database/migration_phase2_hebergement.sql`

---

## 🔐 Comptes par défaut (mot de passe : `ChangeMoi@2026`)

| Rôle | Email | Périmètre |
|---|---|---|
| `superadmin` | superadmin@palaisdespionniers.ml | Contrôle technique total, gestion des comptes |
| `admin_dg` | dg@palaisdespionniers.ml | Vue complète sur tout, lecture seule, observations, exports |
| `ministre` | ministre@palaisdespionniers.ml | Même périmètre que Admin DG |
| `admin_espaces` | admin.espaces@palaisdespionniers.ml | Espaces, réservations (valider/refuser), guichet |
| `admin_activites` | admin.activites@palaisdespionniers.ml | Activités, galerie |
| `admin_messages` | admin.messages@palaisdespionniers.ml | Boîte de contact |
| `admin_comptable` | comptable@palaisdespionniers.ml | Paiements, guichet, exports |

> ⚠️ Mots de passe à changer en production. Les comptes admin ne peuvent
> jamais réserver comme un client (bloqué serveur + interface).

---

## 🔄 Circuit de réservation (sans paiement en ligne)

1. **Client réserve en ligne** (créneau horaire ou séjour en nuitées selon l'espace)
   → `admin_espaces` notifié.
2. **`admin_espaces` valide ou refuse** → si validé, le **bon de réservation**
   est immédiatement disponible côté client (statut "à payer au guichet"),
   et `admin_comptable` est notifié.
3. **Le client se présente au guichet** et règle en espèces / Mobile Money.
4. **`admin_comptable` encaisse** — montant, mode, référence, réduction
   éventuelle (motif obligatoire, notifié à l'Admin DG et au Ministre) → le
   même document devient automatiquement un **reçu de paiement**.
5. **`admin_espaces` est notifié en retour** (paiement reçu, plus de conflit
   possible sur le créneau).

**Cas guichet** : un admin peut aussi enregistrer un client venu sur place —
la réservation est alors auto-validée (l'étape 2 est sautée).

### Hébergement (séjour en nuitées)

Le Centre Tidiani Coulibaly dit Necker (27 chambres) et la Résidence Ely
Abdoulaye Diallo (26 chambres) fonctionnent comme un vrai inventaire
d'hôtel : chaque tarif porte une quantité d'unités disponibles, et le
système ne refuse une réservation que lorsque toutes les chambres d'un type
donné sont prises sur la période demandée — jamais avant.

### Tarifs "bail"

Les locations longue durée (`est_bail = 1`) sont affichées à titre
informatif avec un bouton **"Nous contacter"** — jamais réservables en ligne,
car négociées directement avec l'administration.

---

## ✅ Fonctionnalités

### Site public
- Catalogue d'espaces filtrable, avec descriptions réelles et galeries photo
- Réservation en ligne (créneau ou séjour selon l'espace), anti-conflit avec
  gestion d'inventaire
- Suivi des réservations et téléchargement du bon/reçu depuis **Mon compte**
- Section **Services & Prestations** (support publicitaire, lavage auto/moto)
- Formulaire de contact

### Back-office (7 rôles, périmètres cloisonnés)
- Dashboard avec statistiques filtrées par rôle
- CRUD espaces (mode créneau/séjour, inventaire, tarifs multiples, flag bail)
- Validation/refus des réservations, guichet (enregistrement physique)
- Paiements avec traçabilité complète des réductions accordées
- Export CSV (paiements, réservations) pour la comptabilité et la supervision
- Observations (notes internes) et journal d'audit
- Notifications internes croisées entre rôles à chaque étape clé

---

## 🛡️ Sécurité

- Requêtes préparées PDO systématiques
- `password_hash` (bcrypt) pour tous les mots de passe
- Jetons CSRF sur tous les formulaires POST
- Contrôle d'accès par rôle sur chaque page (`require_role`, `require_admin`,
  `require_client`, `is_readonly_admin`)
- Séparation stricte comptes admin / comptes clients (impossible pour un
  admin de réserver comme un client, y compris en tapant l'URL directement)
- Validation MIME réelle sur les uploads d'images (pas seulement l'extension)

---

## 💳 Évolution future : paiement en ligne

Le circuit actuel est physique par choix, pour livrer un socle propre et
fiable avant d'ajouter la complexité d'une API de paiement. Le jour où
Orange Money / Moov Money seront intégrés, seule l'étape d'encaissement
manuel (`admin/paiements.php`) sera remplacée par un webhook qui exécutera
la même écriture en base — tout le reste du circuit (notifications, bon qui
devient reçu, traçabilité) reste inchangé.

---
© Palais des Pionniers — Magnambougou, Bamako, Mali
