-- =====================================================================
--  MIGRATION — Suivi des loyers (bail_paiements), avec périodicité et
--  traçabilité des réductions accordées
--  À exécuter une seule fois, après migration_type_bail.sql
--  Généré le 29/07/2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS `bail_paiements` (
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

-- Si la table existait déjà dans une version antérieure sans les colonnes
-- de réduction, on les ajoute sans tout recréer :
ALTER TABLE `bail_paiements`
  ADD COLUMN IF NOT EXISTS `montant_reference` DECIMAL(12,2) DEFAULT NULL
  COMMENT 'Loyer attendu selon le tarif, avant réduction' AFTER `montant`,
  ADD COLUMN IF NOT EXISTS `motif_reduction` TEXT DEFAULT NULL
  COMMENT 'Raison de la réduction accordée, si montant < montant_reference' AFTER `montant_reference`;
