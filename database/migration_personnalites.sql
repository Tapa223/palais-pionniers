-- =====================================================================
--  MIGRATION — Table personnalites (figures dont les salles portent le nom)
--  À exécuter une seule fois.
--  Généré le 29/07/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `personnalites` (
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
