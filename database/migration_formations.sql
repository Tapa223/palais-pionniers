-- =====================================================================
--  MIGRATION — Table formations (Savonnerie, Informatique, etc.)
--  À exécuter une seule fois.
--  Généré le 30/07/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `formations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `duree`       VARCHAR(150) DEFAULT NULL COMMENT 'Ex: 3 mois, 2 semaines...',
  `public_cible`VARCHAR(200) DEFAULT NULL COMMENT 'Ex: Jeunes 15-25 ans, Femmes...',
  `photo`       VARCHAR(255) DEFAULT NULL,
  `contact`     VARCHAR(150) DEFAULT NULL,
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  `ordre`       INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Formations dispensées au sein du Palais';
