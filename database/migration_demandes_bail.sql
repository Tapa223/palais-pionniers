-- =====================================================================
--  MIGRATION — Demandes de prise en bail (en ligne / guichet)
--  À exécuter une seule fois.
--  Généré le 03/08/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `demandes_bail` (
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
  `date_traitement`      TIMESTAMP NULL DEFAULT NULL,
  `note_traitement`      TEXT DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_demande_bail_espace` (`espace_id`),
  CONSTRAINT `fk_demande_bail_espace` FOREIGN KEY (`espace_id`) REFERENCES `espaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Demandes de prise en bail d''un espace, en ligne ou au guichet';
