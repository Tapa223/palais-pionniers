-- =====================================================================
--  MIGRATION — Base des jeunes engagés (bouton "S'engager")
--  À exécuter une seule fois.
--  Généré le 07/08/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `jeunes_engages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`             VARCHAR(150) NOT NULL,
  `prenom`          VARCHAR(150) DEFAULT NULL,
  `age`             TINYINT UNSIGNED DEFAULT NULL,
  `telephone`       VARCHAR(30) NOT NULL,
  `email`           VARCHAR(190) DEFAULT NULL,
  `commune`         VARCHAR(150) DEFAULT NULL,
  `domaine_interet` VARCHAR(150) DEFAULT NULL,
  `motivation`      TEXT DEFAULT NULL,
  `statut`          ENUM('nouveau','contacte') NOT NULL DEFAULT 'nouveau',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Base des jeunes intéressés à s''engager auprès du Palais';
