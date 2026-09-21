-- =====================================================================
--  MIGRATION — Demande de résiliation de bail (côté client)
--  Le client demande, l'admin espaces confirme (via "Terminer le bail").
--  À exécuter une seule fois.
--  Généré le 05/08/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `resiliation_demandee` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gerant_user_id`,
  ADD COLUMN IF NOT EXISTS `resiliation_demandee_le` TIMESTAMP NULL DEFAULT NULL AFTER `resiliation_demandee`,
  ADD COLUMN IF NOT EXISTS `resiliation_note` TEXT DEFAULT NULL COMMENT 'Motif donné par le client à la demande de résiliation' AFTER `resiliation_demandee_le`;
