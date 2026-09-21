-- =====================================================================
--  MIGRATION — Coordonnées du gestionnaire par tarif en bail
--  À exécuter une seule fois, après les migrations précédentes.
--  Généré le 29/07/2026
-- =====================================================================

ALTER TABLE `tarifs`
  ADD COLUMN IF NOT EXISTS `gerant_nom` VARCHAR(150) DEFAULT NULL
  COMMENT 'Nom du gestionnaire à contacter pour ce tarif en bail' AFTER `est_bail`,
  ADD COLUMN IF NOT EXISTS `gerant_contact` VARCHAR(100) DEFAULT NULL
  COMMENT 'Téléphone/contact du gestionnaire pour ce tarif en bail' AFTER `gerant_nom`;
