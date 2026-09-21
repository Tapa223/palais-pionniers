-- =====================================================================
--  MIGRATION — Galerie photo des formations (fiche détail enrichie)
--  À exécuter une seule fois.
--  Généré le 31/07/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `formation_galerie` (
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
