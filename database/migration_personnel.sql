-- =====================================================================
--  MIGRATION — Table personnel (carrousel équipe sur À propos)
--  À exécuter une seule fois.
--  Généré le 01/08/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `personnel` (
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
