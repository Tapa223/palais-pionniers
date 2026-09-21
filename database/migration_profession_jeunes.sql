-- =====================================================================
--  MIGRATION — Profession des jeunes engagés
--  À exécuter une seule fois.
--  Généré le 10/08/2026
-- =====================================================================

ALTER TABLE `jeunes_engages`
  ADD COLUMN IF NOT EXISTS `profession` VARCHAR(150) DEFAULT NULL
  COMMENT 'Étudiant, travailleur, sans emploi...'
  AFTER `commune`;
