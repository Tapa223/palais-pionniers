-- =====================================================================
--  MIGRATION — Réponse aux messages clients (site + email)
--  À exécuter une seule fois.
--  Généré le 31/07/2026
-- =====================================================================

ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `reponse` TEXT DEFAULT NULL COMMENT 'Réponse de l''admin messages' AFTER `message`,
  ADD COLUMN IF NOT EXISTS `repondu_par` INT UNSIGNED DEFAULT NULL AFTER `reponse`,
  ADD COLUMN IF NOT EXISTS `repondu_at` TIMESTAMP NULL DEFAULT NULL AFTER `repondu_par`,
  ADD COLUMN IF NOT EXISTS `envoye_email` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'La réponse a été envoyée par email' AFTER `repondu_at`,
  ADD COLUMN IF NOT EXISTS `envoye_site` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'La réponse est visible sur le compte du client' AFTER `envoye_email`;
