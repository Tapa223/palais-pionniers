-- =====================================================================
--  MIGRATION PHASE 3 — Expiration automatique (48h), notifications
--  client, accès comptable au reçu, profil client éditable
--  À exécuter après migration_phase2_hebergement.sql
--  Généré le 27/07/2026
-- =====================================================================

ALTER TABLE `reservations`
  ADD COLUMN `date_validation` DATETIME DEFAULT NULL
  COMMENT 'Horodatage de la validation par admin_espaces — sert au calcul du délai de 48h avant expiration automatique'
  AFTER `date_depart`;

-- Initialise date_validation pour les réservations déjà validées (approximation
-- avec updated_at, faute d'historique précis avant cette migration)
UPDATE `reservations` SET `date_validation` = COALESCE(`updated_at`, `created_at`)
WHERE `statut` = 'validee' AND `date_validation` IS NULL;
