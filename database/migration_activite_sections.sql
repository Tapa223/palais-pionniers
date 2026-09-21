-- =====================================================================
--  MIGRATION — Sections libres pour les fiches activités
--  Remplace le format rigide "missions" par des blocs facultatifs.
--  Corrige aussi l'absence de la table activite_liens (jamais créée).
--  À exécuter une seule fois.
--  Généré le 10/08/2026
-- =====================================================================

ALTER TABLE `activite_galerie`
  ADD COLUMN IF NOT EXISTS `section_id` INT UNSIGNED DEFAULT NULL AFTER `activite_id`,
  ADD COLUMN IF NOT EXISTS `ordre` INT NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `activite_sections` (
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

CREATE TABLE IF NOT EXISTS `activite_liens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activite_id` INT UNSIGNED NOT NULL,
  `titre`       VARCHAR(150) NOT NULL,
  `url`         VARCHAR(500) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_activite_liens_parent` (`activite_id`),
  CONSTRAINT `fk_activite_liens_parent` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Liens externes complémentaires pour une activité';
