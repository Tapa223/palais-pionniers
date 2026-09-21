-- =====================================================================
--  MIGRATION — Fiche complète du gestionnaire externe (nom, prénom, email)
--  À exécuter une seule fois, après les migrations précédentes.
--  Généré le 29/07/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `gerant_nom` VARCHAR(100) DEFAULT NULL
  COMMENT 'Nom du gestionnaire externe' AFTER `gerant_externe`,
  ADD COLUMN IF NOT EXISTS `gerant_prenom` VARCHAR(100) DEFAULT NULL
  COMMENT 'Prénom du gestionnaire externe' AFTER `gerant_nom`,
  ADD COLUMN IF NOT EXISTS `gerant_email` VARCHAR(150) DEFAULT NULL
  COMMENT 'Email du gestionnaire externe' AFTER `gerant_prenom`;
