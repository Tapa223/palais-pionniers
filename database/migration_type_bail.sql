-- =====================================================================
--  MIGRATION — Type de bail (périodicité du contrat)
--  À exécuter une seule fois.
--  Généré le 29/07/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `type_bail` ENUM('mensuel','trimestriel','semestriel','annuel') NOT NULL DEFAULT 'mensuel'
  COMMENT 'Périodicité du contrat de bail — détermine la fréquence attendue des paiements'
  AFTER `gerant_email`;
