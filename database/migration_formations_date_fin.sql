-- =====================================================================
--  MIGRATION — Date de fin des formations (statut "Terminée" sans les cacher)
--  À exécuter une seule fois, après migration_formations.sql.
--  Généré le 30/07/2026
-- =====================================================================

ALTER TABLE `formations`
  ADD COLUMN IF NOT EXISTS `date_fin` DATE DEFAULT NULL
  COMMENT 'Si dépassée, la formation reste visible mais affiche un badge Terminée'
  AFTER `contact`;
