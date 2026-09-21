-- =====================================================================
--  MIGRATION — Lien compte client pour les baux (onglet "Mes Baux")
--  À exécuter une seule fois.
--  Généré le 03/08/2026
-- =====================================================================

ALTER TABLE `espaces`
  ADD COLUMN IF NOT EXISTS `gerant_user_id` INT UNSIGNED DEFAULT NULL
  COMMENT 'Compte client lié au bail, pour l''onglet "Mes Baux"'
  AFTER `gerant_contact`;

ALTER TABLE `demandes_bail`
  ADD COLUMN IF NOT EXISTS `client_user_id` INT UNSIGNED DEFAULT NULL
  COMMENT 'Compte client créé/lié automatiquement à l''acceptation'
  AFTER `traite_par`;
