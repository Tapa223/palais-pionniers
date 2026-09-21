-- =====================================================================
--  PALAIS DES PIONNIERS DE MAGNAMBOUGOU
--  Script d'installation de la base de données
-- =====================================================================
--  Plateforme de gestion des espaces, activités et réservations
--  Ministère de la Jeunesse et des Sports — République du Mali
--
--  Moteur      : MySQL 5.7+ / MariaDB 10.3+
--  Encodage    : utf8mb4 (support complet Unicode)
--  Généré le   : 07/07/2026
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

CREATE DATABASE IF NOT EXISTS `palais_pionniers`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `palais_pionniers`;

-- =====================================================================
--  SECTION 1 — UTILISATEURS & AUTHENTIFICATION
-- =====================================================================

--
-- Table `users`
-- Comptes publics et comptes d'administration (multi-rôles).
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom_complet`   VARCHAR(200) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `telephone`     VARCHAR(30)  DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('user','superadmin','ministre','admin_espaces','admin_activites','admin_messages','admin_comptable')
                  NOT NULL DEFAULT 'user'
                  COMMENT 'user = client public ; admin_dg = Direction Générale (vue complète, lecture seule) ; les autres valeurs sont les rôles d''administration opérationnelle',
  `actif`         TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Blocage de compte (0 = suspendu)',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Comptes utilisateurs publics et administrateurs';

-- =====================================================================
--  SECTION 2 — CATALOGUE : CATÉGORIES, ESPACES & TARIFS
-- =====================================================================

--
-- Table `categories`
-- Familles d'espaces (Amphithéâtre, Sport, Hébergement, ...).
--
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`  VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Catégories de classement des espaces';

--
-- Table `espaces`
-- Salles, terrains et infrastructures réservables du Palais.
--
DROP TABLE IF EXISTS `espaces`;
CREATE TABLE `espaces` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`              VARCHAR(200) NOT NULL,
  `slug`             VARCHAR(200) NOT NULL,
  `categorie_id`     INT UNSIGNED NOT NULL,
  `capacite`         VARCHAR(100) DEFAULT NULL,
  `description`      TEXT DEFAULT NULL,
  `equipements`      TEXT DEFAULT NULL,
  `mode_reservation` ENUM('creneau','sejour') NOT NULL DEFAULT 'creneau'
                     COMMENT 'creneau = date + heure début/fin (salles, terrains) ; sejour = arrivée/départ en nuitées (hébergement)',
  `option_petit_dejeuner` TINYINT(1) NOT NULL DEFAULT 0
                     COMMENT 'Propose un supplément petit-déjeuner au moment de la réservation (uniquement pour les espaces séjour qui le prévoient réellement)',
  `option_vip`       TINYINT(1) NOT NULL DEFAULT 0
                     COMMENT 'Propose un supplément VIP (espace/accueil réservé aux personnalités) au moment de la réservation',
  `prix_vip`         DECIMAL(12,2) DEFAULT NULL COMMENT 'Montant du supplément VIP, si option_vip actif',
  `gerant_externe`   VARCHAR(255) DEFAULT NULL
                     COMMENT 'Si renseigné, cet espace est loué/géré par un tiers — la réservation en ligne est désactivée. Sert de texte de présentation de la gestion.',
  `gerant_nom`       VARCHAR(100) DEFAULT NULL COMMENT 'Nom du gestionnaire externe',
  `gerant_prenom`    VARCHAR(100) DEFAULT NULL COMMENT 'Prénom du gestionnaire externe',
  `gerant_email`     VARCHAR(150) DEFAULT NULL COMMENT 'Email du gestionnaire externe',
  `type_bail`        ENUM('mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'mensuel'
                     COMMENT 'Périodicité du contrat de bail — détermine la fréquence attendue des paiements',
  `gerant_contact`   VARCHAR(100) DEFAULT NULL
                     COMMENT 'Téléphone du gestionnaire externe de cet espace',
  `gerant_user_id`   INT UNSIGNED DEFAULT NULL
                     COMMENT 'Compte client lié au bail, pour l''onglet "Mes Baux" — distinct des champs texte gerant_*',
  `resiliation_demandee`    TINYINT(1) NOT NULL DEFAULT 0,
  `resiliation_demandee_le` TIMESTAMP NULL DEFAULT NULL,
  `resiliation_note`        TEXT DEFAULT NULL COMMENT 'Motif donné par le client à la demande de résiliation',
  `groupe_batiment`  VARCHAR(150) DEFAULT NULL
                     COMMENT 'Nom du bâtiment si cet espace fait partie d''un ensemble (ex: "Daba Modibo Keita") — permet de proposer le bail groupé des espaces du même bâtiment',
  `disponible`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_espaces_cat` (`categorie_id`),
  CONSTRAINT `fk_espaces_cat` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Espaces réservables (salles, terrains, hébergement, etc.)';

--
-- Table `espace_images`
-- Galerie photo associée à chaque espace.
--
DROP TABLE IF EXISTS `espace_images`;
CREATE TABLE `espace_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `espace_id`  INT UNSIGNED NOT NULL,
  `chemin`     VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_images_espace` (`espace_id`),
  CONSTRAINT `fk_images_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Photos complémentaires des espaces';

--
-- Table `tarifs`
-- Grille tarifaire officielle (un espace peut avoir plusieurs tarifs).
--
DROP TABLE IF EXISTS `tarifs`;
CREATE TABLE `tarifs` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `espace_id`           INT UNSIGNED NOT NULL,
  `libelle`             VARCHAR(200) NOT NULL,
  `montant`             DECIMAL(12,2) NOT NULL COMMENT 'Montant en F CFA',
  `unite`               ENUM('heure','demi-journee','jour','mois','match','nuitée','événement','seance','personne_jour','personne_mois','personne_an','activite','support')
                        NOT NULL DEFAULT 'jour',
  `quantite_disponible` INT UNSIGNED DEFAULT NULL
                        COMMENT 'Nombre d''unités identiques disponibles (ex: 7 chambres) — NULL = unité unique (comportement classique, un seul créneau à la fois)',
  `est_bail`            TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Location longue durée négociée directement (bail) — affiché à titre informatif, jamais réservable en ligne',
  `gerant_nom`          VARCHAR(150) DEFAULT NULL COMMENT 'Nom du gestionnaire à contacter pour ce tarif en bail',
  `gerant_contact`      VARCHAR(100) DEFAULT NULL COMMENT 'Téléphone/contact du gestionnaire pour ce tarif en bail',
  PRIMARY KEY (`id`),
  KEY `idx_tarifs_espace` (`espace_id`),
  CONSTRAINT `fk_tarifs_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Grille tarifaire officielle par espace';

--
-- Table `services_annexes`
-- Prestations annexes du Palais (support publicitaire, lavage auto/moto...)
-- affichées à titre informatif sur le site public, jamais réservables en
-- ligne — le visiteur est orienté vers le formulaire de contact.
--
DROP TABLE IF EXISTS `services_annexes`;
CREATE TABLE `services_annexes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `montant`     DECIMAL(12,2) NOT NULL COMMENT 'Montant en F CFA',
  `unite`       VARCHAR(50) NOT NULL DEFAULT 'mois',
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Prestations annexes informationnelles (contact direct, non réservables)';

--
-- Table `demandes_services`
-- Demande tracée d'un service annexe, réservée aux comptes connectés
-- (au lieu d'un simple message de contact) — pour digitaliser et tracer
-- ces demandes.
--
--
-- Table `jeunes_engages`
-- Base des jeunes ayant manifesté leur intérêt à s'engager auprès du
-- Palais (bouton "S'engager" sur l'accueil) — mobilisation future.
--
--
-- Table `requisitions_ministerielles`
-- Réquisition d'un espace déjà réservé, pour un besoin institutionnel
-- prioritaire. Déclenchée par Superadmin/Comptable, le client choisit
-- ensuite en ligne comment il souhaite être dédommagé.
--
DROP TABLE IF EXISTS `requisitions_ministerielles`;
CREATE TABLE `requisitions_ministerielles` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id`   INT UNSIGNED NOT NULL,
  `motif`            TEXT NOT NULL,
  `declenche_par`    INT UNSIGNED NOT NULL,
  `date_declenchee`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `choix_client`     ENUM('remboursement','nouvelle_date','autre_espace') DEFAULT NULL,
  `details_choix`    TEXT DEFAULT NULL COMMENT 'Précisions du client selon son choix (date souhaitée, espace souhaité...)',
  `date_choix`       TIMESTAMP NULL DEFAULT NULL,
  `statut`           ENUM('en_attente_choix','traite') NOT NULL DEFAULT 'en_attente_choix',
  `traite_par`       INT UNSIGNED DEFAULT NULL,
  `date_traitement`  TIMESTAMP NULL DEFAULT NULL,
  `note_traitement`  TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_requisition_reservation` (`reservation_id`),
  CONSTRAINT `fk_requisition_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Réquisitions ministérielles/institutionnelles d''un espace déjà réservé';

DROP TABLE IF EXISTS `jeunes_engages`;
CREATE TABLE `jeunes_engages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`             VARCHAR(150) NOT NULL,
  `prenom`          VARCHAR(150) DEFAULT NULL,
  `age`             TINYINT UNSIGNED DEFAULT NULL,
  `date_naissance`  DATE DEFAULT NULL,
  `telephone`       VARCHAR(30) NOT NULL,
  `email`           VARCHAR(190) DEFAULT NULL,
  `commune`         VARCHAR(150) DEFAULT NULL,
  `profession`      VARCHAR(150) DEFAULT NULL COMMENT 'Étudiant, travailleur, sans emploi...',
  `domaine_interet` VARCHAR(150) DEFAULT NULL,
  `motivation`      TEXT DEFAULT NULL,
  `statut`          ENUM('nouveau','contacte') NOT NULL DEFAULT 'nouveau',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Base des jeunes intéressés à s''engager auprès du Palais';

DROP TABLE IF EXISTS `demandes_services`;
CREATE TABLE `demandes_services` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `service_id`      INT UNSIGNED NOT NULL,
  `message`         TEXT DEFAULT NULL,
  `statut`          ENUM('en_attente','traitee') NOT NULL DEFAULT 'en_attente',
  `traite_par`      INT UNSIGNED DEFAULT NULL,
  `date_traitement` TIMESTAMP NULL DEFAULT NULL,
  `note_traitement` TEXT DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_demande_service_user` (`user_id`),
  KEY `fk_demande_service_service` (`service_id`),
  CONSTRAINT `fk_demande_service_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_demande_service_service` FOREIGN KEY (`service_id`) REFERENCES `services_annexes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Demandes tracées de services annexes, réservées aux comptes connectés';

--
-- Table `bail_paiements`
-- Suivi des loyers mensuels des espaces loués à un tiers (bail). Une ligne
-- = un mois encaissé pour un espace donné. L'absence de ligne pour un mois
-- donné = loyer non encore payé pour ce mois.
--
DROP TABLE IF EXISTS `bail_paiements`;
CREATE TABLE `bail_paiements` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `espace_id`      INT UNSIGNED NOT NULL,
  `periode_debut`  DATE NOT NULL COMMENT 'Premier jour de la période couverte par ce paiement',
  `duree_mois`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Durée couverte en mois (1=mensuel, 3=trimestriel, 6=semestriel, 12=annuel)',
  `montant`        DECIMAL(12,2) NOT NULL COMMENT 'Loyer réellement encaissé pour cette période, en F CFA',
  `montant_reference` DECIMAL(12,2) DEFAULT NULL COMMENT 'Loyer attendu selon le tarif, avant réduction',
  `motif_reduction`   TEXT DEFAULT NULL COMMENT 'Raison de la réduction accordée, si montant < montant_reference',
  `mode`           ENUM('especes','orange_money','moov_money','virement','cheque') NOT NULL,
  `reference`      VARCHAR(100) DEFAULT NULL,
  `note`           TEXT DEFAULT NULL,
  `enregistre_par` INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_espace_periode` (`espace_id`, `periode_debut`),
  KEY `fk_bail_paiement_user` (`enregistre_par`),
  CONSTRAINT `fk_bail_paiement_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bail_paiement_user` FOREIGN KEY (`enregistre_par`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Suivi des loyers des espaces en bail, selon la périodicité de chaque contrat';

--
-- Table `formations`
-- Formations dispensées au Palais (ex: Savonnerie, Informatique), gérées
-- par admin_activites, affichées sur une page publique dédiée.
--
DROP TABLE IF EXISTS `formations`;
CREATE TABLE `formations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `duree`       VARCHAR(150) DEFAULT NULL COMMENT 'Ex: 3 mois, 2 semaines...',
  `public_cible`VARCHAR(200) DEFAULT NULL COMMENT 'Ex: Jeunes 15-25 ans, Femmes...',
  `photo`       VARCHAR(255) DEFAULT NULL,
  `contact`     VARCHAR(150) DEFAULT NULL,
  `date_fin`    DATE DEFAULT NULL COMMENT 'Si dépassée, la formation reste visible mais affiche un badge Terminée',
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  `ordre`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Formations dispensées au sein du Palais';

--
-- Table `direction`
-- Ministre, DG, DGA — affichés sur l'accueil et À propos. Gérée par
-- admin_dg / superadmin (contenu institutionnel sensible).
--
DROP TABLE IF EXISTS `direction`;
CREATE TABLE `direction` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_key`  ENUM('ministre','dg','dga') NOT NULL,
  `nom`       VARCHAR(200) NOT NULL,
  `titre`     VARCHAR(255) DEFAULT NULL,
  `citation`  VARCHAR(500) DEFAULT NULL COMMENT 'Phrase de mise en avant',
  `texte`     TEXT DEFAULT NULL COMMENT 'Paragraphe complet',
  `photo`     VARCHAR(255) DEFAULT NULL COMMENT 'Si vide, un avatar avec initiales est affiché',
  `ordre`     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_role_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Ministre, DG, DGA — contenu affiché sur accueil et à propos';

INSERT INTO `direction` (`role_key`, `nom`, `titre`, `citation`, `texte`, `photo`, `ordre`) VALUES
('ministre', 'M. Abdoul Kassim Ibrahim Fomba', 'Ministre de la Jeunesse et des Sports, chargé de l''Instruction Civique et de la Construction Citoyenne',
 'Le Palais est le levier stratégique de notre politique de construction citoyenne.',
 'Sous l''impulsion des plus hautes autorités, nous avons confié au Palais des Pionniers la mission noble de transformer notre jeunesse en un rempart inébranlable contre l''incivisme. C''est ici que s''enseigne l''amour sacré de la patrie.',
 'minis.jpeg', 1),
('dg', 'Mr Sidi Dicko', 'Directeur Général du Palais des Pionniers',
 'Nous transformons la vision nationale en actes concrets pour la patrie.',
 'En notre qualité d''EPST, nous œuvrons quotidiennement à produire de la compétence citoyenne. Le Palais est une véritable usine à bâtisseurs de nation, garantissant que chaque parcours soit une pierre solide à l''édifice du Mali souverain.',
 'dg.jpeg', 2),
('dga', 'Nouhoum Chérif HAÏDARA', 'Directeur Général Adjoint du Palais des Pionniers',
 'Une jeunesse formée, c''est une nation qui avance.',
 'Jeune, rigoureusement formé et pleinement dévoué à la mission du Palais, le Directeur Général Adjoint incarne cette nouvelle génération de cadres maliens qui allient exigence académique et engagement de terrain, au service quotidien de la jeunesse pionnière.',
 NULL, 3);

-- =====================================================================
--  SECTION 3 — ACTIVITÉS
-- =====================================================================

--
-- Table `activites`
-- Programmes et activités du Palais (fiches de présentation publique).
--
DROP TABLE IF EXISTS `activites`;
CREATE TABLE `activites` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`               VARCHAR(200) NOT NULL,
  `slug`              VARCHAR(200) NOT NULL,
  `description`       TEXT DEFAULT NULL,
  `icone`             VARCHAR(100) DEFAULT NULL,
  `image_principale`  VARCHAR(255) DEFAULT NULL,
  `sous_titre`        VARCHAR(300) DEFAULT NULL,
  `missions`          TEXT DEFAULT NULL COMMENT 'Contenu structuré (JSON) de la section missions',
  `chiffres_cles`     TEXT DEFAULT NULL COMMENT 'Contenu structuré (JSON) des statistiques affichées',
  `citation`          TEXT DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Fiches des activités proposées par le Palais';

--
-- Table `activite_galerie`
-- Galerie photo associée à chaque activité — chaque photo peut être liée
-- à une section précise (section_id), ou rester libre (photo générale).
--
DROP TABLE IF EXISTS `activite_galerie`;
CREATE TABLE `activite_galerie` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activite_id` INT UNSIGNED NOT NULL,
  `section_id`  INT UNSIGNED DEFAULT NULL,
  `image_path`  VARCHAR(255) NOT NULL,
  `categorie`   VARCHAR(100) DEFAULT NULL,
  `legende`     VARCHAR(255) DEFAULT NULL,
  `ordre`       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_activite_galerie_parent` (`activite_id`),
  KEY `fk_activite_galerie_section` (`section_id`),
  CONSTRAINT `fk_activite_galerie_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Photos complémentaires des activités';

--
-- Table `activite_sections`
-- Blocs de contenu libres pour une fiche activité — un titre et/ou un
-- texte, entièrement facultatifs. N'apparaît sur la fiche publique que
-- si au moins un champ est rempli. Remplace l'ancien format rigide
-- "missions" (texte à séparateurs). Permet à chaque activité d'avoir
-- exactement les sections dont elle a besoin, ni plus ni moins.
--
DROP TABLE IF EXISTS `activite_sections`;
CREATE TABLE `activite_sections` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activite_id` INT UNSIGNED NOT NULL,
  `ordre`       INT NOT NULL DEFAULT 0,
  `titre`       VARCHAR(200) DEFAULT NULL,
  `texte`       TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activite_sections_parent` (`activite_id`),
  CONSTRAINT `fk_activite_sections_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Sections de contenu libres et facultatives pour une fiche activité';

--
-- Table `activite_liens`
-- Liens externes complémentaires (ex: page Facebook de l'événement,
-- site du partenaire). Référencée dans le code depuis le début mais
-- jamais créée — corrigé ici.
--
DROP TABLE IF EXISTS `activite_liens`;
CREATE TABLE `activite_liens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activite_id` INT UNSIGNED NOT NULL,
  `titre`       VARCHAR(150) NOT NULL,
  `url`         VARCHAR(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activite_liens_parent` (`activite_id`),
  CONSTRAINT `fk_activite_liens_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Liens externes complémentaires pour une activité';

--
-- Table `formation_galerie`
-- Photos complémentaires d'une formation, affichées en carrousel sur sa
-- fiche détail (en plus de la photo principale de la carte).
--
DROP TABLE IF EXISTS `formation_galerie`;
CREATE TABLE `formation_galerie` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation_id` INT UNSIGNED NOT NULL,
  `image_path`   VARCHAR(255) NOT NULL,
  `legende`      VARCHAR(255) DEFAULT NULL,
  `ordre`        INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_formation_galerie_parent` (`formation_id`),
  CONSTRAINT `fk_formation_galerie_parent` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Photos complémentaires des formations, pour la fiche détail';

--
-- Table `personnalites`
-- Figures historiques/institutionnelles dont les espaces portent le nom
-- (ex: Seydou Badian, Ely Abdoulaye Diallo...). Gérée par admin_activites,
-- affichée sur une page publique dédiée liée depuis l'accueil.
--
DROP TABLE IF EXISTS `personnalites`;
CREATE TABLE `personnalites` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`        VARCHAR(150) NOT NULL,
  `prenom`     VARCHAR(150) DEFAULT NULL,
  `titre`      VARCHAR(255) DEFAULT NULL COMMENT 'Ex: Écrivain, Homme politique, ancien Ministre...',
  `photo`      VARCHAR(255) DEFAULT NULL,
  `parcours`   TEXT DEFAULT NULL COMMENT 'Biographie / parcours',
  `espace_id`  INT UNSIGNED DEFAULT NULL COMMENT 'Espace du Palais qui porte son nom, si applicable',
  `ordre`      INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_personnalite_espace` (`espace_id`),
  CONSTRAINT `fk_personnalite_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Figures dont les espaces du Palais portent le nom';

--
-- Table `personnel`
-- Membres du personnel du Palais (nom, prénom, poste, photo), affichés en
-- carrousel circulaire en bas de la page À propos. Distinct des Icônes
-- (personnalités historiques) — ici c'est l'équipe actuelle.
--
DROP TABLE IF EXISTS `personnel`;
CREATE TABLE `personnel` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`     VARCHAR(150) NOT NULL,
  `prenom`  VARCHAR(150) DEFAULT NULL,
  `poste`   VARCHAR(200) DEFAULT NULL,
  `photo`   VARCHAR(255) DEFAULT NULL,
  `ordre`   INT UNSIGNED NOT NULL DEFAULT 0,
  `actif`   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Membres du personnel du Palais, affichés en carrousel sur À propos';

-- =====================================================================
--  SECTION 4 — RÉSERVATIONS & PAIEMENTS (circuit physique)
-- =====================================================================

--
-- Table `reservations`
-- Demandes de réservation d'espace, en ligne ou saisies au guichet.
--
DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `espace_id`        INT UNSIGNED NOT NULL,
  `tarif_id`         INT UNSIGNED DEFAULT NULL,
  `date_resa`        DATE NOT NULL COMMENT 'Date du créneau, ou date d''arrivée pour un séjour',
  `date_depart`      DATE DEFAULT NULL COMMENT 'Date de départ — uniquement pour les espaces en mode séjour (nuitées)',
  `date_validation`  DATETIME DEFAULT NULL COMMENT 'Horodatage de la validation par admin_espaces — sert au calcul du délai de 48h avant expiration automatique',
  `heure_debut`      TIME DEFAULT NULL COMMENT 'NULL pour les réservations en mode séjour',
  `heure_fin`        TIME DEFAULT NULL COMMENT 'NULL pour les réservations en mode séjour',
  `petit_dejeuner`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Option petit-déjeuner (hébergement uniquement)',
  `canal`            ENUM('en_ligne','guichet') NOT NULL DEFAULT 'en_ligne' COMMENT 'Réservation faite en ligne par le client, ou saisie directement au guichet par un admin',
  `vip`              TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Option accueil VIP (supplément, espaces qui le proposent)',
  `quantite`         INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Nombre d''unités réservées (ex: 3 chambres du même type en une seule demande)',
  `statut`           ENUM('en_attente','validee','refusee','annulee','expiree','requisitionnee') NOT NULL DEFAULT 'en_attente'
                     COMMENT 'expiree = auto-annulée après 48h sans paiement (distinct d''un refus ou d''une annulation manuelle)',
  `statut_paiement`  ENUM('non_paye','attente_paiement','partiellement_paye','paye') NOT NULL DEFAULT 'non_paye'
                     COMMENT 'Paiement toujours physique : espèces / Mobile Money au guichet, jamais en ligne',
  `date_limite_solde` DATE DEFAULT NULL COMMENT 'Date limite pour régler le solde d''un acompte',
  `paiement_notifie` TINYINT(1) NOT NULL DEFAULT 0,
  `motif`            TEXT DEFAULT NULL,
  `note_admin`       TEXT DEFAULT NULL,
  `notification_vue` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resa_user` (`user_id`),
  KEY `idx_resa_espace` (`espace_id`),
  KEY `idx_resa_date` (`date_resa`),
  KEY `idx_resa_statut` (`statut`),
  KEY `idx_resa_horaires` (`espace_id`,`date_resa`,`heure_debut`,`heure_fin`),
  KEY `fk_reservation_tarif` (`tarif_id`),
  CONSTRAINT `fk_resa_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`),
  CONSTRAINT `fk_resa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_reservation_tarif` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Demandes de réservation, avec détection de conflit sur les créneaux';

--
-- Table `paiements`
-- Encaissements physiques enregistrés par le comptable après validation.
--
DROP TABLE IF EXISTS `paiements`;
CREATE TABLE `paiements` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id`     INT UNSIGNED NOT NULL,
  `montant`            DECIMAL(12,2) NOT NULL COMMENT 'Montant réellement encaissé (après réduction éventuelle)',
  `montant_reference`  DECIMAL(12,2) DEFAULT NULL COMMENT 'Montant attendu selon le tarif, avant toute réduction',
  `motif_reduction`    TEXT DEFAULT NULL COMMENT 'Raison de la réduction accordée, obligatoire si montant < montant_reference',
  `mode`               ENUM('especes','orange_money','moov_money','virement','cheque') NOT NULL
                       COMMENT 'Mobile Money = paiement présentiel via marchand Orange/Moov, sans API en ligne',
  `reference`          VARCHAR(100) DEFAULT NULL,
  `note`               TEXT DEFAULT NULL,
  `enregistre_par`     INT UNSIGNED NOT NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_paiement_resa` (`reservation_id`),
  KEY `idx_paiement_user` (`enregistre_par`),
  CONSTRAINT `fk_paiement_resa` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_paiement_user` FOREIGN KEY (`enregistre_par`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Historique des encaissements physiques (guichet)';

-- =====================================================================
--  SECTION 5 — COMMUNICATION
-- =====================================================================

--
-- Table `messages`
-- Messages reçus via le formulaire de contact public.
--
--
-- Table `demandes_bail`
-- Demande de prise en bail d'un espace (en ligne ou saisie au guichet).
-- Distincte des messages généraux — traitée par admin_espaces.
--
DROP TABLE IF EXISTS `demandes_bail`;
CREATE TABLE `demandes_bail` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`                  VARCHAR(150) NOT NULL,
  `prenom`               VARCHAR(150) DEFAULT NULL,
  `telephone`            VARCHAR(30) NOT NULL,
  `email`                VARCHAR(190) DEFAULT NULL,
  `espace_id`            INT UNSIGNED NOT NULL,
  `usage_prevu`          TEXT DEFAULT NULL,
  `duree_souhaitee`      ENUM('mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'mensuel',
  `date_debut_souhaitee` DATE DEFAULT NULL,
  `message`              TEXT DEFAULT NULL,
  `statut`               ENUM('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
  `canal`                ENUM('en_ligne','guichet') NOT NULL DEFAULT 'en_ligne',
  `enregistre_par`       INT UNSIGNED DEFAULT NULL COMMENT 'Admin qui a saisi la demande, si prise au guichet',
  `traite_par`           INT UNSIGNED DEFAULT NULL,
  `client_user_id`       INT UNSIGNED DEFAULT NULL COMMENT 'Compte client créé/lié automatiquement à l''acceptation',
  `date_traitement`      TIMESTAMP NULL DEFAULT NULL,
  `note_traitement`      TEXT DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_demande_bail_espace` (`espace_id`),
  CONSTRAINT `fk_demande_bail_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Demandes de prise en bail d''un espace, en ligne ou au guichet';

--
-- Table `demande_bail_espaces`
-- Espaces supplémentaires demandés dans une même demande groupée (bail
-- couvrant plusieurs espaces d'un même bâtiment, en plus de l'espace
-- principal déjà stocké sur demandes_bail.espace_id)
--
DROP TABLE IF EXISTS `demande_bail_espaces`;
CREATE TABLE `demande_bail_espaces` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `demande_id` INT UNSIGNED NOT NULL,
  `espace_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_dbe_demande` (`demande_id`),
  KEY `fk_dbe_espace` (`espace_id`),
  CONSTRAINT `fk_dbe_demande` FOREIGN KEY (`demande_id`) REFERENCES `demandes_bail` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dbe_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Espaces supplémentaires inclus dans une demande de bail groupée';

--
-- Table `messages`
--
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(150) NOT NULL,
  `email`       VARCHAR(190) NOT NULL,
  `telephone`   VARCHAR(30) DEFAULT NULL,
  `sujet`       ENUM('reservation_espace','activite','information_generale','reclamation','autre')
                NOT NULL DEFAULT 'information_generale',
  `espace_id`   INT UNSIGNED DEFAULT NULL,
  `activite_id` INT UNSIGNED DEFAULT NULL,
  `message`     TEXT NOT NULL,
  `reponse`     TEXT DEFAULT NULL COMMENT 'Réponse de l''admin messages',
  `repondu_par` INT UNSIGNED DEFAULT NULL,
  `repondu_at`  TIMESTAMP NULL DEFAULT NULL,
  `envoye_email` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'La réponse a été envoyée par email',
  `envoye_site`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'La réponse est visible sur le compte du client (si un compte correspond à cet email)',
  `lu`          TINYINT(1) NOT NULL DEFAULT 0,
  `lu_at`       TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_sujet` (`sujet`),
  KEY `idx_messages_lu` (`lu`),
  KEY `fk_messages_espace` (`espace_id`),
  KEY `fk_messages_activite` (`activite_id`),
  CONSTRAINT `fk_messages_activite` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Messages du formulaire de contact public';

--
-- Table `notifications`
-- Notifications internes (cloche) adressées à un rôle ou un utilisateur.
--
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `destinataire_role` VARCHAR(30) NOT NULL COMMENT 'Rôle ciblé ; NULL implicite = tous les membres du rôle',
  `destinataire_id`   INT UNSIGNED DEFAULT NULL,
  `type`              VARCHAR(60) NOT NULL,
  `message`           TEXT NOT NULL,
  `lien`              VARCHAR(200) DEFAULT NULL,
  `lu`                TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_role` (`destinataire_role`),
  KEY `idx_notif_lu` (`lu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Notifications internes du back-office';

-- =====================================================================
--  SECTION 6 — GOUVERNANCE & TRAÇABILITÉ
-- =====================================================================

--
-- Table `observations`
-- Notes internes de suivi, rédigées par tout profil d'administration
-- sur une réservation, un paiement, une activité ou un espace.
--
DROP TABLE IF EXISTS `observations`;
CREATE TABLE `observations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `auteur_id`  INT UNSIGNED NOT NULL,
  `cible_type` ENUM('reservation','paiement','activite','espace') NOT NULL,
  `cible_id`   INT UNSIGNED NOT NULL,
  `contenu`    TEXT NOT NULL,
  `parent_id`  INT UNSIGNED DEFAULT NULL COMMENT 'Si renseigné, ceci est une réponse à cette observation',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_obs_cible` (`cible_type`,`cible_id`),
  KEY `idx_obs_auteur` (`auteur_id`),
  KEY `idx_obs_parent` (`parent_id`),
  CONSTRAINT `fk_obs_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_obs_parent` FOREIGN KEY (`parent_id`) REFERENCES `observations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Notes de suivi rédigées par les profils d''administration';

--
-- Table `activity_log`
-- Journal d'audit des actions effectuées dans le back-office.
--
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `user_nom`   VARCHAR(150) NOT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `module`     ENUM('espaces','activites','reservations','messages','users') NOT NULL,
  `details`    TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_module` (`module`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Journal d''audit des actions d''administration';

-- =====================================================================
--  DONNÉES DE DÉMARRAGE (DONNÉES OFFICIELLES UNIQUEMENT)
-- =====================================================================

--
-- Comptes d'administration — un compte par rôle, mot de passe par défaut
-- à modifier obligatoirement à la première connexion.
--
-- Mot de passe par défaut pour tous les comptes ci-dessous : ChangeMoi@2026
--
INSERT INTO `users` (`nom_complet`, `email`, `telephone`, `password_hash`, `role`, `actif`) VALUES
('Administration Système',  'superadmin@palaisdespionniers.ml',      NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin',      1),
('Direction Générale',      'dg@palaisdespionniers.ml',               NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'superadmin',        1),
('Cabinet du Ministre',     'ministre@palaisdespionniers.ml',        NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'ministre',        1),
('Administration Espaces',  'admin.espaces@palaisdespionniers.ml',   NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_espaces',   1),
('Administration Activités','admin.activites@palaisdespionniers.ml', NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_activites', 1),
('Administration Messages', 'admin.messages@palaisdespionniers.ml',  NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_messages',  1),
('Service Comptable',       'comptable@palaisdespionniers.ml',       NULL, '$2y$10$X2oLmY342067eF7fbwEDRuFAJ24vMgPo2nHvr68ofIRLkrlyPyhJW', 'admin_comptable', 1);

--
-- Catégories officielles d'espaces
--
INSERT INTO `categories` (`id`, `nom`, `slug`) VALUES
(1, 'Amphithéâtre',        'amphitheatre'),
(2, 'Salle de conférence', 'conference'),
(3, 'Sport',               'sport'),
(4, 'Hébergement',         'hebergement'),
(5, 'Loisir',              'loisir'),
(6, 'Restauration',        'restauration');

--
-- Espaces officiels du Palais
--
INSERT INTO `espaces` (`id`, `nom`, `slug`, `categorie_id`, `capacite`, `description`, `mode_reservation`, `option_petit_dejeuner`, `option_vip`, `prix_vip`, `disponible`) VALUES
(1, 'Salle Seydou BADIAN', 'salle-seydou-badian', 1, '400 places',
   'Grand amphithéâtre du Palais, taillé pour les cérémonies officielles, conférences de grande envergure et rassemblements institutionnels. Un accueil VIP est proposé en option pour les personnalités et délégations.',
   'creneau', 0, 1, 150000.00, 1),
(2, 'Salle Sory Ibrahim KOITA dit Bomba', 'salle-sory-ibrahim-koita-dit-bomba', 1, '420 places',
   'Deuxième grand amphithéâtre du Palais, adapté aux mêmes usages que la Salle Seydou Badian : conférences, assemblées, cérémonies et grands événements institutionnels ou culturels.',
   'creneau', 0, 0, NULL, 1),
(3, 'Terrain de Maracana', 'terrain-de-maracana', 3, 'Football',
   'Terrain de football extérieur du Palais, ouvert aux séances d''entraînement individuelles ou de club, ainsi qu''aux matchs de gala et compétitions amicales.',
   'creneau', 0, 0, NULL, 1),
(4, 'Centre d''Accueil Tidiani COULIBALY dit Necker', 'centre-tidiani-coulibaly-dit-necker', 4, '27 chambres',
   'Hébergement économique du Palais, pensé pour l''accueil de groupes, stagiaires et délégations en séjour à Bamako. 27 chambres réparties en deux formules : chambres ventilées, et chambres avec douche intérieure.',
   'sejour', 0, 0, NULL, 1),
(5, 'Piscine', 'piscine', 5, 'Événementiel',
   'Piscine du Palais, ouverte aux cours de natation (enfants et adultes), aux entrées journalières, et à la location pour événements privés ou dînatoires.',
   'creneau', 0, 0, NULL, 1),
(6, 'Terrain de Basketball', 'terrain-de-basketball', 3, 'Équipes de club',
   'Terrain de basketball extérieur, disponible pour les séances d''entraînement de club et les matchs de gala.',
   'creneau', 0, 0, NULL, 1),
(7, 'Salle de Gym Daba Modibo KEITA', 'salle-de-gym-daba-modibo-keita', 3, 'Variable',
   'Salle de gymnastique du complexe Daba Modibo Keita, avec une salle multifonction pouvant accueillir d''autres disciplines et événements.',
   'creneau', 0, 0, NULL, 1),
(8, 'Résidence Ely Abdoulaye DIALLO', 'residence-ely-abdoulaye-diallo', 4, '26 chambres',
   'Résidence haut de gamme du Palais : 26 chambres climatisées, entièrement équipées (téléviseur, douche intérieure), avec option petit-déjeuner. Un cadre confortable pour séjours officiels, missions ou délégations.',
   'sejour', 1, 0, NULL, 1),
(9, 'Salle de Séminaire', 'salle-de-seminaire', 2, 'Séminaires & réunions',
   'Salle de travail au sein de la Résidence Ely Abdoulaye Diallo, adaptée aux séminaires, formations et réunions de délégation en séjour sur place.',
   'creneau', 0, 0, NULL, 1),
(12, 'Salles de Classe', 'salles-de-classe', 4, '6 salles',
   'Six salles de classe identiques au sein de la Résidence Ely Abdoulaye Diallo, louables à l''unité pour des formations, ateliers ou sessions pédagogiques.',
   'creneau', 0, 0, NULL, 1),
(13, 'Cour Événementielle', 'cour-evenementielle', 5, 'Événementiel',
   'Cour extérieure de la Résidence Ely Abdoulaye Diallo, pour l''organisation d''événements en plein air : cérémonies, réceptions, activités de groupe.',
   'creneau', 0, 0, NULL, 1),
(14, 'Salle Adama SAMASSEKOU', 'salle-adama-samassekou', 2, '200 places',
   'Amphithéâtre de taille moyenne, idéal pour les conférences, assemblées générales et cérémonies de moyenne envergure.',
   'creneau', 0, 0, NULL, 1),
(15, 'Salle de conférence Pr Assétou Founé SAMAKE MIGAN', 'salle-pr-assetou-founé-samake-migan', 2, '80 places',
   'Salle de conférence à taille humaine, adaptée aux réunions de travail, formations et ateliers nécessitant un cadre plus intimiste qu''un grand amphithéâtre.',
   'creneau', 0, 0, NULL, 1),
(16, 'Terrain de Handball', 'terrain-de-handball', 3, 'Équipes de club',
   'Terrain de handball extérieur, distinct du terrain de basketball, disponible pour l''entraînement de club et les matchs de gala.',
   'creneau', 0, 0, NULL, 1),
(17, 'Salle Informatique', 'salle-informatique', 2, 'Postes informatiques',
   'Salle équipée de postes informatiques, dédiée aux formations numériques, à l''initiation à l''outil informatique et aux ateliers de bureautique.',
   'creneau', 0, 0, NULL, 1),
(18, 'Salle de Taekwondo Daba Modibo KEITA', 'salle-de-taekwondo-daba-modibo-keita', 3, 'Variable',
   'Salle dédiée à la pratique du taekwondo au sein du complexe Daba Modibo Keita.',
   'creneau', 0, 0, NULL, 1),
(19, 'Salle de Karaté Daba Modibo KEITA', 'salle-de-karate-daba-modibo-keita', 3, 'Variable',
   'Salle dédiée à la pratique du karaté au sein du complexe Daba Modibo Keita.',
   'creneau', 0, 0, NULL, 1);

--
-- Grille tarifaire officielle
-- NB : les tarifs 'est_bail' = 1 sont des locations longue durée négociées
-- directement (jamais réservables en ligne). Les tarifs avec
-- 'quantite_disponible' représentent un inventaire d'unités identiques
-- (chambres, salles de classe) : le système ne refuse une nouvelle
-- réservation que lorsque toutes les unités sont prises sur la période.
--
-- Espaces gérés en bail par un tiers (le Palais n'y prend pas de réservation
-- Ces espaces proposent une option de location en bail (voir table tarifs,
-- est_bail=1) mais ne sont pas encore effectivement loués — ils apparaissent
-- donc comme "Bail possible" tant qu'aucun gestionnaire réel n'est renseigné
-- depuis Admin > Espaces.

-- Les 3 salles du bâtiment Daba Modibo Keita peuvent être prises en bail
-- individuellement, ou ensemble (bail groupé de tout le bâtiment).
UPDATE `espaces` SET `groupe_batiment` = 'Daba Modibo Keita'
WHERE `slug` IN ('salle-de-gym-daba-modibo-keita','salle-de-taekwondo-daba-modibo-keita','salle-de-karate-daba-modibo-keita');

INSERT INTO `tarifs` (`espace_id`, `libelle`, `montant`, `unite`, `quantite_disponible`, `est_bail`) VALUES
-- Salle Seydou Badian
(1, 'Location Journée complète',             400000.00, 'jour',   NULL, 0),
-- Salle Sory Ibrahim Koita dit Bomba
(2, 'Location Journée complète',             400000.00, 'jour',   NULL, 0),
-- Terrain de Maracana
(3, 'Séance d\'entraînement',                 10000.00, 'heure',  NULL, 0),
(3, 'Match de Gala',                          20000.00, 'match',  NULL, 0),
(3, 'Location en bail',                      500000.00, 'mois',   NULL, 1),
-- Centre Tidiani Coulibaly dit Necker (27 chambres)
(4, 'Chambre avec douche intérieure',         10000.00, 'nuitée', 7,    0),
(4, 'Chambre ventilée sans douche',            5000.00, 'nuitée', 20,   0),
-- Piscine
(5, 'Location en PPP',                       750000.00, 'mois',   NULL, 1),
(5, 'Location pour dîner ou déjeuner',       200000.00, 'jour',   NULL, 0),
(5, 'Fédération Aquatique du Mali',           50000.00, 'activite', NULL, 0),
(5, 'Redevance support publicitaire',         25000.00, 'support', NULL, 0),
(5, 'Natation enfant',                         1500.00, 'jour',   NULL, 0),
(5, 'Natation adulte',                         3000.00, 'jour',   NULL, 0),
(5, 'Inscription natation (mensualité)',      10000.00, 'personne_mois', NULL, 0),
(5, 'Location en bail',                      750000.00, 'mois',   NULL, 1),
-- Terrain de Basketball
(6, 'Location terrain de basket (mensuel)',  150000.00, 'mois',   NULL, 1),
(6, 'Séance d''entraînement',                 10000.00, 'seance', NULL, 0),
(6, 'Match de Gala',                          50000.00, 'match',  NULL, 0),
(6, 'Location en bail',                      300000.00, 'mois',   NULL, 1),
-- Salle de Gym Daba Modibo Keita
(7, 'Location salle de gymnastique (PPP)',   500000.00, 'mois',   NULL, 1),
(7, 'Salle multifonction',                   200000.00, 'mois',   NULL, 1),
-- Résidence Ely Abdoulaye Diallo (26 chambres — petit-déjeuner en option lors de la réservation, +5 000 F CFA/nuit)
(8, 'Chambre climatisée (douche intérieure, TV)', 25000.00, 'nuitée', 26, 0),
(8, 'Location en bail de la résidence entière', 10000000.00, 'mois', NULL, 1),
-- Salle de Séminaire (Résidence Ely)
(9, 'Location journée',                      100000.00, 'jour',   NULL, 0),
-- Salles de classe (Résidence Ely — 6 salles identiques)
(12, 'Location salle de classe',              25000.00, 'jour',   6,    0),
-- Cour événementielle (Résidence Ely)
(13, 'Événement dans la cour',                200000.00, 'jour',  NULL, 0),
-- Salle Adama Samassekou
(14, 'Location journée',                     150000.00, 'jour',   NULL, 0),
-- Salle Pr Assétou Founé Samake Migan
(15, 'Location journée',                     150000.00, 'jour',   NULL, 0),
-- Terrain de Handball
(16, 'Séance d''entraînement',                10000.00, 'heure',  NULL, 0),
(16, 'Club (abonnement mensuel)',             30000.00, 'mois',   NULL, 0),
(16, 'Match de Gala',                         50000.00, 'match',  NULL, 0),
(16, 'Location en bail',                     300000.00, 'mois',   NULL, 1),
-- Salle Informatique
(17, 'Location journée',                      50000.00, 'jour',   NULL, 0),
-- Salle de Taekwondo Daba Modibo Keita
(18, 'Location salle (PPP)',                 250000.00, 'mois',   NULL, 1),
(18, 'Inscription annuelle',                  50000.00, 'personne_an', NULL, 0),
(18, 'Mensualité',                            15000.00, 'personne_mois', NULL, 0),
(18, 'Location en bail',                     500000.00, 'mois',   NULL, 1),
-- Salle de Karaté Daba Modibo Keita
(19, 'Location salle (PPP)',                 250000.00, 'mois',   NULL, 1),
(19, 'Inscription annuelle',                  50000.00, 'personne_an', NULL, 0),
(19, 'Mensualité',                            15000.00, 'personne_mois', NULL, 0),
(19, 'Location en bail',                     500000.00, 'mois',   NULL, 1);

--
-- Services annexes (informationnels — contact direct, non réservables)
--
INSERT INTO `services_annexes` (`nom`, `description`, `montant`, `unite`) VALUES
('Support publicitaire au sein du Palais des Pionniers',
 'Affichage publicitaire dans les espaces du Palais — visibilité auprès du public fréquentant les activités sportives et culturelles.',
 100000.00, 'mois'),
('Espace lavage auto et moto',
 'Service de lavage automobile et deux-roues disponible au sein du Palais des Pionniers.',
 100000.00, 'mois');

--
-- Activités officielles
--
INSERT INTO `activites` (`id`, `nom`, `slug`, `sous_titre`) VALUES
(1, 'L\'École de la Citoyenneté', 'l-ecole-de-la-citoyennete', 'Forger le maliden koura');

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
