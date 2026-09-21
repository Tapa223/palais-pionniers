-- =====================================================================
--  MIGRATION — Réponses aux observations (parent_id)
--  À exécuter une seule fois.
--  Généré le 30/07/2026
-- =====================================================================

ALTER TABLE `observations`
  ADD COLUMN IF NOT EXISTS `parent_id` INT UNSIGNED DEFAULT NULL
  COMMENT 'Si renseigné, ceci est une réponse à cette observation' AFTER `contenu`,
  ADD KEY IF NOT EXISTS `idx_obs_parent` (`parent_id`);

-- Ajoute la contrainte séparément (MariaDB ne permet pas ADD CONSTRAINT IF NOT EXISTS)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'observations' AND CONSTRAINT_NAME = 'fk_obs_parent');
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `observations` ADD CONSTRAINT `fk_obs_parent` FOREIGN KEY (`parent_id`) REFERENCES `observations` (`id`) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
