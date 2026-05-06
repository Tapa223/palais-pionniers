-- =====================================================================
--  PALAIS DES PIONNIERS — Base de données MySQL
--  Compatible MySQL 5.7+ / MariaDB 10.3+ (XAMPP / WAMP)
--  Encodage : utf8mb4 (support complet UTF-8 + emojis)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `palais_pionniers`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `palais_pionniers`;

-- ---------------------------------------------------------------------
--  1. Utilisateurs
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `tarifs`;
DROP TABLE IF EXISTS `espaces`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `activites`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom_complet`   VARCHAR(150)   NOT NULL,
  `email`         VARCHAR(190)   NOT NULL UNIQUE,
  `telephone`     VARCHAR(30)    DEFAULT NULL,
  `password_hash` VARCHAR(255)   NOT NULL,
  `role`          ENUM('user','admin') NOT NULL DEFAULT 'user',
  `actif`         TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  2. Catégories d'espaces
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`  VARCHAR(60)  NOT NULL UNIQUE,
  `nom`   VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `categories` (`slug`,`nom`) VALUES
  ('amphitheatre','Amphithéâtre'),
  ('conference','Salle de conférence'),
  ('sport','Sport'),
  ('hebergement','Hébergement'),
  ('loisir','Loisir');

-- ---------------------------------------------------------------------
--  3. Espaces du Palais
-- ---------------------------------------------------------------------
CREATE TABLE `espaces` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`           VARCHAR(120) NOT NULL UNIQUE,
  `nom`            VARCHAR(150) NOT NULL,
  `categorie_id`   INT UNSIGNED NOT NULL,
  `description`    TEXT         NOT NULL,
  `capacite`       VARCHAR(100) NOT NULL,
  `equipements`    TEXT         DEFAULT NULL,
  `image`          VARCHAR(255) DEFAULT NULL,
  `disponible`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_espaces_cat` (`categorie_id`),
  CONSTRAINT `fk_espaces_categorie`
    FOREIGN KEY (`categorie_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  4. Tarifs (un espace peut avoir plusieurs lignes de tarif)
-- ---------------------------------------------------------------------
CREATE TABLE `tarifs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `espace_id`  INT UNSIGNED NOT NULL,
  `libelle`    VARCHAR(120) NOT NULL,
  `montant`    INT UNSIGNED NOT NULL COMMENT 'Montant en F CFA',
  `unite`      VARCHAR(40)  NOT NULL DEFAULT 'jour',
  PRIMARY KEY (`id`),
  KEY `idx_tarifs_espace` (`espace_id`),
  CONSTRAINT `fk_tarifs_espace`
    FOREIGN KEY (`espace_id`) REFERENCES `espaces`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  5. Activités sportives / culturelles
-- ---------------------------------------------------------------------
CREATE TABLE `activites` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(120) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `icone`       VARCHAR(10)  DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `activites` (`nom`,`description`,`icone`) VALUES
  ('Football',   'Entraînements et compétitions toute l''année.', '⚽'),
  ('Taekwondo',  'Arts martiaux pour tous les niveaux.',         '🥋'),
  ('Basketball', 'Initiation et perfectionnement.',              '🏀'),
  ('Natation',   'Cours encadrés par des moniteurs diplômés.',   '🏊'),
  ('Théâtre',    'Ateliers d''expression et de mise en scène.',  '🎭'),
  ('Musique',    'Découverte instrumentale et chorale.',         '🎵');

-- ---------------------------------------------------------------------
--  6. Réservations
--     Statuts : en_attente | validee | refusee | annulee
--     Index unique partiel simulé via contrôle applicatif (voir API).
-- ---------------------------------------------------------------------
CREATE TABLE `reservations` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `espace_id`     INT UNSIGNED NOT NULL,
  `date_resa`     DATE         NOT NULL,
  `heure_debut`   TIME         NOT NULL,
  `heure_fin`     TIME         NOT NULL,
  `motif`         TEXT         NOT NULL,
  `statut`        ENUM('en_attente','validee','refusee','annulee')
                  NOT NULL DEFAULT 'en_attente',
  `note_admin`    TEXT         DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resa_user`   (`user_id`),
  KEY `idx_resa_espace` (`espace_id`),
  KEY `idx_resa_date`   (`date_resa`),
  KEY `idx_resa_statut` (`statut`),
  CONSTRAINT `fk_resa_user`
    FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_resa_espace`
    FOREIGN KEY (`espace_id`) REFERENCES `espaces`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_horaires` CHECK (`heure_fin` > `heure_debut`)
) ENGINE=InnoDB;

-- =====================================================================
--  DONNÉES OFFICIELLES — Espaces & Tarifs
-- =====================================================================

-- Amphithéâtres
INSERT INTO `espaces`
  (`slug`,`nom`,`categorie_id`,`description`,`capacite`,`equipements`,`disponible`)
VALUES
  ('salle-seydou-badian',
   'Salle Seydou BADIAN',
   (SELECT id FROM categories WHERE slug='amphitheatre'),
   'Amphithéâtre principal du Palais des Pionniers, idéal pour conférences nationales, cérémonies institutionnelles et grands événements culturels.',
   '400 places',
   'Sonorisation, Vidéoprojecteur, Scène, Climatisation, Loges',
   1),
  ('salle-sony-bounian-koita',
   'Salle Sony Bounian KOITA',
   (SELECT id FROM categories WHERE slug='amphitheatre'),
   'Espace modulable parfait pour spectacles, projections, séminaires et grandes assemblées.',
   '420 places',
   'Sonorisation, Éclairage scénique, Vidéoprojecteur, Scène',
   1),
  ('salle-amadou-seydou-traore',
   'Salle Pr Amadou Seydou TRAORÉ',
   (SELECT id FROM categories WHERE slug='conference'),
   'Salle moderne dédiée aux formations, ateliers, réunions et conférences à audience restreinte.',
   '80 places',
   'Vidéoprojecteur, Sonorisation, Tables modulables, Wi-Fi',
   1),
  ('terrain-maracana',
   'Terrain de Maracana',
   (SELECT id FROM categories WHERE slug='sport'),
   'Terrain dédié au football, accueillant entraînements quotidiens, matchs amicaux et tournois de gala.',
   '22 joueurs',
   'Vestiaires, Buts homologués, Éclairage',
   1),
  ('centre-tidiani-coulibaly',
   'Centre Tidiani COULIBALY',
   (SELECT id FROM categories WHERE slug='hebergement'),
   'Centre d''hébergement adapté aux délégations, stagiaires et participants aux activités du Palais.',
   'Multiples chambres',
   'Lits équipés, Sanitaires, Espaces communs',
   1),
  ('piscine',
   'Piscine',
   (SELECT id FROM categories WHERE slug='loisir'),
   'Location de la piscine pour événements festifs, anniversaires et activités aquatiques organisées.',
   'Événement complet',
   'Bassin, Vestiaires, Espace détente',
   1);

-- Tarifs officiels
INSERT INTO `tarifs` (`espace_id`,`libelle`,`montant`,`unite`) VALUES
  ((SELECT id FROM espaces WHERE slug='salle-seydou-badian'),       'Journée',              400000, 'jour'),
  ((SELECT id FROM espaces WHERE slug='salle-sony-bounian-koita'),  'Journée',              400000, 'jour'),
  ((SELECT id FROM espaces WHERE slug='salle-amadou-seydou-traore'),'Journée',              150000, 'jour'),
  ((SELECT id FROM espaces WHERE slug='terrain-maracana'),          'Heure d''entraînement', 10000, 'heure'),
  ((SELECT id FROM espaces WHERE slug='terrain-maracana'),          'Match gala',            20000, 'match'),
  ((SELECT id FROM espaces WHERE slug='centre-tidiani-coulibaly'),  'Nuitée standard',        5000, 'nuit'),
  ((SELECT id FROM espaces WHERE slug='centre-tidiani-coulibaly'),  'Nuitée confort',        10000, 'nuit'),
  ((SELECT id FROM espaces WHERE slug='piscine'),                   'Journée événement',    250000, 'jour');

-- =====================================================================
--  COMPTE ADMINISTRATEUR PAR DÉFAUT
--  Email      : admin@palaisdespionniers.ml
--  Mot passe  : Admin@Palais2026!
--  ⚠ Changez ce mot de passe en production !
-- =====================================================================
INSERT INTO `users` (`nom_complet`,`email`,`password_hash`,`role`) VALUES
  ('Administrateur Palais',
   'admin@palaisdespionniers.ml',
   '$2b$10$NVW.VDw/e5qWPuM//9yN2OIjE6Bvz/5tWNwqToAQL/N/THe17rsmy',
   'admin');
